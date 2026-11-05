<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/management_schema.php';
require_once __DIR__ . '/admin_management_i18n.php';

if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit(adminManagementText('db_unavailable'));
}
ensureManagementTables($pdo);
requireRole(['admin'], $pdo);
$csrf = csrfToken();
$errorKey = '';
$savedKey = [
    'saved' => 'spiritual_access_saved',
    'removed' => 'spiritual_access_removed',
][(string)($_GET['saved'] ?? '')] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $errorKey = 'csrf_error';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $accountId = (int)($_POST['admin_user_id'] ?? 0);
        try {
            if ($action === 'grant') {
                $permission = (string)($_POST['permission'] ?? '');
                $canManageConfidential = isset($_POST['can_manage_confidential']) && $permission === 'manage' ? 1 : 0;
                $accountStmt = $pdo->prepare("SELECT id FROM admin_users WHERE id = :id AND LOWER(role) <> 'admin' LIMIT 1");
                $accountStmt->execute([':id' => $accountId]);
                if (!$accountStmt->fetchColumn() || !in_array($permission, ['view', 'manage'], true)) {
                    $errorKey = 'finance_invalid_access';
                } else {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare('INSERT INTO spiritual_access (admin_user_id, permission, can_manage_confidential, granted_by) VALUES (:admin_user_id, :permission, :can_manage_confidential, :granted_by) ON DUPLICATE KEY UPDATE permission = VALUES(permission), can_manage_confidential = VALUES(can_manage_confidential), granted_by = VALUES(granted_by), updated_at = CURRENT_TIMESTAMP');
                    $stmt->execute([
                        ':admin_user_id' => $accountId,
                        ':permission' => $permission,
                        ':can_manage_confidential' => $canManageConfidential,
                        ':granted_by' => (int)$_SESSION['admin_id'],
                    ]);
                    $pdo->commit();
                    header('Location: admin_spiritual_access.php?lang=' . urlencode($adminManagementLanguage) . '&saved=saved');
                    exit;
                }
            } elseif ($action === 'revoke' && $accountId > 0) {
                $stmt = $pdo->prepare('DELETE FROM spiritual_access WHERE admin_user_id = :id');
                $stmt->execute([':id' => $accountId]);
                header('Location: admin_spiritual_access.php?lang=' . urlencode($adminManagementLanguage) . '&saved=removed');
                exit;
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Spiritual permission update failed: ' . $exception->getMessage());
            $errorKey = 'spiritual_save_failed';
        }
    }
}

$accounts = $pdo->query("SELECT id, username, email, role FROM admin_users WHERE LOWER(role) <> 'admin' ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
$grants = $pdo->query('SELECT sa.admin_user_id, sa.permission, sa.can_manage_confidential, sa.updated_at, au.username, au.email, au.role, grantor.username AS granted_by_name FROM spiritual_access sa JOIN admin_users au ON au.id = sa.admin_user_id LEFT JOIN admin_users grantor ON grantor.id = sa.granted_by ORDER BY au.username ASC')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe(adminManagementText('spiritual_permissions')); ?></title>
    <style>
        :root{font-family:Arial,sans-serif;color:#202b2b;background:#f4f7f5;font-synthesis:none}*{box-sizing:border-box}body{margin:0}.topbar{background:#143b36;color:#fff;padding:16px max(20px,calc((100% - 1100px)/2));display:flex;justify-content:space-between;align-items:center;gap:12px}.topbar h1{font-size:20px;margin:0}.nav{display:flex;gap:8px;flex-wrap:wrap}.topbar a{color:#fff;text-decoration:none;border:1px solid #739188;border-radius:4px;padding:9px 12px}.wrap{max-width:1100px;margin:24px auto;padding:0 18px}.section{background:#fff;padding:18px;border-top:2px solid #d7e2dc;margin-bottom:18px}.section h2{font-size:18px;margin:0 0 14px}.form-row{display:grid;grid-template-columns:2fr 1fr auto;gap:12px;align-items:end}.field{display:flex;flex-direction:column;gap:6px}label{font-size:13px;font-weight:600}input,select{padding:10px;border:1px solid #cbd5d1;border-radius:4px;background:#fff;font:inherit}.check{display:flex;gap:8px;align-items:center;margin-top:12px}.check input{width:auto}.button{padding:10px 14px;border:0;border-radius:4px;background:#236c5e;color:#fff;font:inherit;cursor:pointer}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;padding:10px 8px;border-bottom:1px solid #e5ebe7;vertical-align:top}th{background:#f3f7f4}.notice{padding:11px 13px;margin-bottom:14px;background:#e8f4ef;color:#176244}.error{background:#fff0eb;color:#9b351f}.revoke{border:0;padding:0;background:none;color:#a33b35;text-decoration:underline;cursor:pointer;font:inherit}.empty{color:#65736e}@media(max-width:720px){.topbar{align-items:flex-start;flex-direction:column}.form-row{grid-template-columns:1fr}}
    </style>
</head>
<body>
<header class="topbar"><h1><?php echo safe(adminManagementText('spiritual_permissions')); ?></h1><nav class="nav"><a href="<?php echo safe($adminManagementSwitchUrl); ?>"><?php echo safe(adminManagementText('language_switch')); ?></a><a href="admin_spiritual_dashboard.php">← <?php echo safe(adminManagementText('spiritual_dashboard_title')); ?></a></nav></header>
<main class="wrap">
    <?php if ($savedKey !== ''): ?><div class="notice"><?php echo safe(adminManagementText($savedKey)); ?></div><?php endif; ?>
    <?php if ($errorKey !== ''): ?><div class="notice error"><?php echo safe(adminManagementText($errorKey)); ?></div><?php endif; ?>
    <section class="section">
        <h2><?php echo safe(adminManagementText('spiritual_grant_access')); ?></h2>
        <form method="post" class="form-row">
            <input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="grant">
            <div class="field"><label for="admin_user_id"><?php echo safe(adminManagementText('finance_account')); ?></label><select id="admin_user_id" name="admin_user_id" required><option value=""><?php echo safe(adminManagementText('finance_select_account')); ?></option><?php foreach ($accounts as $account): ?><option value="<?php echo (int)$account['id']; ?>"><?php echo safe($account['username']); ?> · <?php echo safe($account['role']); ?> · <?php echo safe($account['email']); ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="permission"><?php echo safe(adminManagementText('status')); ?></label><select id="permission" name="permission"><option value="view"><?php echo safe(adminManagementText('finance_view_only')); ?></option><option value="manage"><?php echo safe(adminManagementText('finance_manage_permission')); ?></option></select></div>
            <button class="button" type="submit"><?php echo safe(adminManagementText('spiritual_grant_access')); ?></button>
            <label class="check"><input type="checkbox" name="can_manage_confidential" value="1"> <?php echo safe(adminManagementText('spiritual_view_private')); ?></label>
        </form>
    </section>
    <section class="section">
        <h2><?php echo safe(adminManagementText('spiritual_permissions')); ?></h2>
        <?php if (!$grants): ?><p class="empty"><?php echo safe(adminManagementText('spiritual_no_grants')); ?></p><?php else: ?><div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('finance_account')); ?></th><th><?php echo safe(adminManagementText('status')); ?></th><th><?php echo safe(adminManagementText('spiritual_appointment')); ?></th><th><?php echo safe(adminManagementText('finance_actor')); ?></th><th><?php echo safe(adminManagementText('finance_changed_at')); ?></th><th></th></tr></thead><tbody><?php foreach ($grants as $grant): ?><tr><td><?php echo safe($grant['username']); ?> · <?php echo safe($grant['role']); ?><br><?php echo safe($grant['email']); ?></td><td><?php echo safe(adminManagementText($grant['permission'] === 'view' ? 'finance_view_only' : 'finance_manage_permission')); ?></td><td><?php echo safe(adminManagementText($grant['can_manage_confidential'] ? 'resource_active' : 'resource_inactive')); ?></td><td><?php echo safe($grant['granted_by_name'] ?? ''); ?></td><td><?php echo safe($grant['updated_at']); ?></td><td><form method="post" onsubmit="return confirm(<?php echo htmlspecialchars(json_encode(adminManagementText('finance_revoke_access')), ENT_QUOTES, 'UTF-8'); ?>)"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="revoke"><input type="hidden" name="admin_user_id" value="<?php echo (int)$grant['admin_user_id']; ?>"><button class="revoke" type="submit"><?php echo safe(adminManagementText('finance_revoke_access')); ?></button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
</main>
</body>
</html>