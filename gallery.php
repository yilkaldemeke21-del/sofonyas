<?php
require_once __DIR__ . '/db.php';

$stmt = $pdo->query('SELECT * FROM gallery_images ORDER BY created_at DESC');
$images = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f3f4f6; color: #111827; }
        .container { max-width: 1140px; margin: 26px auto; padding: 0 16px; }
        .hero { padding: 28px; background: #ffffff; border-radius: 24px; box-shadow: 0 16px 40px rgba(15,23,42,0.08); margin-bottom: 18px; }
        .hero h1 { margin: 0 0 10px; font-size: 32px; }
        .hero p { margin: 0; color: #475569; line-height: 1.75; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; }
        .card { background: #ffffff; border-radius: 22px; overflow: hidden; box-shadow: 0 14px 30px rgba(15,23,42,0.08); }
        .card img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; display: block; }
        .card-body { padding: 16px; }
        .card-body h3 { margin: 0 0 10px; font-size: 18px; color: #1f2937; }
        .card-body p { margin: 0; color: #475569; font-size: 14px; }
        .empty { padding: 32px; text-align: center; color: #64748b; background: #fff; border-radius: 20px; box-shadow: 0 14px 30px rgba(15,23,42,0.05); }
        .back-link { display: inline-flex; margin-top: 12px; padding: 10px 16px; border-radius: 999px; background: #e2e8f0; color: #1f2937; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
<div class="container">
    <section class="hero">
        <h1>Gallery</h1>
        <p>Explore photos from our programs, events, and community activities. Admins can manage gallery images from the dashboard.</p>
    </section>

    <?php if (empty($images)): ?>
        <div class="empty">
            <p>No gallery images are available yet.</p>
            <a class="back-link" href="index.php">Back to Home</a>
        </div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($images as $image): ?>
                <article class="card">
                    <img src="<?php echo htmlspecialchars($image['file_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($image['title'] ?: 'Gallery image', ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="card-body">
                        <h3><?php echo htmlspecialchars($image['title'] ?: 'Untitled photo', ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p>Uploaded: <?php echo htmlspecialchars($image['created_at'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
