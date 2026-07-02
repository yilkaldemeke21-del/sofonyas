<?php
require_once __DIR__ . '/db.php';

$paymentMethods = [
    'telebirr' => 'Telebirr',
    'cbe_birr' => 'CBE Birr',
    'chapa' => 'Chapa',
    'arifpay' => 'ArifPay',
];

$current = null;

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare('SELECT * FROM registrations WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $current = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $paymentMethod = $_POST['payment_method'] ?? null;
    if (!isset($paymentMethods[$paymentMethod])) {
        $paymentMethod = null;
    }

    $stmt = $pdo->prepare('UPDATE registrations SET payment_status = :status, payment_method = :payment_method, paid_at = :paid_at WHERE id = :id');
    $stmt->execute([
        ':status' => 'paid',
        ':payment_method' => $paymentMethod,
        ':paid_at' => date('Y-m-d H:i:s'),
        ':id' => $id,
    ]);

    $stmt = $pdo->prepare('SELECT * FROM registrations WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $current = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>ክፍያ ገጽ</title>
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
            <form method="post" style="margin-top: 20px;">
                <input type="hidden" name="id" value="<?php echo safe($current['id']); ?>">
                <label for="payment_method" style="display:block; margin-bottom:10px; font-weight:700;">የክፍያ ዘዴ ይምረጡ</label>
                <select id="payment_method" name="payment_method" required style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; margin-bottom:16px;">
                    <option value="">-- ይምረጡ --</option>
                    <?php foreach ($paymentMethods as $key => $label): ?>
                        <option value="<?php echo safe($key); ?>"><?php echo safe($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button">ክፍያ አረጋግጥ</button>
            </form>
        <?php else: ?>
            <p>ክፍያዎ ተከፍሏል። እናመሰግናለን።</p>
            <a class="button" href="dashboard.php">ዳሽቦርድ እይ</a>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
