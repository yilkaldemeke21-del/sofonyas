<?php
session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload: ' . json_last_error_msg()]);
    exit;
}

$data = $payload;
if (isset($payload['subscription']) && is_array($payload['subscription'])) {
    $data = $payload['subscription'];
}

$endpoint = isset($data['endpoint']) && is_string($data['endpoint']) ? trim($data['endpoint']) : '';
$keys = isset($data['keys']) && is_array($data['keys']) ? $data['keys'] : [];
$p256dh = isset($keys['p256dh']) && is_string($keys['p256dh']) ? trim($keys['p256dh']) : '';
$auth = isset($keys['auth']) && is_string($keys['auth']) ? trim($keys['auth']) : '';

if ($endpoint === '' || $p256dh === '' || $auth === '') {
    error_log('push_subscribe.php payload validation failed: ' . print_r($payload, true));
    echo json_encode(['success' => false, 'message' => 'Invalid push subscription data.']);
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO push_subscriptions (endpoint, p256dh, auth) VALUES (:endpoint, :p256dh, :auth) ON DUPLICATE KEY UPDATE p256dh = :p256dh, auth = :auth');
    $stmt->execute([
        ':endpoint' => $endpoint,
        ':p256dh' => $p256dh,
        ':auth' => $auth,
    ]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('push_subscribe.php DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to save push subscription.']);
}
exit;
