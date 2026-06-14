<?php
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: 'sofonyas_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$pdo = null;
$lastError = null;

$hosts = array_unique(array_filter([$host, '127.0.0.1', 'localhost']));

foreach ($hosts as $candidateHost) {
    $dsn = "mysql:host=$candidateHost;port=$port;dbname=$db;charset=$charset";

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        break;
    } catch (PDOException $e) {
        $lastError = $e;
    }
}

if (!$pdo) {
    error_log('DB connection failed: ' . $lastError->getMessage());
    die('DB connection failed. Please start the MySQL service on port 3306 and confirm the database credentials. Details: ' . $lastError->getMessage());
}

if (!function_exists('ensureCourseColumns')) {
    function ensureCourseColumns(PDO $pdo): void
    {
        $existingColumns = [];
        $columnStmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses'");

        foreach ($columnStmt->fetchAll(PDO::FETCH_COLUMN) as $columnName) {
            $existingColumns[$columnName] = true;
        }

        $columnDefinitions = [
            'short_description' => 'ALTER TABLE courses ADD COLUMN short_description TEXT DEFAULT NULL',
            'category' => 'ALTER TABLE courses ADD COLUMN category VARCHAR(100) DEFAULT NULL',
            'level' => 'ALTER TABLE courses ADD COLUMN level VARCHAR(50) DEFAULT NULL',
            'thumbnail' => 'ALTER TABLE courses ADD COLUMN thumbnail VARCHAR(255) DEFAULT NULL',
            'tutorial_topic' => 'ALTER TABLE courses ADD COLUMN tutorial_topic VARCHAR(255) DEFAULT NULL',
            'tutorial_text' => 'ALTER TABLE courses ADD COLUMN tutorial_text TEXT DEFAULT NULL',
            'tutorial_image' => 'ALTER TABLE courses ADD COLUMN tutorial_image VARCHAR(255) DEFAULT NULL',
            'tutorial_audio' => 'ALTER TABLE courses ADD COLUMN tutorial_audio VARCHAR(255) DEFAULT NULL',
            'tutorial_video' => 'ALTER TABLE courses ADD COLUMN tutorial_video VARCHAR(255) DEFAULT NULL',
            'modules' => 'ALTER TABLE courses ADD COLUMN modules TEXT DEFAULT NULL',
            'quiz' => 'ALTER TABLE courses ADD COLUMN quiz TEXT DEFAULT NULL',
            'assignment' => 'ALTER TABLE courses ADD COLUMN assignment TEXT DEFAULT NULL',
            'certificate_requirements' => 'ALTER TABLE courses ADD COLUMN certificate_requirements TEXT DEFAULT NULL',
        ];

        foreach ($columnDefinitions as $columnName => $sql) {
            if (!isset($existingColumns[$columnName])) {
                try {
                    $pdo->exec($sql);
                } catch (PDOException $e) {
                    error_log('Course schema migration warning for ' . $columnName . ': ' . $e->getMessage());
                }
            }
        }
    }
}

try {
    ensureCourseColumns($pdo);
} catch (Throwable $e) {
    error_log('Course schema validation failed: ' . $e->getMessage());
}

function cleanText($value): string {
    return trim((string)($value ?? ''));
}

function sanitizeRichText($value): string {
    $html = (string)($value ?? '');
    $html = html_entity_decode($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><pre><code><a><img><table><thead><tbody><tr><th><td><span><div>'; 

    return strip_tags($html, $allowedTags);
}

function renderRichText($value): string {
    return sanitizeRichText($value);
}

function safe($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
