<?php
$pageTitle = "Forgot Password";
require_once __DIR__ . '/../config/performance.php'; // ADD THIS
$perf = new PerformanceMonitor(); // ADD THIS

session_start();
include "../config/db.php";
// ... rest
$check = $conn->query("SHOW COLUMNS FROM users LIKE 'reset_token'");
if ($check && $check->num_rows === 0) {
    require_once __DIR__ . '/../config/migrate_auth.php';
}

$message = '';
$error = '';

if (!empty($_POST['email'])) {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);

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

        header("Location: new-password.php?email=$email&token=$token");
        exit;

    } else {
        $error = 'No account found with that email.';
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

<body class="auth-page auth-forgot">

<div class="container">
<div class="card auth-card auth-card-teal">

<h2 class="auth-card-heading">Forgot Password</h2>
<p class="auth-card-sub accent-text">Reset Password?</p>
<p class="auth-card-desc">
Enter your email to reset your password and regain access to your account.
</p>

<form method="POST" class="auth-form">

<?php if ($error): ?>
<p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<label class="input-label">Enter Email Address</label>

<input
name="email"
type="email"
placeholder="example@example.com"
value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
required
>

<button type="submit" class="btn-primary">Next Step</button>

<p class="auth-or">or sign up with</p>

<a href="register.php" class="btn-secondary">Sign Up</a>

<div class="auth-social">
<a href="#" class="social-btn" aria-label="Facebook">f</a>
<a href="#" class="social-btn" aria-label="Google">G</a>
</div>

<p class="auth-switch">
Don't have an account?
<a href="register.php">Sign Up</a>
</p>

</form>
</div>
</div>

</body>
</html>