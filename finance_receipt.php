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
$id = (int)($_GET['id'] ?? 0);
$receiptColumns = 'ft.receipt_number, ft.transaction_type, ft.giving_type, ft.is_anonymous, ft.category, ft.description, ft.amount, ft.transaction_date, ft.payment_method, ft.reference, p.project_name';
if ($financeAccess['show_donor_details']) {
    $receiptColumns .= ', ft.donor_name';
}
$stmt = $pdo->prepare('SELECT ' . $receiptColumns . ' FROM finance_transactions ft LEFT JOIN finance_projects p ON p.id = ft.project_id WHERE ft.id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$receipt = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$receipt || $receipt['transaction_type'] !== 'income' || empty($receipt['receipt_number'])) {
    http_response_code(404);
    exit('Receipt not found.');
}

$donorName = $financeAccess['show_donor_details'] && empty($receipt['is_anonymous']) && !empty($receipt['donor_name'])
    ? $receipt['donor_name']
    : (!$financeAccess['show_donor_details'] ? adminManagementText('finance_donor_withheld') : adminManagementText('finance_anonymous_label'));
$givingLabels = [
    'general' => 'finance_general_income',
    'tithe' => 'finance_tithe',
    'donation' => 'finance_donation',
];
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe(adminManagementText('finance_receipt')); ?> · <?php echo safe($receipt['receipt_number']); ?></title>
    <style>
        :root{font-family:Arial,sans-serif;color:#202b2b;background:#f4f7f5;font-synthesis:none}*{box-sizing:border-box}body{margin:0;padding:24px}.receipt{max-width:680px;margin:22px auto;background:#fff;border:1px solid #d8e2dc;padding:32px}.brand{border-bottom:2px solid #236c5e;padding-bottom:16px;margin-bottom:22px}.brand h1{font-size:22px;margin:0 0 5px}.muted{color:#65736e}.number{display:flex;justify-content:space-between;gap:14px;border-bottom:1px solid #e5ebe7;padding:11px 0}.number strong{text-align:right}.amount{font-size:25px;color:#236c5e}.actions{max-width:680px;margin:0 auto 12px;display:flex;gap:9px}.actions a,.actions button{border:0;background:#236c5e;color:#fff;text-decoration:none;padding:10px 14px;border-radius:4px;font:inherit;cursor:pointer}@media print{body{padding:0;background:#fff}.receipt{border:0;margin:0 auto;padding:12px 20px}.actions{display:none}}
    </style>
</head>
<body>
<nav class="actions"><button type="button" onclick="window.print()"><?php echo safe(adminManagementText('finance_print_receipt')); ?></button><a href="admin_finance.php"><?php echo safe(adminManagementText('finance_title')); ?></a></nav>
<main class="receipt">
    <header class="brand"><h1><?php echo safe(adminManagementText('finance_receipt_for')); ?></h1><div class="muted"><?php echo safe(adminManagementText('finance_receipt_brand')); ?></div></header>
    <div class="number"><span><?php echo safe(adminManagementText('finance_receipt_number')); ?></span><strong><?php echo safe($receipt['receipt_number']); ?></strong></div>
    <div class="number"><span><?php echo safe(adminManagementText('date')); ?></span><strong><?php echo safe($receipt['transaction_date']); ?></strong></div>
    <div class="number"><span><?php echo safe(adminManagementText('finance_received_from')); ?></span><strong><?php echo safe($donorName); ?></strong></div>
    <div class="number"><span><?php echo safe(adminManagementText('finance_giving_type')); ?></span><strong><?php echo safe(adminManagementText($givingLabels[$receipt['giving_type']] ?? 'finance_general_income')); ?></strong></div>
    <div class="number"><span><?php echo safe(adminManagementText('category')); ?></span><strong><?php echo safe($receipt['category']); ?></strong></div>
    <div class="number"><span><?php echo safe(adminManagementText('description')); ?></span><strong><?php echo safe($receipt['description']); ?></strong></div>
    <?php if (!empty($receipt['project_name'])): ?><div class="number"><span><?php echo safe(adminManagementText('finance_project')); ?></span><strong><?php echo safe($receipt['project_name']); ?></strong></div><?php endif; ?>
    <div class="number"><span><?php echo safe(adminManagementText('payment_method')); ?></span><strong><?php echo safe($receipt['payment_method'] ?? ''); ?></strong></div>
    <div class="number"><span><?php echo safe(adminManagementText('amount')); ?></span><strong class="amount"><?php echo number_format((float)$receipt['amount'], 2); ?></strong></div>
</main>
</body>
</html>