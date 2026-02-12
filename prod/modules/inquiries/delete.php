<?php
/**
 * Inquiry Delete
 * Handles inquiry deletion with proper authorization and logging
 */
require_once '../../app/bootstrap.php';
requireLogin();
requireCRMAccess();

// Validate ID parameter
$id = requireIdParam();

// Delete inquiry using transaction for data integrity
try {
    $pdo->beginTransaction();

    // Log the deletion before removing
    logAction('inquiry_delete', "Deleted inquiry ID: {$id}");

    // Delete the inquiry
    $stmt = $pdo->prepare("DELETE FROM inquiries WHERE id = ?");
    $stmt->execute([$id]);

    $pdo->commit();
    redirectWithAlert("list.php", "Inquiry deleted successfully!", "success");
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error: Cannot delete inquiry. It may be linked to other records.");
}
