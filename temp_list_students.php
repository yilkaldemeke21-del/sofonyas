<?php
require_once __DIR__ . '/db.php';
$stmt = $pdo->query('SELECT name, email, student_id FROM students');
foreach ($stmt as $row) {
    echo $row['name'] . '|' . $row['email'] . '|' . $row['student_id'] . PHP_EOL;
}
