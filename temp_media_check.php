<?php
require 'db.php';
global $pdo;
$stmt = $pdo->query('SELECT id, course_name, pdf_file, tutorial_audio, tutorial_video FROM courses WHERE pdf_file IS NOT NULL OR tutorial_audio IS NOT NULL OR tutorial_video IS NOT NULL ORDER BY id DESC LIMIT 100');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo 'ID=' . $row['id'] . ' | NAME=' . $row['course_name'] . PHP_EOL;
    foreach (['pdf_file' => $row['pdf_file'], 'tutorial_audio' => $row['tutorial_audio'], 'tutorial_video' => $row['tutorial_video']] as $k => $v) {
        if (empty($v)) continue;
        $path = $v;
        $public = publicMediaUrl($path);
        $fs = __DIR__ . '/' . ltrim($path, '/');
        $exists = file_exists($fs) ? 'YES' : 'NO';
        if ($exists === 'NO') {
            echo 'MISSING|' . $row['id'] . '|' . $row['course_name'] . '|' . $k . '|' . $path . '|' . $public . PHP_EOL;
        }
    }
}
