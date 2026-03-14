<?php
include "../config/db.php";

$check = $conn->query("SHOW COLUMNS FROM users LIKE 'reset_token'");
if ($check && $check->num_rows === 0) {
    require_once __DIR__ . '/../config/migrate_auth.php';
}

$message = '';
$error = '';

if (!empty($_POST['email'])) {
    $email = trim($_POST['email']);
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $uid = (int) $user['id'];
        $st = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
        $st->bind_param("ssi", $token, $expires, $uid);
        $st->execute();
        $st->close();
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_token'] = $token;
        header("Location: new-password.php");
        exit;
    }
    $error = 'No account found with that email.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password – SmartBudget</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body class="auth-page auth-forgot">
  <div class="container">
    <div class="card auth-card auth-card-teal">
      <h2 class="auth-card-heading">Forgot Password</h2>
      <p class="auth-card-sub accent-text">Reset Password?</p>
      <p class="auth-card-desc">Enter your email to reset your password and regain access to your account.</p>
      <form method="POST" class="auth-form">
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <label class="input-label">Enter Email Address</label>
        <input name="email" type="email" placeholder="example@example.com" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
        <button type="submit" class="btn-primary">Next Step</button>
        <p class="auth-or">or sign up with</p>
        <a href="register.php" class="btn-secondary">Sign Up</a>
        <div class="auth-social">
          <a href="#" class="social-btn" aria-label="Facebook">f</a>
          <a href="#" class="social-btn" aria-label="Google">G</a>
        </div>
        <p class="auth-switch">Don't have an account? <a href="register.php">Sign Up</a></p>
      </form>
    </div>
  </div>
</body>
</html>
