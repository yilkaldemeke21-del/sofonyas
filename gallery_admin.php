<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}
require_once __DIR__ . '/db.php';

$message = '';
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
$action = $_GET['action'] ?? '';

if ($success) {
    $message = 'Photo uploaded successfully.';
}

if ($error === 'upload') {
    $message = 'Upload failed. Please try again.';
} elseif ($error === 'invalid_type') {
    $message = 'Invalid file type. Only images are allowed.';
} elseif ($error === 'upload_move') {
    $message = 'Unable to save the image file. Please check upload permissions.';
}

if ($action === 'delete' && !empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare('SELECT file_path FROM gallery_images WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $image = $stmt->fetch();
    if ($image) {
        if (!empty($image['file_path']) && file_exists(__DIR__ . '/' . $image['file_path'])) {
            @unlink(__DIR__ . '/' . $image['file_path']);
        }
        $delete = $pdo->prepare('DELETE FROM gallery_images WHERE id = :id');
        $delete->execute([':id' => $id]);
        $message = 'Photo removed successfully.';
    }
}

$stmt = $pdo->query('SELECT * FROM gallery_images ORDER BY created_at DESC');
$images = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f7fa; color: #1f2937; }
        .page { max-width: 1140px; margin: 24px auto; padding: 0 16px; }
        .header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 18px; }
        .header h1 { margin: 0; font-size: 28px; }
        .hero { background: #fff; border-radius: 20px; padding: 22px; box-shadow: 0 14px 30px rgba(15,23,42,0.08); }
        .message { margin: 16px 0; padding: 14px; border-radius: 14px; background: #ecfdf5; color: #065f46; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(240px,1fr)); gap: 18px; }
        .card { background: #ffffff; border-radius: 18px; overflow: hidden; box-shadow: 0 14px 30px rgba(15,23,42,0.06); }
        .card img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; display: block; cursor: pointer; }
        .card-body { padding: 16px; }
        .card-body h3 { margin: 0 0 10px; font-size: 18px; }
        .card-body .meta { font-size: 13px; color: #475569; margin-bottom: 12px; }
        .card-body .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn, .btn-secondary { padding: 10px 14px; border-radius: 999px; font-weight: 700; text-decoration: none; display: inline-flex; align-items:center; justify-content:center; }
        .btn { background:#2563eb; color:#fff; border:none; }
        .btn.secondary { background:#f8fafc; color:#0f172a; border:1px solid #cbd5e1; }
        .upload-panel { background:#fff; border-radius:20px; padding:22px; box-shadow:0 14px 30px rgba(15,23,42,0.08); margin-bottom:20px; }
        .field { display:flex; flex-direction:column; gap:8px; margin-bottom:14px; }
        label { font-weight:700; color:#334155; }
        input[type=file], input[type=text] { width:100%; padding:12px 14px; border-radius:12px; border:1px solid #cbd5e1; }
        input[type=submit] { cursor:pointer; }
        .lightbox { position:fixed; inset:0; display:none; justify-content:center; align-items:center; background:rgba(15,23,42,0.82); z-index:1000; padding:24px; }
        .lightbox.active { display:flex; }
        .lightbox img { max-width:100%; max-height:100%; border-radius:18px; box-shadow:0 24px 60px rgba(0,0,0,0.35); }
        .lightbox-close { position:absolute; top:18px; right:18px; color:#fff; background:rgba(15,23,42,0.6); border-radius:999px; width:40px; height:40px; display:flex; justify-content:center; align-items:center; cursor:pointer; font-size:22px; }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <div>
            <h1>Gallery Admin</h1>
            <p style="color:#475569; margin:6px 0 0;">Upload and manage gallery photos for the public gallery.</p>
        </div>
        <a class="btn" href="admin_dashboard.php">Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="message"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="upload-panel">
        <h2>Upload New Photo</h2>
        <form action="save_gallery.php" method="post" enctype="multipart/form-data">
            <div class="field">
                <label for="title">Photo Title</label>
                <input type="text" id="title" name="title" placeholder="Enter a title for the photo">
            </div>
            <div class="field">
                <label for="image">Select Photo</label>
                <input type="file" id="image" name="image" accept="image/*" required>
            </div>
            <input type="submit" class="btn" value="Upload Photo">
        </form>
    </section>

    <section>
        <h2 style="margin-bottom:16px;">Uploaded Photos</h2>
        <?php if (empty($images)): ?>
            <p style="color:#475569;">No photos uploaded yet.</p>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($images as $image): ?>
                    <div class="card">
                        <img src="<?php echo htmlspecialchars($image['file_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($image['title'] ?: 'Gallery image', ENT_QUOTES, 'UTF-8'); ?>" data-src="<?php echo htmlspecialchars($image['file_path'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="card-body">
                            <h3><?php echo htmlspecialchars($image['title'] ?: 'Untitled photo', ENT_QUOTES, 'UTF-8'); ?></h3>
                            <div class="meta">Uploaded: <?php echo htmlspecialchars($image['created_at'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="actions">
                                <a class="btn secondary" href="edit_gallery.php?id=<?php echo (int)$image['id']; ?>">Edit</a>
                                <a class="btn secondary" href="gallery_admin.php?action=delete&id=<?php echo (int)$image['id']; ?>" onclick="return confirm('Delete this photo?');">Delete</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<div class="lightbox" id="lightbox">
    <div class="lightbox-close" id="lightboxClose">×</div>
    <img id="lightboxImage" src="" alt="Full photo">
</div>
<script>
    document.querySelectorAll('.card img').forEach(function(img) {
        img.addEventListener('click', function() {
            const lightbox = document.getElementById('lightbox');
            const lightboxImage = document.getElementById('lightboxImage');
            lightboxImage.src = img.dataset.src;
            lightbox.classList.add('active');
        });
    });
    document.getElementById('lightboxClose').addEventListener('click', function() {
        document.getElementById('lightbox').classList.remove('active');
    });
    document.getElementById('lightbox').addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.remove('active');
        }
    });
</script>
</body>
</html>
