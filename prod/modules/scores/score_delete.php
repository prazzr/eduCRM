<?php
require_once '../../app/bootstrap.php';
requireLogin();

if (hasRole('student')) {
    die("Unauthorized");
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id)
    die("Invalid ID");

try {
    $pdo->prepare("DELETE FROM test_scores WHERE id = ?")->execute([$id]);
    header("Location: manage.php?msg=deleted");
} catch (PDOException $e) {
    die("Error deleting record.");
}
exit;
