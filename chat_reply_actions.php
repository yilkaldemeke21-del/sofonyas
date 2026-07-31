<?php
session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$action = trim((string)($_POST['action'] ?? ''));

if ($action === 'student_chat_feed') {
    if (!isset($_SESSION['student_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $studentId = trim((string)$_SESSION['student_id']);
    $studentName = '';
    try {
        $studentStmt = $pdo->prepare('SELECT name FROM students WHERE student_id = :student_id LIMIT 1');
        $studentStmt->execute([':student_id' => $studentId]);
        $studentRow = $studentStmt->fetch();
        if ($studentRow) {
            $studentName = trim((string)($studentRow['name'] ?? ''));
        }
    } catch (Throwable $e) {
        $studentName = '';
    }

    if ($studentName === '') {
        $studentName = 'Student';
    }

    try {
        $stmt = $pdo->prepare('SELECT id, sender_type, sender_name, message, reply_message, reply_updated_at, status, created_at FROM site_chat_messages WHERE sender_type = :sender_type AND sender_name = :sender_name AND (reply_deleted IS NULL OR reply_deleted = 0) ORDER BY created_at DESC LIMIT 8');
        $stmt->execute([
            ':sender_type' => 'student',
            ':sender_name' => $studentName,
        ]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'messages' => $messages]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$replyId = (int)($_POST['reply_id'] ?? 0);

if ($replyId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid reply']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, reply_message, reply_admin_id, reply_deleted, status FROM site_chat_messages WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $replyId]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Reply not found']);
        exit;
    }

    if ($action === 'edit_reply') {
        $replyMessage = trim((string)($_POST['reply_message'] ?? ''));
        if ($replyMessage === '') {
            echo json_encode(['success' => false, 'message' => 'Reply cannot be empty']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE site_chat_messages SET reply_message = :reply_message, reply_admin_id = :reply_admin_id, reply_updated_at = NOW(), reply_deleted = 0, status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            ':reply_message' => $replyMessage,
            ':reply_admin_id' => (int)$_SESSION['admin_id'],
            ':status' => 'replied',
            ':id' => $replyId,
        ]);

        echo json_encode(['success' => true, 'message' => 'Reply updated']);
        exit;
    }

    if ($action === 'delete_reply') {
        // Delete the deleted reply/message row entirely so students no longer see stale pending data
        $stmt = $pdo->prepare('DELETE FROM site_chat_messages WHERE id = :id');
        $stmt->execute([':id' => $replyId]);

        echo json_encode(['success' => true, 'message' => 'Reply deleted']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
