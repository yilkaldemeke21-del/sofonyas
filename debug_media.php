<?php
require_once __DIR__ . '/db.php';
$rows = $pdo->query('SELECT id, course_name, pdf_file, tutorial_audio, tutorial_video FROM courses WHERE pdf_file IS NOT NULL OR tutorial_audio IS NOT NULL OR tutorial_video IS NOT NULL ORDER BY id DESC LIMIT 30')->fetchAll();
foreach ($rows as $row) {
    echo json_encode($row, JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
