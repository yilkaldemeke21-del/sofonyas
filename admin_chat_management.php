<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'], $_POST['chat_id'])) {
    $chatId = (int)$_POST['chat_id'];
    $reply = trim((string)$_POST['reply_message']);
    $status = isset($_POST['status']) ? trim((string)$_POST['status']) : 'replied';

    if ($chatId > 0 && $reply !== '') {
        $stmt = $pdo->prepare('UPDATE site_chat_messages SET reply_message = :reply_message, status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            ':reply_message' => $reply,
            ':status' => $status,
            ':id' => $chatId,
        ]);
        $message = 'መልስ ተላክቷል።';
    } else {
        $error = 'እባክዎ መልስ ያስገቡ።';
    }
}

if (isset($_GET['delete'])) {
    $chatId = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM site_chat_messages WHERE id = :id');
    $stmt->execute([':id' => $chatId]);
    $message = 'የቻት መልእክት ተሰርዟል።';
}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS site_chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_type VARCHAR(30) NOT NULL DEFAULT "guest",
        sender_name VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        reply_message TEXT DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT "new",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_site_chat_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {
    $error = 'ቻት ሰንጠረዥ ማዘጋጀት አልተቻለም: ' . $e->getMessage();
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$totalMessages = 0;
if ($error === '') {
    $totalMessages = (int)$pdo->query('SELECT COUNT(*) FROM site_chat_messages')->fetchColumn();
}
$totalPages = max(1, (int)ceil($totalMessages / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

if ($error === '') {
    $stmt = $pdo->prepare('SELECT * FROM site_chat_messages ORDER BY id DESC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $messages = $stmt->fetchAll();
} else {
    $messages = [];
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የቻት አስተዳደር</title>
    <style>
        * { box-sizing: border-box; }
        body { margin:0; font-family:Arial,sans-serif; background:#f5f7fb; color:#0f172a; }
        .page { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .topbar { background: linear-gradient(135deg,#2563eb,#7c3aed); color:#fff; padding:18px 20px; border-radius:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
        .card { background:#fff; border-radius:16px; padding:18px; box-shadow:0 10px 24px rgba(15,23,42,0.08); margin-top:16px; }
        .message { padding:12px 14px; border-radius:10px; margin-bottom:12px; font-weight:700; }
        .message.success { background:#ecfdf5; color:#166534; }
        .message.error { background:#fef2f2; color:#b91c1c; }
        .chat-item { border:1px solid #e5e7eb; border-radius:12px; padding:14px; margin-bottom:12px; background:#fcfdff; }
        .chat-meta { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; color:#64748b; font-size:13px; margin-bottom:8px; }
        .status-badge { display:inline-block; padding:5px 10px; border-radius:999px; font-size:12px; font-weight:700; }
        .status-new { background:#dbeafe; color:#1d4ed8; }
        .status-replied { background:#dcfce7; color:#166534; }
        .reply-form textarea { width:100%; min-height:80px; border:1px solid #cbd5e1; border-radius:10px; padding:10px 12px; margin-top:8px; }
        .btn { display:inline-block; padding:8px 12px; border:none; border-radius:10px; cursor:pointer; text-decoration:none; font-weight:700; }
        .btn-primary { background:#2563eb; color:#fff; }
        .btn-danger { background:#dc2626; color:#fff; }
        .nav-link { color:#fff; text-decoration:none; font-weight:700; background:rgba(255,255,255,0.16); padding:8px 12px; border-radius:999px; }
        .pager { display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:flex-end; margin-top:12px; }
    </style>
</head>
<body>
<div class="page">
  <div class="topbar">
    <div>
      <h2 style="margin:0;">💬 የቻት አስተዳደር</h2>
      <p style="margin:4px 0 0; color:#e0e7ff;">የጎብኚዎች መልእክቶችን ተመልከት እና መልስ ይስጡ።</p>
    </div>
    <a class="nav-link" href="admin_dashboard.php">← ዳሽቦርድ</a>
  </div>

  <div class="card">
    <?php if ($message !== ''): ?><div class="message success"><?php echo safe($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="message error"><?php echo safe($error); ?></div><?php endif; ?>

    <?php if (empty($messages)): ?>
      <p style="color:#64748b;">ምንም የቻት መልእክት የለም።</p>
    <?php else: ?>
      <?php foreach ($messages as $item): ?>
        <div class="chat-item">
          <div class="chat-meta">
            <div>
              <strong><?php echo safe($item['sender_name']); ?></strong>
              <span style="margin-left:8px; color:#94a3b8;"><?php echo safe($item['sender_type']); ?></span>
            </div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
              <span class="status-badge <?php echo ($item['status'] === 'replied') ? 'status-replied' : 'status-new'; ?>"><?php echo safe($item['status']); ?></span>
              <span><?php echo safe($item['created_at']); ?></span>
            </div>
          </div>
          <div style="margin-bottom:10px; color:#334155; line-height:1.6;"><?php echo safe($item['message']); ?></div>
          <?php if (!empty($item['reply_message'])): ?>
            <div style="background:#f5f3ff; padding:10px 12px; border-left:3px solid #8b5cf6; border-radius:8px; color:#5b21b6;">
              <strong>Admin reply:</strong> <?php echo safe($item['reply_message']); ?>
            </div>
          <?php else: ?>
            <form class="reply-form" method="post">
              <input type="hidden" name="chat_id" value="<?php echo (int)$item['id']; ?>">
              <textarea name="reply_message" placeholder="መልስ ይጻፉ..."></textarea>
              <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-top:8px;">
                <select name="status" style="padding:8px 10px; border-radius:8px; border:1px solid #cbd5e1;">
                  <option value="replied">replied</option>
                  <option value="pending">pending</option>
                </select>
                <div style="display:flex; gap:8px;">
                  <button class="btn btn-primary" type="submit">📤 ላክ</button>
                  <a class="btn btn-danger" href="admin_chat_management.php?delete=<?php echo (int)$item['id']; ?>" onclick="return confirm('ይህን መልእክት ሰርዝ?');">🗑️ ሰርዝ</a>
                </div>
              </div>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <div class="pager">
        <?php if ($page > 1): ?><a class="btn btn-primary" href="admin_chat_management.php?page=<?php echo max(1, $page - 1); ?>">◀ ቀዳሚ</a><?php endif; ?>
        <span style="color:#64748b;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
        <?php if ($page < $totalPages): ?><a class="btn btn-primary" href="admin_chat_management.php?page=<?php echo min($totalPages, $page + 1); ?>">ቀጣይ ▶</a><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
