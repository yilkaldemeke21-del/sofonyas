<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$cert_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

if ($cert_id) {
    $stmt = $pdo->prepare('SELECT * FROM certificates WHERE id = :id');
    $stmt->execute([':id' => $cert_id]);
    $certificate = $stmt->fetch();
    if (!$certificate) {
        header('Location: admin_certificate.php');
        exit;
    }
} else {
    header('Location: admin_certificate.php');
    exit;
}

$student_name = $certificate['student_name'] ?? '';
$exam_type = $certificate['exam_type'] ?? '';
$score = (int)($certificate['score'] ?? 0);
$total_questions = (int)($certificate['total_questions'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_name = trim($_POST['student_name'] ?? '');
    $exam_type = trim($_POST['exam_type'] ?? '');
    $score = max(0, (int)($_POST['score'] ?? 0));
    $total_questions = max(1, (int)($_POST['total_questions'] ?? 1));

    if ($student_name === '' || $exam_type === '') {
        $error = 'ስም እና ፈተና አይነት ያስገቡ።';
    } else {
        try {
            $stmt = $pdo->prepare('UPDATE certificates SET student_name = :student_name, exam_type = :exam_type, score = :score, total_questions = :total_questions WHERE id = :id');
            $stmt->execute([
                ':student_name' => $student_name,
                ':exam_type' => $exam_type,
                ':score' => $score,
                ':total_questions' => $total_questions,
                ':id' => $cert_id,
            ]);
            $success = 'ሰርቲፊኬት በትክክል ተስተካክሏል።';
            $certificate['student_name'] = $student_name;
            $certificate['exam_type'] = $exam_type;
            $certificate['score'] = $score;
            $certificate['total_questions'] = $total_questions;
        } catch (PDOException $e) {
            $error = 'ስህተት: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>ሰርቲፊኬት ማስተካከል</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f5f7fa; color: #1f2937; }
        .topbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; }
        .topbar a { color: white; text-decoration: none; background: rgba(255,255,255,0.12); padding: 8px 12px; border-radius: 6px; }
        .wrap { max-width: 700px; margin: 24px auto; padding: 0 18px; }
        .card { background: white; border-radius: 10px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); padding: 18px; }
        .msg { padding: 10px 12px; border-radius: 6px; margin-bottom: 12px; }
        .msg.ok { background: #d4f1d8; color: #1d6a2b; }
        .msg.err { background: #ffe7e7; color: #a41616; }
        label { display: block; margin-bottom: 6px; font-weight: bold; }
        input, button { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #d1d5db; font-size: 14px; }
        button { background: #667eea; color: white; border: none; cursor: pointer; font-weight: bold; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .field { margin-bottom: 12px; }
    </style>
</head>
<body>
<div class="topbar">
    <h2 style="margin: 0;">✏️ ሰርቲፊኬት ማስተካከል</h2>
    <a href="admin_certificate.php">ወደ ሰርቲፊኬት ማስተዳደር</a>
</div>
<div class="wrap">
    <div class="card">
        <?php if ($error): ?><div class="msg err"><?php echo safe($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="msg ok"><?php echo safe($success); ?></div><?php endif; ?>
        <form method="post">
            <div class="field">
                <label for="student_name">ተማሪ ስም</label>
                <input type="text" id="student_name" name="student_name" value="<?php echo safe($student_name); ?>" required>
            </div>
            <div class="field">
                <label for="exam_type">ፈተና አይነት</label>
                <input type="text" id="exam_type" name="exam_type" value="<?php echo safe($exam_type); ?>" required>
            </div>
            <div class="row">
                <div class="field">
                    <label for="score">ውጤት</label>
                    <input type="number" id="score" name="score" min="0" value="<?php echo (int)$score; ?>" required>
                </div>
                <div class="field">
                    <label for="total_questions">ጠቅላላ ጥያቄዎች</label>
                    <input type="number" id="total_questions" name="total_questions" min="1" value="<?php echo (int)$total_questions; ?>" required>
                </div>
            </div>
            <button type="submit">ሰርቲፊኬት አስተካክል</button>
        </form>
    </div>
</div>
</body>
</html>
