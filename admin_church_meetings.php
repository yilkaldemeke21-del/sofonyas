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
$message = '';
$editingId = (int)($_GET['edit'] ?? 0);
$editingMeeting = null;

if ($editingId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM church_meetings WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $editingId]);
    $editingMeeting = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(400);
        exit(adminManagementText('csrf_error'));
    }

    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save_meeting') {
        $meetingId = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $meetingAtInput = (string)($_POST['meeting_at'] ?? '');
        $meetingAt = DateTime::createFromFormat('!Y-m-d\TH:i', $meetingAtInput);
        $branchId = (int)($_POST['branch_id'] ?? 0);
        $location = trim((string)($_POST['location'] ?? ''));
        $attendees = trim((string)($_POST['attendees'] ?? ''));
        $minutes = trim((string)($_POST['minutes'] ?? ''));
        $decisions = trim((string)($_POST['decisions'] ?? ''));
        $status = (string)($_POST['status'] ?? 'scheduled');
        $branchValid = true;
        if ($branchId > 0) {
            $branchCheck = $pdo->prepare('SELECT id FROM church_branches WHERE id = :id LIMIT 1');
            $branchCheck->execute([':id' => $branchId]);
            $branchValid = (bool)$branchCheck->fetchColumn();
        }

        if ($title === '' || strlen($title) > 180 || !$meetingAt || $meetingAt->format('Y-m-d\TH:i') !== $meetingAtInput || strlen($location) > 180 || strlen($attendees) > 20000 || strlen($minutes) > 50000 || strlen($decisions) > 20000 || !in_array($status, ['scheduled', 'completed', 'cancelled'], true) || !$branchValid) {
            $error = adminManagementText('meeting_validation_error');
        } else {
            try {
                if ($meetingId > 0) {
                    $stmt = $pdo->prepare('UPDATE church_meetings SET branch_id = :branch_id, title = :title, meeting_at = :meeting_at, location = :location, attendees = :attendees, minutes = :minutes, decisions = :decisions, status = :status WHERE id = :id');
                    $stmt->execute([
                        ':branch_id' => $branchId > 0 ? $branchId : null,
                        ':title' => $title,
                        ':meeting_at' => $meetingAt->format('Y-m-d H:i:s'),
                        ':location' => $location !== '' ? $location : null,
                        ':attendees' => $attendees !== '' ? $attendees : null,
                        ':minutes' => $minutes !== '' ? $minutes : null,
                        ':decisions' => $decisions !== '' ? $decisions : null,
                        ':status' => $status,
                        ':id' => $meetingId,
                    ]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO church_meetings (branch_id, title, meeting_at, location, attendees, minutes, decisions, status, created_by) VALUES (:branch_id, :title, :meeting_at, :location, :attendees, :minutes, :decisions, :status, :created_by)');
                    $stmt->execute([
                        ':branch_id' => $branchId > 0 ? $branchId : null,
                        ':title' => $title,
                        ':meeting_at' => $meetingAt->format('Y-m-d H:i:s'),
                        ':location' => $location !== '' ? $location : null,
                        ':attendees' => $attendees !== '' ? $attendees : null,
                        ':minutes' => $minutes !== '' ? $minutes : null,
                        ':decisions' => $decisions !== '' ? $decisions : null,
                        ':status' => $status,
                        ':created_by' => (int)$_SESSION['admin_id'],
                    ]);
                }
                header('Location: admin_church_meetings.php?saved=1');
                exit;
            } catch (Throwable $exception) {
                error_log('Church meeting save failed: ' . $exception->getMessage());
                $error = adminManagementText('meeting_save_error');
            }
        }
    } elseif ($action === 'set_status') {
        $meetingId = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if ($meetingId > 0 && in_array($status, ['scheduled', 'completed', 'cancelled'], true)) {
            $stmt = $pdo->prepare('UPDATE church_meetings SET status = :status WHERE id = :id');
            $stmt->execute([':status' => $status, ':id' => $meetingId]);
            header('Location: admin_church_meetings.php');
            exit;
        }
        $error = adminManagementText('meeting_status_error');
    }
}

if (isset($_GET['saved'])) {
    $message = adminManagementText('meeting_saved');
}
$branches = $pdo->query('SELECT id, branch_name, status FROM church_branches ORDER BY status ASC, branch_name ASC')->fetchAll(PDO::FETCH_ASSOC);
$search = trim((string)($_GET['q'] ?? ''));
$branchFilter = (int)($_GET['branch_id'] ?? 0);
$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = '(m.title LIKE :title OR m.location LIKE :location)';
    $params[':title'] = '%' . $search . '%';
    $params[':location'] = '%' . $search . '%';
}
if ($branchFilter > 0) {
    $conditions[] = 'm.branch_id = :branch_id';
    $params[':branch_id'] = $branchFilter;
}
$query = 'SELECT m.*, b.branch_name FROM church_meetings m LEFT JOIN church_branches b ON b.id = m.branch_id';
if ($conditions) {
    $query .= ' WHERE ' . implode(' AND ', $conditions);
}
$query .= ' ORDER BY m.meeting_at DESC, m.id DESC LIMIT 100';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe(adminManagementText('meeting_title')); ?></title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f2f5f3;color:#202b27;font-family:Arial,sans-serif}.topbar{background:#143b36;color:#fff;padding:16px max(20px,calc((100% - 1220px)/2));display:flex;justify-content:space-between;align-items:center;gap:12px}.topbar h1{font-size:20px;margin:0}.topbar a{color:#fff;text-decoration:none;border:1px solid #739188;padding:9px 12px;border-radius:4px}.wrap{max-width:1220px;margin:22px auto;padding:0 18px}.section{background:#fff;padding:18px;border-top:2px solid #d7e2dc;margin-bottom:16px;min-width:0}.section h2{font-size:17px;margin:0 0 14px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.field{display:flex;flex-direction:column;gap:5px}.field.full{grid-column:1/-1}label{font-size:13px;font-weight:600}input,select,textarea{width:100%;padding:9px;border:1px solid #cbd5d1;border-radius:4px;font:inherit;background:#fff}textarea{min-height:110px;resize:vertical}.button{padding:9px 13px;border:0;border-radius:4px;background:#236c5e;color:#fff;text-decoration:none;font:inherit;cursor:pointer}.secondary{background:#fff;color:#236c5e;border:1px solid #236c5e}.notice{padding:10px 12px;background:#e8f4ef;color:#176244;margin-bottom:12px}.error{background:#fff0eb;color:#9b351f}.toolbar{display:flex;justify-content:space-between;align-items:end;gap:12px;flex-wrap:wrap;margin-bottom:12px}.filters{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.filters input{width:min(300px,100%)}.filters select{width:auto}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;padding:10px 8px;border-bottom:1px solid #e5ebe7;vertical-align:top}th{background:#f3f7f4;white-space:nowrap}.minutes{white-space:pre-wrap;min-width:180px;max-width:320px}.actions{display:flex;gap:8px;align-items:center;white-space:nowrap}.actions a,.actions button{color:#236c5e;background:none;border:0;padding:0;text-decoration:underline;cursor:pointer;font:inherit}.empty{color:#65736e}@media(max-width:700px){.topbar{align-items:flex-start;flex-direction:column}.form-grid{grid-template-columns:1fr}.filters{align-items:stretch;flex-direction:column}.filters input,.filters select,.filters button{width:100%}}
    </style>
</head>
<body>
<header class="topbar"><h1><?php echo safe(adminManagementText('meeting_title')); ?></h1><nav><a href="<?php echo safe($adminManagementSwitchUrl); ?>"><?php echo safe(adminManagementText('language_switch')); ?></a> <a href="admin_church_branches.php?lang=<?php echo safe($adminManagementLanguage); ?>"><?php echo safe(adminManagementText('branch_title')); ?></a> <a href="admin_management_dashboard.php"><?php echo safe(adminManagementText('back_dashboard')); ?></a></nav></header>
<main class="wrap">
    <?php if ($message !== ''): ?><div class="notice"><?php echo safe($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error"><?php echo safe($error); ?></div><?php endif; ?>
    <section class="section">
        <h2><?php echo safe(adminManagementText($editingMeeting ? 'meeting_edit' : 'meeting_new')); ?></h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>">
            <input type="hidden" name="action" value="save_meeting">
            <input type="hidden" name="id" value="<?php echo (int)($editingMeeting['id'] ?? 0); ?>">
            <div class="form-grid">
                <div class="field"><label for="title"><?php echo safe(adminManagementText('meeting_name')); ?></label><input id="title" name="title" maxlength="180" value="<?php echo safe($editingMeeting['title'] ?? ''); ?>" required></div>
                <div class="field"><label for="meeting_at"><?php echo safe(adminManagementText('meeting_datetime')); ?></label><input id="meeting_at" name="meeting_at" type="datetime-local" value="<?php echo safe(isset($editingMeeting['meeting_at']) ? date('Y-m-d\TH:i', strtotime($editingMeeting['meeting_at'])) : ''); ?>" required></div>
                <div class="field"><label for="branch_id"><?php echo safe(adminManagementText('meeting_branch')); ?></label><select id="branch_id" name="branch_id"><option value="0"><?php echo safe(adminManagementText('meeting_no_branch')); ?></option><?php foreach ($branches as $branch): ?><option value="<?php echo (int)$branch['id']; ?>" <?php echo (int)($editingMeeting['branch_id'] ?? 0) === (int)$branch['id'] ? 'selected' : ''; ?>><?php echo safe($branch['branch_name'] . ($branch['status'] === 'active' ? '' : ' · ' . adminManagementText('branch_inactive'))); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="location"><?php echo safe(adminManagementText('meeting_location')); ?></label><input id="location" name="location" maxlength="180" value="<?php echo safe($editingMeeting['location'] ?? ''); ?>"></div>
                <div class="field"><label for="status"><?php echo safe(adminManagementText('meeting_status')); ?></label><select id="status" name="status"><?php foreach (['scheduled' => 'meeting_scheduled', 'completed' => 'meeting_completed', 'cancelled' => 'meeting_cancelled'] as $statusValue => $statusKey): ?><option value="<?php echo safe($statusValue); ?>" <?php echo ($editingMeeting['status'] ?? 'scheduled') === $statusValue ? 'selected' : ''; ?>><?php echo safe(adminManagementText($statusKey)); ?></option><?php endforeach; ?></select></div>
                <div class="field full"><label for="attendees"><?php echo safe(adminManagementText('meeting_attendees')); ?></label><textarea id="attendees" name="attendees" maxlength="20000"><?php echo safe($editingMeeting['attendees'] ?? ''); ?></textarea></div>
                <div class="field full"><label for="minutes"><?php echo safe(adminManagementText('meeting_minutes')); ?></label><textarea id="minutes" name="minutes" maxlength="50000"><?php echo safe($editingMeeting['minutes'] ?? ''); ?></textarea></div>
                <div class="field full"><label for="decisions"><?php echo safe(adminManagementText('meeting_decisions')); ?></label><textarea id="decisions" name="decisions" maxlength="20000"><?php echo safe($editingMeeting['decisions'] ?? ''); ?></textarea></div>
            </div>
            <button class="button" type="submit"><?php echo safe(adminManagementText('meeting_save')); ?></button><?php if ($editingMeeting): ?> <a class="button secondary" href="admin_church_meetings.php?lang=<?php echo safe($adminManagementLanguage); ?>"><?php echo safe(adminManagementText('cancel_edit')); ?></a><?php endif; ?>
        </form>
    </section>
    <section class="section">
        <div class="toolbar"><h2><?php echo safe(adminManagementText('meeting_records')); ?></h2><form class="filters" method="get"><input type="hidden" name="lang" value="<?php echo safe($adminManagementLanguage); ?>"><input name="q" value="<?php echo safe($search); ?>" placeholder="<?php echo safe(adminManagementText('meeting_search')); ?>"><select name="branch_id"><option value="0"><?php echo safe(adminManagementText('meeting_all_branches')); ?></option><?php foreach ($branches as $branch): ?><option value="<?php echo (int)$branch['id']; ?>" <?php echo $branchFilter === (int)$branch['id'] ? 'selected' : ''; ?>><?php echo safe($branch['branch_name']); ?></option><?php endforeach; ?></select><button class="button" type="submit"><?php echo safe(adminManagementText('meeting_filter')); ?></button></form></div>
        <div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('meeting_datetime')); ?></th><th><?php echo safe(adminManagementText('meeting_name')); ?></th><th><?php echo safe(adminManagementText('meeting_branch')); ?></th><th><?php echo safe(adminManagementText('meeting_attendees')); ?></th><th><?php echo safe(adminManagementText('meeting_minutes')); ?></th><th><?php echo safe(adminManagementText('meeting_decisions')); ?></th><th><?php echo safe(adminManagementText('meeting_status')); ?></th><th><?php echo safe(adminManagementText('action')); ?></th></tr></thead><tbody>
        <?php if (!$meetings): ?><tr><td class="empty" colspan="8"><?php echo safe(adminManagementText('meeting_no_records')); ?></td></tr><?php else: foreach ($meetings as $meeting): ?><tr><td><?php echo safe($meeting['meeting_at']); ?><br><small><?php echo safe($meeting['location'] ?? ''); ?></small></td><td><?php echo safe($meeting['title']); ?></td><td><?php echo safe($meeting['branch_name'] ?? '—'); ?></td><td class="minutes"><?php echo safe($meeting['attendees'] ?? ''); ?></td><td class="minutes"><?php echo safe($meeting['minutes'] ?? ''); ?></td><td class="minutes"><?php echo safe($meeting['decisions'] ?? ''); ?></td><td><?php echo safe(adminManagementText(['scheduled' => 'meeting_scheduled', 'completed' => 'meeting_completed', 'cancelled' => 'meeting_cancelled'][$meeting['status']] ?? 'meeting_scheduled')); ?></td><td><div class="actions"><a href="admin_church_meetings.php?lang=<?php echo safe($adminManagementLanguage); ?>&edit=<?php echo (int)$meeting['id']; ?>"><?php echo safe(adminManagementText('resource_edit_action')); ?></a><form method="post"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="set_status"><input type="hidden" name="id" value="<?php echo (int)$meeting['id']; ?>"><select name="status" aria-label="<?php echo safe(adminManagementText('meeting_status')); ?>"><option value="scheduled" <?php echo $meeting['status'] === 'scheduled' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('meeting_scheduled')); ?></option><option value="completed" <?php echo $meeting['status'] === 'completed' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('meeting_completed')); ?></option><option value="cancelled" <?php echo $meeting['status'] === 'cancelled' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('meeting_cancelled')); ?></option></select><button type="submit"><?php echo safe(adminManagementText('update_record')); ?></button></form></div></td></tr><?php endforeach; endif; ?>
        </tbody></table></div>
        <p class="empty"><?php echo safe(adminManagementText('meeting_limit_note')); ?></p>
    </section>
</main>
</body>
</html>
