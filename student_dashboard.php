<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit;
}

$studentId = $_SESSION['student_id'];
$stmt = $pdo->prepare('SELECT * FROM students WHERE student_id = :student_id');
$stmt->execute([':student_id' => $studentId]);
$student = $stmt->fetch();

$stmt = $pdo->prepare('SELECT * FROM registrations WHERE student_id = :student_id ORDER BY created_at DESC');
$stmt->execute([':student_id' => $studentId]);
$registrations = $stmt->fetchAll();

$summary = ['total' => count($registrations), 'paid' => 0, 'unpaid' => 0, 'revenue' => 0.0];
foreach ($registrations as $row) {
    if ($row['payment_status'] === 'paid') {
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
    <title>የተማሪ ዳሽቦርድ</title>
    <style>
        body { font-family: Arial, sans-serif; background: #eef2fb; color: #1f2937; margin: 0; padding: 20px; }
        .container { max-width: 1080px; margin: auto; background: white; border-radius: 12px; box-shadow: 0 10px 30px rgba(15,23,42,0.08); padding: 24px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .header h1 { font-size: 28px; }
        .button { padding: 10px 16px; background: #2563eb; color: white; border: none; border-radius: 8px; text-decoration: none; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .card { padding: 18px; border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .card h2 { margin: 0 0 10px; font-size: 16px; color: #475569; }
        .card p { font-size: 30px; font-weight: bold; margin: 0; color: #1d4ed8; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        th { background: #f1f5f9; }
        .badge { display: inline-flex; padding: 6px 12px; border-radius: 9999px; font-size: 13px; }
        .paid { background: #dcfce7; color: #166534; }
        .unpaid { background: #fee2e2; color: #b91c1c; }
        .action-link { color: #2563eb; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>እንኳን በደህና መጡ, <?php echo safe($student['name']); ?></h1>
            <p>የኢሜይልዎ: <?php echo safe($student['email']); ?></p>
        </div>
        <a class="button" href="student_logout.php">ውጣ</a>
    </div>

    <div class="stats">
        <div class="card">
            <h2>ጠቅላላ መዝገቦች</h2>
            <p><?php echo $summary['total']; ?></p>
        </div>
        <div class="card">
            <h2>ክፍያ የከፈለ</h2>
            <p><?php echo $summary['paid']; ?></p>
        </div>
        <div class="card">
            <h2>ክፍያ ያልከፈለ</h2>
            <p><?php echo $summary['unpaid']; ?></p>
        </div>
        <div class="card">
            <h2>ጠቅላላ ገቢ (ብር)</h2>
            <p><?php echo number_format($summary['revenue'], 2); ?></p>
        </div>
    </div>

    <div class="card" style="margin-bottom: 24px;">
        <h2>ቀጥታ ፈተናዎች</h2>
        <p style="margin-bottom: 12px; color: #475569;">የተማሪዎ ፈተናዎችን ከዚህ በኋላ ይመልከቱ።</p>
        <a class="button" href="exam20.php" style="display:inline-block; margin-right:10px;">ፈተና 20</a>
        <a class="button" href="short_exam.php" style="display:inline-block;">አጭር ፈተና</a>
    </div>

    <h2>የእርስዎ ተመዝጋቢ ኮርሶች</h2>
    <p style="margin-bottom: 16px; color: #475569;">ከዚህ በኋላ የተመዘገቡትን ኮርሶች እና የክፍያ ሁኔታዎች በቀላሉ ይመልከቱ።</p>
    <?php if (empty($registrations)): ?>
        <p>እስካሁን ምንም ተመዝጋቢ ኮርስ አልተመዘገበም።</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ኮርስ</th>
                    <th>መጠን</th>
                    <th>የክፍያ ሁኔታ</th>
                    <th>ቀን</th>
                    <th>እርምጃ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registrations as $row): ?>
                    <tr>
                        <td><?php echo safe($row['course']); ?></td>
                        <td><?php echo safe($row['amount']); ?> ብር</td>
                        <td><span class="badge <?php echo ($row['payment_status'] === 'paid' ? 'paid' : 'unpaid'); ?>"><?php echo safe($row['payment_status']); ?></span></td>
                        <td><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                        <td>
                            <?php if ($row['payment_status'] !== 'paid'): ?>
                                <a class="action-link" href="payment.php?id=<?php echo urlencode($row['id']); ?>">ክፍያ አረጋግጥ</a>
                            <?php else: ?>
                                ተከፍሏል
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
