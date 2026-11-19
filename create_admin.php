<?php
require 'config.php';

if (PHP_SAPI !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit;
}

$pdo = getDB();
$pass = password_hash('admin123', PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'is_verified'");
    $stmt->execute();
    if (!$stmt->fetchColumn()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 0");
    }

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, is_verified) VALUES (?, ?, ?, 'admin', 1) ON DUPLICATE KEY UPDATE name = VALUES(name), password = VALUES(password), role = VALUES(role), is_verified = 1");
    $stmt->execute(['Admin', 'admin@happiva.com', $pass]);

    $stmt = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE email = ?");
    $stmt->execute(['admin@happiva.com']);

    echo "Admin account ready. Email: admin@happiva.com Password: admin123\n";
} catch (PDOException $e) {
    echo "Failed to create admin account: " . $e->getMessage() . "\n";
}
