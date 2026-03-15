<?php
/**
 * One-time migration: add columns for forgot password, security PIN, and Create Account fields.
 * Run once via browser or CLI: php config/migrate_auth.php
 */
require_once __DIR__ . '/db.php';

$additions = [
    'mobile' => "ALTER TABLE users ADD COLUMN mobile VARCHAR(30) NULL AFTER email",
    'date_of_birth' => "ALTER TABLE users ADD COLUMN date_of_birth DATE NULL AFTER mobile",
    'security_pin' => "ALTER TABLE users ADD COLUMN security_pin VARCHAR(255) NULL AFTER password",
    'reset_token' => "ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL AFTER security_pin",
    'reset_token_expires' => "ALTER TABLE users ADD COLUMN reset_token_expires DATETIME NULL AFTER reset_token",
];

foreach ($additions as $col => $sql) {
    $check = $conn->query("SHOW COLUMNS FROM users LIKE '$col'");
    if ($check && $check->num_rows === 0) {
        $conn->query($sql);
    }
}
if (php_sapi_name() === 'cli') {
    echo "Migration done.\n";
}
