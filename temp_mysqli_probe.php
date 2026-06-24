<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$mysqli = @new mysqli('127.0.0.1', 'root', '', 'sofonyas_db', 3306);
if ($mysqli->connect_errno) {
    echo 'MYSQLI_FAIL:' . $mysqli->connect_error . PHP_EOL;
} else {
    echo 'MYSQLI_OK' . PHP_EOL;
    echo $mysqli->server_info . PHP_EOL;
    $mysqli->close();
}
