<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: vedio_admin.php');
    exit;
}

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$uploadDir = __DIR__ . '/uploads/videos';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    header('Location: vedio_admin.php?error=Unable+to+create+upload+folder');
    exit;
}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS uploaded_videos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        file_path VARCHAR(255) NOT NULL,
        uploaded_by VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_uploaded_videos_created_at (created_at),
        INDEX idx_uploaded_videos_title (title)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (Throwable $e) {
    header('Location: vedio_admin.php?error=Video+table+not+available');
    exit;
}

if (!isset($_FILES['video_files']) || !is_array($_FILES['video_files']['name'])) {
    header('Location: vedio_admin.php?error=No+video+files+selected');
    exit;
}

$allowedExt = ['mp4', 'webm', 'mov', 'avi', 'mkv', 'ogg'];
$uploaded = 0;
$errorMessage = '';

foreach ($_FILES['video_files']['name'] as $index => $name) {
    if ($_FILES['video_files']['error'][$index] !== UPLOAD_ERR_OK) {
        continue;
    }
    $tmpName = $_FILES['video_files']['tmp_name'][$index];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mime = mime_content_type($tmpName) ?: '';

    if (!in_array($ext, $allowedExt, true) || stripos($mime, 'video/') !== 0) {
        $errorMessage = 'Invalid video type. Allowed types: mp4, webm, mov, avi, mkv, ogg';
        continue;
    }

    $safeExt = preg_replace('/[^a-z0-9]/', '', $ext);
    $destName = 'video_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $safeExt;
    $destPath = $uploadDir . '/' . $destName;

    if (!move_uploaded_file($tmpName, $destPath)) {
        $errorMessage = 'Unable to save the video file.';
        continue;
    }

    $videoTitle = $title !== '' ? ($title . ' · ' . pathinfo($name, PATHINFO_FILENAME)) : pathinfo($name, PATHINFO_FILENAME);
    $videoPath = 'uploads/videos/' . $destName;

    $stmt = $pdo->prepare('INSERT INTO uploaded_videos (title, description, file_path, uploaded_by) VALUES (:title, :description, :file_path, :uploaded_by)');
    $stmt->execute([
        ':title' => $videoTitle,
        ':description' => $description,
        ':file_path' => $videoPath,
        ':uploaded_by' => $_SESSION['admin_id'],
    ]);
    $uploaded++;
}

if ($uploaded === 0) {
    $errorParam = $errorMessage ?: 'No valid videos were uploaded.';
    header('Location: vedio_admin.php?error=' . urlencode($errorParam));
    exit;
}

$successParam = $uploaded > 1 ? "{$uploaded}+videos+uploaded" : '1';
header('Location: vedio_admin.php?success=' . urlencode($successParam));
exit;
