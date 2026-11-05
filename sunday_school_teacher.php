<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/management_schema.php';
require_once __DIR__ . '/admin_management_i18n.php';
requireRole(['admin', 'instructor', 'teacher'], $pdo);
ensureManagementTables($pdo);
$csrf = csrfToken();
$userRole = strtolower((string)getCurrentUserRole($pdo));
$isAdmin = $userRole === 'admin';
$teacherId = (int)($_SESSION['admin_id'] ?? 0);
$errorKey = '';
$savedKey = [
    'attendance' => 'sunday_attendance_saved',
    'assessment' => 'sunday_assessment_saved',
    'lesson' => 'sunday_lesson_saved',
][(string)($_GET['saved'] ?? '')] ?? '';

$classQuery = 'SELECT c.*, au.username AS teacher_name FROM sunday_school_classes c LEFT JOIN admin_users au ON au.id = c.teacher_id';
if (!$isAdmin) {
    $classQuery .= ' WHERE c.teacher_id = :teacher_id';
}
$classQuery .= ' ORDER BY c.class_name ASC';
$classStmt = $pdo->prepare($classQuery);
if (!$isAdmin) {
    $classStmt->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);
}
$classStmt->execute();
$classes = $classStmt->fetchAll(PDO::FETCH_ASSOC);
$classId = (int)($_GET['class_id'] ?? ($classes[0]['id'] ?? 0));
$class = null;
foreach ($classes as $candidate) {
    if ((int)$candidate['id'] === $classId) {
        $class = $candidate;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $errorKey = 'csrf_error';
    } elseif (!$class) {
        http_response_code(403);
        exit(adminManagementText('sunday_forbidden'));
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'record_attendance') {
                $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
                $attendanceDate = (string)($_POST['attendance_date'] ?? '');
                $attendanceStatus = (string)($_POST['attendance_status'] ?? '');
                $date = DateTime::createFromFormat('!Y-m-d', $attendanceDate);
                $enrollmentCheck = $pdo->prepare('SELECT id FROM sunday_school_enrollments WHERE id = :id AND class_id = :class_id LIMIT 1');
                $enrollmentCheck->execute([':id' => $enrollmentId, ':class_id' => $classId]);
                if (!$enrollmentCheck->fetchColumn() || !$date || $date->format('Y-m-d') !== $attendanceDate || !in_array($attendanceStatus, ['present', 'absent', 'late', 'excused'], true)) {
                    $errorKey = 'sunday_invalid_entry';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO sunday_school_attendance (enrollment_id, attendance_date, attendance_status, notes, recorded_by) VALUES (:enrollment_id, :attendance_date, :attendance_status, :notes, :recorded_by) ON DUPLICATE KEY UPDATE attendance_status = VALUES(attendance_status), notes = VALUES(notes), recorded_by = VALUES(recorded_by)');
                    $stmt->execute([
                        ':enrollment_id' => $enrollmentId,
                        ':attendance_date' => $attendanceDate,
                        ':attendance_status' => $attendanceStatus,
                        ':notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
                        ':recorded_by' => $teacherId,
                    ]);
                    header('Location: sunday_school_teacher.php?lang=' . urlencode($adminManagementLanguage) . '&class_id=' . $classId . '&date=' . urlencode($attendanceDate) . '&saved=attendance');
                    exit;
                }
            } elseif ($action === 'add_assessment') {
                $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
                $title = trim((string)($_POST['title'] ?? ''));
                $score = filter_var($_POST['score'] ?? null, FILTER_VALIDATE_FLOAT);
                $maxScore = filter_var($_POST['max_score'] ?? null, FILTER_VALIDATE_FLOAT);
                $dateInput = (string)($_POST['assessed_on'] ?? '');
                $date = DateTime::createFromFormat('!Y-m-d', $dateInput);
                $enrollmentCheck = $pdo->prepare('SELECT id FROM sunday_school_enrollments WHERE id = :id AND class_id = :class_id LIMIT 1');
                $enrollmentCheck->execute([':id' => $enrollmentId, ':class_id' => $classId]);
                if (!$enrollmentCheck->fetchColumn() || $title === '' || $score === false || $maxScore === false || $score < 0 || $maxScore <= 0 || $score > $maxScore || !$date || $date->format('Y-m-d') !== $dateInput) {
                    $errorKey = 'sunday_invalid_entry';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO sunday_school_assessments (enrollment_id, title, score, max_score, assessed_on, teacher_notes, recorded_by) VALUES (:enrollment_id, :title, :score, :max_score, :assessed_on, :teacher_notes, :recorded_by)');
                    $stmt->execute([
                        ':enrollment_id' => $enrollmentId,
                        ':title' => $title,
                        ':score' => $score,
                        ':max_score' => $maxScore,
                        ':assessed_on' => $dateInput,
                        ':teacher_notes' => trim((string)($_POST['teacher_notes'] ?? '')) ?: null,
                        ':recorded_by' => $teacherId,
                    ]);
                    header('Location: sunday_school_teacher.php?lang=' . urlencode($adminManagementLanguage) . '&class_id=' . $classId . '&saved=assessment');
                    exit;
                }
            } elseif ($action === 'add_lesson') {
                $title = trim((string)($_POST['title'] ?? ''));
                $notes = trim((string)($_POST['lesson_notes'] ?? ''));
                $resourceUrl = trim((string)($_POST['resource_url'] ?? ''));
                $resourceType = (string)($_POST['resource_type'] ?? 'none');
                $weekOf = (string)($_POST['week_of'] ?? '');
                $weekDate = $weekOf !== '' ? DateTime::createFromFormat('!Y-m-d', $weekOf) : null;
                if ($title === '' || !in_array($resourceType, ['none', 'video', 'audio', 'pdf', 'link'], true) || ($resourceUrl !== '' && !filter_var($resourceUrl, FILTER_VALIDATE_URL)) || ($weekOf !== '' && (!$weekDate || $weekDate->format('Y-m-d') !== $weekOf))) {
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
                        ':created_by' => $teacherId,
                    ]);
                    header('Location: sunday_school_teacher.php?lang=' . urlencode($adminManagementLanguage) . '&class_id=' . $classId . '&saved=lesson');
                    exit;
                }
            }
        } catch (PDOException $exception) {
            error_log('Sunday School teacher action failed: ' . $exception->getMessage());
            $errorKey = 'education_save_failed';
        }
    }
}

$attendanceDate = (string)($_GET['date'] ?? date('Y-m-d'));
$dateCheck = DateTime::createFromFormat('!Y-m-d', $attendanceDate);
if (!$dateCheck || $dateCheck->format('Y-m-d') !== $attendanceDate) {
    $attendanceDate = date('Y-m-d');
}
$enrollments = [];
$lessons = [];
$assessments = [];
if ($class) {
    $stmt = $pdo->prepare("SELECT e.id AS enrollment_id, e.student_id, m.full_name, m.member_number, m.birth_date, a.attendance_status, COALESCE(assessment_stats.average_score, 0) AS average_score FROM sunday_school_enrollments e JOIN church_members m ON m.id = e.member_id LEFT JOIN sunday_school_attendance a ON a.enrollment_id = e.id AND a.attendance_date = :attendance_date LEFT JOIN (SELECT enrollment_id, AVG(score / max_score * 100) AS average_score FROM sunday_school_assessments GROUP BY enrollment_id) assessment_stats ON assessment_stats.enrollment_id = e.id WHERE e.class_id = :class_id AND e.status = 'active' ORDER BY m.full_name");
    $stmt->execute([':attendance_date' => $attendanceDate, ':class_id' => $classId]);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare('SELECT * FROM sunday_school_lessons WHERE class_id = :class_id ORDER BY week_of DESC, created_at DESC LIMIT 30');
    $stmt->execute([':class_id' => $classId]);
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare('SELECT a.*, m.full_name FROM sunday_school_assessments a JOIN sunday_school_enrollments e ON e.id = a.enrollment_id JOIN church_members m ON m.id = e.member_id WHERE e.class_id = :class_id ORDER BY a.assessed_on DESC LIMIT 30');
    $stmt->execute([':class_id' => $classId]);
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?php echo safe(adminManagementText('sunday_teacher_portal')); ?></title><style>
:root{font-family:Arial,sans-serif;color:#202b2b;background:#f4f7f5;font-synthesis:none}*{box-sizing:border-box}body{margin:0}.topbar{background:#143b36;color:#fff;padding:16px max(20px,calc((100% - 1180px)/2));display:flex;justify-content:space-between;align-items:center;gap:12px}.topbar h1{margin:0;font-size:20px}.nav{display:flex;gap:8px;flex-wrap:wrap}.nav a{color:#fff;text-decoration:none;border:1px solid #739188;border-radius:4px;padding:9px 12px}.wrap{max-width:1180px;margin:22px auto;padding:0 18px}.section{background:#fff;border-top:2px solid #d7e2dc;padding:16px;margin-bottom:16px}.section h2,.section h3{font-size:17px;margin:0 0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}.toolbar form{display:flex;gap:8px;align-items:center}input,select,textarea{padding:8px;border:1px solid #cbd5d1;border-radius:4px;background:#fff;font:inherit;font-size:13px}.form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}.field{display:flex;flex-direction:column;gap:5px}.field.wide{grid-column:span 2}label{font-size:12px;font-weight:600}.submit{padding:8px 11px;background:#236c5e;color:#fff;border:0;border-radius:4px;cursor:pointer;font:inherit;font-size:13px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:12px}th,td{text-align:left;padding:8px 6px;border-bottom:1px solid #e5ebe7;vertical-align:top}th{background:#f3f7f4;white-space:nowrap}.inline{display:flex;gap:5px;align-items:center}.notice{padding:10px 12px;margin-bottom:12px;background:#e8f4ef;color:#176244}.error{background:#fff0eb;color:#9b351f}.muted,.empty{color:#65736e}.quick{display:flex;gap:8px;flex-wrap:wrap}.quick a{color:#236c5e;text-decoration:none;border:1px solid #dbe4df;background:#fff;padding:8px 10px;border-radius:4px}@media(max-width:760px){.topbar{align-items:flex-start;flex-direction:column}.form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:520px){.form-grid{grid-template-columns:1fr}.field.wide{grid-column:auto}}
</style></head>
<body>
<header class="topbar"><h1><?php echo safe(adminManagementText('sunday_teacher_portal')); ?> · <?php echo safe($_SESSION['admin_username'] ?? ''); ?></h1><nav class="nav"><a href="<?php echo safe($adminManagementSwitchUrl); ?>"><?php echo safe(adminManagementText('language_switch')); ?></a><?php if ($isAdmin): ?><a href="admin_sunday_school_classes.php"><?php echo safe(adminManagementText('sunday_dashboard')); ?></a><?php endif; ?><a href="admin_logout.php"><?php echo safe(adminManagementText('sunday_teacher_logout')); ?></a></nav></header>
<main class="wrap">
    <?php if ($savedKey !== ''): ?><div class="notice"><?php echo safe(adminManagementText($savedKey)); ?></div><?php endif; ?>
    <?php if ($errorKey !== ''): ?><div class="notice error"><?php echo safe(adminManagementText($errorKey)); ?></div><?php endif; ?>
    <?php if (!$classes): ?><section class="section"><div class="empty"><?php echo safe(adminManagementText('sunday_no_classes')); ?></div></section><?php else: ?>
    <section class="section toolbar"><div><h2><?php echo safe($class['class_name']); ?></h2><span class="muted"><?php echo safe($class['education_level'] ?? ''); ?> · <?php echo safe($class['location'] ?? ''); ?></span></div><form method="get"><input type="hidden" name="lang" value="<?php echo safe($adminManagementLanguage); ?>"><select name="class_id"><?php foreach ($classes as $item): ?><option value="<?php echo (int)$item['id']; ?>" <?php echo (int)$item['id'] === $classId ? 'selected' : ''; ?>><?php echo safe($item['class_name']); ?></option><?php endforeach; ?></select><button class="submit" type="submit"><?php echo safe(adminManagementText('view')); ?></button></form></section>
    <nav class="quick"><a href="#attendance"><?php echo safe(adminManagementText('sunday_attendance')); ?></a><a href="#lessons"><?php echo safe(adminManagementText('sunday_lessons')); ?></a><a href="#assessments"><?php echo safe(adminManagementText('sunday_assessments')); ?></a></nav>
    <section class="section" id="attendance"><div class="toolbar"><h3><?php echo safe(adminManagementText('sunday_attendance')); ?></h3><form method="get"><input type="hidden" name="lang" value="<?php echo safe($adminManagementLanguage); ?>"><input type="hidden" name="class_id" value="<?php echo $classId; ?>"><input name="date" type="date" value="<?php echo safe($attendanceDate); ?>"><button class="submit" type="submit"><?php echo safe(adminManagementText('view')); ?></button></form></div>
        <?php if (!$enrollments): ?><div class="empty"><?php echo safe(adminManagementText('sunday_no_students')); ?></div><?php else: ?><div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('full_name')); ?></th><th><?php echo safe(adminManagementText('member_number')); ?></th><th><?php echo safe(adminManagementText('sunday_attendance_status')); ?></th><th><?php echo safe(adminManagementText('sunday_average_score')); ?></th><th><?php echo safe(adminManagementText('sunday_attendance')); ?></th></tr></thead><tbody><?php foreach ($enrollments as $enrollment): ?><tr><td><?php echo safe($enrollment['full_name']); ?></td><td><?php echo safe($enrollment['member_number']); ?></td><td><?php echo $enrollment['attendance_status'] ? safe(adminManagementText(['present' => 'present', 'absent' => 'absent', 'late' => 'late', 'excused' => 'sunday_excused'][$enrollment['attendance_status']] ?? 'sunday_excused')) : '—'; ?></td><td><?php echo number_format((float)$enrollment['average_score'], 1); ?>%</td><td><form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="record_attendance"><input type="hidden" name="enrollment_id" value="<?php echo (int)$enrollment['enrollment_id']; ?>"><input type="hidden" name="attendance_date" value="<?php echo safe($attendanceDate); ?>"><select name="attendance_status"><option value="present"><?php echo safe(adminManagementText('present')); ?></option><option value="absent"><?php echo safe(adminManagementText('absent')); ?></option><option value="late"><?php echo safe(adminManagementText('late')); ?></option><option value="excused"><?php echo safe(adminManagementText('sunday_excused')); ?></option></select><button class="submit" type="submit"><?php echo safe(adminManagementText('save_attendance')); ?></button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
    <div class="columns">
        <section class="section" id="lessons"><h3><?php echo safe(adminManagementText('sunday_lessons')); ?></h3>
            <form method="post"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="add_lesson"><div class="form-grid"><div class="field"><label><?php echo safe(adminManagementText('sunday_lesson_title')); ?></label><input name="title" maxlength="180" required></div><div class="field"><label><?php echo safe(adminManagementText('sunday_weekday')); ?></label><input type="date" name="week_of"></div><div class="field"><label><?php echo safe(adminManagementText('sunday_resource_type')); ?></label><select name="resource_type"><option value="none">—</option><?php foreach (['video','audio','pdf','link'] as $resource): ?><option value="<?php echo $resource; ?>"><?php echo ucfirst($resource); ?></option><?php endforeach; ?></select></div><div class="field wide"><label><?php echo safe(adminManagementText('sunday_resource_url')); ?></label><input type="url" name="resource_url"></div><div class="field wide"><label><?php echo safe(adminManagementText('sunday_lesson_notes')); ?></label><textarea name="lesson_notes"></textarea></div><label class="field"><input type="checkbox" name="is_published" value="1"> <?php echo safe(adminManagementText('sunday_publish_lesson')); ?></label></div><button class="submit" type="submit"><?php echo safe(adminManagementText('sunday_lessons')); ?></button></form>
            <?php foreach ($lessons as $lesson): ?><article style="border-top:1px solid #e5ebe7;padding-top:8px;margin-top:10px"><strong><?php echo safe($lesson['title']); ?></strong> · <?php echo safe($lesson['week_of'] ?? ''); ?><p><?php echo safe($lesson['lesson_notes'] ?? ''); ?></p><?php if ($lesson['resource_url']): ?><a href="<?php echo safe($lesson['resource_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo safe(adminManagementText('education_materials')); ?></a><?php endif; ?></article><?php endforeach; ?>
        </section>
        <section class="section" id="assessments"><h3><?php echo safe(adminManagementText('sunday_assessments')); ?></h3>
            <form method="post"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="add_assessment"><div class="form-grid"><div class="field wide"><label><?php echo safe(adminManagementText('sunday_select_member')); ?></label><select name="enrollment_id" required><?php foreach ($enrollments as $enrollment): ?><option value="<?php echo (int)$enrollment['enrollment_id']; ?>"><?php echo safe($enrollment['full_name']); ?></option><?php endforeach; ?></select></div><div class="field"><label><?php echo safe(adminManagementText('sunday_assessment_title')); ?></label><input name="title" maxlength="180" required></div><div class="field"><label><?php echo safe(adminManagementText('sunday_score')); ?></label><input name="score" type="number" min="0" step="0.01" required></div><div class="field"><label><?php echo safe(adminManagementText('sunday_max_score')); ?></label><input name="max_score" type="number" min="0.01" step="0.01" value="100" required></div><div class="field"><label><?php echo safe(adminManagementText('date')); ?></label><input name="assessed_on" type="date" value="<?php echo date('Y-m-d'); ?>" required></div><div class="field wide"><label><?php echo safe(adminManagementText('notes')); ?></label><input name="teacher_notes" maxlength="1000"></div></div><button class="submit" type="submit"><?php echo safe(adminManagementText('sunday_assessment_saved')); ?></button></form>
            <?php foreach ($assessments as $assessment): ?><div style="border-top:1px solid #e5ebe7;padding-top:8px;margin-top:10px"><strong><?php echo safe($assessment['full_name']); ?></strong> · <?php echo safe($assessment['title']); ?><br><?php echo number_format((float)$assessment['score'], 1); ?> / <?php echo number_format((float)$assessment['max_score'], 1); ?> · <?php echo safe($assessment['assessed_on']); ?></div><?php endforeach; ?>
        </section>
    </div>
    <?php endif; ?>
</main>
</body>
</html>