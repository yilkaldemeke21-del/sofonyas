<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}
require_once __DIR__ . '/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: vedio_admin.php?error=Invalid+video+id');
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
    header('Location: vedio_admin.php?error=Unable+to+prepare+video+table');
    exit;
}

$stmt = $pdo->prepare('SELECT file_path FROM uploaded_videos WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$video = $stmt->fetch();

if (!$video) {
    header('Location: vedio_admin.php?error=Video+not+found');
    exit;
}

if (!empty($video['file_path']) && file_exists(__DIR__ . '/' . $video['file_path'])) {
    @unlink(__DIR__ . '/' . $video['file_path']);
}

$delete = $pdo->prepare('DELETE FROM uploaded_videos WHERE id = :id');
$delete->execute([':id' => $id]);

header('Location: vedio_admin.php?success=Video+deleted');
exit;
