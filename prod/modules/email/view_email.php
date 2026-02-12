<?php
/**
 * View Email Details (AJAX endpoint)
 */

require_once '../../app/bootstrap.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('admin') && !hasRole('branch_manager') && !hasRole('counselor')) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Invalid email ID']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM email_queue WHERE id = ?");
$stmt->execute([$id]);
$email = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$email) {
    echo json_encode(['success' => false, 'error' => 'Email not found']);
    exit;
}

echo json_encode([
    'success' => true,
    'email' => [
        'id' => $email['id'],
        'recipient_name' => htmlspecialchars($email['recipient_name'] ?? ''),
        'recipient_email' => htmlspecialchars($email['recipient_email']),
        'subject' => htmlspecialchars($email['subject']),
        'body' => $email['body'], // Already HTML
        'template' => $email['template'],
        'status' => $email['status'],
        'attempts' => $email['attempts'],
        'error_message' => $email['error_message'],
        'scheduled_at' => $email['scheduled_at'],
        'sent_at' => $email['sent_at'],
        'created_at' => $email['created_at']
    ]
]);
