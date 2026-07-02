<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: gallery_admin.php');
    exit;
}

$title = trim($_POST['title'] ?? '');
$uploadDir = __DIR__ . '/uploads/gallery';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    die('Unable to create upload directory.');
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    header('Location: gallery_admin.php?error=upload');
    exit;
}

$file = $_FILES['image'];
$mime = mime_content_type($file['tmp_name']);
if (!str_starts_with($mime, 'image/')) {
    header('Location: gallery_admin.php?error=invalid_type');
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext === '') {
    $ext = explode('/', $mime)[1] ?? 'jpg';
}

$destName = 'gallery_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . preg_replace('/[^a-z0-9]/', '', $ext);
$destPath = $uploadDir . '/' . $destName;
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    header('Location: gallery_admin.php?error=upload_move');
    exit;
}

$relativePath = 'uploads/gallery/' . $destName;
$stmt = $pdo->prepare('INSERT INTO gallery_images (title, file_path, uploaded_by) VALUES (:title, :file_path, :uploaded_by)');
$stmt->execute([
    ':title' => $title,
    ':file_path' => $relativePath,
    ':uploaded_by' => $_SESSION['admin_id'] ?? null,
]);

header('Location: gallery_admin.php?success=1');
exit;
