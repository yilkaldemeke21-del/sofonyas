<?php
require_once __DIR__ . '/db.php';
$stmt = $pdo->prepare('SELECT email, password_hash FROM students WHERE email = :e');
$stmt->execute([':e' => 'yilkaldemeke21@gmail.com']);
$row = $stmt->fetch();
var_export($row);
echo PHP_EOL;
if ($row) {
    echo 'verify123=' . (password_verify('123456', $row['password_hash']) ? 'yes' : 'no') . PHP_EOL;
    echo 'verifyPass=' . (password_verify('password', $row['password_hash']) ? 'yes' : 'no') . PHP_EOL;
}
