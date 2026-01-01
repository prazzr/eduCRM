<?php
/**
 * Viber Webhook Handler
 * Receives webhooks from Viber Bot API
 * Handles delivery receipts and incoming messages
 */

require_once '../../config.php';

// Get webhook payload
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log webhook for debugging
error_log('Viber Webhook: ' . $input);

if (!$data) {
    http_response_code(400);
    exit;
}

$event = $data['event'] ?? '';

try {
    switch ($event) {
        case 'webhook':
            // Webhook verification
            http_response_code(200);
            echo json_encode(['status' => 0, 'status_message' => 'ok']);
            break;

        case 'delivered':
        case 'seen':
            // Update message status
            handleDeliveryReceipt($pdo, $data);
            http_response_code(200);
            echo json_encode(['status' => 0, 'status_message' => 'ok']);
            break;

        case 'failed':
            // Mark message as failed
            handleFailedMessage($pdo, $data);
            http_response_code(200);
            echo json_encode(['status' => 0, 'status_message' => 'ok']);
            break;

        case 'message':
            // Handle incoming message
            handleIncomingMessage($pdo, $data);
            http_response_code(200);
            echo json_encode(['status' => 0, 'status_message' => 'ok']);
            break;

        case 'subscribed':
            // User subscribed to bot
            handleSubscription($pdo, $data, true);
            http_response_code(200);
            echo json_encode(['status' => 0, 'status_message' => 'ok']);
            break;

        case 'unsubscribed':
            // User unsubscribed from bot
            handleSubscription($pdo, $data, false);
            http_response_code(200);
            echo json_encode(['status' => 0, 'status_message' => 'ok']);
            break;

        case 'conversation_started':
            // New conversation started
            http_response_code(200);
            echo json_encode([
                'status' => 0,
                'status_message' => 'ok',
                'type' => 'text',
                'text' => 'Welcome to eduCRM! How can we help you today?'
            ]);
            break;

        default:
            http_response_code(200);
            echo json_encode(['status' => 0, 'status_message' => 'ok']);
    }

} catch (Exception $e) {
    error_log('Viber Webhook Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 1, 'status_message' => $e->getMessage()]);
}

exit;

/**
 * Handle delivery receipt
 */
function handleDeliveryReceipt($pdo, $data)
{
    $messageToken = $data['message_token'] ?? null;
    $userId = $data['user_id'] ?? null;

    if (!$messageToken)
        return;

    $status = $data['event'] === 'seen' ? 'delivered' : 'sent';

    $stmt = $pdo->prepare("
        UPDATE messaging_queue 
        SET status = ?,
            delivered_at = NOW()
        WHERE gateway_message_id = ?
    ");

    $stmt->execute([$status, $messageToken]);
}

/**
 * Handle failed message
 */
function handleFailedMessage($pdo, $data)
{
    $messageToken = $data['message_token'] ?? null;
    $desc = $data['desc'] ?? 'Unknown error';

    if (!$messageToken)
        return;

    $stmt = $pdo->prepare("
        UPDATE messaging_queue 
        SET status = 'failed',
            error_message = ?
        WHERE gateway_message_id = ?
    ");

    $stmt->execute([$desc, $messageToken]);
}

/**
 * Handle incoming message
 */
function handleIncomingMessage($pdo, $data)
{
    $sender = $data['sender'] ?? [];
    $message = $data['message'] ?? [];

    $viberId = $sender['id'] ?? null;
    $senderName = $sender['name'] ?? 'Unknown';
    $messageType = $message['type'] ?? 'text';
    $messageText = $message['text'] ?? '';
    $mediaUrl = $message['media'] ?? null;
    $timestamp = $data['timestamp'] ?? time();

    if (!$viberId)
        return;

    // Find or create contact
    $stmt = $pdo->prepare("SELECT id FROM messaging_contacts WHERE viber_id = ?");
    $stmt->execute([$viberId]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);

    $contactId = $contact['id'] ?? null;

    if (!$contactId) {
        // Create new contact
        $stmt = $pdo->prepare("
            INSERT INTO messaging_contacts (name, viber_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$senderName, $viberId]);
        $contactId = $pdo->lastInsertId();
    }

    // Store incoming message
    $stmt = $pdo->prepare("
        INSERT INTO whatsapp_incoming_messages (
            from_number, message_type, message_text, 
            media_url, contact_id, received_at
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $viberId,
        $messageType,
        $messageText,
        $mediaUrl,
        $contactId,
        date('Y-m-d H:i:s', $timestamp / 1000) // Viber uses milliseconds
    ]);
}

/**
 * Handle subscription
 */
function handleSubscription($pdo, $data, $subscribed)
{
    $user = $data['user'] ?? [];
    $viberId = $user['id'] ?? null;
    $userName = $user['name'] ?? 'Unknown';

    if (!$viberId)
        return;

    // Find or create contact
    $stmt = $pdo->prepare("SELECT id FROM messaging_contacts WHERE viber_id = ?");
    $stmt->execute([$viberId]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($contact) {
        // Update opt-out status
        $stmt = $pdo->prepare("
            UPDATE messaging_contacts 
            SET viber_opt_out = ?
            WHERE id = ?
        ");
        $stmt->execute([$subscribed ? 0 : 1, $contact['id']]);
    } else {
        // Create new contact
        $stmt = $pdo->prepare("
            INSERT INTO messaging_contacts (name, viber_id, viber_opt_out)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userName, $viberId, $subscribed ? 0 : 1]);
    }
}
