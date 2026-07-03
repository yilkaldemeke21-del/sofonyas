<?php
session_start();
require_once __DIR__ . '/db.php';

$search = trim($_GET['search'] ?? '');

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
    // ignore if table creation fails; page can still show no videos.
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
    <title>Videos</title>
    <style>
        body { margin:0; font-family: Arial, sans-serif; background: #f8fafc; color: #0f172a; }
        .page { max-width: 1180px; margin: 0 auto; padding: 24px 16px 40px; }
        .hero { display:grid; gap:16px; background: linear-gradient(135deg,#2563eb,#7c3aed); border-radius:26px; padding:32px; color:#fff; box-shadow:0 22px 60px rgba(37,99,235,0.18); margin-bottom:26px; }
        .hero h1 { margin:0; font-size:clamp(2rem,3vw,3.8rem); }
        .hero p { margin:0; color: rgba(255,255,255,0.88); line-height:1.7; max-width:760px; }
        .search-form { display:flex; gap:12px; flex-wrap: wrap; margin-top:12px; }
        .search-form input { flex:1; min-width:220px; padding:12px 14px; border-radius:999px; border:none; }
        .search-form button { padding:12px 20px; border-radius:999px; border:none; background:#ffffff; color:#2563eb; font-weight:700; cursor:pointer; }
        .grid { display:grid; gap:22px; grid-template-columns: repeat(auto-fit,minmax(280px,1fr)); }
        .video-card { background:#fff; border-radius:22px; overflow:hidden; border:1px solid #e2e8f0; box-shadow:0 18px 40px rgba(15,23,42,0.06); }
        .video-thumb { position:relative; display:block; width:100%; max-height:220px; object-fit:cover; background:#0f172a; }
        .video-info { padding:18px; display:flex; flex-direction:column; gap:10px; }
        .video-info h3 { margin:0; font-size:1.1rem; }
        .video-info p { margin:0; color:#475569; line-height:1.6; }
        .video-meta { font-size:0.9rem; color:#64748b; }
        .video-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:auto; }
        .btn { text-decoration:none; display:inline-flex; align-items:center; justify-content:center; padding:10px 16px; border-radius:999px; background:#2563eb; color:#fff; font-weight:700; border:none; cursor:pointer; }
        .btn.secondary { background:#f8fafc; color:#111827; border:1px solid #cbd5e1; }
        .modal { position:fixed; inset:0; display:none; justify-content:center; align-items:center; padding:18px; background:rgba(15,23,42,0.88); z-index:1000; }
        .modal.active { display:flex; }
        .modal-content { width:100%; max-width:880px; background:#000; border-radius:20px; overflow:hidden; position:relative; }
        .modal-content video { width:100%; height:auto; display:block; }
        .modal-close { position:absolute; top:14px; right:14px; width:44px; height:44px; border:none; border-radius:50%; background:rgba(255,255,255,0.16); color:#fff; font-size:24px; cursor:pointer; }
    </style>
</head>
<body>
<div class="page">
    <section class="hero">
        <div>
            <span style="display:inline-flex;padding:10px 16px;border-radius:999px;background:rgba(255,255,255,0.18);font-weight:700;">SMART VIDEO LIBRARY</span>
            <h1>እንኳን ደህና መጡ ወደ ቪዲዮ ገጽ</h1>
            <p>የሚገኙ ቪዲዮዎችን ይመልከቱ፣ ይፈልጉ፣ እና እየተማሩ ያለዎትን እውቀት ይጨምሩ።</p>
        </div>
        <form class="search-form" action="vedio.php" method="get">
            <input type="text" name="search" placeholder="Search videos by title or description" value="<?php echo safe($search); ?>" />
            <button type="submit">Search</button>
        </form>
    </section>

    <?php if (empty($videos)): ?>
        <p style="color:#475569; font-size:1rem;">No videos available yet. Please check back later.</p>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($videos as $video): ?>
                <article class="video-card">
                    <video class="video-thumb" muted preload="metadata" src="<?php echo safe($video['file_path']); ?>" data-src="<?php echo safe($video['file_path']); ?>"></video>
                    <div class="video-info">
                        <h3><?php echo safe($video['title'] ?: 'Untitled Video'); ?></h3>
                        <p><?php echo safe(mb_strimwidth($video['description'] ?? '', 0, 120, '...')); ?></p>
                        <div class="video-meta">Uploaded: <?php echo safe($video['created_at']); ?></div>
                        <div class="video-actions">
                            <button class="btn" type="button" data-video="<?php echo safe($video['file_path']); ?>">Play</button>
                            <a class="btn secondary" href="<?php echo safe($video['file_path']); ?>" target="_blank">Open File</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
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

    document.querySelectorAll('.video-actions button').forEach(btn => {
        btn.addEventListener('click', () => {
            const source = btn.dataset.video;
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
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });
</script>
</body>
</html>
