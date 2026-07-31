<?php
function loadEnvFileFromDirectory(string $startDir): void
{
    $dirs = [];
    $currentDir = realpath($startDir);

    while ($currentDir !== false && $currentDir !== dirname($currentDir)) {
        $dirs[] = $currentDir;
        $currentDir = dirname($currentDir);
    }

    if (!empty($dirs)) {
        $dirs[] = dirname($dirs[0]);
    }

    $dirs = array_values(array_unique($dirs));

    foreach ($dirs as $dir) {
        $envPath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($envPath)) {
            continue;
        }

        $envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($envLines === false) {
            continue;
        }

        foreach ($envLines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '#') === 0) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if ($key === '') {
                continue;
            }

            $existingValue = getenv($key);
            if ($existingValue === false || $existingValue === '') {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }

        break;
    }
}

loadEnvFileFromDirectory(__DIR__);

$host = getenv('DB_HOST');
$db = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

$hostHeader = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
$hostHeader = is_string($hostHeader) ? preg_replace('/:.*$/', '', trim($hostHeader)) : '';
$useHostedDefaults = is_string($hostHeader) && preg_match('/(infinityfreeapp\.com|epizy\.com)$/i', $hostHeader) === 1;
$isLocalRequest = $hostHeader === '' || in_array(strtolower($hostHeader), ['localhost', '127.0.0.1', '::1'], true);

if ($isLocalRequest) {
    $host = '127.0.0.1';
    $db = 'sofonyas_db';
    $user = 'root';
    $pass = '';
} elseif (($host === false || trim((string)$host) === '' || strtolower(trim((string)$host)) === 'localhost' || strtolower(trim((string)$host)) === '127.0.0.1') && $useHostedDefaults) {
    $host = 'sql311.infinityfree.com';
    $db = $db ?: 'if0_42532820_sofonyas_db';
    $user = $user ?: 'if0_42532820';
    $pass = $pass ?: 'sofonyas2127';
} elseif ($host === false || trim((string)$host) === '' || strtolower(trim((string)$host)) === 'localhost') {
    $host = '127.0.0.1';
    $db = $db ?: 'sofonyas_db';
    $user = $user ?: 'root';
    $pass = $pass ?: '';
}

$port = getenv('DB_PORT') ?: '3306';
$charset = 'utf8mb4';
$socket = getenv('DB_SOCKET') ?: '';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_TIMEOUT => 2,
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
];

$pdo = null;
$lastError = null;

$hosts = array_values(array_unique(array_filter([
    $host,
    '127.0.0.1',
    '::1',
    'localhost',
], static function ($value): bool {
    return $value !== null && $value !== '';
})));

$socketUsed = false;
if ($socket !== '' && (is_file($socket) || strpos($socket, 'mysql.sock') !== false)) {
    try {
        $pdo = new PDO("mysql:unix_socket=$socket;dbname=$db;charset=$charset", $user, $pass, $options);
        $socketUsed = true;
    } catch (PDOException $e) {
        $lastError = $e;
        error_log('DB connection attempt failed over socket ' . $socket . ': ' . $e->getMessage());
    }
}

if (!$pdo) {
    $databaseCandidates = array_values(array_unique(array_filter([
        $db,
        'if0_42532820_sofonyas_db',
        'sofonyas_db',
    ], static function ($value): bool {
        return $value !== null && $value !== '';
    })));

    $userCandidates = array_values(array_unique(array_filter([
        $user,
        'if0_42532820',
        'root',
    ], static function ($value): bool {
        return $value !== null && $value !== '';
    })));

    foreach ($hosts as $candidateHost) {
        foreach ($databaseCandidates as $candidateDb) {
            foreach ($userCandidates as $candidateUser) {
                $dsn = "mysql:host=$candidateHost;port=$port;dbname=$candidateDb;charset=$charset";

                try {
                    $pdo = new PDO($dsn, $candidateUser, $pass, $options);
                    $db = $candidateDb;
                    $user = $candidateUser;
                    break 3;
                } catch (PDOException $e) {
                    $lastError = $e;
                    error_log('DB connection attempt failed for host ' . $candidateHost . ' / db ' . $candidateDb . ' / user ' . $candidateUser . ': ' . $e->getMessage());
                }
            }
        }
    }
}

$dbConnectionError = '';

if (!$pdo) {
    $dbConnectionError = 'DB connection failed. Please start the MySQL service on port 3306 and confirm the database credentials.';
    if ($lastError instanceof PDOException) {
        $dbConnectionError .= ' Details: ' . $lastError->getMessage();
    }
    error_log($dbConnectionError);
}

if (!defined('DB_CONNECTION_ERROR')) {
    define('DB_CONNECTION_ERROR', $dbConnectionError);
}

function ensureDemoStudentAccount(PDO $pdo): void
{
    $email = 'student@example.com';
    $studentId = 'TEST-001';
    $password = 'student123';
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('SELECT id, password_hash FROM students WHERE email = :email OR student_id = :student_id LIMIT 1');
    $stmt->execute([':email' => $email, ':student_id' => $studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        $insertStmt = $pdo->prepare('INSERT INTO students (name, email, student_id, password_hash, role, created_at) VALUES (:name, :email, :student_id, :password_hash, :role, NOW())');
        $insertStmt->execute([
            ':name' => 'Test Student',
            ':email' => $email,
            ':student_id' => $studentId,
            ':password_hash' => $passwordHash,
            ':role' => 'Student',
        ]);
        return;
    }

    if (empty($student['password_hash'])) {
        $updateStmt = $pdo->prepare('UPDATE students SET password_hash = :password_hash WHERE id = :id');
        $updateStmt->execute([
            ':password_hash' => $passwordHash,
            ':id' => $student['id'],
        ]);
    }
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

if ($pdo instanceof PDO) {
    try {
        ensureDemoStudentAccount($pdo);
        ensureCourseColumns($pdo);
        ensureUtf8mb4CourseSchema($pdo);
        ensureUtf8mb4TextColumns($pdo, 'courses', ['description', 'short_description', 'tutorial_text', 'assignment', 'quiz', 'certificate_requirements']);
        ensureBlogPoetrySchema($pdo);
    } catch (Throwable $e) {
        error_log('Course schema validation failed: ' . $e->getMessage());
    }
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

if ($pdo instanceof PDO) {
    try {
        ensureUserRoleColumns($pdo);
    } catch (Throwable $e) {
        error_log('User role schema validation failed: ' . $e->getMessage());
    }
}

function columnExists(PDO $pdo, string $tableName, string $columnName): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    $stmt->execute([':table' => $tableName, ':column' => $columnName]);
    return (int)$stmt->fetchColumn() > 0;
}

if (!function_exists('escape_pdf_text')) {
    function escape_pdf_text(string $value): string
    {
        $text = preg_replace('/[^\x20-\x7E\r\n]/u', '', (string)$value);
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        return $text;
    }
}

if (!function_exists('build_simple_pdf_bytes')) {
    function build_simple_pdf_bytes(string $title, array $lines, string $subtitle = ''): string
    {
        $stream = "BT\n/F1 20 Tf\n50 740 Td\n(" . escape_pdf_text($title) . ") Tj\nET\n";

        if ($subtitle !== '') {
            $stream .= "BT\n/F1 12 Tf\n50 710 Td\n(" . escape_pdf_text($subtitle) . ") Tj\nET\n";
        }

        $y = $subtitle !== '' ? 688 : 710;
        foreach ($lines as $line) {
            $text = escape_pdf_text((string)$line);
            $stream .= "BT\n/F1 12 Tf\n50 {$y} Td\n(" . $text . ") Tj\nET\n";
            $y -= 18;
        }

        $objects = [];
        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
        $objects[] = "4 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream\nendobj\n";
        $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefStart = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefStart . "\n%%EOF\n";

        return $pdf;
    }
}

function ensureStudentLocationColumns(PDO $pdo): void
{
    $definitions = [
        'country' => 'ALTER TABLE students ADD COLUMN country VARCHAR(100) DEFAULT NULL',
        'city' => 'ALTER TABLE students ADD COLUMN city VARCHAR(100) DEFAULT NULL',
        'latitude' => 'ALTER TABLE students ADD COLUMN latitude DECIMAL(10,7) DEFAULT NULL',
        'longitude' => 'ALTER TABLE students ADD COLUMN longitude DECIMAL(10,7) DEFAULT NULL',
    ];

    foreach ($definitions as $columnName => $sql) {
        if (!columnExists($pdo, 'students', $columnName)) {
            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                error_log('Student location schema migration warning for students.' . $columnName . ': ' . $e->getMessage());
            }
        }
    }
}

function ensureBlogPoetrySchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS content_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL,
        type VARCHAR(20) NOT NULL DEFAULT 'blog',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_category (type, slug),
        INDEX idx_type (type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS content_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        author_type VARCHAR(20) NOT NULL DEFAULT 'admin',
        author_id INT DEFAULT NULL,
        author_name VARCHAR(150) NOT NULL,
        title VARCHAR(220) NOT NULL,
        slug VARCHAR(220) NOT NULL,
        excerpt TEXT DEFAULT NULL,
        body LONGTEXT DEFAULT NULL,
        post_type VARCHAR(20) NOT NULL DEFAULT 'blog',
        category_id INT DEFAULT NULL,
        category_name VARCHAR(100) DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        is_featured TINYINT(1) NOT NULL DEFAULT 0,
        view_count INT NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_type_status (post_type, status, created_at),
        INDEX idx_category (category_id),
        INDEX idx_author (author_type, author_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $defaultCategories = [
        ['name' => 'General', 'slug' => 'general', 'type' => 'blog'],
        ['name' => 'Faith', 'slug' => 'faith', 'type' => 'blog'],
        ['name' => 'Leadership', 'slug' => 'leadership', 'type' => 'blog'],
        ['name' => 'Devotional', 'slug' => 'devotional', 'type' => 'poetry'],
        ['name' => 'Inspirational', 'slug' => 'inspirational', 'type' => 'poetry'],
        ['name' => 'Prayer', 'slug' => 'prayer', 'type' => 'poetry'],
    ];

    foreach ($defaultCategories as $category) {
        try {
            $stmt = $pdo->prepare('INSERT IGNORE INTO content_categories (name, slug, type) VALUES (:name, :slug, :type)');
            $stmt->execute($category);
        } catch (PDOException $e) {
            error_log('Blog/poetry category seed warning: ' . $e->getMessage());
        }
    }
}

function getStudentRegistration(PDO $pdo, string $studentId, int $courseId)
{
    $stmt = $pdo->prepare(
        'SELECT r.* FROM registrations r WHERE r.student_id = :student_id AND (r.course_id = :course_id OR r.course = (SELECT course_name FROM courses WHERE id = :course_id_name)) LIMIT 1'
    );
    $stmt->execute([
        ':student_id' => $studentId,
        ':course_id' => $courseId,
        ':course_id_name' => $courseId,
    ]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function isStudentEnrolled(PDO $pdo, string $studentId, int $courseId): bool
{
    return (bool)getStudentRegistration($pdo, $studentId, $courseId);
}

function getCourseLessonProgress(PDO $pdo, string $studentId, int $courseId): array
{
    $stmt = $pdo->prepare('SELECT COUNT(*) AS completed_lessons FROM lesson_progress WHERE student_id = :student_id AND course_id = :course_id');
    $stmt->execute([':student_id' => $studentId, ':course_id' => $courseId]);
    $completed = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) AS total_lessons FROM course_lessons WHERE course_id = :course_id');
    $stmt->execute([':course_id' => $courseId]);
    $total = (int)$stmt->fetchColumn();

    return ['completed' => $completed, 'total' => $total];
}

function recordLessonProgress(PDO $pdo, string $studentId, int $courseId, int $lessonId): void
{
    if ($studentId === '' || $courseId <= 0 || $lessonId <= 0) {
        return;
    }

    try {
        $stmt = $pdo->prepare('INSERT IGNORE INTO lesson_progress (student_id, course_id, lesson_id, completed_at) VALUES (:student_id, :course_id, :lesson_id, NOW())');
        $stmt->execute([
            ':student_id' => $studentId,
            ':course_id' => $courseId,
            ':lesson_id' => $lessonId,
        ]);
    } catch (PDOException $e) {
        error_log('Lesson progress record failed: ' . $e->getMessage());
    }
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
        content TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_course_lessons_course (course_id),
        INDEX idx_course_lessons_module (module_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    if (!columnExists($pdo, 'course_lessons', 'content')) {
        $pdo->exec('ALTER TABLE course_lessons ADD COLUMN content TEXT DEFAULT NULL');
    }
}

function ensureRegistrationTable(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS registrations (
        id VARCHAR(50) NOT NULL PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        student_id VARCHAR(100) NOT NULL,
        course VARCHAR(255) NOT NULL,
        course_id INT DEFAULT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        payment_status VARCHAR(30) NOT NULL DEFAULT "unpaid",
        payment_method VARCHAR(60) DEFAULT NULL,
        created_at DATETIME NOT NULL,
        paid_at DATETIME DEFAULT NULL,
        INDEX idx_reg_student (student_id),
        INDEX idx_reg_course (course),
        INDEX idx_reg_course_id (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    if (!columnExists($pdo, 'registrations', 'course_id')) {
        $pdo->exec('ALTER TABLE registrations ADD COLUMN course_id INT DEFAULT NULL');
    }
    if (!columnExists($pdo, 'registrations', 'payment_method')) {
        $pdo->exec('ALTER TABLE registrations ADD COLUMN payment_method VARCHAR(60) DEFAULT NULL');
    }
}

function ensureLessonProgressTable(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS lesson_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        course_id INT NOT NULL,
        lesson_id INT NOT NULL,
        completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_lesson_progress (student_id, course_id, lesson_id),
        INDEX idx_lesson_progress_student (student_id),
        INDEX idx_lesson_progress_course (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

function ensureCourseQuizTables(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS quiz_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        quiz_name VARCHAR(255) NOT NULL,
        course_id INT DEFAULT NULL,
        score INT NOT NULL DEFAULT 0,
        total_questions INT NOT NULL DEFAULT 0,
        status VARCHAR(30) NOT NULL DEFAULT "pending",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_quiz_results_student (student_id),
        INDEX idx_quiz_results_course (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

function ensureAssignmentTables(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        course_id INT DEFAULT NULL,
        course_name VARCHAR(255) DEFAULT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        file_path VARCHAR(255) DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT "pending",
        due_date DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_assignments_student (student_id),
        INDEX idx_assignments_course (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

if (!function_exists('sofonyas_ensureExamSubmissionsTable')) {
    function sofnyas_ensureExamSubmissionsTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS exam_submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(100) NOT NULL,
            student_name VARCHAR(255) NOT NULL,
            exam_type VARCHAR(50) NOT NULL,
            access_code VARCHAR(50) DEFAULT NULL,
            score INT NOT NULL DEFAULT 0,
            total_questions INT NOT NULL DEFAULT 0,
            answers JSON DEFAULT NULL,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_exam_submissions_student (student_id),
            INDEX idx_exam_submissions_exam_type (exam_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
}

function ensureGamificationTables(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS student_gamification (
        student_id VARCHAR(100) NOT NULL PRIMARY KEY,
        points INT NOT NULL DEFAULT 0,
        level VARCHAR(50) NOT NULL DEFAULT "Novice",
        streak_days INT NOT NULL DEFAULT 0,
        last_activity DATETIME DEFAULT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_student_gamification_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $pdo->exec('CREATE TABLE IF NOT EXISTS student_badges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        badge_key VARCHAR(80) NOT NULL,
        badge_name VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        awarded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_student_badges (student_id, badge_key),
        INDEX idx_student_badges_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $pdo->exec('CREATE TABLE IF NOT EXISTS gamification_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        event_type VARCHAR(80) NOT NULL,
        points INT NOT NULL DEFAULT 0,
        detail VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_gamification_events_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

function ensureUtf8mb4CourseSchema(PDO $pdo): void
{
    try {
        $stmt = $pdo->prepare('SELECT TABLE_COLLATION FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
        $stmt->execute([':table' => 'courses']);
        $collation = $stmt->fetchColumn();
        if ($collation !== 'utf8mb4_unicode_ci') {
            $pdo->exec('ALTER TABLE courses CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        }
    } catch (PDOException $e) {
        error_log('Utf8mb4 course schema enforcement failed: ' . $e->getMessage());
    }
}

function ensureUtf8mb4TextColumns(PDO $pdo, string $tableName, array $columns): void
{
    foreach ($columns as $columnName) {
        try {
            $stmt = $pdo->prepare('SELECT CHARACTER_SET_NAME FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
            $stmt->execute([':table' => $tableName, ':column' => $columnName]);
            $charset = $stmt->fetchColumn();
            if ($charset !== 'utf8mb4') {
                $pdo->exec("ALTER TABLE $tableName MODIFY $columnName TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        } catch (PDOException $e) {
            error_log('Utf8mb4 column enforcement failed for ' . $tableName . '.' . $columnName . ': ' . $e->getMessage());
        }
    }
}

function ensureSiteChatTables(PDO $pdo): void
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

function ensurePushSubscriptionTable(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        endpoint TEXT NOT NULL,
        p256dh VARCHAR(255) DEFAULT NULL,
        auth VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_push_endpoint (endpoint(255))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

function ensureNewsletterSubscribersTable(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS newsletter_subscribers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) DEFAULT NULL,
        email VARCHAR(255) NOT NULL,
        source VARCHAR(100) NOT NULL DEFAULT "homepage",
        status VARCHAR(30) NOT NULL DEFAULT "active",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_newsletter_email (email),
        INDEX idx_newsletter_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

function ensureAiTables(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS student_ai_activity (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        action VARCHAR(100) NOT NULL,
        course_id INT DEFAULT NULL,
        detail TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student_ai_activity_student (student_id),
        INDEX idx_student_ai_activity_course (course_id),
        INDEX idx_student_ai_activity_action (action)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

function ensureGalleryTables(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS gallery_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) DEFAULT NULL,
        file_path VARCHAR(255) NOT NULL,
        uploaded_by VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_gallery_images_created_at (created_at)
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

    $pdo->exec('CREATE TABLE IF NOT EXISTS student_login_alerts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        event_type VARCHAR(50) NOT NULL,
        event_details TEXT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student_login_alerts_student (student_id),
        INDEX idx_student_login_alerts_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $pdo->exec('CREATE TABLE IF NOT EXISTS student_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        session_id VARCHAR(100) NOT NULL UNIQUE,
        device_label VARCHAR(255) DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_seen_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        ended_at DATETIME DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        INDEX idx_student_sessions_student (student_id),
        INDEX idx_student_sessions_active (is_active, last_seen_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

function getDeviceDisplayName(string $userAgent): string
{
    $agent = trim($userAgent);
    if ($agent === '') {
        return 'Unknown device';
    }

    $deviceType = 'Desktop';
    if (preg_match('/(android|iphone|ipad|mobile|tablet)/i', $agent)) {
        $deviceType = 'Mobile';
    } elseif (preg_match('/(macintosh|mac os|iphone|ipad)/i', $agent)) {
        $deviceType = 'Apple device';
    }

    $browser = 'Browser';
    if (preg_match('/Edg\//i', $agent)) {
        $browser = 'Edge';
    } elseif (preg_match('/Chrome\//i', $agent)) {
        $browser = 'Chrome';
    } elseif (preg_match('/Firefox\//i', $agent)) {
        $browser = 'Firefox';
    } elseif (preg_match('/Safari\//i', $agent)) {
        $browser = 'Safari';
    } elseif (preg_match('/OPR\//i', $agent)) {
        $browser = 'Opera';
    }

    return $browser . ' on ' . $deviceType;
}

function recordStudentSecurityEvent(PDO $pdo, string $studentId, string $eventType, string $eventDetails, ?string $ipAddress = null, ?string $userAgent = null): void
{
    $stmt = $pdo->prepare('INSERT INTO student_login_alerts (student_id, event_type, event_details, ip_address, user_agent) VALUES (:student_id, :event_type, :event_details, :ip_address, :user_agent)');
    $stmt->execute([
        ':student_id' => $studentId,
        ':event_type' => $eventType,
        ':event_details' => $eventDetails,
        ':ip_address' => $ipAddress,
        ':user_agent' => $userAgent,
    ]);
}

function upsertStudentSession(PDO $pdo, string $studentId, string $sessionId, ?string $ipAddress = null, ?string $userAgent = null): void
{
    $stmt = $pdo->prepare('INSERT INTO student_sessions (student_id, session_id, device_label, ip_address, user_agent, created_at, last_seen_at, is_active)
        VALUES (:student_id, :session_id, :device_label, :ip_address, :user_agent, NOW(), NOW(), 1)
        ON DUPLICATE KEY UPDATE device_label = VALUES(device_label), ip_address = VALUES(ip_address), user_agent = VALUES(user_agent), last_seen_at = NOW(), is_active = 1');
    $stmt->execute([
        ':student_id' => $studentId,
        ':session_id' => $sessionId,
        ':device_label' => getDeviceDisplayName((string)$userAgent),
        ':ip_address' => $ipAddress,
        ':user_agent' => $userAgent,
    ]);
}

function markStudentSessionInactive(PDO $pdo, string $sessionId): void
{
    $stmt = $pdo->prepare('UPDATE student_sessions SET is_active = 0, ended_at = NOW() WHERE session_id = :session_id');
    $stmt->execute([':session_id' => $sessionId]);
}

function invalidateOtherStudentSessions(PDO $pdo, string $studentId, string $currentSessionId): int
{
    $stmt = $pdo->prepare('UPDATE student_sessions SET is_active = 0, ended_at = NOW() WHERE student_id = :student_id AND session_id != :current_session_id AND is_active = 1');
    $stmt->execute([
        ':student_id' => $studentId,
        ':current_session_id' => $currentSessionId,
    ]);
    return $stmt->rowCount();
}

function getStudentSessions(PDO $pdo, string $studentId): array
{
    $stmt = $pdo->prepare('SELECT * FROM student_sessions WHERE student_id = :student_id ORDER BY last_seen_at DESC LIMIT 8');
    $stmt->execute([':student_id' => $studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ensureSiteSettingsTable(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS site_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT DEFAULT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

function getSiteSettings(?PDO $pdo): array
{
    if (!$pdo instanceof PDO) {
        return [];
    }

    $stmt = $pdo->query('SELECT setting_key, setting_value FROM site_settings');
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

function getSiteSetting(?PDO $pdo, string $key, string $default = ''): string
{
    $settings = getSiteSettings($pdo);
    return isset($settings[$key]) ? (string)$settings[$key] : $default;
}

function loginAttemptWindowCount(?PDO $pdo, string $key, int $windowSeconds = 900): int
{
    if (!$pdo instanceof PDO) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE key_identifier = :key_identifier AND created_at >= DATE_SUB(NOW(), INTERVAL :window SECOND)');
    $stmt->execute([
        ':key_identifier' => $key,
        ':window' => $windowSeconds,
    ]);
    return (int)$stmt->fetchColumn();
}

function recordLoginAttempt(?PDO $pdo, string $key): void
{
    if (!$pdo instanceof PDO) {
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO login_attempts (key_identifier, created_at) VALUES (:key_identifier, NOW())');
    $stmt->execute([':key_identifier' => $key]);
}

function clearLoginAttempts(?PDO $pdo, string $key): void
{
    if (!$pdo instanceof PDO) {
        return;
    }

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

function sendJsonPostRequest(string $url, array $data, int $timeoutSeconds = 10): array
{
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
    $result = [
        'success' => false,
        'status' => 0,
        'response' => null,
        'error' => '',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result['status'] = $statusCode;
        if ($responseBody === false) {
            $result['error'] = $curlError ?: 'Unknown cURL error';
            return $result;
        }

        $result['response'] = json_decode($responseBody, true);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => $timeoutSeconds,
            ],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            $result['error'] = 'file_get_contents failed';
            return $result;
        }

        $result['response'] = json_decode($responseBody, true);
    }

    if (is_array($result['response']) && isset($result['response']['success']) && $result['response']['success'] === true) {
        $result['success'] = true;
        return $result;
    }

    if (is_array($result['response']) && isset($result['response']['message'])) {
        $result['error'] = (string)$result['response']['message'];
    }

    return $result;
}

function sendGoogleSheetsExamSync(array $payload): array
{
    $webhook = getenv('GOOGLE_SHEETS_EXAM_WEBHOOK') ?: '';
    if ($webhook === '') {
        return ['success' => false, 'status' => 0, 'response' => null, 'error' => 'Google Sheets webhook not configured.'];
    }

    return sendJsonPostRequest($webhook, $payload, 10);
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

if ($pdo instanceof PDO) {
    try {
        ensureCourseStructureTables($pdo);
    } catch (Throwable $e) {
        error_log('Course structure schema validation failed: ' . $e->getMessage());
    }

    try {
        ensureRegistrationTable($pdo);
    } catch (Throwable $e) {
        error_log('Registration schema validation failed: ' . $e->getMessage());
    }

    try {
        ensureLessonProgressTable($pdo);
    } catch (Throwable $e) {
        error_log('Lesson progress schema validation failed: ' . $e->getMessage());
    }

    try {
        ensureCourseQuizTables($pdo);
    } catch (Throwable $e) {
        error_log('Course quiz schema validation failed: ' . $e->getMessage());
    }

    try {
        ensureAssignmentTables($pdo);
    } catch (Throwable $e) {
        error_log('Assignment schema validation failed: ' . $e->getMessage());
    }

    try {
        sofnyas_ensureExamSubmissionsTable($pdo);
    } catch (Throwable $e) {
        error_log('Exam submissions schema validation failed: ' . $e->getMessage());
    }

    try {
        ensureGamificationTables($pdo);
    } catch (Throwable $e) {
        error_log('Gamification schema validation failed: ' . $e->getMessage());
    }

    try {
        ensureSiteChatTables($pdo);
    } catch (Throwable $e) {
        error_log('Site chat schema validation failed: ' . $e->getMessage());
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
        ensurePushSubscriptionTable($pdo);
    } catch (Throwable $e) {
        error_log('Push subscription schema validation failed: ' . $e->getMessage());
    }

    try {
        ensureNewsletterSubscribersTable($pdo);
    } catch (Throwable $e) {
        error_log('Newsletter subscriber schema validation failed: ' . $e->getMessage());
    }

    try {
        ensureGalleryTables($pdo);
    } catch (Throwable $e) {
        error_log('Gallery schema validation failed: ' . $e->getMessage());
    }

    try {
        ensureSecurityTables($pdo);
    } catch (Throwable $e) {
        error_log('Security schema validation failed: ' . $e->getMessage());
    }

    try {
        ensureStudentLocationColumns($pdo);
    } catch (Throwable $e) {
        error_log('Student location schema validation failed: ' . $e->getMessage());
    }

    try {
        ensureSiteSettingsTable($pdo);
    } catch (Throwable $e) {
        error_log('Site settings schema validation failed: ' . $e->getMessage());
    }

    try {
        ensureAiTables($pdo);
    } catch (Throwable $e) {
        error_log('AI tables schema validation failed: ' . $e->getMessage());
    }

    try {
        ensureStudentEmailVerificationColumns($pdo);
    } catch (Throwable $e) {
        error_log('Email verification schema validation failed: ' . $e->getMessage());
    }

    try {
        ensureSiteChatTables($pdo);
    } catch (Throwable $e) {
        error_log('Site chat schema validation failed: ' . $e->getMessage());
    }
}

function cleanText($value): string {
    return trim((string)($value ?? ''));
}

function sanitizeRichText($value): string {
    $text = (string)($value ?? '');
    $text = str_replace(['&nbsp;', '&#160;', "\xC2\xA0"], ' ', $text);
    return trim($text);
}

function renderSafeCourseContent($value): string
{
    $html = sanitizeRichText($value);
    if ($html === '') {
        return '';
    }

    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
    $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html);
    $html = preg_replace('/on\w+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html);
    $html = preg_replace('/javascript:/i', '', $html);
    $html = preg_replace('/\b(vbscript|data):/i', '', $html);

    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $wrapped = '<div>' . $html . '</div>';
    $dom->loadHTML('<?xml version="1.0" encoding="UTF-8"?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $allowedTags = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'a', 'img', 'span', 'div', 'section', 'article', 'pre', 'code'];
    $allowedAttrs = ['href', 'src', 'alt', 'title', 'target', 'rel', 'class'];

    foreach (iterator_to_array($dom->getElementsByTagName('*')) as $node) {
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            continue;
        }

        $tag = strtolower($node->nodeName);
        if (!in_array($tag, $allowedTags, true)) {
            $parent = $node->parentNode;
            if ($parent instanceof DOMNode) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
            }
            continue;
        }

        foreach (iterator_to_array($node->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            if (!in_array($name, $allowedAttrs, true)) {
                $node->removeAttribute($attribute->name);
                continue;
            }

            if (in_array($name, ['href', 'src'], true)) {
                $value = trim($attribute->value);
                if ($value === '' || !preg_match('~^(https?:|mailto:|/|\\.)~i', $value)) {
                    $node->removeAttribute($attribute->name);
                    continue;
                }
            }

            if ($name === 'href') {
                $node->setAttribute('target', '_blank');
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    libxml_clear_errors();

    $wrapper = $dom->getElementsByTagName('div')->item(0);
    if ($wrapper instanceof DOMElement) {
        $content = $dom->saveHTML($wrapper);
        return trim((string)preg_replace('/^<div>|<\/div>$/i', '', $content));
    }

    return trim((string)$html);
}

function renderRichText($value): string {
    return (string)($value ?? '');
}

if (!function_exists('safe')) {
    function safe($value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Role helper utilities
 */
function getCurrentUserRole(?PDO $pdo = null): ?string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!empty($_SESSION['student_id'])) {
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare('SELECT role FROM students WHERE student_id = :student_id LIMIT 1');
                $stmt->execute([':student_id' => $_SESSION['student_id']]);
                $row = $stmt->fetch();
                if (!empty($row['role'])) {
                    $_SESSION['user_role'] = $row['role'];
                    return strtolower($row['role']);
                }
            } catch (Throwable $e) {
            }
        }

        $_SESSION['user_role'] = $_SESSION['user_role'] ?? 'Student';
        return 'student';
    }

    if (!empty($_SESSION['admin_id'])) {
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare('SELECT role FROM admin_users WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $_SESSION['admin_id']]);
                $row = $stmt->fetch();
                if (!empty($row['role'])) {
                    $_SESSION['user_role'] = $row['role'];
                    return strtolower($row['role']);
                }
            } catch (Throwable $e) {
            }
        }
        $_SESSION['user_role'] = $_SESSION['user_role'] ?? 'Admin';
        return 'admin';
    }

    if (!empty($_SESSION['user_role'])) {
        return strtolower((string)$_SESSION['user_role']);
    }

    return null;
}

function requireRole(array $allowedRoles, ?PDO $pdo = null)
{
    $allowed = array_map('strtolower', $allowedRoles);
    $role = getCurrentUserRole($pdo);

    if ($role === null) {
        // Not logged in - pick login based on requested roles
        if (in_array('admin', $allowed, true) || in_array('instructor', $allowed, true) || in_array('teacher', $allowed, true)) {
            header('Location: admin_login.php');
        } else {
            header('Location: student_login.php');
        }
        exit;
    }

    if (!in_array($role, $allowed, true)) {
        if (in_array('admin', $allowed, true) || in_array('instructor', $allowed, true) || in_array('teacher', $allowed, true)) {
            header('Location: admin_login.php');
            exit;
        }

        http_response_code(403);
        echo '<!doctype html><html><head><meta charset="utf-8"><title>403 Forbidden</title></head><body style="font-family:Arial,sans-serif;padding:24px;">';
        echo '<h1>403 Forbidden</h1>';
        echo '<p>Your account does not have permission to access this resource.</p>';
        echo '<p><a href="student_dashboard.php">Go back</a></p>';
        echo '</body></html>';
        exit;
    }
}

function isRole(string $role, ?PDO $pdo = null): bool
{
    $current = getCurrentUserRole($pdo);
    return $current !== null && strtolower($role) === strtolower($current);
}

function csrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    $token = (string)$_SESSION['csrf_token'];
    if (empty($_COOKIE['csrf_token']) || (string)$_COOKIE['csrf_token'] !== $token) {
        setcookie('csrf_token', $token, 0, '/', '', false, true);
    }

    return $token;
}

function validateCsrfToken(string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $expected = (string)($_SESSION['csrf_token'] ?? '');
    $cookieToken = (string)($_COOKIE['csrf_token'] ?? '');
    $provided = trim($token);

    if ($provided === '') {
        return false;
    }

    return hash_equals($expected, $provided) || hash_equals($cookieToken, $provided);
}

function getCurrentLanguage(string $fallback = 'am'): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $requestedLang = '';
    if (!empty($_GET['lang'])) {
        $requestedLang = trim((string)$_GET['lang']);
    } elseif (!empty($_POST['set_lang'])) {
        $requestedLang = trim((string)$_POST['set_lang']);
    } elseif (!empty($_POST['lang'])) {
        $requestedLang = trim((string)$_POST['lang']);
    }

    if ($requestedLang === 'en' || $requestedLang === 'am') {
        return setCurrentLanguage($requestedLang);
    }

    if (!empty($_SESSION['app_lang']) && in_array($_SESSION['app_lang'], ['am', 'en'], true)) {
        return $_SESSION['app_lang'];
    }

    if (!empty($_COOKIE['app_lang']) && in_array($_COOKIE['app_lang'], ['am', 'en'], true)) {
        return $_COOKIE['app_lang'];
    }

    return $fallback === 'en' ? 'en' : 'am';
}

function setCurrentLanguage(string $lang): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $lang = $lang === 'en' ? 'en' : 'am';
    $_SESSION['app_lang'] = $lang;

    if (!headers_sent()) {
        setcookie('app_lang', $lang, time() + 60 * 60 * 24 * 365, '/');
    }

    return $lang;
}

function translateText(string $am, string $en, ?string $lang = null): string
{
    $lang = $lang ?? getCurrentLanguage();
    return $lang === 'en' ? $en : $am;
}

function renderSeoMeta(array $options = []): string
{
    $title = $options['title'] ?? 'Sofoniyas Learning Platform';
    $description = $options['description'] ?? 'A professional learning platform for courses, exams, certificates, and community communication.';
    $canonical = $options['canonical'] ?? '';
    $lang = $options['lang'] ?? getCurrentLanguage();

    $meta = [];
    $meta[] = '<meta charset="UTF-8">';
    $meta[] = '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    $meta[] = '<meta name="description" content="' . safe($description) . '">';
    $meta[] = '<meta name="keywords" content="sofonyas, learning, courses, exams, certificates, amharic education">';
    $meta[] = '<meta property="og:title" content="' . safe($title) . '">';
    $meta[] = '<meta property="og:description" content="' . safe($description) . '">';
    $meta[] = '<meta property="og:type" content="website">';
    $meta[] = '<meta name="robots" content="index, follow">';
    $meta[] = '<meta http-equiv="Content-Language" content="' . safe($lang) . '">';

    if ($canonical !== '') {
        $meta[] = '<link rel="canonical" href="' . safe($canonical) . '">';
    }

    return implode("\n", $meta);
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
        return buildAppUrl($normalized);
    }

    return $path;
}

function buildAppUrl(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = '';

    if (!empty($_SERVER['SCRIPT_NAME'])) {
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($scriptDir !== '' && $scriptDir !== '.' && $scriptDir !== '/') {
            $basePath = $scriptDir;
        }
    }

    $relativePath = ltrim($path, '/');
    if ($relativePath === '') {
        return $scheme . '://' . $host . $basePath . '/';
    }

    return $scheme . '://' . $host . $basePath . '/' . $relativePath;
}
