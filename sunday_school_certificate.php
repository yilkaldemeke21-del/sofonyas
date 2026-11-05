<?php
session_start();
require_once __DIR__ . '/db.php';
requireRole(['admin', 'student'], $pdo);
ensureManagementTables($pdo);
$certificateId = (int)($_GET['id'] ?? 0);
$params = [':certificate_id' => $certificateId];
$query = 'SELECT cert.certificate_code, cert.issued_at, e.student_id, m.full_name, m.member_number, c.class_name FROM sunday_school_certificates cert JOIN sunday_school_enrollments e ON e.id = cert.enrollment_id JOIN church_members m ON m.id = e.member_id JOIN sunday_school_classes c ON c.id = e.class_id WHERE cert.id = :certificate_id';
if (!empty($_SESSION['student_id'])) {
    $query .= ' AND e.student_id = :student_id';
    $params[':student_id'] = (string)$_SESSION['student_id'];
}
$stmt = $pdo->prepare($query . ' LIMIT 1');
$stmt->execute($params);
$certificate = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$certificate) {
    http_response_code(404);
    exit('Certificate not found.');
}
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?php echo safe(adminManagementText('sunday_certificate_print')); ?></title><style>
:root{font-family:Georgia,"Times New Roman",serif;color:#20372f;background:#f3f5f2}*{box-sizing:border-box}body{margin:0;padding:24px}.actions{max-width:900px;margin:0 auto 12px;display:flex;justify-content:space-between;gap:10px}.actions a,.actions button{background:#236c5e;color:#fff;border:0;border-radius:4px;padding:9px 13px;text-decoration:none;font:14px Arial,sans-serif;cursor:pointer}.certificate{max-width:900px;min-height:600px;margin:0 auto;background:#fff;border:10px double #b6853b;padding:54px;text-align:center;display:flex;flex-direction:column;justify-content:center}.eyebrow{font:13px Arial,sans-serif;letter-spacing:2px;color:#8a6a2d;text-transform:uppercase}.certificate h1{font-size:38px;margin:24px 0 10px}.certificate h2{font-size:28px;margin:24px 0 10px}.rule{width:120px;border-top:2px solid #b6853b;margin:15px auto}.class{font-size:20px}.meta{display:flex;justify-content:space-between;margin-top:42px;font:13px Arial,sans-serif;color:#56645c}.code{font:12px Arial,sans-serif;color:#65736e;margin-top:20px}@media print{body{background:#fff;padding:0}.actions{display:none}.certificate{max-width:none;min-height:100vh;border-width:8px;margin:0;padding:45px;break-inside:avoid}}
</style></head>
<body><nav class="actions"><a href="sunday_school_student.php"><?php echo safe(adminManagementText('sunday_student_portal')); ?></a><button type="button" onclick="window.print()"><?php echo safe(adminManagementText('sunday_certificate_print')); ?></button></nav>
<main class="certificate"><div class="eyebrow">✝ <?php echo safe(adminManagementText('sunday_dashboard')); ?></div><h1><?php echo safe(adminManagementText('sunday_certificate_print')); ?></h1><div class="rule"></div><p><?php echo safe(adminManagementText('sunday_certificate_awarded')); ?></p><h2><?php echo safe($certificate['full_name']); ?></h2><p class="class"><?php echo safe($certificate['class_name']); ?></p><div class="rule"></div><p><?php echo safe(adminManagementText('sunday_certificate_completed')); ?></p><div class="meta"><span><?php echo safe(adminManagementText('member_number')); ?>: <?php echo safe($certificate['member_number']); ?></span><span><?php echo safe(adminManagementText('date')); ?>: <?php echo safe(date('Y-m-d', strtotime($certificate['issued_at']))); ?></span></div><p class="code"><?php echo safe(adminManagementText('sunday_certificate_code')); ?>: <?php echo safe($certificate['certificate_code']); ?></p></main></body></html>