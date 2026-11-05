<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/management_schema.php';
requireRole(['admin'], $pdo);

if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit('Database connection is unavailable. Start MySQL and try again.');
}

ensureManagementTables($pdo);
$pdo->exec('CREATE TABLE IF NOT EXISTS exam_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(100) NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    exam_type VARCHAR(50) NOT NULL,
    score INT NOT NULL DEFAULT 0,
    total_questions INT NOT NULL DEFAULT 0,
    answers JSON DEFAULT NULL,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$education = [
    'students' => (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
    'courses' => (int)$pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn(),
    'registrations' => (int)$pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn(),
    'exam_results' => (int)$pdo->query('SELECT COUNT(*) FROM exam_submissions')->fetchColumn(),
];
$church = [
    'members' => (int)$pdo->query('SELECT COUNT(*) FROM church_members')->fetchColumn(),
    'active_members' => (int)$pdo->query("SELECT COUNT(*) FROM church_members WHERE status = 'active'")->fetchColumn(),
    'today_attendance' => (int)$pdo->query('SELECT COUNT(*) FROM church_attendance WHERE service_date = CURDATE() AND attendance_status = "present"')->fetchColumn(),
];
$finance = $pdo->query("SELECT COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) AS income, COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) AS expense FROM finance_transactions")->fetch(PDO::FETCH_ASSOC);
$finance['balance'] = (float)$finance['income'] - (float)$finance['expense'];
$recentExams = $pdo->query('SELECT student_name, exam_type, score, total_questions, submitted_at FROM exam_submissions ORDER BY submitted_at DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
$recentAttendance = $pdo->query('SELECT a.service_name, a.service_date, a.attendance_status, m.full_name FROM church_attendance a JOIN church_members m ON m.id = a.member_id ORDER BY a.service_date DESC, a.id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
$recentFinance = $pdo->query('SELECT transaction_type, category, description, amount, transaction_date FROM finance_transactions ORDER BY transaction_date DESC, id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Church and Education Dashboard</title>
    <style>
        :root{font-family:Arial,sans-serif;color:#202b2b;background:#f4f7f5;font-synthesis:none}*{box-sizing:border-box}body{margin:0}.topbar{background:#143b36;color:#fff;padding:17px max(20px,calc((100% - 1200px)/2));display:flex;align-items:center;justify-content:space-between;gap:16px}.brand h1{margin:0;font-size:20px}.brand p{margin:4px 0 0;color:#c4d5ce;font-size:13px}.topbar a{color:#fff;text-decoration:none;border:1px solid #739188;border-radius:4px;padding:9px 12px}.wrap{max-width:1200px;margin:24px auto;padding:0 18px}.section-title{margin:25px 0 12px;font-size:18px}.cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.metric{background:#fff;padding:16px;border-top:3px solid #236c5e;min-height:100px}.metric span{display:block;color:#65736e;font-size:13px}.metric strong{display:block;font-size:25px;margin-top:8px}.metric.money{border-top-color:#b6853b}.metric.expense{border-top-color:#a33b35}.metric.secondary{border-top-color:#607b8a}.links{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px}.links a{display:flex;align-items:center;justify-content:space-between;background:#fff;border:1px solid #dbe4df;padding:13px 14px;color:#215e52;text-decoration:none;font-weight:600}.links a:hover{border-color:#236c5e;background:#f9fcfa}.panels{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.panel{background:#fff;padding:16px;border-top:2px solid #d7e2dc;min-width:0}.panel h3{margin:0 0 12px;font-size:16px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:13px}td{padding:9px 7px;border-bottom:1px solid #e8eeea;text-align:left;vertical-align:top}td:last-child{text-align:right;white-space:nowrap}.muted{color:#6a7873}.tag{font-weight:600}.income{color:#19734e}.expense-text{color:#a33b35}.empty{padding:12px 4px;color:#6a7873;font-size:14px}.section-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px}.section-head h3{margin:0}.section-head a{font-size:13px;color:#236c5e;text-decoration:none}@media(max-width:850px){.cards{grid-template-columns:repeat(2,minmax(0,1fr))}.links{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.panels{grid-template-columns:1fr}.topbar{padding:14px 18px}.brand h1{font-size:17px}}@media(max-width:380px){.cards,.links{grid-template-columns:1fr}}
    </style>
</head>
<body>
<header class="topbar"><div class="brand"><h1>የቤተክርስቲያንና ትምህርት አስተዳደር</h1><p>Church membership, education, attendance, and financial overview</p></div><a href="admin_dashboard.php">← Main admin dashboard</a></header>
<main class="wrap">
    <h2 class="section-title" style="margin-top:0">Education overview</h2>
    <div class="cards">
        <div class="metric"><span>Registered students</span><strong><?php echo number_format($education['students']); ?></strong></div>
        <div class="metric secondary"><span>Courses</span><strong><?php echo number_format($education['courses']); ?></strong></div>
        <div class="metric secondary"><span>Course registrations</span><strong><?php echo number_format($education['registrations']); ?></strong></div>
        <div class="metric"><span>Exam results</span><strong><?php echo number_format($education['exam_results']); ?></strong></div>
    </div>
    <h2 class="section-title">Church overview</h2>
    <div class="cards">
        <div class="metric"><span>All members</span><strong><?php echo number_format($church['members']); ?></strong></div>
        <div class="metric"><span>Active members</span><strong><?php echo number_format($church['active_members']); ?></strong></div>
        <div class="metric secondary"><span>Present today</span><strong><?php echo number_format($church['today_attendance']); ?></strong></div>
        <div class="metric money"><span>Attendance records today</span><strong><?php echo (int)$pdo->query('SELECT COUNT(*) FROM church_attendance WHERE service_date = CURDATE()')->fetchColumn(); ?></strong></div>
    </div>
    <h2 class="section-title">Financial overview</h2>
    <div class="cards">
        <div class="metric money"><span>Total income</span><strong><?php echo number_format((float)$finance['income'], 2); ?></strong></div>
        <div class="metric expense"><span>Total expenses</span><strong><?php echo number_format((float)$finance['expense'], 2); ?></strong></div>
        <div class="metric"><span>Net balance</span><strong><?php echo number_format((float)$finance['balance'], 2); ?></strong></div>
        <div class="metric secondary"><span>Unpaid course registrations</span><strong><?php echo (int)$pdo->query("SELECT COUNT(*) FROM registrations WHERE payment_status = 'unpaid'")->fetchColumn(); ?></strong></div>
    </div>
    <h2 class="section-title">Management</h2>
    <nav class="links" aria-label="Management pages">
        <a href="admin_church_members.php">Church member register <span>→</span></a>
        <a href="admin_spiritual_dashboard.php">Spiritual management <span>→</span></a>
        <a href="admin_education_dashboard.php">Educational management <span>→</span></a>
        <a href="admin_sunday_school.php">Sunday school and ministry <span>→</span></a>
        <a href="admin_church_management.php">Church members and attendance <span>→</span></a>
        <a href="admin_finance.php">Income and expense ledger <span>→</span></a>
        <a href="admin_students.php">Student registration <span>→</span></a>
        <a href="admin_registrations.php">Course enrollments and payments <span>→</span></a>
        <a href="admin_exam_results.php">Exam grades and results <span>→</span></a>
        <a href="admin_reports.php?type=students">Student report <span>→</span></a>
        <a href="admin_reports.php?type=enrollment_reports">Enrollment report <span>→</span></a>
        <a href="admin_reports.php?type=quiz_results">Exam report <span>→</span></a>
    </nav>
    <h2 class="section-title">Recent activity</h2>
    <div class="panels">
        <section class="panel"><div class="section-head"><h3>Latest exam results</h3><a href="admin_exam_results.php">All results →</a></div>
            <?php if (!$recentExams): ?><div class="empty">No exam results yet.</div><?php else: ?><div class="table-wrap"><table><tbody><?php foreach ($recentExams as $row): ?><tr><td><?php echo safe($row['student_name']); ?><br><span class="muted"><?php echo safe($row['exam_type']); ?></span></td><td><?php echo (int)$row['score']; ?> / <?php echo (int)$row['total_questions']; ?></td><td><?php echo safe($row['submitted_at']); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        </section>
        <section class="panel"><div class="section-head"><h3>Latest attendance</h3><a href="admin_church_management.php">Attendance report →</a></div>
            <?php if (!$recentAttendance): ?><div class="empty">No church attendance recorded yet.</div><?php else: ?><div class="table-wrap"><table><tbody><?php foreach ($recentAttendance as $row): ?><tr><td><?php echo safe($row['full_name']); ?><br><span class="muted"><?php echo safe($row['service_name']); ?></span></td><td><?php echo safe(ucfirst($row['attendance_status'])); ?></td><td><?php echo safe($row['service_date']); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        </section>
        <section class="panel"><div class="section-head"><h3>Latest financial entries</h3><a href="admin_finance.php">Open ledger →</a></div>
            <?php if (!$recentFinance): ?><div class="empty">No income or expense entries yet.</div><?php else: ?><div class="table-wrap"><table><tbody><?php foreach ($recentFinance as $row): ?><tr><td><?php echo safe($row['category']); ?><br><span class="muted"><?php echo safe($row['description']); ?></span></td><td class="tag <?php echo $row['transaction_type'] === 'income' ? 'income' : 'expense-text'; ?>"><?php echo safe(ucfirst($row['transaction_type'])); ?></td><td><?php echo number_format((float)$row['amount'], 2); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        </section>
        <section class="panel"><div class="section-head"><h3>Next steps</h3></div><div class="empty">Use the management links above to register members and students, record attendance, enter finance transactions, and review grades.</div></section>
    </div>
</main>
</body>
</html>