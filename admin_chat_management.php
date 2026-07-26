<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$message = '';
$error = '';

function ensureChatReplyColumns(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS site_chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_type VARCHAR(30) NOT NULL DEFAULT "guest",
        sender_name VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        reply_message TEXT DEFAULT NULL,
        reply_admin_id INT DEFAULT NULL,
        reply_updated_at DATETIME DEFAULT NULL,
        reply_deleted TINYINT(1) NOT NULL DEFAULT 0,
        status VARCHAR(30) NOT NULL DEFAULT "new",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_site_chat_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $alterStatements = [
        "ALTER TABLE site_chat_messages ADD COLUMN IF NOT EXISTS reply_admin_id INT DEFAULT NULL",
        "ALTER TABLE site_chat_messages ADD COLUMN IF NOT EXISTS reply_updated_at DATETIME DEFAULT NULL",
        "ALTER TABLE site_chat_messages ADD COLUMN IF NOT EXISTS reply_deleted TINYINT(1) NOT NULL DEFAULT 0",
    ];

    foreach ($alterStatements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
        }
    }
}

ensureChatReplyColumns($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'], $_POST['chat_id'])) {
    $chatId = (int)$_POST['chat_id'];
    $reply = trim((string)$_POST['reply_message']);
    $status = isset($_POST['status']) ? trim((string)$_POST['status']) : 'replied';

    if ($chatId > 0 && $reply !== '') {
        $stmt = $pdo->prepare('UPDATE site_chat_messages SET reply_message = :reply_message, reply_admin_id = :reply_admin_id, reply_updated_at = NULL, status = :status, updated_at = NOW() WHERE id = :id AND (reply_admin_id IS NULL OR reply_admin_id = :reply_admin_id)');
        $stmt->execute([
            ':reply_message' => $reply,
            ':reply_admin_id' => (int)$_SESSION['admin_id'],
            ':status' => $status,
            ':id' => $chatId,
        ]);
        if ($stmt->rowCount() > 0) {
            $message = 'መልስ ተላክቷል።';
        } else {
            $error = 'ይህ መልእክት ለእርስዎ ተመራጭ አይደለም።';
        }
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
$printMode = isset($_GET['print']) && $_GET['print'] == '1';
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
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
      <?php if ($printMode): ?>
        <button class="nav-link" type="button" onclick="window.print()" style="border:none; cursor:pointer;">🖨 አትም</button>
      <?php else: ?>
        <a class="nav-link" href="admin_chat_management.php?print=1">🖨 ሪፖርት አትም</a>
      <?php endif; ?>
      <a class="nav-link" href="admin_dashboard.php">← ዳሽቦርድ</a>
    </div>
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
          <?php if ($printMode): ?>
            <?php if (!empty($item['reply_message'])): ?>
              <div style="background:#f5f3ff; padding:10px 12px; border-left:3px solid #8b5cf6; border-radius:8px; color:#5b21b6;">
                <strong>Admin reply:</strong> <?php echo safe($item['reply_message']); ?>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <?php if (!empty($item['reply_message'])): ?>
              <div style="background:#f5f3ff; padding:10px 12px; border-left:3px solid #8b5cf6; border-radius:8px; color:#5b21b6;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;">
                  <div>
                    <strong>Admin reply:</strong> <?php echo safe($item['reply_message']); ?>
                    <?php if (!empty($item['reply_updated_at'])): ?><span style="margin-left:8px; font-size:12px; color:#7c3aed;">(Edited)</span><?php endif; ?>
                  </div>
                  <?php if ((int)($item['reply_admin_id'] ?? 0) === (int)$_SESSION['admin_id']): ?>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                      <button type="button" class="btn btn-primary reply-edit-btn" data-reply-id="<?php echo (int)$item['id']; ?>" data-reply-text="<?php echo safe($item['reply_message']); ?>">✏️ Edit</button>
                      <button type="button" class="btn btn-danger reply-delete-btn" data-reply-id="<?php echo (int)$item['id']; ?>">🗑 Delete</button>
                    </div>
                  <?php endif; ?>
                </div>
                <form class="reply-edit-form" data-reply-id="<?php echo (int)$item['id']; ?>" style="display:none; margin-top:10px;">
                  <textarea name="reply_message" rows="3" style="width:100%; min-height:80px; border:1px solid #cbd5e1; border-radius:10px; padding:10px 12px;"><?php echo safe($item['reply_message']); ?></textarea>
                  <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:8px;">
                    <button class="btn btn-primary" type="submit">💾 Save</button>
                    <button class="btn btn-danger reply-edit-cancel" type="button">Cancel</button>
                  </div>
                </form>
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
<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.reply-edit-btn').forEach(function (button) {
      button.addEventListener('click', function () {
        const replyId = this.dataset.replyId;
        const form = document.querySelector('.reply-edit-form[data-reply-id="' + replyId + '"]');
        if (form) {
          form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
      });
    });

    document.querySelectorAll('.reply-edit-cancel').forEach(function (button) {
      button.addEventListener('click', function () {
        const form = this.closest('.reply-edit-form');
        if (form) {
          form.style.display = 'none';
        }
      });
    });

    document.querySelectorAll('.reply-edit-form').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        const replyId = this.dataset.replyId;
        const textarea = this.querySelector('textarea[name="reply_message"]');
        const message = textarea ? textarea.value.trim() : '';
        if (!replyId || !message) {
          return;
        }
        fetch('chat_reply_actions.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: new URLSearchParams({ action: 'edit_reply', reply_id: replyId, reply_message: message })
        })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            if (data && data.success) {
              const replyText = document.querySelector('.reply-edit-form[data-reply-id="' + replyId + '"] textarea');
              const replyBlock = document.querySelector('.reply-edit-form[data-reply-id="' + replyId + '"]');
              if (replyBlock) {
                const parent = replyBlock.closest('div[style*="background:#f5f3ff"]');
                if (parent) {
                  const replyLabel = parent.querySelector('strong');
                  if (replyLabel) {
                    replyLabel.nextSibling.textContent = ' ' + message;
                  }
                  const editedBadge = parent.querySelector('span[style*="color:#7c3aed"]');
                  if (!editedBadge) {
                    const badge = document.createElement('span');
                    badge.style.marginLeft = '8px';
                    badge.style.fontSize = '12px';
                    badge.style.color = '#7c3aed';
                    badge.textContent = '(Edited)';
                    replyLabel.parentNode.appendChild(badge);
                  }
                }
                replyBlock.style.display = 'none';
              }
            }
          })
          .catch(function () {
            window.location.reload();
          });
      });
    });

    document.querySelectorAll('.reply-delete-btn').forEach(function (button) {
      button.addEventListener('click', function () {
        const replyId = this.dataset.replyId;
        if (!replyId || !window.confirm('Are you sure you want to delete this reply?')) {
          return;
        }
        fetch('chat_reply_actions.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: new URLSearchParams({ action: 'delete_reply', reply_id: replyId })
        })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            if (data && data.success) {
              const btn = document.querySelector('.reply-delete-btn[data-reply-id="' + replyId + '"]');
              const container = btn ? btn.closest('.chat-item') : null;
              if (container) {
                const replyBlock = container.querySelector('div[style*="background:#f5f3ff"]');
                if (replyBlock) {
                  replyBlock.remove();
                  const form = document.createElement('form');
                  form.className = 'reply-form';
                  form.method = 'post';
                  form.innerHTML = '<input type="hidden" name="chat_id" value="' + container.querySelector('input[name="chat_id"]')?.value + '"><textarea name="reply_message" placeholder="መልስ ይጻፉ..."></textarea><div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-top:8px;"><select name="status" style="padding:8px 10px; border-radius:8px; border:1px solid #cbd5e1;"><option value="replied">replied</option><option value="pending">pending</option></select><div style="display:flex; gap:8px;"><button class="btn btn-primary" type="submit">📤 ላክ</button><a class="btn btn-danger" href="admin_chat_management.php?delete=' + replyId + '" onclick="return confirm(\'ይህን መልእክት ሰርዝ?\');">🗑️ ሰርዝ</a></div></div>';
                  container.appendChild(form);
                }
              }
            }
          })
          .catch(function () {
            window.location.reload();
          });
      });
    });
  });
</script>
</body>
</html>
