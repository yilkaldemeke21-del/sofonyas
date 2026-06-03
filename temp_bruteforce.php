<?php
require_once __DIR__ . '/db.php';
$stmt = $pdo->prepare('SELECT email, password_hash FROM students WHERE email = :e');
$stmt->execute([':e' => 'yilkaldemeke21@gmail.com']);
$row = $stmt->fetch();
if (!$row) {
    echo "NO_STUDENT\n";
    exit;
}
$candidates = ['123456', 'password', 'admin', 'student', '12345', 'Yilkal123', 'yilkal123', 'sofonyas', 'welcome', 'qwerty', '1234', '000000', 'secret', 'demo', 'test123'];
foreach ($candidates as $p) {
    echo $p . ':' . (password_verify($p, $row['password_hash']) ? 'yes' : 'no') . PHP_EOL;
}
