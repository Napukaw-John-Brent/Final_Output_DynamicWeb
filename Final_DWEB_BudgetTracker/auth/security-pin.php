<?php
include "../config/db.php";

$check = $conn->query("SHOW COLUMNS FROM users LIKE 'security_pin'");
if ($check && $check->num_rows === 0) {
    require_once __DIR__ . '/../config/migrate_auth.php';
}

$verify = isset($_GET['verify']) && $_GET['verify'] === '1';
$pending_id = isset($_SESSION['pending_pin_user_id']) ? (int) $_SESSION['pending_pin_user_id'] : 0;

if ($verify && $pending_id > 0) {
    // Verify PIN after login
    $stmt = $conn->prepare("SELECT security_pin FROM users WHERE id = ?");
    $stmt->bind_param("i", $pending_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    if (!$user || empty($user['security_pin'])) {
        $_SESSION['user_id'] = $pending_id;
        unset($_SESSION['pending_pin_user_id']);
        header("Location: ../dashboard/index.php");
        exit;
    }
} else if (!$verify) {
    header("Location: login.php");
    exit;
}

$pinError = '';
if (!empty($_POST['pin'])) {
    $pin = preg_replace('/\D/', '', $_POST['pin']);
    if (strlen($pin) !== 6) {
        $pinError = 'Enter all 6 digits.';
    } else {
        $stmt = $conn->prepare("SELECT security_pin FROM users WHERE id = ?");
        $stmt->bind_param("i", $pending_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        if ($row && password_verify($pin, $row['security_pin'])) {
            $_SESSION['user_id'] = $pending_id;
            unset($_SESSION['pending_pin_user_id']);
            header("Location: ../dashboard/index.php");
            exit;
        }
        $pinError = 'Incorrect security pin.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Security Pin – SmartBudget</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body class="auth-page auth-pin">
  <div class="container">
    <div class="card auth-card auth-card-teal">
      <h2 class="auth-card-heading">Security Pin</h2>
      <p class="auth-card-sub">Enter Security Pin</p>
      <form method="POST" class="auth-form pin-form" id="pin-form">
        <?php if ($pinError): ?><p class="error"><?= htmlspecialchars($pinError) ?></p><?php endif; ?>
        <div class="pin-input-wrap">
          <input type="hidden" name="pin" id="pin-value">
          <?php for ($i = 0; $i < 6; $i++): ?>
            <input type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" class="pin-dot" data-index="<?= $i ?>" autocomplete="off" aria-label="Digit <?= $i + 1 ?>">
          <?php endfor; ?>
        </div>
        <button type="submit" class="btn-primary">Accept</button>
        <button type="button" class="btn-secondary" id="send-again">Send Again</button>
        <p class="auth-or">or sign up with</p>
        <div class="auth-social">
          <a href="#" class="social-btn" aria-label="Facebook">f</a>
          <a href="#" class="social-btn" aria-label="Google">G</a>
        </div>
        <p class="auth-switch">Don't have an account? <a href="register.php">Sign Up</a></p>
      </form>
    </div>
  </div>
  <script src="../js/pin-input.js"></script>
</body>
</html>
