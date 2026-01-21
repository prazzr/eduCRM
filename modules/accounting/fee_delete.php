<?php
/**
 * Fee Type Delete
 * Handles fee type deletion with referential integrity check
 */
require_once '../../app/bootstrap.php';
requireLogin();
requireAdmin();

// Validate ID parameter
$id = requireIdParam();

try {
    // Log the deletion
    logAction('fee_type_delete', "Deleted fee type ID: {$id}");
    
    $pdo->prepare("DELETE FROM fee_types WHERE id = ?")->execute([$id]);
    redirectWithAlert("fee_types.php", "Fee type deleted successfully!", "danger");
} catch (PDOException $e) {
    die("Error: Cannot delete fee type. It may be linked to student records.");
}
