<?php
/**
 * Save Section (Add or Edit)
 */
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id           = (int)($_POST['id']            ?? 0);
$sectionName  = trim($_POST['section_name']   ?? '');
$gradeLevel   = $_POST['grade_level']         ?? '';
$scheduleType = $_POST['schedule_type']       ?? 'full_day';
$adviserId    = !empty($_POST['adviser_id'])   ? (int)$_POST['adviser_id'] : null;
$schoolYear   = trim($_POST['school_year']    ?? '2026-2027');

$allowedGrades    = getGradeLevels();
$allowedSchedules = ['full_day', 'am_only', 'pm_only'];

// Validate
if (empty($sectionName)) {
    setFlash('danger', 'Section name is required.');
    header('Location: index.php');
    exit;
}
if (!in_array($gradeLevel, $allowedGrades)) {
    setFlash('danger', 'Invalid grade level selected.');
    header('Location: index.php');
    exit;
}
if (!in_array($scheduleType, $allowedSchedules)) {
    setFlash('danger', 'Invalid schedule type.');
    header('Location: index.php');
    exit;
}

$db = getDB();

// Time fields with defaults
$amInStart       = !empty($_POST['am_in_start'])       ? $_POST['am_in_start']       : '06:00:00';
$amInEnd         = !empty($_POST['am_in_end'])         ? $_POST['am_in_end']         : '08:00:00';
$amLateThreshold = !empty($_POST['am_late_threshold']) ? $_POST['am_late_threshold'] : '07:31:00';
$amOutStart      = !empty($_POST['am_out_start'])      ? $_POST['am_out_start']      : '11:00:00';
$amOutEnd        = !empty($_POST['am_out_end'])        ? $_POST['am_out_end']        : '12:00:00';
$pmInStart       = !empty($_POST['pm_in_start'])       ? $_POST['pm_in_start']       : '12:00:00';
$pmInEnd         = !empty($_POST['pm_in_end'])         ? $_POST['pm_in_end']         : '13:30:00';
$pmLateThreshold = !empty($_POST['pm_late_threshold']) ? $_POST['pm_late_threshold'] : '12:31:00';
$pmOutStart      = !empty($_POST['pm_out_start'])      ? $_POST['pm_out_start']      : '17:00:00';
$pmOutEnd        = !empty($_POST['pm_out_end'])        ? $_POST['pm_out_end']        : '18:00:00';

if ($id > 0) {
    // ── Update existing section ───────────────────────────────
    $db->prepare("
        UPDATE sections SET
            section_name      = ?,
            grade_level       = ?,
            schedule_type     = ?,
            adviser_id        = ?,
            school_year       = ?,
            am_in_start       = ?,
            am_in_end         = ?,
            am_late_threshold = ?,
            am_out_start      = ?,
            am_out_end        = ?,
            pm_in_start       = ?,
            pm_in_end         = ?,
            pm_late_threshold = ?,
            pm_out_start      = ?,
            pm_out_end        = ?
        WHERE id = ?
    ")->execute([
        $sectionName, $gradeLevel, $scheduleType, $adviserId, $schoolYear,
        $amInStart, $amInEnd, $amLateThreshold, $amOutStart, $amOutEnd,
        $pmInStart, $pmInEnd, $pmLateThreshold, $pmOutStart, $pmOutEnd,
        $id
    ]);

    setFlash('success', "Section '{$sectionName}' updated successfully.");

} else {
    // ── Insert new section ────────────────────────────────────
    $db->prepare("
        INSERT INTO sections (
            section_name, grade_level, schedule_type, adviser_id, school_year,
            am_in_start, am_in_end, am_late_threshold, am_out_start, am_out_end,
            pm_in_start, pm_in_end, pm_late_threshold, pm_out_start, pm_out_end
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ")->execute([
        $sectionName, $gradeLevel, $scheduleType, $adviserId, $schoolYear,
        $amInStart, $amInEnd, $amLateThreshold, $amOutStart, $amOutEnd,
        $pmInStart, $pmInEnd, $pmLateThreshold, $pmOutStart, $pmOutEnd,
    ]);

    setFlash('success', "Section '{$sectionName}' created successfully.");
}

header('Location: index.php');
exit;