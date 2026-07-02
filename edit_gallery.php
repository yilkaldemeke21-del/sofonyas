<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}
require_once __DIR__ . '/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: gallery_admin.php');
    exit;
}

$message = '';
$stmt = $pdo->prepare('SELECT * FROM gallery_images WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$image = $stmt->fetch();
if (!$image) {
    header('Location: gallery_admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $update = $pdo->prepare('UPDATE gallery_images SET title = :title, updated_at = NOW() WHERE id = :id');
    $update->execute([':title' => $title, ':id' => $id]);
    $message = 'Photo details updated successfully.';
    $image['title'] = $title;
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Gallery Photo</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; margin: 0; padding: 0; color: #0f172a; }
        .page { max-width: 760px; margin: 28px auto; padding: 0 16px; }
        .box { background: #fff; border-radius: 20px; padding: 24px; box-shadow: 0 18px 40px rgba(15,23,42,0.08); }
        h1 { margin-top: 0; font-size: 28px; }
        .message { margin-bottom: 16px; padding: 14px; border-radius: 14px; background: #ecfdf5; color: #065f46; }
        .field { margin-bottom: 18px; }
        label { display: block; font-weight: 700; margin-bottom: 8px; color: #334155; }
        input[type=text] { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 15px; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 12px 18px; border-radius: 999px; text-decoration: none; font-weight: 700; }
        .btn.primary { background: #2563eb; color: #fff; }
        .btn.secondary { background: #f8fafc; color: #0f172a; border: 1px solid #cbd5e1; }
        .preview { margin: 20px 0; }
        .preview img { width: 100%; border-radius: 18px; max-height: 420px; object-fit: cover; }
    </style>
</head>
<body>
<div class="page">
    <div class="box">
        <h1>Edit Gallery Photo</h1>
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <div class="preview">
            <img src="<?php echo htmlspecialchars($image['file_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Gallery photo">
        </div>
        <form method="post">
            <div class="field">
                <label for="title">Photo Title</label>
                <input id="title" type="text" name="title" value="<?php echo htmlspecialchars($image['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter a title for the photo">
            </div>
            <div class="actions">
                <button type="submit" class="btn primary">Save Changes</button>
                <a href="gallery_admin.php" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
