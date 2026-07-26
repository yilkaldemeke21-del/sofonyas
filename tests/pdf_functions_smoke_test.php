<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION['admin_id'] = 1;
$_SESSION['student_id'] = 'TEST-001';
$_GET = [];

require_once __DIR__ . '/../db.php';

try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO students (student_id, name, email, country, city, role) VALUES (:student_id, :name, :email, :country, :city, 'Student')");
    $stmt->execute([
        ':student_id' => 'TEST-001',
        ':name' => 'Test Student',
        ':email' => 'student@example.com',
        ':country' => 'Ethiopia',
        ':city' => 'Addis Ababa',
    ]);
} catch (Throwable $e) {
    // Ignore database setup issues for the smoke test and continue with the function-level check.
}

ob_start();
require_once __DIR__ . '/../admin_certificate.php';
ob_end_clean();

ob_start();
require_once __DIR__ . '/../student_id_card.php';
ob_end_clean();

$certResult = generate_certificate_pdf_file([
    'id' => 7,
    'student_name' => 'Test Student',
    'exam_type' => 'Final Exam',
    'score' => 88,
    'total_questions' => 100,
    'issued_at' => '2025-01-01',
    'signer_name' => 'Authorized Signatory',
]);

$idCardResult = generateIdCardPdfFile(
    ['name' => 'Test Student', 'student_id' => 'TEST-001', 'email' => 'student@example.com', 'country' => 'Ethiopia', 'city' => 'Addis Ababa'],
    [['course' => 'Intro Course', 'payment_status' => 'paid']],
    'SFC-TEST001',
    'https://example.com/verify'
);

if ($certResult === false || !isset($certResult['path']) || !is_file($certResult['path'])) {
    fwrite(STDERR, "CERT_FAIL\n");
    exit(1);
}

if ($idCardResult === false || !is_file($idCardResult)) {
    fwrite(STDERR, "IDCARD_FAIL\n");
    exit(1);
}

$certBytes = file_get_contents($certResult['path']);
$idCardBytes = file_get_contents($idCardResult);

if (strpos($certBytes, '%PDF') !== 0 || strpos($idCardBytes, '%PDF') !== 0) {
    fwrite(STDERR, "BAD_PDF\n");
    exit(1);
}

fwrite(STDOUT, "PDF_OK " . basename($certResult['path']) . ' ' . basename($idCardResult) . "\n");
