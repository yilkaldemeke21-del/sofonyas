<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/management_schema.php';
require_once __DIR__ . '/admin_management_i18n.php';
require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/mail_config.php';
requireRole(['admin'], $pdo);

if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit(adminManagementText('db_unavailable'));
}

ensureManagementTables($pdo);
ensureAssignmentTables($pdo);
$csrf = csrfToken();
$errorKey = '';
$savedKey = [
    'class' => 'sunday_class_saved',
    'enrollment' => 'sunday_enrollment_saved',
    'attendance' => 'sunday_attendance_saved',
    'assessment' => 'sunday_assessment_saved',
    'lesson' => 'sunday_lesson_saved',
    'certificate' => 'sunday_certificate_issued',
    'notification' => 'sunday_notification_sent',
    'class_status' => 'sunday_class_updated',
    'completed' => 'ministry_status_updated',
][(string)($_GET['saved'] ?? '')] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $errorKey = 'csrf_error';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'create_class') {
                $className = trim((string)($_POST['class_name'] ?? ''));
                $level = trim((string)($_POST['education_level'] ?? ''));
                $ageMin = trim((string)($_POST['age_min'] ?? ''));
                $ageMax = trim((string)($_POST['age_max'] ?? ''));
                $teacherId = (int)($_POST['teacher_id'] ?? 0);
                $weekday = (int)($_POST['weekday'] ?? 0);
                $startTime = (string)($_POST['start_time'] ?? '');
                $endTime = (string)($_POST['end_time'] ?? '');
                $location = trim((string)($_POST['location'] ?? ''));
                $validAgeMin = $ageMin === '' || (ctype_digit($ageMin) && (int)$ageMin <= 100);
                $validAgeMax = $ageMax === '' || (ctype_digit($ageMax) && (int)$ageMax <= 100);
                $validSchedule = ($startTime === '' && $endTime === '') || ($startTime !== '' && $endTime !== '' && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $startTime) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $endTime) && $endTime > $startTime);
                $validTeacher = true;
                if ($teacherId > 0) {
                    $teacherStmt = $pdo->prepare("SELECT id FROM admin_users WHERE id = :id AND LOWER(role) IN ('instructor', 'teacher') LIMIT 1");
                    $teacherStmt->execute([':id' => $teacherId]);
                    $validTeacher = (bool)$teacherStmt->fetchColumn();
                }
                if ($className === '' || strlen($className) > 160 || !$validAgeMin || !$validAgeMax || ($ageMin !== '' && $ageMax !== '' && (int)$ageMin > (int)$ageMax) || !$validSchedule || ($weekday !== 0 && ($weekday < 1 || $weekday > 7)) || !$validTeacher) {
                    $errorKey = 'sunday_invalid_entry';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO sunday_school_classes (class_name, education_level, age_min, age_max, teacher_id, weekday, start_time, end_time, location, created_by) VALUES (:class_name, :education_level, :age_min, :age_max, :teacher_id, :weekday, :start_time, :end_time, :location, :created_by)');
                    $stmt->execute([
                        ':class_name' => $className,
                        ':education_level' => $level !== '' ? $level : null,
                        ':age_min' => $ageMin !== '' ? (int)$ageMin : null,
                        ':age_max' => $ageMax !== '' ? (int)$ageMax : null,
                        ':teacher_id' => $teacherId > 0 ? $teacherId : null,
                        ':weekday' => $weekday > 0 ? $weekday : null,
                        ':start_time' => $startTime !== '' ? $startTime . ':00' : null,
                        ':end_time' => $endTime !== '' ? $endTime . ':00' : null,
                        ':location' => $location !== '' ? $location : null,
                        ':created_by' => (int)$_SESSION['admin_id'],
                    ]);
                    header('Location: admin_sunday_school_classes.php?lang=' . urlencode($adminManagementLanguage) . '&saved=class');
                    exit;
                }
            } elseif ($action === 'enroll_student') {
                $classId = (int)($_POST['class_id'] ?? 0);
                $memberId = (int)($_POST['member_id'] ?? 0);
                $studentId = trim((string)($_POST['student_id'] ?? ''));
                $guardianName = trim((string)($_POST['guardian_name'] ?? ''));
                $guardianPhone = trim((string)($_POST['guardian_phone'] ?? ''));
                $guardianEmail = strtolower(trim((string)($_POST['guardian_email'] ?? '')));
                $classStmt = $pdo->prepare("SELECT age_min, age_max FROM sunday_school_classes WHERE id = :id AND status = 'active' LIMIT 1");
                $classStmt->execute([':id' => $classId]);
                $class = $classStmt->fetch(PDO::FETCH_ASSOC);
                $memberStmt = $pdo->prepare('SELECT id, birth_date FROM church_members WHERE id = :id LIMIT 1');
                $memberStmt->execute([':id' => $memberId]);
                $member = $memberStmt->fetch(PDO::FETCH_ASSOC);
                $validStudentLogin = true;
                if ($studentId !== '') {
                    $loginStmt = $pdo->prepare('SELECT student_id FROM students WHERE student_id = :student_id LIMIT 1');
                    $loginStmt->execute([':student_id' => $studentId]);
                    $validStudentLogin = (bool)$loginStmt->fetchColumn();
                }
                $age = !empty($member['birth_date']) ? (int)(new DateTime($member['birth_date']))->diff(new DateTime('today'))->y : null;
                $ageInRange = !$class || $age === null || (($class['age_min'] === null || $age >= (int)$class['age_min']) && ($class['age_max'] === null || $age <= (int)$class['age_max']));
                if (!$class || !$member || !$validStudentLogin || !$ageInRange || ($guardianEmail !== '' && !filter_var($guardianEmail, FILTER_VALIDATE_EMAIL))) {
                    $errorKey = 'sunday_invalid_entry';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO sunday_school_enrollments (class_id, member_id, student_id, guardian_name, guardian_phone, guardian_email, enrolled_on) VALUES (:class_id, :member_id, :student_id, :guardian_name, :guardian_phone, :guardian_email, CURDATE())');
                    $stmt->execute([
                        ':class_id' => $classId,
                        ':member_id' => $memberId,
                        ':student_id' => $studentId !== '' ? $studentId : null,
                        ':guardian_name' => $guardianName !== '' ? $guardianName : null,
                        ':guardian_phone' => $guardianPhone !== '' ? $guardianPhone : null,
                        ':guardian_email' => $guardianEmail !== '' ? $guardianEmail : null,
                    ]);
                    header('Location: admin_sunday_school_classes.php?lang=' . urlencode($adminManagementLanguage) . '&class_id=' . $classId . '&saved=enrollment');
                    exit;
                }
            } elseif ($action === 'record_attendance') {
                $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
                $attendanceDate = (string)($_POST['attendance_date'] ?? '');
                $status = (string)($_POST['attendance_status'] ?? '');
                $date = DateTime::createFromFormat('!Y-m-d', $attendanceDate);
                if ($enrollmentId < 1 || !$date || $date->format('Y-m-d') !== $attendanceDate || !in_array($status, ['present', 'absent', 'late', 'excused'], true)) {
                    $errorKey = 'sunday_invalid_entry';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO sunday_school_attendance (enrollment_id, attendance_date, attendance_status, notes, recorded_by) VALUES (:enrollment_id, :attendance_date, :attendance_status, :notes, :recorded_by) ON DUPLICATE KEY UPDATE attendance_status = VALUES(attendance_status), notes = VALUES(notes), recorded_by = VALUES(recorded_by)');
                    $stmt->execute([
                        ':enrollment_id' => $enrollmentId,
                        ':attendance_date' => $attendanceDate,
                        ':attendance_status' => $status,
                        ':notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
                        ':recorded_by' => (int)$_SESSION['admin_id'],
                    ]);
                    header('Location: admin_sunday_school_classes.php?lang=' . urlencode($adminManagementLanguage) . '&class_id=' . (int)($_POST['class_id'] ?? 0) . '&date=' . urlencode($attendanceDate) . '&saved=attendance');
                    exit;
                }
            } elseif ($action === 'add_assessment') {
                $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
                $title = trim((string)($_POST['title'] ?? ''));
                $score = filter_var($_POST['score'] ?? null, FILTER_VALIDATE_FLOAT);
                $maxScore = filter_var($_POST['max_score'] ?? null, FILTER_VALIDATE_FLOAT);
                $assessedOn = (string)($_POST['assessed_on'] ?? '');
                $date = DateTime::createFromFormat('!Y-m-d', $assessedOn);
                if ($enrollmentId < 1 || $title === '' || $score === false || $maxScore === false || $score < 0 || $maxScore <= 0 || $score > $maxScore || !$date || $date->format('Y-m-d') !== $assessedOn) {
                    $errorKey = 'sunday_invalid_entry';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO sunday_school_assessments (enrollment_id, title, score, max_score, assessed_on, teacher_notes, recorded_by) VALUES (:enrollment_id, :title, :score, :max_score, :assessed_on, :teacher_notes, :recorded_by)');
                    $stmt->execute([
                        ':enrollment_id' => $enrollmentId,
                        ':title' => $title,
                        ':score' => $score,
                        ':max_score' => $maxScore,
                        ':assessed_on' => $assessedOn,
                        ':teacher_notes' => trim((string)($_POST['teacher_notes'] ?? '')) ?: null,
                        ':recorded_by' => (int)$_SESSION['admin_id'],
                    ]);
                    header('Location: admin_sunday_school_classes.php?lang=' . urlencode($adminManagementLanguage) . '&class_id=' . (int)($_POST['class_id'] ?? 0) . '&saved=assessment');
                    exit;
                }
            } elseif ($action === 'add_lesson') {
                $classId = (int)($_POST['class_id'] ?? 0);
                $title = trim((string)($_POST['title'] ?? ''));
                $notes = trim((string)($_POST['lesson_notes'] ?? ''));
                $resourceUrl = trim((string)($_POST['resource_url'] ?? ''));
                $resourceType = (string)($_POST['resource_type'] ?? 'none');
                $weekOf = (string)($_POST['week_of'] ?? '');
                $weekDate = $weekOf !== '' ? DateTime::createFromFormat('!Y-m-d', $weekOf) : null;
                if ($classId < 1 || $title === '' || !in_array($resourceType, ['none', 'video', 'audio', 'pdf', 'link'], true) || ($resourceUrl !== '' && !filter_var($resourceUrl, FILTER_VALIDATE_URL)) || ($weekOf !== '' && (!$weekDate || $weekDate->format('Y-m-d') !== $weekOf))) {
                    $errorKey = 'sunday_invalid_entry';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO sunday_school_lessons (class_id, title, lesson_notes, resource_url, resource_type, week_of, is_published, created_by) VALUES (:class_id, :title, :lesson_notes, :resource_url, :resource_type, :week_of, :is_published, :created_by)');
                    $stmt->execute([
                        ':class_id' => $classId,
                        ':title' => $title,
                        ':lesson_notes' => $notes !== '' ? $notes : null,
                        ':resource_url' => $resourceUrl !== '' ? $resourceUrl : null,
                        ':resource_type' => $resourceType,
                        ':week_of' => $weekOf !== '' ? $weekOf : null,
                        ':is_published' => isset($_POST['is_published']) ? 1 : 0,
                        ':created_by' => (int)$_SESSION['admin_id'],
                    ]);
                    header('Location: admin_sunday_school_classes.php?lang=' . urlencode($adminManagementLanguage) . '&class_id=' . $classId . '&saved=lesson');
                    exit;
                }
            } elseif ($action === 'complete_enrollment') {
                $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
                if ($enrollmentId > 0) {
                    $stmt = $pdo->prepare("UPDATE sunday_school_enrollments SET status = 'completed' WHERE id = :id");
                    $stmt->execute([':id' => $enrollmentId]);
                    header('Location: admin_sunday_school_classes.php?lang=' . urlencode($adminManagementLanguage) . '&class_id=' . (int)($_POST['class_id'] ?? 0) . '&saved=completed');
                    exit;
                }
            } elseif ($action === 'issue_certificate') {
                $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
                $check = $pdo->prepare("SELECT id FROM sunday_school_enrollments WHERE id = :id AND status = 'completed' LIMIT 1");
                $check->execute([':id' => $enrollmentId]);
                if (!$check->fetchColumn()) {
                    $errorKey = 'sunday_invalid_entry';
                } else {
                    $certificateCode = 'SS-' . strtoupper(bin2hex(random_bytes(6)));
                    $stmt = $pdo->prepare('INSERT IGNORE INTO sunday_school_certificates (enrollment_id, certificate_code, issued_by) VALUES (:enrollment_id, :certificate_code, :issued_by)');
                    $stmt->execute([':enrollment_id' => $enrollmentId, ':certificate_code' => $certificateCode, ':issued_by' => (int)$_SESSION['admin_id']]);
                    header('Location: admin_sunday_school_classes.php?lang=' . urlencode($adminManagementLanguage) . '&class_id=' . (int)($_POST['class_id'] ?? 0) . '&saved=certificate');
                    exit;
                }
            } elseif ($action === 'notify_guardian') {
                $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
                $stmt = $pdo->prepare('SELECT e.id, e.guardian_email, e.guardian_name, m.full_name, c.class_name FROM sunday_school_enrollments e JOIN church_members m ON m.id = e.member_id JOIN sunday_school_classes c ON c.id = e.class_id WHERE e.id = :id LIMIT 1');
                $stmt->execute([':id' => $enrollmentId]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$student || !filter_var((string)$student['guardian_email'], FILTER_VALIDATE_EMAIL)) {
                    $errorKey = 'email_validation_error';
                } else {
                    $summaryStmt = $pdo->prepare("SELECT COUNT(*) AS assessments, COALESCE(AVG(score / max_score * 100), 0) AS average_score FROM sunday_school_assessments WHERE enrollment_id = :enrollment_id");
                    $summaryStmt->execute([':enrollment_id' => $enrollmentId]);
                    $progress = $summaryStmt->fetch(PDO::FETCH_ASSOC);
                    $mailSubject = adminManagementText('sunday_progress') . ': ' . $student['full_name'];
                    $mailBody = '<p>' . htmlspecialchars(adminManagementText('sunday_progress'), ENT_QUOTES, 'UTF-8') . '</p>'
                        . '<p>' . htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8') . ' · ' . htmlspecialchars($student['class_name'], ENT_QUOTES, 'UTF-8') . '</p>'
                        . '<p>' . htmlspecialchars(adminManagementText('sunday_assessments'), ENT_QUOTES, 'UTF-8') . ': ' . (int)$progress['assessments'] . '<br>'
                        . htmlspecialchars(adminManagementText('sunday_average_score'), ENT_QUOTES, 'UTF-8') . ': ' . number_format((float)$progress['average_score'], 1) . '%</p>';
                    $sent = sendAppEmail((string)$student['guardian_email'], $mailSubject, $mailBody);
                    $logStmt = $pdo->prepare('INSERT INTO sunday_school_notifications (enrollment_id, recipient_email, subject, notification_type, delivery_status) VALUES (:enrollment_id, :recipient_email, :subject, :notification_type, :delivery_status)');
                    $logStmt->execute([
                        ':enrollment_id' => $enrollmentId,
                        ':recipient_email' => $student['guardian_email'],
                        ':subject' => $mailSubject,
                        ':notification_type' => 'progress',
                        ':delivery_status' => $sent ? 'sent' : 'failed',
                    ]);
                    if (!$sent) {
                        $errorKey = 'sunday_notification_failed';
                    } else {
                        header('Location: admin_sunday_school_classes.php?lang=' . urlencode($adminManagementLanguage) . '&class_id=' . (int)($_POST['class_id'] ?? 0) . '&saved=notification');
                        exit;
                    }
                }
            }
        } catch (PDOException $exception) {
            error_log('Sunday School admin action failed: ' . $exception->getMessage());
            $errorKey = $exception->getCode() === '23000' ? 'sunday_invalid_entry' : 'education_save_failed';
        }
    }
}

$classes = $pdo->query('SELECT c.*, au.username AS teacher_name FROM sunday_school_classes c LEFT JOIN admin_users au ON au.id = c.teacher_id ORDER BY c.status ASC, c.class_name ASC')->fetchAll(PDO::FETCH_ASSOC);
$classId = (int)($_GET['class_id'] ?? ($classes[0]['id'] ?? 0));
$classInfo = null;
foreach ($classes as $class) {
    if ((int)$class['id'] === $classId) {
        $classInfo = $class;
        break;
    }
}
$teachers = $pdo->query("SELECT id, username FROM admin_users WHERE LOWER(role) IN ('instructor', 'teacher') ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
$members = $pdo->query("SELECT m.id, m.member_number, m.full_name, m.birth_date, m.status, m.family_id, s.student_id AS linked_student_id FROM church_members m LEFT JOIN students s ON s.email = m.email WHERE m.status = 'active' ORDER BY m.full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$studentAccounts = $pdo->query('SELECT student_id, name, email FROM students ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
$attendanceDate = (string)($_GET['date'] ?? date('Y-m-d'));
$parsedAttendanceDate = DateTime::createFromFormat('!Y-m-d', $attendanceDate);
if (!$parsedAttendanceDate || $parsedAttendanceDate->format('Y-m-d') !== $attendanceDate) {
    $attendanceDate = date('Y-m-d');
}
$enrollments = [];
$lessons = [];
$assessmentRows = [];
if ($classInfo) {
    $enrollmentStmt = $pdo->prepare("SELECT e.*, m.member_number, m.full_name, m.birth_date, TIMESTAMPDIFF(YEAR, m.birth_date, CURDATE()) AS age, a.attendance_status AS day_status, a.notes AS attendance_notes, COALESCE(scores.average_score, 0) AS average_score, COALESCE(scores.assessment_count, 0) AS assessment_count, COALESCE(attendance_stats.attendance_rate, 0) AS attendance_rate, cert.id AS certificate_id, cert.certificate_code FROM sunday_school_enrollments e JOIN church_members m ON m.id = e.member_id LEFT JOIN sunday_school_attendance a ON a.enrollment_id = e.id AND a.attendance_date = :attendance_date LEFT JOIN (SELECT enrollment_id, AVG(score / max_score * 100) AS average_score, COUNT(*) AS assessment_count FROM sunday_school_assessments GROUP BY enrollment_id) scores ON scores.enrollment_id = e.id LEFT JOIN (SELECT enrollment_id, 100 * SUM(CASE WHEN attendance_status = 'present' THEN 1 ELSE 0 END) / COUNT(*) AS attendance_rate FROM sunday_school_attendance GROUP BY enrollment_id) attendance_stats ON attendance_stats.enrollment_id = e.id LEFT JOIN sunday_school_certificates cert ON cert.enrollment_id = e.id WHERE e.class_id = :class_id ORDER BY m.full_name ASC");
    $enrollmentStmt->execute([':attendance_date' => $attendanceDate, ':class_id' => $classId]);
    $enrollments = $enrollmentStmt->fetchAll(PDO::FETCH_ASSOC);
    $lessonStmt = $pdo->prepare('SELECT * FROM sunday_school_lessons WHERE class_id = :class_id ORDER BY week_of DESC, created_at DESC LIMIT 50');
    $lessonStmt->execute([':class_id' => $classId]);
    $lessons = $lessonStmt->fetchAll(PDO::FETCH_ASSOC);
    $assessmentStmt = $pdo->prepare('SELECT a.*, m.full_name FROM sunday_school_assessments a JOIN sunday_school_enrollments e ON e.id = a.enrollment_id JOIN church_members m ON m.id = e.member_id WHERE e.class_id = :class_id ORDER BY a.assessed_on DESC, a.id DESC LIMIT 50');
    $assessmentStmt->execute([':class_id' => $classId]);
    $assessmentRows = $assessmentStmt->fetchAll(PDO::FETCH_ASSOC);
}
$classTypeDays = [1 => 'education_monday', 2 => 'education_tuesday', 3 => 'education_wednesday', 4 => 'education_thursday', 5 => 'education_friday', 6 => 'education_saturday', 7 => 'education_sunday'];
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe(adminManagementText('sunday_dashboard')); ?></title>
    <style>
        :root{font-family:Arial,sans-serif;color:#202b2b;background:#f4f7f5;font-synthesis:none}*{box-sizing:border-box}body{margin:0}.topbar{background:#143b36;color:#fff;padding:16px max(20px,calc((100% - 1260px)/2));display:flex;justify-content:space-between;align-items:center;gap:14px}.topbar h1{font-size:20px;margin:0}.topbar p{margin:4px 0 0;color:#c4d5ce;font-size:13px}.nav{display:flex;gap:8px;flex-wrap:wrap}.nav a{color:#fff;text-decoration:none;border:1px solid #739188;border-radius:4px;padding:9px 12px}.wrap{max-width:1260px;margin:22px auto;padding:0 18px}.intro{display:flex;justify-content:space-between;align-items:end;gap:15px;margin-bottom:16px}.intro h2{font-size:21px;margin:0 0 5px}.muted{color:#65736e}.sections{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}.sections a{border:1px solid #ccd8d2;border-radius:4px;padding:8px 11px;color:#215e52;background:#fff;text-decoration:none;font-size:13px}.columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.section{background:#fff;border-top:2px solid #d7e2dc;padding:16px;margin-bottom:15px;min-width:0}.section h3{font-size:16px;margin:0 0 12px}.form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}.field{display:flex;flex-direction:column;gap:4px}.field.wide{grid-column:span 2}.field.full{grid-column:1/-1}label{font-size:12px;font-weight:600}input,select,textarea{width:100%;padding:8px;border:1px solid #cbd5d1;border-radius:4px;background:#fff;color:#202b2b;font:inherit;font-size:13px}textarea{min-height:54px}.submit{margin-top:10px;padding:8px 12px;border:0;border-radius:4px;background:#236c5e;color:#fff;font:inherit;font-size:13px;cursor:pointer}.notice{padding:10px 12px;background:#e8f4ef;color:#176244;margin-bottom:12px}.error{background:#fff0eb;color:#9b351f}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:12px}th,td{text-align:left;vertical-align:top;padding:8px 6px;border-bottom:1px solid #e5ebe7}th{background:#f3f7f4;white-space:nowrap}.inline{display:flex;gap:5px;align-items:center}.inline select{min-width:95px}.inline button{white-space:nowrap}.small{font-size:11px}.empty{color:#65736e;padding:10px 0}.actions{display:flex;gap:6px;flex-wrap:wrap}.actions button{padding:5px 7px;font-size:11px}.status-pill{font-size:11px;padding:3px 6px;background:#edf4ef;border-radius:3px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}.toolbar form{display:flex;gap:7px;align-items:center}.toolbar input{width:auto}.resource-type{font-size:11px;color:#65736e}@media(max-width:980px){.columns{grid-template-columns:1fr}.form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.topbar{align-items:flex-start;flex-direction:column}.intro{align-items:flex-start;flex-direction:column}.form-grid{grid-template-columns:1fr}.field.wide,.field.full{grid-column:auto}.toolbar form{width:100%;flex-wrap:wrap}}@media print{body{background:#fff}.topbar,.sections,.form-section,.no-print,.actions{display:none!important}.wrap{margin:0;max-width:none}.section{border:0;padding:0}}
    </style>
</head>
<body>
<header class="topbar"><div><h1><?php echo safe(adminManagementText('sunday_dashboard')); ?></h1><p><?php echo safe(adminManagementText('sunday_intro')); ?></p></div><nav class="nav"><a href="<?php echo safe($adminManagementSwitchUrl); ?>"><?php echo safe(adminManagementText('language_switch')); ?></a><a href="admin_sunday_school.php">← <?php echo safe(adminManagementText('ministry_title')); ?></a><a href="admin_education_dashboard.php"><?php echo safe(adminManagementText('education_dashboard_title')); ?></a></nav></header>
<main class="wrap">
    <?php if ($savedKey !== ''): ?><div class="notice"><?php echo safe(adminManagementText($savedKey)); ?></div><?php endif; ?>
    <?php if ($errorKey !== ''): ?><div class="notice error"><?php echo safe(adminManagementText($errorKey)); ?></div><?php endif; ?>
    <nav class="sections"><a href="#classes"><?php echo safe(adminManagementText('sunday_classes')); ?></a><a href="#enrollments"><?php echo safe(adminManagementText('sunday_enrolled_students')); ?></a><a href="#attendance"><?php echo safe(adminManagementText('sunday_attendance')); ?></a><a href="#assessments"><?php echo safe(adminManagementText('sunday_assessments')); ?></a><a href="#lessons"><?php echo safe(adminManagementText('sunday_lessons')); ?></a><a href="#progress"><?php echo safe(adminManagementText('sunday_progress')); ?></a></nav>
    <div class="columns">
        <section class="section form-section" id="classes">
            <h3><?php echo safe(adminManagementText('sunday_class_create')); ?></h3>
            <form method="post"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="create_class"><div class="form-grid">
                <div class="field wide"><label for="class_name"><?php echo safe(adminManagementText('sunday_class_name')); ?> *</label><input id="class_name" name="class_name" maxlength="160" required></div>
                <div class="field"><label for="education_level"><?php echo safe(adminManagementText('sunday_level')); ?></label><input id="education_level" name="education_level" maxlength="100"></div>
                <div class="field"><label for="age_min"><?php echo safe(adminManagementText('sunday_min_age')); ?></label><input id="age_min" name="age_min" type="number" min="0" max="100"></div>
                <div class="field"><label for="age_max"><?php echo safe(adminManagementText('sunday_max_age')); ?></label><input id="age_max" name="age_max" type="number" min="0" max="100"></div>
                <div class="field"><label for="teacher_id"><?php echo safe(adminManagementText('sunday_teacher')); ?></label><select id="teacher_id" name="teacher_id"><option value="0"><?php echo safe(adminManagementText('sunday_unassigned_teacher')); ?></option><?php foreach ($teachers as $teacher): ?><option value="<?php echo (int)$teacher['id']; ?>"><?php echo safe($teacher['username']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="weekday"><?php echo safe(adminManagementText('sunday_weekday')); ?></label><select id="weekday" name="weekday"><option value="0">-</option><?php foreach ($classTypeDays as $day => $key): ?><option value="<?php echo $day; ?>"><?php echo safe(adminManagementText($key)); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="start_time"><?php echo safe(adminManagementText('sunday_start_time')); ?></label><input id="start_time" name="start_time" type="time"></div>
                <div class="field"><label for="end_time"><?php echo safe(adminManagementText('sunday_end_time')); ?></label><input id="end_time" name="end_time" type="time"></div>
                <div class="field"><label for="location"><?php echo safe(adminManagementText('sunday_location')); ?></label><input id="location" name="location" maxlength="180"></div>
            </div><button class="submit" type="submit"><?php echo safe(adminManagementText('sunday_class_create')); ?></button></form>
        </section>
        <section class="section form-section" id="enrollments">
            <h3><?php echo safe(adminManagementText('sunday_enroll')); ?></h3>
            <form method="post"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="enroll_student"><div class="form-grid">
                <div class="field"><label for="enroll_class"><?php echo safe(adminManagementText('sunday_select_class')); ?> *</label><select id="enroll_class" name="class_id" required><option value=""><?php echo safe(adminManagementText('sunday_select_class')); ?></option><?php foreach ($classes as $class): if ($class['status'] !== 'active') continue; ?><option value="<?php echo (int)$class['id']; ?>"><?php echo safe($class['class_name']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="member_id"><?php echo safe(adminManagementText('sunday_select_member')); ?> *</label><select id="member_id" name="member_id" required><option value=""><?php echo safe(adminManagementText('sunday_select_member')); ?></option><?php foreach ($members as $member): ?><option value="<?php echo (int)$member['id']; ?>"><?php echo safe($member['member_number'] . ' · ' . $member['full_name']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="student_id"><?php echo safe(adminManagementText('sunday_student_login')); ?></label><select id="student_id" name="student_id"><option value=""><?php echo safe(adminManagementText('sunday_no_login')); ?></option><?php foreach ($studentAccounts as $studentAccount): ?><option value="<?php echo safe($studentAccount['student_id']); ?>"><?php echo safe($studentAccount['name'] . ' · ' . $studentAccount['student_id']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="guardian_name"><?php echo safe(adminManagementText('sunday_guardian_name')); ?></label><input id="guardian_name" name="guardian_name" maxlength="180"></div>
                <div class="field"><label for="guardian_phone"><?php echo safe(adminManagementText('sunday_guardian_phone')); ?></label><input id="guardian_phone" name="guardian_phone" maxlength="40"></div>
                <div class="field"><label for="guardian_email"><?php echo safe(adminManagementText('sunday_guardian_email')); ?></label><input id="guardian_email" name="guardian_email" type="email" maxlength="190"></div>
            </div><button class="submit" type="submit"><?php echo safe(adminManagementText('sunday_enroll')); ?></button></form>
        </section>
    </div>
    <section class="section">
        <div class="toolbar"><h3><?php echo safe(adminManagementText('sunday_classes')); ?></h3><form method="get"><input type="hidden" name="lang" value="<?php echo safe($adminManagementLanguage); ?>"><label for="class_id"><?php echo safe(adminManagementText('sunday_select_class')); ?></label><select id="class_id" name="class_id"><?php foreach ($classes as $class): ?><option value="<?php echo (int)$class['id']; ?>" <?php echo (int)$class['id'] === $classId ? 'selected' : ''; ?>><?php echo safe($class['class_name']); ?></option><?php endforeach; ?></select><button class="submit" style="margin:0" type="submit"><?php echo safe(adminManagementText('member_search_button')); ?></button></form></div>
        <?php if (!$classes): ?><div class="empty"><?php echo safe(adminManagementText('sunday_no_classes')); ?></div><?php else: ?><div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('sunday_class_name')); ?></th><th><?php echo safe(adminManagementText('sunday_level')); ?></th><th><?php echo safe(adminManagementText('member_age')); ?></th><th><?php echo safe(adminManagementText('sunday_teacher')); ?></th><th><?php echo safe(adminManagementText('sunday_weekday')); ?></th><th><?php echo safe(adminManagementText('sunday_start_time')); ?></th><th><?php echo safe(adminManagementText('sunday_location')); ?></th></tr></thead><tbody><?php foreach ($classes as $class): ?><tr><td><?php echo safe($class['class_name']); ?></td><td><?php echo safe($class['education_level'] ?? ''); ?></td><td><?php echo safe(($class['age_min'] ?? '—') . '–' . ($class['age_max'] ?? '—')); ?></td><td><?php echo safe($class['teacher_name'] ?? adminManagementText('sunday_unassigned_teacher')); ?></td><td><?php echo $class['weekday'] ? safe(adminManagementText($classTypeDays[(int)$class['weekday']] ?? 'education_sunday')) : '—'; ?></td><td><?php echo safe(substr((string)($class['start_time'] ?? ''), 0, 5)); ?><?php if ($class['end_time']): ?>–<?php echo safe(substr($class['end_time'], 0, 5)); ?><?php endif; ?></td><td><?php echo safe($class['location'] ?? ''); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
    <?php if ($classInfo): ?>
    <section class="section" id="attendance">
        <div class="toolbar"><h3><?php echo safe($classInfo['class_name']); ?> · <?php echo safe(adminManagementText('sunday_enrolled_students')); ?></h3><form method="get"><input type="hidden" name="lang" value="<?php echo safe($adminManagementLanguage); ?>"><input type="hidden" name="class_id" value="<?php echo $classId; ?>"><label for="attendance_date"><?php echo safe(adminManagementText('date')); ?></label><input id="attendance_date" type="date" name="date" value="<?php echo safe($attendanceDate); ?>"><button class="submit" style="margin:0" type="submit"><?php echo safe(adminManagementText('member_search_button')); ?></button><button class="print" type="button" onclick="window.print()"><?php echo safe(adminManagementText('print')); ?></button></form></div>
        <?php if (!$enrollments): ?><div class="empty"><?php echo safe(adminManagementText('sunday_no_students')); ?></div><?php else: ?><div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('full_name')); ?></th><th><?php echo safe(adminManagementText('member_number')); ?></th><th><?php echo safe(adminManagementText('member_age')); ?></th><th><?php echo safe(adminManagementText('date')); ?></th><th><?php echo safe(adminManagementText('sunday_attendance_status')); ?></th><th><?php echo safe(adminManagementText('sunday_attendance_rate')); ?></th><th><?php echo safe(adminManagementText('sunday_average_score')); ?></th><th class="no-print"><?php echo safe(adminManagementText('sunday_attendance')); ?></th></tr></thead><tbody><?php foreach ($enrollments as $enrollment): ?><tr><td><?php echo safe($enrollment['full_name']); ?><br><small class="muted"><?php echo safe($enrollment['guardian_name'] ?? ''); ?></small></td><td><?php echo safe($enrollment['member_number']); ?></td><td><?php echo $enrollment['age'] !== null ? (int)$enrollment['age'] : safe(adminManagementText('member_age_unknown')); ?></td><td><?php echo safe($attendanceDate); ?></td><td><?php echo $enrollment['day_status'] ? safe(adminManagementText(['present' => 'present', 'absent' => 'absent', 'late' => 'late', 'excused' => 'sunday_excused'][$enrollment['day_status']] ?? 'sunday_excused')) : '—'; ?></td><td><?php echo number_format((float)$enrollment['attendance_rate'], 1); ?>%</td><td><?php echo number_format((float)$enrollment['average_score'], 1); ?>%</td><td class="no-print"><form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="record_attendance"><input type="hidden" name="enrollment_id" value="<?php echo (int)$enrollment['id']; ?>"><input type="hidden" name="class_id" value="<?php echo $classId; ?>"><input type="hidden" name="attendance_date" value="<?php echo safe($attendanceDate); ?>"><select name="attendance_status"><option value="present"><?php echo safe(adminManagementText('present')); ?></option><option value="absent"><?php echo safe(adminManagementText('absent')); ?></option><option value="late"><?php echo safe(adminManagementText('late')); ?></option><option value="excused"><?php echo safe(adminManagementText('sunday_excused')); ?></option></select><button class="submit" style="margin:0" type="submit"><?php echo safe(adminManagementText('save_attendance')); ?></button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
    <div class="columns">
        <section class="section form-section" id="assessments">
            <h3><?php echo safe(adminManagementText('sunday_assessments')); ?></h3>
            <form method="post"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="add_assessment"><input type="hidden" name="class_id" value="<?php echo $classId; ?>"><div class="form-grid"><div class="field full"><label for="assessment_enrollment"><?php echo safe(adminManagementText('sunday_select_member')); ?> *</label><select id="assessment_enrollment" name="enrollment_id" required><?php foreach ($enrollments as $enrollment): ?><option value="<?php echo (int)$enrollment['id']; ?>"><?php echo safe($enrollment['full_name']); ?></option><?php endforeach; ?></select></div><div class="field"><label for="assessment_title"><?php echo safe(adminManagementText('sunday_assessment_title')); ?> *</label><input id="assessment_title" name="title" maxlength="180" required></div><div class="field"><label for="score"><?php echo safe(adminManagementText('sunday_score')); ?> *</label><input id="score" type="number" name="score" min="0" step="0.01" required></div><div class="field"><label for="max_score"><?php echo safe(adminManagementText('sunday_max_score')); ?> *</label><input id="max_score" type="number" name="max_score" min="0.01" step="0.01" value="100" required></div><div class="field"><label for="assessed_on"><?php echo safe(adminManagementText('date')); ?> *</label><input id="assessed_on" type="date" name="assessed_on" value="<?php echo date('Y-m-d'); ?>" required></div><div class="field full"><label for="teacher_notes"><?php echo safe(adminManagementText('notes')); ?></label><input id="teacher_notes" name="teacher_notes" maxlength="1000"></div></div><button class="submit" type="submit"><?php echo safe(adminManagementText('sunday_assessments')); ?></button></form>
        </section>
        <section class="section form-section" id="lessons">
            <h3><?php echo safe(adminManagementText('sunday_lessons')); ?></h3>
            <form method="post"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="add_lesson"><input type="hidden" name="class_id" value="<?php echo $classId; ?>"><div class="form-grid"><div class="field full"><label for="lesson_title"><?php echo safe(adminManagementText('sunday_lesson_title')); ?> *</label><input id="lesson_title" name="title" maxlength="180" required></div><div class="field full"><label for="lesson_notes"><?php echo safe(adminManagementText('sunday_lesson_notes')); ?></label><textarea id="lesson_notes" name="lesson_notes"></textarea></div><div class="field"><label for="resource_type"><?php echo safe(adminManagementText('sunday_resource_type')); ?></label><select id="resource_type" name="resource_type"><option value="none">—</option><?php foreach (['video', 'audio', 'pdf', 'link'] as $resourceType): ?><option value="<?php echo safe($resourceType); ?>"><?php echo safe(ucfirst($resourceType)); ?></option><?php endforeach; ?></select></div><div class="field"><label for="resource_url"><?php echo safe(adminManagementText('sunday_resource_url')); ?></label><input id="resource_url" type="url" name="resource_url" maxlength="500"></div><div class="field"><label for="week_of"><?php echo safe(adminManagementText('sunday_weekday')); ?></label><input id="week_of" type="date" name="week_of"></div><label class="field check"><input type="checkbox" name="is_published" value="1"> <?php echo safe(adminManagementText('sunday_publish_lesson')); ?></label></div><button class="submit" type="submit"><?php echo safe(adminManagementText('sunday_lessons')); ?></button></form>
        </section>
    </div>
    <section class="section" id="progress">
        <h3><?php echo safe(adminManagementText('sunday_progress')); ?></h3>
        <?php if (!$enrollments): ?><div class="empty"><?php echo safe(adminManagementText('sunday_no_students')); ?></div><?php else: ?><div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('full_name')); ?></th><th><?php echo safe(adminManagementText('member_number')); ?></th><th><?php echo safe(adminManagementText('sunday_average_score')); ?></th><th><?php echo safe(adminManagementText('sunday_assessments')); ?></th><th><?php echo safe(adminManagementText('sunday_attendance_rate')); ?></th><th><?php echo safe(adminManagementText('sunday_guardian_email')); ?></th><th class="no-print"><?php echo safe(adminManagementText('action')); ?></th></tr></thead><tbody><?php foreach ($enrollments as $enrollment): ?><tr><td><?php echo safe($enrollment['full_name']); ?></td><td><?php echo safe($enrollment['member_number']); ?></td><td><?php echo number_format((float)$enrollment['average_score'], 1); ?>%</td><td><?php echo (int)$enrollment['assessment_count']; ?></td><td>—</td><td><?php echo safe($enrollment['guardian_email'] ?? ''); ?></td><td class="no-print"><div class="actions"><form method="post"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="notify_guardian"><input type="hidden" name="enrollment_id" value="<?php echo (int)$enrollment['id']; ?>"><input type="hidden" name="class_id" value="<?php echo $classId; ?>"><button class="submit" type="submit"><?php echo safe(adminManagementText('sunday_notify_guardian')); ?></button></form><?php if ($enrollment['status'] === 'active'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="complete_enrollment"><input type="hidden" name="enrollment_id" value="<?php echo (int)$enrollment['id']; ?>"><input type="hidden" name="class_id" value="<?php echo $classId; ?>"><button class="submit" type="submit"><?php echo safe(adminManagementText('sunday_completed')); ?></button></form><?php endif; ?><?php if ($enrollment['certificate_id']): ?><a href="sunday_school_certificate.php?id=<?php echo (int)$enrollment['certificate_id']; ?>"><?php echo safe(adminManagementText('sunday_certificate_print')); ?></a><?php else: ?><form method="post"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="issue_certificate"><input type="hidden" name="enrollment_id" value="<?php echo (int)$enrollment['id']; ?>"><input type="hidden" name="class_id" value="<?php echo $classId; ?>"><button class="submit" type="submit" <?php echo $enrollment['status'] !== 'completed' ? 'disabled' : ''; ?>><?php echo safe(adminManagementText('sunday_issue_certificate')); ?></button></form><?php endif; ?></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        <h3 style="margin-top:18px"><?php echo safe(adminManagementText('sunday_assessments')); ?></h3>
        <?php if (!$assessmentRows): ?><div class="empty">—</div><?php else: ?><div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('full_name')); ?></th><th><?php echo safe(adminManagementText('sunday_assessment_title')); ?></th><th><?php echo safe(adminManagementText('sunday_score')); ?></th><th><?php echo safe(adminManagementText('date')); ?></th><th><?php echo safe(adminManagementText('notes')); ?></th></tr></thead><tbody><?php foreach ($assessmentRows as $assessment): ?><tr><td><?php echo safe($assessment['full_name']); ?></td><td><?php echo safe($assessment['title']); ?></td><td><?php echo number_format((float)$assessment['score'], 1); ?> / <?php echo number_format((float)$assessment['max_score'], 1); ?></td><td><?php echo safe($assessment['assessed_on']); ?></td><td><?php echo safe($assessment['teacher_notes'] ?? ''); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        <h3 style="margin-top:18px"><?php echo safe(adminManagementText('sunday_lessons')); ?></h3>
        <?php if (!$lessons): ?><div class="empty">—</div><?php else: ?><div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('sunday_lesson_title')); ?></th><th><?php echo safe(adminManagementText('sunday_weekday')); ?></th><th><?php echo safe(adminManagementText('sunday_publish_lesson')); ?></th><th><?php echo safe(adminManagementText('sunday_resource_type')); ?></th><th><?php echo safe(adminManagementText('sunday_resource_url')); ?></th></tr></thead><tbody><?php foreach ($lessons as $lesson): ?><tr><td><?php echo safe($lesson['title']); ?><br><small class="muted"><?php echo safe($lesson['lesson_notes'] ?? ''); ?></small></td><td><?php echo safe($lesson['week_of'] ?? ''); ?></td><td><?php echo safe(adminManagementText($lesson['is_published'] ? 'member_active' : 'member_inactive')); ?></td><td><?php echo safe($lesson['resource_type']); ?></td><td><?php if ($lesson['resource_url']): ?><a href="<?php echo safe($lesson['resource_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo safe(adminManagementText('education_materials')); ?></a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
    <?php else: ?>
        <section class="section" id="attendance"><h3><?php echo safe(adminManagementText('sunday_attendance')); ?></h3><div class="empty"><?php echo safe(adminManagementText('sunday_select_or_create_class')); ?></div></section>
        <section class="section" id="assessments"><h3><?php echo safe(adminManagementText('sunday_assessments')); ?></h3><div class="empty"><?php echo safe(adminManagementText('sunday_select_or_create_class')); ?></div></section>
        <section class="section" id="lessons"><h3><?php echo safe(adminManagementText('sunday_lessons')); ?></h3><div class="empty"><?php echo safe(adminManagementText('sunday_select_or_create_class')); ?></div></section>
        <section class="section" id="progress"><h3><?php echo safe(adminManagementText('sunday_progress')); ?></h3><div class="empty"><?php echo safe(adminManagementText('sunday_select_or_create_class')); ?></div></section>
    <?php endif; ?>
</main>
</body>
</html>