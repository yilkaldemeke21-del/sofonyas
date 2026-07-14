<?php
require 'db.php';
$stmt = $pdo->query("SELECT id, course_name, course_code, tutorial_text, modules, quiz, assignment FROM courses ORDER BY id DESC LIMIT 3");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row["id"] . "|" . $row["course_name"] . "|" . $row["course_code"] . "|" . strlen((string)($row["tutorial_text"] ?? "")) . "|" . strlen((string)($row["modules"] ?? "")) . "|" . strlen((string)($row["quiz"] ?? "")) . "|" . strlen((string)($row["assignment"] ?? "")) . PHP_EOL;
}
