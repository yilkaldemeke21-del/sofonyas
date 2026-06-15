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
$issued_at = $certificate['issued_at'] ?? date('Y-m-d H:i:s');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_name = trim($_POST['student_name'] ?? '');
    $exam_type = trim($_POST['exam_type'] ?? '');
    $score = max(0, (int)($_POST['score'] ?? 0));
    $total_questions = max(1, (int)($_POST['total_questions'] ?? 1));
    $issued_at = trim($_POST['issued_at'] ?? '');
    $issued_at = $issued_at !== '' ? date('Y-m-d H:i:s', strtotime($issued_at)) : date('Y-m-d H:i:s');

    if ($student_name === '' || $exam_type === '') {
        $error = 'ስም እና ፈተና አይነት ያስገቡ።';
    } else {
        try {
            $stmt = $pdo->prepare('UPDATE certificates SET student_name = :student_name, exam_type = :exam_type, score = :score, total_questions = :total_questions, issued_at = :issued_at WHERE id = :id');
            $stmt->execute([
                ':student_name' => $student_name,
                ':exam_type' => $exam_type,
                ':score' => $score,
                ':total_questions' => $total_questions,
                ':issued_at' => $issued_at,
                ':id' => $cert_id,
            ]);
            $success = 'ሰርቲፊኬት በትክክል ተስተካክሏል።';
            $certificate['student_name'] = $student_name;
            $certificate['exam_type'] = $exam_type;
            $certificate['score'] = $score;
            $certificate['total_questions'] = $total_questions;
            $certificate['issued_at'] = $issued_at;
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
        .preview { border: 1px solid #e5e7eb; border-radius: 14px; padding: 16px; background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%); margin-bottom: 16px; box-shadow: 0 12px 30px rgba(124,58,237,0.10); }
        .logo-chip { display: inline-flex; align-items: center; gap: 10px; font-weight: bold; color: #5b21b6; font-size: 14px; }
        .logo-box { width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, #7c3aed, #a78bfa); color: white; display: grid; place-items: center; font-weight: bold; box-shadow: 0 8px 16px rgba(124,58,237,0.25); }
        .preview h3 { margin: 8px 0 8px; font-size: 20px; color: #111827; }
        .mini { color: #475569; font-size: 13px; line-height: 1.5; }
        .mini strong { color: #111827; }
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

        <div class="preview">
            <div class="logo-chip"><span class="logo-box">ሶፊ</span>ዲ/ን ሶፎንያስ(ቤተ ገብርኤል) ኦንላይን መንፈሳዊ የቤ/ክ ዌቭሣይት</div>
            <h3>Certificate Preview</h3>
            <p class="mini">የተማሪ ስም: <strong><?php echo safe($student_name ?: 'student name'); ?></strong></p>
            <p class="mini">የፈተናው አይነት: <strong><?php echo safe($exam_type ?: 'Exam Type'); ?></strong></p>
            <p class="mini">ያመጣው ነጥብ: <strong><?php echo (int)$score; ?> / <?php echo (int)$total_questions; ?></strong></p>
            <p class="mini">የተሰጠበት ቀን: <strong><?php echo safe(date('Y-m-d H:i', strtotime($issued_at))); ?></strong></p>
            <p class="mini">ይህንን በpdf መልኩ የሚታየዉን የሠርተፊኬት ዉጤት ማዉረድ ይቻላል።</p>
        </div>

        <form method="post">
            <div class="field">
                <label for="student_name">የተማሪ ስም</label>
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
            <div class="field">
                <label for="issued_at">የተሰጠበት ቀን</label>
                <input type="datetime-local" id="issued_at" name="issued_at" value="<?php echo date('Y-m-d\TH:i', strtotime($issued_at)); ?>" required>
            </div>
            <button type="submit">ሰርቲፊኬት አስተካክል</button>
        </form>
    </div>
</div>
</body>
</html>
