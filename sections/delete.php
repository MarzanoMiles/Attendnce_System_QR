<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$db = getDB();

$stmt = $db->prepare("SELECT * FROM sections WHERE id = ?");
$stmt->execute([$id]);
$section = $stmt->fetch();

if (!$section) {
    setFlash('danger', 'Section not found.');
} else {
    $db->prepare("UPDATE sections SET is_active = 0 WHERE id = ?")->execute([$id]);
    setFlash('success', "Section '{$section['section_name']}' deleted.");
}

header('Location: index.php');
exit;