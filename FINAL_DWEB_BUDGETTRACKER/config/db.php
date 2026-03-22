<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Defaults suit XAMPP on Windows. Docker Compose sets DB_HOST=db, DB_PASS=rootpassword, etc.
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$dbPassEnv = getenv('DB_PASS');
$pass = $dbPassEnv !== false ? $dbPassEnv : '';
$dbname = getenv('DB_NAME') ?: 'budget_app';
$dbPort = getenv('DB_PORT');
$port = ($dbPort !== false && $dbPort !== '') ? (int) $dbPort : 3306;

$conn = new mysqli($host, $user, $pass, null, $port);
if ($conn->connect_error) {
    $hint = '';
    if ($conn->connect_errno === 2002) {
        $hint = ' Start MySQL in XAMPP Control Panel (MySQL → Start) so it listens on port ' . $port . '.';
    }
    die('Database connection failed: ' . $conn->connect_error . $hint);
}

$conn->query("CREATE DATABASE IF NOT EXISTS `$dbname`");
$conn->select_db($dbname);
$conn->set_charset("utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100), email VARCHAR(100) UNIQUE, mobile VARCHAR(30) NULL, date_of_birth DATE NULL, password VARCHAR(255), security_pin VARCHAR(255) NULL, reset_token VARCHAR(64) NULL, reset_token_expires DATETIME NULL, avatar VARCHAR(255) NULL, city VARCHAR(100) NULL, country VARCHAR(100) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

$conn->query("CREATE TABLE IF NOT EXISTS budgets (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, month VARCHAR(20), total_budget DECIMAL(10,2))");

$conn->query("CREATE TABLE IF NOT EXISTS category_budgets (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, month VARCHAR(20), category VARCHAR(50), allocated_amount DECIMAL(10,2), percentage DECIMAL(5,2))");

$conn->query("CREATE TABLE IF NOT EXISTS expenses (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, amount DECIMAL(10,2), category VARCHAR(50), description VARCHAR(255), date DATE, deleted_at DATETIME NULL DEFAULT NULL)");

$conn->query("CREATE TABLE IF NOT EXISTS qr_codes (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, qr_data TEXT)");

$conn->query("CREATE TABLE IF NOT EXISTS user_settings (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT UNIQUE, currency VARCHAR(3) DEFAULT 'PHP', theme VARCHAR(20) DEFAULT 'dark', notifications_enabled BOOLEAN DEFAULT TRUE, email_notifications BOOLEAN DEFAULT TRUE, budget_alerts BOOLEAN DEFAULT TRUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)");
?>
