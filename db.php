<?php
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: 'sofonyas_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_TIMEOUT => 5,
];

$pdo = null;
$lastError = null;

$hosts = array_values(array_unique(array_filter([
    $host,
    '127.0.0.1',
    'localhost',
], static function ($value): bool {
    return $value !== null && $value !== '';
})));

foreach ($hosts as $candidateHost) {
    $dsn = "mysql:host=$candidateHost;port=$port;dbname=$db;charset=$charset";

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        break;
    } catch (PDOException $e) {
        $lastError = $e;
        error_log('DB connection attempt failed for host ' . $candidateHost . ': ' . $e->getMessage());
    }
}

if (!$pdo) {
    error_log('DB connection failed: ' . $lastError->getMessage());
    die('DB connection failed. Please start the MySQL service on port 3306 and confirm the database credentials. Details: ' . $lastError->getMessage());
}

if (!function_exists('ensureCourseColumns')) {
    function ensureCourseColumns(PDO $pdo): void
    {
        $existingColumns = [];
        $columnStmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses'");

        foreach ($columnStmt->fetchAll(PDO::FETCH_COLUMN) as $columnName) {
            $existingColumns[$columnName] = true;
        }

        $columnDefinitions = [
            'short_description' => 'ALTER TABLE courses ADD COLUMN short_description TEXT DEFAULT NULL',
            'category' => 'ALTER TABLE courses ADD COLUMN category VARCHAR(100) DEFAULT NULL',
            'level' => 'ALTER TABLE courses ADD COLUMN level VARCHAR(50) DEFAULT NULL',
            'thumbnail' => 'ALTER TABLE courses ADD COLUMN thumbnail VARCHAR(255) DEFAULT NULL',
            'tutorial_topic' => 'ALTER TABLE courses ADD COLUMN tutorial_topic VARCHAR(255) DEFAULT NULL',
            'tutorial_text' => 'ALTER TABLE courses ADD COLUMN tutorial_text TEXT DEFAULT NULL',
            'tutorial_image' => 'ALTER TABLE courses ADD COLUMN tutorial_image VARCHAR(255) DEFAULT NULL',
            'tutorial_audio' => 'ALTER TABLE courses ADD COLUMN tutorial_audio VARCHAR(255) DEFAULT NULL',
            'tutorial_video' => 'ALTER TABLE courses ADD COLUMN tutorial_video VARCHAR(255) DEFAULT NULL',
            'modules' => 'ALTER TABLE courses ADD COLUMN modules TEXT DEFAULT NULL',
            'quiz' => 'ALTER TABLE courses ADD COLUMN quiz TEXT DEFAULT NULL',
            'assignment' => 'ALTER TABLE courses ADD COLUMN assignment TEXT DEFAULT NULL',
            'certificate_requirements' => 'ALTER TABLE courses ADD COLUMN certificate_requirements TEXT DEFAULT NULL',
        ];

        foreach ($columnDefinitions as $columnName => $sql) {
            if (!isset($existingColumns[$columnName])) {
                try {
                    $pdo->exec($sql);
                } catch (PDOException $e) {
                    error_log('Course schema migration warning for ' . $columnName . ': ' . $e->getMessage());
                }
            }
        }
    }
}

try {
    ensureCourseColumns($pdo);
} catch (Throwable $e) {
    error_log('Course schema validation failed: ' . $e->getMessage());
}

function ensureUserRoleColumns(PDO $pdo): void
{
    $tables = [
        'admin_users' => [
            'role' => "ALTER TABLE admin_users ADD COLUMN role VARCHAR(30) NOT NULL DEFAULT 'Admin'"
        ],
        'students' => [
            'role' => "ALTER TABLE students ADD COLUMN role VARCHAR(30) NOT NULL DEFAULT 'Student'"
        ],
    ];

    foreach ($tables as $tableName => $columns) {
        $existingColumns = [];
        $columnStmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tableName'");

        foreach ($columnStmt->fetchAll(PDO::FETCH_COLUMN) as $columnName) {
            $existingColumns[$columnName] = true;
        }

        foreach ($columns as $columnName => $sql) {
            if (!isset($existingColumns[$columnName])) {
                try {
                    $pdo->exec($sql);
                } catch (PDOException $e) {
                    error_log('User role schema migration warning for ' . $tableName . '.' . $columnName . ': ' . $e->getMessage());
                }
            }
        }
    }

    $pdo->exec("UPDATE admin_users SET role = 'Admin' WHERE role IS NULL OR role = ''");
    $pdo->exec("UPDATE students SET role = 'Student' WHERE role IS NULL OR role = ''");
}

try {
    ensureUserRoleColumns($pdo);
} catch (Throwable $e) {
    error_log('User role schema validation failed: ' . $e->getMessage());
}

function ensureCourseStructureTables(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS course_modules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_course_modules_course (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $pdo->exec('CREATE TABLE IF NOT EXISTS course_lessons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        module_id INT DEFAULT NULL,
        title VARCHAR(255) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_course_lessons_course (course_id),
        INDEX idx_course_lessons_module (module_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

function ensureLessonBookmarkTables(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS lesson_bookmarks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        lesson_id INT NOT NULL,
        course_id INT NOT NULL,
        lesson_title VARCHAR(255) NOT NULL,
        course_name VARCHAR(255) NOT NULL,
        instructor VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_student_lesson (student_id, lesson_id),
        INDEX idx_lesson_bookmarks_student (student_id),
        INDEX idx_lesson_bookmarks_lesson (lesson_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

function ensureExamAccessTables(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS exam_access_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exam_type VARCHAR(50) NOT NULL,
        access_code VARCHAR(50) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_by VARCHAR(100) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_exam_access_code (exam_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $pdo->exec('CREATE TABLE IF NOT EXISTS student_exam_approvals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        exam_type VARCHAR(50) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT "pending",
        approved_by VARCHAR(100) DEFAULT NULL,
        approved_at DATETIME DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_student_exam_approval (student_id, exam_type),
        INDEX idx_student_exam_approval_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

function ensureNotificationSupportTables(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT "new",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_contact_messages_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $pdo->exec('CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_password_reset_token (token),
        INDEX idx_password_reset_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $pdo->exec('CREATE TABLE IF NOT EXISTS email_verification_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_email_verification_token (token),
        INDEX idx_email_verification_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

function ensureSecurityTables(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        key_identifier VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_login_attempts_key_time (key_identifier, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

function loginAttemptWindowCount(PDO $pdo, string $key, int $windowSeconds = 900): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE key_identifier = :key_identifier AND created_at >= DATE_SUB(NOW(), INTERVAL :window SECOND)');
    $stmt->execute([
        ':key_identifier' => $key,
        ':window' => $windowSeconds,
    ]);
    return (int)$stmt->fetchColumn();
}

function recordLoginAttempt(PDO $pdo, string $key): void
{
    $stmt = $pdo->prepare('INSERT INTO login_attempts (key_identifier, created_at) VALUES (:key_identifier, NOW())');
    $stmt->execute([':key_identifier' => $key]);
}

function clearLoginAttempts(PDO $pdo, string $key): void
{
    $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE key_identifier = :key_identifier');
    $stmt->execute([':key_identifier' => $key]);
}

function verifyCaptchaResponse(string $response, string $secret): bool
{
    if ($response === '' || $secret === '') {
        return false;
    }

    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $postData = http_build_query([
        'secret' => $secret,
        'response' => $response,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $postData,
            'timeout' => 8,
        ]
    ]);

    $result = @file_get_contents($url, false, $context);
    if ($result === false) {
        return false;
    }

    $payload = json_decode($result, true);
    return is_array($payload) && !empty($payload['success']);
}

function ensureStudentEmailVerificationColumns(PDO $pdo): void
{
    $tables = [
        'students' => [
            'email_verified' => "ALTER TABLE students ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0",
            'email_verification_token' => "ALTER TABLE students ADD COLUMN email_verification_token VARCHAR(255) DEFAULT NULL",
            'email_verified_at' => "ALTER TABLE students ADD COLUMN email_verified_at DATETIME DEFAULT NULL"
        ]
    ];

    foreach ($tables as $tableName => $columns) {
        $existingColumns = [];
        $columnStmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tableName'");
        foreach ($columnStmt->fetchAll(PDO::FETCH_COLUMN) as $columnName) {
            $existingColumns[$columnName] = true;
        }
        foreach ($columns as $columnName => $sql) {
            if (!isset($existingColumns[$columnName])) {
                try {
                    $pdo->exec($sql);
                } catch (PDOException $e) {
                    error_log('Email verification schema migration warning for ' . $tableName . '.' . $columnName . ': ' . $e->getMessage());
                }
            }
        }
    }
}

try {
    ensureCourseStructureTables($pdo);
} catch (Throwable $e) {
    error_log('Course structure schema validation failed: ' . $e->getMessage());
}

try {
    ensureLessonBookmarkTables($pdo);
} catch (Throwable $e) {
    error_log('Lesson bookmark schema validation failed: ' . $e->getMessage());
}

try {
    ensureExamAccessTables($pdo);
} catch (Throwable $e) {
    error_log('Exam access schema validation failed: ' . $e->getMessage());
}

try {
    ensureNotificationSupportTables($pdo);
} catch (Throwable $e) {
    error_log('Notification support schema validation failed: ' . $e->getMessage());
}

try {
    ensureSecurityTables($pdo);
} catch (Throwable $e) {
    error_log('Security schema validation failed: ' . $e->getMessage());
}

try {
    ensureStudentEmailVerificationColumns($pdo);
} catch (Throwable $e) {
    error_log('Email verification schema validation failed: ' . $e->getMessage());
}

function cleanText($value): string {
    return trim((string)($value ?? ''));
}

function sanitizeRichText($value): string {
    $html = (string)($value ?? '');
    $html = html_entity_decode($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><pre><code><a><img><table><thead><tbody><tr><th><td><span><div>'; 

    return strip_tags($html, $allowedTags);
}

function renderRichText($value): string {
    return sanitizeRichText($value);
}

function safe($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function csrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['csrf_token'];
}

function validateCsrfToken(string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function publicMediaUrl($value): string
{
    $path = trim((string)($value ?? ''));
    if ($path === '') {
        return '';
    }

    if (preg_match('~^(https?:)?//~i', $path) === 1) {
        return $path;
    }

    $normalized = str_replace('\\', '/', $path);
    if (preg_match('~^(uploads/|course_media/|uploads$|course_media$)~i', $normalized) === 1) {
        return '/' . ltrim($normalized, '/');
    }

    return $path;
}
