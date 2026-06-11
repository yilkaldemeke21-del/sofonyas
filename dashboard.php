<?php
require_once __DIR__ . '/db.php';

if (isset($_GET['mark_paid'])) {
    $markPaidId = $_GET['mark_paid'];
    $stmt = $pdo->prepare('UPDATE registrations SET payment_status = :status WHERE id = :id');
    $stmt->execute([':status' => 'paid', ':id' => $markPaidId]);
    header('Location: dashboard.php');
    exit;
}

$stmt = $pdo->query('SELECT * FROM registrations ORDER BY created_at DESC');
$registrations = $stmt->fetchAll();

$summary = [
    'total' => count($registrations),
    'paid' => 0,
    'unpaid' => 0,
    'revenue' => 0,
];
foreach ($registrations as $row) {
    if (($row['payment_status'] ?? '') === 'paid') {
        $summary['paid']++;
        $summary['revenue'] += (float)$row['amount'];
    } else {
        $summary['unpaid']++;
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>ኦንላይን ዳሽቦርድ</title>
    <style>
        body { font-family: Arial, sans-serif; background: #eef2fb; color: #222; padding: 20px; }
        .container { max-width: 1000px; margin: auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 14px rgba(0,0,0,0.08); }
        h1 { margin-bottom: 5px; }
        .stats { display: flex; gap: 15px; margin-bottom: 20px; }
        .stat { flex: 1; padding: 15px; border-radius: 8px; background: #f7f9ff; border: 1px solid #dde3f3; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px 10px; border-bottom: 1px solid #ddd; }
        th { background: #f1f5ff; }
        .badge { padding: 6px 10px; border-radius: 6px; font-size: 0.95rem; }
        .paid { background: #d4f1d8; color: #1d6a2b; }
        .unpaid { background: #ffe6e6; color: #a41616; }
        .button { display: inline-block; padding: 8px 14px; border-radius: 6px; text-decoration: none; color: white; background: #3f6ad8; }
        .top-actions { display: flex; flex-wrap: wrap; gap: 10px; margin: 14px 0 18px; }
        .top-actions a { background: #2563eb; color: #fff; padding: 10px 12px; border-radius: 8px; text-decoration: none; font-weight: 700; }
        .top-actions a.secondary { background: #7c3aed; }
        .top-actions a.ghost { background: #0f766e; }
    </style>
</head>
<body>
<div class="container">
    <h1>ኦንላይን ዳሽቦርድ</h1>
    <p style="color:#475569; margin-top:6px;">የተማሪዎች ምዝገባ፣ ክፍያ ሁኔታ እና ቀጥታ ድርጊቶች ለመከታተል የተዘጋጀ።</p>
    <div class="top-actions">
        <a href="admin_dashboard.php">🛠️ አድሚን ዳሽቦርድ</a>
        <a class="secondary" href="discussion_forum.php">💬 ዲስኬሽን ፎርም</a>
        <a class="ghost" href="library.php">📚 ላይበራሪ ዳሽቦርድ</a>
    </div>
    <div class="stats">
        <div class="stat"><strong>ጠቅላላ ተመዝጋቢዎች</strong><br><?php echo $summary['total']; ?></div>
        <div class="stat"><strong>ክፍያ የተከፈለ</strong><br><?php echo $summary['paid']; ?></div>
        <div class="stat"><strong>ክፍያ ያልተከፈለ</strong><br><?php echo $summary['unpaid']; ?></div>
        <div class="stat"><strong>ጠቅላላ ገቢ (ብር)</strong><br><?php echo number_format($summary['revenue'], 2); ?></div>
    </div>

    <?php if (empty($registrations)): ?>
        <p>እስካሁን ምንም የምዝገባ መረጃ የለም።</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ስም</th>
                    <th>ኢሜይል</th>
                    <th>መለያ</th>
                    <th>ኮርስ</th>
                    <th>መጠን</th>
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
                        <td><?php echo safe($row['amount']); ?> ብር</td>
                        <td><span class="badge <?php echo ($row['payment_status'] === 'paid' ? 'paid' : 'unpaid'); ?>"><?php echo safe($row['payment_status']); ?></span></td>
                        <td>
                            <?php if ($row['payment_status'] !== 'paid'): ?>
                                <a class="button" href="payment.php?id=<?php echo urlencode($row['id']); ?>">ክፍያ አረጋግጥ</a>
                            <?php else: ?>
                                ተከፍሏል
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <p style="margin-top: 20px;"><a class="button" href="sofonyas2.html">መመለስ</a></p>
</div>
</body>
</html>
