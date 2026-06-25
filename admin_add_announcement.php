<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once 'db.php';
require_once 'admin_lang.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $created_at = date('Y-m-d H:i:s');

    if ($title !== '' && $content !== '') {
        try {
            $pdo->exec('CREATE TABLE IF NOT EXISTS event_announcements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_title VARCHAR(255) NOT NULL,
                event_description TEXT NOT NULL,
                event_date DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } catch (PDOException $e) {}

        $description = $content . ($link !== '' ? "\n\nሊንክ: $link" : '');
        $stmt = $pdo->prepare('INSERT INTO event_announcements (event_title, event_description, event_date) VALUES (?, ?, ?)');
        $stmt->execute([$title, $description, $created_at]);
        header('Location: admin_dashboard.php?success=1&section=announcement');
        exit;
    } else {
        $message = admin_text('required_message');
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo admin_text('add_announcement'); ?></title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;padding:0;color:#222}.
        .wrap{max-width:760px;margin:40px auto;padding:24px;background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.08)}
        h1{margin-top:0;color:#dc2626}label{display:block;margin-top:12px;font-weight:bold}input, textarea{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;margin-top:6px}textarea{min-height:140px}button{margin-top:16px;background:#dc2626;color:#fff;border:0;padding:10px 16px;border-radius:8px;cursor:pointer}a{display:inline-block;margin-top:16px;color:#dc2626;text-decoration:none}
        .msg{margin-top:12px;padding:10px 12px;border-radius:8px;background:#ecfdf3;color:#047857}
    </style>
</head>
<body>
<div class="wrap">
    <h1>📢 <?php echo admin_text('add_announcement'); ?></h1>
    <?php if ($message): ?><div class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <form method="post">
        <label><?php echo admin_text('title_label'); ?></label>
        <input type="text" name="title" required>
        <label><?php echo admin_text('content_label'); ?></label>
        <textarea name="content" required></textarea>
        <label><?php echo admin_text('link_label'); ?></label>
        <input type="text" name="link" placeholder="https://example.com">
        <button type="submit"><?php echo admin_text('save_announcement'); ?></button>
    </form>
    <a href="admin_dashboard.php">← <?php echo admin_text('back'); ?></a>
    <a href="admin_lang.php?lang=<?php echo ($_SESSION['admin_lang'] ?? 'am') === 'am' ? 'en' : 'am'; ?>&redirect=admin_add_announcement.php" style="margin-left:12px;"><?php echo admin_text('language_switch'); ?></a>
</div>
</body>
</html>
