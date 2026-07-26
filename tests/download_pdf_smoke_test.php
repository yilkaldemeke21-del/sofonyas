<?php
ob_start();
session_start();
$_SESSION['admin_id'] = 1;
$_SESSION['student_id'] = 'TEST-001';
$_GET = [];
require_once __DIR__ . '/../admin_certificate.php';
require_once __DIR__ . '/../student_id_card.php';
ob_end_clean();

$certificate = [
    'id' => 1,
    'student_name' => 'Test Student',
    'exam_type' => 'Final Exam',
    'score' => 88,
    'total_questions' => 100,
    'issued_at' => '2025-01-01 00:00:00',
    'signer_name' => 'Authorized Signatory',
];

$certResult = generate_certificate_pdf_file($certificate);
$studentResult = generateIdCardPdfFile(
    ['name' => 'Test Student', 'student_id' => 'TEST-001', 'email' => 'student@example.com', 'country' => 'Ethiopia', 'city' => 'Addis Ababa'],
    [['course' => 'Intro Course', 'payment_status' => 'paid']],
    'SFC-TEST001',
    'https://example.com/verify'
);

if ($certResult === false || !isset($certResult['path']) || !is_file($certResult['path'])) {
    fwrite(STDERR, "CERT_FAIL\n");
    exit(1);
}

if ($studentResult === false || !is_file($studentResult)) {
    fwrite(STDERR, "IDCARD_FAIL\n");
    exit(1);
}

$certBytes = file_get_contents($certResult['path']);
$idBytes = file_get_contents($studentResult);
if (strpos($certBytes, '%PDF') !== 0 || strpos($idBytes, '%PDF') !== 0) {
    fwrite(STDERR, "BAD_PDF\n");
    exit(1);
}

fwrite(STDOUT, "PDF_OK\n");
