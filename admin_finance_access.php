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
requireRole(['admin'], $pdo);
$csrf = csrfToken();
$messageKey = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $error = adminManagementText('csrf_error');
    } else {
        $action = (string)($_POST['action'] ?? '');
        $accountId = (int)($_POST['admin_user_id'] ?? 0);
        try {
            if ($action === 'grant') {
                $permission = (string)($_POST['permission'] ?? '');
                $accountStmt = $pdo->prepare("SELECT id, username, role FROM admin_users WHERE id = :id AND LOWER(role) <> 'admin' LIMIT 1");
                $accountStmt->execute([':id' => $accountId]);
                $account = $accountStmt->fetch(PDO::FETCH_ASSOC);
                if (!$account || !in_array($permission, ['view', 'manage'], true)) {
                    $error = adminManagementText('finance_invalid_access');
                } else {
                    $pdo->beginTransaction();
                    $existingStmt = $pdo->prepare('SELECT permission FROM finance_access WHERE admin_user_id = :id LIMIT 1');
                    $existingStmt->execute([':id' => $accountId]);
                    $oldPermission = $existingStmt->fetchColumn();
                    $stmt = $pdo->prepare('INSERT INTO finance_access (admin_user_id, permission, granted_by) VALUES (:admin_user_id, :permission, :granted_by) ON DUPLICATE KEY UPDATE permission = VALUES(permission), granted_by = VALUES(granted_by), updated_at = CURRENT_TIMESTAMP');
                    $stmt->execute([
                        ':admin_user_id' => $accountId,
                        ':permission' => $permission,
                        ':granted_by' => (int)$_SESSION['admin_id'],
                    ]);
                    recordFinanceAudit($pdo, $oldPermission === false ? 'grant_access' : 'update_access', 'access', $accountId,
                        $oldPermission === false ? null : ['permission' => $oldPermission],
                        ['username' => $account['username'], 'permission' => $permission]);
                    $pdo->commit();
                    $messageKey = 'finance_access_saved';
                }
            } elseif ($action === 'revoke' && $accountId > 0) {
                $oldStmt = $pdo->prepare('SELECT fa.permission, au.username FROM finance_access fa JOIN admin_users au ON au.id = fa.admin_user_id WHERE fa.admin_user_id = :id LIMIT 1');
                $oldStmt->execute([':id' => $accountId]);
                $oldAccess = $oldStmt->fetch(PDO::FETCH_ASSOC);
                if ($oldAccess) {
                    $pdo->beginTransaction();
                    $deleteStmt = $pdo->prepare('DELETE FROM finance_access WHERE admin_user_id = :id');
                    $deleteStmt->execute([':id' => $accountId]);
                    recordFinanceAudit($pdo, 'revoke_access', 'access', $accountId, $oldAccess, null);
                    $pdo->commit();
                }
                $messageKey = 'finance_access_removed';
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Finance access update failed: ' . $exception->getMessage());
            $error = adminManagementText('finance_save_error');
        }
    }
}

$accounts = $pdo->query("SELECT id, username, email, role FROM admin_users WHERE LOWER(role) <> 'admin' ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
$grants = $pdo->query('SELECT fa.admin_user_id, fa.permission, fa.updated_at, au.username, au.email, au.role, grantor.username AS granted_by_name FROM finance_access fa JOIN admin_users au ON au.id = fa.admin_user_id LEFT JOIN admin_users grantor ON grantor.id = fa.granted_by ORDER BY au.username ASC')->fetchAll(PDO::FETCH_ASSOC);
$auditRows = $pdo->query('SELECT actor_username, action, entity_type, entity_id, old_data, new_data, created_at FROM finance_audit_log ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe(adminManagementText('finance_share_access')); ?></title>
    <style>
        :root{font-family:Arial,sans-serif;color:#202b2b;background:#f4f7f5;font-synthesis:none}*{box-sizing:border-box}body{margin:0}.topbar{background:#143b36;color:#fff;padding:16px max(20px,calc((100% - 1160px)/2));display:flex;justify-content:space-between;align-items:center;gap:12px}.topbar h1{font-size:20px;margin:0}.topbar a{color:#fff;text-decoration:none;border:1px solid #739188;border-radius:4px;padding:9px 12px}.nav{display:flex;gap:8px;flex-wrap:wrap}.wrap{max-width:1160px;margin:24px auto;padding:0 18px}.section{background:#fff;padding:18px;border-top:2px solid #d7e2dc;margin-bottom:18px}.section h2{font-size:18px;margin:0 0 14px}.form-row{display:grid;grid-template-columns:2fr 1fr auto;gap:10px;align-items:end}.field{display:flex;flex-direction:column;gap:5px}label{font-size:13px;font-weight:600}input,select{padding:10px;border:1px solid #cbd5d1;border-radius:4px;background:#fff;font:inherit}.button{padding:10px 14px;border:0;border-radius:4px;background:#236c5e;color:#fff;font:inherit;cursor:pointer}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;padding:10px 8px;border-bottom:1px solid #e5ebe7;vertical-align:top}th{background:#f3f7f4}.notice{padding:11px 13px;margin-bottom:14px;background:#e8f4ef;color:#176244}.error{background:#fff0eb;color:#9b351f}.inline{display:inline}.revoke{background:none;border:0;padding:0;color:#a33b35;text-decoration:underline;cursor:pointer;font:inherit}.muted{color:#65736e}.json{max-width:360px;white-space:pre-wrap;overflow-wrap:anywhere}.empty{padding:12px 0;color:#65736e}@media(max-width:700px){.topbar{align-items:flex-start;flex-direction:column}.form-row{grid-template-columns:1fr}}
    </style>
</head>
<body>
<header class="topbar"><h1><?php echo safe(adminManagementText('finance_share_access')); ?></h1><nav class="nav"><a href="<?php echo safe($adminManagementSwitchUrl); ?>"><?php echo safe(adminManagementText('language_switch')); ?></a><a href="admin_finance.php">← <?php echo safe(adminManagementText('finance_title')); ?></a></nav></header>
<main class="wrap">
    <?php if ($messageKey !== ''): ?><div class="notice"><?php echo safe(adminManagementText($messageKey)); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error"><?php echo safe($error); ?></div><?php endif; ?>
    <section class="section">
        <h2><?php echo safe(adminManagementText('finance_grant_access')); ?></h2>
        <form method="post" class="form-row">
            <input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="grant">
            <div class="field"><label for="admin_user_id"><?php echo safe(adminManagementText('finance_account')); ?></label><select id="admin_user_id" name="admin_user_id" required><option value=""><?php echo safe(adminManagementText('finance_select_account')); ?></option><?php foreach ($accounts as $account): ?><option value="<?php echo (int)$account['id']; ?>"><?php echo safe($account['username']); ?> · <?php echo safe($account['role']); ?> · <?php echo safe($account['email']); ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="permission"><?php echo safe(adminManagementText('status')); ?></label><select id="permission" name="permission"><option value="view"><?php echo safe(adminManagementText('finance_view_only')); ?></option><option value="manage"><?php echo safe(adminManagementText('finance_manage_permission')); ?></option></select></div>
            <button class="button" type="submit"><?php echo safe(adminManagementText('finance_grant_access')); ?></button>
        </form>
    </section>
    <section class="section">
        <h2><?php echo safe(adminManagementText('finance_share_access')); ?></h2>
        <?php if (!$grants): ?><div class="empty"><?php echo safe(adminManagementText('finance_no_access')); ?></div><?php else: ?><div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('finance_account')); ?></th><th><?php echo safe(adminManagementText('status')); ?></th><th><?php echo safe(adminManagementText('finance_actor')); ?></th><th><?php echo safe(adminManagementText('finance_changed_at')); ?></th><th></th></tr></thead><tbody><?php foreach ($grants as $grant): ?><tr><td><?php echo safe($grant['username']); ?> · <?php echo safe($grant['role']); ?><br><span class="muted"><?php echo safe($grant['email']); ?></span></td><td><?php echo safe(adminManagementText($grant['permission'] === 'view' ? 'finance_view_only' : 'finance_manage_permission')); ?></td><td><?php echo safe($grant['granted_by_name'] ?? ''); ?></td><td><?php echo safe($grant['updated_at']); ?></td><td><form method="post" onsubmit="return confirm(<?php echo htmlspecialchars(json_encode(adminManagementText('finance_revoke_access')), ENT_QUOTES, 'UTF-8'); ?>)"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="revoke"><input type="hidden" name="admin_user_id" value="<?php echo (int)$grant['admin_user_id']; ?>"><button class="revoke" type="submit"><?php echo safe(adminManagementText('finance_revoke_access')); ?></button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
    <section class="section">
        <h2><?php echo safe(adminManagementText('finance_audit_log')); ?></h2>
        <?php if (!$auditRows): ?><div class="empty"><?php echo safe(adminManagementText('no_finance_records')); ?></div><?php else: ?><div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('finance_changed_at')); ?></th><th><?php echo safe(adminManagementText('finance_actor')); ?></th><th><?php echo safe(adminManagementText('finance_action')); ?></th><th><?php echo safe(adminManagementText('finance_entity')); ?></th><th><?php echo safe(adminManagementText('finance_before')); ?></th><th><?php echo safe(adminManagementText('finance_after')); ?></th></tr></thead><tbody><?php foreach ($auditRows as $audit): ?><tr><td><?php echo safe($audit['created_at']); ?></td><td><?php echo safe($audit['actor_username']); ?></td><td><?php echo safe($audit['action']); ?></td><td><?php echo safe($audit['entity_type'] . ' #' . ($audit['entity_id'] ?? '')); ?></td><td class="json"><?php echo safe($audit['old_data'] ?? ''); ?></td><td class="json"><?php echo safe($audit['new_data'] ?? ''); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
</main>
</body>
</html>