<?php
// Function to add notification
function addNotification($user_id, $message)
{
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $stmt->execute([$user_id, $message]);
}

// Function to get unread notifications
function getNotifications($user_id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Mark all as read
if (isset($_GET['mark_read'])) {
    $uid = $_SESSION['user_id'];
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$uid]);
    // Redirect back to avoid re-triggering
    $redirect = strtok($_SERVER["REQUEST_URI"], '?');
    header("Location: $redirect");
    exit;
}
?>