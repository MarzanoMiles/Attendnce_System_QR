<?php
/**
 * Dashboard — Elementary School
 * Kinder through Grade 6
 */
require_once 'config/database.php';
require_once 'includes/functions.php';
requireLogin();

$pageTitle = 'Dashboard';
$db        = getDB();
$today     = date('Y-m-d');
$user      = currentUser();

// Holiday check
$calendarEntry = getCalendarEntry($today);
$isHoliday     = isHolidayOrNoClass($today);

// Stats — scoped to teacher's sections if not admin
$stats = getDashboardStats();

// 7-day trend
$trend = [];
for ($i = 6; $i >= 0; $i--) {
    $d    = date('Y-m-d', strtotime("-{$i} days"));
    $isHol = isHolidayOrNoClass($d);

    if ($isHol) {
        $trend[] = [
            'date'      => date('M d', strtotime($d)),
            'full_day'  => 0,
            'partial'   => 0,
            'absent'    => 0,
            'holiday'   => 1,
        ];
        continue;
    }

    // Build filter for teacher
    $tFilter = '';
    $tParams = [$d];
    if (!isAdmin()) {
        $tFilter = 'AND sec.adviser_id = ?';
        $tParams[] = $user['id'];
    }

    $stmt = $db->prepare("
        SELECT
            SUM(a.attendance_type = 'full_day') AS full_day,
            SUM(a.attendance_type = 'partial')  AS partial,
            SUM(a.attendance_type = 'absent')   AS absent
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE a.date = ? {$tFilter}
    ");
    $stmt->execute($tParams);
    $row = $stmt->fetch();

    $trend[] = [
        'date'     => date('M d', strtotime($d)),
        'full_day' => (int)($row['full_day'] ?? 0),
        'partial'  => (int)($row['partial']  ?? 0),
        'absent'   => (int)($row['absent']   ?? 0),
        'holiday'  => 0,
    ];
}

// Grade level summary (admin only)
$gradeSummary = [];
if (isAdmin()) {
    foreach (getGradeLevels() as $grade) {
        $stmt = $db->prepare("
            SELECT
                COUNT(DISTINCT s.id) AS total,
                SUM(a.attendance_type IN ('full_day','partial')) AS present,
                SUM(a.attendance_type = 'absent') AS absent,
                SUM(a.attendance_type = 'partial') AS partial
            FROM students s
            LEFT JOIN sections sec ON s.section_id = sec.id
            LEFT JOIN attendance a ON a.student_id = s.id AND a.date = ?
            WHERE sec.grade_level = ? AND s.is_active = 1
        ");
        $stmt->execute([$today, $grade]);
        $row = $stmt->fetch();
        $gradeSummary[$grade] = $row;
    }
}

// Teacher's sections summary
$sectionSummary = [];
if (!isAdmin()) {
    $allowedSections = getAllowedSections();
    foreach ($allowedSections as $sec) {
        $stmt = $db->prepare("
            SELECT
                COUNT(DISTINCT s.id) AS total,
                SUM(a.attendance_type IN ('full_day','partial')) AS present,
                SUM(a.attendance_type = 'absent')  AS absent,
                SUM(a.attendance_type = 'partial') AS partial
            FROM students s
            LEFT JOIN attendance a ON a.student_id = s.id AND a.date = ?
            WHERE s.section_id = ? AND s.is_active = 1
        ");
        $stmt->execute([$today, $sec['id']]);
        $row = $stmt->fetch();
        $sectionSummary[] = array_merge($sec, $row);
    }
}

// Recent scans
$recentFilter = '';
$recentParams = [$today];
if (!isAdmin()) {
    $recentFilter = 'AND sec.adviser_id = ?';
    $recentParams[] = $user['id'];
}
$recent = $db->prepare("
    SELECT a.*, s.first_name, s.last_name, s.photo,
           sec.section_name, sec.grade_level
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE a.date = ? {$recentFilter}
    ORDER BY a.updated_at DESC
    LIMIT 10
");
$recent->execute($recentParams);
$recent = $recent->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<?php showFlash(); ?>

<?php if ($isHoliday && $calendarEntry): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-calendar-x fs-3"></i>
    <div>
        <strong>
            <?= $calendarEntry['type'] === 'holiday' ? '🎉 Holiday' : '📢 No Class Today' ?>:
            <?= sanitize($calendarEntry['title']) ?>
        </strong>
        <?php if ($calendarEntry['description']): ?>
        — <?= sanitize($calendarEntry['description']) ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard
        </h1>
        <p class="page-subtitle"><?= date('l, F j, Y') ?> — Overview</p>
    </div>
    <a href="attendance/scanner.php" class="btn btn-primary">
        <i class="bi bi-qr-code-scan me-1"></i>Open Scanner
    </a>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
            <div>
                <div class="stat-number"><?= $stats['total_students'] ?></div>
                <div class="stat-label">Total Students</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card green">
            <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <div>
                <div class="stat-number"><?= $stats['present_today'] ?></div>
                <div class="stat-label">Present Today</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card orange">
            <div class="stat-icon orange"><i class="bi bi-clock-history"></i></div>
            <div>
                <div class="stat-number"><?= $stats['partial_today'] ?></div>
                <div class="stat-label">Partial Today</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card red">
            <div class="stat-icon red"><i class="bi bi-x-circle-fill"></i></div>
            <div>
                <div class="stat-number"><?= $stats['absent_today'] ?></div>
                <div class="stat-label">Absent Today</div>
            </div>
        </div>
    </div>
</div>

<!-- Trend Chart + Summary -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-graph-up me-2 text-primary"></i>7-Day Attendance Trend
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-diagram-3 me-2 text-primary"></i>
                <?= isAdmin() ? 'Grade Level Summary' : 'My Sections' ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th><?= isAdmin() ? 'Grade' : 'Section' ?></th>
                                <th class="text-center">Present</th>
                                <th class="text-center">Partial</th>
                                <th class="text-center">Absent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $summaryData = isAdmin() ? $gradeSummary : [];
                            if (!isAdmin()) {
                                foreach ($sectionSummary as $s) {
                                    $summaryData[$s['section_name']] = $s;
                                }
                            }
                            foreach ($summaryData as $label => $row):
                            ?>
                            <tr>
                                <td class="fw-600 small"><?= sanitize($label) ?></td>
                                <td class="text-center">
                                    <span class="status-badge badge-present">
                                        <?= ($row['present'] ?? 0) - ($row['partial'] ?? 0) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="status-badge badge-partial">
                                        <?= $row['partial'] ?? 0 ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="status-badge badge-absent">
                                        <?= $row['absent'] ?? 0 ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Attendance -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-clock-history me-2 text-primary"></i>Today's Recent Scans
        </span>
        <a href="attendance/index.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Grade / Section</th>
                        <th class="text-center">AM In</th>
                        <th class="text-center">AM Out</th>
                        <th class="text-center">PM In</th>
                        <th class="text-center">PM Out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2 opacity-25"></i>
                            No attendance records yet today.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($recent as $row): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <img src="<?= BASE_URL ?>uploads/students/<?= htmlspecialchars($row['photo']) ?>"
                                     class="student-photo-sm"
                                     onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($row['first_name'].' '.$row['last_name']) ?>&size=40&background=1a56db&color=fff'">
                                <span class="fw-600 small">
                                    <?= sanitize($row['last_name'] . ', ' . $row['first_name']) ?>
                                </span>
                            </div>
                        </td>
                        <td class="small">
                            <?= sanitize($row['grade_level'] ?? '') ?>
                            <span class="text-muted">/ <?= sanitize($row['section_name'] ?? '—') ?></span>
                        </td>
                        <td class="text-center small">
                            <?= $row['am_in']  ? date('h:i A', strtotime($row['am_in']))  : '—' ?>
                        </td>
                        <td class="text-center small">
                            <?= $row['am_out'] ? date('h:i A', strtotime($row['am_out'])) : '—' ?>
                        </td>
                        <td class="text-center small">
                            <?= $row['pm_in']  ? date('h:i A', strtotime($row['pm_in']))  : '—' ?>
                        </td>
                        <td class="text-center small">
                            <?= $row['pm_out'] ? date('h:i A', strtotime($row['pm_out'])) : '—' ?>
                        </td>
                        <td>
                            <?= attendanceTypeBadge($row['attendance_type']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$trendLabels   = json_encode(array_column($trend, 'date'));
$trendFullDay  = json_encode(array_column($trend, 'full_day'));
$trendPartial  = json_encode(array_column($trend, 'partial'));
$trendAbsent   = json_encode(array_column($trend, 'absent'));

$extraJS = <<<JS
<script>
new Chart(document.getElementById('trendChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: {$trendLabels},
        datasets: [
            {
                label: 'Full Day',
                data: {$trendFullDay},
                backgroundColor: 'rgba(14,159,110,0.75)',
                borderRadius: 5
            },
            {
                label: 'Partial',
                data: {$trendPartial},
                backgroundColor: 'rgba(245,158,11,0.75)',
                borderRadius: 5
            },
            {
                label: 'Absent',
                data: {$trendAbsent},
                backgroundColor: 'rgba(224,36,36,0.65)',
                borderRadius: 5
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: {
            x: { stacked: false },
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});
</script>
JS;
include 'includes/footer.php';
?>