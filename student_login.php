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
        $stmt = $pdo->prepare('SELECT * FROM students WHERE email = :email');
        $stmt->execute([':email' => $email]);
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
        body { font-family: Arial, sans-serif; background: #f4f7ff; margin: 0; padding: 0; }
        .wrapper { max-width: 480px; margin: 50px auto; padding: 24px; background: white; border-radius: 12px; box-shadow: 0 10px 24px rgba(15,23,42,0.08); }
        h1 { margin-bottom: 18px; color: #1f2937; }
        label { display: block; margin-top: 14px; margin-bottom: 6px; font-weight: bold; }
        input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; }
        .button { margin-top: 18px; width: 100%; padding: 12px 18px; background: #2563eb; color: white; border: none; border-radius: 8px; cursor: pointer; }
        .button:hover { background: #1d4ed8; }
        .message { margin-bottom: 20px; padding: 14px; border-radius: 10px; }
        .error { background: #fee2e2; color: #991b1b; }
        .small-link { margin-top: 18px; display: block; color: #334155; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrapper">
    <h1>የተማሪዎች መግቢያ</h1>
    <?php if ($error): ?>
        <div class="message error"><?php echo safe($error); ?></div>
    <?php endif; ?>
    <form method="post">
        <label for="email">ኢሜይል</label>
        <input id="email" type="email" name="email" value="<?php echo safe($email); ?>" required>

        <label for="password">የይለፍ ቃል</label>
        <input id="password" type="password" name="password" required>

        <button class="button" type="submit">ግባ</button>
    </form>
    <a class="small-link" href="student_register.php">እንደ አዲስ ተማሪ ይመዝገቡ</a>
</div>
</body>
</html>
