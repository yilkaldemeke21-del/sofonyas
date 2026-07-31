<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_config.php';

function wantsHtmlResponse(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return stripos($accept, 'application/json') === false && strtolower($requestedWith) !== 'xmlhttprequest';
}

function sendJsonResponse(array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function sendHtmlResponse(bool $success, string $message): void
{
    header('Content-Type: text/html; charset=utf-8');
    $status = $success ? 'Success' : 'Error';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<style>body{font-family:Arial,sans-serif;background:#f7f8fb;color:#111;margin:0;padding:24px} .card{max-width:600px;margin:40px auto;padding:24px;background:#fff;border-radius:14px;box-shadow:0 16px 40px rgba(0,0,0,0.08);} button{padding:10px 14px;border:0;border-radius:8px;background:#2563eb;color:#fff;cursor:pointer;} .message{margin-bottom:18px;}</style>';
    echo '</head><body><div class="card"><h1>' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</h1><p class="message">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><a href="sofonyas2.php"><button>Go back</button></a></div></body></html>';
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $message = 'Invalid request method. Please submit the contact form from the website.';
        if (isset($_SERVER['HTTP_REFERER']) && strtolower($_SERVER['REQUEST_METHOD']) === 'get') {
            header('Location: ' . strtok($_SERVER['HTTP_REFERER'], '#'));
            exit;
        }
        http_response_code(405);
        if (wantsHtmlResponse()) {
            sendHtmlResponse(false, $message);
        }
        sendJsonResponse(['success' => false, 'message' => $message], 405);
    }

    $uploadDir = __DIR__ . '/uploads/contacts/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to create upload directory.');
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    $savedFile = null;

    if ($name === '') {
        throw new RuntimeException('Please enter your name.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Please enter a valid email address.');
    }
    if ($subject === '') {
        throw new RuntimeException('Please enter a subject.');
    }
    if ($message === '') {
        throw new RuntimeException('Please enter a message.');
    }

    if (!empty($_FILES['attachment']) && is_array($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $base = basename((string)($file['name'] ?? ''));
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $base);
        $target = $uploadDir . time() . '_' . $safe;
        if (!move_uploaded_file((string)($file['tmp_name'] ?? ''), $target)) {
            throw new RuntimeException('Failed to save uploaded file.');
        }
        $savedFile = basename($target);
    }

    $stmt = $pdo->prepare('INSERT INTO contact_messages (name, email, phone, subject, message, status, created_at) VALUES (:name, :email, :phone, :subject, :message, "new", NOW())');
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':phone' => '',
        ':subject' => $subject,
        ':message' => $message,
    ]);

    $adminStmt = $pdo->query('SELECT email FROM admin_users WHERE email IS NOT NULL AND email <> ""');
    foreach ($adminStmt->fetchAll() as $admin) {
        $adminEmail = $admin['email'] ?? '';
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        sendAppEmail(
            $adminEmail,
            'New contact message: ' . $subject,
            '<p><strong>Name:</strong> ' . safe($name) . '</p>' .
            '<p><strong>Email:</strong> ' . safe($email) . '</p>' .
            '<p><strong>Subject:</strong> ' . safe($subject) . '</p>' .
            '<p><strong>Message:</strong><br>' . nl2br(safe($message)) . '</p>' .
            ($savedFile !== null ? '<p><strong>Attachment:</strong> ' . safe($savedFile) . '</p>' : '')
        );
    }

    sendAppEmail(
        $email,
        'We received your message',
        '<p>Thank you for contacting Sofnyas.</p><p>We have received your message and will get back to you soon.</p>'
    );

    $successMessage = 'Your message has been sent successfully. Thank you for reaching out.';
    if (wantsHtmlResponse()) {
        sendHtmlResponse(true, $successMessage);
    }
    sendJsonResponse(['success' => true, 'message' => $successMessage, 'file' => $savedFile]);
} catch (Throwable $e) {
    error_log('Contact submission failed: ' . $e->getMessage());
    $message = $e->getMessage() ?: 'Unable to submit your message. Please try again later.';
    if (wantsHtmlResponse()) {
        sendHtmlResponse(false, $message);
    }
    sendJsonResponse(['success' => false, 'message' => $message]);
}
