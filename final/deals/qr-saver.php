<?php
$pageTitle = "QR Codes";
require_once __DIR__ . '/../config/performance.php';
$perf = new PerformanceMonitor();

include "../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

if (!empty($_POST['delete_id'])) {
    $delete_id = (int) $_POST['delete_id'];
    $stmt = $conn->prepare("DELETE FROM qr_codes WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $delete_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: qr-saver.php");
    exit;
}

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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Scan and save QR codes with SmartBudget.">
<link rel="icon" type="image/png" sizes="32x32" href="../images/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="../images/favicon-16x16.png">
<link rel="apple-touch-icon" href="../images/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../css/style.css?v=<?= filemtime('../css/style.css') ?>">
    <link rel="stylesheet" href="../css/responsive.css">
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<title><?= $pageTitle ?> – SmartBudget</title>
</head>
<body class="app-page">
<div class="container figma-container">

  <?php include '../includes/header.php'; ?>

  <div class="qr-wrap">

    <div class="qr-toprow">
      <h2>Saved <span>QR Codes</span></h2>
      <span class="qr-count-pill"><?= count($qr_list) ?> saved</span>
    </div>

    <div class="qr-top-grid">

      <!-- CAMERA SCANNER -->
      <div class="card figma-teal">
        <div class="teal-card-title">Scan QR Code</div>
        <div class="scanner-card-inner">
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
            <div class="scanner-status" id="scannerStatus">Scanning…</div>
          </div>
          <button type="button" class="btn-start-scan" id="btnScan" onclick="toggleScan()">
            Start Camera Scan
          </button>
          <div class="qr-toast" id="scanToast"></div>
        </div>
      </div>

      <!-- MANUAL SAVE FORM -->
      <div class="card figma-teal">
        <div class="teal-card-title">Save QR Manually</div>
        <form method="POST" class="qr-save-form" id="saveForm">
          <label for="qrInput">QR / Payment Data</label>
          <textarea id="qrInput"
                    name="qr"
                    placeholder="Paste or type QR code data here…"></textarea>
          <button type="submit" class="btn-save-qr">Save QR Code</button>
        </form>
        <p class="qr-hint">Scanned QR data is auto-filled and auto-saved. You can also paste manually.</p>
      </div>

    </div>

    <!-- SAVED LIST -->
    <div class="qr-section-hdr qr-section-hdr-spaced">Saved QR Codes</div>
    <div class="card figma-teal">
      <div class="teal-card-title">Your Saved Codes
        <span class="qr-list-count"><?= count($qr_list) ?> total</span>
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
/* ── Copy button ── */
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

/* ── QR Scanner ── */
let scanning  = false;
let stream    = null;
let rafId     = null;
let lastValue = null;

const video       = document.getElementById('qr-video');
const canvas      = document.getElementById('qr-canvas');
const ctx         = canvas.getContext('2d');
const btnScan     = document.getElementById('btnScan');
const placeholder = document.getElementById('scanPlaceholder');
const scanLine    = document.getElementById('scanLine');
const scanFrame   = document.getElementById('scannerFrame');
const statusBadge = document.getElementById('scannerStatus');
const toast       = document.getElementById('scanToast');

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

    video.style.display       = 'block';
    placeholder.style.display = 'none';
    scanLine.style.display    = 'block';
    scanFrame.style.display   = 'block';
    statusBadge.classList.add('show');
    btnScan.textContent = '⏹ Stop Scanning';
    btnScan.classList.add('scanning');
    scanning  = true;
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

  video.srcObject           = null;
  video.style.display       = 'none';
  placeholder.style.display = 'flex';
  scanLine.style.display    = 'none';
  scanFrame.style.display   = 'none';
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
    const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'dontInvert' });
    if (code && code.data && code.data !== lastValue) {
      lastValue = code.data;
      onQRDetected(code.data);
      return;
    }
  }
  rafId = requestAnimationFrame(tick);
}

function onQRDetected(value) {
  stopScan();
  document.getElementById('qrInput').value = value;
  showToast('⏳ QR detected — saving…', false);
  const form   = document.createElement('form');
  form.method  = 'POST';
  form.action  = 'qr-saver.php';
  const input  = document.createElement('input');
  input.type   = 'hidden';
  input.name   = 'qr';
  input.value  = value;
  form.appendChild(input);
  document.body.appendChild(form);
  setTimeout(() => form.submit(), 600);
}

function showToast(msg, isDuplicate) {
  toast.textContent = msg;
  toast.className   = 'qr-toast show' + (isDuplicate ? ' duplicate' : '');
}
</script>
<?php $perf->displayStats(); ?>
</body>
</html>