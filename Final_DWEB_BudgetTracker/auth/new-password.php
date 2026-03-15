<?php
session_start();
include "../config/db.php";

$error = "";
$success = "";

$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';

if (!$email || !$token) {
    die("Invalid reset link.");
}

$stmt = $conn->prepare("SELECT id, reset_token, reset_token_expires FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || $user['reset_token'] !== $token) {
    die("Invalid or expired reset link.");
}

if (strtotime($user['reset_token_expires']) < time()) {
    die("Reset link expired.");
}

if (!empty($_POST['password'])) {
    if (isset($_POST['confirm_password']) && $_POST['password'] !== $_POST['confirm_password']) {
        $error = "Passwords do not match.";
    } else {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password=?, reset_token=NULL, reset_token_expires=NULL WHERE id=?");
        $stmt->bind_param("si", $password, $user['id']);
        $stmt->execute();
        $stmt->close();

        $success = "Password changed successfully.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Set New Password – SmartBudget</title>
<link rel="stylesheet" href="../css/style.css">
<style>
/* Optional inline CSS for the X button */
.close-btn {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 22px;
    font-weight: bold;
    color: #0f766e;
    text-decoration: none;
    cursor: pointer;
    transition: color 0.2s ease;
}
.close-btn:hover {
    color: #0d9488;
}
.auth-card {
    position: relative; /* needed for the X button */
}
</style>
</head>

<body class="auth-page auth-reset">
  <div class="container">
    <div class="card auth-card auth-card-teal">

      <!-- X button -->
      <a href="login.php" class="close-btn">&times;</a>

      <h2 class="auth-card-heading">Set New Password</h2>
      <p class="auth-card-desc">Enter your new password to secure your account.</p>

      <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <?php if ($success): ?>
        <p class="success"><?= htmlspecialchars($success) ?></p>
        <a href="login.php" class="btn-primary">Go to Login</a>
      <?php else: ?>
        <form method="POST" class="auth-form">

          <label class="input-label" for="password">New Password</label>
          <input
            id="password"
            type="password"
            name="password"
            placeholder="Enter new password"
            required
          />

          <label class="input-label" for="confirm_password">Confirm Password</label>
          <input
            id="confirm_password"
            type="password"
            name="confirm_password"
            placeholder="Confirm new password"
            required
          />

          <button type="submit" class="btn-primary">Change Password</button>
        </form>
      <?php endif; ?>

    </div>
  </div>
</body>
</html>