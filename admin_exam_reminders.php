<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once 'db.php';
require_once 'admin_lang.php';

$message = '';
$editId = (int)($_GET['edit'] ?? 0);
$students = [];

try {
    $studentStmt = $pdo->query('SELECT student_id, name, email FROM students ORDER BY name, student_id');
    $students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS exam_reminders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        exam_type VARCHAR(100) NOT NULL,
        exam_date DATETIME NOT NULL,
        reminder_message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {}

$existing = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM exam_reminders WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = trim($_POST['student_id'] ?? '');
    $studentId = $studentId === '' ? 'ALL' : $studentId;
    $examType = trim($_POST['exam_type'] ?? '');
    $examDate = trim($_POST['exam_date'] ?? '');
    $reminder = trim($_POST['reminder_message'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($studentId !== '' && $examType !== '' && $examDate !== '' && $reminder !== '') {
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE exam_reminders SET student_id = ?, exam_type = ?, exam_date = ?, reminder_message = ? WHERE id = ?');
            $stmt->execute([$studentId, $examType, $examDate, $reminder, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO exam_reminders (student_id, exam_type, exam_date, reminder_message) VALUES (?, ?, ?, ?)');
            $stmt->execute([$studentId, $examType, $examDate, $reminder]);
        }
        header('Location: admin_exam_reminders.php?success=1');
        exit;
    } else {
        $message = admin_text('required_message');
    }
}

$reminders = $pdo->query('SELECT * FROM exam_reminders ORDER BY exam_date DESC, created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo admin_text('manage_exam_reminders'); ?></title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;padding:0;color:#222}.
        .wrap{max-width:980px;margin:30px auto;padding:24px;background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.08)}
        h1{margin-top:0;color:#2563eb}label{display:block;margin-top:10px;font-weight:bold}input, textarea, button{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;margin-top:6px}textarea{min-height:110px}button{background:#2563eb;color:#fff;border:0;cursor:pointer}a{color:#2563eb;text-decoration:none}.msg{margin-top:12px;padding:10px 12px;border-radius:8px;background:#ecfdf3;color:#047857}table{width:100%;border-collapse:collapse;margin-top:16px}th,td{padding:10px;border-bottom:1px solid #e2e8f0;text-align:left}
    </style>
</head>
<body>
<div class="wrap">
    <h1>🔔 <?php echo admin_text('manage_exam_reminders'); ?></h1>
    <p><?php echo admin_text('reminder_intro'); ?></p>
    <?php if ($message): ?><div class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="id" value="<?php echo (int)($existing['id'] ?? 0); ?>">
        <label><?php echo admin_text('student_id_label'); ?></label>
        <select name="student_id" required>
            <option value="ALL" <?php echo (($existing['student_id'] ?? 'ALL') === 'ALL' ? 'selected' : ''); ?>><?php echo admin_text('all_students'); ?></option>
            <?php foreach ($students as $student): ?>
                <option value="<?php echo htmlspecialchars($student['student_id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($existing['student_id'] ?? '') === (string)$student['student_id'] ? 'selected' : ''); ?>><?php echo htmlspecialchars(($student['name'] ?: $student['student_id']) . ' (' . $student['student_id'] . ')', ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
        <label><?php echo admin_text('exam_type_label'); ?></label>
        <select name="exam_type" required>
            <option value="Mid Exam" <?php echo (($existing['exam_type'] ?? '') === 'Mid Exam' ? 'selected' : ''); ?>><?php echo admin_text('mid_exam'); ?></option>
            <option value="Final Exam" <?php echo (($existing['exam_type'] ?? '') === 'Final Exam' ? 'selected' : ''); ?>><?php echo admin_text('final_exam'); ?></option>
            <option value="Short Exam" <?php echo (($existing['exam_type'] ?? '') === 'Short Exam' ? 'selected' : ''); ?>><?php echo admin_text('short_exam'); ?></option>
        </select>
        <label><?php echo admin_text('exam_date_label'); ?></label>
        <input type="datetime-local" name="exam_date" value="<?php echo htmlspecialchars($existing['exam_date'] ? date('Y-m-d\TH:i', strtotime($existing['exam_date'])) : '', ENT_QUOTES, 'UTF-8'); ?>" required>
        <label><?php echo admin_text('reminder_message_label'); ?></label>
        <textarea name="reminder_message" placeholder="<?php echo admin_text('reminder_placeholder'); ?>" required><?php echo htmlspecialchars($existing['reminder_message'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
        <p style="margin-top:8px;color:#64748b;font-size:13px;"><?php echo admin_text('reminder_hint'); ?></p>
        <button type="submit"><?php echo $editId > 0 ? admin_text('update_reminder') : admin_text('add_reminder'); ?></button>
    </form>

    <h2 style="margin-top:24px;">📋 <?php echo admin_text('existing_reminders'); ?></h2>
    <table>
        <thead>
            <tr>
                <th><?php echo admin_text('student'); ?></th>
                <th><?php echo admin_text('exam'); ?></th>
                <th><?php echo admin_text('date_label'); ?></th>
                <th><?php echo admin_text('message'); ?></th>
                <th><?php echo admin_text('actions'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reminders)): ?>
                <tr><td colspan="5" style="text-align:center;"><?php echo admin_text('no_reminders'); ?></td></tr>
            <?php else: foreach ($reminders as $rem): ?>
                <tr>
                    <td><?php echo htmlspecialchars($rem['student_id'] === 'ALL' ? admin_text('all_students') : $rem['student_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($rem['exam_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($rem['exam_date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(substr($rem['reminder_message'], 0, 80), ENT_QUOTES, 'UTF-8'); ?><?php echo strlen($rem['reminder_message']) > 80 ? '...' : ''; ?></td>
                    <td><a href="admin_exam_reminders.php?edit=<?php echo (int)$rem['id']; ?>">✏️ <?php echo admin_text('edit'); ?></a></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <a href="admin_dashboard.php" style="display:inline-block;margin-top:18px;">← <?php echo admin_text('back'); ?></a>
    <a href="admin_lang.php?lang=<?php echo ($_SESSION['admin_lang'] ?? 'am') === 'am' ? 'en' : 'am'; ?>&redirect=admin_exam_reminders.php" style="margin-left:12px;"><?php echo admin_text('language_switch'); ?></a>
</div>
</body>
</html>
