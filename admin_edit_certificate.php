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
        .preview { border: 1px solid #e5e7eb; border-radius: 18px; padding: 18px; background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%); margin-bottom: 16px; box-shadow: 0 16px 40px rgba(124,58,237,0.12); border: 2px solid #d8b4fe; }
        .preview-frame { border: 8px solid #8b5cf6; border-radius: 28px; padding: 18px 20px; background: linear-gradient(180deg, #ffffff 0%, #f5f3ff 100%); }
        .logo-chip { display: inline-flex; align-items: center; gap: 10px; font-weight: bold; color: #5b21b6; font-size: 14px; }
        .logo-box { width: 44px; height: 44px; border-radius: 14px; background: linear-gradient(135deg, #7c3aed, #a78bfa); color: white; display: grid; place-items: center; font-weight: bold; box-shadow: 0 10px 20px rgba(124,58,237,0.25); }
        .preview h3 { margin: 10px 0 8px; font-size: 22px; color: #111827; }
        .preview-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-top: 16px; }
        .preview-value { margin: 6px 0 0; font-size: 16px; color: #111827; font-weight: 700; }
        .mini { color: #475569; font-size: 13px; line-height: 1.6; }
        .mini strong { color: #111827; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; }
        .print-btn { display: inline-block; padding: 10px 14px; border-radius: 6px; background: #16a34a; color: white; text-decoration: none; border: 0; cursor: pointer; }
        .print-btn:hover { background: #15803d; }
        @media print {
            .topbar, .no-print, .msg, .actions { display: none !important; }
            body { background: #fff; }
            .card { box-shadow: none; border: 1px solid #ddd; }
            .preview-frame { border-color: #7c3aed; }
        }
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
            <div class="preview-frame">
                <div class="logo-chip"><span class="logo-box">ሶፊ</span> ዲ/ን ሶፎንያስ (ቤተ ገብርኤል) ኦንላይን ትምህርት ዌቭሣይት</div>
                <h3>የማጠናከር ሰርቲፊኬት</h3>
                <p class="mini">ይህ ሰርቲፊኬት እውነተኛ ብቃትና በደንብ እንዳሳየ የተማሪውን ማረጋገጫ ነው።</p>
                <div class="preview-grid">
                    <div>
                        <p class="mini"><strong>የተማሪ ስም</strong></p>
                        <p class="preview-value"><?php echo safe($student_name ?: 'እባክዎ ስም ያስገቡ'); ?></p>
                    </div>
                    <div>
                        <p class="mini"><strong>የፈተና አይነት</strong></p>
                        <p class="preview-value"><?php echo safe($exam_type ?: 'እባክዎ ያስገቡ'); ?></p>
                    </div>
                    <div>
                        <p class="mini"><strong>ውጤት</strong></p>
                        <p class="preview-value"><?php echo (int)$score; ?> / <?php echo (int)$total_questions; ?></p>
                    </div>
                    <div>
                        <p class="mini"><strong>የተሰጠበት ቀን</strong></p>
                        <p class="preview-value"><?php echo safe(date('Y-m-d H:i', strtotime($issued_at))); ?></p>
                    </div>
                </div>
            </div>
            <div class="actions no-print">
                <button type="submit" form="certificate-form">💾 ሰርቲፊኬት አስተካክል</button>
                <button type="button" class="print-btn" onclick="printCertificate()">🖨️ ሰርቲፊኬት አትም</button>
                <a class="print-btn" href="admin_certificate.php?download=<?php echo (int)$cert_id; ?>">⬇️ PDF አውርድ</a>
            </div>
        </div>

        <form id="certificate-form" method="post">
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
        <script>
        function printCertificate() {
            var preview = document.querySelector('.preview-frame');
            if (!preview) {
                window.print();
                return;
            }

            var css = 'body{font-family: Arial, Helvetica, sans-serif; margin:0; padding:24px; background:#fff} .preview-frame{border:8px solid #8b5cf6; border-radius:28px; padding:18px 20px; background:linear-gradient(180deg,#fff 0%,#f5f3ff 100%);}';
            var newWin = window.open('', '_blank');
            newWin.document.open();
            newWin.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Certificate Print</title><style>' + css + '</style></head><body>');
            newWin.document.write(preview.outerHTML);
            newWin.document.write('</body></html>');
            newWin.document.close();
            newWin.focus();
            setTimeout(function(){ newWin.print(); }, 250);
        }
        </script>
</body>
</html>
