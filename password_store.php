<?php
session_start();
require_once __DIR__ . '/db.php';
requireRole(['admin'], $pdo);
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password security</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f7fa; color: #333; margin: 0; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: white; text-decoration: none; margin-left: 15px; padding: 8px 14px; background: rgba(255,255,255,0.1); border-radius: 5px; }
        .navbar a:hover { background: rgba(255,255,255,0.2); }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h1 { margin-bottom: 10px; }
        p.note { color: #4b5563; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; word-break: break-word; }
        th { background: #eef2ff; font-size: 13px; }
        td { font-size: 14px; }
        .hash { font-family: Consolas, monospace; font-size: 12px; color: #111827; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 12px; font-weight: 700; background: #ecfdf5; color: #047857; }
        .badge.muted { background: #f3f4f6; color: #4b5563; }
    </style>
</head>
<body>
<div class="navbar">
    <div>
        <a href="admin_dashboard.php">← ዳሽቦርድ</a>
    </div>
    <div>
        <span>እንኳን ደህና መጡ, <?php echo safe($_SESSION['admin_username']); ?>!</span>
        <a href="admin_logout.php">ውጣ</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <h1>Password recovery</h1>
        <p class="note">For security, password hashes and candidate passwords are not displayed. Ask the account holder to use the password recovery flow.</p>
        <a href="admin_dashboard.php">Return to admin dashboard</a>
    </div>
</div>
</body>
</html>
