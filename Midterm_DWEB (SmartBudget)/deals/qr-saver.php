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
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>QR Saver</title>
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .qr-row { display: flex; gap: 8px; align-items: center; justify-content: space-between; }
    .qr-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .btn-small { padding: 6px 10px; border-radius: 8px; border: 1px solid rgba(0,0,0,.15); background: #fff; cursor: pointer; font-size: 12px; }
    .btn-danger { border-color: rgba(200,0,0,.35); color: #a00; }
    .qr-text { word-break: break-word; }
    .hint { font-size: 12px; opacity: .75; margin-top: 8px; }
  </style>
</head>
<body class="app-page">
  <div class="container">
    <header class="page-header">
      <h1>QR Saver</h1>
      <nav>
        <a href="../dashboard/index.php">Dashboard</a> |
        <a href="deals.php">Deals</a> |
        <a href="../auth/logout.php">Logout</a>
      </nav>
    </header>

    <div class="card">
      <form method="POST">
        <input name="qr" type="text" placeholder="Paste QR data">
        <button type="submit">Save QR</button>
      </form>
      <p class="hint">Tip: On the Dashboard, you can pick a saved QR to auto-fill the expense description.</p>
    </div>

    <div class="card">
      <h3>Saved QRs</h3>
      <?php
      $hasQr = false;
      while ($qr = $result->fetch_assoc()):
        $hasQr = true;
      ?>
        <div class="qr-item qr-row">
          <div class="qr-text"><?= htmlspecialchars($qr['qr_data']) ?></div>
          <div class="qr-actions">
            <button type="button" class="btn-small" data-copy="<?= htmlspecialchars($qr['qr_data'], ENT_QUOTES) ?>">Copy</button>
            <form method="POST" style="display:inline">
              <input type="hidden" name="delete_id" value="<?= (int) $qr['id'] ?>">
              <button type="submit" class="btn-small btn-danger" onclick="return confirm('Delete this saved QR?')">Delete</button>
            </form>
          </div>
        </div>
      <?php endwhile; ?>
      <?php if (!$hasQr): ?>
        <p class="empty-state">No saved QR codes yet. Paste data above to save.</p>
      <?php endif; ?>
    </div>
  </div>
  <script>
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-copy]');
      if (!btn) return;
      const text = btn.getAttribute('data-copy') || '';
      if (!text) return;
      navigator.clipboard?.writeText(text).then(() => {
        const old = btn.textContent;
        btn.textContent = 'Copied';
        setTimeout(() => (btn.textContent = old), 900);
      }).catch(() => {
        // fallback
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (err) {}
        document.body.removeChild(ta);
      });
    });
  </script>
</body>
</html>
