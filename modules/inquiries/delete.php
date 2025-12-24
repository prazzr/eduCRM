<?php
require_once '../../config.php';
requireLogin();

requireAdminOrCounselor();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id)
    die("Invalid ID");

$pdo->prepare("DELETE FROM inquiries WHERE id = ?")->execute([$id]);
header("Location: list.php?msg=deleted");
exit;
