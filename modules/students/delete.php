<?php
require_once '../../config.php';
requireLogin();

requireAdmin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id)
    die("Invalid ID");

try {
    $pdo->beginTransaction();

    // Delete roles
    $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$id]);

    // Delete logs
    $pdo->prepare("DELETE FROM student_logs WHERE student_id = ?")->execute([$id]);

    // Delete documents (files remain on disk for safety in this demo)
    $pdo->prepare("DELETE FROM student_documents WHERE student_id = ?")->execute([$id]);

    // Delete enrollments
    $pdo->prepare("DELETE FROM enrollments WHERE student_id = ?")->execute([$id]);

    // Delete scores
    $pdo->prepare("DELETE FROM test_scores WHERE student_id = ?")->execute([$id]);

    // Finally delete user
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

    $pdo->commit();
    header("Location: list.php?msg=deleted");
} catch (PDOException $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    die("Error deleting student: " . $e->getMessage());
}
exit;
