<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/management_schema.php';
require_once __DIR__ . '/admin_management_i18n.php';
requireRole(['admin'], $pdo);

if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit(adminManagementText('db_unavailable'));
}

ensureManagementTables($pdo);
ensureCourseStructureTables($pdo);
ensureLessonProgressTable($pdo);
ensureCourseQuizTables($pdo);
ensureAssignmentTables($pdo);
$pdo->exec('CREATE TABLE IF NOT EXISTS exam_submissions (id INT AUTO_INCREMENT PRIMARY KEY, student_id VARCHAR(100) NOT NULL, student_name VARCHAR(255) NOT NULL, exam_type VARCHAR(50) NOT NULL, score INT NOT NULL DEFAULT 0, total_questions INT NOT NULL DEFAULT 0, answers JSON DEFAULT NULL, submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$pdo->exec('CREATE TABLE IF NOT EXISTS certificates (id INT AUTO_INCREMENT PRIMARY KEY, student_id VARCHAR(100) NOT NULL, student_name VARCHAR(255) NOT NULL, exam_type VARCHAR(50) NOT NULL, score INT NOT NULL DEFAULT 0, total_questions INT NOT NULL DEFAULT 0, course_name VARCHAR(255) DEFAULT NULL, issued_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_certificate_student_exam (student_id, exam_type)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$csrf = csrfToken();
$errorKey = '';
$savedKey = [
    'teacher' => 'education_teacher_created',
    'schedule' => 'education_schedule_saved',
    'status' => 'education_schedule_updated',
][(string)($_GET['saved'] ?? '')] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $errorKey = 'csrf_error';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'create_teacher') {
                $username = trim((string)($_POST['username'] ?? ''));
                $email = strtolower(trim((string)($_POST['email'] ?? '')));
                $password = (string)($_POST['password'] ?? '');
                if (!preg_match('/^[A-Za-z0-9._-]{3,100}$/', $username) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errorKey = 'education_account_invalid';
                } elseif (strlen($password) < 10) {
                    $errorKey = 'education_password_short';
                } else {
                    $duplicate = $pdo->prepare('SELECT id FROM admin_users WHERE username = :username OR email = :email LIMIT 1');
                    $duplicate->execute([':username' => $username, ':email' => $email]);
                    if ($duplicate->fetchColumn()) {
                        $errorKey = 'education_account_invalid';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, email, role) VALUES (:username, :password_hash, :email, 'Instructor')");
                        $stmt->execute([
                            ':username' => $username,
                            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                            ':email' => $email,
                        ]);
                        header('Location: admin_education_dashboard.php?lang=' . urlencode($adminManagementLanguage) . '&saved=teacher');
                        exit;
                    }
                }
            } elseif ($action === 'add_schedule') {
                $courseId = (int)($_POST['course_id'] ?? 0);
                $teacherId = (int)($_POST['teacher_id'] ?? 0);
                $weekday = (int)($_POST['weekday'] ?? 0);
                $startTime = (string)($_POST['start_time'] ?? '');
                $endTime = (string)($_POST['end_time'] ?? '');
                $location = trim((string)($_POST['location'] ?? ''));
                $startDate = DateTime::createFromFormat('!H:i', $startTime);
                $endDate = DateTime::createFromFormat('!H:i', $endTime);

                $courseStmt = $pdo->prepare('SELECT course_name FROM courses WHERE id = :id LIMIT 1');
                $courseStmt->execute([':id' => $courseId]);
                $courseName = $courseStmt->fetchColumn();
                $teacherName = null;
                if ($teacherId > 0) {
                    $teacherStmt = $pdo->prepare("SELECT username FROM admin_users WHERE id = :id AND LOWER(role) IN ('instructor', 'teacher') LIMIT 1");
                    $teacherStmt->execute([':id' => $teacherId]);
                    $teacherName = $teacherStmt->fetchColumn();
                }

                if (!$courseName || $weekday < 1 || $weekday > 7 || !$startDate || !$endDate || $startDate->format('H:i') !== $startTime || $endDate->format('H:i') !== $endTime || $endTime <= $startTime || ($teacherId > 0 && $teacherName === false)) {
                    $errorKey = 'education_schedule_invalid';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO course_weekly_schedule (course_id, course_name, teacher_id, weekday, start_time, end_time, location, created_by) VALUES (:course_id, :course_name, :teacher_id, :weekday, :start_time, :end_time, :location, :created_by)');
                    $stmt->execute([
                        ':course_id' => $courseId,
                        ':course_name' => $courseName,
                        ':teacher_id' => $teacherId > 0 ? $teacherId : null,
                        ':weekday' => $weekday,
                        ':start_time' => $startTime . ':00',
                        ':end_time' => $endTime . ':00',
                        ':location' => $location !== '' ? $location : null,
                        ':created_by' => (int)$_SESSION['admin_id'],
                    ]);
                    header('Location: admin_education_dashboard.php?lang=' . urlencode($adminManagementLanguage) . '&saved=schedule');
                    exit;
                }
            } elseif ($action === 'toggle_schedule') {
                $scheduleId = (int)($_POST['schedule_id'] ?? 0);
                if ($scheduleId > 0) {
                    $stmt = $pdo->prepare("UPDATE course_weekly_schedule SET status = IF(status = 'active', 'inactive', 'active') WHERE id = :id");
                    $stmt->execute([':id' => $scheduleId]);
                    header('Location: admin_education_dashboard.php?lang=' . urlencode($adminManagementLanguage) . '&saved=status');
                    exit;
                }
            }
        } catch (PDOException $exception) {
            error_log('Education dashboard save failed: ' . $exception->getMessage());
            $errorKey = 'education_save_failed';
        }
    }
}

$counts = [
    'courses' => (int)$pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn(),
    'students' => (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
    'teachers' => (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE LOWER(role) IN ('instructor', 'teacher')")->fetchColumn(),
    'enrollments' => (int)$pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn(),
    'exams' => (int)$pdo->query('SELECT COUNT(*) FROM exam_submissions')->fetchColumn(),
    'assignments' => (int)$pdo->query('SELECT COUNT(*) FROM assignments')->fetchColumn(),
    'pending_assignments' => (int)$pdo->query("SELECT COUNT(*) FROM assignments WHERE LOWER(status) IN ('pending', 'submitted')")->fetchColumn(),
    'lessons_completed' => (int)$pdo->query('SELECT COUNT(*) FROM lesson_progress')->fetchColumn(),
    'certificates' => (int)$pdo->query('SELECT COUNT(*) FROM certificates')->fetchColumn(),
    'materials' => (int)$pdo->query('SELECT COUNT(*) FROM course_lessons')->fetchColumn(),
];
$courses = $pdo->query('SELECT id, course_name, course_code FROM courses ORDER BY course_name ASC')->fetchAll(PDO::FETCH_ASSOC);
$teachers = $pdo->query("SELECT id, username FROM admin_users WHERE LOWER(role) IN ('instructor', 'teacher') ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
$schedule = $pdo->query('SELECT s.*, au.username AS teacher_name FROM course_weekly_schedule s LEFT JOIN admin_users au ON au.id = s.teacher_id ORDER BY s.weekday ASC, s.start_time ASC, s.id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
$recentCourses = $pdo->query('SELECT course_name, course_code, instructor, created_at FROM courses ORDER BY created_at DESC LIMIT 6')->fetchAll(PDO::FETCH_ASSOC);
$weekdays = [
    1 => 'education_monday',
    2 => 'education_tuesday',
    3 => 'education_wednesday',
    4 => 'education_thursday',
    5 => 'education_friday',
    6 => 'education_saturday',
    7 => 'education_sunday',
];
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe(adminManagementText('education_dashboard_title')); ?></title>
    <style>
        :root{font-family:Arial,sans-serif;color:#202b2b;background:#f4f7f5;font-synthesis:none}*{box-sizing:border-box}body{margin:0}.topbar{background:#143b36;color:#fff;padding:16px max(20px,calc((100% - 1200px)/2));display:flex;justify-content:space-between;align-items:center;gap:14px}.topbar h1{font-size:20px;margin:0}.topbar p{margin:4px 0 0;color:#c4d5ce;font-size:13px}.nav{display:flex;gap:8px;flex-wrap:wrap}.nav a{color:#fff;text-decoration:none;border:1px solid #739188;border-radius:4px;padding:9px 12px}.wrap{max-width:1200px;margin:24px auto;padding:0 18px}.section-title{margin:23px 0 12px;font-size:18px}.stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}.stat{background:#fff;padding:14px 16px;border-top:3px solid #236c5e;min-width:0}.stat span{display:block;color:#65736e;font-size:13px}.stat strong{display:block;font-size:25px;margin-top:6px}.stat.warm{border-top-color:#b6853b}.stat.cool{border-top-color:#607b8a}.quick{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px}.quick a{display:flex;justify-content:space-between;gap:8px;background:#fff;border:1px solid #dbe4df;padding:12px 14px;color:#215e52;text-decoration:none;font-weight:600}.quick a:hover{border-color:#236c5e}.columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.section{background:#fff;padding:18px;border-top:2px solid #d7e2dc;margin-bottom:18px;min-width:0}.section h3{font-size:17px;margin:0 0 14px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}.field{display:flex;flex-direction:column;gap:5px}.field.full{grid-column:1/-1}label{font-size:13px;font-weight:600}input,select{width:100%;padding:10px;border:1px solid #cbd5d1;border-radius:4px;background:#fff;color:#202b2b;font:inherit}.submit{margin-top:12px;padding:10px 14px;border:0;border-radius:4px;background:#236c5e;color:#fff;font:inherit;cursor:pointer}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;vertical-align:top;padding:10px 8px;border-bottom:1px solid #e5ebe7;white-space:nowrap}th{background:#f3f7f4;color:#46534e}.notice{padding:11px 13px;margin-bottom:14px;background:#e8f4ef;color:#176244}.error{background:#fff0eb;color:#9b351f}.muted{color:#65736e}.empty{padding:12px 2px;color:#65736e}.inline{display:inline}.toggle{border:0;padding:0;background:none;color:#236c5e;text-decoration:underline;cursor:pointer;font:inherit}@media(max-width:900px){.stats{grid-template-columns:repeat(3,minmax(0,1fr))}.quick{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:650px){.topbar{align-items:flex-start;flex-direction:column}.columns{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}}@media(max-width:380px){.quick{grid-template-columns:1fr}}
    </style>
</head>
<body>
<header class="topbar"><div><h1><?php echo safe(adminManagementText('education_dashboard_title')); ?></h1><p><?php echo safe(adminManagementText('education_dashboard_intro')); ?></p></div><nav class="nav"><a href="<?php echo safe($adminManagementSwitchUrl); ?>"><?php echo safe(adminManagementText('language_switch')); ?></a><a href="admin_dashboard.php">← <?php echo safe(adminManagementText('back_dashboard')); ?></a></nav></header>
<main class="wrap">
    <?php if ($savedKey !== ''): ?><div class="notice"><?php echo safe(adminManagementText($savedKey)); ?></div><?php endif; ?>
    <?php if ($errorKey !== ''): ?><div class="notice error"><?php echo safe(adminManagementText($errorKey)); ?></div><?php endif; ?>
    <h2 class="section-title" style="margin-top:0"><?php echo safe(adminManagementText('education_overview')); ?></h2>
    <section class="stats">
        <?php foreach ([['courses', 'education_courses'], ['students', 'education_students'], ['teachers', 'education_teachers'], ['enrollments', 'education_enrollments'], ['exams', 'education_exams'], ['assignments', 'education_assignments'], ['pending_assignments', 'education_pending_assignments'], ['lessons_completed', 'education_completed_lessons'], ['certificates', 'education_certificates'], ['materials', 'education_materials']] as [$key, $label]): ?>
            <div class="stat <?php echo in_array($key, ['pending_assignments', 'certificates'], true) ? 'warm' : (in_array($key, ['materials', 'lessons_completed'], true) ? 'cool' : ''); ?>"><span><?php echo safe(adminManagementText($label)); ?></span><strong><?php echo number_format($counts[$key]); ?></strong></div>
        <?php endforeach; ?>
    </section>
    <h2 class="section-title"><?php echo safe(adminManagementText('education_quick_actions')); ?></h2>
    <nav class="quick" aria-label="Education tools">
        <a href="admin_course_builder.php"><?php echo safe(adminManagementText('education_create_course')); ?><span>→</span></a>
        <a href="admin_students.php"><?php echo safe(adminManagementText('education_manage_students')); ?><span>→</span></a>
        <a href="admin_edit_student.php"><?php echo safe(adminManagementText('education_register_student')); ?><span>→</span></a>
        <a href="admin_exam_results.php"><?php echo safe(adminManagementText('education_grades')); ?><span>→</span></a>
        <a href="admin_add_question.php?view=sections"><?php echo safe(adminManagementText('education_exams')); ?><span>→</span></a>
        <a href="library.php"><?php echo safe(adminManagementText('education_material_library')); ?><span>→</span></a>
        <a href="admin_sunday_school_classes.php"><?php echo safe(adminManagementText('sunday_dashboard')); ?><span>→</span></a>
        <a href="admin_certificate.php"><?php echo safe(adminManagementText('education_certificates_manage')); ?><span>→</span></a>
        <a href="admin_registrations.php"><?php echo safe(adminManagementText('education_enrollments')); ?><span>→</span></a>
    </nav>
    <div class="columns" style="margin-top:18px">
        <section class="section">
            <h3><?php echo safe(adminManagementText('education_teacher_form')); ?></h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="create_teacher">
                <div class="form-grid">
                    <div class="field"><label for="username"><?php echo safe(adminManagementText('education_username')); ?> *</label><input id="username" name="username" maxlength="100" autocomplete="off" required></div>
                    <div class="field"><label for="email"><?php echo safe(adminManagementText('email')); ?> *</label><input id="email" name="email" type="email" maxlength="255" required></div>
                    <div class="field full"><label for="password"><?php echo safe(adminManagementText('education_password')); ?> *</label><input id="password" name="password" type="password" minlength="10" autocomplete="new-password" required></div>
                </div>
                <button class="submit" type="submit"><?php echo safe(adminManagementText('education_create_teacher')); ?></button>
            </form>
        </section>
        <section class="section">
            <h3><?php echo safe(adminManagementText('education_schedule_form')); ?></h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="add_schedule">
                <div class="form-grid">
                    <div class="field full"><label for="course_id"><?php echo safe(adminManagementText('education_course')); ?> *</label><select id="course_id" name="course_id" required><option value=""><?php echo safe(adminManagementText('education_select_course')); ?></option><?php foreach ($courses as $course): ?><option value="<?php echo (int)$course['id']; ?>"><?php echo safe($course['course_name']); ?> · <?php echo safe($course['course_code']); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="weekday"><?php echo safe(adminManagementText('education_day')); ?> *</label><select id="weekday" name="weekday" required><?php foreach ($weekdays as $day => $translation): ?><option value="<?php echo $day; ?>"><?php echo safe(adminManagementText($translation)); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="teacher_id"><?php echo safe(adminManagementText('education_teacher')); ?></label><select id="teacher_id" name="teacher_id"><option value="0"><?php echo safe(adminManagementText('education_no_teacher')); ?></option><?php foreach ($teachers as $teacher): ?><option value="<?php echo (int)$teacher['id']; ?>"><?php echo safe($teacher['username']); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="start_time"><?php echo safe(adminManagementText('education_start_time')); ?> *</label><input id="start_time" name="start_time" type="time" required></div>
                    <div class="field"><label for="end_time"><?php echo safe(adminManagementText('education_end_time')); ?> *</label><input id="end_time" name="end_time" type="time" required></div>
                    <div class="field full"><label for="location"><?php echo safe(adminManagementText('education_location')); ?></label><input id="location" name="location" maxlength="180"></div>
                </div>
                <button class="submit" type="submit"><?php echo safe(adminManagementText('education_add_schedule')); ?></button>
            </form>
        </section>
    </div>
    <section class="section">
        <div style="display:flex;justify-content:space-between;gap:10px;align-items:center"><h3><?php echo safe(adminManagementText('education_weekly_schedule')); ?></h3><span class="muted"><?php echo count($schedule); ?></span></div>
        <?php if (!$schedule): ?><div class="empty"><?php echo safe(adminManagementText('education_no_schedule')); ?></div><?php else: ?><div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('education_day')); ?></th><th><?php echo safe(adminManagementText('education_course')); ?></th><th><?php echo safe(adminManagementText('education_start_time')); ?></th><th><?php echo safe(adminManagementText('education_end_time')); ?></th><th><?php echo safe(adminManagementText('education_teacher')); ?></th><th><?php echo safe(adminManagementText('education_location')); ?></th><th><?php echo safe(adminManagementText('resource_status')); ?></th><th><?php echo safe(adminManagementText('action')); ?></th></tr></thead><tbody><?php foreach ($schedule as $item): ?><tr><td><?php echo safe(adminManagementText($weekdays[(int)$item['weekday']] ?? 'education_monday')); ?></td><td><?php echo safe($item['course_name']); ?></td><td><?php echo safe(substr($item['start_time'], 0, 5)); ?></td><td><?php echo safe(substr($item['end_time'], 0, 5)); ?></td><td><?php echo safe($item['teacher_name'] ?? adminManagementText('education_no_teacher')); ?></td><td><?php echo safe($item['location'] ?? ''); ?></td><td><?php echo safe(adminManagementText($item['status'] === 'active' ? 'resource_active' : 'resource_inactive')); ?></td><td><form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="toggle_schedule"><input type="hidden" name="schedule_id" value="<?php echo (int)$item['id']; ?>"><button class="toggle" type="submit"><?php echo safe(adminManagementText('education_toggle_status')); ?></button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
    <section class="section">
        <h3><?php echo safe(adminManagementText('education_courses')); ?></h3>
        <?php if (!$recentCourses): ?><div class="empty"><?php echo safe(adminManagementText('education_no_courses')); ?></div><?php else: ?><div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('education_course')); ?></th><th><?php echo safe(adminManagementText('education_teacher')); ?></th><th><?php echo safe(adminManagementText('date')); ?></th></tr></thead><tbody><?php foreach ($recentCourses as $course): ?><tr><td><?php echo safe($course['course_name']); ?> · <?php echo safe($course['course_code']); ?></td><td><?php echo safe($course['instructor'] ?? ''); ?></td><td><?php echo safe($course['created_at']); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
</main>
</body>
</html>