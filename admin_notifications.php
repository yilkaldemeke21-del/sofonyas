<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_config.php';
requireRole(['admin'], $pdo);

$notificationCounts = [
    'contact_messages' => 0,
    'newsletter_subscribers' => 0,
    'email_notifications' => 0,
];

try {
    $notificationCounts['contact_messages'] = (int)$pdo->query('SELECT COUNT(*) FROM contact_messages WHERE status = "new"')->fetchColumn();
} catch (Throwable $e) {
}

try {
    $notificationCounts['newsletter_subscribers'] = (int)$pdo->query('SELECT COUNT(*) FROM newsletter_subscribers WHERE status = "active"')->fetchColumn();
} catch (Throwable $e) {
}

try {
    $notificationCounts['email_notifications'] = (int)$pdo->query('SELECT COUNT(*) FROM email_notifications')->fetchColumn();
} catch (Throwable $e) {
}

function safeHtml($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Notifications</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f7fafc;color:#0f172a;margin:0;padding:24px}
        .page-wrap{max-width:1100px;margin:0 auto}
        h1{margin-bottom:14px;color:#1d4ed8}
        .grid{display:grid;gap:18px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));}
        .card{background:#fff;border-radius:16px;padding:20px;box-shadow:0 12px 30px rgba(15,23,42,0.06);border:1px solid #e2e8f0}
        .card h2{margin-top:0;font-size:1.1rem;color:#0f3d91}
        .count{font-size:2.4rem;font-weight:700;margin:14px 0;color:#0f172a}
        .actions a{display:inline-flex;margin:4px 4px 0 0;padding:8px 12px;border-radius:10px;text-decoration:none;color:#fff;background:#2563eb;font-size:0.95rem}
        .actions a.secondary{background:#64748b}
        .note{margin-top:10px;color:#475569;line-height:1.6}
    </style>
</head>
<body>
<div class="page-wrap">
    <h1>Admin Notifications Center</h1>
    <p class="note">Quick access to contact messages, newsletter subscribers, and recent email notifications. Use these links to manage incoming communication and subscriber activity.</p>
    <div class="grid">
        <div class="card">
            <h2>New contact messages</h2>
            <div class="count"><?php echo safeHtml($notificationCounts['contact_messages']); ?></div>
            <div class="actions">
                <a href="admin_reports.php?type=contact_messages">Manage</a>
                <a class="secondary" href="admin_reports.php?type=contact_messages&print=1">Print</a>
            </div>
        </div>
        <div class="card">
            <h2>Active newsletter subscribers</h2>
            <div class="count"><?php echo safeHtml($notificationCounts['newsletter_subscribers']); ?></div>
            <div class="actions">
                <a href="admin_reports.php?type=newsletter_subscribers">View Subscribers</a>
                <a class="secondary" href="admin_reports.php?type=newsletter_subscribers&print=1">Print</a>
            </div>
        </div>
        <div class="card">
            <h2>Sent email notifications</h2>
            <div class="count"><?php echo safeHtml($notificationCounts['email_notifications']); ?></div>
            <div class="actions">
                <a href="admin_dashboard.php">Dashboard</a>
                <a class="secondary" href="admin_reports.php?type=contact_messages">Any Message Logs</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
