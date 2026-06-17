<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_config.php';

$errors = [];
$success = '';
$csrfToken = csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token failed. Please refresh the page and try again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email.';
        } else {
            $stmt = $pdo->prepare('SELECT id, name FROM students WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $student = $stmt->fetch();

            if ($student) {
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $stmt = $pdo->prepare('INSERT INTO password_reset_tokens (email, token, expires_at, used, created_at) VALUES (:email, :token, :expires_at, 0, NOW())');
                $stmt->execute([
                    ':email' => $email,
                    ':token' => $token,
                    ':expires_at' => $expiresAt,
                ]);

                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $resetUrl = $scheme . '://' . $host . '/reset_password.php?token=' . urlencode($token);
                sendAppEmail(
                    $email,
                    'Reset your password',
                    '<p>Hello ' . safe($student['name']) . ',</p>'
                    . '<p>You requested a password reset.</p>'
                    . '<p><a href="' . safe($resetUrl) . '">Click here to reset your password</a></p>'
                    . '<p>If you did not request this, you can ignore this email.</p>'
                );
            }

            $success = 'If an account exists for that email, a reset link has been sent.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f7f8fb; margin: 0; padding: 32px; }
        .card { max-width: 500px; margin: auto; background: #fff; border-radius: 14px; padding: 24px; box-shadow: 0 12px 35px rgba(0,0,0,0.08); }
        input { width: 100%; padding: 10px; margin-top: 6px; margin-bottom: 14px; border: 1px solid #cbd5e1; border-radius: 8px; }
        button { background: #2563eb; color: #fff; border: none; padding: 12px 16px; border-radius: 8px; cursor: pointer; font-weight: 700; }
        .error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 14px; }
        .success { background: #e0fce9; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 14px; }
    </style>
</head>
<body>
<div class="card">
    <h1>Forgot Password</h1>
    <?php if (!empty($errors)): ?><div class="error"><ul><?php foreach ($errors as $error) { echo '<li>' . safe($error) . '</li>'; } ?></ul></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="success"><?php echo safe($success); ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo safe($csrfToken); ?>">
        <label>Email</label>
        <input type="email" name="email" required>
        <button type="submit">Send Reset Link</button>
    </form>
</div>
</body>
</html>
