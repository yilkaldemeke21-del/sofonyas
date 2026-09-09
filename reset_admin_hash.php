<?php
require 'db.php';
if (!($pdo instanceof PDO)) {
    echo "NO_PDO\n";
    exit;
}
$password = 'admin@123';
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE username = ?');
$stmt->execute([$hash, 'sofonyas']);
echo "updated|" . $hash . PHP_EOL;
