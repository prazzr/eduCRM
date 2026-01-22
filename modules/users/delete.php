<?php
/**
 * User Delete
 * Handles staff user deletion with safety checks
 */
require_once '../../app/bootstrap.php';
requireLogin();
requireAdmin();

// Validate ID parameter
$id = requireIdParam();

// Prevent self-deletion
if ($id == $_SESSION['user_id']) {
    die("Error: You cannot delete your own account while logged in.");
}

try {
    $pdo->beginTransaction();

    // Log the deletion before removing
    logAction('user_delete', "Deleted user ID: {$id}");

    // Delete roles first (foreign key constraint)
    $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$id]);

    // Delete user
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

    $pdo->commit();
    redirectWithAlert("list.php", "User deleted successfully!", "success");
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error: Cannot delete user. They might be linked to inquiries, classes, or payments.");
}
