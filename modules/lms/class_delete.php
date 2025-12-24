<?php
require_once '../../config.php';
requireLogin();

requireAdmin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id)
    die("Invalid ID");

try {
    $pdo->beginTransaction();
    // Delete enrollments first
    $pdo->prepare("DELETE FROM enrollments WHERE class_id = ?")->execute([$id]);
    // Delete materials
    $pdo->prepare("DELETE FROM class_materials WHERE class_id = ?")->execute([$id]);
    // Delete class
    $pdo->prepare("DELETE FROM classes WHERE id = ?")->execute([$id]);
    $pdo->commit();
    header("Location: classes.php?msg=deleted");
} catch (PDOException $e) {
    $pdo->rollBack();
    die("Error: Cannot delete class. It might be linked to other records.");
}
exit;
