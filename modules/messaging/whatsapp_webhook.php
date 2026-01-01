<?php
/**
 * WhatsApp Webhook Handler
 * Receives webhooks from WhatsApp Business API
 * Handles delivery receipts and incoming messages
 */

require_once '../../config.php';

// Verify webhook (GET request)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    // Get verify token from settings
    $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'whatsapp_webhook_verify_token'");
    $verifyToken = $stmt->fetchColumn();

    if ($mode === 'subscribe' && $token === $verifyToken) {
        echo $challenge;
        exit;
    } else {
        http_response_code(403);
        exit;
    }
}

// Process webhook (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Log webhook for debugging
    error_log('WhatsApp Webhook: ' . $input);

    try {
        // Meta Cloud API webhook format
        if (isset($data['entry'][0]['changes'][0]['value'])) {
            $value = $data['entry'][0]['changes'][0]['value'];

            // Handle status updates (delivery receipts)
            if (isset($value['statuses'])) {
                foreach ($value['statuses'] as $status) {
                    handleStatusUpdate($pdo, $status);
                }
            }

            // Handle incoming messages
            if (isset($value['messages'])) {
                foreach ($value['messages'] as $message) {
                    handleIncomingMessage($pdo, $message, $value['metadata'] ?? []);
                }
            }
        }

        // Twilio webhook format
        if (isset($data['MessageSid'])) {
            handleTwilioWebhook($pdo, $data);
        }

        http_response_code(200);
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        error_log('WhatsApp Webhook Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

    exit;
}

/**
 * Handle status update (delivery receipt)
 */
function handleStatusUpdate($pdo, $status)
{
    $messageId = $status['id'];
    $statusValue = $status['status']; // sent, delivered, read, failed
    $timestamp = $status['timestamp'] ?? time();

    // Update message status in queue
    $newStatus = match ($statusValue) {
        'sent' => 'sent',
        'delivered' => 'delivered',
        'read' => 'delivered',
        'failed' => 'failed',
        default => 'sent'
    };

    $stmt = $pdo->prepare("
        UPDATE messaging_queue 
        SET status = ?, 
            delivered_at = ?,
            error_message = ?
        WHERE gateway_message_id = ?
    ");

    $stmt->execute([
        $newStatus,
        $statusValue === 'delivered' || $statusValue === 'read' ? date('Y-m-d H:i:s', $timestamp) : null,
        $status['errors'][0]['message'] ?? null,
        $messageId
    ]);
}

/**
 * Handle incoming message
 */
function handleIncomingMessage($pdo, $message, $metadata)
{
    $messageId = $message['id'];
    $from = $message['from'];
    $to = $metadata['phone_number_id'] ?? '';
    $timestamp = $message['timestamp'] ?? time();

    // Determine message type and content
    $messageType = $message['type'];
    $messageText = '';
    $mediaUrl = null;
    $mediaType = null;
    $buttonPayload = null;

    switch ($messageType) {
        case 'text':
            $messageText = $message['text']['body'];
            break;
        case 'image':
            $mediaUrl = $message['image']['id'];
            $mediaType = 'image';
            $messageText = $message['image']['caption'] ?? '';
            break;
        case 'video':
            $mediaUrl = $message['video']['id'];
            $mediaType = 'video';
            $messageText = $message['video']['caption'] ?? '';
            break;
        case 'document':
            $mediaUrl = $message['document']['id'];
            $mediaType = 'document';
            $messageText = $message['document']['filename'] ?? '';
            break;
        case 'button':
            $buttonPayload = $message['button']['payload'];
            $messageText = $message['button']['text'];
            $messageType = 'button_reply';
            break;
    }

    // Find or create contact
    $stmt = $pdo->prepare("SELECT id FROM messaging_contacts WHERE whatsapp_number = ?");
    $stmt->execute([$from]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);

    $contactId = $contact['id'] ?? null;

    // Store incoming message
    $stmt = $pdo->prepare("
        INSERT INTO whatsapp_incoming_messages (
            whatsapp_message_id, from_number, to_number,
            message_type, message_text, media_url, media_type,
            button_payload, contact_id, received_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $messageId,
        $from,
        $to,
        $messageType,
        $messageText,
        $mediaUrl,
        $mediaType,
        $buttonPayload,
        $contactId,
        date('Y-m-d H:i:s', $timestamp)
    ]);

    // Update or create session
    updateSession($pdo, $from, $contactId);
}

/**
 * Handle Twilio webhook
 */
function handleTwilioWebhook($pdo, $data)
{
    $messageId = $data['MessageSid'];
    $status = $data['MessageStatus']; // queued, sent, delivered, failed

    $newStatus = match ($status) {
        'sent', 'delivered' => 'delivered',
        'failed', 'undelivered' => 'failed',
        default => 'sent'
    };

    $stmt = $pdo->prepare("
        UPDATE messaging_queue 
        SET status = ?,
            delivered_at = ?
        WHERE gateway_message_id = ?
    ");

    $stmt->execute([
        $newStatus,
        $status === 'delivered' ? date('Y-m-d H:i:s') : null,
        $messageId
    ]);
}

/**
 * Update WhatsApp session
 */
function updateSession($pdo, $whatsappNumber, $contactId)
{
    // Check for existing active session
    $stmt = $pdo->prepare("
        SELECT id FROM whatsapp_sessions 
        WHERE whatsapp_number = ? AND session_status = 'active'
    ");
    $stmt->execute([$whatsappNumber]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24 hours

    if ($session) {
        // Update existing session
        $stmt = $pdo->prepare("
            UPDATE whatsapp_sessions 
            SET last_message_at = NOW(), expires_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$expiresAt, $session['id']]);
    } else {
        // Create new session
        $stmt = $pdo->prepare("
            INSERT INTO whatsapp_sessions (contact_id, whatsapp_number, last_message_at, expires_at)
            VALUES (?, ?, NOW(), ?)
        ");
        $stmt->execute([$contactId, $whatsappNumber, $expiresAt]);
    }
}
