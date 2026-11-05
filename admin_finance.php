<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/management_schema.php';
require_once __DIR__ . '/admin_management_i18n.php';
require_once __DIR__ . '/finance_security.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit(adminManagementText('db_unavailable'));
}

ensureManagementTables($pdo);
$financeAccess = requireFinanceAccess($pdo);
$csrf = csrfToken();
$savedMessages = [
    'entry' => 'financial_saved',
    'project' => 'finance_project_saved',
    'deleted' => 'financial_deleted',
];
$message = $savedMessages[(string)($_GET['saved'] ?? '')] ?? '';
$message = $message !== '' ? adminManagementText($message) : '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$financeAccess['can_manage']) {
        http_response_code(403);
        exit(adminManagementText('finance_access_denied'));
    } elseif (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $error = adminManagementText('csrf_error');
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'add_project') {
                $projectName = trim((string)($_POST['project_name'] ?? ''));
                $projectDescription = trim((string)($_POST['project_description'] ?? ''));
                if ($projectName === '' || strlen($projectName) > 180) {
                    $error = adminManagementText('finance_project_name_required');
                } else {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare('INSERT INTO finance_projects (project_name, description, created_by) VALUES (:project_name, :description, :created_by)');
                    $stmt->execute([
                        ':project_name' => $projectName,
                        ':description' => $projectDescription !== '' ? $projectDescription : null,
                        ':created_by' => (int)$_SESSION['admin_id'],
                    ]);
                    $projectId = (int)$pdo->lastInsertId();
                    recordFinanceAudit($pdo, 'create', 'project', $projectId, null, ['project_name' => $projectName, 'description' => $projectDescription]);
                    $pdo->commit();
                    header('Location: admin_finance.php?saved=project');
                    exit;
                }
            } elseif ($action === 'save_entry') {
                $id = (int)($_POST['id'] ?? 0);
                $type = (string)($_POST['transaction_type'] ?? '');
                $requestedGivingType = (string)($_POST['giving_type'] ?? 'general');
                $givingType = $type === 'income' ? $requestedGivingType : 'general';
                $category = trim((string)($_POST['category'] ?? ''));
                $description = trim((string)($_POST['description'] ?? ''));
                $amount = trim((string)($_POST['amount'] ?? ''));
                $transactionDate = (string)($_POST['transaction_date'] ?? '');
                $paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
                $reference = trim((string)($_POST['reference'] ?? ''));
                $projectId = (int)($_POST['project_id'] ?? 0);
                $donorMemberId = $type === 'income' ? (int)($_POST['donor_member_id'] ?? 0) : 0;
                $donorName = $type === 'income' ? trim((string)($_POST['donor_name'] ?? '')) : '';
                $isAnonymous = isset($_POST['is_anonymous']) && $type === 'income' ? 1 : 0;
                $validDate = DateTime::createFromFormat('!Y-m-d', $transactionDate);

                if (!in_array($type, ['income', 'expense'], true) || !in_array($givingType, ['general', 'tithe', 'donation'], true) || $category === '' || $description === '' || !is_numeric($amount) || (float)$amount <= 0 || (float)$amount >= 10000000000 || !$validDate || $validDate->format('Y-m-d') !== $transactionDate) {
                    $error = adminManagementText('finance_validation_error');
                } elseif (strlen($category) > 100 || strlen($description) > 255) {
                    $error = adminManagementText('length_error');
                } elseif ($type !== 'income' && in_array($requestedGivingType, ['tithe', 'donation'], true)) {
                    $error = adminManagementText('finance_tithe_donation_income');
                } else {
                    if ($isAnonymous) {
                        $donorMemberId = 0;
                        $donorName = '';
                    } elseif ($donorMemberId > 0) {
                        $memberStmt = $pdo->prepare('SELECT full_name FROM church_members WHERE id = :id LIMIT 1');
                        $memberStmt->execute([':id' => $donorMemberId]);
                        $memberName = $memberStmt->fetchColumn();
                        if ($memberName === false) {
                            $error = adminManagementText('finance_member_invalid');
                        } else {
                            $donorName = (string)$memberName;
                        }
                    }

                    if ($error === '' && in_array($givingType, ['tithe', 'donation'], true) && !$isAnonymous && $donorName === '') {
                        $error = adminManagementText('finance_donor_name_required');
                    }

                    if ($error === '' && $projectId > 0) {
                        $projectStmt = $pdo->prepare("SELECT id FROM finance_projects WHERE id = :id AND status = 'active' LIMIT 1");
                        $projectStmt->execute([':id' => $projectId]);
                        if (!$projectStmt->fetchColumn()) {
                            $error = adminManagementText('finance_project_required');
                        }
                    }

                    if ($error !== '') {
                        $validEntry = false;
                    } else {
                        $validEntry = true;
                    }

                    if ($validEntry) {
                        $oldData = $id > 0 ? financeTransactionSnapshot($pdo, $id) : null;
                        if ($id > 0 && !$oldData) {
                            $error = adminManagementText('finance_save_error');
                            $validEntry = false;
                        }
                    }

                    if ($validEntry) {
                    $pdo->beginTransaction();
                    $values = [
                        ':transaction_type' => $type,
                        ':giving_type' => $givingType,
                        ':donor_member_id' => $donorMemberId > 0 ? $donorMemberId : null,
                        ':donor_name' => $donorName !== '' ? $donorName : null,
                        ':is_anonymous' => $isAnonymous,
                        ':project_id' => $projectId > 0 ? $projectId : null,
                        ':category' => $category,
                        ':description' => $description,
                        ':amount' => number_format((float)$amount, 2, '.', ''),
                        ':transaction_date' => $transactionDate,
                        ':payment_method' => $paymentMethod !== '' ? $paymentMethod : null,
                        ':reference' => $reference !== '' ? $reference : null,
                    ];
                    if ($id > 0) {
                        $values[':id'] = $id;
                        $values[':receipt_number'] = $type === 'income' ? ($oldData['receipt_number'] ?? null) : null;
                        $stmt = $pdo->prepare('UPDATE finance_transactions SET transaction_type = :transaction_type, giving_type = :giving_type, donor_member_id = :donor_member_id, donor_name = :donor_name, is_anonymous = :is_anonymous, project_id = :project_id, category = :category, description = :description, amount = :amount, transaction_date = :transaction_date, payment_method = :payment_method, reference = :reference, receipt_number = :receipt_number WHERE id = :id');
                    } else {
                        $values[':recorded_by'] = (string)($_SESSION['admin_username'] ?? 'Admin');
                        $stmt = $pdo->prepare('INSERT INTO finance_transactions (transaction_type, giving_type, donor_member_id, donor_name, is_anonymous, project_id, category, description, amount, transaction_date, payment_method, reference, recorded_by) VALUES (:transaction_type, :giving_type, :donor_member_id, :donor_name, :is_anonymous, :project_id, :category, :description, :amount, :transaction_date, :payment_method, :reference, :recorded_by)');
                    }
                    $stmt->execute($values);
                    $entryId = $id > 0 ? $id : (int)$pdo->lastInsertId();
                    if ($type === 'income') {
                        $receiptNumber = $oldData['receipt_number'] ?? null;
                        if (!$receiptNumber) {
                            $receiptNumber = 'GIV-' . date('Ymd') . '-' . str_pad((string)$entryId, 6, '0', STR_PAD_LEFT);
                            $receiptStmt = $pdo->prepare('UPDATE finance_transactions SET receipt_number = :receipt_number WHERE id = :id');
                            $receiptStmt->execute([':receipt_number' => $receiptNumber, ':id' => $entryId]);
                        }
                    }
                    $newData = financeTransactionSnapshot($pdo, $entryId);
                    recordFinanceAudit($pdo, $id > 0 ? 'update' : 'create', 'transaction', $entryId, $oldData, $newData);
                    $pdo->commit();
                    header('Location: admin_finance.php?saved=entry');
                    exit;
                    }
                }
            } elseif ($action === 'delete_entry') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $pdo->beginTransaction();
                    $oldData = financeTransactionSnapshot($pdo, $id);
                    $stmt = $pdo->prepare('DELETE FROM finance_transactions WHERE id = :id');
                    $stmt->execute([':id' => $id]);
                    if ($oldData) {
                        recordFinanceAudit($pdo, 'delete', 'transaction', $id, $oldData, null);
                    }
                    $pdo->commit();
                    header('Location: admin_finance.php?saved=deleted');
                    exit;
                }
            } elseif ($action === 'set_project_status') {
                $id = (int)($_POST['id'] ?? 0);
                $status = (string)($_POST['status'] ?? '');
                if ($id > 0 && in_array($status, ['active', 'inactive'], true)) {
                    $projectStmt = $pdo->prepare('SELECT project_name, status FROM finance_projects WHERE id = :id LIMIT 1');
                    $projectStmt->execute([':id' => $id]);
                    $oldData = $projectStmt->fetch(PDO::FETCH_ASSOC);
                    if ($oldData) {
                        $pdo->beginTransaction();
                        $updateStmt = $pdo->prepare('UPDATE finance_projects SET status = :status WHERE id = :id');
                        $updateStmt->execute([':status' => $status, ':id' => $id]);
                        recordFinanceAudit($pdo, 'update', 'project', $id, $oldData, ['project_name' => $oldData['project_name'], 'status' => $status]);
                        $pdo->commit();
                    }
                    header('Location: admin_finance.php');
                    exit;
                }
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = adminManagementText('finance_save_error');
            error_log('Finance record save failed: ' . $exception->getMessage());
        }
    }
}

if (isset($_GET['deleted'])) {
    $message = adminManagementText('financial_deleted');
}

$editingId = $financeAccess['can_manage'] ? (int)($_GET['edit'] ?? 0) : 0;
$editingEntry = null;
if ($editingId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM finance_transactions WHERE id = :id');
    $stmt->execute([':id' => $editingId]);
    $editingEntry = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$filterType = (string)($_GET['type'] ?? 'all');
if (!in_array($filterType, ['all', 'income', 'expense'], true)) {
    $filterType = 'all';
}
$reportMonth = (string)($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $reportMonth)) {
    $reportMonth = date('Y-m');
}
$reportYear = filter_var($_GET['year'] ?? date('Y'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]);
$reportYear = $reportYear === false ? (int)date('Y') : (int)$reportYear;
$monthStart = $reportMonth . '-01';
$monthEnd = date('Y-m-d', strtotime($monthStart . ' +1 month'));
$yearStart = sprintf('%04d-01-01', $reportYear);
$yearEnd = sprintf('%04d-01-01', $reportYear + 1);
$summary = $pdo->query("SELECT COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) AS income, COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) AS expense FROM finance_transactions")->fetch(PDO::FETCH_ASSOC);
$periodStmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) AS income, COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) AS expense, COALESCE(SUM(CASE WHEN giving_type = 'tithe' THEN amount ELSE 0 END), 0) AS tithes, COALESCE(SUM(CASE WHEN giving_type = 'donation' THEN amount ELSE 0 END), 0) AS donations FROM finance_transactions WHERE transaction_date >= :date_from AND transaction_date < :date_to");
$periodStmt->execute([':date_from' => $monthStart, ':date_to' => $monthEnd]);
$monthlySummary = $periodStmt->fetch(PDO::FETCH_ASSOC);
$periodStmt->execute([':date_from' => $yearStart, ':date_to' => $yearEnd]);
$annualSummary = $periodStmt->fetch(PDO::FETCH_ASSOC);
$projectReportStmt = $pdo->prepare("SELECT p.id, p.project_name, p.status, COALESCE(SUM(CASE WHEN ft.transaction_type = 'income' THEN ft.amount ELSE 0 END), 0) AS income, COALESCE(SUM(CASE WHEN ft.transaction_type = 'expense' THEN ft.amount ELSE 0 END), 0) AS expense FROM finance_projects p LEFT JOIN finance_transactions ft ON ft.project_id = p.id AND ft.transaction_date >= :date_from AND ft.transaction_date < :date_to GROUP BY p.id, p.project_name, p.status ORDER BY p.project_name ASC");
$projectReportStmt->execute([':date_from' => $monthStart, ':date_to' => $monthEnd]);
$monthlyProjectReport = $projectReportStmt->fetchAll(PDO::FETCH_ASSOC);
$projectReportStmt->execute([':date_from' => $yearStart, ':date_to' => $yearEnd]);
$annualProjectReport = $projectReportStmt->fetchAll(PDO::FETCH_ASSOC);
$projects = $pdo->query('SELECT id, project_name, description, status FROM finance_projects ORDER BY status ASC, project_name ASC')->fetchAll(PDO::FETCH_ASSOC);
$members = $financeAccess['can_manage'] ? $pdo->query("SELECT id, full_name FROM church_members WHERE status = 'active' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC) : [];
$auditRows = $financeAccess['can_manage'] ? $pdo->query('SELECT actor_username, action, entity_type, entity_id, created_at FROM finance_audit_log ORDER BY id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC) : [];
$selectColumns = 'ft.id, ft.transaction_type, ft.giving_type, ft.project_id, ft.category, ft.description, ft.amount, ft.transaction_date, ft.payment_method, ft.reference, ft.receipt_number, p.project_name';
if ($financeAccess['show_donor_details']) {
    $selectColumns .= ', ft.donor_name, ft.is_anonymous, ft.donor_member_id';
}
$query = 'SELECT ' . $selectColumns . ' FROM finance_transactions ft LEFT JOIN finance_projects p ON p.id = ft.project_id';
$params = [];
$conditions = [];
if (!$financeAccess['show_donor_details']) {
    $conditions[] = "ft.giving_type = 'general'";
}
if ($filterType !== 'all') {
    $conditions[] = 'ft.transaction_type = :type';
    $params[':type'] = $filterType;
}
if ($conditions) {
    $query .= ' WHERE ' . implode(' AND ', $conditions);
}
$query .= ' ORDER BY ft.transaction_date DESC, ft.id DESC LIMIT 200';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
$netBalance = (float)$summary['income'] - (float)$summary['expense'];
$financeTableColspan = 10 + ($financeAccess['show_donor_details'] ? 1 : 0);
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe(adminManagementText('finance_title')); ?></title>
    <style>
        :root{font-family:Arial,sans-serif;color:#202b2b;background:#f4f7f5;font-synthesis:none}*{box-sizing:border-box}body{margin:0}.topbar{background:#143b36;color:#fff;padding:16px max(20px,calc((100% - 1180px)/2));display:flex;align-items:center;justify-content:space-between;gap:16px}.topbar h1{font-size:20px;margin:0}.topbar a{color:#fff;text-decoration:none;background:#236c5e;padding:10px 14px;border-radius:5px}.wrap{max-width:1180px;margin:24px auto;padding:0 18px}.intro{display:flex;align-items:end;justify-content:space-between;gap:16px;margin-bottom:18px}.intro h2{margin:0 0 5px;font-size:23px}.muted{color:#64736e}.totals{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1px;background:#dbe4df;border:1px solid #dbe4df;margin-bottom:18px}.total{background:#fff;padding:16px}.total span{display:block;color:#64736e;font-size:13px}.total strong{display:block;font-size:23px;margin-top:5px}.income{color:#19734e}.expense{color:#a33b35}.section{background:#fff;padding:18px;border-top:2px solid #d7e2dc;margin-bottom:18px}.section h3{margin:0 0 15px;font-size:17px}.form-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:11px}.field{display:flex;flex-direction:column;gap:5px}.field.wide{grid-column:span 2}label{font-size:13px;font-weight:600}input,select{width:100%;padding:10px;border:1px solid #cbd5d1;border-radius:4px;font:inherit;background:#fff;color:#202b2b}.submit{margin-top:12px;background:#236c5e;color:#fff;border:0;border-radius:4px;padding:10px 14px;font:inherit;cursor:pointer}.cancel{color:#236c5e;margin-left:12px}.notice{padding:11px 13px;background:#e8f4ef;color:#176244;margin-bottom:14px}.error{background:#fff0eb;color:#9b351f}.toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}.toolbar form{display:flex;align-items:center;gap:8px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:14px}th,td{text-align:left;padding:10px;border-bottom:1px solid #e5ebe7;white-space:nowrap}th{background:#f3f7f4;color:#46534e}.row-actions{display:flex;align-items:center;gap:10px}.row-actions a,.row-actions button{color:#236c5e;background:none;border:0;padding:0;text-decoration:underline;cursor:pointer;font:inherit}.row-actions .delete{color:#a33b35}.type-badge{font-weight:600}.print{background:#b6853b}@media(max-width:760px){.intro{align-items:start;flex-direction:column}.totals{grid-template-columns:1fr}.form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.field.wide{grid-column:span 2}.topbar{padding:14px 18px}}@media(max-width:480px){.form-grid{grid-template-columns:1fr}.field.wide{grid-column:auto}}@media print{body{background:#fff}.topbar,.intro,.section:first-of-type,.toolbar form,.no-print{display:none!important}.wrap{margin:0;max-width:none;padding:0}.section{border:0;padding:0}.totals{border:0}}
    </style>
</head>
<body>
<header class="topbar"><h1><?php echo safe(adminManagementText('finance_title')); ?></h1><nav style="display:flex;gap:8px;flex-wrap:wrap"><a href="<?php echo safe($adminManagementSwitchUrl); ?>"><?php echo safe(adminManagementText('language_switch')); ?></a><?php if ($financeAccess['can_manage_access']): ?><a href="admin_finance_access.php"><?php echo safe(adminManagementText('finance_share_access')); ?></a><?php endif; ?><a href="admin_management_dashboard.php">← <?php echo safe(adminManagementText('back_dashboard')); ?></a></nav></header>
<main class="wrap">
    <?php if ($message !== ''): ?><div class="notice"><?php echo safe($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error"><?php echo safe($error); ?></div><?php endif; ?>
    <?php if (!$financeAccess['show_donor_details']): ?><div class="notice"><?php echo safe(adminManagementText('finance_private')); ?></div><?php endif; ?>
    <div class="intro"><div><h2><?php echo safe(adminManagementText('finance_heading')); ?></h2><div class="muted"><?php echo safe(adminManagementText('finance_intro')); ?></div></div></div>
    <div class="totals">
        <div class="total"><span><?php echo safe(adminManagementText('total_income')); ?></span><strong class="income"><?php echo number_format((float)$summary['income'], 2); ?></strong></div>
        <div class="total"><span><?php echo safe(adminManagementText('total_expenses')); ?></span><strong class="expense"><?php echo number_format((float)$summary['expense'], 2); ?></strong></div>
        <div class="total"><span><?php echo safe(adminManagementText('net_balance')); ?></span><strong><?php echo number_format($netBalance, 2); ?></strong></div>
    </div>
    <section class="section">
        <div class="toolbar"><h3 style="margin:0"><?php echo safe(adminManagementText('finance_monthly_report')); ?></h3><form method="get" class="no-print"><label for="month"><?php echo safe(adminManagementText('finance_month')); ?></label><input id="month" type="month" name="month" value="<?php echo safe($reportMonth); ?>"><label for="year"><?php echo safe(adminManagementText('finance_year')); ?></label><input id="year" type="number" name="year" min="2000" max="2100" value="<?php echo $reportYear; ?>"><button class="submit" type="submit" style="margin:0"><?php echo safe(adminManagementText('filter')); ?></button></form></div>
        <div class="totals"><div class="total"><span><?php echo safe(adminManagementText('total_income')); ?> · <?php echo safe($reportMonth); ?></span><strong class="income"><?php echo number_format((float)$monthlySummary['income'], 2); ?></strong></div><div class="total"><span><?php echo safe(adminManagementText('total_expenses')); ?> · <?php echo safe($reportMonth); ?></span><strong class="expense"><?php echo number_format((float)$monthlySummary['expense'], 2); ?></strong></div><div class="total"><span><?php echo safe(adminManagementText('net_balance')); ?> · <?php echo safe($reportMonth); ?></span><strong><?php echo number_format((float)$monthlySummary['income'] - (float)$monthlySummary['expense'], 2); ?></strong></div></div>
        <div class="totals"><div class="total"><span><?php echo safe(adminManagementText('finance_tithe_total')); ?> · <?php echo safe($reportMonth); ?></span><strong><?php echo number_format((float)$monthlySummary['tithes'], 2); ?></strong></div><div class="total"><span><?php echo safe(adminManagementText('finance_donation_total')); ?> · <?php echo safe($reportMonth); ?></span><strong><?php echo number_format((float)$monthlySummary['donations'], 2); ?></strong></div><div class="total"><span><?php echo safe(adminManagementText('finance_annual_report')); ?> · <?php echo $reportYear; ?></span><strong><?php echo number_format((float)$annualSummary['income'] - (float)$annualSummary['expense'], 2); ?></strong></div></div>
        <div class="totals"><div class="total"><span><?php echo safe(adminManagementText('total_income')); ?> · <?php echo $reportYear; ?></span><strong class="income"><?php echo number_format((float)$annualSummary['income'], 2); ?></strong></div><div class="total"><span><?php echo safe(adminManagementText('total_expenses')); ?> · <?php echo $reportYear; ?></span><strong class="expense"><?php echo number_format((float)$annualSummary['expense'], 2); ?></strong></div><div class="total"><span><?php echo safe(adminManagementText('finance_tithe_total')); ?> · <?php echo $reportYear; ?></span><strong><?php echo number_format((float)$annualSummary['tithes'], 2); ?></strong></div></div>
        <div class="totals"><div class="total"><span><?php echo safe(adminManagementText('finance_donation_total')); ?> · <?php echo $reportYear; ?></span><strong><?php echo number_format((float)$annualSummary['donations'], 2); ?></strong></div><div class="total"><span><?php echo safe(adminManagementText('finance_report_range')); ?></span><strong><?php echo safe($yearStart); ?> – <?php echo safe(date('Y-m-d', strtotime($yearEnd . ' -1 day'))); ?></strong></div><div class="total"><span><?php echo safe(adminManagementText('finance_report_range')); ?></span><strong><?php echo safe($monthStart); ?> – <?php echo safe(date('Y-m-d', strtotime($monthEnd . ' -1 day'))); ?></strong></div></div>
    </section>
    <section class="section">
        <h3><?php echo safe(adminManagementText('finance_project_report')); ?></h3>
        <div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('finance_project_name')); ?></th><th><?php echo safe(adminManagementText('finance_total_project_income')); ?> · <?php echo safe($reportMonth); ?></th><th><?php echo safe(adminManagementText('finance_total_project_expense')); ?> · <?php echo safe($reportMonth); ?></th><th><?php echo safe(adminManagementText('finance_total_project_income')); ?> · <?php echo $reportYear; ?></th><th><?php echo safe(adminManagementText('finance_total_project_expense')); ?> · <?php echo $reportYear; ?></th><?php if ($financeAccess['can_manage']): ?><th><?php echo safe(adminManagementText('finance_project_status')); ?></th><?php endif; ?></tr></thead><tbody>
        <?php if (!$monthlyProjectReport): ?><tr><td colspan="<?php echo $financeAccess['can_manage'] ? 6 : 5; ?>"><?php echo safe(adminManagementText('finance_no_projects')); ?></td></tr><?php else: foreach ($monthlyProjectReport as $projectIndex => $projectMonth): $projectYear = $annualProjectReport[$projectIndex] ?? ['income' => 0, 'expense' => 0]; ?><tr><td><?php echo safe($projectMonth['project_name']); ?></td><td><?php echo number_format((float)$projectMonth['income'], 2); ?></td><td><?php echo number_format((float)$projectMonth['expense'], 2); ?></td><td><?php echo number_format((float)$projectYear['income'], 2); ?></td><td><?php echo number_format((float)$projectYear['expense'], 2); ?></td><?php if ($financeAccess['can_manage']): ?><td><form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="set_project_status"><input type="hidden" name="id" value="<?php echo (int)$projectMonth['id']; ?>"><input type="hidden" name="status" value="<?php echo $projectMonth['status'] === 'active' ? 'inactive' : 'active'; ?>"><button class="delete" type="submit"><?php echo safe(adminManagementText($projectMonth['status'] === 'active' ? 'set_inactive' : 'set_active')); ?></button></form></td><?php endif; ?></tr><?php endforeach; endif; ?>
        </tbody></table></div>
    </section>
    <?php if ($financeAccess['can_manage']): ?>
    <section class="section">
        <h3><?php echo safe(adminManagementText('finance_add_project')); ?></h3>
        <form method="post" class="toolbar"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="add_project"><label for="project_name"><?php echo safe(adminManagementText('finance_project_name')); ?></label><input id="project_name" name="project_name" maxlength="180" required><label for="project_description"><?php echo safe(adminManagementText('finance_description')); ?></label><input id="project_description" name="project_description" maxlength="500"><button class="submit" type="submit" style="margin:0"><?php echo safe(adminManagementText('finance_save_project')); ?></button></form>
    </section>
    <section class="section">
        <h3><?php echo safe(adminManagementText($editingEntry ? 'edit_record' : 'record_transaction')); ?></h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>">
            <input type="hidden" name="action" value="save_entry">
            <input type="hidden" name="id" value="<?php echo (int)($editingEntry['id'] ?? 0); ?>">
            <div class="form-grid">
                <div class="field"><label for="transaction_type"><?php echo safe(adminManagementText('type')); ?> *</label><select id="transaction_type" name="transaction_type" required><option value="income" <?php echo ($editingEntry['transaction_type'] ?? '') === 'income' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('income')); ?></option><option value="expense" <?php echo ($editingEntry['transaction_type'] ?? '') === 'expense' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('expense')); ?></option></select></div>
                <div class="field"><label for="giving_type"><?php echo safe(adminManagementText('finance_giving_type')); ?></label><select id="giving_type" name="giving_type"><option value="general" <?php echo ($editingEntry['giving_type'] ?? 'general') === 'general' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('finance_general_income')); ?></option><option value="tithe" <?php echo ($editingEntry['giving_type'] ?? '') === 'tithe' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('finance_tithe')); ?></option><option value="donation" <?php echo ($editingEntry['giving_type'] ?? '') === 'donation' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('finance_donation')); ?></option></select></div>
                <div class="field"><label for="category"><?php echo safe(adminManagementText('category')); ?> *</label><input id="category" name="category" maxlength="100" value="<?php echo safe($editingEntry['category'] ?? ''); ?>" required></div>
                <div class="field"><label for="amount"><?php echo safe(adminManagementText('amount')); ?> *</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" value="<?php echo safe($editingEntry['amount'] ?? ''); ?>" required></div>
                <div class="field"><label for="transaction_date"><?php echo safe(adminManagementText('date')); ?> *</label><input id="transaction_date" name="transaction_date" type="date" value="<?php echo safe($editingEntry['transaction_date'] ?? date('Y-m-d')); ?>" required></div>
                <div class="field wide"><label for="description"><?php echo safe(adminManagementText('description')); ?> *</label><input id="description" name="description" maxlength="255" value="<?php echo safe($editingEntry['description'] ?? ''); ?>" required></div>
                <div class="field"><label for="payment_method"><?php echo safe(adminManagementText('payment_method')); ?></label><select id="payment_method" name="payment_method"><option value=""><?php echo safe(adminManagementText('not_specified_payment')); ?></option><?php foreach (['Cash' => 'cash', 'Bank transfer' => 'bank_transfer', 'Mobile money' => 'mobile_money', 'Card' => 'card', 'Other' => 'other'] as $method => $methodKey): ?><option value="<?php echo safe($method); ?>" <?php echo ($editingEntry['payment_method'] ?? '') === $method ? 'selected' : ''; ?>><?php echo safe(adminManagementText($methodKey)); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="reference"><?php echo safe(adminManagementText('receipt_reference')); ?></label><input id="reference" name="reference" maxlength="120" value="<?php echo safe($editingEntry['reference'] ?? ''); ?>"></div>
                <div class="field"><label for="project_id"><?php echo safe(adminManagementText('finance_project')); ?></label><select id="project_id" name="project_id"><option value="0"><?php echo safe(adminManagementText('finance_no_projects')); ?></option><?php foreach ($projects as $project): if ($project['status'] !== 'active' && (int)($editingEntry['project_id'] ?? 0) !== (int)$project['id']) continue; ?><option value="<?php echo (int)$project['id']; ?>" <?php echo (int)($editingEntry['project_id'] ?? 0) === (int)$project['id'] ? 'selected' : ''; ?>><?php echo safe($project['project_name']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="donor_member_id"><?php echo safe(adminManagementText('finance_donor_member')); ?></label><select id="donor_member_id" name="donor_member_id"><option value="0"><?php echo safe(adminManagementText('finance_no_member_link')); ?></option><?php foreach ($members as $member): ?><option value="<?php echo (int)$member['id']; ?>" <?php echo (int)($editingEntry['donor_member_id'] ?? 0) === (int)$member['id'] ? 'selected' : ''; ?>><?php echo safe($member['full_name']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="donor_name"><?php echo safe(adminManagementText('finance_donor_name')); ?></label><input id="donor_name" name="donor_name" maxlength="180" value="<?php echo safe($editingEntry['donor_name'] ?? ''); ?>"></div>
                <div class="field"><label><input type="checkbox" name="is_anonymous" value="1" style="width:auto" <?php echo !empty($editingEntry['is_anonymous']) ? 'checked' : ''; ?>> <?php echo safe(adminManagementText('finance_anonymous')); ?></label></div>
            </div>
            <button class="submit" type="submit"><?php echo safe(adminManagementText($editingEntry ? 'update_record' : 'save_record')); ?></button>
            <?php if ($editingEntry): ?><a class="cancel" href="admin_finance.php"><?php echo safe(adminManagementText('cancel_edit')); ?></a><?php endif; ?>
        </form>
    </section>
    <?php endif; ?>
    <section class="section" id="finance-report">
        <div class="toolbar"><div><h3 style="margin-bottom:4px"><?php echo safe(adminManagementText('transaction_report')); ?></h3><span class="muted"><?php echo safe(adminManagementText('showing_limit')); ?></span></div><form method="get" class="no-print"><input type="hidden" name="month" value="<?php echo safe($reportMonth); ?>"><input type="hidden" name="year" value="<?php echo $reportYear; ?>"><label for="type"><?php echo safe(adminManagementText('type')); ?></label><select id="type" name="type"><option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('all')); ?></option><option value="income" <?php echo $filterType === 'income' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('income')); ?></option><option value="expense" <?php echo $filterType === 'expense' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('expense')); ?></option></select><button class="submit" type="submit" style="margin:0"><?php echo safe(adminManagementText('filter')); ?></button><button class="submit print" type="button" onclick="window.print()"><?php echo safe(adminManagementText('print')); ?></button></form></div>
        <div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('date')); ?></th><th><?php echo safe(adminManagementText('type')); ?></th><th><?php echo safe(adminManagementText('finance_giving_type')); ?></th><th><?php echo safe(adminManagementText('category')); ?></th><th><?php echo safe(adminManagementText('description')); ?></th><th><?php echo safe(adminManagementText('finance_project')); ?></th><?php if ($financeAccess['show_donor_details']): ?><th><?php echo safe(adminManagementText('finance_donor_name')); ?></th><?php endif; ?><th><?php echo safe(adminManagementText('amount')); ?></th><th><?php echo safe(adminManagementText('payment_method')); ?></th><th><?php echo safe(adminManagementText('finance_receipt_number')); ?></th><th class="no-print"><?php echo safe(adminManagementText('action')); ?></th></tr></thead><tbody>
        <?php if (!$entries): ?><tr><td colspan="<?php echo $financeTableColspan; ?>"><?php echo safe(adminManagementText('no_finance_records')); ?></td></tr><?php else: foreach ($entries as $entry): ?><tr><td><?php echo safe($entry['transaction_date']); ?></td><td class="type-badge <?php echo safe($entry['transaction_type']); ?>"><?php echo safe(adminManagementText($entry['transaction_type'])); ?></td><td><?php echo safe(adminManagementText(['general' => 'finance_general_income', 'tithe' => 'finance_tithe', 'donation' => 'finance_donation'][$entry['giving_type']] ?? 'finance_general_income')); ?></td><td><?php echo safe($entry['category']); ?></td><td><?php echo safe($entry['description']); ?></td><td><?php echo safe($entry['project_name'] ?? ''); ?></td><?php if ($financeAccess['show_donor_details']): ?><td><?php echo !empty($entry['is_anonymous']) ? safe(adminManagementText('finance_anonymous_label')) : safe($entry['donor_name'] ?? ''); ?></td><?php endif; ?><td><?php echo number_format((float)$entry['amount'], 2); ?></td><td><?php echo safe($entry['payment_method'] ? adminManagementText(str_replace(' ', '_', strtolower($entry['payment_method']))) : ''); ?></td><td><?php echo safe($entry['receipt_number'] ?? ''); ?></td><td class="no-print"><div class="row-actions"><?php if ($entry['transaction_type'] === 'income' && !empty($entry['receipt_number'])): ?><a href="finance_receipt.php?id=<?php echo (int)$entry['id']; ?>"><?php echo safe(adminManagementText('finance_print_receipt')); ?></a><?php endif; ?><?php if ($financeAccess['can_manage']): ?><a href="admin_finance.php?edit=<?php echo (int)$entry['id']; ?>"><?php echo safe(adminManagementText('edit_record')); ?></a><form method="post" onsubmit="return confirm(<?php echo htmlspecialchars(json_encode(adminManagementText('delete_confirm')), ENT_QUOTES, 'UTF-8'); ?>)"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="delete_entry"><input type="hidden" name="id" value="<?php echo (int)$entry['id']; ?>"><button class="delete" type="submit"><?php echo safe(adminManagementText('delete')); ?></button></form><?php endif; ?></div></td></tr><?php endforeach; endif; ?>
        </tbody></table></div>
    </section>
</main>
</body>
</html>