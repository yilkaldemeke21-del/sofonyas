<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_config.php';

$errors = [];
$success = '';
$fallbackLink = '';
$csrfToken = csrfToken();

$name = '';
$email = '';
$studentId = '';
$course = '';
$password = '';
$confirmPassword = '';
$amount = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'ደኅንነት ተሰርዟል። እባክዎ ገጹን እንደገና ይጫኑ።';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $studentId = trim($_POST['student_id'] ?? '');
        $course = trim($_POST['course'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $amount = trim($_POST['amount'] ?? '0');

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
            $course = 'General Education';
        }
        if ($amount === '' || !is_numeric($amount) || (float)$amount < 0) {
            $errors[] = 'እባክዎ ትክክለኛ የክፍያ መጠን ያስገቡ።';
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
                $studentId = $studentId !== '' ? $studentId : strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', str_replace([' ', '@', '.'], '', $email)), 0, 6)) . random_int(100, 999);

                $stmt = $pdo->prepare('INSERT INTO students (name, email, student_id, password_hash, country, city) VALUES (:name, :email, :student_id, :password_hash, NULL, NULL)');
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
                    . '<p>Welcome to Sofnyas. Your registration is complete.</p>'
                    . '<p><strong>Student ID:</strong> ' . safe($studentId) . '<br>'
                    . '<strong>Course:</strong> ' . safe($course) . '<br>'
                    . '<strong>Amount:</strong> ' . safe($amount) . '</p>'
                    . '<p>Please verify your email by clicking the link below:</p>'
                    . '<p><a href="' . safe($verifyUrl) . '">Verify Email</a></p>'
                    . '<p>Thank you for choosing Sofnyas.</p>';
                $mailSent = sendAppEmail($email, $welcomeSubject, $welcomeMessage);

                if ($mailSent) {
                    $success = 'የተመዘገቡት ስኬታማ ነው። እባክዎ የማረጋገጫ ኢሜይልዎን ይፈትሹ።';
                } else {
                    $success = 'የምውራው ኢሜይል እንደሌለ ተመዝግቧል። ከዚህ በታች ያለውን አግኝቶ እርስዎን ያረጋግጡ።';
                    $fallbackLink = $verifyUrl;
                }

                $name = $email = $studentId = $course = $password = $confirmPassword = ''; 
            } catch (PDOException $e) {
                $errors[] = 'ተማሪውን መመዝገብ አልተሳካም። እባክዎ እንደገና ይሞክሩ።';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ይመዝገቡ - Sofnyas</title>
    <style>
        :root { color-scheme: light; font-family: 'Noto Sans Ethiopic', Arial, sans-serif; }
        body { margin: 0; min-height: 100vh; background: radial-gradient(circle at top, rgba(37, 99, 235, 0.16), transparent 30%), linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%); }
        .page { display: grid; min-height: 100vh; place-items: center; padding: 24px; }
        .card { width: min(100%, 780px); background: #ffffff; border-radius: 28px; box-shadow: 0 32px 80px rgba(15, 23, 42, 0.14); overflow: hidden; }
        .hero { display: grid; gap: 18px; padding: 38px 32px; background: linear-gradient(135deg, #1d4ed8, #4f46e5); color: #ffffff; }
        .hero h1 { margin: 0; font-size: clamp(2rem, 3vw, 2.8rem); line-height: 1.05; }
        .hero p { margin: 0; max-width: 720px; opacity: 0.92; }
        .content { padding: 32px; display: grid; gap: 24px; }
        .notice { padding: 16px 18px; border-radius: 16px; background: #f8fafc; border: 1px solid #cbd5e1; color: #334155; }
        .success { background: #ecfdf5; border-color: #a7f3d0; color: #14532d; }
        .error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        .fallback { background: #ffedd5; border-color: #fbbf24; color: #7c2d12; }
        form { display: grid; gap: 18px; }
        .grid { display: grid; gap: 18px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .field { display: grid; gap: 8px; }
        label { font-weight: 700; color: #0f172a; }
        input { width: 100%; border: 1px solid #cbd5e1; border-radius: 14px; padding: 14px 16px; font-size: 1rem; color: #0f172a; background: #ffffff; }
        input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12); }
        button { width: 100%; border: none; border-radius: 14px; padding: 16px; background: linear-gradient(135deg, #2563eb, #4f46e5); color: white; font-size: 1rem; font-weight: 700; cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        button:hover { transform: translateY(-2px); box-shadow: 0 16px 26px rgba(37, 99, 235, 0.22); }
        .small-text { color: #475569; font-size: 0.95rem; line-height: 1.6; }
        .link { color: #2563eb; text-decoration: none; }
        @media (max-width: 720px) { .grid { grid-template-columns: 1fr; } .hero { padding: 28px 22px; } .content { padding: 24px; } }
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <section class="hero">
            <div>
                <h1>የሙያዊ ይመዝገብ ገጽ</h1>
                <p>በSofnyas የተማሪ ምዝገባዎን በፈጣንና በተስማሚ መንገድ ያስተካክሉ። እባክዎ እንዲገቡ እና ኢሜይልዎን በማረጋገጥ አካውንቱን ያድርጉ።</p>
            </div>
            <div class="notice">
                <strong>እባክዎ ያስገቡ:</strong> ስም, ኢሜይል, የይለፍ ቃል, እና ኮርስ ስም።
            </div>
        </section>

        <section class="content">
            <?php if (!empty($errors)): ?>
                <div class="notice error">
                    <strong>ችግኝ:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo safe($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="notice success"><?php echo safe($success); ?></div>
            <?php endif; ?>

            <?php if ($fallbackLink !== ''): ?>
                <div class="notice fallback">
                    <p><strong>Email delivery failed.</strong> Use the direct link below to verify your account:</p>
                    <p><a href="<?php echo safe($fallbackLink); ?>"><?php echo safe($fallbackLink); ?></a></p>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo safe($csrfToken); ?>">
                <div class="grid">
                    <div class="field">
                        <label for="name">ስም</label>
                        <input type="text" id="name" name="name" value="<?php echo safe($name); ?>" required placeholder="ሙሉ ስም">
                    </div>
                    <div class="field">
                        <label for="email">ኢሜይል</label>
                        <input type="email" id="email" name="email" value="<?php echo safe($email); ?>" required placeholder="example@email.com">
                    </div>
                </div>

                <div class="grid">
                    <div class="field">
                        <label for="password">የይለፍ ቃል</label>
                        <input type="password" id="password" name="password" required placeholder="የይለፍ ቃል">
                    </div>
                    <div class="field">
                        <label for="confirm_password">የይለፍ ቃል እርስ በእርሱ</label>
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="የይለፍ ቃል ድጋፍ">
                    </div>
                </div>

                <div class="grid">
                    <div class="field">
                        <label for="student_id">የተማሪ መለያ (አማራጭ)</label>
                        <input type="text" id="student_id" name="student_id" value="<?php echo safe($studentId); ?>" placeholder="አማራጭ የተማሪ መለያ">
                    </div>
                    <div class="field">
                        <label for="course">ኮርስ</label>
                        <input type="text" id="course" name="course" value="<?php echo safe($course); ?>" placeholder="የኮርስ ስም">
                    </div>
                </div>

                <div class="field">
                    <label for="amount">የክፍያ መጠን</label>
                    <input type="number" id="amount" name="amount" value="<?php echo safe($amount === '' ? '0' : $amount); ?>" min="0" step="0.01" placeholder="0">
                </div>

                <button type="submit">አሁን ይመዝገቡ</button>
                <p class="small-text">እዚህ የተመዘገቡ በኢሜይል የማረጋገጫ አገልግሎት ይቀበላሉ። ከፍ ያለ ስርዓተ ኢንተርኔት ወይም SMTP እንዳለ ጥንካሬ ይኖራል።</p>
                <p class="small-text">አሉታዊ ግምገማ ካለዎት <a class="link" href="student_login.php">ይግቡ</a>።</p>
            </form>
        </section>
    </div>
</div>
</body>
</html>
