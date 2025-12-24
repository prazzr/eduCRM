<?php
require_once '../../config.php';
requireLogin();

if (hasRole('student')) {
    die("Unauthorized");
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id)
    die("Invalid ID");

$pdo->prepare("DELETE FROM university_applications WHERE id = ?")->execute([$id]);
header("Location: tracker.php?msg=deleted");
exit;
