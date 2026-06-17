<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_config.php';

$errors = [];
$success = '';
$token = trim($_GET['token'] ?? '');
$emailToResend = trim($_GET['email'] ?? '');

if ($token === '') {
    if ($emailToResend !== '' && filter_var($emailToResend, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'The verification link was missing. You can request a new one below.';
    } else {
        $errors[] = 'Missing verification token. Please open the verification link from your email, or request a new one.';
    }
} else {
    $stmt = $pdo->prepare('SELECT email, expires_at, used FROM email_verification_tokens WHERE token = :token LIMIT 1');
    $stmt->execute([':token' => $token]);
    $record = $stmt->fetch();

    if (!$record || (int)$record['used'] === 1 || strtotime($record['expires_at']) < time()) {
        $errors[] = 'This verification link is invalid or expired. Please request a new one.';
    } else {
        $stmt = $pdo->prepare('UPDATE students SET email_verified = 1, email_verified_at = NOW() WHERE email = :email');
        $stmt->execute([':email' => $record['email']]);

        $stmt = $pdo->prepare('UPDATE email_verification_tokens SET used = 1 WHERE token = :token');
        $stmt->execute([':token' => $token]);

        $success = 'Your email has been verified successfully.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['resend_email'])) {
    $emailToResend = strtolower(trim($_POST['resend_email'] ?? ''));
    if (!filter_var($emailToResend, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare('SELECT name, student_id FROM students WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $emailToResend]);
        $student = $stmt->fetch();
        if ($student) {
            $verificationToken = bin2hex(random_bytes(32));
            $verificationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $stmt = $pdo->prepare('INSERT INTO email_verification_tokens (email, token, expires_at, used, created_at) VALUES (:email, :token, :expires_at, 0, NOW())');
            $stmt->execute([
                ':email' => $emailToResend,
                ':token' => $verificationToken,
                ':expires_at' => $verificationExpires,
            ]);

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $verifyUrl = $scheme . '://' . $host . '/verify_email.php?token=' . urlencode($verificationToken);

            sendAppEmail(
                $emailToResend,
                'Verify your email',
                '<p>Hello ' . safe($student['name']) . ',</p>'
                . '<p>Please verify your email address by clicking the link below.</p>'
                . '<p><a href="' . safe($verifyUrl) . '">Verify Email</a></p>'
            );
            $success = 'A new verification email has been sent.';
        } else {
            $errors[] = 'No account was found for that email.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f7f8fb; margin: 0; padding: 32px; }
        .card { max-width: 560px; margin: auto; background: #fff; border-radius: 14px; padding: 24px; box-shadow: 0 12px 35px rgba(0,0,0,0.08); }
        .error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 14px; }
        .success { background: #e0fce9; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 14px; }
        input { width: 100%; padding: 10px; margin-top: 6px; margin-bottom: 14px; border: 1px solid #cbd5e1; border-radius: 8px; }
        button { background: #2563eb; color: #fff; border: none; padding: 12px 16px; border-radius: 8px; cursor: pointer; font-weight: 700; }
    </style>
</head>
<body>
<div class="card">
    <h1>Email Verification</h1>
    <?php if (!empty($errors)): ?><div class="error"><ul><?php foreach ($errors as $error) { echo '<li>' . safe($error) . '</li>'; } ?></ul></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="success"><?php echo safe($success); ?></div><?php endif; ?>
    <?php if (!empty($errors) || $success === ''): ?>
    <form method="post">
        <label for="resend_email">Email address</label>
        <input type="email" id="resend_email" name="resend_email" value="<?php echo safe($emailToResend); ?>" placeholder="Enter your email" required>
        <button type="submit">Resend verification email</button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
