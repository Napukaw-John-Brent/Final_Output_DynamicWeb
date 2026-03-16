<?php
$pageTitle = "Profile";
require_once __DIR__ . '/../config/performance.php'; // ADD THIS
$perf = new PerformanceMonitor(); // ADD THIS

include "../config/db.php";
// ... rest of your code

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

// ── Handle: Avatar upload ─────────────────────────────────────────────────
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

        // Remove old avatar from disk
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

    $stmt = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=? LIMIT 1");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $dup = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($dup) {
        $error = 'That email is already in use by another account.';
    } else {
        $stmt = $conn->prepare("UPDATE users SET name=?, email=?, mobile=?, city=?, country=? WHERE id=?");
        $stmt->bind_param("sssssi", $full, $email, $mobile, $city, $country, $user_id);
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
<meta name="viewport" content="width=device-width, initial-scale=1.0"/> <!-- Changed to 1.0 -->
<meta name="description" content="Manage your SmartBudget profile settings. Update personal information, change password, upload avatar, and manage account security.">
<meta name="keywords" content="profile settings, account management, change password, avatar upload, user profile, security settings">
<meta name="author" content="SmartBudget Team">

<!-- Open Graph / Social Media -->
<meta property="og:title" content="Profile Settings - SmartBudget">
<meta property="og:description" content="Manage your account settings and personal information with SmartBudget.">
<meta property="og:image" content="../images/smartbudget-og.jpg">
<meta property="og:url" content="<?= 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>">
<meta property="og:type" content="website">

<!-- Favicon -->
<link rel="icon" type="image/png" sizes="32x32" href="../images/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="../images/favicon-16x16.png">
<link rel="apple-touch-icon" href="../images/apple-touch-icon.png">

<!-- Preconnect for fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<!-- CSS with cache busting -->
<link rel="stylesheet" href="../css/style.css?v=<?= filemtime('../css/style.css') ?>">

<title><?= $pageTitle ?> – SmartBudget</title>

<style>
/* ── YOUR EXISTING PROFILE STYLES ── KEEP ALL OF THESE! */
/* ── Spacing reset ── */
body.app-page .container.figma-container { padding-top: 0 !important; }
.page-header {
  margin-bottom: 0.75rem !important;
  padding-top: 1.25rem !important;
  padding-bottom: 0.75rem !important;
}

.profile-wrap { padding: 16px 0 60px; }

.profile-toprow { margin-bottom: 6px; }
.profile-toprow h2 { font-size: 1.35rem; font-weight: 700; color: #fff; margin: 0 0 2px; }
.profile-toprow p  { font-size: .78rem; color: rgba(255,255,255,.45); margin: 0; }

/* ── 2-col grid ── */
.profile-grid {
  display: grid;
  grid-template-columns: 260px 1fr;
  gap: 18px;
  align-items: start;
  margin-top: 20px;
}
@media(max-width: 800px) { .profile-grid { grid-template-columns: 1fr; } }

/* ══ SIDEBAR ══ */
.profile-sidebar { display: flex; flex-direction: column; gap: 14px; }

.avatar-card {
  background: var(--card-teal);
  border-radius: 16px;
  padding: 28px 20px 22px;
  text-align: center;
}
.avatar-circle {
  width: 80px; height: 80px; border-radius: 50%;
  background: rgba(255,255,255,.9);
  color: #0D9488;
  font-size: 1.8rem; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 14px;
  position: relative;
  box-shadow: 0 4px 16px rgba(0,0,0,.18);
  letter-spacing: -1px; overflow: hidden; cursor: pointer;
}
.avatar-circle img {
  width: 100%; height: 100%; object-fit: cover;
  position: absolute; inset: 0; border-radius: 50%;
}
.avatar-hover-overlay {
  position: absolute; inset: 0; border-radius: 50%;
  background: rgba(0,0,0,.45);
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  opacity: 0; transition: opacity .2s; pointer-events: none;
}
.avatar-circle:hover .avatar-hover-overlay { opacity: 1; }
.avatar-hover-overlay span {
  font-size: .55rem; font-weight: 700; color: #fff;
  letter-spacing: .5px; text-transform: uppercase; margin-top: 2px;
}
.avatar-hover-overlay svg { width: 18px; height: 18px; fill: #fff; }

.avatar-name  { font-size: 1rem; font-weight: 700; color: #1A2828; margin-bottom: 2px; }
.avatar-email { font-size: .72rem; color: rgba(26,40,40,.7); margin-bottom: 10px; word-break: break-all; }
.member-badge {
  display: inline-block;
  background: rgba(26,40,40,.12); color: #1A2828;
  font-size: .68rem; font-weight: 600;
  padding: 4px 12px; border-radius: 99px; margin-bottom: 18px;
}

.stats-strip {
  display: flex; justify-content: space-around;
  border-top: 1px solid rgba(26,40,40,.12);
  padding-top: 14px; gap: 4px;
}
.stat-item { text-align: center; }
.stat-val { font-size: .95rem; font-weight: 800; color: #1A2828; display: block; }
.stat-lbl {
  font-size: .58rem; font-weight: 600; color: rgba(26,40,40,.55);
  text-transform: uppercase; letter-spacing: .5px; display: block; margin-top: 2px;
}
.stat-divider { width: 1px; background: rgba(26,40,40,.12); align-self: stretch; }

.quick-card {
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 14px; padding: 16px;
}
.quick-label {
  font-size: .63rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 1px; color: rgba(255,255,255,.35); margin-bottom: 10px;
}
.quick-btn {
  display: block; width: 100%; padding: 9px 12px; border-radius: 8px;
  font-size: .82rem; font-weight: 600; text-align: left; cursor: pointer;
  border: none; background: transparent; color: rgba(255,255,255,.65);
  font-family: inherit; transition: all .18s; margin-bottom: 2px;
}
.quick-btn:hover  { background: rgba(255,255,255,.07); color: #fff; }
.quick-btn.active { background: rgba(255,255,255,.12); color: #fff; }
.quick-btn.danger { color: #f87171; }
.quick-btn.danger:hover { background: rgba(248,113,113,.1); }

/* ══ PANELS ══ */
.profile-content { display: flex; flex-direction: column; gap: 16px; }

.profile-panel {
  background: #fff;
  border-radius: 16px;
  padding: 28px 28px 24px;
  display: none;
  color: #1f2937;
}
.profile-panel.active { display: block; }

/* Force every element inside the white panel to use dark text */
.profile-panel,
.profile-panel h1,.profile-panel h2,.profile-panel h3,.profile-panel h4,
.profile-panel p,.profile-panel label,.profile-panel span,.profile-panel div,
.profile-panel li,.profile-panel a { color: #1f2937; }

/* Colour-specific overrides */
.profile-panel .btn-save               { color: #fff !important; }
.profile-panel .btn-discard            { color: #374151 !important; }
.profile-panel .btn-delete-acct        { color: #dc2626 !important; }
.profile-panel .delete-info h4         { color: #dc2626 !important; }
.profile-panel .delete-info p          { color: #6b7280 !important; }
.profile-panel .pf-section-label       { color: #9ca3af !important; }
.profile-panel .file-name-display      { color: #9ca3af !important; }
.profile-panel .avatar-upload-info p   { color: #6b7280 !important; }
.profile-panel .panel-title            { color: #111827 !important; }
.profile-panel .pf-label               { color: #374151 !important; }
.profile-panel .pf-input               { color: #111827 !important; }

.panel-title { font-size: 1.2rem; font-weight: 800; margin: 0 0 20px; font-family: inherit; }

/* ── Avatar upload row ── */
.avatar-upload-row {
  display: flex; align-items: center; gap: 16px;
  padding-bottom: 20px; border-bottom: 1px solid #f0f0f0; margin-bottom: 22px;
}
.avatar-upload-preview {
  width: 64px; height: 64px; border-radius: 50%;
  background: #e5e7eb;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.4rem; font-weight: 800; color: #0D9488;
  flex-shrink: 0; overflow: hidden; border: 2px solid #e5e7eb; position: relative;
}
.avatar-upload-preview img {
  width: 100%; height: 100%; object-fit: cover; border-radius: 50%;
  position: absolute; inset: 0;
}
.avatar-upload-info { flex: 1; min-width: 0; }
.avatar-upload-info strong { font-size: .82rem; color: #374151 !important; display: block; margin-bottom: 3px; }
.avatar-upload-info p { font-size: .74rem; color: #6b7280 !important; margin: 0 0 8px; line-height: 1.4; }

.file-btn-label {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 14px; border-radius: 7px;
  border: 1.5px solid #d1d5db; background: #f9fafb;
  color: #374151 !important; font-size: .78rem; font-weight: 600;
  cursor: pointer; font-family: inherit; transition: all .15s;
  position: relative; overflow: hidden;
}
.file-btn-label:hover { background: #e5e7eb; border-color: #9ca3af; }
.file-btn-label input[type="file"] {
  position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer;
}
.file-name-display { font-size: .72rem; color: #9ca3af !important; margin-top: 5px; }

.avatar-action-row { display: none; gap: 8px; margin-top: 8px; }

/* ── Form ── */
.pf-section-label {
  font-size: .65rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 1px; color: #9ca3af !important; margin: 18px 0 10px;
}
.pf-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 18px; margin-bottom: 14px; }
.pf-grid-1 { margin-bottom: 14px; }
@media(max-width: 500px) { .pf-grid-2 { grid-template-columns: 1fr; } }

.pf-field  { display: flex; flex-direction: column; gap: 5px; }
.pf-label  { font-size: .72rem; font-weight: 700; color: #374151 !important; letter-spacing: .3px; }
.pf-input {
  padding: 10px 13px; border-radius: 8px;
  border: 1.5px solid #d1d5db; background: #f9fafb;
  color: #111827 !important; font-size: .875rem;
  font-family: inherit; outline: none;
  transition: border-color .18s, box-shadow .18s;
  width: 100%; box-sizing: border-box;
}
.pf-input:focus {
  border-color: #00CC99;
  box-shadow: 0 0 0 3px rgba(0,204,153,.15);
  background: #fff;
}
.pf-input::placeholder { color: #9ca3af !important; }

.pf-divider { height: 1px; background: #f0f0f0; margin: 18px 0; }

.pf-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 6px; }
.btn-discard {
  padding: 9px 20px; border-radius: 8px;
  border: 1.5px solid #e5e7eb; background: #f3f4f6;
  color: #374151 !important; font-size: .82rem; font-weight: 600;
  cursor: pointer; font-family: inherit; transition: background .15s;
}
.btn-discard:hover { background: #e5e7eb; }
.btn-save {
  padding: 9px 22px; border-radius: 8px; border: none;
  background: #1A2828; color: #fff !important;
  font-size: .82rem; font-weight: 700;
  cursor: pointer; font-family: inherit; transition: background .15s, transform .12s;
}
.btn-save:hover { background: #0d1a1a; transform: translateY(-1px); }

/* ── Delete zone ── */
.delete-panel {
  background: #fff8f8; border: 1.5px solid #fecaca;
  border-radius: 12px; padding: 18px 22px;
  display: flex; align-items: center; justify-content: space-between;
  gap: 16px; flex-wrap: wrap; margin-top: 4px;
}
.btn-delete-acct {
  padding: 9px 18px; border-radius: 8px;
  border: 1.5px solid #fca5a5; background: transparent;
  color: #dc2626 !important; font-size: .8rem; font-weight: 700;
  cursor: pointer; font-family: inherit; transition: all .15s; white-space: nowrap;
}
.btn-delete-acct:hover { background: #fee2e2; }

/* ── Alerts ── */
.alert { padding: 10px 16px; border-radius: 8px; font-size: .82rem; font-weight: 600; margin-bottom: 16px; }
.alert.success { background: #d1fae5; color: #065f46 !important; border: 1px solid #6ee7b7; }
.alert.error   { background: #fee2e2; color: #991b1b !important; border: 1px solid #fca5a5; }

/* ── Delete modal ── */
.modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.6); z-index: 999;
  align-items: center; justify-content: center;
}
.modal-overlay.open { display: flex; }
.modal-box {
  background: #fff; border-radius: 14px;
  padding: 28px; width: 360px; max-width: 92vw;
}
.modal-box h4 { color: #dc2626 !important; font-size: 1rem; font-weight: 700; margin: 0 0 8px; }
.modal-box p  { color: #6b7280 !important; font-size: .82rem; margin: 0 0 16px; line-height: 1.5; }
.modal-box p strong { color: #374151 !important; }
.modal-box input {
  width: 100%; padding: 9px 12px; border-radius: 7px;
  border: 1.5px solid #e5e7eb; font-size: .875rem;
  font-family: inherit; outline: none; margin-bottom: 16px;
  color: #111827 !important; background: #f9fafb; box-sizing: border-box;
}
.modal-box input:focus { border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,.1); }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; }
.modal-cancel {
  padding: 8px 18px; border-radius: 7px;
  border: 1.5px solid #e5e7eb; background: #f3f4f6;
  color: #374151 !important; font-size: .82rem; font-weight: 600;
  cursor: pointer; font-family: inherit;
}
.modal-confirm-del {
  padding: 8px 18px; border-radius: 7px; border: none;
  background: #dc2626; color: #fff !important;
  font-size: .82rem; font-weight: 700;
  cursor: pointer; font-family: inherit; transition: background .15s;
}
.modal-confirm-del:hover { background: #b91c1c; }


/* Hide any legacy edit-btn that may come from style.css */
.avatar-edit-btn { display: none !important; }
/* Make avatar-circle a label (for file input) */
label.avatar-circle { display: flex; }

/* ── Password eye ── */
.pwd-wrap { position: relative; }
.pwd-wrap .pf-input { padding-right: 42px; }
.pwd-eye {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  cursor: pointer; font-size: .85rem; user-select: none; opacity: .45; transition: opacity .15s;
}
.pwd-eye:hover { opacity: 1; }
</style>
</head>
<body class="app-page">
<div class="container figma-container">

  <!-- ══ HEADER ══ -->
  <header class="page-header">
    <div class="brand">
      <img src="../images/smartbudget-logo.svg" alt="SmartBudget" class="logo-img" style="height:40px;width:auto;">
      <span class="brand-name">SmartBudget</span>
    </div>
    <nav>
      <a href="../dashboard/index.php" class="nav-link">Dashboard</a>
      <a href="../deals/stats.php"     class="nav-link">Stats</a>
      <a href="../deals/qr-saver.php"  class="nav-link">Saved QR Codes</a>
      <a href="profile.php"            class="nav-link active">Profile</a>
    </nav>
  </header>

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

        <div class="avatar-card">
          <form method="POST" enctype="multipart/form-data" id="sidebarAvatarForm">
            <input type="hidden" name="upload_avatar" value="1">
            <label class="avatar-circle" title="Click to change profile picture">
              <?php if ($avatar_src): ?>
                <img src="<?= $avatar_src ?>" alt="Avatar" id="sidebarAvatarImg">
              <?php else: ?>
                <span id="sidebarInitials"><?= htmlspecialchars($initials) ?></span>
                <img src="" alt="" id="sidebarAvatarImg" style="display:none;position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:50%;">
              <?php endif; ?>
              <div class="avatar-hover-overlay">
                <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75l11.06-11.06-3.75-3.75L3 17.25zm17.71-10.21a1 1 0 000-1.41l-2.34-2.34a1 1 0 00-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                <span>Change</span>
              </div>
              <input type="file" name="avatar" id="sidebarAvatarInput"
                     accept="image/jpeg,image/png,image/gif,image/webp"
                     style="position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;z-index:2;"
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

        <div class="quick-card">
          <div class="quick-label">Quick Actions</div>
          <button class="quick-btn active" onclick="showPanel('info',this)">Personal Information</button>
          <button class="quick-btn"        onclick="showPanel('password',this)">Change Password</button>
          <button class="quick-btn danger" onclick="showPanel('delete',this)">Delete Account</button>
          <div style="height:1px;background:rgba(255,255,255,.08);margin:8px 0;"></div>
          <a href="../auth/logout.php"
             style="display:block;text-decoration:none;color:rgba(255,255,255,.5);padding:9px 12px;border-radius:8px;font-size:.82rem;font-weight:600;transition:all .18s;"
             onmouseover="this.style.background='rgba(255,255,255,.07)';this.style.color='#fff'"
             onmouseout="this.style.background='transparent';this.style.color='rgba(255,255,255,.5)'">
            Log Out
          </a>
        </div>

      </div>

      <!-- ══ RIGHT CONTENT ══ -->
      <div class="profile-content">

        <!-- PANEL 1: Personal Information -->
        <div class="profile-panel active" id="panel-info">
          <div class="panel-title">Personal Information</div>

          <!-- Avatar upload -->
          <div class="avatar-upload-row">
            <div class="avatar-upload-preview" id="previewCircle">
              <?php if ($avatar_src): ?>
                <img src="<?= $avatar_src ?>" alt="Preview" id="previewImg">
              <?php else: ?>
                <span id="initialsSpan"><?= htmlspecialchars($initials) ?></span>
                <img src="" alt="" id="previewImg" style="display:none;position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:50%;">
              <?php endif; ?>
            </div>
            <div class="avatar-upload-info">
              <strong>Profile Picture</strong>
              <p>JPG, PNG, GIF or WEBP &middot; Max 2 MB</p>
              <form method="POST" enctype="multipart/form-data" id="avatarForm">
                <input type="hidden" name="upload_avatar" value="1">
                <label class="file-btn-label">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19.35 10.04A7.49 7.49 0 0012 4C9.11 4 6.6 5.64 5.35 8.04A5.994 5.994 0 000 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/>
                  </svg>
                  Upload Photo
                  <input type="file" name="avatar" id="avatarFileInput"
                         accept="image/jpeg,image/png,image/gif,image/webp"
                         onchange="previewAvatar(this)">
                </label>
                <div class="file-name-display" id="fileNameDisplay">No file chosen</div>
                <div class="avatar-action-row" id="avatarUploadBtns">
                  <button type="submit" class="btn-save" style="padding:7px 16px;font-size:.78rem;">Save Photo</button>
                  <button type="button" class="btn-discard" style="padding:7px 14px;font-size:.78rem;" onclick="cancelAvatarPreview()">Cancel</button>
                </div>
              </form>
            </div>
          </div>

          <!-- Profile info form -->
          <form method="POST">
            <input type="hidden" name="update_info" value="1">

            <div class="pf-section-label">Basic Info</div>
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

            <div class="pf-section-label">Contact</div>
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

            <div class="pf-section-label">Location</div>
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
            <div class="pf-grid-2" style="margin-top:14px;">
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
          <div class="panel-title" style="color:#dc2626 !important;">⚠ Delete Account</div>
          <p style="font-size:.85rem;color:#6b7280 !important;margin:0 0 20px;line-height:1.6;">
            This will permanently delete your account and all associated data including
            expenses, budgets, and QR codes.
            <strong style="color:#dc2626 !important;">This action cannot be undone.</strong>
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

/* Avatar live preview */
function previewAvatar(input) {
  if (!input.files || !input.files[0]) return;
  const file    = input.files[0];
  const display = document.getElementById('fileNameDisplay');
  const img     = document.getElementById('previewImg');
  const span    = document.getElementById('initialsSpan');
  const btns    = document.getElementById('avatarUploadBtns');

  display.textContent = file.name;
  const reader = new FileReader();
  reader.onload = e => {
    img.src = e.target.result;
    img.style.display = 'block';
    if (span) span.style.display = 'none';
  };
  reader.readAsDataURL(file);
  btns.style.display = 'flex';
}

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
    // Auto-submit after short delay so preview flashes before reload
    setTimeout(() => document.getElementById('sidebarAvatarForm').submit(), 300);
  };
  reader.readAsDataURL(file);
}

function cancelAvatarPreview() {
  const input   = document.getElementById('avatarFileInput');
  const display = document.getElementById('fileNameDisplay');
  const img     = document.getElementById('previewImg');
  const span    = document.getElementById('initialsSpan');
  const btns    = document.getElementById('avatarUploadBtns');

  input.value         = '';
  display.textContent = 'No file chosen';
  btns.style.display  = 'none';

  <?php if ($avatar_src): ?>
    img.src           = '<?= $avatar_src ?>';
    img.style.display = 'block';
  <?php else: ?>
    img.style.display = 'none';
    if (span) span.style.display = '';
  <?php endif; ?>
}

/* Auto-open correct panel on form error */
<?php if ($error && isset($_POST['change_password'])): ?>
  showPanel('password', document.querySelectorAll('.quick-btn')[1]);
<?php elseif ($error && isset($_POST['delete_account'])): ?>
  showPanel('delete', document.querySelectorAll('.quick-btn')[2]);
<?php endif; ?>
</script>
<?php $perf->displayStats(); ?> <!-- ADD THIS -->
</body>
</html>