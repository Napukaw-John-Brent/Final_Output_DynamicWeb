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

// Auto-create tables if they don't exist
$sqlFile = __DIR__ . '/../budget_app.sql';
if (file_exists($sqlFile)) {
    // Check if users table exists
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows === 0) {
        // Import SQL file
        $sql = file_get_contents($sqlFile);
        // Remove CREATE DATABASE and USE statements to avoid errors
        $sql = preg_replace('/CREATE DATABASE.*?;/i', '', $sql);
        $sql = preg_replace('/USE \w+;/i', '', $sql);
        if ($conn->multi_query($sql)) {
            while ($conn->next_result()) {} // Clear all results
        }
    }
}
?>
