<?php
$pageTitle = "Login";
require_once __DIR__ . '/../config/performance.php'; // ADD THIS
$perf = new PerformanceMonitor(); // ADD THIS

include "../config/db.php";
// ... rest of your code

// Run migration if new columns missing
$check = $conn->query("SHOW COLUMNS FROM users LIKE 'security_pin'");
if ($check && $check->num_rows === 0) {
    require_once __DIR__ . '/../config/migrate_auth.php';
}

if (!empty($_POST['email']) && isset($_POST['password'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password, security_pin FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {
        if (!empty($user['security_pin'])) {
            $_SESSION['pending_pin_user_id'] = (int) $user['id'];
            header("Location: security-pin.php?verify=1");
            exit;
        }
        $_SESSION['user_id'] = (int) $user['id'];
        header("Location: ../dashboard/index.php");
        exit;
    }
    $loginError = "Invalid email or password.";
}

$resetSuccess = isset($_GET['reset']) && $_GET['reset'] === '1';
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
<body class="auth-page auth-welcome">
  <div class="container">
    <h1 class="auth-welcome-title">Welcome</h1>
    <div class="card auth-card">
      <form method="POST" class="auth-form">
        <?php if ($resetSuccess): ?><p class="success-msg">Password changed. You can log in now.</p><?php endif; ?>
        <?php if (!empty($loginError)): ?><p class="error"><?= htmlspecialchars($loginError) ?></p><?php endif; ?>
        <label class="input-label">Email:</label>
        <input name="email" type="email" placeholder="Username" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
        <label class="input-label">Password:</label>
        <div class="input-password-wrap">
          <input name="password" type="password" placeholder="Password" id="login-password" required>
          <button type="button" class="toggle-password" aria-label="Show password" data-target="login-password">
            <img src="../images/eye.svg" alt="" class="icon-password-toggle">
          </button>
        </div>
        <button type="submit" class="btn-primary">Log In</button>
        <a href="forgot-password.php" class="auth-forgot-link">Forgot Password?</a>
        <a href="register.php" class="btn-secondary">Sign Up</a>
        <p class="auth-or">or sign up with</p>
        <div class="auth-social">
          <a href="facebook-login.php" class="social-btn" aria-label="Facebook">f</a>
          <a href="google-login.php" class="social-btn" aria-label="Google">G</a>
        </div>
        <p class="auth-switch">Don't have an account? <a href="register.php">Sign Up</a></p>
      </form>
    </div>
  </div>
  <script src="../js/password-toggle.js"></script>
</body>
</html>

<?php $perf->displayStats(); ?> <!-- ADD THIS -->
</body>
</html>