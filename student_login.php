<?php
session_start();
require_once __DIR__ . '/db.php';

if (isset($_SESSION['student_id'])) {
    header('Location: student_dashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'እባክዎ ኢሜይልና የይለፍ ቃል ያስገቡ።';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM students WHERE email = :email OR student_id = :student_id LIMIT 1');
        $stmt->execute([':email' => $email, ':student_id' => $email]);
        $student = $stmt->fetch();

        if ($student && password_verify($password, $student['password_hash'])) {
            $_SESSION['student_id'] = $student['student_id'];
            $_SESSION['student_email'] = $student['email'];
            $_SESSION['student_name'] = $student['name'];
            header('Location: student_dashboard.php');
            exit;
        }

        $error = 'ኢሜይል ወይም የይለፍ ቃል ትክክል አይደለም።';
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>የተማሪ ግባ</title>
    <style>
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%); margin: 0; padding: 0; }
        .wrapper { max-width: 520px; margin: 40px auto; padding: 24px; background: white; border-radius: 18px; box-shadow: 0 18px 40px rgba(15,23,42,0.10); }
        .hero { background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%); color: #fff; border-radius: 14px; padding: 18px; margin-bottom: 18px; }
        .hero h1 { margin: 0 0 6px; color: #fff; font-size: 24px; }
        .hero p { margin: 0; color: #e0e7ff; font-size: 14px; line-height: 1.5; }
        label { display: block; margin-top: 14px; margin-bottom: 6px; font-weight: 700; color: #334155; }
        input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; }
        .button { margin-top: 18px; width: 100%; padding: 14px 18px; background: linear-gradient(135deg, #16a34a 0%, #22c55e 100%); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 700; font-size: 16px; box-shadow: 0 12px 24px rgba(16, 185, 129, 0.22); transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .button:hover { background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); transform: translateY(-1px); box-shadow: 0 14px 26px rgba(16, 185, 129, 0.28); }
        .message { margin-bottom: 20px; padding: 14px; border-radius: 10px; }
        .error { background: #fee2e2; color: #991b1b; }
        .small-link { margin-top: 18px; display: block; color: #334155; text-decoration: none; font-size: 14px; }
        .demo-box { margin-top: 18px; padding: 16px; border-radius: 12px; background: #f0fdf4; border: 1px solid #d1fae5; color: #14532d; }
        .demo-box strong { display: block; margin-bottom: 8px; font-size: 15px; }
        .demo-box code { display: inline-block; background: #ecfdf5; padding: 4px 8px; border-radius: 6px; color: #065f46; font-size: 14px; }
        .tip-box { margin-top: 14px; padding: 12px; border-radius: 10px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; font-size: 13px; line-height: 1.5; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="hero">
        <h1>የተማሪዎች መግቢያ</h1>
        <p>ኢሜይል ወይም የተማሪ መለያ ቁጥርዎን በመጠቀም ወደ ዳሽቦርድ ይግቡ።</p>
    </div>
    <?php if ($error): ?>
        <div class="message error"><?php echo safe($error); ?></div>
    <?php endif; ?>
    <form method="post">
        <label for="email">ኢሜይል ወይም የተማሪ መለያ</label>
        <input id="email" type="text" name="email" value="<?php echo safe($email); ?>" required>

        <label for="password">የይለፍ ቃል</label>
        <input id="password" type="password" name="password" required>

        <button class="button" type="submit">ግባ</button>
    </form>
    <div class="tip-box">
        መረጃ: ከመመዝገብዎ በኋላ የተፈጠረውን የተማሪ መለያ ወይም ኢሜይል በመጠቀም ይግቡ። የይለፍ ቃል የመመዝገቡትን ቃል ይጠቀሙ።
    </div>
    <div class="demo-box">
        <strong>Demo Student</strong>
        የተማሪ መለያ: <code>sofi2127</code><br>
        ይለፍ ቃል: <code>student123</code>
    </div>
    <a class="small-link" href="student_register.php">አዲስ ተማሪ መመዝገብ ይፈልጋሉ?</a>
</div>
</body>
</html>
