<?php
require_once 'includes/header.php'; // or your config/db only if preferred

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    // Unlink batches first (course_id set to NULL via FK, but we can be explicit)
    $pdo->prepare("UPDATE batches SET course_id = NULL WHERE course_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$id]);
}
header('Location: courses.php');
exit;