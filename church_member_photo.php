<?php
session_start();
require_once __DIR__ . '/db.php';
requireRole(['admin'], $pdo);
if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit;
}

$memberId = (int)($_GET['id'] ?? 0);
if ($memberId < 1) {
    http_response_code(404);
    exit;
}

$stmt = $pdo->prepare('SELECT photo_path FROM church_members WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $memberId]);
$fileName = (string)($stmt->fetchColumn() ?: '');
if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $fileName)) {
    http_response_code(404);
    exit;
}

$filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'private_uploads' . DIRECTORY_SEPARATOR . 'church_members' . DIRECTORY_SEPARATOR . $fileName;
if (!is_file($filePath)) {
    http_response_code(404);
    exit;
}

$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($filePath);
$allowedMimeTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($allowedMimeTypes[$mimeType]) || $allowedMimeTypes[$mimeType] !== pathinfo($filePath, PATHINFO_EXTENSION)) {
    http_response_code(415);
    exit;
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($filePath);