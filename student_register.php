<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_config.php';

$errors = [];
$csrfToken = csrfToken();
$recaptchaSiteKey = getenv('RECAPTCHA_SITE_KEY') ?: '';
$recaptchaSecret = getenv('RECAPTCHA_SECRET_KEY') ?: '';
$name = '';
$email = '';
$studentId = '';
$confirmPassword = '';
$course = trim($_GET['course'] ?? '');
$amount = trim($_GET['amount'] ?? '0');

if ($course === '' && isset($_GET['course'])) {
    $course = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'ደህንነት ተሰርዟል። እባክዎ ገጹን እንደገና ይጫኑ።';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $studentId = trim($_POST['student_id'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $course = trim($_POST['course'] ?? '');
        $amount = trim($_POST['amount'] ?? '0');
        $captchaResponse = $_POST['g-recaptcha-response'] ?? '';

        if ($name === '') {
            $errors[] = 'ስም ያስገቡ።';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'እባክዎ ትክክለኛ ኢሜይል ያስገቡ።';
        }
        if ($password === '') {
            $errors[] = 'የይለፍ ቃል ያስገቡ።';
        } elseif (strlen($password) < 6) {
            $errors[] = 'የይለፍ ቃል ብዛት ቢያንስ 6 መሆን አለበት።';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'የይለፍ ቃላት አይመሳሰሉም።';
        }
        if ($course === '') {
            $errors[] = 'ኮርሱን ያስገቡ።';
        }
        if (!is_numeric($amount) || $amount < 0) {
            $errors[] = 'እባክዎ ትክክለኛ የክፍያ መጠን ያስገቡ።';
        }
        if ($recaptchaSiteKey !== '' && $recaptchaSecret !== '' && !verifyCaptchaResponse($captchaResponse, $recaptchaSecret)) {
            $errors[] = 'የማንኛውም ደህንነት ምልክት አልተሳካም። እባክዎ ዳግም ይሞክሩ።';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT id FROM students WHERE email =:email OR student_id = :student_id');
            $stmt->execute([':email' => $email, ':student_id' => $studentId]);
            if ($stmt->fetch()) {
                $errors[] = 'እባክዎ ያስገቡት ኢሜይል ወይም የተማሪ መለያ ቁጥር አስምር ነው።';
            }
        }

        if (empty($errors)) {
            if ($studentId === '') {
                $base = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', str_replace([' ', '@', '.'], '', $email)), 0, 6));
                $base = $base !== '' ? $base : 'STU';

                do {
                    $studentId = $base . str_pad((string) random_int(100, 999), 3, '0', STR_PAD_LEFT);
                    $check = $pdo->prepare('SELECT id FROM students WHERE student_id = :student_id LIMIT 1');
                    $check->execute([':student_id' => $studentId]);
                } while ($check->fetch());
            }

            try {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare('INSERT INTO students (name, email, student_id, password_hash) VALUES (:name, :email, :student_id, :password_hash)');
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':student_id' => $studentId,
                    ':password_hash' => $passwordHash,
                ]);

                $registrationId = uniqid('reg_', true);
                $stmt = $pdo->prepare('INSERT INTO registrations (`id`, `name`, `email`, `student_id`, `course`, `amount`, `payment_status`, `created_at`) VALUES (:id, :name, :email, :student_id, :course, :amount, :payment_status, :created_at)');
                $stmt->execute([
                    ':id' => $registrationId,
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

                $welcomeSubject = 'Welcome to Sofnyas';
                $welcomeMessage = '<p>Dear ' . safe($name) . ',</p>'
                    . '<p>Thank you for registering with Sofnyas.</p>'
                    . '<p><strong>Student ID:</strong> ' . safe($studentId) . '<br>'
                    . '<strong>Course:</strong> ' . safe($course) . '<br>'
                    . '<strong>Amount:</strong> ' . safe($amount) . '</p>'
                    . '<p>Please verify your email here: <a href="' . safe($verifyUrl) . '">Verify Email</a></p>'
                    . '<p>You can now log in and access your dashboard.</p>';
                sendAppEmail($email, $welcomeSubject, $welcomeMessage);

                session_regenerate_id(true);
                $_SESSION['student_id'] = $studentId;
                $_SESSION['student_email'] = $email;
                $_SESSION['student_name'] = $name;

                header('Location: student_dashboard.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'ተማሪውን መመዝገብ አልተሳካም። ኢሜይል ወይም የተማሪ መለያ ቁጥር አስምር ሊሆን ይችላል።';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>የተማሪ ምዝገባ</title>
    <style>
        :root { --brand: #2563eb; --brand-2: #7c3aed; --muted: #475569; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background:
            radial-gradient(circle at top, rgba(191,219,254,0.35), transparent 18%),
            linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%); margin: 0; padding: 0; }
        .wrapper { max-width: 700px; margin: 40px auto; padding: 24px; background: rgba(255,255,255,0.96); border: 1px solid rgba(148,163,184,0.2); border-radius: 20px; box-shadow: 0 18px 40px rgba(15,23,42,0.12); backdrop-filter: blur(6px); }
        .hero { background: linear-gradient(135deg, var(--brand) 0%, var(--brand-2) 100%); color: #fff; border-radius: 16px; padding: 18px; margin-bottom: 18px; }
        .hero h1 { margin: 0 0 6px; color: #fff; font-size: 22px; }
        .hero p { margin: 0; color: #e0e7ff; font-size: 14px; line-height: 1.5; }
        .chip { display: inline-flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 999px; background: rgba(255,255,255,0.16); border: 1px solid rgba(255,255,255,0.2); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 10px; }
        .chip span { width: 8px; height: 8px; border-radius: 50%; background: #4ade80; box-shadow: 0 0 0 0 rgba(74,222,128,0.35); animation: pulse 1.8s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(74,222,128,0.35); } 70% { box-shadow: 0 0 0 10px rgba(74,222,128,0); } 100% { box-shadow: 0 0 0 0 rgba(74,222,128,0); } }
        label { display: block; margin: 12px 0 6px; font-weight: 700; color: #334155; }
        input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; transition: border-color 0.18s ease, box-shadow 0.18s ease; }
        input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 4px rgba(37,99,235,0.12); }
        .button { display: inline-block; width: 100%; margin-top: 18px; padding: 12px 18px; background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 700; box-shadow: 0 12px 18px rgba(37,99,235,0.22); transition: transform 0.18s ease, box-shadow 0.18s ease; }
        .button:hover { background: linear-gradient(135deg, #1d4ed8 0%, #4338ca 100%); transform: translateY(-1px); box-shadow: 0 14px 24px rgba(37,99,235,0.28); }
        .message { margin-bottom: 20px; padding: 14px; border-radius: 10px; }
        .error { background: #fee2e2; color: #991b1b; }
        .success { background: #e6fffa; color: #065f46; }
        .small-link { margin-top: 14px; display: block; color: #334155; text-decoration: none; font-size: 14px; }
        .tip-box { margin-top: 16px; padding: 12px; border-radius: 10px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; font-size: 13px; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        @media (max-width: 640px) { .row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="hero">
        <div class="chip"><span></span> live student sign-up</div>
        <h1>የተማሪ መመዝገብ እንዲመስል አዲስ ቀላል እና በመስመር ላይ መለያ መፍጠር</h1>
        <p>ኮርስ መመዝገብ፣ የክፍያ መጠን ማስገባት እና በአንድ ጊዜ ግብአት መስጠት ይቻላል። ይህ ገጽ እርስዎን በቀላሉ ወደ ለማግኘት እንዲያመቻች በይበልጥ እንዲሰራ ተዘጋጅቷል።</p>
    </div>
    <h2 style="margin: 0 0 12px; color: #111827; font-size: 18px;">አዲስ ተማሪ ይመዝገቡ</h2>
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
        <div class="row">
            <div>
                <label for="name">ስም</label>
                <input id="name" name="name" value="<?php echo safe($name); ?>" required placeholder="ሙሉ ስም ያስገቡ">
            </div>
            <div>
                <label for="email">ኢሜይል</label>
                <input id="email" type="email" name="email" value="<?php echo safe($email); ?>" required placeholder="example@email.com">
            </div>
        </div>

        <label for="student_id">የተማሪ መለያ (አማራጭ)</label>
        <input id="student_id" name="student_id" value="<?php echo safe($studentId); ?>" placeholder="ባዶ ቢተው በራስ-ሰር ይፈጥራል">

        <div class="row">
            <div>
                <label for="password">የይለፍ ቃል</label>
                <input id="password" type="password" name="password" minlength="6" autocomplete="new-password" required>
            </div>
            <div>
                <label for="confirm_password">የይለፍ ቃል እንደገና</label>
                <input id="confirm_password" type="password" name="confirm_password" minlength="6" autocomplete="new-password" required>
            </div>
        </div>

        <div class="row">
            <div>
                <label for="course">ኮርስ</label>
                <input id="course" name="course" value="<?php echo safe($course); ?>" required placeholder="ኮርስ ስም ያስገቡ">
            </div>
            <div>
                <label for="amount">ክፍያ መጠን (ብር)</label>
                <input id="amount" type="number" step="0.01" name="amount" value="<?php echo safe($amount ?: '0'); ?>" required>
            </div>
        </div>

        <?php if ($recaptchaSiteKey !== ''): ?>
            <div style="margin-top: 16px;">
                <script src="https://www.google.com/recaptcha/api.js" async defer></script>
                <div class="g-recaptcha" data-sitekey="<?php echo safe($recaptchaSiteKey); ?>"></div>
            </div>
        <?php endif; ?>

        <button class="button" type="submit">መመዝገብ ጨርስ</button>
    </form>
    <div class="tip-box">የተማሪ መለያ ባዶ ከተተው በራስ-ሰር ይፈጥራል። የይለፍ ቃል ቢያንስ 6 ቁምፊ መሆን አለበት። ከመመዝገብዎ በኋላ በድር በኩል ወደ ዳሽቦርድ ትመለሳላችሁ።</div>
    <a class="small-link" href="student_login.php">ከዚህ በፊት እንደ ተማሪ ይገቡ</a>
</div>
</body>
</html>
