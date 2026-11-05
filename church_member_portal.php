<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/management_schema.php';
require_once __DIR__ . '/admin_management_i18n.php';
require_once __DIR__ . '/mail_config.php';
requireRole(['student'], $pdo);
if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit(adminManagementText('db_unavailable'));
}
ensureManagementTables($pdo);
$csrf = csrfToken();
$studentId = (string)($_SESSION['student_id'] ?? '');
$studentStmt = $pdo->prepare('SELECT email, email_verified FROM students WHERE student_id = :student_id LIMIT 1');
$studentStmt->execute([':student_id' => $studentId]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC) ?: null;
$memberRows = [];
if ($student && !empty($student['email']) && (int)$student['email_verified'] === 1) {
    $memberStmt = $pdo->prepare('SELECT m.id, m.member_number, m.full_name, m.phone, m.address, m.status, m.branch_id, b.branch_name FROM church_members m LEFT JOIN church_branches b ON b.id = m.branch_id WHERE m.email = :email LIMIT 2');
    $memberStmt->execute([':email' => $student['email']]);
    $memberRows = $memberStmt->fetchAll(PDO::FETCH_ASSOC);
}
$member = count($memberRows) === 1 ? $memberRows[0] : null;
$errorKey = '';
$saved = isset($_GET['saved']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $member && $student && (int)$student['email_verified'] === 1) {
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(400);
        exit(adminManagementText('csrf_error'));
    }

    $requested = [
        'full_name' => trim((string)($_POST['full_name'] ?? '')),
        'phone' => trim((string)($_POST['phone'] ?? '')),
        'address' => trim((string)($_POST['address'] ?? '')),
    ];
    if ($requested['full_name'] === '' || strlen($requested['full_name']) > 180 || strlen($requested['phone']) > 40 || strlen($requested['address']) > 255) {
        $errorKey = 'member_portal_validation';
    } else {
        $changes = [];
        foreach ($requested as $field => $value) {
            if ((string)($member[$field] ?? '') !== $value) {
                $changes[$field] = $value;
            }
        }
        if (!$changes) {
            $errorKey = 'member_portal_empty_changes';
        } else {
            $pendingStmt = $pdo->prepare("SELECT id FROM church_member_change_requests WHERE member_id = :member_id AND status = 'pending' LIMIT 1");
            $pendingStmt->execute([':member_id' => (int)$member['id']]);
            if ($pendingStmt->fetchColumn()) {
                $errorKey = 'member_portal_pending_exists';
            } else {
                try {
                    $insert = $pdo->prepare('INSERT INTO church_member_change_requests (member_id, requested_by_student_id, requested_changes) VALUES (:member_id, :student_id, :changes)');
                    $insert->execute([
                        ':member_id' => (int)$member['id'],
                        ':student_id' => $studentId,
                        ':changes' => json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    ]);
                    $requestId = (int)$pdo->lastInsertId();
                    $mailBody = '<p>' . htmlspecialchars(adminManagementText('member_portal_request_saved'), ENT_QUOTES, 'UTF-8') . '</p>'
                        . '<p>' . htmlspecialchars($member['full_name'], ENT_QUOTES, 'UTF-8') . ' · ' . htmlspecialchars($member['member_number'], ENT_QUOTES, 'UTF-8') . '</p>';
                    $mailSent = sendAppEmail((string)$student['email'], adminManagementText('member_portal_title'), $mailBody);
                    $notificationStatus = $mailSent ? 'sent' : 'failed';
                    $notifyStmt = $pdo->prepare('UPDATE church_member_change_requests SET notification_status = :status WHERE id = :id');
                    $notifyStmt->execute([':status' => $notificationStatus, ':id' => $requestId]);
                    header('Location: church_member_portal.php?lang=' . urlencode($adminManagementLanguage) . '&saved=1');
                    exit;
                } catch (Throwable $exception) {
                    error_log('Member change request failed: ' . $exception->getMessage());
                    $errorKey = 'member_save_error';
                }
            }
        }
    }
}

$requests = [];
if ($member) {
    $requestStmt = $pdo->prepare('SELECT id, requested_changes, status, review_note, created_at, reviewed_at FROM church_member_change_requests WHERE member_id = :member_id AND requested_by_student_id = :student_id ORDER BY created_at DESC LIMIT 20');
    $requestStmt->execute([':member_id' => (int)$member['id'], ':student_id' => $studentId]);
    $requests = $requestStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe(adminManagementText('member_portal_title')); ?></title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f2f5f3;color:#202b27;font-family:Arial,sans-serif}.topbar{background:#143b36;color:#fff;padding:16px max(20px,calc((100% - 1040px)/2));display:flex;justify-content:space-between;align-items:center;gap:12px}.topbar h1{font-size:20px;margin:0}.topbar a{color:#fff;text-decoration:none;border:1px solid #739188;padding:9px 12px;border-radius:4px}.wrap{max-width:1040px;margin:22px auto;padding:0 18px}.intro{margin-bottom:16px;color:#65736e}.columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.section{background:#fff;border-top:2px solid #d7e2dc;padding:18px;margin-bottom:16px;min-width:0}.section h2{font-size:17px;margin:0 0 14px}.identity{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.field{display:flex;flex-direction:column;gap:5px}.field.full{grid-column:1/-1}label,.label{font-size:13px;font-weight:600}.value{padding:10px;background:#f5f8f6;border:1px solid #e5ebe7;min-height:40px}.input,textarea{width:100%;padding:10px;border:1px solid #cbd5d1;border-radius:4px;font:inherit;background:#fff}textarea{min-height:80px;resize:vertical}.button{padding:9px 13px;border:0;border-radius:4px;background:#236c5e;color:#fff;text-decoration:none;font:inherit;cursor:pointer}.secondary{background:#fff;color:#236c5e;border:1px solid #236c5e}.notice{padding:11px 13px;background:#e8f4ef;color:#176244;margin-bottom:12px}.error{background:#fff0eb;color:#9b351f}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;padding:9px 8px;border-bottom:1px solid #e5ebe7;vertical-align:top}th{background:#f3f7f4;white-space:nowrap}.status{font-weight:700}.pending{color:#9a6518}.approved{color:#176244}.rejected{color:#9b351f}.empty{color:#65736e}.request-values{white-space:pre-wrap;min-width:180px}@media(max-width:680px){.topbar{align-items:flex-start;flex-direction:column}.columns,.identity{grid-template-columns:1fr}}
    </style>
</head>
<body>
<header class="topbar"><h1><?php echo safe(adminManagementText('member_portal_title')); ?></h1><nav><a href="<?php echo safe($adminManagementSwitchUrl); ?>"><?php echo safe(adminManagementText('language_switch')); ?></a> <a href="student_dashboard.php"><?php echo safe(adminManagementText('back_dashboard')); ?></a></nav></header>
<main class="wrap">
    <p class="intro"><?php echo safe(adminManagementText('member_portal_intro')); ?></p>
    <?php if ($saved): ?><div class="notice"><?php echo safe(adminManagementText('member_portal_request_saved')); ?></div><?php endif; ?>
    <?php if ($errorKey !== ''): ?><div class="notice error"><?php echo safe(adminManagementText($errorKey)); ?></div><?php endif; ?>
    <?php if (!$student || (int)$student['email_verified'] !== 1): ?>
        <section class="section"><p><?php echo safe(adminManagementText('member_portal_unverified')); ?></p><a class="button" href="verify_email.php"><?php echo safe(adminManagementText('member_portal_verify_email')); ?></a></section>
    <?php elseif (count($memberRows) > 1): ?>
        <section class="section"><p><?php echo safe(adminManagementText('member_portal_ambiguous')); ?></p></section>
    <?php elseif (!$member): ?>
        <section class="section"><p><?php echo safe(adminManagementText('member_portal_no_match')); ?></p></section>
    <?php else: ?>
        <div class="columns">
            <section class="section">
                <h2><?php echo safe(adminManagementText('member_portal_profile')); ?></h2>
                <div class="identity">
                    <div class="field"><span class="label"><?php echo safe(adminManagementText('member_number')); ?></span><span class="value"><?php echo safe($member['member_number']); ?></span></div>
                    <div class="field"><span class="label"><?php echo safe(adminManagementText('status')); ?></span><span class="value"><?php echo safe(adminManagementText($member['status'] === 'active' ? 'member_active' : 'member_inactive')); ?></span></div>
                    <div class="field"><span class="label"><?php echo safe(adminManagementText('member_portal_field_name')); ?></span><span class="value"><?php echo safe($member['full_name']); ?></span></div>
                    <div class="field"><span class="label"><?php echo safe(adminManagementText('email')); ?></span><span class="value"><?php echo safe($student['email']); ?></span></div>
                    <div class="field"><span class="label"><?php echo safe(adminManagementText('member_portal_field_phone')); ?></span><span class="value"><?php echo safe($member['phone'] ?? ''); ?></span></div>
                    <div class="field"><span class="label"><?php echo safe(adminManagementText('member_portal_field_address')); ?></span><span class="value"><?php echo safe($member['address'] ?? ''); ?></span></div>
                    <?php if (!empty($member['branch_name'])): ?><div class="field full"><span class="label"><?php echo safe(adminManagementText('meeting_branch')); ?></span><span class="value"><?php echo safe($member['branch_name']); ?></span></div><?php endif; ?>
                </div>
                <p><a class="button secondary" href="church_member_id_card.php?id=<?php echo (int)$member['id']; ?>&lang=<?php echo safe($adminManagementLanguage); ?>"><?php echo safe(adminManagementText('member_portal_card')); ?></a></p>
            </section>
            <section class="section">
                <h2><?php echo safe(adminManagementText('member_portal_request')); ?></h2>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>">
                    <div class="field"><label for="full_name"><?php echo safe(adminManagementText('member_portal_field_name')); ?></label><input class="input" id="full_name" name="full_name" maxlength="180" required value="<?php echo safe($member['full_name']); ?>"></div>
                    <div class="field"><label for="phone"><?php echo safe(adminManagementText('member_portal_field_phone')); ?></label><input class="input" id="phone" name="phone" maxlength="40" value="<?php echo safe($member['phone'] ?? ''); ?>"></div>
                    <div class="field"><label for="address"><?php echo safe(adminManagementText('member_portal_field_address')); ?></label><textarea id="address" name="address" maxlength="255"><?php echo safe($member['address'] ?? ''); ?></textarea></div>
                    <button class="button" type="submit"><?php echo safe(adminManagementText('member_portal_request')); ?></button>
                </form>
            </section>
        </div>
        <section class="section">
            <h2><?php echo safe(adminManagementText('member_portal_request_history')); ?></h2>
            <?php if (!$requests): ?><p class="empty"><?php echo safe(adminManagementText('member_portal_no_requests')); ?></p><?php else: ?>
                <div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('date')); ?></th><th><?php echo safe(adminManagementText('member_portal_request_status')); ?></th><th><?php echo safe(adminManagementText('member_portal_request')); ?></th><th><?php echo safe(adminManagementText('member_portal_review_note')); ?></th></tr></thead><tbody>
                <?php foreach ($requests as $request): $changes = json_decode((string)$request['requested_changes'], true) ?: []; $changeLabels = ['full_name' => 'member_portal_field_name', 'phone' => 'member_portal_field_phone', 'address' => 'member_portal_field_address']; $statusKey = ['pending' => 'member_portal_pending', 'approved' => 'member_portal_approved', 'rejected' => 'member_portal_rejected'][$request['status']] ?? 'member_portal_pending'; ?>
                    <tr><td><?php echo safe($request['created_at']); ?></td><td class="status <?php echo safe($request['status']); ?>"><?php echo safe(adminManagementText($statusKey)); ?></td><td class="request-values"><?php foreach ($changes as $field => $value): if (!isset($changeLabels[$field])) continue; ?><?php echo safe(adminManagementText($changeLabels[$field]) . ': ' . (string)$value); ?>
<?php endforeach; ?></td><td><?php echo safe($request['review_note'] ?? ''); ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
