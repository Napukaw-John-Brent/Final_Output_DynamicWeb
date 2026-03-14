<?php
include "../config/db.php";

$check = $conn->query("SHOW COLUMNS FROM users LIKE 'security_pin'");
if ($check && $check->num_rows === 0) {
    require_once __DIR__ . '/../config/migrate_auth.php';
}

$registerError = '';
if (!empty($_POST['name']) && !empty($_POST['email']) && isset($_POST['password'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
    $mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : null;
    $dob = isset($_POST['date_of_birth']) && $_POST['date_of_birth'] !== '' ? $_POST['date_of_birth'] : null;

    if ($password !== $confirm) {
        $registerError = 'Passwords do not match.';
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, mobile, date_of_birth, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $email, $mobile, $dob, $password_hash);
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: login.php");
            exit;
        }
        $registerError = $conn->errno === 1062 ? "Email already registered." : "Registration failed.";
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Account – SmartBudget</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body class="auth-page auth-create">
  <div class="container">
    <h1 class="auth-welcome-title">Create Account</h1>
    <div class="card auth-card">
      <form method="POST" class="auth-form">
        <?php if ($registerError !== ''): ?><p class="error"><?= htmlspecialchars($registerError) ?></p><?php endif; ?>
        <label class="input-label">Full Name</label>
        <input name="name" type="text" placeholder="John Doe" value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" required>
        <label class="input-label">Email</label>
        <input name="email" type="email" placeholder="example@example.com" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
        <label class="input-label">Mobile Number</label>
        <input name="mobile" type="text" placeholder="+ 123 456 789" value="<?= isset($_POST['mobile']) ? htmlspecialchars($_POST['mobile']) : '' ?>">
        <label class="input-label">Date Of Birth</label>
        <input name="date_of_birth" type="text" placeholder="DD / MM / YYYY" value="<?= isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : '' ?>">
        <label class="input-label">Password</label>
        <div class="input-password-wrap">
          <input name="password" type="password" placeholder="........" id="reg-password" required>
          <button type="button" class="toggle-password" aria-label="Show password" data-target="reg-password">
            <img src="../images/eye-off.svg" alt="" class="icon-eye">
            <img src="../images/eye.svg" alt="" class="icon-eye-open" hidden>
          </button>
        </div>
        <label class="input-label">Confirm Password</label>
        <div class="input-password-wrap">
          <input name="password_confirm" type="password" placeholder="........" id="reg-password-confirm" required>
          <button type="button" class="toggle-password" aria-label="Show password" data-target="reg-password-confirm">
            <img src="../images/eye-off.svg" alt="" class="icon-eye">
            <img src="../images/eye.svg" alt="" class="icon-eye-open" hidden>
          </button>
        </div>
        <p class="auth-terms">By continuing, you agree to <a href="#">Terms of Use</a> and <a href="#">Privacy Policy</a>.</p>
        <button type="submit" class="btn-primary">Sign Up</button>
        <p class="auth-switch">Already have an account? <a href="login.php">Log In</a></p>
      </form>
    </div>
  </div>
  <script src="../js/password-toggle.js"></script>
</body>
</html>
