<?php
/**
 * SmartBudget – landing page (Figma): Log In / Sign Up / Forgot Password
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="SmartBudget - Track your expenses intelligently. Start managing your money better today with our easy-to-use budget tracker.">
  <meta name="keywords" content="budget app, expense tracker, personal finance, money management, QR scanner, budget planner">
  <meta name="author" content="SmartBudget Team">
  
  <!-- Open Graph / Social Media -->
  <meta property="og:title" content="SmartBudget - Intelligent Expense Tracking">
  <meta property="og:description" content="Track your expenses intelligently and achieve your financial goals with SmartBudget.">
  <meta property="og:image" content="images/smartbudget-og.jpg">
  <meta property="og:url" content="<?= 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>">
  <meta property="og:type" content="website">
  
  <!-- Favicon -->
  <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
  <link rel="apple-touch-icon" href="images/apple-touch-icon.png">
  
  <!-- Preconnect for fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  
  <!-- CSS with cache busting -->
  <link rel="stylesheet" href="css/style.css?v=<?= filemtime('css/style.css') ?>">
  
  <title>SmartBudget – Intelligent Expense Tracking</title>
</head>
<body class="landing-page">
  <div class="landing-wrap">
    <div class="landing-brand">
      <div class="landing-logo">
        <img src="images/smartbudget-logo.svg" alt="" width="48" height="40">
      </div>
      <h1>SmartBudget</h1>
      <p class="landing-tagline">Track It Fast, Think Vast.</p>
    </div>
    <div class="landing-actions">
      <a href="auth/login.php" class="landing-btn">Log In</a>
      <a href="auth/register.php" class="landing-btn">Sign Up</a>
    </div>
    <a href="auth/forgot-password.php" class="landing-forgot">Forgot Password?</a>
  </div>
</body>
</html>
