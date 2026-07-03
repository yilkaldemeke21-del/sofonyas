<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}
require_once __DIR__ . '/db.php';

$search = trim($_GET['search'] ?? '');
$error = trim($_GET['error'] ?? '');
$success = trim($_GET['success'] ?? '');

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
    $error = 'Unable to prepare the video table.';
}

$query = 'SELECT * FROM uploaded_videos';
$params = [];
if ($search !== '') {
    $query .= ' WHERE title LIKE :search OR description LIKE :search';
    $params[':search'] = '%' . $search . '%';
}
$query .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$videos = $stmt->fetchAll();

function safe($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f4f7fb; color: #1f2937; }
        .page { max-width: 1160px; margin: 24px auto; padding: 0 16px 32px; }
        .header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px; margin-bottom: 22px; }
        .header h1 { margin: 0; font-size: 32px; }
        .hero { background: #ffffff; border-radius: 24px; padding: 26px; box-shadow: 0 18px 42px rgba(15, 23, 42, 0.08); }
        .hero p { color: #475569; line-height: 1.7; margin: 10px 0 0; }
        .message { margin: 18px 0; padding: 14px 16px; border-radius: 16px; }
        .message.success { background: #ecfdf5; color: #065f46; }
        .message.error { background: #fee2e2; color: #991b1b; }
        .panel { background: #ffffff; border-radius: 24px; padding: 24px; box-shadow: 0 18px 42px rgba(15, 23, 42, 0.08); margin-bottom: 24px; }
        .panel h2 { margin-top: 0; color: #0f172a; }
        .field { display: grid; gap: 8px; margin-bottom: 16px; }
        label { font-weight: 700; color: #334155; }
        input[type=text], textarea, input[type=file] { width: 100%; padding: 12px 14px; border-radius: 14px; border: 1px solid #cbd5e1; font-size: 0.98rem; }
        textarea { min-height: 110px; resize: vertical; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: #2563eb; color: white; border: none; border-radius: 999px; padding: 12px 20px; font-weight: 700; cursor: pointer; text-decoration: none; }
        .btn.secondary { background: #f8fafc; color: #111827; border: 1px solid #cbd5e1; }
        .search-bar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .search-bar input { flex: 1; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(270px,1fr)); gap: 22px; }
        .card { background: #ffffff; border-radius: 22px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 14px 28px rgba(15, 23, 42, 0.05); display: flex; flex-direction: column; }
        .card video { width: 100%; height: 190px; object-fit: cover; background: #0f172a; }
        .card-body { padding: 18px; display: flex; flex-direction: column; gap: 10px; }
        .card-body h3 { margin: 0; font-size: 1.05rem; }
        .card-body p { margin: 0; color: #475569; font-size: 0.95rem; line-height: 1.6; }
        .meta { font-size: 0.9rem; color: #64748b; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: auto; }
        .play-overlay { position: relative; cursor: pointer; }
        .play-overlay:before { content: '▶'; position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 64px; height: 64px; border-radius: 50%; background: rgba(37, 99, 235, 0.8); color: #fff; display: grid; place-items: center; font-size: 24px; }
        .modal { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.88); display: none; justify-content: center; align-items: center; padding: 18px; z-index: 999; }
        .modal.active { display: flex; }
        .modal-content { max-width: 920px; width: 100%; background: #0f172a; border-radius: 24px; overflow: hidden; position: relative; }
        .modal-content video { width: 100%; height: auto; display: block; }
        .modal-close { position: absolute; top: 16px; right: 16px; width: 44px; height: 44px; border-radius: 50%; border: none; background: rgba(255,255,255,0.12); color: white; font-size: 28px; cursor: pointer; }
        @media (max-width: 620px) { .search-bar { flex-direction: column; } }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <div>
            <h1>Video Admin</h1>
            <p>Upload, manage, search, and edit videos for your website. Multiple video files can be uploaded at once.</p>
        </div>
        <a class="btn" href="admin_dashboard.php">Back to Dashboard</a>
    </div>

    <?php if ($success): ?>
        <div class="message success"><?php echo safe($success === '1' ? 'Video upload completed successfully.' : $success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message error"><?php echo safe($error); ?></div>
    <?php endif; ?>

    <section class="panel">
        <h2>Upload Video</h2>
        <form action="save_vedio.php" method="post" enctype="multipart/form-data">
            <div class="field">
                <label for="title">Video Title</label>
                <input type="text" id="title" name="title" placeholder="Enter a title for these videos">
            </div>
            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description" placeholder="Optional video description"></textarea>
            </div>
            <div class="field">
                <label for="video_files">Select Video Files</label>
                <input type="file" id="video_files" name="video_files[]" accept="video/*" multiple required>
            </div>
            <button class="btn" type="submit">Upload Videos</button>
        </form>
    </section>

    <section class="panel">
        <div class="search-bar">
            <form method="get" action="vedio_admin.php" style="display:flex; width:100%; gap:12px; flex:1;">
                <input type="text" name="search" placeholder="Search videos by title or description" value="<?php echo safe($search); ?>">
                <button class="btn secondary" type="submit">Search</button>
            </form>
        </div>
        <?php if (empty($videos)): ?>
            <p style="color:#475569;">No videos found yet.</p>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($videos as $video): ?>
                    <div class="card">
                        <div class="play-overlay" data-video="<?php echo safe($video['file_path']); ?>" data-title="<?php echo safe($video['title']); ?>">
                            <video muted preload="metadata" src="<?php echo safe($video['file_path']); ?>"></video>
                        </div>
                        <div class="card-body">
                            <h3><?php echo safe($video['title'] ?: 'Untitled Video'); ?></h3>
                            <p><?php echo safe(mb_strimwidth($video['description'] ?? '', 0, 110, '...')); ?></p>
                            <div class="meta">Uploaded: <?php echo safe($video['created_at']); ?></div>
                            <div class="actions">
                                <a class="btn secondary" href="edit_vedio.php?id=<?php echo (int)$video['id']; ?>">Edit</a>
                                <a class="btn secondary" href="delete_vedio.php?id=<?php echo (int)$video['id']; ?>" onclick="return confirm('Delete this video?');">Delete</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<div class="modal" id="videoModal">
    <div class="modal-content">
        <button class="modal-close" id="modalClose">×</button>
        <video id="modalVideo" controls playsinline></video>
    </div>
</div>

<script>
    const modal = document.getElementById('videoModal');
    const modalVideo = document.getElementById('modalVideo');
    const modalClose = document.getElementById('modalClose');

    document.querySelectorAll('.play-overlay').forEach(function(card) {
        card.addEventListener('click', function() {
            const source = card.dataset.video;
            modalVideo.src = source;
            modal.classList.add('active');
            modalVideo.play().catch(() => {});
        });
    });

    const closeModal = () => {
        modal.classList.remove('active');
        modalVideo.pause();
        modalVideo.src = '';
    };

    modalClose.addEventListener('click', closeModal);
    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });
</script>
</body>
</html>
