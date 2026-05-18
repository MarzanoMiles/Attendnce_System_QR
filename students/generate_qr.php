<?php
/**
 * Generate / View QR Code for a student
 * Also handles printing
 */
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$db = getDB();

$stmt = $db->prepare("SELECT s.*, sec.section_name FROM students s
                       LEFT JOIN sections sec ON s.section_id = sec.id
                       WHERE s.id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    setFlash('danger', 'Student not found.');
    header('Location: index.php');
    exit;
}

// Regenerate QR if missing
if (empty($student['qr_code']) || !file_exists(BASE_PATH . $student['qr_code'])) {
    if (file_exists(BASE_PATH . 'vendor/phpqrcode/qrlib.php')) {
        require_once BASE_PATH . 'vendor/phpqrcode/qrlib.php';
        $qrFile = 'qrcodes/qr_' . $student['id'] . '.png';
        QRcode::png($student['qr_token'], BASE_PATH . $qrFile, QR_ECLEVEL_M, 8, 2);
        $db->prepare("UPDATE students SET qr_code = ? WHERE id = ?")->execute([$qrFile, $student['id']]);
        $student['qr_code'] = $qrFile;
    }
}

$pageTitle = 'QR Code — ' . $student['first_name'];
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="page-header no-print">
    <div>
        <h1 class="page-title"><i class="bi bi-qr-code me-2 text-primary"></i>Student QR Code</h1>
        <p class="page-subtitle">Print and distribute to student</p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-success">
            <i class="bi bi-printer me-1"></i>Print QR Card
        </button>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card qr-print-card" id="qrCard">
            <!-- School Header -->
            <div class="card-body text-center p-4">
                <div class="mb-2">
                    <div class="fw-800 text-primary" style="font-size:1rem">
                        San Pablo City Central School
                    </div>
                    <div class="text-muted small">Kindergarten Department</div>
                    <div class="text-muted small">School Year <?= getSetting('school_year') ?></div>
                </div>

                <hr>

                <!-- Student photo -->
                <img src="<?= BASE_URL ?>uploads/students/<?= htmlspecialchars($student['photo']) ?>"
                     class="rounded-circle mb-3"
                     style="width:90px;height:90px;object-fit:cover;border:3px solid #1a56db"
                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($student['first_name'].' '.$student['last_name']) ?>&size=90&background=1a56db&color=fff'">

                <!-- Student info -->
                <h5 class="fw-800 mb-0">
                    <?= sanitize($student['first_name'] . ' ' . $student['last_name']) ?>
                </h5>
                <div class="text-muted small mb-1"><?= sanitize($student['section_name'] ?? 'No Section') ?></div>
                <div class="small"><strong>LRN:</strong> <?= sanitize($student['lrn']) ?></div>

                <hr>

                <!-- QR Code -->
                <?php if (!empty($student['qr_code']) && file_exists(BASE_PATH . $student['qr_code'])): ?>
                <img src="<?= BASE_URL . $student['qr_code'] ?>"
                     class="img-fluid mb-2"
                     style="max-width:200px"
                     alt="QR Code">
                <?php else: ?>
                <div class="alert alert-warning py-2 small">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    QR code not yet generated. Install phpqrcode library.
                </div>
                <?php endif; ?>

                <div class="small text-muted">
                    <code><?= sanitize($student['qr_token']) ?></code>
                </div>

                <div class="mt-3 small text-muted border-top pt-2">
                    This QR code is used for daily attendance scanning.<br>
                    Please keep this card safe.
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>