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
$editingBranch = null;

if ($editingId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM church_branches WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $editingId]);
    $editingBranch = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(400);
        exit(adminManagementText('csrf_error'));
    }

    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save_branch') {
        $branchId = (int)($_POST['id'] ?? 0);
        $branchName = trim((string)($_POST['branch_name'] ?? ''));
        $parentId = (int)($_POST['parent_id'] ?? 0);
        $address = trim((string)($_POST['address'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $status = (string)($_POST['status'] ?? 'active');

        if ($branchName === '' || strlen($branchName) > 180 || !in_array($status, ['active', 'inactive'], true)) {
            $error = adminManagementText('branch_invalid');
        } else {
            $parentValid = true;
            if ($parentId > 0) {
                $parentCheck = $pdo->prepare('SELECT id FROM church_branches WHERE id = :id LIMIT 1');
                $parentCheck->execute([':id' => $parentId]);
                $parentValid = (bool)$parentCheck->fetchColumn();
                $ancestorId = $parentId;
                $ancestorStmt = $pdo->prepare('SELECT parent_id FROM church_branches WHERE id = :id LIMIT 1');
                for ($depth = 0; $parentValid && $ancestorId > 0 && $depth < 100; $depth++) {
                    if ($ancestorId === $branchId && $branchId > 0) {
                        $parentValid = false;
                        break;
                    }
                    $ancestorStmt->execute([':id' => $ancestorId]);
                    $nextAncestor = $ancestorStmt->fetchColumn();
                    $ancestorId = $nextAncestor === false || $nextAncestor === null ? 0 : (int)$nextAncestor;
                }
                if ($ancestorId > 0 && $depth >= 100) {
                    $parentValid = false;
                }
            }

            if (!$parentValid) {
                $error = adminManagementText('branch_invalid_parent');
            } else {
                try {
                    $pdo->beginTransaction();
                    if ($branchId > 0) {
                        $stmt = $pdo->prepare('UPDATE church_branches SET branch_name = :branch_name, parent_id = :parent_id, address = :address, phone = :phone, status = :status WHERE id = :id');
                        $stmt->execute([
                            ':branch_name' => $branchName,
                            ':parent_id' => $parentId > 0 ? $parentId : null,
                            ':address' => $address !== '' ? $address : null,
                            ':phone' => $phone !== '' ? $phone : null,
                            ':status' => $status,
                            ':id' => $branchId,
                        ]);
                    } else {
                        $stmt = $pdo->prepare('INSERT INTO church_branches (branch_code, branch_name, parent_id, address, phone, status, created_by) VALUES (:branch_code, :branch_name, :parent_id, :address, :phone, :status, :created_by)');
                        $stmt->execute([
                            ':branch_code' => 'TMP-' . bin2hex(random_bytes(8)),
                            ':branch_name' => $branchName,
                            ':parent_id' => $parentId > 0 ? $parentId : null,
                            ':address' => $address !== '' ? $address : null,
                            ':phone' => $phone !== '' ? $phone : null,
                            ':status' => $status,
                            ':created_by' => (int)$_SESSION['admin_id'],
                        ]);
                        $branchId = (int)$pdo->lastInsertId();
                        $codeStmt = $pdo->prepare('UPDATE church_branches SET branch_code = :code WHERE id = :id');
                        $codeStmt->execute([':code' => 'BR-' . str_pad((string)$branchId, 6, '0', STR_PAD_LEFT), ':id' => $branchId]);
                    }
                    $pdo->commit();
                    header('Location: admin_church_branches.php?saved=1');
                    exit;
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Church branch save failed: ' . $exception->getMessage());
                    $error = adminManagementText('branch_save_error');
                }
            }
        }
    } elseif ($action === 'set_status') {
        $branchId = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if ($branchId > 0 && in_array($status, ['active', 'inactive'], true)) {
            $stmt = $pdo->prepare('UPDATE church_branches SET status = :status WHERE id = :id');
            $stmt->execute([':status' => $status, ':id' => $branchId]);
            header('Location: admin_church_branches.php');
            exit;
        }
        $error = adminManagementText('branch_status_error');
    }
}

if (isset($_GET['saved'])) {
    $message = adminManagementText('branch_saved');
}
$branches = $pdo->query('SELECT b.*, p.branch_name AS parent_name, COUNT(m.id) AS member_count FROM church_branches b LEFT JOIN church_branches p ON p.id = b.parent_id LEFT JOIN church_members m ON m.branch_id = b.id GROUP BY b.id, p.branch_name ORDER BY b.status ASC, b.branch_name ASC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
$parentOptions = $pdo->query("SELECT id, branch_code, branch_name, status FROM church_branches ORDER BY branch_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe(adminManagementText('branch_title')); ?></title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f2f5f3;color:#202b27;font-family:Arial,sans-serif}.topbar{background:#143b36;color:#fff;padding:16px max(20px,calc((100% - 1120px)/2));display:flex;justify-content:space-between;align-items:center;gap:12px}.topbar h1{font-size:20px;margin:0}.topbar a{color:#fff;text-decoration:none;border:1px solid #739188;padding:9px 12px;border-radius:4px}.wrap{max-width:1120px;margin:22px auto;padding:0 18px}.columns{display:grid;grid-template-columns: minmax(260px,340px) minmax(0,1fr);gap:16px}.section{background:#fff;padding:18px;border-top:2px solid #d7e2dc;min-width:0}.section h2{font-size:17px;margin:0 0 14px}.field{display:flex;flex-direction:column;gap:5px;margin-bottom:11px}label{font-size:13px;font-weight:600}input,select{width:100%;padding:10px;border:1px solid #cbd5d1;border-radius:4px;font:inherit;background:#fff}.button{padding:9px 13px;border:0;border-radius:4px;background:#236c5e;color:#fff;text-decoration:none;font:inherit;cursor:pointer}.secondary{background:#fff;color:#236c5e;border:1px solid #236c5e}.notice{padding:10px 12px;background:#e8f4ef;color:#176244;margin-bottom:12px}.error{background:#fff0eb;color:#9b351f}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;padding:10px 8px;border-bottom:1px solid #e5ebe7;vertical-align:top}th{background:#f3f7f4;white-space:nowrap}.actions{display:flex;align-items:center;gap:9px;white-space:nowrap}.actions a,.actions button{color:#236c5e;background:none;border:0;padding:0;text-decoration:underline;cursor:pointer;font:inherit}.empty{color:#65736e}@media(max-width:760px){.topbar{align-items:flex-start;flex-direction:column}.columns{grid-template-columns:1fr}}
    </style>
</head>
<body>
<header class="topbar"><h1><?php echo safe(adminManagementText('branch_title')); ?></h1><nav><a href="<?php echo safe($adminManagementSwitchUrl); ?>"><?php echo safe(adminManagementText('language_switch')); ?></a> <a href="admin_church_members.php"><?php echo safe(adminManagementText('church_members_page')); ?></a> <a href="admin_management_dashboard.php"><?php echo safe(adminManagementText('back_dashboard')); ?></a></nav></header>
<main class="wrap">
    <?php if ($message !== ''): ?><div class="notice"><?php echo safe($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error"><?php echo safe($error); ?></div><?php endif; ?>
    <div class="columns">
        <section class="section">
            <h2><?php echo safe(adminManagementText($editingBranch ? 'branch_edit' : 'branch_add')); ?></h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>">
                <input type="hidden" name="action" value="save_branch">
                <input type="hidden" name="id" value="<?php echo (int)($editingBranch['id'] ?? 0); ?>">
                <div class="field"><label for="branch_name"><?php echo safe(adminManagementText('branch_name')); ?></label><input id="branch_name" name="branch_name" maxlength="180" value="<?php echo safe($editingBranch['branch_name'] ?? ''); ?>" required></div>
                <div class="field"><label for="parent_id"><?php echo safe(adminManagementText('branch_parent')); ?></label><select id="parent_id" name="parent_id"><option value="0"><?php echo safe(adminManagementText('branch_none')); ?></option><?php foreach ($parentOptions as $option): if ((int)$option['id'] === $editingId) continue; ?><option value="<?php echo (int)$option['id']; ?>" <?php echo (int)($editingBranch['parent_id'] ?? 0) === (int)$option['id'] ? 'selected' : ''; ?>><?php echo safe($option['branch_name'] . ' · ' . $option['branch_code']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="address"><?php echo safe(adminManagementText('address')); ?></label><input id="address" name="address" maxlength="255" value="<?php echo safe($editingBranch['address'] ?? ''); ?>"></div>
                <div class="field"><label for="phone"><?php echo safe(adminManagementText('phone')); ?></label><input id="phone" name="phone" maxlength="40" value="<?php echo safe($editingBranch['phone'] ?? ''); ?>"></div>
                <div class="field"><label for="status"><?php echo safe(adminManagementText('status')); ?></label><select id="status" name="status"><option value="active" <?php echo ($editingBranch['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('member_active')); ?></option><option value="inactive" <?php echo ($editingBranch['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('member_inactive')); ?></option></select></div>
                <button class="button" type="submit"><?php echo safe(adminManagementText('save_record')); ?></button><?php if ($editingBranch): ?> <a class="button secondary" href="admin_church_branches.php"><?php echo safe(adminManagementText('cancel_edit')); ?></a><?php endif; ?>
            </form>
        </section>
        <section class="section">
            <h2><?php echo safe(adminManagementText('branch_directory')); ?></h2>
            <div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('branch_code')); ?></th><th><?php echo safe(adminManagementText('branch_name')); ?></th><th><?php echo safe(adminManagementText('branch_parent')); ?></th><th><?php echo safe(adminManagementText('branch_member_count')); ?></th><th><?php echo safe(adminManagementText('status')); ?></th><th><?php echo safe(adminManagementText('action')); ?></th></tr></thead><tbody>
            <?php if (!$branches): ?><tr><td class="empty" colspan="6"><?php echo safe(adminManagementText('branch_no_records')); ?></td></tr><?php else: foreach ($branches as $branch): ?><tr><td><?php echo safe($branch['branch_code']); ?></td><td><?php echo safe($branch['branch_name']); ?></td><td><?php echo safe($branch['parent_name'] ?? '—'); ?></td><td><?php echo (int)$branch['member_count']; ?></td><td><?php echo safe(adminManagementText($branch['status'] === 'active' ? 'member_active' : 'branch_inactive')); ?></td><td><div class="actions"><a href="admin_church_branches.php?lang=<?php echo safe($adminManagementLanguage); ?>&edit=<?php echo (int)$branch['id']; ?>"><?php echo safe(adminManagementText('resource_edit_action')); ?></a><form method="post"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="set_status"><input type="hidden" name="id" value="<?php echo (int)$branch['id']; ?>"><input type="hidden" name="status" value="<?php echo $branch['status'] === 'active' ? 'inactive' : 'active'; ?>"><button type="submit"><?php echo safe(adminManagementText($branch['status'] === 'active' ? 'branch_deactivate' : 'branch_activate')); ?></button></form></div></td></tr><?php endforeach; endif; ?>
            </tbody></table></div>
        </section>
    </div>
</main>
</body>
</html>
