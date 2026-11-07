<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/management_schema.php';
require_once __DIR__ . '/admin_management_i18n.php';
require_once __DIR__ . '/finance_security.php';

if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit(adminManagementText('db_unavailable'));
}
ensureManagementTables($pdo);
$financeAccess = requireFinanceAccess($pdo);
if (!$financeAccess['can_manage']) {
    http_response_code(403);
    exit(adminManagementText('finance_access_denied'));
}
$csrf = csrfToken();
$error = '';
$message = '';
$paymentMethods = ['cash', 'bank_transfer', 'mobile_money', 'check', 'other'];
$methodKeys = ['cash' => 'cash', 'bank_transfer' => 'bank_transfer', 'mobile_money' => 'mobile_money', 'check' => 'payroll_method_check', 'other' => 'payroll_method_other'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $error = adminManagementText('csrf_error');
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'save_employee') {
                $employeeId = filter_var($_POST['employee_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                $employeeCode = trim((string)($_POST['employee_code'] ?? ''));
                $fullName = trim((string)($_POST['full_name'] ?? ''));
                $jobTitle = trim((string)($_POST['job_title'] ?? ''));
                $phone = trim((string)($_POST['phone'] ?? ''));
                $email = trim((string)($_POST['email'] ?? ''));
                $monthlySalary = trim((string)($_POST['monthly_salary'] ?? ''));
                $employeeStatus = (string)($_POST['employee_status'] ?? 'active');

                if ($employeeId === false || $employeeCode === '' || strlen($employeeCode) > 40 || $fullName === '' || strlen($fullName) > 180 || $jobTitle === '' || strlen($jobTitle) > 120 || strlen($phone) > 40 || strlen($email) > 190 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) || !is_numeric($monthlySalary) || (float)$monthlySalary <= 0 || (float)$monthlySalary >= 10000000000 || !in_array($employeeStatus, ['active', 'inactive'], true)) {
                    $error = adminManagementText('payroll_employee_validation');
                } else {
                    $values = [
                        ':employee_code' => $employeeCode,
                        ':full_name' => $fullName,
                        ':job_title' => $jobTitle,
                        ':phone' => $phone !== '' ? $phone : null,
                        ':email' => $email !== '' ? $email : null,
                        ':monthly_salary' => number_format((float)$monthlySalary, 2, '.', ''),
                        ':status' => $employeeStatus,
                    ];
                    if ((int)$employeeId > 0) {
                        $values[':id'] = (int)$employeeId;
                        $stmt = $pdo->prepare('UPDATE church_payroll_employees SET employee_code = :employee_code, full_name = :full_name, job_title = :job_title, phone = :phone, email = :email, monthly_salary = :monthly_salary, status = :status WHERE id = :id');
                        $stmt->execute($values);
                    } else {
                        $values[':created_by'] = (int)($_SESSION['admin_id'] ?? 0);
                        $stmt = $pdo->prepare('INSERT INTO church_payroll_employees (employee_code, full_name, job_title, phone, email, monthly_salary, status, created_by) VALUES (:employee_code, :full_name, :job_title, :phone, :email, :monthly_salary, :status, :created_by)');
                        $stmt->execute($values);
                    }
                    header('Location: admin_payroll.php?lang=' . urlencode($adminManagementLanguage) . '&saved=employee');
                    exit;
                }
            } elseif ($action === 'create_payroll') {
                $employeeId = filter_var($_POST['employee_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $period = (string)($_POST['payroll_period'] ?? '');
                $grossSalary = trim((string)($_POST['gross_salary'] ?? ''));
                $incomeTax = trim((string)($_POST['income_tax'] ?? '0'));
                $pension = trim((string)($_POST['pension'] ?? '0'));
                $otherDeductions = trim((string)($_POST['other_deductions'] ?? '0'));
                $notes = trim((string)($_POST['notes'] ?? ''));
                $periodValid = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) === 1;
                $employeeStmt = $pdo->prepare("SELECT id FROM church_payroll_employees WHERE id = :id AND status = 'active' LIMIT 1");
                if ($employeeId !== false) {
                    $employeeStmt->execute([':id' => (int)$employeeId]);
                }
                $employeeExists = $employeeId !== false && (bool)$employeeStmt->fetchColumn();
                $amountsValid = is_numeric($grossSalary) && (float)$grossSalary > 0 && (float)$grossSalary < 10000000000
                    && is_numeric($incomeTax) && (float)$incomeTax >= 0
                    && is_numeric($pension) && (float)$pension >= 0
                    && is_numeric($otherDeductions) && (float)$otherDeductions >= 0;
                $deductionsTotal = (float)$incomeTax + (float)$pension + (float)$otherDeductions;
                if (!$employeeExists || !$periodValid || !$amountsValid || $deductionsTotal > (float)$grossSalary || strlen($notes) > 500) {
                    $error = adminManagementText('payroll_record_validation');
                } else {
                    $netPay = (float)$grossSalary - $deductionsTotal;
                    if ($netPay <= 0) {
                        $error = adminManagementText('payroll_record_validation');
                    } else {
                        $stmt = $pdo->prepare('INSERT INTO church_payroll_records (employee_id, payroll_period, gross_salary, income_tax, pension, other_deductions, net_pay, notes, created_by) VALUES (:employee_id, :payroll_period, :gross_salary, :income_tax, :pension, :other_deductions, :net_pay, :notes, :created_by)');
                        $stmt->execute([
                            ':employee_id' => (int)$employeeId,
                            ':payroll_period' => $period,
                            ':gross_salary' => number_format((float)$grossSalary, 2, '.', ''),
                            ':income_tax' => number_format((float)$incomeTax, 2, '.', ''),
                            ':pension' => number_format((float)$pension, 2, '.', ''),
                            ':other_deductions' => number_format((float)$otherDeductions, 2, '.', ''),
                            ':net_pay' => number_format($netPay, 2, '.', ''),
                            ':notes' => $notes !== '' ? $notes : null,
                            ':created_by' => (int)($_SESSION['admin_id'] ?? 0),
                        ]);
                        $payrollId = (int)$pdo->lastInsertId();
                        $payrollNumber = 'PAY-' . str_replace('-', '', $period) . '-' . str_pad((string)$payrollId, 6, '0', STR_PAD_LEFT);
                        $numberStmt = $pdo->prepare('UPDATE church_payroll_records SET payroll_number = :payroll_number WHERE id = :id');
                        $numberStmt->execute([':payroll_number' => $payrollNumber, ':id' => $payrollId]);
                        header('Location: admin_payroll.php?lang=' . urlencode($adminManagementLanguage) . '&period=' . urlencode($period) . '&saved=payroll');
                        exit;
                    }
                }
            } elseif ($action === 'mark_paid') {
                $payrollId = filter_var($_POST['payroll_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $paymentDate = (string)($_POST['payment_date'] ?? '');
                $paymentDateObject = DateTime::createFromFormat('!Y-m-d', $paymentDate);
                $paymentMethod = (string)($_POST['payment_method'] ?? '');
                $paymentReference = trim((string)($_POST['payment_reference'] ?? ''));
                if ($payrollId === false || !$paymentDateObject || $paymentDateObject->format('Y-m-d') !== $paymentDate || !in_array($paymentMethod, $paymentMethods, true) || strlen($paymentReference) > 120) {
                    $error = adminManagementText('payroll_payment_validation');
                } else {
                    $pdo->beginTransaction();
                    $recordStmt = $pdo->prepare('SELECT id, payroll_number, payroll_period, net_pay, status, finance_transaction_id FROM church_payroll_records WHERE id = :id FOR UPDATE');
                    $recordStmt->execute([':id' => (int)$payrollId]);
                    $record = $recordStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$record || $record['status'] !== 'pending' || !empty($record['finance_transaction_id']) || (float)$record['net_pay'] <= 0) {
                        $pdo->rollBack();
                        $error = adminManagementText('payroll_payment_state_error');
                    } else {
                        $transactionStmt = $pdo->prepare("INSERT INTO finance_transactions (transaction_type, category, description, amount, transaction_date, payment_method, reference, recorded_by) VALUES ('expense', 'Payroll', :description, :amount, :transaction_date, :payment_method, :reference, :recorded_by)");
                        $transactionStmt->execute([
                            ':description' => 'Payroll payment ' . (string)$record['payroll_number'],
                            ':amount' => number_format((float)$record['net_pay'], 2, '.', ''),
                            ':transaction_date' => $paymentDate,
                            ':payment_method' => $paymentMethod,
                            ':reference' => $paymentReference !== '' ? $paymentReference : null,
                            ':recorded_by' => (string)($_SESSION['admin_username'] ?? 'Admin'),
                        ]);
                        $transactionId = (int)$pdo->lastInsertId();
                        recordFinanceAudit($pdo, 'create', 'transaction', $transactionId, null, financeTransactionSnapshot($pdo, $transactionId));
                        $updateStmt = $pdo->prepare("UPDATE church_payroll_records SET status = 'paid', payment_date = :payment_date, payment_method = :payment_method, payment_reference = :payment_reference, finance_transaction_id = :finance_transaction_id WHERE id = :id AND status = 'pending' AND finance_transaction_id IS NULL");
                        $updateStmt->execute([
                            ':payment_date' => $paymentDate,
                            ':payment_method' => $paymentMethod,
                            ':payment_reference' => $paymentReference !== '' ? $paymentReference : null,
                            ':finance_transaction_id' => $transactionId,
                            ':id' => (int)$payrollId,
                        ]);
                        if ($updateStmt->rowCount() !== 1) {
                            throw new RuntimeException('Payroll payment status changed during payment.');
                        }
                        $pdo->commit();
                        header('Location: admin_payroll.php?lang=' . urlencode($adminManagementLanguage) . '&period=' . urlencode((string)$record['payroll_period']) . '&saved=paid');
                        exit;
                    }
                }
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Payroll action failed: ' . $exception->getMessage());
            $error = $exception instanceof PDOException && $exception->getCode() === '23000'
                ? adminManagementText('payroll_duplicate_period')
                : adminManagementText('payroll_save_error');
        }
    }
}

$savedMessages = [
    'employee' => 'payroll_employee_saved',
    'payroll' => 'payroll_record_saved',
    'paid' => 'payroll_paid_saved',
];
$messageKey = $savedMessages[(string)($_GET['saved'] ?? '')] ?? '';
$message = $messageKey !== '' ? adminManagementText($messageKey) : '';
$editEmployeeId = filter_var($_GET['edit_employee'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?: 0;
$editingEmployee = null;
if ($editEmployeeId > 0) {
    $editEmployeeStmt = $pdo->prepare('SELECT * FROM church_payroll_employees WHERE id = :id LIMIT 1');
    $editEmployeeStmt->execute([':id' => $editEmployeeId]);
    $editingEmployee = $editEmployeeStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$employeeFormData = array_merge($editingEmployee ?? [], $_POST);
$period = (string)($_GET['period'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
    $period = date('Y-m');
}
$employees = $pdo->query('SELECT * FROM church_payroll_employees ORDER BY status ASC, full_name ASC LIMIT 300')->fetchAll(PDO::FETCH_ASSOC);
$activeEmployees = array_values(array_filter($employees, static fn(array $employee): bool => $employee['status'] === 'active'));
$payrollStmt = $pdo->prepare('SELECT p.*, e.employee_code, e.full_name, e.job_title FROM church_payroll_records p JOIN church_payroll_employees e ON e.id = p.employee_id WHERE p.payroll_period = :period ORDER BY e.full_name ASC');
$payrollStmt->execute([':period' => $period]);
$payrollRows = $payrollStmt->fetchAll(PDO::FETCH_ASSOC);
$summaryStmt = $pdo->prepare("SELECT COUNT(*) AS records, SUM(status = 'pending') AS pending_records, COALESCE(SUM(CASE WHEN status = 'paid' THEN net_pay ELSE 0 END), 0) AS paid_total FROM church_payroll_records WHERE payroll_period = :period");
$summaryStmt->execute([':period' => $period]);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
$staffEditForm = (string)($_GET['edit_employee'] ?? '') !== '';
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe(adminManagementText('payroll_title')); ?></title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f3f6f4;color:#202b27;font-family:Arial,sans-serif}.topbar{background:#143b36;color:#fff;padding:16px max(20px,calc((100% - 1220px)/2));display:flex;justify-content:space-between;align-items:center;gap:12px}.topbar h1{font-size:20px;margin:0}.topbar nav{display:flex;gap:8px;flex-wrap:wrap}.topbar a,.button{color:#fff;text-decoration:none;background:#236c5e;border:0;padding:9px 12px;border-radius:4px;font:inherit;cursor:pointer}.wrap{max-width:1220px;margin:22px auto;padding:0 18px}.summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:15px}.metric,.panel{background:#fff;border-top:2px solid #d7e2dc;padding:15px;min-width:0}.metric span{display:block;color:#63716c;font-size:13px}.metric strong{display:block;font-size:22px;margin-top:5px}.layout{display:grid;grid-template-columns:minmax(280px,.8fr) minmax(0,1.8fr);gap:15px}.panel h2{font-size:17px;margin:0 0 13px}.forms{display:grid;gap:15px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.field{display:flex;flex-direction:column;gap:5px}.field.full{grid-column:1/-1}label{font-size:13px;font-weight:600}input,select,textarea{width:100%;padding:9px;border:1px solid #cbd5d1;border-radius:4px;background:#fff;color:inherit;font:inherit}textarea{min-height:60px;resize:vertical}.button{display:inline-block}.button.secondary{color:#236c5e;background:#fff;border:1px solid #236c5e}.button.warn{background:#875f22}.notice{padding:10px 12px;margin-bottom:12px;background:#e8f4ef;color:#176244}.notice.error{background:#fff0eb;color:#9b351f}.privacy{background:#fff9e8;border-left:3px solid #b6853b;padding:10px 12px;color:#594a2b;font-size:13px;margin:0 0 14px}.toolbar{display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:12px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;padding:9px 8px;border-bottom:1px solid #e4ebe7;vertical-align:top}th{background:#f3f7f4;white-space:nowrap}.muted{color:#64736e;font-size:12px}.actions{display:grid;gap:7px;min-width:190px}.actions form{display:grid;gap:6px}.actions input,.actions select{padding:7px}.actions a{color:#236c5e}.status{font-weight:700}.status-paid{color:#176244}.status-pending{color:#8a611e}@media(max-width:900px){.layout{grid-template-columns:1fr}.topbar{align-items:flex-start;flex-direction:column}}@media(max-width:620px){.summary{grid-template-columns:1fr}.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.wrap{padding:0 12px}}
    </style>
</head>
<body>
<header class="topbar"><h1><?php echo safe(adminManagementText('payroll_title')); ?></h1><nav><a href="<?php echo safe($adminManagementSwitchUrl); ?>"><?php echo safe(adminManagementText('language_switch')); ?></a><a href="admin_management_dashboard.php?lang=<?php echo safe($adminManagementLanguage); ?>"><?php echo safe(adminManagementText('back_dashboard')); ?></a></nav></header>
<main class="wrap">
    <p class="privacy"><?php echo safe(adminManagementText('payroll_privacy')); ?></p>
    <?php if ($message !== ''): ?><div class="notice"><?php echo safe($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error"><?php echo safe($error); ?></div><?php endif; ?>
    <section class="summary">
        <div class="metric"><span><?php echo safe(adminManagementText('payroll_records')); ?></span><strong><?php echo number_format((int)$summary['records']); ?></strong></div>
        <div class="metric"><span><?php echo safe(adminManagementText('payroll_pending')); ?></span><strong><?php echo number_format((int)$summary['pending_records']); ?></strong></div>
        <div class="metric"><span><?php echo safe(adminManagementText('payroll_paid_total')); ?></span><strong><?php echo number_format((float)$summary['paid_total'], 2); ?></strong></div>
    </section>
    <div class="layout">
        <div class="forms">
            <section class="panel">
                <h2><?php echo safe(adminManagementText($staffEditForm ? 'payroll_edit_employee' : 'payroll_add_employee')); ?></h2>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="save_employee"><input type="hidden" name="employee_id" value="<?php echo (int)($employeeFormData['id'] ?? 0); ?>">
                    <div class="form-grid">
                        <div class="field"><label for="employee_code"><?php echo safe(adminManagementText('payroll_employee_code')); ?></label><input id="employee_code" name="employee_code" maxlength="40" value="<?php echo safe($employeeFormData['employee_code'] ?? ''); ?>" required></div>
                        <div class="field"><label for="full_name"><?php echo safe(adminManagementText('full_name')); ?></label><input id="full_name" name="full_name" maxlength="180" value="<?php echo safe($employeeFormData['full_name'] ?? ''); ?>" required></div>
                        <div class="field"><label for="job_title"><?php echo safe(adminManagementText('payroll_job_title')); ?></label><input id="job_title" name="job_title" maxlength="120" value="<?php echo safe($employeeFormData['job_title'] ?? ''); ?>" required></div>
                        <div class="field"><label for="monthly_salary"><?php echo safe(adminManagementText('payroll_monthly_salary')); ?></label><input id="monthly_salary" name="monthly_salary" type="number" min="0.01" step="0.01" value="<?php echo safe($employeeFormData['monthly_salary'] ?? ''); ?>" required></div>
                        <div class="field"><label for="phone"><?php echo safe(adminManagementText('phone')); ?></label><input id="phone" name="phone" maxlength="40" value="<?php echo safe($employeeFormData['phone'] ?? ''); ?>"></div>
                        <div class="field"><label for="email"><?php echo safe(adminManagementText('email')); ?></label><input id="email" name="email" type="email" maxlength="190" value="<?php echo safe($employeeFormData['email'] ?? ''); ?>"></div>
                        <?php if ($staffEditForm): ?><div class="field"><label for="employee_status"><?php echo safe(adminManagementText('status')); ?></label><select id="employee_status" name="employee_status"><option value="active" <?php echo ($employeeFormData['status'] ?? '') === 'active' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('member_active')); ?></option><option value="inactive" <?php echo ($employeeFormData['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('member_inactive')); ?></option></select></div><?php else: ?><input type="hidden" name="employee_status" value="active"><?php endif; ?>
                    </div>
                    <button class="button" type="submit"><?php echo safe(adminManagementText('payroll_save_employee')); ?></button><?php if ($staffEditForm): ?> <a class="button secondary" href="admin_payroll.php?lang=<?php echo safe($adminManagementLanguage); ?>"><?php echo safe(adminManagementText('cancel_edit')); ?></a><?php endif; ?>
                </form>
            </section>
            <section class="panel">
                <h2><?php echo safe(adminManagementText('payroll_create_record')); ?></h2>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="create_payroll">
                    <div class="form-grid">
                        <div class="field full"><label for="employee_id"><?php echo safe(adminManagementText('payroll_employee')); ?></label><select id="employee_id" name="employee_id" required><option value=""><?php echo safe(adminManagementText('select_member')); ?></option><?php foreach ($activeEmployees as $employee): ?><option value="<?php echo (int)$employee['id']; ?>" data-salary="<?php echo safe($employee['monthly_salary']); ?>"><?php echo safe($employee['employee_code'] . ' · ' . $employee['full_name']); ?></option><?php endforeach; ?></select></div>
                        <div class="field"><label for="payroll_period"><?php echo safe(adminManagementText('payroll_period')); ?></label><input id="payroll_period" name="payroll_period" type="month" value="<?php echo safe($period); ?>" required></div>
                        <div class="field"><label for="gross_salary"><?php echo safe(adminManagementText('payroll_gross_salary')); ?></label><input id="gross_salary" name="gross_salary" type="number" min="0.01" step="0.01" required></div>
                        <div class="field"><label for="income_tax"><?php echo safe(adminManagementText('payroll_income_tax')); ?></label><input id="income_tax" name="income_tax" type="number" min="0" step="0.01" value="0.00" required></div>
                        <div class="field"><label for="pension"><?php echo safe(adminManagementText('payroll_pension')); ?></label><input id="pension" name="pension" type="number" min="0" step="0.01" value="0.00" required></div>
                        <div class="field"><label for="other_deductions"><?php echo safe(adminManagementText('payroll_other_deductions')); ?></label><input id="other_deductions" name="other_deductions" type="number" min="0" step="0.01" value="0.00" required></div>
                        <div class="field"><label for="notes"><?php echo safe(adminManagementText('notes')); ?></label><input id="notes" name="notes" maxlength="500"></div>
                    </div>
                    <button class="button" type="submit"><?php echo safe(adminManagementText('payroll_save_pending')); ?></button>
                </form>
            </section>
        </div>
        <section class="panel">
            <div class="toolbar"><h2><?php echo safe(adminManagementText('payroll_period_records')); ?></h2><form method="get"><input type="hidden" name="lang" value="<?php echo safe($adminManagementLanguage); ?>"><label for="period_filter"><?php echo safe(adminManagementText('payroll_period')); ?></label> <input id="period_filter" type="month" name="period" value="<?php echo safe($period); ?>"> <button class="button" type="submit"><?php echo safe(adminManagementText('filter')); ?></button></form></div>
            <div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('payroll_number')); ?></th><th><?php echo safe(adminManagementText('payroll_employee')); ?></th><th><?php echo safe(adminManagementText('payroll_amounts')); ?></th><th><?php echo safe(adminManagementText('status')); ?></th><th><?php echo safe(adminManagementText('payroll_payment')); ?></th></tr></thead><tbody>
            <?php if (!$payrollRows): ?><tr><td colspan="5"><?php echo safe(adminManagementText('payroll_no_records')); ?></td></tr><?php else: foreach ($payrollRows as $row): ?>
                <tr>
                    <td><?php echo safe($row['payroll_number'] ?? ''); ?><br><span class="muted"><?php echo safe($row['payroll_period']); ?></span></td>
                    <td><?php echo safe($row['full_name']); ?><br><span class="muted"><?php echo safe($row['employee_code'] . ' · ' . $row['job_title']); ?></span></td>
                    <td><?php echo safe(adminManagementText('payroll_gross_short')); ?>: <?php echo number_format((float)$row['gross_salary'], 2); ?><br><?php echo safe(adminManagementText('payroll_deductions_short')); ?>: <?php echo number_format((float)$row['income_tax'] + (float)$row['pension'] + (float)$row['other_deductions'], 2); ?><br><strong><?php echo safe(adminManagementText('payroll_net_short')); ?>: <?php echo number_format((float)$row['net_pay'], 2); ?></strong></td>
                    <td class="status status-<?php echo safe($row['status']); ?>"><?php echo safe(adminManagementText('payroll_status_' . $row['status'])); ?><?php if (!empty($row['finance_transaction_id'])): ?><br><span class="muted"><?php echo safe(adminManagementText('payroll_finance_link')); ?> #<?php echo (int)$row['finance_transaction_id']; ?></span><?php endif; ?></td>
                    <td><?php if ($row['status'] === 'pending'): ?><form method="post" class="actions" onsubmit="return confirm(<?php echo safe(json_encode(adminManagementText('payroll_mark_paid_confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)); ?>);"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="mark_paid"><input type="hidden" name="payroll_id" value="<?php echo (int)$row['id']; ?>"><input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required><select name="payment_method" required><?php foreach ($paymentMethods as $method): ?><option value="<?php echo safe($method); ?>"><?php echo safe(adminManagementText($methodKeys[$method])); ?></option><?php endforeach; ?></select><input name="payment_reference" maxlength="120" placeholder="<?php echo safe(adminManagementText('payroll_payment_reference')); ?>"><button class="button" type="submit"><?php echo safe(adminManagementText('payroll_mark_paid')); ?></button></form><?php else: ?><?php echo safe($row['payment_date'] ?? ''); ?><br><span class="muted"><?php echo safe(adminManagementText($methodKeys[$row['payment_method']] ?? 'payroll_method_other')); ?><?php if (!empty($row['payment_reference'])): ?> · <?php echo safe($row['payment_reference']); ?><?php endif; ?></span><?php endif; ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody></table></div>
        </section>
    </div>
    <section class="panel" style="margin-top:15px"><div class="toolbar"><h2><?php echo safe(adminManagementText('payroll_employees')); ?></h2><span class="muted"><?php echo safe(adminManagementText('payroll_records_limit')); ?></span></div><div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('payroll_employee_code')); ?></th><th><?php echo safe(adminManagementText('full_name')); ?></th><th><?php echo safe(adminManagementText('payroll_job_title')); ?></th><th><?php echo safe(adminManagementText('payroll_monthly_salary')); ?></th><th><?php echo safe(adminManagementText('status')); ?></th><th><?php echo safe(adminManagementText('action')); ?></th></tr></thead><tbody><?php if (!$employees): ?><tr><td colspan="6"><?php echo safe(adminManagementText('payroll_no_employees')); ?></td></tr><?php else: foreach ($employees as $employee): ?><tr><td><?php echo safe($employee['employee_code']); ?></td><td><?php echo safe($employee['full_name']); ?></td><td><?php echo safe($employee['job_title']); ?></td><td><?php echo number_format((float)$employee['monthly_salary'], 2); ?></td><td><?php echo safe(adminManagementText($employee['status'] === 'active' ? 'member_active' : 'member_inactive')); ?></td><td><a href="admin_payroll.php?lang=<?php echo safe($adminManagementLanguage); ?>&edit_employee=<?php echo (int)$employee['id']; ?>"><?php echo safe(adminManagementText('resource_edit_action')); ?></a></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
</main>
<script>
document.getElementById('employee_id')?.addEventListener('change', function () {
    const salary = this.options[this.selectedIndex]?.dataset.salary;
    const grossInput = document.getElementById('gross_salary');
    if (salary && grossInput && !grossInput.value) grossInput.value = salary;
});
</script>
</body>
</html>
