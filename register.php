<?php
require_once __DIR__ . '/db.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = safe($_POST['name'] ?? '');
    $email = safe($_POST['email'] ?? '');
    $studentId = safe($_POST['student_id'] ?? '');
    $course = safe($_POST['course'] ?? '');
    $amount = safe($_POST['amount'] ?? '0');

    if ($name === '') {
        $errors[] = 'ስም ያስገቡ።';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'እባክዎ ትክክለኛ ኢሜይል ያስገቡ።';
    }
    if ($studentId === '') {
        $errors[] = 'የተማሪ መለያ ቁጥር ያስገቡ።';
    }

    if (empty($errors)) {
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

        header('Location: payment.php?id=' . urlencode($recordId));
        exit;
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
    <h1>የምዝገባ ውጤት</h1>
    <?php if (!empty($errors)): ?>
        <div class="message error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <p>እባክዎ የምዝገባ ቅጽዎን ይሙሉ እና ወደ ዳሽቦርድ ይግቡ።</p>
    <a class="button" href="dashboard.php">ዳሽቦርድ እይ</a>
</div>
</body>
</html>
