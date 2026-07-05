<?php
session_start();
require_once __DIR__ . '/db.php';

$studentId = $_SESSION['student_id'] ?? null;
$logoutAll = isset($_GET['action']) && $_GET['action'] === 'logout_all';
$sessionId = session_id();

if ($pdo instanceof PDO && $studentId !== null) {
    try {
        if ($logoutAll) {
            invalidateOtherStudentSessions($pdo, $studentId, $sessionId);
            markStudentSessionInactive($pdo, $sessionId);
            recordStudentSecurityEvent($pdo, $studentId, 'logout_all', 'Student logged out all devices.', $_SERVER['REMOTE_ADDR'] ?? 'Unknown', substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 500));
        } else {
            markStudentSessionInactive($pdo, $sessionId);
            recordStudentSecurityEvent($pdo, $studentId, 'logout', 'Student signed out from a device.', $_SERVER['REMOTE_ADDR'] ?? 'Unknown', substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 500));
        }
    } catch (Throwable $e) {
        error_log('Session logout update failed: ' . $e->getMessage());
    }
}

session_unset();
session_destroy();
header('Location: student_login.php');
exit;
