<?php
require_once '../../app/bootstrap.php';
requireLogin();

if (hasRole('student')) {
    die("Unauthorized");
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id)
    die("Invalid ID");

$pdo->prepare("DELETE FROM university_applications WHERE id = ?")->execute([$id]);
redirectWithAlert("tracker.php", "Application deleted successfully!", "success");
exit;
