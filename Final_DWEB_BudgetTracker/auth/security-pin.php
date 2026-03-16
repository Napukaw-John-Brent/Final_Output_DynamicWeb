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
