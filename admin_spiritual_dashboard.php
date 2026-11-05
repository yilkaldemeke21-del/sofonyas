<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/management_schema.php';
require_once __DIR__ . '/admin_management_i18n.php';
require_once __DIR__ . '/spiritual_security.php';

if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit(adminManagementText('db_unavailable'));
}

ensureManagementTables($pdo);
$spiritualAccess = requireSpiritualAccess($pdo);
$csrf = csrfToken();
$errorKey = '';
$savedKey = [
    'event' => 'spiritual_event_saved',
    'event_status' => 'spiritual_event_status_saved',
    'appointment' => 'spiritual_appointment_saved',
    'appointment_status' => 'spiritual_status_updated',
][(string)($_GET['saved'] ?? '')] ?? '';
$eventTypeKeys = [
    'spiritual_class' => 'spiritual_class',
    'liturgy' => 'spiritual_liturgy',
    'prayer' => 'spiritual_prayer',
    'service' => 'spiritual_service',
    'holiday' => 'spiritual_holiday',
    'special' => 'spiritual_special',
];
$appointmentTypeKeys = [
    'confession' => 'spiritual_confession',
    'counseling' => 'spiritual_counseling',
    'private' => 'spiritual_private',
];
$appointmentStatusKeys = [
    'requested' => 'spiritual_requested',
    'scheduled' => 'spiritual_scheduled',
    'completed' => 'spiritual_completed',
    'cancelled' => 'spiritual_cancelled',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $errorKey = 'csrf_error';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'add_event') {
                if (!$spiritualAccess['can_manage']) {
                    http_response_code(403);
                    exit(adminManagementText('spiritual_access_denied'));
                }
                $eventType = (string)($_POST['event_type'] ?? '');
                $title = trim((string)($_POST['title'] ?? ''));
                $description = trim((string)($_POST['description'] ?? ''));
                $startInput = (string)($_POST['starts_at'] ?? '');
                $endInput = (string)($_POST['ends_at'] ?? '');
                $location = trim((string)($_POST['location'] ?? ''));
                $startsAt = DateTime::createFromFormat('!Y-m-d\TH:i', $startInput);
                $endsAt = $endInput !== '' ? DateTime::createFromFormat('!Y-m-d\TH:i', $endInput) : null;

                if (!isset($eventTypeKeys[$eventType]) || $title === '' || strlen($title) > 180 || !$startsAt || ($endInput !== '' && !$endsAt) || ($endsAt && $endsAt <= $startsAt)) {
                    $errorKey = 'spiritual_invalid_event';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO spiritual_events (event_type, title, description, starts_at, ends_at, location, created_by) VALUES (:event_type, :title, :description, :starts_at, :ends_at, :location, :created_by)');
                    $stmt->execute([
                        ':event_type' => $eventType,
                        ':title' => $title,
                        ':description' => $description !== '' ? $description : null,
                        ':starts_at' => $startsAt->format('Y-m-d H:i:s'),
                        ':ends_at' => $endsAt ? $endsAt->format('Y-m-d H:i:s') : null,
                        ':location' => $location !== '' ? $location : null,
                        ':created_by' => (int)$_SESSION['admin_id'],
                    ]);
                    header('Location: admin_spiritual_dashboard.php?lang=' . urlencode($adminManagementLanguage) . '&saved=event');
                    exit;
                }
            } elseif ($action === 'set_event_status') {
                if (!$spiritualAccess['can_manage']) {
                    http_response_code(403);
                    exit(adminManagementText('spiritual_access_denied'));
                }
                $eventId = (int)($_POST['event_id'] ?? 0);
                $status = (string)($_POST['status'] ?? '');
                if ($eventId > 0 && in_array($status, ['scheduled', 'completed', 'cancelled'], true)) {
                    $stmt = $pdo->prepare('UPDATE spiritual_events SET status = :status WHERE id = :id');
                    $stmt->execute([':status' => $status, ':id' => $eventId]);
                    header('Location: admin_spiritual_dashboard.php?lang=' . urlencode($adminManagementLanguage) . '&saved=event_status');
                    exit;
                }
            } elseif ($action === 'add_appointment') {
                if (!$spiritualAccess['can_manage_confidential']) {
                    http_response_code(403);
                    exit(adminManagementText('spiritual_access_denied'));
                }
                $type = (string)($_POST['appointment_type'] ?? '');
                $personName = trim((string)($_POST['person_name'] ?? ''));
                $startInput = (string)($_POST['starts_at'] ?? '');
                $assignedTo = (int)($_POST['assigned_to'] ?? 0);
                $privateNotes = trim((string)($_POST['private_notes'] ?? ''));
                $startsAt = DateTime::createFromFormat('!Y-m-d\TH:i', $startInput);
                $validAssignee = true;
                if ($assignedTo > 0) {
                    $assigneeStmt = $pdo->prepare("SELECT id FROM admin_users WHERE id = :id AND LOWER(role) IN ('admin', 'instructor', 'teacher') LIMIT 1");
                    $assigneeStmt->execute([':id' => $assignedTo]);
                    $validAssignee = (bool)$assigneeStmt->fetchColumn();
                }

                if (!isset($appointmentTypeKeys[$type]) || $personName === '' || strlen($personName) > 180 || !$startsAt || !$validAssignee) {
                    $errorKey = 'spiritual_invalid_appointment';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO spiritual_appointments (appointment_type, person_name, starts_at, assigned_to, private_notes, status, created_by) VALUES (:appointment_type, :person_name, :starts_at, :assigned_to, :private_notes, :status, :created_by)');
                    $stmt->execute([
                        ':appointment_type' => $type,
                        ':person_name' => $personName,
                        ':starts_at' => $startsAt->format('Y-m-d H:i:s'),
                        ':assigned_to' => $assignedTo > 0 ? $assignedTo : null,
                        ':private_notes' => $privateNotes !== '' ? $privateNotes : null,
                        ':status' => 'scheduled',
                        ':created_by' => (int)$_SESSION['admin_id'],
                    ]);
                    header('Location: admin_spiritual_dashboard.php?lang=' . urlencode($adminManagementLanguage) . '&saved=appointment');
                    exit;
                }
            } elseif ($action === 'set_appointment_status') {
                if (!$spiritualAccess['can_manage_confidential']) {
                    http_response_code(403);
                    exit(adminManagementText('spiritual_access_denied'));
                }
                $appointmentId = (int)($_POST['appointment_id'] ?? 0);
                $status = (string)($_POST['status'] ?? '');
                if ($appointmentId > 0 && isset($appointmentStatusKeys[$status])) {
                    $stmt = $pdo->prepare('UPDATE spiritual_appointments SET status = :status WHERE id = :id');
                    $stmt->execute([':status' => $status, ':id' => $appointmentId]);
                    header('Location: admin_spiritual_dashboard.php?lang=' . urlencode($adminManagementLanguage) . '&saved=appointment_status');
                    exit;
                }
            }
        } catch (PDOException $exception) {
            error_log('Spiritual dashboard save failed: ' . $exception->getMessage());
            $errorKey = 'spiritual_save_failed';
        }
    }
}

$events = $pdo->query('SELECT id, event_type, title, description, starts_at, ends_at, location, status FROM spiritual_events WHERE starts_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY starts_at ASC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
$upcomingEvents = (int)$pdo->query("SELECT COUNT(*) FROM spiritual_events WHERE starts_at >= NOW() AND status = 'scheduled'")->fetchColumn();
$appointments = [];
$servants = [];
if ($spiritualAccess['can_manage_confidential']) {
    $appointments = $pdo->query('SELECT a.id, a.appointment_type, a.person_name, a.starts_at, a.assigned_to, a.private_notes, a.status, u.username AS assigned_name FROM spiritual_appointments a LEFT JOIN admin_users u ON u.id = a.assigned_to ORDER BY a.starts_at DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
    $servants = $pdo->query("SELECT id, username FROM admin_users WHERE LOWER(role) IN ('admin', 'instructor', 'teacher') ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
}
$eventTypes = array_keys($eventTypeKeys);
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe(adminManagementText('spiritual_dashboard_title')); ?></title>
    <style>
        :root{font-family:Arial,sans-serif;color:#202b2b;background:#f4f7f5;font-synthesis:none}*{box-sizing:border-box}body{margin:0}.topbar{background:#143b36;color:#fff;padding:16px max(20px,calc((100% - 1200px)/2));display:flex;justify-content:space-between;align-items:center;gap:14px}.topbar h1{font-size:20px;margin:0}.topbar p{margin:4px 0 0;color:#c4d5ce;font-size:13px}.nav{display:flex;gap:8px;flex-wrap:wrap}.nav a{color:#fff;text-decoration:none;border:1px solid #739188;border-radius:4px;padding:9px 12px}.wrap{max-width:1200px;margin:24px auto;padding:0 18px}.intro{display:flex;justify-content:space-between;align-items:end;gap:15px;margin-bottom:16px}.intro h2{margin:0 0 5px;font-size:22px}.muted{color:#65736e}.stat{background:#fff;border-left:3px solid #b6853b;padding:12px 16px;min-width:150px}.stat strong{font-size:24px;display:block}.columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.section{background:#fff;border-top:2px solid #d7e2dc;padding:18px;margin-bottom:18px;min-width:0}.section h3{font-size:17px;margin:0 0 14px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}.field{display:flex;flex-direction:column;gap:5px}.field.full{grid-column:1/-1}label{font-size:13px;font-weight:600}input,select,textarea{width:100%;padding:10px;border:1px solid #cbd5d1;border-radius:4px;background:#fff;color:#202b2b;font:inherit}textarea{min-height:66px}.submit{margin-top:12px;background:#236c5e;color:#fff;border:0;border-radius:4px;padding:10px 14px;font:inherit;cursor:pointer}.notice{padding:11px 13px;margin-bottom:14px;background:#e8f4ef;color:#176244}.error{background:#fff0eb;color:#9b351f}.privacy{background:#fff8e8;color:#795b1b;border-left:3px solid #b6853b;padding:12px 14px;margin-bottom:18px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;vertical-align:top;padding:10px 8px;border-bottom:1px solid #e5ebe7}th{background:#f3f7f4;white-space:nowrap}.inline{display:flex;gap:6px;align-items:center}.inline select{min-width:110px}.empty{padding:12px 2px;color:#65736e}.quick{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;margin-bottom:18px}.quick a{background:#fff;border:1px solid #dbe4df;padding:12px 14px;color:#215e52;text-decoration:none;font-weight:600}.quick a:hover{border-color:#236c5e}.print{border:1px solid #cbd5d1;background:#fff;color:#215e52;padding:9px 12px;border-radius:4px;cursor:pointer;font:inherit}@media(max-width:850px){.quick{grid-template-columns:repeat(2,minmax(0,1fr))}.columns{grid-template-columns:1fr}}@media(max-width:620px){.topbar{align-items:flex-start;flex-direction:column}.intro{align-items:flex-start;flex-direction:column}.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}}@media print{body{background:#fff}.topbar,.quick,.form-section,.privacy,.no-print{display:none!important}.wrap{margin:0;max-width:none}.section{border:0;padding:0}}
    </style>
</head>
<body>
<header class="topbar"><div><h1><?php echo safe(adminManagementText('spiritual_dashboard_title')); ?></h1><p><?php echo safe(adminManagementText('spiritual_intro')); ?></p></div><nav class="nav"><a href="<?php echo safe($adminManagementSwitchUrl); ?>"><?php echo safe(adminManagementText('language_switch')); ?></a><?php if ($spiritualAccess['is_admin']): ?><a href="admin_spiritual_access.php"><?php echo safe(adminManagementText('spiritual_permissions')); ?></a><?php endif; ?><a href="admin_management_dashboard.php">← <?php echo safe(adminManagementText('back_dashboard')); ?></a></nav></header>
<main class="wrap">
    <?php if ($savedKey !== ''): ?><div class="notice"><?php echo safe(adminManagementText($savedKey)); ?></div><?php endif; ?>
    <?php if ($errorKey !== ''): ?><div class="notice error"><?php echo safe(adminManagementText($errorKey)); ?></div><?php endif; ?>
    <?php if (!$spiritualAccess['can_manage_confidential']): ?><div class="privacy"><?php echo safe(adminManagementText('spiritual_privacy_notice')); ?></div><?php endif; ?>
    <div class="intro"><div><h2><?php echo safe(adminManagementText('spiritual_events')); ?></h2><div class="muted"><?php echo safe(adminManagementText('spiritual_intro')); ?></div></div><div class="stat"><strong><?php echo number_format($upcomingEvents); ?></strong><span><?php echo safe(adminManagementText('spiritual_events')); ?></span></div></div>
    <nav class="quick" aria-label="Spiritual resources">
        <a href="admin_sunday_school.php"><?php echo safe(adminManagementText('spiritual_choir_manage')); ?> →</a>
        <a href="admin_church_resources.php"><?php echo safe(adminManagementText('spiritual_resources_manage')); ?> →</a>
        <a href="library.php"><?php echo safe(adminManagementText('spiritual_book_archive')); ?> →</a>
        <a href="admin_add_announcement.php"><?php echo safe(adminManagementText('spiritual_publish')); ?> →</a>
    </nav>
    <?php if ($spiritualAccess['can_manage']): ?>
    <section class="section form-section">
        <h3><?php echo safe(adminManagementText('spiritual_add_event')); ?></h3>
        <form method="post"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="add_event">
            <div class="form-grid">
                <div class="field"><label for="event_type"><?php echo safe(adminManagementText('spiritual_event_type')); ?> *</label><select id="event_type" name="event_type" required><?php foreach ($eventTypes as $type): ?><option value="<?php echo safe($type); ?>"><?php echo safe(adminManagementText($eventTypeKeys[$type])); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="title"><?php echo safe(adminManagementText('spiritual_event_title')); ?> *</label><input id="title" name="title" maxlength="180" required></div>
                <div class="field"><label for="starts_at"><?php echo safe(adminManagementText('spiritual_starts_at')); ?> *</label><input id="starts_at" name="starts_at" type="datetime-local" required></div>
                <div class="field"><label for="ends_at"><?php echo safe(adminManagementText('spiritual_ends_at')); ?></label><input id="ends_at" name="ends_at" type="datetime-local"></div>
                <div class="field"><label for="location"><?php echo safe(adminManagementText('spiritual_location')); ?></label><input id="location" name="location" maxlength="180"></div>
                <div class="field full"><label for="description"><?php echo safe(adminManagementText('spiritual_event_description')); ?></label><textarea id="description" name="description"></textarea></div>
            </div>
            <button class="submit" type="submit"><?php echo safe(adminManagementText('spiritual_create_event')); ?></button>
        </form>
    </section>
    <?php endif; ?>
    <section class="section" id="event-list">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px"><h3><?php echo safe(adminManagementText('spiritual_events')); ?></h3><button class="print no-print" type="button" onclick="window.print()"><?php echo safe(adminManagementText('print')); ?></button></div>
        <?php if (!$events): ?><div class="empty"><?php echo safe(adminManagementText('spiritual_no_events')); ?></div><?php else: ?><div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('spiritual_event_type')); ?></th><th><?php echo safe(adminManagementText('spiritual_event_title')); ?></th><th><?php echo safe(adminManagementText('spiritual_starts_at')); ?></th><th><?php echo safe(adminManagementText('spiritual_ends_at')); ?></th><th><?php echo safe(adminManagementText('spiritual_location')); ?></th><th><?php echo safe(adminManagementText('resource_status')); ?></th><?php if ($spiritualAccess['can_manage']): ?><th class="no-print"><?php echo safe(adminManagementText('action')); ?></th><?php endif; ?></tr></thead><tbody><?php foreach ($events as $event): ?><tr><td><?php echo safe(adminManagementText($eventTypeKeys[$event['event_type']] ?? 'spiritual_special')); ?></td><td><?php echo safe($event['title']); ?><?php if (!empty($event['description'])): ?><br><span class="muted"><?php echo safe($event['description']); ?></span><?php endif; ?></td><td><?php echo safe($event['starts_at']); ?></td><td><?php echo safe($event['ends_at'] ?? ''); ?></td><td><?php echo safe($event['location'] ?? ''); ?></td><td><?php echo safe(adminManagementText(['scheduled' => 'spiritual_scheduled', 'completed' => 'spiritual_completed', 'cancelled' => 'spiritual_cancelled'][$event['status']] ?? 'spiritual_scheduled')); ?></td><?php if ($spiritualAccess['can_manage']): ?><td class="no-print"><form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="set_event_status"><input type="hidden" name="event_id" value="<?php echo (int)$event['id']; ?>"><select name="status" aria-label="<?php echo safe(adminManagementText('resource_status')); ?>"><option value="scheduled"><?php echo safe(adminManagementText('spiritual_scheduled')); ?></option><option value="completed" <?php echo $event['status'] === 'completed' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('spiritual_completed')); ?></option><option value="cancelled" <?php echo $event['status'] === 'cancelled' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('spiritual_cancelled')); ?></option></select><button class="submit" style="margin:0" type="submit"><?php echo safe(adminManagementText('education_toggle_status')); ?></button></form></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
    <?php if ($spiritualAccess['can_manage_confidential']): ?>
    <div class="columns">
        <section class="section form-section">
            <h3><?php echo safe(adminManagementText('spiritual_appointment')); ?></h3>
            <form method="post"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="add_appointment">
                <div class="form-grid">
                    <div class="field"><label for="appointment_type"><?php echo safe(adminManagementText('spiritual_appointment_type')); ?> *</label><select id="appointment_type" name="appointment_type" required><?php foreach ($appointmentTypeKeys as $type => $key): ?><option value="<?php echo safe($type); ?>"><?php echo safe(adminManagementText($key)); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="person_name"><?php echo safe(adminManagementText('spiritual_person_name')); ?> *</label><input id="person_name" name="person_name" maxlength="180" required></div>
                    <div class="field"><label for="appointment_starts_at"><?php echo safe(adminManagementText('spiritual_starts_at')); ?> *</label><input id="appointment_starts_at" name="starts_at" type="datetime-local" required></div>
                    <div class="field"><label for="assigned_to"><?php echo safe(adminManagementText('spiritual_assigned_to')); ?></label><select id="assigned_to" name="assigned_to"><option value="0"><?php echo safe(adminManagementText('education_no_teacher')); ?></option><?php foreach ($servants as $servant): ?><option value="<?php echo (int)$servant['id']; ?>"><?php echo safe($servant['username']); ?></option><?php endforeach; ?></select></div>
                    <div class="field full"><label for="private_notes"><?php echo safe(adminManagementText('spiritual_private_notes')); ?></label><textarea id="private_notes" name="private_notes"></textarea></div>
                </div>
                <button class="submit" type="submit"><?php echo safe(adminManagementText('spiritual_add_appointment')); ?></button>
            </form>
        </section>
        <section class="section">
            <h3><?php echo safe(adminManagementText('spiritual_appointment')); ?></h3>
            <?php if (!$appointments): ?><div class="empty"><?php echo safe(adminManagementText('spiritual_no_appointments')); ?></div><?php else: ?><div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('spiritual_appointment_type')); ?></th><th><?php echo safe(adminManagementText('spiritual_person_name')); ?></th><th><?php echo safe(adminManagementText('spiritual_starts_at')); ?></th><th><?php echo safe(adminManagementText('spiritual_assigned_to')); ?></th><th><?php echo safe(adminManagementText('spiritual_private_notes')); ?></th><th><?php echo safe(adminManagementText('spiritual_appointment_status')); ?></th></tr></thead><tbody><?php foreach ($appointments as $appointment): ?><tr><td><?php echo safe(adminManagementText($appointmentTypeKeys[$appointment['appointment_type']])); ?></td><td><?php echo safe($appointment['person_name']); ?></td><td><?php echo safe($appointment['starts_at']); ?></td><td><?php echo safe($appointment['assigned_name'] ?? ''); ?></td><td><?php echo safe($appointment['private_notes'] ?? ''); ?></td><td><form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="set_appointment_status"><input type="hidden" name="appointment_id" value="<?php echo (int)$appointment['id']; ?>"><select name="status" aria-label="<?php echo safe(adminManagementText('spiritual_appointment_status')); ?>"><?php foreach ($appointmentStatusKeys as $status => $key): ?><option value="<?php echo safe($status); ?>" <?php echo $appointment['status'] === $status ? 'selected' : ''; ?>><?php echo safe(adminManagementText($key)); ?></option><?php endforeach; ?></select><button class="submit" style="margin:0" type="submit"><?php echo safe(adminManagementText('education_toggle_status')); ?></button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        </section>
    </div>
    <?php endif; ?>
</main>
</body>
</html>