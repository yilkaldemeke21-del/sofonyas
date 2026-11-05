<?php

function requireFinanceAccess(PDO $pdo): array
{
    if (!function_exists('adminManagementText')) {
        require_once __DIR__ . '/admin_management_i18n.php';
    }

    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    if ($adminId < 1) {
        header('Location: admin_login.php');
        exit;
    }

    $role = strtolower((string)getCurrentUserRole($pdo));
    if ($role === 'admin') {
        return [
            'permission' => 'manage',
            'is_admin' => true,
            'can_manage' => true,
            'can_manage_access' => true,
            'show_donor_details' => true,
        ];
    }

    $stmt = $pdo->prepare('SELECT permission FROM finance_access WHERE admin_user_id = :admin_user_id LIMIT 1');
    $stmt->execute([':admin_user_id' => $adminId]);
    $permission = (string)($stmt->fetchColumn() ?: '');
    if (!in_array($permission, ['view', 'manage'], true)) {
        http_response_code(403);
        exit(adminManagementText('finance_access_denied'));
    }

    $canManage = $permission === 'manage';
    return [
        'permission' => $permission,
        'is_admin' => false,
        'can_manage' => $canManage,
        'can_manage_access' => false,
        'show_donor_details' => $canManage,
    ];
}

function recordFinanceAudit(PDO $pdo, string $action, string $entityType, ?int $entityId, ?array $oldData, ?array $newData): void
{
    $stmt = $pdo->prepare('INSERT INTO finance_audit_log (actor_id, actor_username, action, entity_type, entity_id, old_data, new_data) VALUES (:actor_id, :actor_username, :action, :entity_type, :entity_id, :old_data, :new_data)');
    $stmt->execute([
        ':actor_id' => isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
        ':actor_username' => (string)($_SESSION['admin_username'] ?? 'Admin'),
        ':action' => $action,
        ':entity_type' => $entityType,
        ':entity_id' => $entityId,
        ':old_data' => $oldData === null ? null : json_encode($oldData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        ':new_data' => $newData === null ? null : json_encode($newData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
    ]);
}

function financeTransactionSnapshot(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM finance_transactions WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    return $record ?: null;
}