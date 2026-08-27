<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id           = (int)($_POST['id'] ?? 0);
$sectionName  = trim($_POST['section_name']  ?? '');
$gradeLevel   = $_POST['grade_level']        ?? '';
$scheduleType = $_POST['schedule_type']      ?? 'full_day';
$adviserId    = !empty($_POST['adviser_id']) ? (int)$_POST['adviser_id'] : null;
$schoolYear   = trim($_POST['school_year']   ?? '2026-2027');
$allowedGrades = getGradeLevels();
$allowedSchedules = ['full_day','am_only','pm_only'];

if (empty($sectionName) || !in_array($gradeLevel, $allowedGrades) ||
    !in_array($scheduleType, $allowedSchedules)) {
    setFlash('danger', 'Invalid section data.');
    header('Location: index.php');
    exit;
}

$db = getDB();
$fields = [
    'section_name'       => $sectionName,
    'grade_level'        => $gradeLevel,
    'schedule_type'      => $scheduleType,
    'adviser_id'         => $adviserId,
    'school_year'        => $schoolYear,
    'am_in_start'        => $_POST['am_in_start']      ?? '06:00:00',
    'am_in_end'          => $_POST['am_in_end']        ?? '08:00:00',
    'am_late_threshold'  => $_POST['am_late_threshold'] ?? '07:31:00',
    'am_out_start'       => $_POST['am_out_start']     ?? '11:00:00',
    'am_out_end'         => $_POST['am_out_end']       ?? '12:00:00',
    'pm_in_start'        => $_POST['pm_in_start']      ?? '12:00:00',
    'pm_in_end'          => $_POST['pm_in_end']        ?? '13:30:00',
    'pm_late_threshold'  => $_POST['pm_late_threshold'] ?? '12:31:00',
    'pm_out_start'       => $_POST['pm_out_start']     ?? '17:00:00',
    'pm_out_end'         => $_POST['pm_out_end']       ?? '18:00:00',
];

if ($id > 0) {
    // Update
    $sets   = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($fields)));
    $values = array_values($fields);
    $values[] = $id;
    $db->prepare("UPDATE sections SET {$sets} WHERE id = ?")->execute($values);
    setFlash('success', "Section '{$sectionName}' updated.");
} else {
    // Insert
    $cols   = implode(', ', array_keys($fields));
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    $db->prepare("INSERT INTO sections ({$cols}) VALUES ({$placeholders})")
       ->execute(array_values($fields));
    setFlash('success', "Section '{$sectionName}' created.");
}

header('Location: index.php');
exit;