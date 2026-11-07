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
$recordTypes = ['baptism', 'marriage', 'communion', 'confirmation', 'funeral', 'other'];
$typeLabelKeys = [
    'baptism' => 'sacramental_baptism',
    'marriage' => 'sacramental_marriage',
    'communion' => 'sacramental_communion',
    'confirmation' => 'sacramental_confirmation',
    'funeral' => 'sacramental_funeral',
    'other' => 'sacramental_other',
];
$error = '';
$message = '';
$editingId = filter_var($_GET['edit'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?: 0;
$editingRecord = null;

if ($editingId > 0) {
    $editStmt = $pdo->prepare('SELECT * FROM church_sacramental_records WHERE id = :id LIMIT 1');
    $editStmt->execute([':id' => $editingId]);
    $editingRecord = $editStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $error = adminManagementText('csrf_error');
    } else {
        $recordId = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $recordType = (string)($_POST['record_type'] ?? '');
        $memberIdInput = trim((string)($_POST['member_id'] ?? ''));
        $memberId = $memberIdInput === '' ? 0 : filter_var($memberIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $participantName = trim((string)($_POST['participant_name'] ?? ''));
        $otherParticipantName = trim((string)($_POST['other_participant_name'] ?? ''));
        $eventDate = (string)($_POST['event_date'] ?? '');
        $eventDateObject = DateTime::createFromFormat('!Y-m-d', $eventDate);
        $officiant = trim((string)($_POST['officiant'] ?? ''));
        $branchIdInput = trim((string)($_POST['branch_id'] ?? ''));
        $branchId = $branchIdInput === '' ? 0 : filter_var($branchIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $location = trim((string)($_POST['location'] ?? ''));
        $sponsorNames = trim((string)($_POST['sponsor_names'] ?? ''));
        $certificateNumber = trim((string)($_POST['certificate_number'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        $memberValid = $memberId === 0;
        if ($memberId !== 0 && $memberId !== false) {
            $memberStmt = $pdo->prepare('SELECT id FROM church_members WHERE id = :id LIMIT 1');
            $memberStmt->execute([':id' => (int)$memberId]);
            $memberValid = (bool)$memberStmt->fetchColumn();
        }
        $branchValid = $branchId === 0;
        if ($branchId !== 0 && $branchId !== false) {
            $branchStmt = $pdo->prepare('SELECT id FROM church_branches WHERE id = :id LIMIT 1');
            $branchStmt->execute([':id' => (int)$branchId]);
            $branchValid = (bool)$branchStmt->fetchColumn();
        }
        $recordExists = true;
        if ($recordId !== false && (int)$recordId > 0) {
            $existsStmt = $pdo->prepare('SELECT id FROM church_sacramental_records WHERE id = :id LIMIT 1');
            $existsStmt->execute([':id' => (int)$recordId]);
            $recordExists = (bool)$existsStmt->fetchColumn();
        }

        if ($recordId === false || !$recordExists || !in_array($recordType, $recordTypes, true) || !$memberValid || !$branchValid || $participantName === '' || strlen($participantName) > 180 || strlen($otherParticipantName) > 180 || strlen($officiant) > 180 || strlen($location) > 180 || strlen($sponsorNames) > 500 || strlen($certificateNumber) > 80 || strlen($notes) > 10000 || !$eventDateObject || $eventDateObject->format('Y-m-d') !== $eventDate) {
            $error = adminManagementText('sacramental_validation_error');
        } else {
            try {
                $values = [
                    ':record_type' => $recordType,
                    ':member_id' => $memberId > 0 ? (int)$memberId : null,
                    ':participant_name' => $participantName,
                    ':other_participant_name' => $otherParticipantName !== '' ? $otherParticipantName : null,
                    ':event_date' => $eventDate,
                    ':officiant' => $officiant !== '' ? $officiant : null,
                    ':branch_id' => $branchId > 0 ? (int)$branchId : null,
                    ':location' => $location !== '' ? $location : null,
                    ':sponsor_names' => $sponsorNames !== '' ? $sponsorNames : null,
                    ':certificate_number' => $certificateNumber !== '' ? $certificateNumber : null,
                    ':notes' => $notes !== '' ? $notes : null,
                ];
                if ((int)$recordId > 0) {
                    $values[':id'] = (int)$recordId;
                    $stmt = $pdo->prepare('UPDATE church_sacramental_records SET record_type = :record_type, member_id = :member_id, participant_name = :participant_name, other_participant_name = :other_participant_name, event_date = :event_date, officiant = :officiant, branch_id = :branch_id, location = :location, sponsor_names = :sponsor_names, certificate_number = :certificate_number, notes = :notes WHERE id = :id');
                    $stmt->execute($values);
                } else {
                    $values[':created_by'] = (int)($_SESSION['admin_id'] ?? 0);
                    $stmt = $pdo->prepare('INSERT INTO church_sacramental_records (record_type, member_id, participant_name, other_participant_name, event_date, officiant, branch_id, location, sponsor_names, certificate_number, notes, created_by) VALUES (:record_type, :member_id, :participant_name, :other_participant_name, :event_date, :officiant, :branch_id, :location, :sponsor_names, :certificate_number, :notes, :created_by)');
                    $stmt->execute($values);
                    $recordNumber = 'SAC-' . str_pad((string)$pdo->lastInsertId(), 8, '0', STR_PAD_LEFT);
                    $numberStmt = $pdo->prepare('UPDATE church_sacramental_records SET record_number = :record_number WHERE id = :id');
                    $numberStmt->execute([':record_number' => $recordNumber, ':id' => (int)$pdo->lastInsertId()]);
                }
                header('Location: admin_sacramental_records.php?lang=' . urlencode($adminManagementLanguage) . '&saved=1');
                exit;
            } catch (PDOException $exception) {
                error_log('Sacramental record save failed: ' . $exception->getMessage());
                $error = $exception->getCode() === '23000'
                    ? adminManagementText('sacramental_duplicate_certificate')
                    : adminManagementText('sacramental_save_error');
            } catch (Throwable $exception) {
                error_log('Sacramental record save failed: ' . $exception->getMessage());
                $error = adminManagementText('sacramental_save_error');
            }
        }
    }
}

if (isset($_GET['saved'])) {
    $message = adminManagementText('sacramental_saved');
}
$formData = array_merge($editingRecord ?? [], $_POST);
$members = $pdo->query('SELECT id, member_number, full_name FROM church_members ORDER BY full_name ASC')->fetchAll(PDO::FETCH_ASSOC);
$branches = $pdo->query('SELECT id, branch_code, branch_name FROM church_branches ORDER BY branch_name ASC')->fetchAll(PDO::FETCH_ASSOC);
$search = trim((string)($_GET['q'] ?? ''));
$typeFilter = (string)($_GET['record_type'] ?? '');
if (!in_array($typeFilter, $recordTypes, true)) {
    $typeFilter = '';
}
$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = '(r.record_number LIKE :record_number OR r.participant_name LIKE :participant_name OR r.other_participant_name LIKE :other_participant_name OR r.certificate_number LIKE :certificate_number OR m.full_name LIKE :member_name)';
    foreach (['record_number', 'participant_name', 'other_participant_name', 'certificate_number', 'member_name'] as $parameter) {
        $params[':' . $parameter] = '%' . $search . '%';
    }
}
if ($typeFilter !== '') {
    $conditions[] = 'r.record_type = :record_type';
    $params[':record_type'] = $typeFilter;
}
$query = 'SELECT r.*, m.member_number, m.full_name AS linked_member_name, b.branch_name FROM church_sacramental_records r LEFT JOIN church_members m ON m.id = r.member_id LEFT JOIN church_branches b ON b.id = r.branch_id';
if ($conditions) {
    $query .= ' WHERE ' . implode(' AND ', $conditions);
}
$query .= ' ORDER BY r.event_date DESC, r.id DESC LIMIT 200';
$recordsStmt = $pdo->prepare($query);
$recordsStmt->execute($params);
$records = $recordsStmt->fetchAll(PDO::FETCH_ASSOC);
$totalRecords = (int)$pdo->query('SELECT COUNT(*) FROM church_sacramental_records')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe(adminManagementText('sacramental_title')); ?></title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f3f6f4;color:#202b27;font-family:Arial,sans-serif}.topbar{background:#143b36;color:#fff;padding:16px max(20px,calc((100% - 1220px)/2));display:flex;justify-content:space-between;align-items:center;gap:12px}.topbar h1{font-size:20px;margin:0}.topbar nav{display:flex;gap:8px;flex-wrap:wrap}.topbar a,.button{color:#fff;text-decoration:none;background:#236c5e;border:0;padding:9px 12px;border-radius:4px;font:inherit;cursor:pointer}.wrap{max-width:1220px;margin:22px auto;padding:0 18px}.summary{display:flex;gap:10px;margin-bottom:14px}.metric,.panel{background:#fff;border-top:2px solid #d7e2dc;padding:16px;min-width:0}.metric span{display:block;color:#63716c;font-size:13px}.metric strong{display:block;font-size:22px;margin-top:5px}.layout{display:grid;grid-template-columns:minmax(290px,.85fr) minmax(0,1.7fr);gap:16px}.panel h2{font-size:17px;margin:0 0 14px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.field{display:flex;flex-direction:column;gap:5px}.field.full{grid-column:1/-1}label{font-size:13px;font-weight:600}input,select,textarea{width:100%;padding:9px;border:1px solid #cbd5d1;border-radius:4px;background:#fff;color:inherit;font:inherit}textarea{min-height:75px;resize:vertical}.button{display:inline-block}.button.secondary{color:#236c5e;background:#fff;border:1px solid #236c5e}.notice{padding:10px 12px;margin-bottom:12px;background:#e8f4ef;color:#176244}.notice.error{background:#fff0eb;color:#9b351f}.toolbar{display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:12px}.filters{display:flex;gap:8px;align-items:end;flex-wrap:wrap}.filters .field{margin:0}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;padding:9px 8px;border-bottom:1px solid #e4ebe7;vertical-align:top}th{background:#f3f7f4;white-space:nowrap}.muted{color:#64736e;font-size:12px}.actions a{color:#236c5e}@media(max-width:900px){.layout{grid-template-columns:1fr}.topbar{align-items:flex-start;flex-direction:column}}@media(max-width:560px){.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.wrap{padding:0 12px}}
    </style>
</head>
<body>
<header class="topbar"><h1><?php echo safe(adminManagementText('sacramental_title')); ?></h1><nav><a href="<?php echo safe($adminManagementSwitchUrl); ?>"><?php echo safe(adminManagementText('language_switch')); ?></a><a href="admin_management_dashboard.php?lang=<?php echo safe($adminManagementLanguage); ?>"><?php echo safe(adminManagementText('back_dashboard')); ?></a></nav></header>
<main class="wrap">
    <?php if ($message !== ''): ?><div class="notice"><?php echo safe($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error"><?php echo safe($error); ?></div><?php endif; ?>
    <div class="summary"><div class="metric"><span><?php echo safe(adminManagementText('sacramental_total_records')); ?></span><strong><?php echo number_format($totalRecords); ?></strong></div></div>
    <div class="layout">
        <section class="panel">
            <h2><?php echo safe(adminManagementText($editingRecord ? 'sacramental_edit' : 'sacramental_new')); ?></h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>">
                <input type="hidden" name="id" value="<?php echo (int)($formData['id'] ?? 0); ?>">
                <div class="form-grid">
                    <div class="field"><label for="record_type"><?php echo safe(adminManagementText('sacramental_type')); ?></label><select id="record_type" name="record_type" required><?php foreach ($recordTypes as $type): ?><option value="<?php echo safe($type); ?>" <?php echo ($formData['record_type'] ?? 'baptism') === $type ? 'selected' : ''; ?>><?php echo safe(adminManagementText($typeLabelKeys[$type])); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="event_date"><?php echo safe(adminManagementText('sacramental_date')); ?></label><input id="event_date" name="event_date" type="date" value="<?php echo safe($formData['event_date'] ?? date('Y-m-d')); ?>" required></div>
                    <div class="field full"><label for="participant_name"><?php echo safe(adminManagementText('sacramental_participant')); ?></label><input id="participant_name" name="participant_name" maxlength="180" value="<?php echo safe($formData['participant_name'] ?? ''); ?>" required></div>
                    <div class="field"><label for="member_id"><?php echo safe(adminManagementText('sacramental_link_member')); ?></label><select id="member_id" name="member_id"><option value=""><?php echo safe(adminManagementText('sacramental_no_member_link')); ?></option><?php foreach ($members as $member): ?><option value="<?php echo (int)$member['id']; ?>" <?php echo (string)($formData['member_id'] ?? '') === (string)$member['id'] ? 'selected' : ''; ?>><?php echo safe($member['full_name'] . ' (' . $member['member_number'] . ')'); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="other_participant_name"><?php echo safe(adminManagementText('sacramental_other_participant')); ?></label><input id="other_participant_name" name="other_participant_name" maxlength="180" value="<?php echo safe($formData['other_participant_name'] ?? ''); ?>"></div>
                    <div class="field"><label for="officiant"><?php echo safe(adminManagementText('sacramental_officiant')); ?></label><input id="officiant" name="officiant" maxlength="180" value="<?php echo safe($formData['officiant'] ?? ''); ?>"></div>
                    <div class="field"><label for="branch_id"><?php echo safe(adminManagementText('meeting_branch')); ?></label><select id="branch_id" name="branch_id"><option value=""><?php echo safe(adminManagementText('meeting_no_branch')); ?></option><?php foreach ($branches as $branch): ?><option value="<?php echo (int)$branch['id']; ?>" <?php echo (string)($formData['branch_id'] ?? '') === (string)$branch['id'] ? 'selected' : ''; ?>><?php echo safe($branch['branch_code'] . ' · ' . $branch['branch_name']); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="location"><?php echo safe(adminManagementText('meeting_location')); ?></label><input id="location" name="location" maxlength="180" value="<?php echo safe($formData['location'] ?? ''); ?>"></div>
                    <div class="field full"><label for="sponsor_names"><?php echo safe(adminManagementText('sacramental_sponsors')); ?></label><input id="sponsor_names" name="sponsor_names" maxlength="500" value="<?php echo safe($formData['sponsor_names'] ?? ''); ?>"></div>
                    <div class="field full"><label for="certificate_number"><?php echo safe(adminManagementText('sacramental_certificate_number')); ?></label><input id="certificate_number" name="certificate_number" maxlength="80" value="<?php echo safe($formData['certificate_number'] ?? ''); ?>"></div>
                    <div class="field full"><label for="notes"><?php echo safe(adminManagementText('notes')); ?></label><textarea id="notes" name="notes" maxlength="10000"><?php echo safe($formData['notes'] ?? ''); ?></textarea></div>
                </div>
                <button class="button" type="submit"><?php echo safe(adminManagementText('sacramental_save')); ?></button><?php if ($editingRecord): ?> <a class="button secondary" href="admin_sacramental_records.php?lang=<?php echo safe($adminManagementLanguage); ?>"><?php echo safe(adminManagementText('cancel_edit')); ?></a><?php endif; ?>
            </form>
        </section>
        <section class="panel">
            <div class="toolbar"><h2><?php echo safe(adminManagementText('sacramental_records')); ?></h2><form class="filters" method="get"><input type="hidden" name="lang" value="<?php echo safe($adminManagementLanguage); ?>"><div class="field"><label for="search"><?php echo safe(adminManagementText('sacramental_search')); ?></label><input id="search" name="q" value="<?php echo safe($search); ?>" maxlength="180"></div><div class="field"><label for="filter_type"><?php echo safe(adminManagementText('sacramental_type')); ?></label><select id="filter_type" name="record_type"><option value=""><?php echo safe(adminManagementText('all')); ?></option><?php foreach ($recordTypes as $type): ?><option value="<?php echo safe($type); ?>" <?php echo $typeFilter === $type ? 'selected' : ''; ?>><?php echo safe(adminManagementText($typeLabelKeys[$type])); ?></option><?php endforeach; ?></select></div><button class="button" type="submit"><?php echo safe(adminManagementText('filter')); ?></button></form></div>
            <div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('sacramental_record_number')); ?></th><th><?php echo safe(adminManagementText('sacramental_type')); ?></th><th><?php echo safe(adminManagementText('sacramental_participant')); ?></th><th><?php echo safe(adminManagementText('sacramental_date')); ?></th><th><?php echo safe(adminManagementText('sacramental_officiant')); ?></th><th><?php echo safe(adminManagementText('sacramental_certificate_number')); ?></th><th><?php echo safe(adminManagementText('action')); ?></th></tr></thead><tbody>
            <?php if (!$records): ?><tr><td colspan="7"><?php echo safe(adminManagementText('sacramental_no_records')); ?></td></tr><?php else: foreach ($records as $record): ?>
                <tr><td><?php echo safe($record['record_number'] ?? ''); ?><br><span class="muted"><?php echo safe($record['branch_name'] ?? ''); ?></span></td><td><?php echo safe(adminManagementText($typeLabelKeys[$record['record_type']])); ?></td><td><?php echo safe($record['participant_name']); ?><?php if (!empty($record['other_participant_name'])): ?><br><span class="muted"><?php echo safe($record['other_participant_name']); ?></span><?php endif; ?><?php if (!empty($record['linked_member_name'])): ?><br><span class="muted"><?php echo safe($record['linked_member_name'] . ' · ' . ($record['member_number'] ?? '')); ?></span><?php endif; ?></td><td><?php echo safe($record['event_date']); ?></td><td><?php echo safe($record['officiant'] ?? '—'); ?></td><td><?php echo safe($record['certificate_number'] ?? '—'); ?></td><td><a href="admin_sacramental_records.php?lang=<?php echo safe($adminManagementLanguage); ?>&edit=<?php echo (int)$record['id']; ?>"><?php echo safe(adminManagementText('resource_edit_action')); ?></a></td></tr>
            <?php endforeach; endif; ?>
            </tbody></table></div>
            <p class="muted"><?php echo safe(adminManagementText('sacramental_limit_note')); ?></p>
        </section>
    </div>
</main>
</body>
</html>
