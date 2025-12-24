<?php
require_once '../../config.php';
requireLogin();

requireAdmin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id)
    die("Invalid ID");

// Prevent self-deletion
if ($id == $_SESSION['user_id']) {
    die("Error: You cannot delete your own account while logged in.");
}

try {
    $pdo->beginTransaction();
    // Delete roles first
    $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$id]);
    // Delete user
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    $pdo->commit();
    header("Location: list.php?msg=deleted");
} catch (PDOException $e) {
    $pdo->rollBack();
    die("Error: Cannot delete user. They might be linked to inquiries, classes, or payments.");
}
exit;
