<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(20);

$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=sofonyas_db;charset=utf8mb4';
$user = 'root';
$pass = '';

$start = microtime(true);
file_put_contents('php://stdout', "before_new_pdo\n");
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 3,
    ]);
    file_put_contents('php://stdout', "after_new_pdo\n");
    file_put_contents('php://stdout', 'elapsed=' . round(microtime(true) - $start, 3) . "\n");
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    file_put_contents('php://stdout', 'version=' . $version . "\n");
} catch (Throwable $e) {
    file_put_contents('php://stdout', 'error=' . get_class($e) . ': ' . $e->getMessage() . "\n");
    file_put_contents('php://stdout', 'elapsed=' . round(microtime(true) - $start, 3) . "\n");
}
