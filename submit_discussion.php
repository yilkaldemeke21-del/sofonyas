<?php
session_start();
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: discussion_forum.php');
    exit;
}

$formType = $_POST['form_type'] ?? 'post';

if ($formType === 'reply') {
    $postId = (int)($_POST['post_id'] ?? 0);
    $replyMessage = trim($_POST['reply_message'] ?? '');

    if ($postId <= 0 || $replyMessage === '') {
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
        $pdo->exec('CREATE TABLE IF NOT EXISTS discussion_replies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            author_name VARCHAR(255) NOT NULL,
            author_role VARCHAR(50) NOT NULL DEFAULT "Guest",
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES discussion_posts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $stmt = $pdo->prepare('INSERT INTO discussion_replies (post_id, author_name, author_role, message, created_at) VALUES (:post_id, :author_name, :author_role, :message, NOW())');
        $stmt->execute([
            ':post_id' => $postId,
            ':author_name' => $authorName,
            ':author_role' => $authorRole,
            ':message' => $replyMessage,
        ]);

        header('Location: discussion_forum.php?status=reply_success');
        exit;
    } catch (PDOException $e) {
        error_log('Discussion reply save failed: ' . $e->getMessage());
        header('Location: discussion_forum.php?status=error');
        exit;
    }
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

    $pdo->exec('CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        is_read TINYINT(1) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $pdo->exec('CREATE TABLE IF NOT EXISTS email_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        recipient_email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $stmt = $pdo->prepare('INSERT INTO discussion_posts (topic, message, author_name, author_role, created_at) VALUES (:topic, :message, :author_name, :author_role, NOW())');
    $stmt->execute([
        ':topic' => $topic,
        ':message' => $message,
        ':author_name' => $authorName,
        ':author_role' => $authorRole,
    ]);

    if (isset($_SESSION['student_id'])) {
        $studentNotice = 'እርስዎ የጻፉት ውይይት ተመዝግቧል። አስተዳዳሪዎች መልስ ይሰጣሉ።';
        $stmt = $pdo->prepare('INSERT INTO notifications (student_id, message, created_at) VALUES (:student_id, :message, NOW())');
        $stmt->execute([':student_id' => $_SESSION['student_id'], ':message' => $studentNotice]);
    }

    $adminStmt = $pdo->query('SELECT id, email FROM admin_users');
    foreach ($adminStmt->fetchAll() as $admin) {
        $adminMsg = 'አዲስ የውይይት ልጥፍ ተሰብስቧል። ርዕስ: ' . $topic . ' በ ' . $authorName . ' አማካኝነት';
        $stmt = $pdo->prepare('INSERT INTO email_notifications (admin_id, recipient_email, subject, message, sent_at) VALUES (:admin_id, :recipient_email, :subject, :message, NOW())');
        $stmt->execute([
            ':admin_id' => (int)$admin['id'],
            ':recipient_email' => $admin['email'],
            ':subject' => 'የውይይት ፎርም አዲስ ልጥፍ',
            ':message' => $adminMsg,
        ]);
    }

    header('Location: discussion_forum.php?status=success');
    exit;
} catch (PDOException $e) {
    error_log('Discussion post save failed: ' . $e->getMessage());
    header('Location: discussion_forum.php?status=error');
    exit;
}
