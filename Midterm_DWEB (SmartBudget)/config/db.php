<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Connect without database first
$conn = new mysqli("localhost", "root", "");
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$conn->query("CREATE DATABASE IF NOT EXISTS budget_app");
$conn->select_db("budget_app");
$conn->set_charset("utf8mb4");

// Auto-create ALL required tables if they don't exist
$tables = [
    'users' => "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        email VARCHAR(100) UNIQUE,
        mobile VARCHAR(30) NULL,
        date_of_birth DATE NULL,
        password VARCHAR(255),
        security_pin VARCHAR(255) NULL,
        reset_token VARCHAR(64) NULL,
        reset_token_expires DATETIME NULL
    )",
    'budgets' => "CREATE TABLE IF NOT EXISTS budgets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        month VARCHAR(20),
        total_budget DECIMAL(10,2)
    )",
    'category_budgets' => "CREATE TABLE IF NOT EXISTS category_budgets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        month VARCHAR(20),
        category VARCHAR(50),
        allocated_amount DECIMAL(10,2),
        percentage DECIMAL(5,2)
    )",
    'expenses' => "CREATE TABLE IF NOT EXISTS expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        amount DECIMAL(10,2),
        category VARCHAR(50),
        description VARCHAR(255),
        date DATE
    )",
    'deals' => "CREATE TABLE IF NOT EXISTS deals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100),
        price DECIMAL(10,2)
    )",
    'qr_codes' => "CREATE TABLE IF NOT EXISTS qr_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        qr_data TEXT
    )",
];

foreach ($tables as $table => $sql) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows === 0) {
        $conn->query($sql);
    }
}

// Migrate existing tables: add any missing columns
$migrations = [
    'expenses' => [
        'description' => "ALTER TABLE expenses ADD COLUMN description VARCHAR(255) NULL AFTER category",
        'date'        => "ALTER TABLE expenses ADD COLUMN date DATE NULL AFTER description",
    ],
    'users' => [
        'mobile'               => "ALTER TABLE users ADD COLUMN mobile VARCHAR(30) NULL",
        'date_of_birth'        => "ALTER TABLE users ADD COLUMN date_of_birth DATE NULL",
        'security_pin'         => "ALTER TABLE users ADD COLUMN security_pin VARCHAR(255) NULL",
        'reset_token'          => "ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL",
        'reset_token_expires'  => "ALTER TABLE users ADD COLUMN reset_token_expires DATETIME NULL",
    ],
    'category_budgets' => [
        'percentage' => "ALTER TABLE category_budgets ADD COLUMN percentage DECIMAL(5,2) NULL",
    ],
];

foreach ($migrations as $table => $columns) {
    foreach ($columns as $column => $alterSql) {
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($check && $check->num_rows === 0) {
            $conn->query($alterSql);
        }
    }
}
?>
