<?php
session_start();
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: discussion_forum.php');
    exit;
}

$topic = trim($_POST['topic'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($topic === '' || $message === '') {
    header('Location: discussion_forum.php?status=error');
    exit;
}

$authorName = 'Guest';
$authorRole = 'Guest';

if (isset($_SESSION['admin_id'])) {
    $authorName = $_SESSION['admin_username'] ?? 'Admin';
    $authorRole = 'Admin';
} elseif (isset($_SESSION['student_id'])) {
    $authorName = $_SESSION['student_name'] ?? 'Student';
    $authorRole = 'Student';
}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS discussion_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        topic VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        author_name VARCHAR(255) NOT NULL,
        author_role VARCHAR(50) NOT NULL DEFAULT "Guest",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $stmt = $pdo->prepare('INSERT INTO discussion_posts (topic, message, author_name, author_role, created_at) VALUES (:topic, :message, :author_name, :author_role, NOW())');
    $stmt->execute([
        ':topic' => $topic,
        ':message' => $message,
        ':author_name' => $authorName,
        ':author_role' => $authorRole,
    ]);

    header('Location: discussion_forum.php?status=success');
    exit;
} catch (PDOException $e) {
    error_log('Discussion post save failed: ' . $e->getMessage());
    header('Location: discussion_forum.php?status=error');
    exit;
}
