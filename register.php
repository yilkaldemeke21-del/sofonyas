<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_config.php';

$errors = [];
$csrfToken = csrfToken();
$recaptchaSiteKey = getenv('RECAPTCHA_SITE_KEY') ?: '';
$recaptchaSecret = getenv('RECAPTCHA_SECRET_KEY') ?: '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'ደኅንነት ተሰርዟል። እባክዎ ገጹን እንደገና ይጫኑ።';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $studentId = trim($_POST['student_id'] ?? '');
        $password = $_POST['password'] ?? '';
        $course = trim($_POST['course'] ?? 'ነገረ ሃይማኖት');
        $amount = trim($_POST['amount'] ?? '0');
        $captchaResponse = $_POST['g-recaptcha-response'] ?? '';

        if ($name === '') {
            $errors[] = 'ስም ያስገቡ።';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'እባክዎ ትክክለኛ ኢሜይል ያስገቡ።';
        }
        if ($studentId === '') {
            $errors[] = 'የተማሪ መለያ ቁጥር ያስገቡ።';
        }
        if ($password === '') {
            $errors[] = 'የይለፍ ቃል ያስገቡ።';
        }
        if (!is_numeric($amount) || (float)$amount < 0) {
            $errors[] = 'እባክዎ ትክክለኛ የክፍያ መጠን ያስገቡ።';
        }
        if ($recaptchaSiteKey !== '' && $recaptchaSecret !== '' && !verifyCaptchaResponse($captchaResponse, $recaptchaSecret)) {
            $errors[] = 'የማንኛውም ደህንነት ምልክት አልተሳካም። እባክዎ ዳግም ይሞክሩ።';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT id FROM students WHERE email = :email OR student_id = :student_id');
            $stmt->execute([':email' => $email, ':student_id' => $studentId]);
            if ($stmt->fetch()) {
                $errors[] = 'ይህ ኢሜይል ወይም የተማሪ መለያ ቁጥር አስቀድሞ ተመዝግቧል።';
            }
        }

        if (empty($errors)) {
            try {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare('INSERT INTO students (name, email, student_id, password_hash) VALUES (:name, :email, :student_id, :password_hash)');
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':student_id' => $studentId,
                    ':password_hash' => $passwordHash,
                ]);

                $recordId = uniqid('reg_', true);
                $stmt = $pdo->prepare(
                    'INSERT INTO registrations (`id`, `name`, `email`, `student_id`, `course`, `amount`, `payment_status`, `created_at`) '
                    . 'VALUES (:id, :name, :email, :student_id, :course, :amount, :payment_status, :created_at)'
                );
                $stmt->execute([
                    ':id' => $recordId,
                    ':name' => $name,
                    ':email' => $email,
                    ':student_id' => $studentId,
                    ':course' => $course,
                    ':amount' => $amount,
                    ':payment_status' => 'unpaid',
                    ':created_at' => date('Y-m-d H:i:s'),
                ]);

                $verificationToken = bin2hex(random_bytes(32));
                $verificationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $stmt = $pdo->prepare('INSERT INTO email_verification_tokens (email, token, expires_at, used, created_at) VALUES (:email, :token, :expires_at, 0, NOW())');
                $stmt->execute([
                    ':email' => $email,
                    ':token' => $verificationToken,
                    ':expires_at' => $verificationExpires,
                ]);

                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $verifyUrl = $scheme . '://' . $host . '/verify_email.php?token=' . urlencode($verificationToken);

                $welcomeSubject = 'Registration successful';
                $welcomeMessage = '<p>Dear ' . safe($name) . ',</p>'
                    . '<p>Your registration has been received successfully.</p>'
                    . '<p><strong>Student ID:</strong> ' . safe($studentId) . '<br>'
                    . '<strong>Course:</strong> ' . safe($course) . '<br>'
                    . '<strong>Amount:</strong> ' . safe($amount) . '</p>'
                    . '<p>Please verify your email here: <a href="' . safe($verifyUrl) . '">Verify Email</a></p>'
                    . '<p>Thank you for choosing Sofnyas.</p>';
                $mailSent = sendAppEmail($email, $welcomeSubject, $welcomeMessage);
                if (!$mailSent) {
                    error_log('Registration email could not be sent for ' . $email);
                }

                session_regenerate_id(true);
                $_SESSION['student_id'] = $studentId;
                $_SESSION['student_email'] = $email;
                $_SESSION['student_name'] = $name;

                header('Location: verify_email.php?token=' . urlencode($verificationToken));
                exit;
            } catch (PDOException $e) {
                $errors[] = 'ምዝገባው አልተሳካም። እባክዎ እንደገና ይሞክሩ።';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>የምዝገባ ገጽ</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; background: #f7f7f7; }
        .message { margin: 20px 0; padding: 15px; border-radius: 6px; }
        .error { background: #ffe7e7; border: 1px solid #ffb7b7; color: #a00000; }
        .success { background: #e8f8e8; border: 1px solid #8bd18b; color: #1c6620; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); max-width: 680px; margin: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        a.button { display: inline-block; padding: 10px 18px; background: #4b62d9; color: white; border-radius: 5px; text-decoration: none; }
    </style>
</head>
<body>
<div class="card">
    <h1>የምዝገባ ገጽ</h1>
    <?php if (!empty($errors)): ?>
        <div class="message error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo safe($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo safe($csrfToken); ?>">

        <p>
            <label for="name">ስም</label>
            <input id="name" name="name" value="<?php echo safe($name); ?>" required>
        </p>

        <p>
            <label for="email">ኢሜይል</label>
            <input id="email" type="email" name="email" value="<?php echo safe($email); ?>" required>
        </p>

        <p>
            <label for="student_id">የተማሪ መለያ</label>
            <input id="student_id" name="student_id" value="<?php echo safe($studentId); ?>" required>
        </p>

        <p>
            <label for="password">የይለፍ ቃል</label>
            <input id="password" type="password" name="password" required>
        </p>

        <p>
            <label for="course">ኮርስ</label>
            <input id="course" name="course" value="<?php echo safe($course); ?>" required>
        </p>

        <p>
            <label for="amount">ክፍያ መጠን</label>
            <input id="amount" type="number" step="0.01" name="amount" value="<?php echo safe($amount); ?>" required>
        </p>

        <?php if ($recaptchaSiteKey !== ''): ?>
            <div style="margin: 16px 0;">
                <script src="https://www.google.com/recaptcha/api.js" async defer></script>
                <div class="g-recaptcha" data-sitekey="<?php echo safe($recaptchaSiteKey); ?>"></div>
            </div>
        <?php endif; ?>

        <button class="button" type="submit">መመዝገብ ጨርስ</button>
    </form>

    <p style="margin-top: 16px;">
        <a class="button" href="dashboard.php">ዳሽቦርድ እይ</a>
    </p>
</div>
</body>
</html>
