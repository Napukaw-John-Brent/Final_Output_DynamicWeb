<?php
/**
 * One-time setup: creates budget_app database and tables.
 * Run this in the browser once (e.g. http://localhost/Midterm_DWEB/setup.php)
 * then use the app as normal.
 */

$conn = new mysqli("localhost", "root", "");
if ($conn->connect_error) {
    die("Cannot connect to MySQL. Check that XAMPP MySQL is running. Error: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Create database if not exists
$conn->query("CREATE DATABASE IF NOT EXISTS budget_app");
$conn->select_db("budget_app");

$errors = [];

$conn->query("CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(100) UNIQUE,
  password VARCHAR(255)
)") or $errors[] = "users: " . $conn->error;

$conn->query("CREATE TABLE IF NOT EXISTS budgets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  month VARCHAR(20),
  total_budget DECIMAL(10,2)
)") or $errors[] = "budgets: " . $conn->error;

$conn->query("CREATE TABLE IF NOT EXISTS expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  amount DECIMAL(10,2),
  category VARCHAR(50),
  date DATE
)") or $errors[] = "expenses: " . $conn->error;

$conn->query("CREATE TABLE IF NOT EXISTS deals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100),
  price DECIMAL(10,2)
)") or $errors[] = "deals: " . $conn->error;

$conn->query("CREATE TABLE IF NOT EXISTS qr_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  qr_data TEXT
)") or $errors[] = "qr_codes: " . $conn->error;

$conn->close();

header("Content-Type: text/html; charset=utf-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Setup</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 480px; margin: 2rem auto; padding: 0 1rem; }
    .ok { color: #0a0; }
    .err { color: #c00; }
    a { color: #07c; }
  </style>
</head>
<body>
  <h1>Database setup</h1>
  <?php if (empty($errors)): ?>
    <p class="ok">Database <code>budget_app</code> and all tables were created successfully.</p>
    <p><a href="auth/register.php">Go to Register</a> | <a href="auth/login.php">Login</a></p>
  <?php else: ?>
    <p class="err">Something went wrong:</p>
    <ul class="err">
      <?php foreach ($errors as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</body>
</html>
