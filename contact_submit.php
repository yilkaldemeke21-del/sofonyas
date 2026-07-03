<?php
header('Content-Type: application/json; charset=utf-8');

$uploadDir = __DIR__ . '/uploads/contacts/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$savedFile = '';

if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['attachment'];
    $base = basename($file['name']);
    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $base);
    $target = $uploadDir . time() . '_' . $safe;
    if (move_uploaded_file($file['tmp_name'], $target)) {
        $savedFile = basename($target);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file.']);
        exit;
    }
}

$logEntry = "Name: $name\nEmail: $email\nSubject: $subject\nMessage: $message\nAttachment: $savedFile\n";
$logfile = __DIR__ . '/uploads/contact_submissions.txt';
file_put_contents($logfile, date('c') . "\n" . $logEntry . "\n---\n", FILE_APPEND);

echo json_encode(['success' => true, 'message' => 'Submission received. Thank you!', 'file' => $savedFile]);

?>