<?php
require_once '../../app/bootstrap.php';
requireLogin();

requireAdmin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id)
    die("Invalid ID");

try {
    $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
    $stmt->execute([$id]);
    redirectWithAlert("courses.php", "Course deleted successfully!", "danger");
} catch (PDOException $e) {
    die("Error: Cannot delete course. It may be linked to active classes. Delete the classes first.");
}
exit;
