<?php
require_once '../../config.php';
requireLogin();

requireAdmin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id)
    die("Invalid ID");

try {
    $pdo->prepare("DELETE FROM fee_types WHERE id = ?")->execute([$id]);
    header("Location: fee_types.php?msg=deleted");
} catch (PDOException $e) {
    die("Error: Cannot delete fee type. It may be linked to student records.");
}
exit;
