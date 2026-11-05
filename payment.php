<?php
session_start();
require_once __DIR__ . '/db.php';
requireRole(['student'], $pdo);

$current = null;
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM registrations WHERE id = :id AND student_id = :student_id LIMIT 1');
    $stmt->execute([
        ':id' => trim((string)$_GET['id']),
        ':student_id' => (string)$_SESSION['student_id'],
    ]);
    $current = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የክፍያ ሁኔታ</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f2f8ff; color: #1f2937; padding: 20px; }
        .card { max-width: 650px; margin: auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 18px rgba(0,0,0,0.08); }
        .button { display: inline-block; margin-top: 15px; padding: 10px 18px; background: #2563eb; color: white; border-radius: 8px; text-decoration: none; }
        .badge { padding: 8px 12px; border-radius: 8px; display: inline-block; margin-top: 8px; }
        .paid { background: #d1fae5; color: #065f46; }
        .unpaid { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
<div class="card">
    <?php if ($current === null): ?>
        <h1>እባክዎ የተሳሳተ መለያ ሰጥተዋል</h1>
        <p>እባክዎ ዳሽቦርድ ወይም የምዝገባ ቅጽ እንደገና ይዘምቱ።</p>
        <a class="button" href="dashboard.php">ዳሽቦርድ እይ</a>
    <?php else: ?>
        <h1>የክፍያ ዝርዝር</h1>
        <p><strong>ስም:</strong> <?php echo safe($current['name']); ?></p>
        <p><strong>ኢሜይል:</strong> <?php echo safe($current['email']); ?></p>
        <p><strong>የተማሪ መለያ:</strong> <?php echo safe($current['student_id']); ?></p>
        <p><strong>ኮርስ:</strong> <?php echo safe($current['course']); ?></p>
        <p><strong>መጠን:</strong> <?php echo safe($current['amount']); ?> ብር</p>
        <p class="badge <?php echo ($current['payment_status'] === 'paid' ? 'paid' : 'unpaid'); ?>">
            የክፍያ ሁኔታ: <?php echo safe($current['payment_status']); ?>
        </p>
        <?php if (!empty($current['payment_method'])): ?>
            <p><strong>የክፍያ መንገድ:</strong> <?php echo safe(ucwords(str_replace('_', ' ', $current['payment_method']))); ?></p>
        <?php endif; ?>

        <?php if ($current['payment_status'] !== 'paid'): ?>
            <p>Online payment is not connected yet. Payment status is updated only after an administrator verifies the payment.</p>
            <a class="button" href="student_dashboard.php">ወደ ዳሽቦርድ ተመለስ</a>
        <?php else: ?>
            <p>ክፍያዎ ተከፍሏል። እናመሰግናለን።</p>
            <a class="button" href="dashboard.php">ዳሽቦርድ እይ</a>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
