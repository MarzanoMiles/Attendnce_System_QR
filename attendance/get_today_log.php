<?php
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$db    = getDB();
$today = date('Y-m-d');
$user  = currentUser();

// Filter by adviser if teacher
$adviserFilter = '';
$params        = [$today];
if (!isAdmin()) {
    $adviserFilter = 'AND sec.adviser_id = ?';
    $params[]      = $user['id'];
}

$rows = $db->prepare("
    SELECT s.first_name, s.last_name, sec.grade_level, sec.section_name,
           a.am_in, a.am_out, a.pm_in, a.pm_out,
           a.am_status, a.pm_status, a.attendance_type
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE a.date = ? {$adviserFilter}
    ORDER BY a.updated_at DESC
    LIMIT 50
");
$rows->execute($params);
$rows = $rows->fetchAll();

$result = array_map(fn($r) => [
    'name'            => htmlspecialchars($r['first_name'] . ' ' . $r['last_name']),
    'grade'           => htmlspecialchars($r['grade_level'] ?? ''),
    'section'         => htmlspecialchars($r['section_name'] ?? ''),
    'am_in'           => $r['am_in']  ? date('h:i A', strtotime($r['am_in']))  : null,
    'am_out'          => $r['am_out'] ? date('h:i A', strtotime($r['am_out'])) : null,
    'pm_in'           => $r['pm_in']  ? date('h:i A', strtotime($r['pm_in']))  : null,
    'pm_out'          => $r['pm_out'] ? date('h:i A', strtotime($r['pm_out'])) : null,
    'am_status'       => $r['am_status'],
    'pm_status'       => $r['pm_status'],
    'attendance_type' => $r['attendance_type'],
], $rows);

echo json_encode($result);