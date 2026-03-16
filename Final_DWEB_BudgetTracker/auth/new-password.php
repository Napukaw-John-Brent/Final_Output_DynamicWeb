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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SmartBudget - Track your expenses intelligently. Manage your budget, scan QR codes, and get spending insights.">
    <meta name="keywords" content="budget, expense tracker, personal finance, money management, QR scanner">
    <meta name="author" content="SmartBudget Team">
    
    <!-- Open Graph / Social Media -->
    <meta property="og:title" content="SmartBudget - Intelligent Expense Tracking">
    <meta property="og:description" content="Track your expenses intelligently and achieve your financial goals.">
    <meta property="og:image" content="../images/smartbudget-og.jpg">
    <meta property="og:url" content="<?= 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon-16x16.png">
    <link rel="apple-touch-icon" href="../images/apple-touch-icon.png">
    
    <!-- Preconnect for fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- CSS with cache busting -->
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime('../css/style.css') ?>">
    
    <title><?= $pageTitle ?? 'SmartBudget' ?> – SmartBudget</title>
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