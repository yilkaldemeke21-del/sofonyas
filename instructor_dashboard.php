<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['user_role'] ?? 'Admin') !== 'Instructor') {
    header('Location: admin_login.php');
    exit;
}

$instructorName = $_SESSION['admin_username'] ?? 'Instructor';

$stmt = $pdo->query('SELECT COUNT(*) as total FROM courses');
$total_courses = (int)($stmt->fetch()['total'] ?? 0);

$stmt = $pdo->query('SELECT COUNT(*) as total FROM registrations');
$total_registrations = (int)($stmt->fetch()['total'] ?? 0);

$stmt = $pdo->query('SELECT COUNT(*) as total FROM students');
$total_students = (int)($stmt->fetch()['total'] ?? 0);

$stmt = $pdo->query('SELECT COUNT(*) as total FROM questions');
$total_questions = (int)($stmt->fetch()['total'] ?? 0);

$stmt = $pdo->query('SELECT SUM(amount) as total_revenue FROM registrations WHERE payment_status = "paid"');
$result = $stmt->fetch();
$total_revenue = (float)($result['total_revenue'] ?? 0);

$recent_courses = $pdo->query('SELECT * FROM courses ORDER BY created_at DESC LIMIT 5')->fetchAll();
$recent_students = $pdo->query('SELECT * FROM students ORDER BY created_at DESC LIMIT 5')->fetchAll();
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>Instructor Dashboard</title>
    <style>
        * { box-sizing: border-box; font-family: Arial, sans-serif; }
        body { margin: 0; background: linear-gradient(135deg, #eff6ff, #f8fafc); color: #0f172a; }
        .topbar { display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff; padding: 18px 24px; box-shadow: 0 10px 24px rgba(37, 99, 235, 0.18); }
        .topbar h1 { margin: 0; font-size: 22px; }
        .topbar p { margin: 4px 0 0; color: #dbeafe; font-size: 13px; }
        .topbar a { color: #fff; text-decoration: none; background: rgba(255,255,255,0.12); padding: 9px 12px; border-radius: 8px; margin-left: 10px; }
        .shell { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .badge { display: inline-flex; align-items: center; gap: 8px; background: #eff6ff; color: #1d4ed8; padding: 8px 12px; border-radius: 999px; font-weight: 700; font-size: 12px; border: 1px solid #bfdbfe; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; margin: 18px 0 24px; }
        .card { background: #fff; border-radius: 18px; padding: 18px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); border: 1px solid #e5eefb; }
        .card h3 { margin: 0 0 6px; color: #334155; font-size: 14px; text-transform: uppercase; letter-spacing: .08em; }
        .card .value { font-size: 28px; font-weight: 800; color: #1e293b; }
        .card .note { color: #64748b; font-size: 12px; margin-top: 6px; }
        .panel { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 18px; }
        .list-card { background: #fff; border-radius: 18px; padding: 18px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); border: 1px solid #e5eefb; }
        .list-card h2 { margin-top: 0; font-size: 18px; color: #111827; }
        ul { padding-left: 18px; color: #334155; }
        li { margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px 8px; border-bottom: 1px solid #e5e7eb; text-align: left; font-size: 13px; }
        th { color: #475569; background: #f8fafc; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
        .btn { display: inline-block; background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff; text-decoration: none; padding: 10px 12px; border-radius: 10px; font-weight: 700; font-size: 13px; }
        .btn.secondary { background: linear-gradient(135deg, #0f766e, #14b8a6); }
    </style>
</head>
<body>
<div class="topbar">
    <div>
        <h1>👩‍🏫 Instructor Panel</h1>
        <p>Role: <?php echo safe($_SESSION['user_role'] ?? 'Instructor'); ?> · Welcome, <?php echo safe($instructorName); ?></p>
    </div>
    <div>
        <a href="admin_logout.php">Logout</a>
        <a href="admin_dashboard.php">Admin Area</a>
    </div>
</div>

<div class="shell">
    <div class="badge">Role-based access is enabled for Admin, Instructor, and Student users.</div>

    <div class="grid">
        <div class="card">
            <h3>ኮርስ</h3>
            <div class="value"><?php echo $total_courses; ?></div>
            <div class="note">Course materials available for teaching</div>
        </div>
        <div class="card">
            <h3>ምዝገባዎች</h3>
            <div class="value"><?php echo $total_registrations; ?></div>
            <div class="note">Student enrollments in this system</div>
        </div>
        <div class="card">
            <h3>ተማሪዎች</h3>
            <div class="value"><?php echo $total_students; ?></div>
            <div class="note">Active student accounts</div>
        </div>
        <div class="card">
            <h3>የጥያቄ ባንክ</h3>
            <div class="value"><?php echo $total_questions; ?></div>
            <div class="note">Ready for quizzes and exams</div>
        </div>
        <div class="card">
            <h3>ገቢ</h3>
            <div class="value"><?php echo number_format($total_revenue, 2); ?> ₮</div>
            <div class="note">Paid registrations</div>
        </div>
    </div>

    <div class="panel">
        <div class="list-card">
            <h2>ፈጣን እርምጃ</h2>
            <ul>
                <li>Review course content, lessons, and video uploads</li>
                <li>Track student registrations and progress</li>
                <li>Prepare quizzes and exam materials</li>
                <li>Use the instructor panel as your teaching workspace</li>
            </ul>
            <div class="actions">
                <a class="btn" href="admin_view_courses.php">View Courses</a>
                <a class="btn secondary" href="admin_questions.php">Manage Questions</a>
                <a class="btn" href="admin_courses.php">Create Course</a>
            </div>
        </div>

        <div class="list-card">
            <h2>የቅርብ ጊዜ ኮርሶች</h2>
            <table>
                <thead>
                    <tr>
                        <th>ኮርስ</th>
                        <th>ኢንስትራክተር</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_courses as $course): ?>
                        <tr>
                            <td><?php echo safe($course['course_name'] ?? '-'); ?></td>
                            <td><?php echo safe($course['instructor'] ?? 'ኢንስትራክተር'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="list-card" style="margin-top:18px;">
        <h2>የቅርብ ጊዜ ተማሪዎች</h2>
        <table>
            <thead>
                <tr>
                    <th>ስም</th>
                    <th>ኢሜይል</th>
                    <th>የተማሪ መለያ ቁጥር</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_students as $student): ?>
                    <tr>
                        <td><?php echo safe($student['name'] ?? '-'); ?></td>
                        <td><?php echo safe($student['email'] ?? '-'); ?></td>
                        <td><?php echo safe($student['student_id'] ?? '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
