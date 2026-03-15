<?php
include "../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Handle delete
if (!empty($_POST['delete_id'])) {
    $delete_id = (int) $_POST['delete_id'];
    $stmt = $conn->prepare("DELETE FROM qr_codes WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $delete_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: qr-saver.php");
    exit;
}

// Handle save (dedupe per user)
if (isset($_POST['qr'])) {
    $qr_data = trim((string) $_POST['qr']);
    if ($qr_data !== '') {
        $stmt = $conn->prepare("SELECT id FROM qr_codes WHERE user_id = ? AND qr_data = ? LIMIT 1");
        $stmt->bind_param("is", $user_id, $qr_data);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$exists) {
            $stmt = $conn->prepare("INSERT INTO qr_codes (user_id, qr_data) VALUES (?, ?)");
            $stmt->bind_param("is", $user_id, $qr_data);
            $stmt->execute();
            $stmt->close();
        }
    }
    header("Location: qr-saver.php");
    exit;
}

$stmt = $conn->prepare("SELECT id, qr_data FROM qr_codes WHERE user_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$qr_list = [];
while ($row = $result->fetch_assoc()) $qr_list[] = $row;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Saved QR Codes – SmartBudget</title>
  <link rel="stylesheet" href="../css/style.css">
  <!-- jsQR: works in ALL browsers, no BarcodeDetector needed -->
  <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
  <style>
    body.app-page .container.figma-container { padding-top: 0 !important; }
    .page-header {
      margin-bottom: 0.75rem !important;
      padding-top: 1.25rem !important;
      padding-bottom: 0.75rem !important;
    }

    .qr-wrap { padding: 16px 0 60px; }

    .qr-toprow {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 20px;
    }
    .qr-toprow h2 { font-size: 1.35rem; font-weight: 700; color: #fff; margin: 0; }
    .qr-toprow h2 span { color: #00CC99; }
    .qr-count-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(0,204,153,.15);
      color: #00CC99;
      font-size: .78rem;
      font-weight: 700;
      padding: 6px 16px;
      border-radius: 99px;
      border: 1px solid rgba(0,204,153,.25);
    }

    .qr-top-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 18px;
      margin-bottom: 18px;
    }
    @media(max-width: 700px) { .qr-top-grid { grid-template-columns: 1fr; } }

    .teal-card-title { font-size: .95rem; font-weight: 700; color: #1A2828; margin-bottom: 16px; }

    /* ── Scanner ── */
    .scanner-box {
      background: rgba(0,0,0,.18);
      border-radius: 12px;
      overflow: hidden;
      aspect-ratio: 1 / 1;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      margin-bottom: 12px;
    }
    #qr-video {
      width: 100%; height: 100%;
      object-fit: cover; display: none;
    }
    /* Hidden canvas used by jsQR — never shown */
    #qr-canvas { display: none; }

    .scanner-overlay {
      position: absolute; inset: 0;
      display: flex; align-items: center; justify-content: center;
      pointer-events: none;
    }
    .scanner-frame {
      width: 60%; aspect-ratio: 1/1;
      border: 3px solid #00CC99;
      border-radius: 12px;
      box-shadow: 0 0 0 9999px rgba(0,0,0,.35);
    }
    .scanner-line {
      position: absolute;
      left: 20%; right: 20%;
      height: 2px;
      background: linear-gradient(90deg, transparent, #00CC99, transparent);
      animation: scan 2s ease-in-out infinite;
      display: none;
    }
    @keyframes scan {
      0%   { top: 20%; }
      50%  { top: 78%; }
      100% { top: 20%; }
    }
    .scanner-placeholder {
      display: flex; flex-direction: column;
      align-items: center; gap: 10px;
      color: rgba(26,40,40,.55);
    }
    .scanner-placeholder-icon { font-size: 2.8rem; }
    .scanner-placeholder-text { font-size: .8rem; font-weight: 600; }

    /* Status badge shown inside scanner while active */
    .scanner-status {
      position: absolute;
      bottom: 10px; left: 50%; transform: translateX(-50%);
      background: rgba(0,0,0,.65);
      color: #00CC99;
      font-size: .7rem; font-weight: 700;
      padding: 4px 14px; border-radius: 99px;
      display: none; white-space: nowrap;
    }
    .scanner-status.show { display: block; }

    .btn-start-scan {
      width: 100%; padding: 11px;
      background: rgba(0,0,0,.2);
      color: #1A2828;
      border: 2px solid rgba(26,40,40,.3);
      border-radius: 8px;
      font-size: .875rem; font-weight: 700;
      cursor: pointer; font-family: inherit;
      transition: all .2s; margin-bottom: 8px;
    }
    .btn-start-scan:hover { background: rgba(0,0,0,.3); }
    .btn-start-scan.scanning { background: #1A2828; color: #00CC99; border-color: #1A2828; }

    /* Toast notification for auto-save feedback */
    .qr-toast {
      display: none;
      align-items: center; gap: 8px;
      background: #065f46; color: #d1fae5;
      border: 1px solid #6ee7b7;
      border-radius: 8px; padding: 10px 14px;
      font-size: .82rem; font-weight: 600;
      margin-top: 8px; word-break: break-all;
    }
    .qr-toast.show { display: flex; }
    .qr-toast.duplicate { background: #1e3a5f; color: #bfdbfe; border-color: #93c5fd; }

    /* ── Save form ── */
    .qr-save-form { display: flex; flex-direction: column; gap: 10px; }
    .qr-save-form label {
      font-size: .72rem; font-weight: 700; color: #1A2828;
      opacity: .7; text-transform: uppercase; letter-spacing: .7px;
    }
    .qr-save-form textarea {
      width: 100%; padding: 10px 14px; border-radius: 8px;
      border: none; background: #fff; color: #1A2828;
      font-size: .875rem; font-family: inherit; outline: none;
      box-shadow: 0 2px 8px rgba(0,0,0,.12);
      transition: box-shadow .2s; resize: vertical;
    }
    .qr-save-form textarea:focus { box-shadow: 0 0 0 3px rgba(0,204,153,.35); }
    .qr-save-form textarea::placeholder { color: #aaa; }
    .btn-save-qr {
      width: 100%; padding: 11px;
      background: #1A2828; color: #fff;
      border: none; border-radius: 8px;
      font-size: .875rem; font-weight: 700;
      cursor: pointer; font-family: inherit;
      transition: background .2s, transform .15s;
    }
    .btn-save-qr:hover { background: #0d1a1a; transform: translateY(-1px); }
    .qr-hint {
      font-size: .7rem; color: rgba(26,40,40,.55);
      font-style: italic; line-height: 1.5; margin-top: 2px;
    }

    /* ── Section divider ── */
    .qr-section-hdr {
      font-size: .7rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: 1.1px; color: rgba(255,255,255,.35);
      margin: 6px 0 12px;
      display: flex; align-items: center; gap: 10px;
    }
    .qr-section-hdr::after {
      content: ''; flex: 1; height: 1px; background: rgba(255,255,255,.07);
    }

    /* ── Saved QR list ── */
    .qr-list { display: flex; flex-direction: column; gap: 10px; }
    .qr-item-row {
      display: flex; align-items: center;
      justify-content: space-between; gap: 12px;
      background: rgba(255,255,255,.18);
      border-radius: 10px; padding: 13px 16px;
      transition: background .15s;
    }
    .qr-item-row:hover { background: rgba(255,255,255,.26); }
    .qr-item-left { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
    .qr-item-icon { font-size: 1.2rem; flex-shrink: 0; }
    .qr-item-data { font-size: .82rem; font-weight: 600; color: #1A2828; word-break: break-all; line-height: 1.4; }
    .qr-item-actions { display: flex; gap: 7px; flex-shrink: 0; }
    .btn-qr-copy {
      padding: 6px 14px; background: #1A2828; color: #fff;
      border: none; border-radius: 6px;
      font-size: .75rem; font-weight: 700;
      cursor: pointer; font-family: inherit;
      transition: background .15s; white-space: nowrap;
    }
    .btn-qr-copy:hover  { background: #0d1a1a; }
    .btn-qr-copy.copied { background: #005c3a; }
    .btn-qr-delete {
      padding: 6px 12px; background: rgba(180,0,0,.18);
      color: #7a0000; border: 1px solid rgba(180,0,0,.25);
      border-radius: 6px; font-size: .75rem; font-weight: 700;
      cursor: pointer; font-family: inherit;
      transition: background .15s; white-space: nowrap;
    }
    .btn-qr-delete:hover { background: rgba(180,0,0,.3); }

    .qr-empty { text-align: center; padding: 40px 20px; color: rgba(26,40,40,.5); }
    .qr-empty-icon { font-size: 2.8rem; margin-bottom: 10px; }
    .qr-empty-text { font-size: .85rem; font-weight: 500; line-height: 1.6; }
  </style>
</head>
<body class="app-page">
<div class="container figma-container">

  <header class="page-header">
    <div class="brand">
      <img src="../images/smartbudget-logo.jpg" alt="SmartBudget" class="logo-img" style="height:40px;width:auto;">
      <span class="brand-name">SmartBudget</span>
    </div>
    <nav>
      <a href="../dashboard/index.php" class="nav-link">Dashboard</a>
      <a href="../deals/stats.php"     class="nav-link">Stats</a>
      <a href="qr-saver.php"           class="nav-link active">Saved QR Codes</a>
      <a href="../deals/profile.php"   class="nav-link">Profile</a>
    </nav>
  </header>

  <div class="qr-wrap">

    <div class="qr-toprow">
      <h2>Saved <span>QR Codes</span></h2>
      <span class="qr-count-pill"><?= count($qr_list) ?> saved</span>
    </div>

    <div class="qr-top-grid">

      <!-- CAMERA SCANNER -->
      <div class="card figma-teal">
        <div class="teal-card-title">Scan QR Code</div>
        <div class="scanner-box" id="scannerBox">
          <video id="qr-video" autoplay playsinline muted></video>
          <canvas id="qr-canvas"></canvas>
          <div class="scanner-overlay" id="scannerOverlay">
            <div class="scanner-frame" id="scannerFrame" style="display:none;"></div>
            <div class="scanner-line"  id="scanLine"></div>
          </div>
          <div class="scanner-placeholder" id="scanPlaceholder">
            <div class="scanner-placeholder-icon">📷</div>
            <div class="scanner-placeholder-text">Camera off</div>
          </div>
          <div class="scanner-status" id="scannerStatus">🔍 Scanning…</div>
        </div>
        <button type="button" class="btn-start-scan" id="btnScan" onclick="toggleScan()">
          Start Camera Scan
        </button>
        <!-- Auto-save feedback toast -->
        <div class="qr-toast" id="scanToast"></div>
      </div>

      <!-- MANUAL SAVE FORM -->
      <div class="card figma-teal">
        <div class="teal-card-title">Save QR Manually</div>
        <form method="POST" class="qr-save-form" id="saveForm">
          <label for="qrInput">QR / Payment Data</label>
          <textarea id="qrInput"
                    name="qr"
                    rows="5"
                    placeholder="Paste or type QR code data here…"></textarea>
          <button type="submit" class="btn-save-qr">Save QR Code</button>
        </form>
        <p class="qr-hint">Scanned QR data is auto-filled and auto-saved. You can also paste manually.</p>
      </div>

    </div>

    <!-- SAVED LIST -->
    <div class="qr-section-hdr">Saved QR Codes</div>
    <div class="card figma-teal">
      <div class="teal-card-title">Your Saved Codes
        <span style="font-size:.75rem;font-weight:500;color:rgba(26,40,40,.55);margin-left:8px;"><?= count($qr_list) ?> total</span>
      </div>

      <?php if (empty($qr_list)): ?>
        <div class="qr-empty">
          <div class="qr-empty-icon">🗂️</div>
          <div class="qr-empty-text">No saved QR codes yet.<br>Scan or paste data above to get started.</div>
        </div>
      <?php else: ?>
        <div class="qr-list">
          <?php foreach ($qr_list as $qr): ?>
            <div class="qr-item-row">
              <div class="qr-item-left">
                <div class="qr-item-icon">◼</div>
                <div class="qr-item-data"><?= htmlspecialchars($qr['qr_data']) ?></div>
              </div>
              <div class="qr-item-actions">
                <button type="button"
                        class="btn-qr-copy"
                        data-copy="<?= htmlspecialchars($qr['qr_data'], ENT_QUOTES) ?>">
                  Copy
                </button>
                <form method="POST" style="display:inline;margin:0;">
                  <input type="hidden" name="delete_id" value="<?= (int)$qr['id'] ?>">
                  <button type="submit"
                          class="btn-qr-delete"
                          onclick="return confirm('Delete this QR code?')">
                    Delete
                  </button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
/* ════════════════════════════════════════════
   COPY BUTTON
════════════════════════════════════════════ */
document.addEventListener('click', e => {
  const btn = e.target.closest('button[data-copy]');
  if (!btn) return;
  const text = btn.getAttribute('data-copy') || '';
  const done = () => {
    btn.textContent = '✓ Copied';
    btn.classList.add('copied');
    setTimeout(() => { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 1400);
  };
  navigator.clipboard
    ? navigator.clipboard.writeText(text).then(done).catch(() => fallbackCopy(text, done))
    : fallbackCopy(text, done);
});
function fallbackCopy(text, cb) {
  const ta = document.createElement('textarea');
  ta.value = text; ta.style.cssText = 'position:fixed;opacity:0';
  document.body.appendChild(ta); ta.select();
  try { document.execCommand('copy'); cb(); } catch(e) {}
  document.body.removeChild(ta);
}

/* ════════════════════════════════════════════
   QR SCANNER  (jsQR — works in all browsers)
════════════════════════════════════════════ */
let scanning  = false;
let stream    = null;
let rafId     = null;
let lastValue = null;   // prevent duplicate auto-saves in same session

const video      = document.getElementById('qr-video');
const canvas     = document.getElementById('qr-canvas');
const ctx        = canvas.getContext('2d');
const btnScan    = document.getElementById('btnScan');
const placeholder= document.getElementById('scanPlaceholder');
const scanLine   = document.getElementById('scanLine');
const scanFrame  = document.getElementById('scannerFrame');
const statusBadge= document.getElementById('scannerStatus');
const toast      = document.getElementById('scanToast');

async function toggleScan() {
  scanning ? stopScan() : await startScan();
}

async function startScan() {
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }
    });
    video.srcObject = stream;
    await video.play();

    video.style.display     = 'block';
    placeholder.style.display = 'none';
    scanLine.style.display  = 'block';
    scanFrame.style.display = 'block';
    statusBadge.classList.add('show');
    btnScan.textContent = '⏹ Stop Scanning';
    btnScan.classList.add('scanning');
    scanning = true;
    lastValue = null;
    requestAnimationFrame(tick);
  } catch(err) {
    alert('Camera access denied or unavailable.\nUse the manual paste field instead.');
  }
}

function stopScan() {
  scanning = false;
  if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
  if (rafId)  { cancelAnimationFrame(rafId); rafId = null; }

  video.srcObject = null;
  video.style.display     = 'none';
  placeholder.style.display = 'flex';
  scanLine.style.display  = 'none';
  scanFrame.style.display = 'none';
  statusBadge.classList.remove('show');
  btnScan.textContent = 'Start Camera Scan';
  btnScan.classList.remove('scanning');
}

function tick() {
  if (!scanning) return;

  if (video.readyState === video.HAVE_ENOUGH_DATA && video.videoWidth > 0) {
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const code = jsQR(imageData.data, imageData.width, imageData.height, {
      inversionAttempts: 'dontInvert'
    });

    if (code && code.data && code.data !== lastValue) {
      lastValue = code.data;
      onQRDetected(code.data);
      return; // stop loop — auto-save will reload the page
    }
  }

  rafId = requestAnimationFrame(tick);
}

function onQRDetected(value) {
  stopScan();

  // Fill manual field
  document.getElementById('qrInput').value = value;

  // Show toast
  showToast('⏳ QR detected — saving…', false);

  // Auto-submit to PHP via hidden form (POST)
  const form  = document.createElement('form');
  form.method = 'POST';
  form.action = 'qr-saver.php';

  const input  = document.createElement('input');
  input.type   = 'hidden';
  input.name   = 'qr';
  input.value  = value;
  form.appendChild(input);

  document.body.appendChild(form);

  // Short delay so user sees the toast before page reloads
  setTimeout(() => form.submit(), 600);
}

function showToast(msg, isDuplicate) {
  toast.textContent = msg;
  toast.className   = 'qr-toast show' + (isDuplicate ? ' duplicate' : '');
}
</script>
</body>
</html>