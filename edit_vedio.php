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

$stmt = $pdo->prepare('SELECT * FROM uploaded_videos WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$video = $stmt->fetch();
if (!$video) {
    header('Location: vedio_admin.php?error=Video+not+found');
    exit;
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $filePath = $video['file_path'];

    if (!empty($_FILES['video_file']['name']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['video_file']['tmp_name'];
        $name = $_FILES['video_file']['name'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = mime_content_type($tmpName) ?: '';
        $allowedExt = ['mp4', 'webm', 'mov', 'avi', 'mkv', 'ogg'];

        if (!in_array($ext, $allowedExt, true) || stripos($mime, 'video/') !== 0) {
            $error = 'Invalid replacement video format.';
        } else {
            $uploadDir = __DIR__ . '/uploads/videos';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                $error = 'Unable to create video directory.';
            } else {
                $safeExt = preg_replace('/[^a-z0-9]/', '', $ext);
                $destName = 'video_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $safeExt;
                $destPath = $uploadDir . '/' . $destName;
                if (!move_uploaded_file($tmpName, $destPath)) {
                    $error = 'Unable to save replacement video.';
                } else {
                    if (!empty($video['file_path']) && file_exists(__DIR__ . '/' . $video['file_path'])) {
                        @unlink(__DIR__ . '/' . $video['file_path']);
                    }
                    $filePath = 'uploads/videos/' . $destName;
                }
            }
        }
    }

    if ($error === '') {
        $update = $pdo->prepare('UPDATE uploaded_videos SET title = :title, description = :description, file_path = :file_path, updated_at = NOW() WHERE id = :id');
        $update->execute([
            ':title' => $title,
            ':description' => $description,
            ':file_path' => $filePath,
            ':id' => $id,
        ]);
        header('Location: vedio_admin.php?success=Video+updated');
        exit;
    }
}

function safe($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Video</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f7fb; color: #1f2937; }
        .page { max-width: 860px; margin: 24px auto; padding: 0 16px 32px; }
        .card { background: #ffffff; border-radius: 24px; padding: 24px; box-shadow: 0 18px 42px rgba(15, 23, 42, 0.08); }
        .card h1 { margin-top: 0; }
        .field { display: grid; gap: 8px; margin-bottom: 16px; }
        label { font-weight: 700; color: #334155; }
        input[type=text], textarea, input[type=file] { width: 100%; padding: 12px 14px; border-radius: 14px; border: 1px solid #cbd5e1; }
        textarea { min-height: 120px; resize: vertical; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 12px 18px; border-radius: 999px; border: none; background: #2563eb; color: white; font-weight: 700; cursor: pointer; }
        .btn.secondary { background: #f8fafc; color: #111827; border: 1px solid #cbd5e1; }
        .meta { color: #475569; font-size: 0.95rem; margin-top: 6px; }
        video { width: 100%; border-radius: 18px; margin-top: 18px; background: #000; }
        .message { margin-bottom: 18px; padding: 14px 16px; border-radius: 16px; }
        .message.error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <h1>Edit Video</h1>
        <?php if ($error): ?><div class="message error"><?php echo safe($error); ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <div class="field">
                <label for="title">Video Title</label>
                <input type="text" id="title" name="title" value="<?php echo safe($video['title']); ?>" required>
            </div>
            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description"><?php echo safe($video['description']); ?></textarea>
            </div>
            <div class="field">
                <label>Current Video</label>
                <video controls>
                    <source src="<?php echo safe($video['file_path']); ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
                <div class="meta">Uploaded: <?php echo safe($video['created_at']); ?></div>
            </div>
            <div class="field">
                <label for="video_file">Replace Video File (optional)</label>
                <input type="file" id="video_file" name="video_file" accept="video/*">
            </div>
            <button class="btn" type="submit">Save Changes</button>
            <a class="btn secondary" href="vedio_admin.php">Cancel</a>
        </form>
    </div>
</div>
</body>
</html>
