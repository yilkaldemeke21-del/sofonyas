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
        body{font-family:Arial,sans-serif;background:linear-gradient(135deg,#fef2f2,#fff7ed);margin:0;padding:0;color:#0f172a}.
        .wrap{max-width:780px;margin:40px auto;padding:28px;background:#fff;border-radius:18px;box-shadow:0 14px 36px rgba(15,23,42,.12)}
        h1{margin-top:0;color:#dc2626;font-size:28px}label{display:block;margin-top:16px;font-weight:700;color:#334155}input, textarea{width:100%;padding:12px 14px;border:1px solid #cbd5e1;border-radius:10px;margin-top:8px;font-size:15px;box-sizing:border-box}input:focus, textarea:focus{outline:none;border-color:#dc2626;box-shadow:0 0 0 3px rgba(220,38,38,.15)}textarea{min-height:160px;resize:vertical}.hint{font-size:13px;color:#64748b;margin-top:6px}.actions{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-top:16px}button{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;border:0;padding:12px 18px;border-radius:999px;cursor:pointer;font-weight:700;box-shadow:0 8px 18px rgba(220,38,38,.22)}button:hover{transform:translateY(-1px)}a{display:inline-block;margin-top:16px;color:#dc2626;text-decoration:none;font-weight:600}.msg{margin-top:12px;padding:12px 14px;border-radius:10px;background:#ecfdf3;color:#047857;border:1px solid #a7f3d0}
    </style>
</head>
<body>
<div class="wrap">
    <h1>📢 <?php echo admin_text('add_announcement'); ?></h1>
    <?php if ($message): ?><div class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <form method="post">
        <label><?php echo admin_text('title_label'); ?></label>
        <input type="text" name="title" placeholder="ለማስታወቂያ ርዕስ ያስገቡ" required>
        <div class="hint">በግልጽ እና የሚያስፈልግ የማስታወቂያ ርዕስ ይምረጡ።</div>
        <label><?php echo admin_text('content_label'); ?></label>
        <textarea name="content" placeholder="የማስታወቂያ ይዘት እዚህ ይጻፉ..." required></textarea>
        <label><?php echo admin_text('link_label'); ?></label>
        <input type="text" name="link" placeholder="https://example.com ወይም ተጨማሪ ሊንክ">
        <div class="hint">ሊንክ ካለ የሚመለከትበት ቦታ ነው።</div>
        <div class="actions">
            <button type="submit"><?php echo admin_text('save_announcement'); ?></button>
            <a href="admin_dashboard.php">← <?php echo admin_text('back'); ?></a>
        </div>
    </form>
    <a href="admin_lang.php?lang=<?php echo ($_SESSION['admin_lang'] ?? 'am') === 'am' ? 'en' : 'am'; ?>&redirect=admin_add_announcement.php" style="margin-left:0;">🌐 <?php echo admin_text('language_switch'); ?></a>
</div>
</body>
</html>
