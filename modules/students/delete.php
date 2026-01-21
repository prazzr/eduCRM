<?php
/**
 * Student Delete
 * Handles student deletion with cascading related records cleanup
 */
require_once '../../app/bootstrap.php';
requireLogin();
requireAdmin();

// Validate ID parameter
$id = requireIdParam();

try {
    $pdo->beginTransaction();

    // Log the deletion before removing
    logAction('student_delete', "Deleted student ID: {$id}");

    // Delete related records in proper order (respecting foreign keys)
    $relatedTables = [
        'user_roles' => 'user_id',
        'student_logs' => 'student_id',
        'student_documents' => 'student_id',
        'enrollments' => 'student_id',
        'test_scores' => 'student_id'
    ];
    
    foreach ($relatedTables as $table => $column) {
        $pdo->prepare("DELETE FROM {$table} WHERE {$column} = ?")->execute([$id]);
    }

    // Finally delete user record
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

    $pdo->commit();
    redirectWithAlert("list.php", "Student deleted successfully!", "danger");
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error deleting student: " . $e->getMessage());
}
