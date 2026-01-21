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
    $pdo->prepare("DELETE FROM partners WHERE id = ?")->execute([$id]);
    redirectWithAlert("list.php", "Partner deleted successfully!", "danger");
} catch (PDOException $e) {
    die("Error: Cannot delete partner. They might be linked to other records.");
}
exit;
