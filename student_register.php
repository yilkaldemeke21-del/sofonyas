<?php
session_start();
require_once __DIR__ . '/db.php';

$errors = [];
$name = '';
$email = '';
$studentId = '';
$course = '';
$amount = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $studentId = trim($_POST['student_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $course = trim($_POST['course'] ?? '');
    $amount = trim($_POST['amount'] ?? '0');

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
    if ($course === '') {
        $errors[] = 'ኮርሱን ያስገቡ።';
    }
    if (!is_numeric($amount) || $amount < 0) {
        $errors[] = 'እባክዎ ትክክለኛ የክፍያ መጠን ያስገቡ።';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM students WHERE email =:email OR student_id = :student_id');
        $stmt->execute([':email' => $email, ':student_id' => $studentId]);
        if ($stmt->fetch()) {
            $errors[] = 'እባክዎ ያስገቡት ኢሜይል ወይም የተማሪ መለያ ቁጥር አስምር ነው።';
        }
    }

    if (empty($errors)) {
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

        $_SESSION['student_id'] = $studentId;
        $_SESSION['student_email'] = $email;
        $_SESSION['student_name'] = $name;

        header('Location: student_dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>የተማሪ ምዝገባ</title>
    <style>
        body { font-family: Arial, sans-serif; background: #eff5ff; margin: 0; padding: 0; }
        .wrapper { max-width: 540px; margin: 40px auto; padding: 20px; background: white; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        h1 { margin-bottom: 20px; color: #1d3557; }
        label { display: block; margin: 12px 0 6px; font-weight: bold; }
        input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; }
        .button { display: inline-block; margin-top: 18px; padding: 12px 18px; background: #4f46e5; color: white; border: none; border-radius: 8px; cursor: pointer; }
        .button:hover { background: #4338ca; }
        .message { margin-bottom: 20px; padding: 14px; border-radius: 8px; }
        .error { background: #fee2e2; color: #991b1b; }
        .success { background: #e6fffa; color: #065f46; }
        .small-link { margin-top: 18px; display: block; color: #334155; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrapper">
    <h1>የተማሪ ምዝገባ</h1>
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
        <label for="name">ስም</label>
        <input id="name" name="name" value="<?php echo safe($name); ?>" required>

        <label for="email">ኢሜይል</label>
        <input id="email" type="email" name="email" value="<?php echo safe($email); ?>" required>

        <label for="student_id">የተማሪ መለያ</label>
        <input id="student_id" name="student_id" value="<?php echo safe($studentId); ?>" required>

        <label for="password">የይለፍ ቃል</label>
        <input id="password" type="password" name="password" required>

        <label for="course">ኮርስ</label>
        <input id="course" name="course" value="<?php echo safe($course); ?>" required>

        <label for="amount">ክፍያ መጠን (ብር)</label>
        <input id="amount" type="number" step="0.01" name="amount" value="<?php echo safe($amount ?: '0'); ?>" required>

        <button class="button" type="submit">ምዝገብ</button>
    </form>
    <a class="small-link" href="student_login.php">ከዚህ በፊት እንደ ተማሪ ይገቡ</a>
</div>
</body>
</html>
