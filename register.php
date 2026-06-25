<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_config.php';

$errors = [];
$csrfToken = csrfToken();
$recaptchaSiteKey = getenv('RECAPTCHA_SITE_KEY') ?: '';
$recaptchaSecret = getenv('RECAPTCHA_SECRET_KEY') ?: '';
$name = $email = $studentId = $course = $amount = '';

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

                $verifyUrl = buildAppUrl('verify_email.php?token=' . urlencode($verificationToken));

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

                header('Location: ' . buildAppUrl('verify_email.php?token=' . urlencode($verificationToken)));
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
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Noto Sans Ethiopic', Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 24px;
            background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 45%, #eef2ff 100%);
            color: #0f172a;
        }
        .card {
            background: #fff;
            padding: 28px;
            border-radius: 18px;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.12);
            max-width: 720px;
            margin: 20px auto;
            border: 1px solid #e2e8f0;
        }
        .card h1 {
            margin-top: 0;
            margin-bottom: 8px;
            font-size: 1.9rem;
            color: #1d4ed8;
        }
        .subtitle {
            color: #475569;
            margin-bottom: 20px;
        }
        .message {
            margin: 16px 0 20px;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: 600;
        }
        .error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
        .success { background: #ecfdf3; border: 1px solid #a7f3d0; color: #166534; }
        form { display: grid; gap: 14px; }
        .field { position: relative; }
        .field input {
            width: 100%;
            padding: 14px 14px 14px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            font-size: 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
            background: #fff;
        }
        .field input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
            transform: translateY(-1px);
        }
        .field label {
            position: absolute;
            left: 14px;
            top: 14px;
            background: #fff;
            padding: 0 6px;
            color: #64748b;
            transition: all 0.2s ease;
            pointer-events: none;
        }
        .field input:focus + label,
        .field input:not(:placeholder-shown) + label,
        .field input.has-value + label {
            top: -8px;
            font-size: 0.8rem;
            color: #2563eb;
        }
        .field small {
            display: block;
            margin-top: 6px;
            color: #64748b;
        }
        .password-wrap { position: relative; }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #2563eb;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .strength-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
            margin-top: 8px;
        }
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: width 0.2s ease, background 0.2s ease;
            background: #ef4444;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 8px;
        }
        .button {
            display: inline-block;
            padding: 12px 18px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            border: none;
            border-radius: 999px;
            text-decoration: none;
            cursor: pointer;
            font-weight: 700;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.22);
        }
        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(37, 99, 235, 0.28);
        }
        .button.secondary {
            background: #111827;
            box-shadow: none;
        }
        .button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        .hint { color: #64748b; font-size: 0.9rem; }
        @media (max-width: 640px) {
            body { padding: 14px; }
            .card { padding: 20px; }
        }
    </style>
</head>
<body>
<div class="card">
    <h1>የምዝገባ ገጽ</h1>
    <p class="subtitle">እባክዎ መረጃዎን በትክክል ያስገቡ፣ በተጨማሪ የተሻሻለ እና የተግባር የሆነ ቅጽ ነው።</p>
    <?php if (!empty($errors)): ?>
        <div class="message error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo safe($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" id="registerForm">
        <input type="hidden" name="csrf_token" value="<?php echo safe($csrfToken); ?>">

        <div class="field">
            <input id="name" name="name" value="<?php echo safe($name); ?>" required placeholder=" ">
            <label for="name">ስም</label>
            <small>እባክዎ ሙሉ ስምዎን ያስገቡ</small>
        </div>

        <div class="field">
            <input id="email" type="email" name="email" value="<?php echo safe($email); ?>" required placeholder=" ">
            <label for="email">ኢሜይል</label>
            <small>ለማረጋገጥ ትክክለኛ ኢሜይል ይጠቀሙ</small>
        </div>

        <div class="field">
            <input id="student_id" name="student_id" value="<?php echo safe($studentId); ?>" required placeholder=" ">
            <label for="student_id">የተማሪ መለያ</label>
            <small>የተማሪዎ ልዩ መለያ ቁጥር</small>
        </div>

        <div class="field password-wrap">
            <input id="password" type="password" name="password" required placeholder=" ">
            <label for="password">የይለፍ ቃል</label>
            <button class="password-toggle" type="button" id="togglePassword">Show</button>
            <small>ቢያንስ 8 ቁምፊዎች በጣም ይመከራል</small>
            <div class="strength-bar" aria-hidden="true"><div class="strength-fill" id="strengthFill"></div></div>
            <div class="hint" id="strengthText">Password strength: weak</div>
        </div>

        <div class="field">
            <input id="course" name="course" value="<?php echo safe($course); ?>" required placeholder=" ">
            <label for="course">ኮርስ</label>
            <small>ለምዝገባ የሚመርጡት ኮርስ</small>
        </div>

        <div class="field">
            <input id="amount" type="number" step="0.01" name="amount" value="<?php echo safe($amount); ?>" required placeholder=" ">
            <label for="amount">ክፍያ መጠን</label>
            <small>የክፍያ መጠኑን ያስገቡ</small>
        </div>

        <?php if ($recaptchaSiteKey !== ''): ?>
            <div style="margin: 10px 0 6px;">
                <script src="https://www.google.com/recaptcha/api.js" async defer></script>
                <div class="g-recaptcha" data-sitekey="<?php echo safe($recaptchaSiteKey); ?>"></div>
            </div>
        <?php endif; ?>

        <div class="actions">
            <button class="button" type="submit" id="submitBtn">መመዝገብ ጨርስ</button>
            <a class="button secondary" href="dashboard.php">ዳሽቦርድ እይ</a>
        </div>
    </form>
</div>
<script>
    const form = document.getElementById('registerForm');
    const submitBtn = document.getElementById('submitBtn');
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');
    const strengthFill = document.getElementById('strengthFill');
    const strengthText = document.getElementById('strengthText');

    document.querySelectorAll('.field input').forEach((input) => {
        const update = () => input.classList.toggle('has-value', input.value.trim() !== '');
        update();
        input.addEventListener('input', update);
        input.addEventListener('focus', () => input.classList.add('is-focused'));
        input.addEventListener('blur', () => input.classList.remove('is-focused'));
    });

    function updateStrength(value) {
        let score = 0;
        if (value.length >= 8) score += 1;
        if (/[A-Z]/.test(value)) score += 1;
        if (/[0-9]/.test(value)) score += 1;
        if (/[^A-Za-z0-9]/.test(value)) score += 1;

        const levels = [
            { width: '20%', color: '#ef4444', text: 'Password strength: weak' },
            { width: '45%', color: '#f59e0b', text: 'Password strength: fair' },
            { width: '75%', color: '#3b82f6', text: 'Password strength: good' },
            { width: '100%', color: '#16a34a', text: 'Password strength: strong' }
        ];
        const level = levels[Math.min(score, 3)];
        strengthFill.style.width = level.width;
        strengthFill.style.background = level.color;
        strengthText.textContent = level.text;
    }

    passwordInput.addEventListener('input', () => updateStrength(passwordInput.value));
    updateStrength(passwordInput.value || '');

    togglePassword.addEventListener('click', () => {
        const isHidden = passwordInput.type === 'password';
        passwordInput.type = isHidden ? 'text' : 'password';
        togglePassword.textContent = isHidden ? 'Hide' : 'Show';
    });

    form.addEventListener('submit', () => {
        submitBtn.disabled = true;
        submitBtn.textContent = 'የሚመዘገብ ነው...';
    });
</script>
</body>
</html>
