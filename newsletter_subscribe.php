<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_config.php';

function wantsHtmlResponse(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return stripos($accept, 'application/json') === false && strtolower($requestedWith) !== 'xmlhttprequest';
}

function sendJsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
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

function sanitizeInput(string $value): string
{
    return trim($value);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $message = 'Invalid request method. Please submit the newsletter form from the website.';
        if (isset($_SERVER['HTTP_REFERER']) && strtolower($_SERVER['REQUEST_METHOD']) === 'get') {
            header('Location: ' . strtok($_SERVER['HTTP_REFERER'], '#'));
            exit;
        }
        if (wantsHtmlResponse()) {
            sendHtmlResponse(false, $message);
        }
        sendJsonResponse(['success' => false, 'message' => $message], 405);
    }

    $name = sanitizeInput($_POST['name'] ?? '');
    $email = strtolower(sanitizeInput($_POST['email'] ?? ''));
    $source = sanitizeInput($_POST['source'] ?? 'homepage');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Please provide a valid email address.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO newsletter_subscribers (name, email, source, status, created_at, updated_at)
         VALUES (:name, :email, :source, "active", NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            source = VALUES(source),
            status = "active",
            updated_at = NOW()'
    );
    $stmt->execute([
        ':name' => $name !== '' ? $name : null,
        ':email' => $email,
        ':source' => $source !== '' ? $source : 'homepage',
    ]);

    $adminStmt = $pdo->query('SELECT email FROM admin_users WHERE email IS NOT NULL AND email <> ""');
    foreach ($adminStmt->fetchAll() as $admin) {
        $adminEmail = $admin['email'] ?? '';
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        sendAppEmail(
            $adminEmail,
            'New newsletter subscriber',
            '<p>A new subscriber joined the newsletter.</p>' .
            '<p><strong>Name:</strong> ' . safe($name) . '</p>' .
            '<p><strong>Email:</strong> ' . safe($email) . '</p>' .
            '<p><strong>Source:</strong> ' . safe($source) . '</p>'
        );
    }

    sendAppEmail(
        $email,
        'Welcome to Sofnyas newsletter',
        '<p>Thank you for subscribing to our newsletter. You will receive course updates and exclusive alerts from Sofnyas.</p>'
    );

    $successMessage = 'Thank you! You have been subscribed to our newsletter.';
    if (wantsHtmlResponse()) {
        sendHtmlResponse(true, $successMessage);
    }
    sendJsonResponse(['success' => true, 'message' => $successMessage]);
} catch (Throwable $e) {
    error_log('Newsletter signup failed: ' . $e->getMessage());
    $message = $e->getMessage() ?: 'Unable to subscribe at this time. Please try again later.';
    if (wantsHtmlResponse()) {
        sendHtmlResponse(false, $message);
    }
    sendJsonResponse(['success' => false, 'message' => $message], 400);
}
