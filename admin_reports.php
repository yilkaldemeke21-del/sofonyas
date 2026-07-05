<?php
session_start();
require_once __DIR__ . '/db.php';

$isAdmin = !empty($_SESSION['admin_id']);
$type = $_GET['type'] ?? 'students';
$printMode = !empty($_GET['print']);

$allowedTypes = [
    'students',
    'courses',
    'quiz_results',
    'certificates',
    'contact_messages',
    'announcements',
    'blog_posts',
    'enrollment_reports',
];

if (!in_array($type, $allowedTypes, true)) {
    $type = 'students';
}

$reportTitle = [
    'students' => 'Student List Report',
    'courses' => 'Course List Report',
    'quiz_results' => 'Quiz Results Report',
    'certificates' => 'Certificate Report',
    'contact_messages' => 'Contact Messages Report',
    'announcements' => 'Announcements Report',
    'blog_posts' => 'Blog Posts Report',
    'enrollment_reports' => 'Enrollment Report',
][$type] ?? 'Admin Report';

$rows = [];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_id'], $_POST['admin_reply'])) {
    $contactId = (int)$_POST['contact_id'];
    $reply = trim((string)$_POST['admin_reply']);
    $adminName = trim((string)($_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin'));

    if ($contactId > 0 && $reply !== '') {
        $stmt = $pdo->prepare('UPDATE contact_messages SET admin_reply = :admin_reply, replied_at = NOW(), replied_by = :replied_by, status = :status WHERE id = :id');
        $stmt->execute([
            ':admin_reply' => $reply,
            ':replied_by' => $adminName,
            ':status' => 'replied',
            ':id' => $contactId,
        ]);
        $message = 'Response saved successfully.';
    } else {
        $error = 'Please enter a response before saving.';
    }
}

switch ($type) {
    case 'students':
        $stmt = $pdo->query('SELECT name, email, student_id, created_at FROM students ORDER BY created_at DESC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
    case 'courses':
        $stmt = $pdo->query('SELECT course_name, course_code, instructor, created_at FROM courses ORDER BY created_at DESC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
    case 'quiz_results':
        $stmt = $pdo->query('SELECT student_name, exam_type, score, total_questions, submitted_at FROM exam_submissions ORDER BY submitted_at DESC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
    case 'certificates':
        $stmt = $pdo->query('SELECT student_name, exam_type, score, total_questions, issued_at FROM certificates ORDER BY issued_at DESC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
    case 'contact_messages':
        $stmt = $pdo->query('SELECT id, name, email, phone, subject, status, admin_reply, replied_at, replied_by, created_at FROM contact_messages ORDER BY created_at DESC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
    case 'announcements':
        $stmt = $pdo->query('SELECT event_title, event_description, event_date, created_at FROM event_announcements ORDER BY event_date DESC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
    case 'blog_posts':
        $stmt = $pdo->query('SELECT event_title, event_description, event_date, created_at FROM event_announcements ORDER BY event_date DESC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
    case 'enrollment_reports':
        $stmt = $pdo->query('SELECT name, email, student_id, course, amount, payment_status, created_at FROM registrations ORDER BY created_at DESC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
}

$columns = [];
switch ($type) {
    case 'students':
        $columns = [['label' => 'Name', 'key' => 'name'], ['label' => 'Email', 'key' => 'email'], ['label' => 'Student ID', 'key' => 'student_id'], ['label' => 'Created At', 'key' => 'created_at']];
        break;
    case 'courses':
        $columns = [['label' => 'Course Name', 'key' => 'course_name'], ['label' => 'Course Code', 'key' => 'course_code'], ['label' => 'Instructor', 'key' => 'instructor'], ['label' => 'Created At', 'key' => 'created_at']];
        break;
    case 'quiz_results':
        $columns = [['label' => 'Student', 'key' => 'student_name'], ['label' => 'Exam Type', 'key' => 'exam_type'], ['label' => 'Score', 'key' => 'score'], ['label' => 'Total', 'key' => 'total_questions'], ['label' => 'Submitted At', 'key' => 'submitted_at']];
        break;
    case 'certificates':
        $columns = [['label' => 'Student', 'key' => 'student_name'], ['label' => 'Exam Type', 'key' => 'exam_type'], ['label' => 'Score', 'key' => 'score'], ['label' => 'Total', 'key' => 'total_questions'], ['label' => 'Issued At', 'key' => 'issued_at']];
        break;
    case 'contact_messages':
        $columns = [['label' => 'Name', 'key' => 'name'], ['label' => 'Email', 'key' => 'email'], ['label' => 'Phone', 'key' => 'phone'], ['label' => 'Subject', 'key' => 'subject'], ['label' => 'Status', 'key' => 'status'], ['label' => 'Admin Reply', 'key' => 'admin_reply'], ['label' => 'Replied By', 'key' => 'replied_by'], ['label' => 'Created At', 'key' => 'created_at']];
        break;
    case 'announcements':
    case 'blog_posts':
        $columns = [['label' => 'Title', 'key' => 'event_title'], ['label' => 'Description', 'key' => 'event_description'], ['label' => 'Date', 'key' => 'event_date'], ['label' => 'Created At', 'key' => 'created_at']];
        break;
    case 'enrollment_reports':
        $columns = [['label' => 'Name', 'key' => 'name'], ['label' => 'Email', 'key' => 'email'], ['label' => 'Student ID', 'key' => 'student_id'], ['label' => 'Course', 'key' => 'course'], ['label' => 'Amount', 'key' => 'amount'], ['label' => 'Payment Status', 'key' => 'payment_status'], ['label' => 'Created At', 'key' => 'created_at']];
        break;
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe($reportTitle); ?></title>
    <style>
        body{font-family:Arial,sans-serif;margin:0;padding:20px;color:#111827;background:#fff}
        .wrap{max-width:1200px;margin:0 auto}
        h1{margin:0 0 10px;font-size:24px;color:#1d4ed8}
        .meta{color:#64748b;margin-bottom:16px}
        table{width:100%;border-collapse:collapse;margin-top:12px}
        th,td{border:1px solid #d1d5db;padding:8px;text-align:left;font-size:13px;vertical-align:top}
        th{background:#f3f4f6}
        .actions{margin-bottom:16px}
        button{padding:8px 12px;border:0;border-radius:6px;background:#2563eb;color:#fff;cursor:pointer}
        @media print{body{padding:0} .actions{display:none} a{color:#111 !important;text-decoration:none} button{display:none}}
    </style>
    <?php if ($printMode): ?>
    <script>
        window.onload = function () { window.print(); };
    </script>
    <?php endif; ?>
</head>
<body>
<div class="wrap">
    <div class="actions">
        <button type="button" onclick="window.print()">Print Report</button>
        <?php if ($isAdmin): ?>
            <a href="admin_dashboard.php" style="margin-left:10px;color:#2563eb;text-decoration:none;display:inline-block;padding:8px 12px;border:1px solid #cbd5e1;border-radius:6px;">ወደ ዳሽቦርድ ተመለስ</a>
        <?php endif; ?>
    </div>
    <?php if (!$isAdmin): ?>
        <div style="margin-bottom:14px;padding:10px 12px;border:1px solid #fde68a;background:#fef3c7;color:#92400e;border-radius:6px;">Preview mode: the report is visible without an active admin session. Sign in from the dashboard for full admin access.</div>
    <?php endif; ?>
    <h1><?php echo safe($reportTitle); ?></h1>
    <div class="meta">Generated on <?php echo date('Y-m-d H:i'); ?> • ሶፊ ዌብሳይት የመረጃ ማዕከል</div>
    <?php if ($message !== ''): ?><div style="margin-bottom:12px;padding:10px 12px;border-radius:8px;background:#ecfdf5;color:#166534;border:1px solid #a7f3d0;"><?php echo safe($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div style="margin-bottom:12px;padding:10px 12px;border-radius:8px;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;"><?php echo safe($error); ?></div><?php endif; ?>
    <?php if (empty($rows)): ?>
        <p>ምንም የቀረበ መረጃ የለም!.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <?php foreach ($columns as $column): ?>
                        <th><?php echo safe($column['label']); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($columns as $column): ?>
                            <td><?php echo safe($row[$column['key']] ?? ''); ?></td>
                        <?php endforeach; ?>
                        <?php if ($type === 'contact_messages'): ?>
                            <td>
                                <form method="post" style="display:flex; flex-direction:column; gap:6px; min-width:260px;">
                                    <input type="hidden" name="contact_id" value="<?php echo (int)($row['id'] ?? 0); ?>">
                                    <textarea name="admin_reply" rows="3" placeholder="Type response here..." style="padding:8px;border:1px solid #cbd5e1;border-radius:6px;"><?php echo safe($row['admin_reply'] ?? ''); ?></textarea>
                                    <button type="submit" style="width:auto;">Save Reply</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
