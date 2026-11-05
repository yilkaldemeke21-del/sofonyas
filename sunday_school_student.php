<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/management_schema.php';
require_once __DIR__ . '/admin_management_i18n.php';
requireRole(['student'], $pdo);
ensureManagementTables($pdo);
$studentId = (string)$_SESSION['student_id'];
$studentStmt = $pdo->prepare('SELECT name FROM students WHERE student_id = :student_id LIMIT 1');
$studentStmt->execute([':student_id' => $studentId]);
$studentName = (string)($studentStmt->fetchColumn() ?: $_SESSION['student_name'] ?? '');
$enrollmentStmt = $pdo->prepare("SELECT e.id AS enrollment_id, e.class_id, e.enrolled_on, c.class_name, c.education_level, c.weekday, c.start_time, c.end_time, c.location, au.username AS teacher_name, COALESCE(scores.average_score, 0) AS average_score, COALESCE(scores.assessment_count, 0) AS assessment_count, COALESCE(attendance_stats.attendance_rate, 0) AS attendance_rate, cert.id AS certificate_id, cert.certificate_code FROM sunday_school_enrollments e JOIN sunday_school_classes c ON c.id = e.class_id LEFT JOIN admin_users au ON au.id = c.teacher_id LEFT JOIN (SELECT enrollment_id, AVG(score / max_score * 100) AS average_score, COUNT(*) AS assessment_count FROM sunday_school_assessments GROUP BY enrollment_id) scores ON scores.enrollment_id = e.id LEFT JOIN (SELECT enrollment_id, 100 * SUM(CASE WHEN attendance_status = 'present' THEN 1 ELSE 0 END) / COUNT(*) AS attendance_rate FROM sunday_school_attendance GROUP BY enrollment_id) attendance_stats ON attendance_stats.enrollment_id = e.id LEFT JOIN sunday_school_certificates cert ON cert.enrollment_id = e.id WHERE e.student_id = :student_id AND e.status IN ('active', 'completed') ORDER BY c.class_name");
$enrollmentStmt->execute([':student_id' => $studentId]);
$classes = $enrollmentStmt->fetchAll(PDO::FETCH_ASSOC);
$selectedEnrollmentId = (int)($_GET['enrollment_id'] ?? ($classes[0]['enrollment_id'] ?? 0));
$selected = null;
foreach ($classes as $class) {
    if ((int)$class['enrollment_id'] === $selectedEnrollmentId) {
        $selected = $class;
        break;
    }
}
$lessons = [];
$assessments = [];
$attendance = [];
if ($selected) {
    $lessonStmt = $pdo->prepare('SELECT title, lesson_notes, resource_url, resource_type, week_of FROM sunday_school_lessons WHERE class_id = :class_id AND is_published = 1 ORDER BY week_of DESC, created_at DESC LIMIT 50');
    $lessonStmt->execute([':class_id' => $selected['class_id']]);
    $lessons = $lessonStmt->fetchAll(PDO::FETCH_ASSOC);
    $assessmentStmt = $pdo->prepare('SELECT title, score, max_score, assessed_on, teacher_notes FROM sunday_school_assessments WHERE enrollment_id = :enrollment_id ORDER BY assessed_on DESC LIMIT 50');
    $assessmentStmt->execute([':enrollment_id' => $selectedEnrollmentId]);
    $assessments = $assessmentStmt->fetchAll(PDO::FETCH_ASSOC);
    $attendanceStmt = $pdo->prepare('SELECT attendance_date, attendance_status, notes FROM sunday_school_attendance WHERE enrollment_id = :enrollment_id ORDER BY attendance_date DESC LIMIT 30');
    $attendanceStmt->execute([':enrollment_id' => $selectedEnrollmentId]);
    $attendance = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
}
$weekdayKeys = [1 => 'education_monday', 2 => 'education_tuesday', 3 => 'education_wednesday', 4 => 'education_thursday', 5 => 'education_friday', 6 => 'education_saturday', 7 => 'education_sunday'];
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?php echo safe(adminManagementText('sunday_student_portal')); ?></title><style>
:root{font-family:Arial,sans-serif;color:#202b2b;background:#f4f7f5;font-synthesis:none}*{box-sizing:border-box}body{margin:0}.topbar{background:#143b36;color:#fff;padding:18px max(20px,calc((100% - 1080px)/2));display:flex;justify-content:space-between;align-items:center;gap:12px}.topbar h1{margin:0;font-size:20px}.topbar p{margin:4px 0 0;color:#c4d5ce;font-size:13px}.nav{display:flex;gap:8px;flex-wrap:wrap}.nav a{color:#fff;text-decoration:none;border:1px solid #739188;padding:8px 11px;border-radius:4px}.wrap{max-width:1080px;margin:22px auto;padding:0 18px}.class-select{display:flex;align-items:end;gap:9px;margin-bottom:16px}.class-select label{font-size:13px;font-weight:600}.class-select select,.class-select button{padding:9px;border:1px solid #cbd5d1;border-radius:4px;background:#fff;font:inherit}.class-select button{background:#236c5e;color:#fff}.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.stat,.section{background:#fff;border-top:2px solid #d7e2dc;padding:14px 16px;margin-bottom:16px}.stat span{display:block;color:#65736e;font-size:12px}.stat strong{display:block;font-size:22px;margin-top:5px}.section h2{font-size:17px;margin:0 0 12px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;vertical-align:top;padding:9px;border-bottom:1px solid #e5ebe7}th{background:#f3f7f4}.lesson{border-top:1px solid #e5ebe7;padding:12px 2px}.lesson:first-child{border-top:0}.lesson h3{font-size:15px;margin:0 0 6px}.lesson p{white-space:pre-wrap;margin:6px 0;color:#46534e}.lesson a{color:#236c5e}.empty{color:#65736e}.columns{display:grid;grid-template-columns:1fr 1fr;gap:15px}@media(max-width:700px){.topbar{align-items:flex-start;flex-direction:column}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.columns{grid-template-columns:1fr}.class-select{align-items:flex-start;flex-direction:column}}
</style></head>
<body>
<header class="topbar"><div><h1><?php echo safe(adminManagementText('sunday_student_portal')); ?></h1><p><?php echo safe($studentName); ?></p></div><nav class="nav"><a href="<?php echo safe($adminManagementSwitchUrl); ?>"><?php echo safe(adminManagementText('language_switch')); ?></a><a href="student_dashboard.php"><?php echo safe(adminManagementText('back_dashboard')); ?></a></nav></header>
<main class="wrap">
    <?php if (!$classes): ?><section class="section"><p class="empty"><?php echo safe(adminManagementText('sunday_student_not_enrolled')); ?></p></section><?php else: ?>
    <form class="class-select" method="get"><input type="hidden" name="lang" value="<?php echo safe($adminManagementLanguage); ?>"><label for="enrollment_id"><?php echo safe(adminManagementText('sunday_classes')); ?></label><select id="enrollment_id" name="enrollment_id"><?php foreach ($classes as $class): ?><option value="<?php echo (int)$class['enrollment_id']; ?>" <?php echo (int)$class['enrollment_id'] === $selectedEnrollmentId ? 'selected' : ''; ?>><?php echo safe($class['class_name']); ?></option><?php endforeach; ?></select><button type="submit"><?php echo safe(adminManagementText('view')); ?></button></form>
    <?php if ($selected): ?>
        <section class="stats"><div class="stat"><span><?php echo safe(adminManagementText('sunday_weekday')); ?></span><strong><?php echo $selected['weekday'] ? safe(adminManagementText($weekdayKeys[(int)$selected['weekday']] ?? 'education_sunday')) : '—'; ?></strong></div><div class="stat"><span><?php echo safe(adminManagementText('sunday_teacher')); ?></span><strong><?php echo safe($selected['teacher_name'] ?? '—'); ?></strong></div><div class="stat"><span><?php echo safe(adminManagementText('sunday_attendance_rate')); ?></span><strong><?php echo number_format((float)$selected['attendance_rate'], 1); ?>%</strong></div><div class="stat"><span><?php echo safe(adminManagementText('sunday_average_score')); ?></span><strong><?php echo number_format((float)$selected['average_score'], 1); ?>%</strong></div></section>
        <div class="columns"><section class="section"><h2><?php echo safe(adminManagementText('sunday_lessons')); ?></h2><?php if (!$lessons): ?><p class="empty"><?php echo safe(adminManagementText('sunday_no_lessons')); ?></p><?php else: foreach ($lessons as $lesson): ?><article class="lesson"><h3><?php echo safe($lesson['title']); ?></h3><?php if ($lesson['week_of']): ?><small><?php echo safe($lesson['week_of']); ?></small><?php endif; ?><?php if ($lesson['lesson_notes']): ?><p><?php echo safe($lesson['lesson_notes']); ?></p><?php endif; ?><?php if ($lesson['resource_url']): ?><a href="<?php echo safe($lesson['resource_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo safe(adminManagementText('education_materials')); ?> · <?php echo safe($lesson['resource_type']); ?></a><?php endif; ?></article><?php endforeach; endif; ?></section>
        <section class="section"><h2><?php echo safe(adminManagementText('sunday_assessments')); ?></h2><?php if (!$assessments): ?><p class="empty">—</p><?php else: ?><div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('sunday_assessment_title')); ?></th><th><?php echo safe(adminManagementText('sunday_score')); ?></th><th><?php echo safe(adminManagementText('date')); ?></th><th><?php echo safe(adminManagementText('notes')); ?></th></tr></thead><tbody><?php foreach ($assessments as $assessment): ?><tr><td><?php echo safe($assessment['title']); ?></td><td><?php echo number_format((float)$assessment['score'], 1); ?> / <?php echo number_format((float)$assessment['max_score'], 1); ?></td><td><?php echo safe($assessment['assessed_on']); ?></td><td><?php echo safe($assessment['teacher_notes'] ?? ''); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        <h2 style="margin-top:16px"><?php echo safe(adminManagementText('sunday_attendance')); ?></h2><?php if (!$attendance): ?><p class="empty">—</p><?php else: ?><div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('date')); ?></th><th><?php echo safe(adminManagementText('status')); ?></th><th><?php echo safe(adminManagementText('notes')); ?></th></tr></thead><tbody><?php foreach ($attendance as $row): ?><tr><td><?php echo safe($row['attendance_date']); ?></td><td><?php echo safe(adminManagementText(['present' => 'present', 'absent' => 'absent', 'late' => 'late', 'excused' => 'sunday_excused'][$row['attendance_status']] ?? 'sunday_excused')); ?></td><td><?php echo safe($row['notes'] ?? ''); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        <?php if ($selected['certificate_id']): ?><p style="margin-top:14px"><a href="sunday_school_certificate.php?id=<?php echo (int)$selected['certificate_id']; ?>" target="_blank"><?php echo safe(adminManagementText('sunday_certificate_print')); ?></a></p><?php endif; ?></section></div>
    <?php endif; endif; ?>
</main>
</body>
</html>