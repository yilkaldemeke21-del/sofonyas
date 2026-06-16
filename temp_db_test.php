<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
var_dump(getenv('DB_HOST'));
var_dump(getenv('DB_PORT'));
var_dump(getenv('DB_NAME'));
var_dump(getenv('DB_USER'));
var_dump(getenv('DB_PASS'));
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db = getenv('DB_NAME') ?: 'sofonyas_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
var_dump($host, $port, $db, $user, $pass);
$opts = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false, PDO::ATTR_TIMEOUT=>5];
if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) { $opts[PDO::MYSQL_ATTR_CONNECT_TIMEOUT]=5; }
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    var_dump($dsn);
    $pdo = new PDO($dsn, $user, $pass, $opts);
    var_dump('connected');
} catch (PDOException $e) {
    var_dump('error', $e->getMessage());
}
