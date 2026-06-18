<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('max_execution_time', '10');

echo "start\n";
try {
    $dsn = 'mysql:host=127.0.0.1;port=3306;dbname=sofonyas_db;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 2,
    ]);
    echo "pdo created\n";
    echo $pdo->query('SELECT VERSION()')->fetchColumn() . "\n";
} catch (Throwable $e) {
    echo get_class($e) . ': ' . $e->getMessage() . "\n";
}
echo "end\n";
