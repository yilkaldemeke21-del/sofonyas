<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/management_schema.php';
require_once __DIR__ . '/admin_management_i18n.php';
require_once __DIR__ . '/finance_security.php';
requireRole(['admin'], $pdo);

if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit(adminManagementText('db_unavailable'));
}

ensureManagementTables($pdo);
$csrf = csrfToken();
$caseTypes = ['food', 'housing', 'medical', 'education', 'financial', 'other'];
$statusLabels = [
    'pending' => 'benevolence_pending',
    'approved' => 'benevolence_approved',
    'declined' => 'benevolence_declined',
    'disbursed' => 'benevolence_disbursed',
    'closed' => 'benevolence_closed',
];
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $error = adminManagementText('csrf_error');
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'create_case') {
                $memberId = (int)($_POST['member_id'] ?? 0);
                $caseType = (string)($_POST['case_type'] ?? 'other');
                $summary = trim((string)($_POST['request_summary'] ?? ''));
                $requestedAmountInput = trim((string)($_POST['requested_amount'] ?? ''));
                $requestedOn = (string)($_POST['requested_on'] ?? '');
                $confidentialNotes = trim((string)($_POST['confidential_notes'] ?? ''));
                $requestedDate = DateTime::createFromFormat('!Y-m-d', $requestedOn);
                $memberStmt = $pdo->prepare('SELECT id FROM church_members WHERE id = :id LIMIT 1');
                $memberStmt->execute([':id' => $memberId]);
                $amountIsValid = $requestedAmountInput === '' || (is_numeric($requestedAmountInput) && (float)$requestedAmountInput > 0 && (float)$requestedAmountInput < 10000000000);

                if (!$memberStmt->fetchColumn() || !in_array($caseType, $caseTypes, true) || $summary === '' || strlen($summary) > 500 || strlen($confidentialNotes) > 10000 || !$requestedDate || $requestedDate->format('Y-m-d') !== $requestedOn || !$amountIsValid) {
                    $error = adminManagementText('benevolence_validation_error');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO church_benevolence_cases (member_id, case_type, request_summary, requested_amount, confidential_notes, requested_on, created_by) VALUES (:member_id, :case_type, :request_summary, :requested_amount, :confidential_notes, :requested_on, :created_by)');
                    $stmt->execute([
                        ':member_id' => $memberId,
                        ':case_type' => $caseType,
                        ':request_summary' => $summary,
                        ':requested_amount' => $requestedAmountInput !== '' ? number_format((float)$requestedAmountInput, 2, '.', '') : null,
                        ':confidential_notes' => $confidentialNotes !== '' ? $confidentialNotes : null,
                        ':requested_on' => $requestedOn,
                        ':created_by' => (int)($_SESSION['admin_id'] ?? 0),
                    ]);
                    header('Location: admin_benevolence.php?lang=' . urlencode($adminManagementLanguage) . '&saved=created');
                    exit;
                }
            } elseif ($action === 'review_case') {
                $caseId = filter_var($_POST['case_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $decision = (string)($_POST['decision'] ?? '');
                $approvedInput = trim((string)($_POST['approved_amount'] ?? ''));
                $approvedAmount = null;
                $amountValid = $decision === 'declined' && $approvedInput === '';
                if ($decision === 'approved' && is_numeric($approvedInput) && (float)$approvedInput > 0 && (float)$approvedInput < 10000000000) {
                    $amountValid = true;
                    $approvedAmount = number_format((float)$approvedInput, 2, '.', '');
                }

                if ($caseId === false || !in_array($decision, ['approved', 'declined'], true) || !$amountValid) {
                    $error = adminManagementText('benevolence_review_error');
                } else {
                    $stmt = $pdo->prepare("UPDATE church_benevolence_cases SET status = :status, approved_amount = :approved_amount, reviewed_by = :reviewed_by, reviewed_at = NOW() WHERE id = :id AND status = 'pending'");
                    $stmt->execute([
                        ':status' => $decision,
                        ':approved_amount' => $approvedAmount,
                        ':reviewed_by' => (int)($_SESSION['admin_id'] ?? 0),
                        ':id' => (int)$caseId,
                    ]);
                    if ($stmt->rowCount() !== 1) {
                        $error = adminManagementText('benevolence_review_error');
                    } else {
                        header('Location: admin_benevolence.php?lang=' . urlencode($adminManagementLanguage) . '&saved=reviewed');
                        exit;
                    }
                }
            } elseif ($action === 'disburse_case') {
                $caseId = filter_var($_POST['case_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($caseId === false) {
                    $error = adminManagementText('benevolence_disburse_error');
                } else {
                    $pdo->beginTransaction();
                    $caseStmt = $pdo->prepare('SELECT id, approved_amount, status, finance_transaction_id FROM church_benevolence_cases WHERE id = :id FOR UPDATE');
                    $caseStmt->execute([':id' => (int)$caseId]);
                    $case = $caseStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$case || $case['status'] !== 'approved' || (float)$case['approved_amount'] <= 0 || !empty($case['finance_transaction_id'])) {
                        $pdo->rollBack();
                        $error = adminManagementText('benevolence_disburse_error');
                    } else {
                        $transactionStmt = $pdo->prepare("INSERT INTO finance_transactions (transaction_type, category, description, amount, transaction_date, payment_method, recorded_by) VALUES ('expense', :category, :description, :amount, :transaction_date, :payment_method, :recorded_by)");
                        $transactionStmt->execute([
                            ':category' => 'Benevolence',
                            ':description' => 'Benevolence assistance case #' . (int)$case['id'],
                            ':amount' => number_format((float)$case['approved_amount'], 2, '.', ''),
                            ':transaction_date' => date('Y-m-d'),
                            ':payment_method' => null,
                            ':recorded_by' => (string)($_SESSION['admin_username'] ?? 'Admin'),
                        ]);
                        $transactionId = (int)$pdo->lastInsertId();
                        recordFinanceAudit($pdo, 'create', 'transaction', $transactionId, null, financeTransactionSnapshot($pdo, $transactionId));
                        $updateStmt = $pdo->prepare("UPDATE church_benevolence_cases SET status = 'disbursed', finance_transaction_id = :transaction_id, disbursed_at = NOW() WHERE id = :id AND status = 'approved' AND finance_transaction_id IS NULL");
                        $updateStmt->execute([':transaction_id' => $transactionId, ':id' => (int)$caseId]);
                        if ($updateStmt->rowCount() !== 1) {
                            throw new RuntimeException('Benevolence case changed during disbursement.');
                        }
                        $pdo->commit();
                        header('Location: admin_benevolence.php?lang=' . urlencode($adminManagementLanguage) . '&saved=disbursed');
                        exit;
                    }
                }
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Benevolence management action failed: ' . $exception->getMessage());
            $error = adminManagementText('benevolence_save_error');
        }
    }
}

$savedMessages = [
    'created' => 'benevolence_saved',
    'reviewed' => 'benevolence_reviewed',
    'disbursed' => 'benevolence_disbursement_saved',
];
$messageKey = $savedMessages[(string)($_GET['saved'] ?? '')] ?? '';
$message = $messageKey !== '' ? adminManagementText($messageKey) : '';
$members = $pdo->query("SELECT id, member_number, full_name FROM church_members ORDER BY status = 'active' DESC, full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$cases = $pdo->query('SELECT c.*, m.member_number, m.full_name FROM church_benevolence_cases c JOIN church_members m ON m.id = c.member_id ORDER BY FIELD(c.status, \'pending\', \'approved\', \'disbursed\', \'declined\', \'closed\'), c.requested_on DESC, c.id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
$summary = $pdo->query("SELECT COUNT(*) AS total_cases, SUM(status = 'pending') AS pending_cases, SUM(status = 'approved') AS approved_cases, COALESCE(SUM(CASE WHEN status = 'disbursed' THEN approved_amount ELSE 0 END), 0) AS disbursed_total FROM church_benevolence_cases")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe(adminManagementText('benevolence_title')); ?></title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f3f6f4;color:#202b27;font-family:Arial,sans-serif}.topbar{background:#143b36;color:#fff;padding:16px max(20px,calc((100% - 1200px)/2));display:flex;justify-content:space-between;align-items:center;gap:12px}.topbar h1{font-size:20px;margin:0}.topbar nav{display:flex;gap:8px;flex-wrap:wrap}.topbar a,.button{color:#fff;text-decoration:none;background:#236c5e;border:0;padding:9px 12px;border-radius:4px;font:inherit;cursor:pointer}.wrap{max-width:1200px;margin:22px auto;padding:0 18px}.summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:16px}.metric,.panel{background:#fff;border-top:2px solid #d7e2dc;padding:15px;min-width:0}.metric span{display:block;color:#63716c;font-size:13px}.metric strong{display:block;font-size:23px;margin-top:6px}.layout{display:grid;grid-template-columns:minmax(260px,0.8fr) minmax(0,1.8fr);gap:16px}.panel h2{font-size:17px;margin:0 0 14px}.field{display:flex;flex-direction:column;gap:5px;margin-bottom:11px}label{font-size:13px;font-weight:600}input,select,textarea{width:100%;padding:9px;border:1px solid #cbd5d1;border-radius:4px;background:#fff;color:inherit;font:inherit}textarea{min-height:75px;resize:vertical}.button{display:inline-block}.button.secondary{color:#236c5e;background:#fff;border:1px solid #236c5e}.button.danger{background:#9e4138}.notice{padding:10px 12px;margin-bottom:12px;background:#e8f4ef;color:#176244}.notice.error{background:#fff0eb;color:#9b351f}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;padding:9px 8px;border-bottom:1px solid #e4ebe7;vertical-align:top}th{background:#f3f7f4;white-space:nowrap}.muted{color:#64736e;font-size:12px}.actions{display:grid;gap:8px;min-width:190px}.actions form{display:grid;gap:6px}.privacy{padding:10px;background:#fff9e8;border-left:3px solid #b6853b;color:#594a2b;font-size:13px;margin:0 0 12px}@media(max-width:850px){.layout{grid-template-columns:1fr}.summary{grid-template-columns:repeat(2,minmax(0,1fr))}.topbar{align-items:flex-start;flex-direction:column}}@media(max-width:520px){.summary{grid-template-columns:1fr 1fr}.wrap{padding:0 12px}}
    </style>
</head>
<body>
<header class="topbar"><h1><?php echo safe(adminManagementText('benevolence_title')); ?></h1><nav><a href="<?php echo safe($adminManagementSwitchUrl); ?>"><?php echo safe(adminManagementText('language_switch')); ?></a><a href="admin_management_dashboard.php?lang=<?php echo safe($adminManagementLanguage); ?>"><?php echo safe(adminManagementText('back_dashboard')); ?></a></nav></header>
<main class="wrap">
    <?php if ($message !== ''): ?><div class="notice"><?php echo safe($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error"><?php echo safe($error); ?></div><?php endif; ?>
    <p class="privacy"><?php echo safe(adminManagementText('benevolence_privacy')); ?></p>
    <section class="summary" aria-label="<?php echo safe(adminManagementText('benevolence_title')); ?>">
        <div class="metric"><span><?php echo safe(adminManagementText('benevolence_total_cases')); ?></span><strong><?php echo number_format((int)$summary['total_cases']); ?></strong></div>
        <div class="metric"><span><?php echo safe(adminManagementText('benevolence_pending')); ?></span><strong><?php echo number_format((int)$summary['pending_cases']); ?></strong></div>
        <div class="metric"><span><?php echo safe(adminManagementText('benevolence_approved')); ?></span><strong><?php echo number_format((int)$summary['approved_cases']); ?></strong></div>
        <div class="metric"><span><?php echo safe(adminManagementText('benevolence_disbursed_total')); ?></span><strong><?php echo number_format((float)$summary['disbursed_total'], 2); ?></strong></div>
    </section>
    <div class="layout">
        <section class="panel">
            <h2><?php echo safe(adminManagementText('benevolence_new_case')); ?></h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>">
                <input type="hidden" name="action" value="create_case">
                <div class="field"><label for="member_id"><?php echo safe(adminManagementText('member')); ?></label><select id="member_id" name="member_id" required><option value=""><?php echo safe(adminManagementText('select_member')); ?></option><?php foreach ($members as $member): ?><option value="<?php echo (int)$member['id']; ?>"><?php echo safe($member['full_name'] . ' (' . ($member['member_number'] ?? '—') . ')'); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="case_type"><?php echo safe(adminManagementText('benevolence_type')); ?></label><select id="case_type" name="case_type" required><?php foreach ($caseTypes as $type): ?><option value="<?php echo safe($type); ?>"><?php echo safe(adminManagementText('benevolence_type_' . $type)); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="request_summary"><?php echo safe(adminManagementText('benevolence_summary')); ?></label><textarea id="request_summary" name="request_summary" maxlength="500" required></textarea></div>
                <div class="field"><label for="requested_amount"><?php echo safe(adminManagementText('benevolence_requested_amount')); ?></label><input id="requested_amount" name="requested_amount" type="number" min="0.01" step="0.01"></div>
                <div class="field"><label for="requested_on"><?php echo safe(adminManagementText('date')); ?></label><input id="requested_on" name="requested_on" type="date" value="<?php echo date('Y-m-d'); ?>" required></div>
                <div class="field"><label for="confidential_notes"><?php echo safe(adminManagementText('benevolence_confidential_notes')); ?></label><textarea id="confidential_notes" name="confidential_notes" maxlength="10000"></textarea></div>
                <button class="button" type="submit"><?php echo safe(adminManagementText('benevolence_create')); ?></button>
            </form>
        </section>
        <section class="panel">
            <h2><?php echo safe(adminManagementText('benevolence_cases')); ?></h2>
            <div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('member')); ?></th><th><?php echo safe(adminManagementText('benevolence_type')); ?></th><th><?php echo safe(adminManagementText('benevolence_summary')); ?></th><th><?php echo safe(adminManagementText('benevolence_amounts')); ?></th><th><?php echo safe(adminManagementText('status')); ?></th><th><?php echo safe(adminManagementText('action')); ?></th></tr></thead><tbody>
            <?php if (!$cases): ?><tr><td colspan="6"><?php echo safe(adminManagementText('benevolence_no_cases')); ?></td></tr><?php else: foreach ($cases as $case): ?>
                <tr>
                    <td><?php echo safe($case['full_name']); ?><br><span class="muted"><?php echo safe($case['member_number'] ?? ''); ?></span></td>
                    <td><?php echo safe(adminManagementText('benevolence_type_' . $case['case_type'])); ?></td>
                    <td><?php echo safe($case['request_summary']); ?><?php if (!empty($case['confidential_notes'])): ?><br><span class="muted"><?php echo safe(adminManagementText('benevolence_confidential_notes')); ?>: <?php echo safe($case['confidential_notes']); ?></span><?php endif; ?></td>
                    <td><?php echo safe(adminManagementText('benevolence_requested_short')); ?>: <?php echo $case['requested_amount'] !== null ? number_format((float)$case['requested_amount'], 2) : '—'; ?><br><?php echo safe(adminManagementText('benevolence_approved_short')); ?>: <?php echo $case['approved_amount'] !== null ? number_format((float)$case['approved_amount'], 2) : '—'; ?></td>
                    <td><?php echo safe(adminManagementText($statusLabels[$case['status']] ?? 'benevolence_pending')); ?><br><span class="muted"><?php echo safe($case['requested_on']); ?></span></td>
                    <td><div class="actions">
                        <?php if ($case['status'] === 'pending'): ?>
                            <form method="post"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="review_case"><input type="hidden" name="case_id" value="<?php echo (int)$case['id']; ?>"><input name="approved_amount" type="number" min="0.01" step="0.01" placeholder="<?php echo safe(adminManagementText('benevolence_approved_amount')); ?>"><button class="button" name="decision" value="approved" type="submit"><?php echo safe(adminManagementText('benevolence_approve')); ?></button><button class="button danger" name="decision" value="declined" type="submit"><?php echo safe(adminManagementText('benevolence_decline')); ?></button></form>
                        <?php elseif ($case['status'] === 'approved'): ?>
                            <form method="post" onsubmit="return confirm(<?php echo safe(json_encode(adminManagementText('benevolence_disburse_confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)); ?>);"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="disburse_case"><input type="hidden" name="case_id" value="<?php echo (int)$case['id']; ?>"><button class="button" type="submit"><?php echo safe(adminManagementText('benevolence_disburse')); ?></button></form>
                        <?php elseif (!empty($case['finance_transaction_id'])): ?><span class="muted"><?php echo safe(adminManagementText('benevolence_transaction')); ?> #<?php echo (int)$case['finance_transaction_id']; ?></span><?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody></table></div>
        </section>
    </div>
</main>
</body>
</html>
