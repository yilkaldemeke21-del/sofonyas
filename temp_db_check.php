<?php
$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=sofonyas_db;charset=utf8mb4';
$user = 'root';
$pass = '';
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    echo "CONNECTED\n";
    echo $pdo->query('SELECT 1')->fetchColumn() . "\n";
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
