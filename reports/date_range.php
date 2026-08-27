<?php
/**
 * Date Range Attendance Report
 * Holiday-aware summary over custom date range
 */
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$pageTitle  = 'Date Range Report';
$db         = getDB();
$dateFrom   = $_GET['date_from']  ?? date('Y-m-01');
$dateTo     = $_GET['date_to']    ?? date('Y-m-d');
$gradeLevel = $_GET['grade']      ?? '';
$sectionId  = (int)($_GET['section'] ?? 0);

$grades          = getGradeLevels();
$allowedSections = getAllowedSections();

// Build filters
$where  = ["a.date BETWEEN ? AND ?", "s.is_active = 1"];
$params = [$dateFrom, $dateTo];

if (!isAdmin()) {
    $ids          = array_column($allowedSections,'id') ?: [0];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $where[]      = "s.section_id IN ({$placeholders})";
    $params       = array_merge($params, $ids);
}
if ($gradeLevel) { $where[] = 'sec.grade_level = ?'; $params[] = $gradeLevel; }
if ($sectionId)  { $where[] = 's.section_id = ?';    $params[] = $sectionId; }

$whereSQL = implode(' AND ', $where);

// Count school days in range (exclude weekends + holidays)
$schoolDays = 0;
$current    = strtotime($dateFrom);
$end        = strtotime($dateTo);
while ($current <= $end) {
    $d = date('Y-m-d', $current);
    $dow = date('N', $current);
    if (!in_array($dow, [6,7]) && !isHolidayOrNoClass($d)) {
        $schoolDays++;
    }
    $current = strtotime('+1 day', $current);
}

// Summary per student
$students = $db->prepare("
    SELECT s.id, s.first_name, s.last_name, s.lrn,
           sec.grade_level, sec.section_name, sec.schedule_type,
           SUM(a.attendance_type = 'full_day') AS full_day,
           SUM(a.attendance_type = 'partial')  AS partial,
           SUM(a.attendance_type = 'absent')   AS absent,
           SUM(a.am_status = 'late')           AS am_late,
           SUM(a.pm_status = 'late')           AS pm_late,
           COUNT(a.id)                         AS total_recorded
    FROM students s
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN attendance a ON a.student_id = s.id
        AND a.date BETWEEN ? AND ?
    WHERE s.is_active = 1
    {$gradeLevel ? "AND sec.grade_level = '{$gradeLevel}'" : ''}
    {$sectionId  ? "AND s.section_id = {$sectionId}"       : ''}
    GROUP BY s.id
    ORDER BY {$gradeLevelOrderSQL('sec.grade_level')}, sec.section_name, s.last_name
");
$students->execute([$dateFrom, $dateTo]);
$students = $students->fetchAll();

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-calendar-range me-2 text-primary"></i>Date Range Report
        </h1>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3 no-print">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label mb-1 small fw-600">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small fw-600">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <?php if (isAdmin()): ?>
            <div class="col-md-2">
                <label class="form-label mb-1 small fw-600">Grade</label>
                <select name="grade" class="form-select form-select-sm">
                    <option value="">All Grades</option>
                    <?php foreach ($grades as $g): ?>
                    <option value="<?=$g?>" <?=$gradeLevel===$g?'selected':''?>><?=$g?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <label class="form-label mb-1 small fw-600">Section</label>
                <select name="section" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($allowedSections as $s): ?>
                    <option value="<?=$s['id']?>" <?=$sectionId==$s['id']?'selected':''?>>
                        <?= sanitize($s['grade_level'].' — '.$s['section_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">Generate</button>
            </div>
        </form>
    </div>
</div>

<!-- Report info -->
<div class="alert alert-info py-2 small">
    <i class="bi bi-info-circle me-1"></i>
    Date range: <strong><?= date('F j, Y', strtotime($dateFrom)) ?></strong>
    to <strong><?= date('F j, Y', strtotime($dateTo)) ?></strong> —
    <strong><?= $schoolDays ?></strong> school day(s) (weekends and holidays excluded)
</div>

<!-- Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span>
            <i class="bi bi-table me-1"></i>Student Summary
            <span class="badge bg-primary ms-1"><?= count($students) ?></span>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0" style="font-size:0.8rem">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Grade / Section</th>
                        <th class="text-center">School Days</th>
                        <th class="text-center">Full Day</th>
                        <th class="text-center">Partial</th>
                        <th class="text-center">Absent</th>
                        <th class="text-center">AM Late</th>
                        <th class="text-center">PM Late</th>
                        <th class="text-center">Attend. Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-4 text-muted">
                            No records found.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($students as $i => $s): ?>
                    <?php
                    $attended = $s['full_day'] + ($s['partial'] * 0.5);
                    $rate     = $schoolDays > 0
                        ? round(($attended / $schoolDays) * 100, 1)
                        : 0;
                    ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="fw-600">
                            <?= sanitize($s['last_name'].', '.$s['first_name']) ?>
                            <div class="text-muted" style="font-size:0.7rem">
                                <?= sanitize($s['lrn']) ?>
                            </div>
                        </td>
                        <td class="small">
                            <?= sanitize($s['grade_level'].' / '.$s['section_name']) ?>
                        </td>
                        <td class="text-center"><?= $schoolDays ?></td>
                        <td class="text-center">
                            <span class="badge bg-success bg-opacity-75"><?= $s['full_day'] ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-warning text-dark"><?= $s['partial'] ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-danger bg-opacity-75"><?= $s['absent'] ?></span>
                        </td>
                        <td class="text-center"><?= $s['am_late'] ?></td>
                        <td class="text-center"><?= $s['pm_late'] ?></td>
                        <td class="text-center">
                            <span class="fw-700 text-<?= $rate>=90?'success':($rate>=75?'warning':'danger') ?>">
                                <?= $rate ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>