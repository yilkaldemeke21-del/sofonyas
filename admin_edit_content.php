<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once 'db.php';
require_once 'admin_lang.php';

$id = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'announcement';
$message = '';

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS event_announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_title VARCHAR(255) NOT NULL,
        event_description TEXT NOT NULL,
        event_date DATETIME NOT NULL,
        content_type VARCHAR(30) NOT NULL DEFAULT "announcement",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {}

$item = null;
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM event_announcements WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $date = trim($_POST['event_date'] ?? date('Y-m-d H:i:s'));

    if ($title !== '' && $content !== '') {
        $contentType = ($type === 'blog') ? 'blog' : (($type === 'news') ? 'news' : 'announcement');
        $description = $content . ($link !== '' ? "\n\nሊንክ: $link" : '');
        $stmt = $pdo->prepare('UPDATE event_announcements SET event_title = ?, event_description = ?, event_date = ?, content_type = ? WHERE id = ?');
        $stmt->execute([$title, $description, $date, $contentType, $id]);
        header('Location: admin_dashboard.php?success=1&section=' . urlencode($type));
        exit;
    } else {
        $message = admin_text('required_message');
    }
}

if ($item) {
    $parts = explode("\n\nሊንክ: ", $item['event_description']);
    $body = $parts[0] ?? $item['event_description'];
    $linkValue = isset($parts[1]) ? $parts[1] : '';
} else {
    $body = '';
    $linkValue = '';
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo admin_text('edit_content'); ?></title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;padding:0;color:#222}.
        .wrap{max-width:760px;margin:40px auto;padding:24px;background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.08)}
        h1{margin-top:0;color:#2563eb}label{display:block;margin-top:12px;font-weight:bold}input, textarea{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;margin-top:6px}textarea{min-height:140px}button{margin-top:16px;background:#2563eb;color:#fff;border:0;padding:10px 16px;border-radius:8px;cursor:pointer}a{display:inline-block;margin-top:16px;color:#2563eb;text-decoration:none}
        .msg{margin-top:12px;padding:10px 12px;border-radius:8px;background:#ecfdf3;color:#047857}
    </style>
</head>
<body>
<div class="wrap">
    <h1>✏️ <?php echo admin_text('edit_content'); ?></h1>
    <?php if ($message): ?><div class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($item): ?>
    <form method="post">
        <label><?php echo admin_text('title_label'); ?></label>
        <input type="text" name="title" value="<?php echo htmlspecialchars($item['event_title'], ENT_QUOTES, 'UTF-8'); ?>" required>
        <label><?php echo admin_text('content_label'); ?></label>
        <textarea name="content" required><?php echo htmlspecialchars($body, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <label><?php echo admin_text('link_label'); ?></label>
        <input type="text" name="link" value="<?php echo htmlspecialchars($linkValue, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://example.com">
        <label><?php echo admin_text('date_label'); ?></label>
        <input type="datetime-local" name="event_date" value="<?php echo date('Y-m-d\TH:i', strtotime($item['event_date'])); ?>">
        <button type="submit"><?php echo admin_text('update'); ?></button>
    </form>
    <?php else: ?>
    <p>ይህ ይዘት አልተገኘም።</p>
    <?php endif; ?>
    <a href="admin_dashboard.php">← <?php echo admin_text('back'); ?></a>
    <a href="admin_lang.php?lang=<?php echo ($_SESSION['admin_lang'] ?? 'am') === 'am' ? 'en' : 'am'; ?>&redirect=admin_edit_content.php?id=<?php echo $id; ?>&type=<?php echo urlencode($type); ?>" style="margin-left:12px;"><?php echo admin_text('language_switch'); ?></a>
</div>
</body>
</html>
