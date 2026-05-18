<?php
/**
 * Global helper functions
 */

require_once __DIR__ . '/../config/database.php';

// ─── Session ────────────────────────────────────────────────

function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

function isAdmin() {
    startSession();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit;
    }
}

function currentUser() {
    startSession();
    return [
        'id'        => $_SESSION['user_id']   ?? null,
        'username'  => $_SESSION['username']  ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['role']       ?? '',
    ];
}

// ─── Flash messages ─────────────────────────────────────────

function setFlash($type, $message) {
    startSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    startSession();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function showFlash() {
    $flash = getFlash();
    if ($flash) {
        $type = htmlspecialchars($flash['type']);
        $msg  = htmlspecialchars($flash['message']);
        echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                {$msg}
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
    }
}

// ─── Settings ────────────────────────────────────────────────

function getSetting($key) {
    $db  = getDB();
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : null;
}

function updateSetting($key, $value) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value)
                          VALUES (?, ?)
                          ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $value]);
}

// ─── Attendance helpers ──────────────────────────────────────

function getAttendanceStatus($timeIn) {
    $lateThreshold = getSetting('late_threshold') ?? '07:31:00';
    if (strtotime($timeIn) > strtotime($lateThreshold)) {
        return 'late';
    }
    return 'present';
}

function getDashboardStats() {
    $db   = getDB();
    $today = date('Y-m-d');

    $stats = [];

    // Total active students
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM students WHERE is_active = 1");
    $stats['total_students'] = $stmt->fetch()['cnt'];

    // Present today
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM attendance WHERE date = ? AND status IN ('present','late')");
    $stmt->execute([$today]);
    $stats['present_today'] = $stmt->fetch()['cnt'];

    // Absent today
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM attendance WHERE date = ? AND status = 'absent'");
    $stmt->execute([$today]);
    $stats['absent_today'] = $stmt->fetch()['cnt'];

    // Late today
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM attendance WHERE date = ? AND status = 'late'");
    $stmt->execute([$today]);
    $stats['late_today'] = $stmt->fetch()['cnt'];

    return $stats;
}

// ─── QR Token generator ──────────────────────────────────────

function generateQRToken($lrn) {
    return 'STU-' . $lrn . '-' . strtoupper(substr(md5(uniqid($lrn, true)), 0, 6));
}

// ─── Sanitize ────────────────────────────────────────────────

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

// ─── Format phone number for Semaphore ──────────────────────

function formatPhone($number) {
    $number = preg_replace('/\D/', '', $number);
    if (substr($number, 0, 2) === '09') {
        $number = '63' . substr($number, 1);
    }
    return $number;
}

// ─── SMS via Semaphore API ────────────────────────────────────

function sendSMS($number, $message, $studentId, $type) {
    $db     = getDB();
    $apiKey = getSetting('semaphore_api_key');
    $sender = getSetting('semaphore_sender_name') ?? 'SPCCS';

    if (empty($apiKey)) {
        // Log as failed - no API key
        $stmt = $db->prepare("INSERT INTO sms_logs (student_id, recipient_number, message, type, status, api_response)
                               VALUES (?, ?, ?, ?, 'failed', 'No API key configured')");
        $stmt->execute([$studentId, $number, $message, $type]);
        return false;
    }

    $phone = formatPhone($number);
    $url   = 'https://api.semaphore.co/api/v4/messages';
    $data  = [
        'apikey'      => $apiKey,
        'number'      => $phone,
        'message'     => $message,
        'sendername'  => $sender,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response   = curl_exec($ch);
    $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError  = curl_error($ch);
    curl_close($ch);

    $status = ($httpCode === 200 && !$curlError) ? 'sent' : 'failed';

    // Log SMS
    $stmt = $db->prepare("INSERT INTO sms_logs (student_id, recipient_number, message, type, status, api_response)
                           VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$studentId, $phone, $message, $type, $status, $response ?: $curlError]);

    return $status === 'sent';
}

// ─── SMS message builder ─────────────────────────────────────

function buildSMSMessage($templateKey, $student) {
    $template = getSetting($templateKey) ?? '';
    $replacements = [
        '{student_name}' => $student['first_name'] . ' ' . $student['last_name'],
        '{time}'         => date('h:i A'),
        '{date}'         => date('F j, Y'),
    ];
    return str_replace(array_keys($replacements), array_values($replacements), $template);
}

// ─── Pagination helper ───────────────────────────────────────

function paginate($totalRecords, $perPage, $currentPage, $url) {
    $totalPages = ceil($totalRecords / $perPage);
    if ($totalPages <= 1) return '';

    $html = '<nav><ul class="pagination pagination-sm mb-0">';

    // Prev
    $prevDisabled = $currentPage <= 1 ? 'disabled' : '';
    $prevPage     = max(1, $currentPage - 1);
    $html .= "<li class='page-item {$prevDisabled}'>
                <a class='page-link' href='{$url}&page={$prevPage}'>«</a>
              </li>";

    // Pages
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i === $currentPage ? 'active' : '';
        $html  .= "<li class='page-item {$active}'>
                     <a class='page-link' href='{$url}&page={$i}'>{$i}</a>
                   </li>";
    }

    // Next
    $nextDisabled = $currentPage >= $totalPages ? 'disabled' : '';
    $nextPage     = min($totalPages, $currentPage + 1);
    $html .= "<li class='page-item {$nextDisabled}'>
                <a class='page-link' href='{$url}&page={$nextPage}'>»</a>
              </li>";

    $html .= '</ul></nav>';
    return $html;
}