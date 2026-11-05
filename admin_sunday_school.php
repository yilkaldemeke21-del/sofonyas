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
$csrf = csrfToken();
$error = '';
$messageKey = [
    'staff' => 'ministry_saved_staff',
    'program' => 'ministry_saved_program',
    'status' => 'ministry_status_updated',
][(string)($_GET['saved'] ?? '')] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $error = adminManagementText('csrf_error');
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'add_staff') {
                $fullName = trim((string)($_POST['full_name'] ?? ''));
                $role = (string)($_POST['role'] ?? '');
                $phone = trim((string)($_POST['phone'] ?? ''));
                $email = trim((string)($_POST['email'] ?? ''));
                $specialization = trim((string)($_POST['specialization'] ?? ''));
                $notes = trim((string)($_POST['notes'] ?? ''));

                if ($fullName === '' || !in_array($role, ['teacher', 'singer', 'member'], true)) {
                    $error = adminManagementText('ministry_invalid_staff');
                } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = adminManagementText('email_validation_error');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO church_ministry_staff (full_name, role, phone, email, specialization, notes) VALUES (:full_name, :role, :phone, :email, :specialization, :notes)');
                    $stmt->execute([
                        ':full_name' => $fullName,
                        ':role' => $role,
                        ':phone' => $phone !== '' ? $phone : null,
                        ':email' => $email !== '' ? $email : null,
                        ':specialization' => $specialization !== '' ? $specialization : null,
                        ':notes' => $notes !== '' ? $notes : null,
                    ]);
                    header('Location: admin_sunday_school.php?lang=' . urlencode($adminManagementLanguage) . '&saved=staff');
                    exit;
                }
            } elseif ($action === 'add_program') {
                $type = (string)($_POST['program_type'] ?? '');
                $programName = trim((string)($_POST['program_name'] ?? ''));
                $levelOrGroup = trim((string)($_POST['level_or_group'] ?? ''));
                $schedule = trim((string)($_POST['schedule'] ?? ''));
                $leaderId = (int)($_POST['leader_id'] ?? 0);
                $description = trim((string)($_POST['description'] ?? ''));
                $allowedTypes = ['sunday_school', 'spiritual', 'education', 'choir', 'youth_service', 'children_service', 'education_service', 'charity_service', 'prayer_hymn'];

                if (!in_array($type, $allowedTypes, true) || $programName === '') {
                    $error = adminManagementText('ministry_invalid_program');
                } else {
                    if ($leaderId > 0) {
                        $leaderCheck = $pdo->prepare("SELECT id FROM church_ministry_staff WHERE id = :id AND status = 'active' AND role IN ('teacher', 'singer') LIMIT 1");
                        $leaderCheck->execute([':id' => $leaderId]);
                        if (!$leaderCheck->fetchColumn()) {
                            $error = adminManagementText('ministry_invalid_program');
                        }
                    }

                    if ($error === '') {
                        $stmt = $pdo->prepare('INSERT INTO church_education_programs (program_type, program_name, level_or_group, schedule, leader_id, description) VALUES (:program_type, :program_name, :level_or_group, :schedule, :leader_id, :description)');
                        $stmt->execute([
                            ':program_type' => $type,
                            ':program_name' => $programName,
                            ':level_or_group' => $levelOrGroup !== '' ? $levelOrGroup : null,
                            ':schedule' => $schedule !== '' ? $schedule : null,
                            ':leader_id' => $leaderId > 0 ? $leaderId : null,
                            ':description' => $description !== '' ? $description : null,
                        ]);
                        header('Location: admin_sunday_school.php?lang=' . urlencode($adminManagementLanguage) . '&saved=program');
                        exit;
                    }
                }
            } elseif ($action === 'set_staff_status') {
                $id = (int)($_POST['id'] ?? 0);
                $status = (string)($_POST['status'] ?? '');
                if ($id > 0 && in_array($status, ['active', 'inactive'], true)) {
                    $stmt = $pdo->prepare('UPDATE church_ministry_staff SET status = :status WHERE id = :id');
                    $stmt->execute([':status' => $status, ':id' => $id]);
                    header('Location: admin_sunday_school.php?lang=' . urlencode($adminManagementLanguage) . '&saved=status');
                    exit;
                }
            } elseif ($action === 'set_program_status') {
                $id = (int)($_POST['id'] ?? 0);
                $status = (string)($_POST['status'] ?? '');
                if ($id > 0 && in_array($status, ['active', 'inactive'], true)) {
                    $stmt = $pdo->prepare('UPDATE church_education_programs SET status = :status WHERE id = :id');
                    $stmt->execute([':status' => $status, ':id' => $id]);
                    header('Location: admin_sunday_school.php?lang=' . urlencode($adminManagementLanguage) . '&saved=status');
                    exit;
                }
            }
        } catch (PDOException $exception) {
            error_log('Sunday school management save failed: ' . $exception->getMessage());
            $error = adminManagementText('ministry_save_error');
        }
    }
}

$staffCounts = $pdo->query("SELECT role, COUNT(*) AS total FROM church_ministry_staff WHERE status = 'active' GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
$programCounts = $pdo->query("SELECT COUNT(*) FROM church_education_programs WHERE status = 'active'")->fetchColumn();
$serviceCounts = $pdo->query("SELECT program_type, COUNT(*) AS total FROM church_education_programs WHERE status = 'active' GROUP BY program_type")->fetchAll(PDO::FETCH_KEY_PAIR);
$staff = $pdo->query('SELECT id, full_name, role, phone, email, specialization, status FROM church_ministry_staff ORDER BY status ASC, role ASC, full_name ASC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
$activeLeaders = $pdo->query("SELECT id, full_name, role FROM church_ministry_staff WHERE status = 'active' AND role IN ('teacher', 'singer') ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$programs = $pdo->query('SELECT p.*, s.full_name AS leader_name, s.role AS leader_role FROM church_education_programs p LEFT JOIN church_ministry_staff s ON s.id = p.leader_id ORDER BY p.status ASC, p.program_type ASC, p.program_name ASC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
$programTypeKeys = [
    'sunday_school' => 'ministry_sunday_school',
    'spiritual' => 'ministry_spiritual',
    'education' => 'ministry_education',
    'choir' => 'ministry_choir',
    'choir_service' => 'ministry_choir_service',
    'youth_service' => 'ministry_youth_service',
    'children_service' => 'ministry_children_service',
    'education_service' => 'ministry_education_service',
    'charity_service' => 'ministry_charity_service',
    'prayer_hymn' => 'ministry_prayer_hymn',
];
$serviceTypeKeys = [
    'choir_service' => 'ministry_choir_service',
    'youth_service' => 'ministry_youth_service',
    'children_service' => 'ministry_children_service',
    'education_service' => 'ministry_education_service',
    'charity_service' => 'ministry_charity_service',
    'prayer_hymn' => 'ministry_prayer_hymn',
];
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe(adminManagementText('ministry_title')); ?></title>
    <style>
        :root{font-family:Arial,sans-serif;color:#202b2b;background:#f4f7f5;font-synthesis:none}*{box-sizing:border-box}body{margin:0}.topbar{background:#143b36;color:#fff;padding:16px max(20px,calc((100% - 1200px)/2));display:flex;align-items:center;justify-content:space-between;gap:14px}.brand h1{font-size:20px;margin:0}.brand p{margin:4px 0 0;color:#c4d5ce;font-size:13px}.nav{display:flex;gap:8px;flex-wrap:wrap}.nav a{color:#fff;text-decoration:none;border:1px solid #739188;border-radius:4px;padding:9px 12px}.wrap{max-width:1200px;margin:24px auto;padding:0 18px}.intro{display:flex;align-items:end;justify-content:space-between;gap:16px;margin-bottom:18px}.intro h2{font-size:23px;margin:0 0 5px}.muted{color:#65736e}.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:18px}.stat{background:#fff;padding:14px 16px;border-top:3px solid #236c5e}.stat span{display:block;font-size:13px;color:#65736e}.stat strong{display:block;font-size:25px;margin-top:5px}.columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-bottom:18px}.section{background:#fff;padding:18px;border-top:2px solid #d7e2dc;margin-bottom:18px;min-width:0}.section h3{font-size:17px;margin:0 0 14px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}.field{display:flex;flex-direction:column;gap:5px}.field.full{grid-column:1/-1}label{font-size:13px;font-weight:600}input,select,textarea{width:100%;padding:10px;border:1px solid #cbd5d1;border-radius:4px;background:#fff;color:#202b2b;font:inherit}textarea{min-height:72px;resize:vertical}.submit{margin-top:12px;padding:10px 14px;border:0;border-radius:4px;background:#236c5e;color:#fff;font:inherit;cursor:pointer}.notice{padding:11px 13px;margin-bottom:14px;background:#e8f4ef;color:#176244}.error{background:#fff0eb;color:#9b351f}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;vertical-align:top;padding:10px 8px;border-bottom:1px solid #e5ebe7;white-space:nowrap}th{background:#f3f7f4;color:#46534e}.actions{display:flex;gap:9px;align-items:center}.actions a,.actions button{font:inherit;color:#236c5e;text-decoration:underline;background:none;border:0;padding:0;cursor:pointer}.actions button{color:#a33b35}.tag{display:inline-block;padding:3px 7px;border-radius:3px;background:#edf4ef;font-size:12px}.empty{padding:12px 2px;color:#65736e}.section-head{display:flex;justify-content:space-between;align-items:center;gap:10px}.section-head span{font-size:13px;color:#65736e}@media(max-width:800px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.columns{grid-template-columns:1fr}.topbar{padding:14px 18px}}@media(max-width:520px){.topbar{align-items:flex-start;flex-direction:column}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.intro{align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<header class="topbar">
    <div class="brand"><h1><?php echo safe(adminManagementText('ministry_title')); ?></h1><p><?php echo safe(adminManagementText('ministry_intro')); ?></p></div>
    <nav class="nav"><a href="<?php echo safe($adminManagementSwitchUrl); ?>"><?php echo safe(adminManagementText('language_switch')); ?></a><a href="admin_sunday_school_classes.php"><?php echo safe(adminManagementText('sunday_dashboard')); ?></a><a href="admin_management_dashboard.php">← <?php echo safe(adminManagementText('back_dashboard')); ?></a></nav>
</header>
<main class="wrap">
    <?php if ($messageKey !== ''): ?><div class="notice"><?php echo safe(adminManagementText($messageKey)); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error"><?php echo safe($error); ?></div><?php endif; ?>
    <div class="intro"><div><h2><?php echo safe(adminManagementText('ministry_programs')); ?></h2><div class="muted"><?php echo safe(adminManagementText('ministry_intro')); ?></div></div></div>
    <section class="stats" aria-label="Management totals">
        <div class="stat"><span><?php echo safe(adminManagementText('ministry_active_programs')); ?></span><strong><?php echo number_format((int)$programCounts); ?></strong></div>
        <div class="stat"><span><?php echo safe(adminManagementText('ministry_teachers')); ?></span><strong><?php echo number_format((int)($staffCounts['teacher'] ?? 0)); ?></strong></div>
        <div class="stat"><span><?php echo safe(adminManagementText('ministry_singers')); ?></span><strong><?php echo number_format((int)($staffCounts['singer'] ?? 0)); ?></strong></div>
        <div class="stat"><span><?php echo safe(adminManagementText('ministry_member')); ?></span><strong><?php echo number_format((int)($staffCounts['member'] ?? 0)); ?></strong></div>
        <div class="stat"><span><?php echo safe(adminManagementText('ministry_staff')); ?></span><strong><?php echo number_format(count($staff)); ?></strong></div>
    </section>
    <h2 class="section-title"><?php echo safe(adminManagementText('ministry_service_overview')); ?></h2>
    <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:10px;margin-bottom:18px">
        <?php foreach ($serviceTypeKeys as $serviceType => $translationKey): ?>
            <div class="stat"><span><?php echo safe(adminManagementText($translationKey)); ?></span><strong><?php echo number_format((int)($serviceCounts[$serviceType] ?? 0)); ?></strong></div>
        <?php endforeach; ?>
    </section>
    <div class="columns">
        <section class="section">
            <h3><?php echo safe(adminManagementText('ministry_add_staff')); ?></h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>">
                <input type="hidden" name="action" value="add_staff">
                <div class="form-grid">
                    <div class="field full"><label for="full_name"><?php echo safe(adminManagementText('full_name')); ?> *</label><input id="full_name" name="full_name" maxlength="180" required></div>
                    <div class="field"><label for="role"><?php echo safe(adminManagementText('ministry_role')); ?> *</label><select id="role" name="role" required><option value="teacher"><?php echo safe(adminManagementText('ministry_teacher')); ?></option><option value="singer"><?php echo safe(adminManagementText('ministry_singer')); ?></option><option value="member"><?php echo safe(adminManagementText('ministry_member')); ?></option></select></div>
                    <div class="field"><label for="phone"><?php echo safe(adminManagementText('phone')); ?></label><input id="phone" name="phone" maxlength="40" type="tel"></div>
                    <div class="field"><label for="email"><?php echo safe(adminManagementText('email')); ?></label><input id="email" name="email" maxlength="190" type="email"></div>
                    <div class="field"><label for="specialization"><?php echo safe(adminManagementText('ministry_specialization')); ?></label><input id="specialization" name="specialization" maxlength="150"></div>
                    <div class="field full"><label for="staff_notes"><?php echo safe(adminManagementText('ministry_notes')); ?></label><textarea id="staff_notes" name="notes" maxlength="500"></textarea></div>
                </div>
                <button class="submit" type="submit"><?php echo safe(adminManagementText('ministry_save_staff')); ?></button>
            </form>
        </section>
        <section class="section">
            <h3><?php echo safe(adminManagementText('ministry_program_form')); ?></h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>">
                <input type="hidden" name="action" value="add_program">
                <div class="form-grid">
                    <div class="field"><label for="program_type"><?php echo safe(adminManagementText('ministry_program_type')); ?> *</label><select id="program_type" name="program_type" required><?php foreach ($programTypeKeys as $type => $key): ?><option value="<?php echo safe($type); ?>"><?php echo safe(adminManagementText($key)); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="program_name"><?php echo safe(adminManagementText('ministry_program_name')); ?> *</label><input id="program_name" name="program_name" maxlength="180" required></div>
                    <div class="field"><label for="level_or_group"><?php echo safe(adminManagementText('ministry_level_group')); ?></label><input id="level_or_group" name="level_or_group" maxlength="100"></div>
                    <div class="field"><label for="schedule"><?php echo safe(adminManagementText('ministry_schedule')); ?></label><input id="schedule" name="schedule" maxlength="150"></div>
                    <div class="field full"><label for="leader_id"><?php echo safe(adminManagementText('ministry_leader')); ?></label><select id="leader_id" name="leader_id"><option value="0"><?php echo safe(adminManagementText('ministry_no_leader')); ?></option><?php foreach ($activeLeaders as $leader): ?><option value="<?php echo (int)$leader['id']; ?>"><?php echo safe($leader['full_name']); ?> · <?php echo safe(adminManagementText($leader['role'] === 'teacher' ? 'ministry_teacher' : 'ministry_singer')); ?></option><?php endforeach; ?></select></div>
                    <div class="field full"><label for="description"><?php echo safe(adminManagementText('ministry_description')); ?></label><textarea id="description" name="description"></textarea></div>
                </div>
                <button class="submit" type="submit"><?php echo safe(adminManagementText('ministry_save_program')); ?></button>
            </form>
        </section>
    </div>
    <section class="section">
        <div class="section-head"><h3><?php echo safe(adminManagementText('ministry_program_list')); ?></h3><span><?php echo count($programs); ?></span></div>
        <?php if (!$programs): ?><div class="empty"><?php echo safe(adminManagementText('ministry_no_programs')); ?></div><?php else: ?><div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('ministry_program_type')); ?></th><th><?php echo safe(adminManagementText('ministry_program_name')); ?></th><th><?php echo safe(adminManagementText('ministry_level_group')); ?></th><th><?php echo safe(adminManagementText('ministry_schedule')); ?></th><th><?php echo safe(adminManagementText('ministry_leader')); ?></th><th><?php echo safe(adminManagementText('status')); ?></th><th><?php echo safe(adminManagementText('action')); ?></th></tr></thead><tbody><?php foreach ($programs as $program): ?><tr><td><span class="tag"><?php echo safe(adminManagementText($programTypeKeys[$program['program_type']] ?? 'ministry_education')); ?></span></td><td><?php echo safe($program['program_name']); ?></td><td><?php echo safe($program['level_or_group'] ?? ''); ?></td><td><?php echo safe($program['schedule'] ?? ''); ?></td><td><?php echo safe($program['leader_name'] ?? adminManagementText('ministry_no_leader')); ?></td><td><?php echo safe(adminManagementText($program['status'] === 'active' ? 'member_active' : 'member_inactive')); ?></td><td><form class="actions" method="post"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="set_program_status"><input type="hidden" name="id" value="<?php echo (int)$program['id']; ?>"><input type="hidden" name="status" value="<?php echo $program['status'] === 'active' ? 'inactive' : 'active'; ?>"><button type="submit"><?php echo safe(adminManagementText($program['status'] === 'active' ? 'set_inactive' : 'set_active')); ?></button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
    <section class="section">
        <div class="section-head"><h3><?php echo safe(adminManagementText('ministry_people_list')); ?></h3><span><?php echo count($staff); ?></span></div>
        <?php if (!$staff): ?><div class="empty"><?php echo safe(adminManagementText('ministry_no_people')); ?></div><?php else: ?><div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('full_name')); ?></th><th><?php echo safe(adminManagementText('ministry_role')); ?></th><th><?php echo safe(adminManagementText('phone')); ?></th><th><?php echo safe(adminManagementText('email')); ?></th><th><?php echo safe(adminManagementText('ministry_specialization')); ?></th><th><?php echo safe(adminManagementText('status')); ?></th><th><?php echo safe(adminManagementText('action')); ?></th></tr></thead><tbody><?php foreach ($staff as $person): ?><tr><td><?php echo safe($person['full_name']); ?></td><td><?php echo safe(adminManagementText('ministry_' . $person['role'])); ?></td><td><?php echo safe($person['phone'] ?? ''); ?></td><td><?php echo safe($person['email'] ?? ''); ?></td><td><?php echo safe($person['specialization'] ?? ''); ?></td><td><?php echo safe(adminManagementText($person['status'] === 'active' ? 'member_active' : 'member_inactive')); ?></td><td><form class="actions" method="post"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="set_staff_status"><input type="hidden" name="id" value="<?php echo (int)$person['id']; ?>"><input type="hidden" name="status" value="<?php echo $person['status'] === 'active' ? 'inactive' : 'active'; ?>"><button type="submit"><?php echo safe(adminManagementText($person['status'] === 'active' ? 'set_inactive' : 'set_active')); ?></button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
</main>
</body>
</html>