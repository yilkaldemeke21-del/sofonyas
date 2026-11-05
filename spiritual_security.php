<?php

function requireSpiritualAccess(PDO $pdo): array
{
    if (!function_exists('adminManagementText')) {
        require_once __DIR__ . '/admin_management_i18n.php';
    }

    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    if ($adminId < 1) {
        header('Location: admin_login.php');
        exit;
    }

    if (strtolower((string)getCurrentUserRole($pdo)) === 'admin') {
        return [
            'permission' => 'manage',
            'is_admin' => true,
            'can_manage' => true,
            'can_manage_confidential' => true,
        ];
    }

    $stmt = $pdo->prepare('SELECT permission, can_manage_confidential FROM spiritual_access WHERE admin_user_id = :admin_user_id LIMIT 1');
    $stmt->execute([':admin_user_id' => $adminId]);
    $grant = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$grant || !in_array($grant['permission'], ['view', 'manage'], true)) {
        http_response_code(403);
        exit(adminManagementText('spiritual_access_denied'));
    }

    return [
        'permission' => $grant['permission'],
        'is_admin' => false,
        'can_manage' => $grant['permission'] === 'manage',
        'can_manage_confidential' => (bool)$grant['can_manage_confidential'],
    ];
}