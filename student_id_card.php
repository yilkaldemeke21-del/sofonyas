<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit;
}

$studentId = $_SESSION['student_id'];
$stmt = $pdo->prepare('SELECT * FROM students WHERE student_id = :student_id LIMIT 1');
$stmt->execute([':student_id' => $studentId]);
$student = $stmt->fetch();
if (!$student) {
    http_response_code(404);
    echo 'ተማሪ አልተገኘም።';
    exit;
}

$registrations = $pdo->prepare('SELECT course, course_id, payment_status, created_at FROM registrations WHERE student_id = :student_id ORDER BY created_at DESC');
$registrations->execute([':student_id' => $studentId]);
$registrations = $registrations->fetchAll();

$displayName = $student['name'] ?? $student['student_name'] ?? 'ተማሪ';
$displayEmail = $student['email'] ?? '';
$displayCountry = $student['country'] ?? 'ኢትዮጵያ';
$displayCity = $student['city'] ?? 'N/A';

$verificationCode = 'SFC-' . strtoupper(preg_replace('/[^A-Z0-9]/', '', $studentId));
$verificationUrl = buildAppUrl('verify_student_id.php?code=' . urlencode($verificationCode));

$qrData = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');
$qrCodeSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=210x210&data=' . urlencode($qrData);

function generateIdCardPdfFile(array $student, array $registrations, string $verificationCode, string $verificationUrl)
{
    $name = (string)($student['name'] ?? $student['student_name'] ?? 'Student');
    $email = (string)($student['email'] ?? '');
    $studentId = (string)($student['student_id'] ?? '');
    $country = (string)($student['country'] ?? 'Ethiopia');
    $city = (string)($student['city'] ?? 'N/A');

    $lines = [
        'Name: ' . $name,
        'Student ID: ' . $studentId,
        'Email: ' . $email,
        'Location: ' . $city . ', ' . $country,
        'Verification Code: ' . $verificationCode,
        'Verification URL: ' . $verificationUrl,
    ];

    $courses = [];
    foreach ($registrations as $row) {
        $course = (string)($row['course'] ?? 'Unknown course');
        $status = ucfirst((string)($row['payment_status'] ?? 'unpaid'));
        $courses[] = 'Course: ' . $course . ' (' . $status . ')';
    }

    if (!empty($courses)) {
        $lines[] = 'Registered Courses:';
        foreach ($courses as $courseLine) {
            $lines[] = ' - ' . $courseLine;
        }
    } else {
        $lines[] = 'Registered Courses: None';
    }

    $pdfContent = build_simple_pdf_bytes('Student ID Card', $lines, 'Sofyias Educational Platform');
    $tmpPdf = tempnam(sys_get_temp_dir(), 'idcard_pdf_');
    if ($tmpPdf === false) {
        return false;
    }

    if (file_put_contents($tmpPdf, $pdfContent) === false) {
        @unlink($tmpPdf);
        return false;
    }

    return $tmpPdf;
}

if (isset($_GET['download'])) {
    $pdfFile = generateIdCardPdfFile($student, $registrations, $verificationCode, $verificationUrl);
    if ($pdfFile === false) {
        $downloadError = 'PDF ማውረድ አልተቻለም። እባክዎ ድጋሚ ይሞክሩ ወይም በእጅ ይታዩ።';
    } else {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="student-id-card-' . preg_replace('/[^A-Za-z0-9_-]/', '', $studentId) . '.pdf"');
        header('Content-Length: ' . filesize($pdfFile));
        readfile($pdfFile);
        @unlink($pdfFile);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የተማሪ ID Card</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%); color: #0f172a; }
        .page { max-width: 1100px; margin: 24px auto; padding: 18px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 22px; }
        .topbar h1 { margin: 0; font-size: 28px; }
        .topbar p { margin: 0; color: #475569; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 12px 18px; border-radius: 14px; color: white; text-decoration: none; font-weight: 700; box-shadow: 0 12px 24px rgba(37,99,235,0.18); }
        .btn.primary { background: linear-gradient(135deg, #2563eb, #4f46e5); }
        .btn.secondary { background: linear-gradient(135deg, #0f172a, #334155); }
        .alert { background: #fde2e2; color: #991b1b; padding: 14px 16px; border-radius: 14px; margin-bottom: 16px; }
        .card { background: white; border-radius: 24px; padding: 28px; box-shadow: 0 18px 50px rgba(15,23,42,0.08); border: 1px solid #e2e8f0; }
        .card .section { display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 24px; align-items: start; }
        .info-box { display: grid; gap: 16px; }
        .field { display: grid; gap: 4px; }
        .field span:first-child { color: #6b7280; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
        .field span:last-child { font-size: 18px; color: #111827; font-weight: 700; }
        .qr-box { background: #eef2ff; border-radius: 22px; padding: 22px; display: grid; place-items: center; }
        .qr-box img { width: 210px; height: 210px; border-radius: 18px; }
        .badge { display: inline-flex; padding: 10px 14px; border-radius: 999px; background: #e0e7ff; color: #3730a3; font-weight: 700; margin-top: 16px; }
        .section-title { margin: 0 0 10px; color: #4338ca; font-size: 18px; font-weight: 800; }
        .course-list { margin: 0; padding-left: 20px; color: #111827; }
        .course-list li { margin-bottom: 8px; }
        .verification { margin-top: 24px; padding: 18px 20px; border-radius: 18px; background: #f8fafc; border: 1px solid #c7d2fe; }
        .verification strong { display: block; color: #1e3a8a; margin-bottom: 8px; }
        @media (max-width: 840px) { .card .section { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>የተማሪ Digital ID Card</h1>
            <p>እዚህ ላይ የሚገኙትን ዲጂታል ካርድ ይመልከቱ፣ የPDF ውስጥ ይውሰዱ እና ይረጋግጡ።</p>
        </div>
        <div class="actions">
            <a class="btn primary" href="student_id_card.php?download=1">PDF አውርድ</a>
            <a class="btn secondary" href="student_dashboard.php">ወደ ዳሽቦርድ</a>
        </div>
    </div>
    <?php if (!empty($downloadError)): ?>
        <div class="alert"><?php echo safe($downloadError); ?></div>
    <?php endif; ?>
    <div class="card">
        <div class="section">
            <div class="info-box">
                <div class="field"><span>ስም</span><span><?php echo safe($displayName); ?></span></div>
                <div class="field"><span>Student ID</span><span><?php echo safe($studentId); ?></span></div>
                <div class="field"><span>ኢሜይል</span><span><?php echo safe($displayEmail); ?></span></div>
                <div class="field"><span>ከተማ</span><span><?php echo safe($displayCity); ?></span></div>
                <div class="field"><span>አገር</span><span><?php echo safe($displayCountry); ?></span></div>
                <div class="field"><span>Verification Code</span><span><?php echo safe($verificationCode); ?></span></div>
                <div class="badge">QR እና የእውነት አረጋጋጭ</div>
            </div>
            <div class="qr-box">
                <img src="<?php echo $qrCodeSrc; ?>" alt="QR Code" />
                <p style="margin-top:14px; font-size:14px; color:#475569; text-align:center;">Scan to verify this ID card</p>
            </div>
        </div>
        <div class="verification">
            <strong>Verification URL</strong>
            <p style="margin:0; word-break: break-all;"><a class="link" href="<?php echo safe($verificationUrl); ?>" target="_blank"><?php echo safe($verificationUrl); ?></a></p>
        </div>
        <div style="margin-top: 24px;">
            <h2 class="section-title">Registered Courses</h2>
            <ul class="course-list">
                <?php if (empty($registrations)): ?>
                    <li>አልተመዘገበም</li>
                <?php else: ?>
                    <?php foreach ($registrations as $row): ?>
                        <li><?php echo safe($row['course'] ?? 'Unknown course'); ?> • <?php echo safe(ucfirst($row['payment_status'] ?? 'unpaid')); ?></li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
</body>
</html>
