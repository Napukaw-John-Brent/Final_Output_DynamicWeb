<?php
$pageTitle = "Profile";
require_once __DIR__ . '/../config/performance.php';
$perf = new PerformanceMonitor();

include "../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$month   = date('Y-m');
$success = '';
$error   = '';

// ── Fetch user ────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Fetch stats ───────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT total_budget FROM budgets WHERE user_id=? AND month=? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("is", $user_id, $month);
$stmt->execute();
$brow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$monthly_budget = $brow ? (float)$brow['total_budget'] : 0;

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM expenses WHERE user_id=? AND DATE_FORMAT(date,'%Y-%m')=?");
$stmt->bind_param("is", $user_id, $month);
$stmt->execute();
$expense_count = (int)$stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM qr_codes WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$qr_count = (int)$stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// ── Handle: Avatar upload (sidebar only) ─────────────────────────────────
if (isset($_POST['upload_avatar']) && isset($_FILES['avatar'])) {
    $file    = $_FILES['avatar'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 2 * 1024 * 1024;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed. Please try again.';
    } elseif (!in_array($file['type'], $allowed)) {
        $error = 'Only JPG, PNG, GIF, or WEBP images are allowed.';
    } elseif ($file['size'] > $maxSize) {
        $error = 'Image must be under 2 MB.';
    } else {
        $uploadDir = '../uploads/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        if (!empty($user['avatar'])) {
            $oldPath = '../' . ltrim($user['avatar'], '/');
            if (file_exists($oldPath)) @unlink($oldPath);
        }

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            $dbPath = 'uploads/avatars/' . $filename;
            $stmt = $conn->prepare("UPDATE users SET avatar=? WHERE id=?");
            $stmt->bind_param("si", $dbPath, $user_id);
            $stmt->execute();
            $stmt->close();
            $success = 'Profile picture updated!';
            $stmt = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $error = 'Could not save the image. Check folder permissions.';
        }
    }
}

// ── Handle: Update personal info ─────────────────────────────────────────
if (isset($_POST['update_info'])) {
    $fname   = trim($_POST['first_name'] ?? '');
    $lname   = trim($_POST['last_name']  ?? '');
    $email   = trim($_POST['email']      ?? '');
    $mobile  = trim($_POST['mobile']     ?? '');
    $city    = trim($_POST['city']       ?? '');
    $country = trim($_POST['country']    ?? '');
    $full    = trim("$fname $lname");

    // Build date_of_birth from dropdowns
    $dob = null;
    if (!empty($_POST['dob_year']) && !empty($_POST['dob_month']) && !empty($_POST['dob_day'])) {
        $dob = $_POST['dob_year'] . '-'
             . str_pad($_POST['dob_month'], 2, '0', STR_PAD_LEFT) . '-'
             . str_pad($_POST['dob_day'],   2, '0', STR_PAD_LEFT);
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=? LIMIT 1");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $dup = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($dup) {
        $error = 'That email is already in use by another account.';
    } else {
        $stmt = $conn->prepare("UPDATE users SET name=?, email=?, mobile=?, city=?, country=?, date_of_birth=? WHERE id=?");
        $stmt->bind_param("ssssssi", $full, $email, $mobile, $city, $country, $dob, $user_id);
        $stmt->execute();
        $stmt->close();
        $success = 'Profile updated successfully!';
        $stmt = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// ── Handle: Change password ───────────────────────────────────────────────
if (isset($_POST['change_password'])) {
    $current  = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    if (!password_verify($current, $user['password'] ?? '')) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new_pass) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new_pass !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hash, $user_id);
        $stmt->execute();
        $stmt->close();
        $success = 'Password changed successfully!';
    }
}

// ── Handle: Delete account ────────────────────────────────────────────────
if (isset($_POST['delete_account'])) {
    $confirm_del = trim($_POST['confirm_delete'] ?? '');
    if (strtoupper($confirm_del) === 'DELETE') {
        if (!empty($user['avatar'])) {
            $p = '../' . ltrim($user['avatar'], '/');
            if (file_exists($p)) @unlink($p);
        }
        foreach (['expenses', 'budgets', 'category_budgets', 'qr_codes'] as $t) {
            $stmt = $conn->prepare("DELETE FROM $t WHERE user_id=?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        session_destroy();
        header("Location: ../auth/login.php");
        exit;
    } else {
        $error = 'Type DELETE to confirm account deletion.';
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────
$name_parts = explode(' ', $user['name'] ?? '', 2);
$first_name = $name_parts[0] ?? '';
$last_name  = $name_parts[1] ?? '';
$initials   = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
if (!$initials) $initials = strtoupper(substr($user['name'] ?? 'U', 0, 2));

$avatar_src   = !empty($user['avatar']) ? '../' . htmlspecialchars($user['avatar']) : null;
$member_since = isset($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : date('M Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="description" content="Manage your SmartBudget profile settings.">
<link rel="icon" type="image/png" sizes="32x32" href="../images/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="../images/favicon-16x16.png">
<link rel="apple-touch-icon" href="../images/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../css/style.css?v=<?= filemtime('../css/style.css') ?>">
    <link rel="stylesheet" href="../css/responsive.css">
<title><?= $pageTitle ?> – SmartBudget</title>
</head>
<body class="app-page">
<div class="container figma-container">

  <!-- ══ HEADER ══ -->
  <?php include '../includes/header.php'; ?>

  <div class="profile-wrap">

    <div class="profile-toprow">
      <h2>Your Profile</h2>
      <p>Manage your personal information, security and preferences.</p>
    </div>

    <?php if ($success): ?>
      <div class="alert success">✓ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert error">✕ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="profile-grid">

      <!-- ══ LEFT SIDEBAR ══ -->
      <div class="profile-sidebar">

        <!-- Avatar card — click to change photo -->
        <div class="avatar-card">
          <form method="POST" enctype="multipart/form-data" id="sidebarAvatarForm">
            <input type="hidden" name="upload_avatar" value="1">
            <label class="avatar-circle" title="Click to change profile picture">
              <?php if ($avatar_src): ?>
                <img src="<?= $avatar_src ?>" alt="Avatar" id="sidebarAvatarImg">
              <?php else: ?>
                <span id="sidebarInitials"><?= htmlspecialchars($initials) ?></span>
                <img src="" alt="" id="sidebarAvatarImg" class="avatar-img-hidden">
              <?php endif; ?>
              <div class="avatar-hover-overlay">
                <span>Change</span>
              </div>
              <input type="file" name="avatar" id="sidebarAvatarInput"
                     accept="image/jpeg,image/png,image/gif,image/webp"
                     class="avatar-file-input"
                     onchange="sidebarAvatarPreview(this)">
            </label>
          </form>
          <div class="avatar-name"><?= htmlspecialchars($user['name'] ?? 'User') ?></div>
          <div class="avatar-email"><?= htmlspecialchars($user['email'] ?? '') ?></div>
          <div class="member-badge">Member since <?= $member_since ?></div>
          <div class="stats-strip">
            <div class="stat-item">
              <span class="stat-val">₱<?= number_format($monthly_budget / 1000, 0) ?>k</span>
              <span class="stat-lbl">Monthly<br>Budget</span>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item">
              <span class="stat-val"><?= $expense_count ?></span>
              <span class="stat-lbl">Expenses</span>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item">
              <span class="stat-val"><?= $qr_count ?></span>
              <span class="stat-lbl">QR Codes</span>
            </div>
          </div>
        </div>

        <!-- Quick actions -->
        <div class="quick-card">
          <div class="quick-label">Quick Actions</div>
          <button class="quick-btn active" onclick="showPanel('info',this)">Personal Information</button>
          <button class="quick-btn"        onclick="showPanel('password',this)">Change Password</button>
          <button class="quick-btn danger" onclick="showPanel('delete',this)">Delete Account</button>
          <div class="quick-card-divider"></div>
          <a href="../auth/logout.php" class="quick-logout-link">Log Out</a>
        </div>

      </div><!-- /sidebar -->

      <!-- ══ RIGHT CONTENT ══ -->
      <div class="profile-content">

        <!-- PANEL 1: Personal Information (no upload photo block) -->
        <div class="profile-panel active" id="panel-info">
          <div class="panel-title">Personal Information</div>

          <form method="POST">
            <input type="hidden" name="update_info" value="1">

            <div class="pf-grid-2">
              <div class="pf-field">
                <label class="pf-label" for="fn">First Name</label>
                <input class="pf-input" type="text" id="fn" name="first_name"
                       value="<?= htmlspecialchars($first_name) ?>" placeholder="First name">
              </div>
              <div class="pf-field">
                <label class="pf-label" for="ln">Last Name</label>
                <input class="pf-input" type="text" id="ln" name="last_name"
                       value="<?= htmlspecialchars($last_name) ?>" placeholder="Last name">
              </div>
            </div>

            <div class="pf-grid-1 pf-field">
              <label class="pf-label" for="em">Email Address</label>
              <input class="pf-input" type="email" id="em" name="email"
                     value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="email@example.com">
            </div>
            <div class="pf-grid-1 pf-field">
              <label class="pf-label" for="mob">Mobile Number</label>
              <input class="pf-input" type="text" id="mob" name="mobile"
                     value="<?= htmlspecialchars($user['mobile'] ?? '') ?>" placeholder="+63 XXX XXX XXXX">
            </div>

            <!-- Birthday -->
            <?php
              $dob_val   = $user['date_of_birth'] ?? '';
              $dob_parts = $dob_val ? explode('-', $dob_val) : ['','',''];
              $dob_y = $dob_parts[0] ?? '';
              $dob_m = isset($dob_parts[1]) ? ltrim($dob_parts[1], '0') : '';
              $dob_d = isset($dob_parts[2]) ? ltrim($dob_parts[2], '0') : '';
            ?>
            <div class="pf-grid-1 pf-field">
              <label class="pf-label">Date of Birth</label>
              <div class="dob-grid">
                <div class="dob-select-wrap">
                  <select name="dob_month" class="pf-input dob-select">
                    <option value="">Month</option>
                    <?php
                    $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                    foreach ($months as $i => $m):
                      $val = $i + 1;
                      $sel = ($dob_m == $val) ? 'selected' : '';
                    ?>
                    <option value="<?= $val ?>" <?= $sel ?>><?= $m ?></option>
                    <?php endforeach; ?>
                  </select>
                  <span class="dob-select-arrow">▼</span>
                </div>
                <div class="dob-select-wrap">
                  <select name="dob_day" class="pf-input dob-select">
                    <option value="">Day</option>
                    <?php for ($d = 1; $d <= 31; $d++): $sel = ($dob_d == $d) ? 'selected' : ''; ?>
                    <option value="<?= $d ?>" <?= $sel ?>><?= $d ?></option>
                    <?php endfor; ?>
                  </select>
                  <span class="dob-select-arrow">▼</span>
                </div>
                <div class="dob-select-wrap">
                  <select name="dob_year" class="pf-input dob-select">
                    <option value="">Year</option>
                    <?php for ($y = date('Y') - 13; $y >= 1920; $y--): $sel = ($dob_y == $y) ? 'selected' : ''; ?>
                    <option value="<?= $y ?>" <?= $sel ?>><?= $y ?></option>
                    <?php endfor; ?>
                  </select>
                  <span class="dob-select-arrow">▼</span>
                </div>
              </div>
            </div>

            <div class="pf-grid-2">
              <div class="pf-field">
                <label class="pf-label" for="cty">City</label>
                <input class="pf-input" type="text" id="cty" name="city"
                       value="<?= htmlspecialchars($user['city'] ?? '') ?>" placeholder="Angeles">
              </div>
              <div class="pf-field">
                <label class="pf-label" for="ctr">Country</label>
                <input class="pf-input" type="text" id="ctr" name="country"
                       value="<?= htmlspecialchars($user['country'] ?? 'Philippines') ?>" placeholder="Philippines">
              </div>
            </div>

            <div class="pf-divider"></div>
            <div class="pf-actions">
              <button type="reset"  class="btn-discard">Discard</button>
              <button type="submit" class="btn-save">Save Changes</button>
            </div>
          </form>
        </div>

        <!-- PANEL 2: Change Password -->
        <div class="profile-panel" id="panel-password">
          <div class="panel-title">Change Password</div>
          <form method="POST">
            <input type="hidden" name="change_password" value="1">
            <div class="pf-grid-1 pf-field">
              <label class="pf-label">Current Password</label>
              <div class="pwd-wrap">
                <input class="pf-input" type="password" name="current_password"
                       id="cur_pwd" placeholder="Current Password">
                <span class="pwd-eye" onclick="togglePwd('cur_pwd',this)">👁</span>
              </div>
            </div>
            <div class="pf-grid-2-mt">
              <div class="pf-field">
                <label class="pf-label">New Password</label>
                <div class="pwd-wrap">
                  <input class="pf-input" type="password" name="new_password"
                         id="new_pwd" placeholder="New Password">
                  <span class="pwd-eye" onclick="togglePwd('new_pwd',this)">👁</span>
                </div>
              </div>
              <div class="pf-field">
                <label class="pf-label">Confirm New Password</label>
                <div class="pwd-wrap">
                  <input class="pf-input" type="password" name="confirm_password"
                         id="con_pwd" placeholder="Confirm Password">
                  <span class="pwd-eye" onclick="togglePwd('con_pwd',this)">👁</span>
                </div>
              </div>
            </div>
            <div class="pf-divider"></div>
            <div class="pf-actions">
              <button type="reset"  class="btn-discard">Clear</button>
              <button type="submit" class="btn-save">Update Password</button>
            </div>
          </form>
        </div>

        <!-- PANEL 3: Delete Account -->
        <div class="profile-panel" id="panel-delete">
          <div class="panel-title panel-title-danger">⚠ Delete Account</div>
          <p class="delete-panel-desc">
            This will permanently delete your account and all associated data including
            expenses, budgets, and QR codes.
            <strong class="text-danger-strong">This action cannot be undone.</strong>
          </p>
          <div class="delete-panel">
            <div class="delete-info">
              <h4>Delete Account</h4>
              <p>Permanently remove your account and all data. This action cannot be undone.</p>
            </div>
            <button type="button" class="btn-delete-acct" onclick="openDeleteModal()">
              Delete My Account
            </button>
          </div>
        </div>

      </div><!-- /content -->
    </div><!-- /grid -->
  </div><!-- /profile-wrap -->
</div><!-- /container -->

<!-- ── DELETE CONFIRM MODAL ── -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-box">
    <h4>⚠ Confirm Account Deletion</h4>
    <p>This will permanently delete your account and <strong>all your data</strong>.<br>
       Type <strong>DELETE</strong> below to confirm.</p>
    <form method="POST">
      <input type="hidden" name="delete_account" value="1">
      <input type="text" name="confirm_delete" placeholder="Type DELETE to confirm" autocomplete="off">
      <div class="modal-actions">
        <button type="button" class="modal-cancel" onclick="closeDeleteModal()">Cancel</button>
        <button type="submit" class="modal-confirm-del">Delete My Account</button>
      </div>
    </form>
  </div>
</div>

<script>
/* Panel switcher */
function showPanel(id, btn) {
  document.querySelectorAll('.profile-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.quick-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('panel-' + id).classList.add('active');
  if (btn) btn.classList.add('active');
}

/* Password show/hide */
function togglePwd(id, el) {
  const inp = document.getElementById(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
  el.style.opacity = inp.type === 'text' ? '1' : '.45';
}

/* Delete modal */
function openDeleteModal()  { document.getElementById('deleteModal').classList.add('open'); }
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('open'); }
document.getElementById('deleteModal').addEventListener('click', e => {
  if (e.target === document.getElementById('deleteModal')) closeDeleteModal();
});

/* Sidebar avatar: preview then auto-submit */
function sidebarAvatarPreview(input) {
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];
  const maxMB = 2 * 1024 * 1024;
  if (!['image/jpeg','image/png','image/gif','image/webp'].includes(file.type)) {
    alert('Only JPG, PNG, GIF or WEBP allowed.');
    input.value = ''; return;
  }
  if (file.size > maxMB) {
    alert('Image must be under 2 MB.');
    input.value = ''; return;
  }
  const img  = document.getElementById('sidebarAvatarImg');
  const span = document.getElementById('sidebarInitials');
  const reader = new FileReader();
  reader.onload = e => {
    img.src = e.target.result;
    img.style.display = 'block';
    if (span) span.style.display = 'none';
    setTimeout(() => document.getElementById('sidebarAvatarForm').submit(), 300);
  };
  reader.readAsDataURL(file);
}

/* Auto-open correct panel on form error */
<?php if ($error && isset($_POST['change_password'])): ?>
  showPanel('password', document.querySelectorAll('.quick-btn')[1]);
<?php elseif ($error && isset($_POST['delete_account'])): ?>
  showPanel('delete', document.querySelectorAll('.quick-btn')[2]);
<?php endif; ?>
</script>
<?php $perf->displayStats(); ?>
</body>
</html>