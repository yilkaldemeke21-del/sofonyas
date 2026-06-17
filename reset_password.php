<?php
session_start();
require_once __DIR__ . '/db.php';

$errors = [];
$success = '';
$csrfToken = csrfToken();
$token = trim($_GET['token'] ?? '');
$resetRecord = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['reset_token'] ?? $_POST['token_input'] ?? $token);
}

if ($token === '') {
    $errors[] = 'Missing reset token. Please paste the token from your email or use the link from the reset message.';
} else {
    $stmt = $pdo->prepare('SELECT email, expires_at, used FROM password_reset_tokens WHERE token = :token LIMIT 1');
    $stmt->execute([':token' => $token]);
    $resetRecord = $stmt->fetch();

    if (!$resetRecord || (int)$resetRecord['used'] === 1 || strtotime($resetRecord['expires_at']) < time()) {
        $errors[] = 'This reset link is invalid or expired.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token failed. Please refresh the page and try again.';
    } else {
        $newPassword = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($newPassword) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        } elseif ($newPassword !== $confirm) {
            $errors[] = 'Passwords do not match.';
        } else {
            $email = $resetRecord['email'];
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare('UPDATE students SET password_hash = :password_hash WHERE email = :email');
            $stmt->execute([
                ':password_hash' => $passwordHash,
                ':email' => $email,
            ]);

            $stmt = $pdo->prepare('UPDATE password_reset_tokens SET used = 1 WHERE token = :token');
            $stmt->execute([':token' => $token]);

            $success = 'Password updated successfully.';

            header('Location: student_login.php?reset=success');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
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
    <h1>Reset Password</h1>
    <?php if (!empty($errors)): ?><div class="error"><ul><?php foreach ($errors as $error) { echo '<li>' . safe($error) . '</li>'; } ?></ul></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="success"><?php echo safe($success); ?></div><?php endif; ?>
    <?php if ($token === '' && $success === ''): ?>
    <form method="post">
        <label>Reset token</label>
        <input type="text" name="token_input" placeholder="Paste the token from your email" required>
        <button type="submit">Continue</button>
    </form>
    <?php elseif (empty($errors) && $success === ''): ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo safe($csrfToken); ?>">
        <input type="hidden" name="reset_token" value="<?php echo safe($token); ?>">
        <label>New Password</label>
        <input type="password" name="new_password" required>
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" required>
        <button type="submit">Update Password</button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
