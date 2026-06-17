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
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($name === '') {
            $errors[] = 'Please enter your name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email.';
        }
        if ($subject === '') {
            $errors[] = 'Please enter a subject.';
        }
        if ($message === '') {
            $errors[] = 'Please enter your message.';
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare('INSERT INTO contact_messages (name, email, phone, subject, message, status, created_at) VALUES (:name, :email, :phone, :subject, :message, "new", NOW())');
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':subject' => $subject,
                    ':message' => $message,
                ]);

                $adminStmt = $pdo->query('SELECT id, email FROM admin_users WHERE email IS NOT NULL AND email <> ""');
                foreach ($adminStmt->fetchAll() as $admin) {
                    sendAppEmail(
                        $admin['email'],
                        'New contact message: ' . $subject,
                        '<p><strong>Name:</strong> ' . safe($name) . '</p>'
                        . '<p><strong>Email:</strong> ' . safe($email) . '</p>'
                        . '<p><strong>Phone:</strong> ' . safe($phone) . '</p>'
                        . '<p><strong>Subject:</strong> ' . safe($subject) . '</p>'
                        . '<p><strong>Message:</strong><br>' . nl2br(safe($message)) . '</p>'
                    );
                }

                sendAppEmail(
                    $email,
                    'We received your message',
                    '<p>Thank you for contacting Sofnyas.</p><p>We have received your message and will get back to you soon.</p>'
                );

                $success = 'Your message has been sent successfully.';
            } catch (PDOException $e) {
                error_log('Contact form save failed: ' . $e->getMessage());
                $errors[] = 'Unable to save your message right now.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f7f8fb; margin: 0; padding: 32px; }
        .card { max-width: 700px; margin: auto; background: #fff; border-radius: 14px; padding: 24px; box-shadow: 0 12px 35px rgba(0,0,0,0.08); }
        h1 { margin-top: 0; }
        input, textarea { width: 100%; padding: 10px; margin-top: 6px; margin-bottom: 14px; border: 1px solid #cbd5e1; border-radius: 8px; }
        button { background: #2563eb; color: #fff; border: none; padding: 12px 16px; border-radius: 8px; cursor: pointer; font-weight: 700; }
        .error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 14px; }
        .success { background: #e0fce9; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 14px; }
    </style>
</head>
<body>
<div class="card">
    <h1>Contact Us</h1>
    <?php if (!empty($errors)): ?>
        <div class="error"><ul><?php foreach ($errors as $error) { echo '<li>' . safe($error) . '</li>'; } ?></ul></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="success"><?php echo safe($success); ?></div>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo safe($csrfToken); ?>">
        <label>Name</label>
        <input type="text" name="name" required>
        <label>Email</label>
        <input type="email" name="email" required>
        <label>Phone (optional)</label>
        <input type="text" name="phone">
        <label>Subject</label>
        <input type="text" name="subject" required>
        <label>Message</label>
        <textarea name="message" rows="6" required></textarea>
        <button type="submit">Send Message</button>
    </form>
</div>
</body>
</html>
