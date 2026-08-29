<?php
/**
 * Attendance Analytics Dashboard v2
 * Uses am_status, pm_status, attendance_type columns
 */
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'Analytics';
$db        = getDB();
$year      = (int)($_GET['year'] ?? date('Y'));

// Build section filter for teachers
$sectionFilter = '';
$sectionParams = [];
if (!isAdmin()) {
    $user            = currentUser();
    $allowedSections = getAllowedSections();
    $ids             = array_column($allowedSections, 'id') ?: [0];
    $placeholders    = implode(',', array_fill(0, count($ids), '?'));
    $sectionFilter   = "AND s.section_id IN ({$placeholders})";
    $sectionParams   = $ids;
}

// ── Monthly attendance totals ─────────────────────────────────
$monthly = [];
for ($m = 1; $m <= 12; $m++) {
    $stmt = $db->prepare("
        SELECT
            SUM(a.attendance_type IN ('full_day','partial')) AS present,
            SUM(a.attendance_type = 'absent')                AS absent
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE MONTH(a.date) = ? AND YEAR(a.date) = ?
        AND s.is_active = 1 {$sectionFilter}
    ");
    $stmt->execute(array_merge([$m, $year], $sectionParams));
    $row = $stmt->fetch();
    $monthly[] = [
        'month'   => date('M', mktime(0,0,0,$m,1)),
        'present' => (int)($row['present'] ?? 0),
        'absent'  => (int)($row['absent']  ?? 0),
    ];
}

// ── Overall attendance rate ───────────────────────────────────
$stmt = $db->prepare("
    SELECT
        COUNT(*)                                         AS total,
        SUM(a.attendance_type IN ('full_day','partial')) AS attended,
        SUM(a.attendance_type = 'full_day')              AS full_day,
        SUM(a.attendance_type = 'partial')               AS partial,
        SUM(a.attendance_type = 'absent')                AS absent,
        SUM(a.am_status = 'late')                        AS am_late,
        SUM(a.pm_status = 'late')                        AS pm_late
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    WHERE YEAR(a.date) = ? AND s.is_active = 1 {$sectionFilter}
");
$stmt->execute(array_merge([$year], $sectionParams));
$overall = $stmt->fetch();

$attendanceRate = $overall['total'] > 0
    ? round(($overall['attended'] / $overall['total']) * 100, 1)
    : 0;

// ── Top 10 most absent students ───────────────────────────────
$stmt = $db->prepare("
    SELECT s.first_name, s.last_name,
           sec.section_name, sec.grade_level,
           COUNT(*) AS absent_count
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE a.attendance_type = 'absent'
    AND YEAR(a.date) = ?
    AND s.is_active = 1 {$sectionFilter}
    GROUP BY a.student_id
    ORDER BY absent_count DESC
    LIMIT 10
");
$stmt->execute(array_merge([$year], $sectionParams));
$mostAbsent = $stmt->fetchAll();

// ── Grade level attendance rates ──────────────────────────────
$gradeRates = [];
foreach (getGradeLevels() as $grade) {
    $stmt = $db->prepare("
        SELECT
            COUNT(a.id)                                      AS total,
            SUM(a.attendance_type IN ('full_day','partial')) AS attended
        FROM sections sec
        LEFT JOIN students s  ON s.section_id  = sec.id AND s.is_active = 1
        LEFT JOIN attendance a ON a.student_id = s.id AND YEAR(a.date) = ?
        WHERE sec.grade_level = ?
    ");
    $stmt->execute([$year, $grade]);
    $row = $stmt->fetch();
    $gradeRates[$grade] = [
        'total'    => (int)($row['total']    ?? 0),
        'attended' => (int)($row['attended'] ?? 0),
        'rate'     => $row['total'] > 0
            ? round(($row['attended'] / $row['total']) * 100, 1)
            : 0,
    ];
}

// ── Section attendance rates (for chart) ─────────────────────
$allowedSecs = getAllowedSections();
$sectionRates = [];
foreach ($allowedSecs as $sec) {
    $stmt = $db->prepare("
        SELECT
            COUNT(a.id)                                      AS total,
            SUM(a.attendance_type IN ('full_day','partial')) AS attended
        FROM students s
        LEFT JOIN attendance a ON a.student_id = s.id AND YEAR(a.date) = ?
        WHERE s.section_id = ? AND s.is_active = 1
    ");
    $stmt->execute([$year, $sec['id']]);
    $row = $stmt->fetch();
    $rate = $row['total'] > 0
        ? round(($row['attended'] / $row['total']) * 100, 1)
        : 0;
    $sectionRates[] = [
        'section_name' => $sec['section_name'],
        'grade_level'  => $sec['grade_level'],
        'total'        => (int)($row['total']    ?? 0),
        'attended'     => (int)($row['attended'] ?? 0),
        'rate'         => $rate,
    ];
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-bar-chart-fill me-2 text-primary"></i>Analytics
        </h1>
        <p class="page-subtitle">Attendance statistics and insights — <?= $year ?></p>
    </div>
    <form method="GET" class="d-flex gap-2">
        <select name="year" class="form-select form-select-sm" style="width:auto">
            <?php for ($y = 2024; $y <= date('Y') + 1; $y++): ?>
            <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-primary">Apply</button>
    </form>
</div>

<!-- Key Metrics -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="stat-card green py-2">
            <div class="stat-icon green" style="width:38px;height:38px;font-size:1rem">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            <div>
                <div class="stat-number" style="font-size:1.4rem"><?= $attendanceRate ?>%</div>
                <div class="stat-label">Attend. Rate</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card blue py-2">
            <div class="stat-icon blue" style="width:38px;height:38px;font-size:1rem">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div>
                <div class="stat-number" style="font-size:1.4rem">
                    <?= number_format($overall['full_day'] ?? 0) ?>
                </div>
                <div class="stat-label">Full Day</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card orange py-2">
            <div class="stat-icon orange" style="width:38px;height:38px;font-size:1rem">
                <i class="bi bi-clock-history"></i>
            </div>
            <div>
                <div class="stat-number" style="font-size:1.4rem">
                    <?= number_format($overall['partial'] ?? 0) ?>
                </div>
                <div class="stat-label">Partial</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card red py-2">
            <div class="stat-icon red" style="width:38px;height:38px;font-size:1rem">
                <i class="bi bi-x-circle-fill"></i>
            </div>
            <div>
                <div class="stat-number" style="font-size:1.4rem">
                    <?= number_format($overall['absent'] ?? 0) ?>
                </div>
                <div class="stat-label">Absent</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card orange py-2">
            <div class="stat-icon orange" style="width:38px;height:38px;font-size:1rem">
                <i class="bi bi-sun"></i>
            </div>
            <div>
                <div class="stat-number" style="font-size:1.4rem">
                    <?= number_format($overall['am_late'] ?? 0) ?>
                </div>
                <div class="stat-label">AM Late</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card orange py-2">
            <div class="stat-icon orange" style="width:38px;height:38px;font-size:1rem">
                <i class="bi bi-moon"></i>
            </div>
            <div>
                <div class="stat-number" style="font-size:1.4rem">
                    <?= number_format($overall['pm_late'] ?? 0) ?>
                </div>
                <div class="stat-label">PM Late</div>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Chart + Grade Rates -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-bar-chart me-2 text-primary"></i>
                Monthly Attendance — <?= $year ?>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-diagram-3 me-2 text-primary"></i>
                By Grade Level
            </div>
            <div class="card-body">
                <?php foreach ($gradeRates as $grade => $data): ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-600"><?= sanitize($grade) ?></span>
                        <span class="text-<?= $data['rate'] >= 90 ? 'success' : ($data['rate'] >= 75 ? 'warning' : 'danger') ?>">
                            <?= $data['rate'] ?>%
                        </span>
                    </div>
                    <div class="progress" style="height:7px;border-radius:4px">
                        <div class="progress-bar bg-<?= $data['rate'] >= 90 ? 'success' : ($data['rate'] >= 75 ? 'warning' : 'danger') ?>"
                             style="width:<?= $data['rate'] ?>%;border-radius:4px">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Section Rates + Most Absent -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-people me-2 text-primary"></i>Section Attendance Rates
            </div>
            <div class="card-body p-0" style="max-height:350px;overflow-y:auto">
                <table class="table table-sm table-hover mb-0">
                    <thead class="sticky-top bg-white">
                        <tr>
                            <th>Section</th>
                            <th>Grade</th>
                            <th class="text-center">Rate</th>
                            <th>Bar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sectionRates as $sr): ?>
                        <tr>
                            <td class="fw-600 small"><?= sanitize($sr['section_name']) ?></td>
                            <td class="small text-muted"><?= sanitize($sr['grade_level']) ?></td>
                            <td class="text-center">
                                <span class="fw-700 text-<?= $sr['rate'] >= 90 ? 'success' : ($sr['rate'] >= 75 ? 'warning' : 'danger') ?>">
                                    <?= $sr['rate'] ?>%
                                </span>
                            </td>
                            <td style="width:80px">
                                <div class="progress" style="height:6px">
                                    <div class="progress-bar bg-<?= $sr['rate'] >= 90 ? 'success' : ($sr['rate'] >= 75 ? 'warning' : 'danger') ?>"
                                         style="width:<?= $sr['rate'] ?>%"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Most Absent -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-exclamation-triangle me-2 text-warning"></i>
                Most Absent Students — <?= $year ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Student</th>
                                <th>Grade / Section</th>
                                <th class="text-center">Absences</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mostAbsent)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    No absence data.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($mostAbsent as $rank => $row): ?>
                            <tr>
                                <td>
                                    <?php if ($rank === 0): ?>
                                    <span class="badge bg-danger">#1</span>
                                    <?php elseif ($rank === 1): ?>
                                    <span class="badge bg-warning text-dark">#2</span>
                                    <?php elseif ($rank === 2): ?>
                                    <span class="badge bg-secondary">#3</span>
                                    <?php else: ?>
                                    <span class="text-muted small">#<?= $rank + 1 ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-600 small">
                                    <?= sanitize($row['last_name'].', '.$row['first_name']) ?>
                                </td>
                                <td class="small text-muted">
                                    <?= sanitize($row['grade_level'].' / '.$row['section_name']) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-danger">
                                        <?= $row['absent_count'] ?>d
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
    </div>
</div>

<?php
$monthlyLabels  = json_encode(array_column($monthly, 'month'));
$monthlyPresent = json_encode(array_column($monthly, 'present'));
$monthlyAbsent  = json_encode(array_column($monthly, 'absent'));

$extraJS = <<<JS
<script>
new Chart(document.getElementById('monthlyChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: {$monthlyLabels},
        datasets: [
            {
                label: 'Present/Partial',
                data: {$monthlyPresent},
                backgroundColor: 'rgba(14,159,110,0.75)',
                borderRadius: 5
            },
            {
                label: 'Absent',
                data: {$monthlyAbsent},
                backgroundColor: 'rgba(224,36,36,0.65)',
                borderRadius: 5
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>
JS;
include '../includes/footer.php';
?>