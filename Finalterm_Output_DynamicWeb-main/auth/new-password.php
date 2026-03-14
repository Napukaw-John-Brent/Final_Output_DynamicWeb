<?php
include "../config/db.php";

$check = $conn->query("SHOW COLUMNS FROM users LIKE 'reset_token'");
if ($check && $check->num_rows === 0) {
    require_once __DIR__ . '/../config/migrate_auth.php';
}

$error = '';
$valid = false;
$email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : '';
$token = isset($_SESSION['reset_token']) ? $_SESSION['reset_token'] : '';

if ($email && $token) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_token_expires > NOW()");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $valid = (bool) $user;
}

if (!$valid) {
    $_SESSION['reset_email'] = null;
    $_SESSION['reset_token'] = null;
    header("Location: forgot-password.php");
    exit;
}

if (!empty($_POST['password']) && !empty($_POST['password_confirm'])) {
    $password = $_POST['password'];
    $confirm = $_POST['password_confirm'];
    if ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $uid = (int) $user['id'];
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
        $stmt->bind_param("si", $hash, $uid);
        $stmt->execute();
        $stmt->close();
        $_SESSION['reset_email'] = null;
        $_SESSION['reset_token'] = null;
        header("Location: login.php?reset=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>New Password – SmartBudget</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body class="auth-page auth-newpassword">
  <div class="container">
    <div class="card auth-card auth-card-teal">
      <h2 class="auth-card-heading">New Password</h2>
      <form method="POST" class="auth-form">
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <label class="input-label">New Password</label>
        <div class="input-password-wrap">
          <input name="password" type="password" placeholder="........" id="new-password" required>
          <button type="button" class="toggle-password" aria-label="Show password" data-target="new-password">
            <img src="../images/eye-off.svg" alt="" class="icon-eye">
            <img src="../images/eye.svg" alt="" class="icon-eye-open" hidden>
          </button>
        </div>
        <label class="input-label">Confirm New Password</label>
        <div class="input-password-wrap">
          <input name="password_confirm" type="password" placeholder="........" id="new-password-confirm" required>
          <button type="button" class="toggle-password" aria-label="Show password" data-target="new-password-confirm">
            <img src="../images/eye-off.svg" alt="" class="icon-eye">
            <img src="../images/eye.svg" alt="" class="icon-eye-open" hidden>
          </button>
        </div>
        <button type="submit" class="btn-primary">Change Password</button>
      </form>
    </div>
  </div>
  <script src="../js/password-toggle.js"></script>
</body>
</html>
