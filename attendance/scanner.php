<?php
/**
 * QR Code Scanner Page
 * Uses html5-qrcode library for camera scanning
 */
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'QR Scanner';
$db        = getDB();

// Sections for manual dropdown
$sections  = $db->query("SELECT * FROM sections WHERE is_active = 1 ORDER BY section_name")->fetchAll();

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-qr-code-scan me-2 text-primary"></i>QR Code Scanner</h1>
        <p class="page-subtitle"><?= date('l, F j, Y') ?> — Scan student QR codes to record attendance</p>
    </div>
    <div>
        <span class="badge bg-success fs-6 py-2 px-3" id="scanCountBadge">
            <i class="bi bi-check-circle me-1"></i>
            <span id="scanCount">0</span> Scanned
        </span>
    </div>
</div>

<div class="row g-3">
    <!-- Scanner -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-camera-video me-2"></i>Camera Scanner</span>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-secondary" id="scannerStatus">Inactive</span>
                    <button class="btn btn-sm btn-primary" id="startScanBtn" onclick="startScanner()">
                        <i class="bi bi-play-fill me-1"></i>Start
                    </button>
                    <button class="btn btn-sm btn-outline-danger d-none" id="stopScanBtn" onclick="stopScanner()">
                        <i class="bi bi-stop-fill me-1"></i>Stop
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- html5-qrcode scanner container -->
                <div id="qr-reader"></div>

                <!-- Manual token input fallback -->
                <div class="mt-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                        <input type="text" id="manualToken" class="form-control"
                               placeholder="Or type/paste QR token here..."
                               autocomplete="off">
                        <button class="btn btn-primary" onclick="processManualScan()">
                            <i class="bi bi-check"></i> Record
                        </button>
                    </div>
                    <small class="text-muted">Use if camera is unavailable</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Result + Log -->
    <div class="col-lg-6">
        <!-- Last Scan Result -->
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Last Scan Result</div>
            <div class="card-body" id="scanResultArea">
                <div class="text-center text-muted py-4">
                    <i class="bi bi-qr-code-scan fs-1 d-block mb-2 opacity-25"></i>
                    Awaiting scan...
                </div>
            </div>
        </div>

        <!-- Today's log -->
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span><i class="bi bi-list-check me-2"></i>Today's Log</span>
                <button class="btn btn-sm btn-outline-secondary" onclick="loadTodayLog()">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
            <div class="card-body p-0" style="max-height:380px;overflow-y:auto">
                <table class="table table-sm table-hover mb-0" id="todayLogTable">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="todayLogBody">
                        <tr>
                            <td colspan="3" class="text-center text-muted py-3">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$extraJS = <<<'JS'
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
let html5QrCode = null;
let scanCount   = 0;
let isCooldown  = false; // prevent duplicate rapid scans

function startScanner() {
    document.getElementById('scannerStatus').textContent = 'Starting...';
    document.getElementById('scannerStatus').className = 'badge bg-warning';

    html5QrCode = new Html5Qrcode("qr-reader");

    const config = {
        fps: 10,
        qrbox: { width: 280, height: 280 },
        aspectRatio: 1.0
    };

    html5QrCode.start(
        { facingMode: "environment" },
        config,
        onScanSuccess,
        () => {} // silent error (frame miss)
    ).then(() => {
        document.getElementById('scannerStatus').textContent = 'Active';
        document.getElementById('scannerStatus').className = 'badge bg-success';
        document.getElementById('startScanBtn').classList.add('d-none');
        document.getElementById('stopScanBtn').classList.remove('d-none');
    }).catch(err => {
        document.getElementById('scannerStatus').textContent = 'Error';
        document.getElementById('scannerStatus').className = 'badge bg-danger';
        showResult('danger', '⚠️ Camera Error', 'Could not access camera. Use manual input below.<br><small>' + err + '</small>');
    });
}

function stopScanner() {
    if (html5QrCode) {
        html5QrCode.stop().then(() => {
            document.getElementById('scannerStatus').textContent = 'Inactive';
            document.getElementById('scannerStatus').className = 'badge bg-secondary';
            document.getElementById('startScanBtn').classList.remove('d-none');
            document.getElementById('stopScanBtn').classList.add('d-none');
        });
    }
}

function onScanSuccess(decodedText) {
    if (isCooldown) return;
    isCooldown = true;

    // Pause scanner briefly to prevent duplicate scans
    setTimeout(() => { isCooldown = false; }, 3000);

    processToken(decodedText);
}

function processManualScan() {
    const token = document.getElementById('manualToken').value.trim();
    if (!token) return;
    processToken(token);
    document.getElementById('manualToken').value = '';
}

function processToken(token) {
    fetch('scan_process.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'token=' + encodeURIComponent(token)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            scanCount++;
            document.getElementById('scanCount').textContent = scanCount;

            const statusClass = data.status === 'late' ? 'warning' : 'success';
            showResult(statusClass,
                (data.type === 'timeout' ? '🚪 Time Out' : '✅ ' + (data.status === 'late' ? 'Late Arrival' : 'Present')) ,
                `<strong>${data.student}</strong><br>
                 Section: ${data.section}<br>
                 Time: <strong>${data.time}</strong><br>
                 Status: <span class="badge bg-${statusClass}">${data.status.toUpperCase()}</span>
                 ${data.sms_sent ? '<br><small class="text-success"><i class="bi bi-chat-dots me-1"></i>SMS sent to parent</small>' : ''}`
            );
            loadTodayLog();
        } else {
            showResult('danger', '❌ Scan Failed', data.message);
        }

        // Beep
        playBeep(data.success);
    })
    .catch(() => {
        showResult('danger', '⚠️ Network Error', 'Could not connect to server. Please check connection.');
    });
}

function showResult(type, title, body) {
    document.getElementById('scanResultArea').innerHTML = `
        <div class="scan-result-card ${type}">
            <h5 class="fw-bold">${title}</h5>
            <p class="mb-0">${body}</p>
        </div>`;
}

function playBeep(success) {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = success ? 880 : 300;
        osc.type = 'square';
        gain.gain.setValueAtTime(0.1, ctx.currentTime);
        osc.start();
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.3);
        osc.stop(ctx.currentTime + 0.3);
    } catch(e) {}
}

function loadTodayLog() {
    fetch('get_today_log.php')
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('todayLogBody');
            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No records yet today</td></tr>';
                return;
            }
            tbody.innerHTML = data.map(r => `
                <tr>
                    <td class="fw-600">${r.name}</td>
                    <td>${r.time_in}</td>
                    <td><span class="status-badge badge-${r.status}">${r.status}</span></td>
                </tr>
            `).join('');
        });
}

// Load log on page ready
document.addEventListener('DOMContentLoaded', loadTodayLog);

// Auto-refresh log every 30 seconds
setInterval(loadTodayLog, 30000);

// Allow Enter key on manual input
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('manualToken').addEventListener('keypress', e => {
        if (e.key === 'Enter') processManualScan();
    });
});
</script>
JS;
include '../includes/footer.php';
?>