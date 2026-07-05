<?php
session_start();
require_once __DIR__ . '/db.php';

$code = trim((string)($_GET['code'] ?? ''));
$studentId = trim((string)($_GET['student_id'] ?? ''));
$foundStudent = null;
$errorMessage = '';

if ($code !== '') {
    $normalized = strtoupper(trim($code));
    if (str_starts_with($normalized, 'SFC-')) {
        $studentId = preg_replace('/[^A-Z0-9]/', '', substr($normalized, 4));
    }
}

if ($studentId !== '') {
    $stmt = $pdo->prepare('SELECT * FROM students WHERE REPLACE(UPPER(student_id), "-", "") = :student_id LIMIT 1');
    $stmt->execute([':student_id' => $studentId]);
    $foundStudent = $stmt->fetch();
    if (!$foundStudent) {
        $errorMessage = 'ይህ መለያ አልተገኘም። እባክዎ የትክክለኛውን ኮድ ይጠቀሙ።';
    }
} elseif ($code !== '') {
    $errorMessage = 'የማረጋገጫ ኮድ የተሳሳተ ነው።';
}

function formatStudentField(string $label, string $value): string
{
    return '<div style="margin-bottom: 10px;"><strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong><div style="color:#111827; margin-top:6px;">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</div></div>';
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Student ID</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #eef2ff; color: #0f172a; }
        .page { max-width: 900px; margin: 24px auto; padding: 20px; }
        .card { background: white; border-radius: 18px; box-shadow: 0 18px 40px rgba(15,23,42,0.08); padding: 24px; border: 1px solid #dde4f0; }
        h1 { margin: 0 0 14px; font-size: 28px; color: #4338ca; }
        p { margin: 0 0 14px; color: #475569; line-height: 1.7; }
        .form-row { display: grid; gap: 12px; margin-bottom: 18px; }
        input[type="text"] { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 16px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 12px 18px; border-radius: 12px; background: linear-gradient(135deg, #2563eb, #4f46e5); color: white; text-decoration: none; border: none; cursor: pointer; font-weight: 700; }
        .alert { padding: 14px 16px; border-radius: 14px; margin-bottom: 18px; }
        .alert.error { background: #fee2e2; color: #991b1b; }
        .alert.success { background: #dcfce7; color: #166534; }
        .student-summary { display: grid; gap: 18px; margin-top: 20px; }
        .summary-box { padding: 18px; border-radius: 16px; background: #f8fafc; border: 1px solid #c7d2fe; }
        .summary-box strong { display: block; margin-bottom: 6px; color: #111827; }
        .qr-code { margin-top: 18px; text-align: center; }
        .qr-code img { width: 210px; height: 210px; border-radius: 18px; }
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <h1>Student ID Verification</h1>
        <p>እባክዎ የተማሪው ኮድ ወይም `SFC-` የሚጀምር የID ኮድ ያስገቡ። ይህን ኮድ ከየID ካርድ የተጠቀሱ በታች የQR ኮድ እና የURL ጋር ይገናኛል።</p>
        <form method="get" action="verify_student_id.php" class="form-row">
            <input type="text" name="code" placeholder="Enter verification code or QR URL" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" />
            <button type="submit" class="btn">Verify</button>
        </form>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($foundStudent): ?>
            <div class="alert success">Verification succeeded. This student ID is valid.</div>
            <div class="student-summary">
                <?php echo formatStudentField('Name', $foundStudent['name'] ?? $foundStudent['student_name'] ?? 'ተማሪ'); ?>
                <?php echo formatStudentField('Student ID', $foundStudent['student_id'] ?? ''); ?>
                <?php echo formatStudentField('Email', $foundStudent['email'] ?? ''); ?>
                <?php echo formatStudentField('Country', $foundStudent['country'] ?? ''); ?>
                <?php echo formatStudentField('City', $foundStudent['city'] ?? ''); ?>
                <div class="summary-box">
                    <strong>Verification Code</strong>
                    <span><?php echo htmlspecialchars($code !== '' ? $code : 'N/A', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
            <div class="qr-code">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=210x210&data=<?php echo urlencode(buildAppUrl('verify_student_id.php?code=' . urlencode($code))); ?>" alt="Verification QR code" />
                <p style="margin-top:10px; color:#475569;">Scan this QR code to verify again later.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
