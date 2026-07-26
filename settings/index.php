<?php
/**
 * System Settings
 */
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin();

$pageTitle = 'Settings';
$db        = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = [
        'school_name','school_address','school_year','grade_level',
        'time_in_start','time_in_end','late_threshold',
        'time_out_start','time_out_end',
        'unisms_api_key','unisms_sender_id',
        'sms_arrival_template','sms_departure_template','sms_absence_template'
    ];
    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            updateSetting($key, trim($_POST[$key]));
        }
    }
    setFlash('success', 'Settings saved successfully.');
    header('Location: index.php');
    exit;
}

// Load all settings
$settings = [];
$rows = $db->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll();
foreach ($rows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<?php showFlash(); ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-gear-fill me-2 text-primary"></i>System Settings</h1>
        <p class="page-subtitle">Configure school info, schedule, and SMS</p>
    </div>
</div>

<form method="POST">
    <div class="row g-4">

        <!-- School Information -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-building me-2"></i>School Information</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">School Name</label>
                        <input type="text" name="school_name" class="form-control"
                               value="<?= htmlspecialchars($settings['school_name'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <input type="text" name="school_address" class="form-control"
                               value="<?= htmlspecialchars($settings['school_address'] ?? '') ?>">
                    </div>
                    <div class="row g-2">
                        <div class="col">
                            <label class="form-label">School Year</label>
                            <input type="text" name="school_year" class="form-control"
                                   placeholder="2024-2025"
                                   value="<?= htmlspecialchars($settings['school_year'] ?? '') ?>">
                        </div>
                        <div class="col">
                            <label class="form-label">Grade Level</label>
                            <input type="text" name="grade_level" class="form-control"
                                   value="<?= htmlspecialchars($settings['grade_level'] ?? 'Kindergarten') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedule Settings -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-clock me-2"></i>Schedule Settings</div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Define school time windows for attendance tracking.</p>
                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label class="form-label">Time-In Start</label>
                            <input type="time" name="time_in_start" class="form-control"
                                   value="<?= $settings['time_in_start'] ?? '07:00' ?>">
                        </div>
                        <div class="col">
                            <label class="form-label">Time-In End</label>
                            <input type="time" name="time_in_end" class="form-control"
                                   value="<?= $settings['time_in_end'] ?? '08:00' ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            Late Threshold
                            <span class="badge bg-warning text-dark ms-1">Arrivals after this = LATE</span>
                        </label>
                        <input type="time" name="late_threshold" class="form-control"
                               value="<?= $settings['late_threshold'] ?? '07:31' ?>">
                    </div>
                    <div class="row g-2">
                        <div class="col">
                            <label class="form-label">Time-Out Start</label>
                            <input type="time" name="time_out_start" class="form-control"
                                   value="<?= $settings['time_out_start'] ?? '11:00' ?>">
                        </div>
                        <div class="col">
                            <label class="form-label">Time-Out End</label>
                            <input type="time" name="time_out_end" class="form-control"
                                   value="<?= $settings['time_out_end'] ?? '12:00' ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- UniSMS Settings -->
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <i class="bi bi-chat-dots-fill me-2 text-primary"></i>
                    UniSMS API Settings
                    <a href="https://unismsapi.com" target="_blank"
                       class="btn btn-sm btn-outline-info ms-2">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Get API Key
                    </a>
                </div>
                <div class="card-body">

                    <div class="alert alert-info py-2 mb-3" style="font-size:0.82rem">
                        <i class="bi bi-info-circle me-1"></i>
                        Register at <strong>unismsapi.com</strong>, go to Dashboard → copy your
                        <strong>Secret Key</strong>. Sender ID must be approved by UniSMS
                        (default: <code>UnisoftSMS</code>).
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label">UniSMS Secret Key</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bi bi-key text-muted"></i>
                                </span>
                                <input type="password"
                                       name="unisms_api_key"
                                       id="apiKeyInput"
                                       class="form-control font-monospace"
                                       placeholder="sk_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                                       value="<?= htmlspecialchars($settings['unisms_api_key'] ?? '') ?>">
                                <button type="button"
                                        class="btn btn-outline-secondary"
                                        onclick="toggleApiKey()">
                                    <i class="bi bi-eye" id="apiKeyEye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sender ID</label>
                            <input type="text"
                                   name="unisms_sender_id"
                                   class="form-control"
                                   maxlength="11"
                                   placeholder="UnisoftSMS"
                                   value="<?= htmlspecialchars($settings['unisms_sender_id'] ?? 'UnisoftSMS') ?>">
                            <small class="text-muted">Max 11 characters</small>
                        </div>
                    </div>

                    <!-- Test SMS button -->
                    <div class="mb-4">
                        <button type="button" class="btn btn-sm btn-outline-success"
                                onclick="testSMS()">
                            <i class="bi bi-send me-1"></i>Send Test SMS
                        </button>
                        <input type="text" id="testNumber" class="form-control d-inline-block ms-2"
                               placeholder="+639XXXXXXXXX" style="width:200px; display:inline-block!important">
                        <span id="testSMSResult" class="ms-2 small"></span>
                    </div>

                    <hr>

                    <p class="fw-600 mb-1">SMS Templates</p>
                    <small class="text-muted d-block mb-3">
                        Available variables:
                        <code>{student_name}</code>
                        <code>{time}</code>
                        <code>{date}</code>
                    </small>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">
                                <i class="bi bi-box-arrow-in-right text-success me-1"></i>
                                Arrival Message
                            </label>
                            <textarea name="sms_arrival_template"
                                      class="form-control" rows="4"><?= htmlspecialchars($settings['sms_arrival_template'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">
                                <i class="bi bi-box-arrow-right text-info me-1"></i>
                                Departure Message
                            </label>
                            <textarea name="sms_departure_template"
                                      class="form-control" rows="4"><?= htmlspecialchars($settings['sms_departure_template'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">
                                <i class="bi bi-exclamation-circle text-danger me-1"></i>
                                Absence Alert
                            </label>
                            <textarea name="sms_absence_template"
                                      class="form-control" rows="4"><?= htmlspecialchars($settings['sms_absence_template'] ?? '') ?></textarea>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-save me-1"></i>Save All Settings
            </button>
        </div>

    </div>
</form>

<?php
$extraJS = <<<'JS'
<script>
// Toggle API key visibility
function toggleApiKey() {
    const input = document.getElementById('apiKeyInput');
    const eye   = document.getElementById('apiKeyEye');
    if (input.type === 'password') {
        input.type    = 'text';
        eye.className = 'bi bi-eye-slash';
    } else {
        input.type    = 'password';
        eye.className = 'bi bi-eye';
    }
}

// Test SMS directly from settings page
async function testSMS() {
    const number = document.getElementById('testNumber').value.trim();
    const result = document.getElementById('testSMSResult');

    if (!number) {
        result.innerHTML = '<span class="text-danger">Enter a phone number first.</span>';
        return;
    }

    result.innerHTML = '<span class="text-muted">Sending...</span>';

    try {
        const res = await fetch('test_sms.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'number=' + encodeURIComponent(number)
        });

        // Debug — show raw response first
        const text = await res.text();
        console.log('Raw response:', text);
        result.innerHTML = '<pre style="font-size:0.75rem">' + text + '</pre>';

    } catch (e) {
        result.innerHTML = '<span class="text-danger">Network error: ' + e.message + '</span>';
        console.error(e);
    }
}
</script>
JS;
include '../includes/footer.php';
?>