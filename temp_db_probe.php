<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=sofonyas_db;charset=utf8mb4';
$user = 'root';
$pass = '';

fwrite(STDERR, "starting\n");
try {
    fwrite(STDERR, "creating PDO\n");
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    fwrite(STDERR, "PDO created\n");
    $result = $pdo->query('SELECT 1')->fetchColumn();
    fwrite(STDERR, "query result: $result\n");
    echo "OK:$result\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    echo 'FAIL:' . $e->getMessage() . "\n";
}
