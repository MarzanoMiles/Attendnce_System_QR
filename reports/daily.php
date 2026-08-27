<?php
/**
 * Daily Attendance Report
 * Full list for a date with AM/PM events, grade/section filter
 */
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$pageTitle  = 'Daily Attendance Report';
$db         = getDB();
$date       = $_GET['date']    ?? date('Y-m-d');
$gradeLevel = $_GET['grade']   ?? '';
$sectionId  = (int)($_GET['section'] ?? 0);

$grades          = getGradeLevels();
$allowedSections = getAllowedSections();
$calEntry        = getCalendarEntry($date);

// Build query
$where  = ['a.date = ?', 's.is_active = 1'];
$params = [$date];

if (!isAdmin()) {
    $ids          = array_column($allowedSections, 'id') ?: [0];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $where[]      = "s.section_id IN ({$placeholders})";
    $params       = array_merge($params, $ids);
}
if ($gradeLevel) { $where[] = 'sec.grade_level = ?'; $params[] = $gradeLevel; }
if ($sectionId)  { $where[] = 's.section_id = ?';    $params[] = $sectionId; }

$whereSQL = implode(' AND ', $where);

$records = $db->prepare("
    SELECT a.*, s.first_name, s.last_name, s.lrn,
           sec.section_name, sec.grade_level, sec.schedule_type
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE {$whereSQL}
    ORDER BY {$gradeLevelOrderSQL('sec.grade_level')},
             sec.section_name, s.last_name
");
$records->execute($params);
$records = $records->fetchAll();

// Summary
$summary = ['full_day'=>0,'partial'=>0,'absent'=>0,'am_late'=>0,'pm_late'=>0];
foreach ($records as $r) {
    $summary[$r['attendance_type']] = ($summary[$r['attendance_type']] ?? 0) + 1;
    if ($r['am_status'] === 'late') $summary['am_late']++;
    if ($r['pm_status'] === 'late') $summary['pm_late']++;
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="page-header no-print">
    <div>
        <h1 class="page-title">
            <i class="bi bi-calendar-day me-2 text-primary"></i>Daily Attendance Report
        </h1>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <a href="export_daily_excel.php?date=<?= $date ?>&grade=<?= urlencode($gradeLevel) ?>&section=<?= $sectionId ?>"
           class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel
        </a>
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
                <label class="form-label mb-1 small fw-600">Date</label>
                <input type="date" name="date" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($date) ?>">
            </div>
            <?php if (isAdmin()): ?>
            <div class="col-md-2">
                <label class="form-label mb-1 small fw-600">Grade</label>
                <select name="grade" class="form-select form-select-sm">
                    <option value="">All Grades</option>
                    <?php foreach ($grades as $g): ?>
                    <option value="<?= $g ?>" <?= $gradeLevel===$g?'selected':'' ?>><?= $g ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-3">
                <label class="form-label mb-1 small fw-600">Section</label>
                <select name="section" class="form-select form-select-sm">
                    <option value="">All Sections</option>
                    <?php foreach ($allowedSections as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $sectionId==$s['id']?'selected':'' ?>>
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

<!-- Report Header -->
<div class="card mb-3">
    <div class="card-body text-center py-3">
        <h5 class="fw-800 mb-0"><?= sanitize(getSetting('school_name')) ?></h5>
        <div class="text-muted small">S.Y. <?= getSetting('school_year') ?></div>
        <h6 class="fw-700 mt-2 mb-0">Daily Attendance Report</h6>
        <div><?= date('l, F j, Y', strtotime($date)) ?></div>
        <?php if ($calEntry && $calEntry['type'] !== 'school_day'): ?>
        <span class="badge bg-<?= entryColor($calEntry['type']) ?> mt-1">
            <?= ucfirst(str_replace('_',' ',$calEntry['type'])) ?>:
            <?= sanitize($calEntry['title']) ?>
        </span>
        <?php endif; ?>
        <?php if ($gradeLevel || $sectionId): ?>
        <div class="small text-muted mt-1">
            <?= $gradeLevel ? sanitize($gradeLevel) : '' ?>
            <?= $sectionId ? ' — ' . sanitize($allowedSections[array_search($sectionId, array_column($allowedSections,'id'))]['section_name'] ?? '') : '' ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Summary -->
<div class="row g-2 mb-3">
    <?php foreach ([
        ['Full Day', 'full_day', 'success'],
        ['Partial',  'partial',  'warning'],
        ['Absent',   'absent',   'danger'],
        ['AM Late',  'am_late',  'orange'],
        ['PM Late',  'pm_late',  'orange'],
    ] as [$label,$key,$color]): ?>
    <div class="col">
        <div class="card text-center py-2">
            <div class="fw-800 fs-4 text-<?= $color === 'orange' ? 'warning' : $color ?>">
                <?= $summary[$key] ?>
            </div>
            <div class="small text-muted"><?= $label ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0" style="font-size:0.8rem">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>LRN</th>
                        <th>Student Name</th>
                        <th>Grade / Section</th>
                        <th class="text-center">AM In</th>
                        <th class="text-center">AM Out</th>
                        <th class="text-center">AM Status</th>
                        <th class="text-center">PM In</th>
                        <th class="text-center">PM Out</th>
                        <th class="text-center">PM Status</th>
                        <th class="text-center">Type</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="12" class="text-center py-4 text-muted">
                            No records found.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($records as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><code style="font-size:0.7rem"><?= sanitize($r['lrn']) ?></code></td>
                        <td class="fw-600">
                            <?= sanitize($r['last_name'].', '.$r['first_name']) ?>
                        </td>
                        <td class="small">
                            <?= sanitize($r['grade_level'].' / '.$r['section_name']) ?>
                        </td>
                        <td class="text-center">
                            <?= $r['am_in']  ? date('h:i A',strtotime($r['am_in']))  : '—' ?>
                        </td>
                        <td class="text-center">
                            <?= $r['am_out'] ? date('h:i A',strtotime($r['am_out'])) : '—' ?>
                        </td>
                        <td class="text-center">
                            <?php if ($r['am_status']): ?>
                            <span class="badge bg-<?= $r['am_status']==='present'?'success':($r['am_status']==='late'?'warning text-dark':'danger') ?>">
                                <?= ucfirst($r['am_status']) ?>
                            </span>
                            <?php else: echo '—'; endif; ?>
                        </td>
                        <td class="text-center">
                            <?= $r['pm_in']  ? date('h:i A',strtotime($r['pm_in']))  : '—' ?>
                        </td>
                        <td class="text-center">
                            <?= $r['pm_out'] ? date('h:i A',strtotime($r['pm_out'])) : '—' ?>
                        </td>
                        <td class="text-center">
                            <?php if ($r['schedule_type'] === 'am_only'): ?>
                            <span class="text-muted small">N/A</span>
                            <?php elseif ($r['pm_status']): ?>
                            <span class="badge bg-<?= $r['pm_status']==='present'?'success':($r['pm_status']==='late'?'warning text-dark':'danger') ?>">
                                <?= ucfirst($r['pm_status']) ?>
                            </span>
                            <?php else: echo '—'; endif; ?>
                        </td>
                        <td class="text-center">
                            <?= attendanceTypeBadge($r['attendance_type']) ?>
                        </td>
                        <td class="small text-muted">
                            <?= sanitize($r['remarks'] ?? '') ?>
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