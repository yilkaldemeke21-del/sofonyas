<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

if (isset($_GET['mark_paid'])) {
    $id = $_GET['mark_paid'];
    $stmt = $pdo->prepare('UPDATE registrations SET payment_status = :status, paid_at = :paid_at WHERE id = :id');
    $stmt->execute([
        ':status' => 'paid',
        ':paid_at' => date('Y-m-d H:i:s'),
        ':id' => $id,
    ]);
    header('Location: admin_registrations.php');
    exit;
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM registrations WHERE id = :id');
    $stmt->execute([':id' => $id]);
    header('Location: admin_registrations.php');
    exit;
}

$stmt = $pdo->query('SELECT * FROM registrations ORDER BY created_at DESC');
$registrations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>የተማሪ ምዝገባ</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f7fa; color: #333; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: white; text-decoration: none; margin-left: 15px; padding: 8px 14px; background: rgba(255,255,255,0.1); border-radius: 5px; }
        .navbar a:hover { background: rgba(255,255,255,0.2); }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h1 { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f1f5ff; }
        .badge { padding: 6px 10px; border-radius: 6px; font-size: 0.95rem; }
        .paid { background: #d4f1d8; color: #1d6a2b; }
        .unpaid { background: #ffe6e6; color: #a41616; }
        .button { display: inline-block; padding: 8px 14px; border-radius: 6px; text-decoration: none; color: white; background: #3f6ad8; }
        .button:hover { background: #2c4db3; }
        .danger { background: #e74c3c; }
        .danger:hover { background: #c0392b; }
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
        <h1>የተማሪ ምዝገባ</h1>
        <?php if (empty($registrations)): ?>
            <p>ምንም የተማሪ መዝገብ የለም።</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ስም</th>
                        <th>ኢሜይል</th>
                        <th>የተማሪ መለያ</th>
                        <th>ኮርስ</th>
                        <th>መጠን</th>
                        <th>የክፍያ መንገድ</th>
                        <th>የክፍያ ሁኔታ</th>
                        <th>እርምጃ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registrations as $row): ?>
                        <tr>
                            <td><?php echo safe($row['name']); ?></td>
                            <td><?php echo safe($row['email']); ?></td>
                            <td><?php echo safe($row['student_id']); ?></td>
                            <td><?php echo safe($row['course']); ?></td>
                            <td><?php echo number_format($row['amount'], 2); ?> ብር</td>
                            <td><?php echo safe($row['payment_method'] ? ucwords(str_replace('_', ' ', $row['payment_method'])) : '—'); ?></td>
                            <td><span class="badge <?php echo ($row['payment_status'] === 'paid' ? 'paid' : 'unpaid'); ?>"><?php echo safe($row['payment_status']); ?></span></td>
                            <td>
                                <?php if ($row['payment_status'] !== 'paid'): ?>
                                    <a class="button" href="admin_registrations.php?mark_paid=<?php echo urlencode($row['id']); ?>">ክፍያ አረጋግጥ</a>
                                <?php else: ?>
                                    ተከፍሏል
                                <?php endif; ?>
                                <a class="button danger" href="admin_registrations.php?delete=<?php echo urlencode($row['id']); ?>" onclick="return confirm('ይህን ምዝገባ ሰርዝ?');">ሰርዝ</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
