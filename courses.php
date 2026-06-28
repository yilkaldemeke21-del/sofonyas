<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit;
}

function ensureRegistrationTable(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS registrations (
        id VARCHAR(50) NOT NULL PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        student_id VARCHAR(100) NOT NULL,
        course VARCHAR(255) NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        payment_status VARCHAR(30) NOT NULL DEFAULT "unpaid",
        created_at DATETIME NOT NULL,
        paid_at DATETIME DEFAULT NULL,
        INDEX idx_reg_student (student_id),
        INDEX idx_reg_course (course)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $existingColumns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM registrations') as $column) {
        $existingColumns[$column['Field']] = true;
    }

    $columnMigrations = [
        'name' => 'ALTER TABLE registrations ADD COLUMN name VARCHAR(255) NOT NULL DEFAULT ""',
        'email' => 'ALTER TABLE registrations ADD COLUMN email VARCHAR(255) NOT NULL DEFAULT ""',
        'created_at' => 'ALTER TABLE registrations ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'paid_at' => 'ALTER TABLE registrations ADD COLUMN paid_at DATETIME DEFAULT NULL',
    ];

    foreach ($columnMigrations as $columnName => $sql) {
        if (!isset($existingColumns[$columnName])) {
            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                error_log('Registration schema migration warning for ' . $columnName . ': ' . $e->getMessage());
            }
        }
    }
}

ensureRegistrationTable($pdo);

$message = '';
$messageType = '';

$studentStmt = $pdo->prepare('SELECT name, email FROM students WHERE student_id = :student_id LIMIT 1');
$studentStmt->execute([':student_id' => $_SESSION['student_id']]);
$studentRecord = $studentStmt->fetch();
$studentName = trim((string)($studentRecord['name'] ?? $_SESSION['student_name'] ?? 'Student'));
$studentEmail = trim((string)($studentRecord['email'] ?? $_SESSION['student_email'] ?? ''));
$studentName = $studentName === '' ? 'Student' : $studentName;
$studentEmail = $studentEmail === '' ? 'student@example.com' : $studentEmail;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['enroll_course_id'])) {
    $courseId = (int)$_POST['enroll_course_id'];
    $stmt = $pdo->prepare('SELECT * FROM courses WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $courseId]);
    $course = $stmt->fetch();

    if ($course) {
        $checkStmt = $pdo->prepare('SELECT id FROM registrations WHERE student_id = :student_id AND course = :course LIMIT 1');
        $checkStmt->execute([':student_id' => $_SESSION['student_id'], ':course' => $course['course_name']]);
        if ($checkStmt->fetch()) {
            $message = 'You are already enrolled in this course.';
            $messageType = 'info';
        } else {
            $recordId = uniqid('reg_', true);
            $insertStmt = $pdo->prepare('INSERT INTO registrations (id, name, email, student_id, course, amount, payment_status, created_at) VALUES (:id, :name, :email, :student_id, :course, :amount, :payment_status, :created_at)');
            $insertStmt->execute([
                ':id' => $recordId,
                ':name' => $studentName,
                ':email' => $studentEmail,
                ':student_id' => $_SESSION['student_id'],
                ':course' => $course['course_name'],
                ':amount' => $course['price'] ?? 0,
                ':payment_status' => 'unpaid',
                ':created_at' => date('Y-m-d H:i:s'),
            ]);
            $message = 'Enrollment request submitted successfully.';
            $messageType = 'success';
        }
    }
}

$stmt = $pdo->query('SELECT * FROM courses ORDER BY created_at DESC');
$courses = $stmt->fetchAll();
$noteStmt = $pdo->query('SELECT COUNT(*) AS total FROM admin_notes');
$noteCount = (int)($noteStmt->fetch()['total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Courses</title>
    <link rel="stylesheet" href="sofonyas (1).css">
</head>
<body>
    <nav>
        <ul>
            <li><a href="student_dashboard.php">ዳሽቦርድ</a></li>
            <li><a href="courses.php" class="active">ኮርሶች</a></li>
        </ul>
    </nav>

    <section class="card" style="margin-top:24px;">
        <h2>Available Courses</h2>
        <p>ሌሰኖችን ተመልከት, ቱቶሪያሎችን ተመልከት,እንዲሁም ከፈለጉ ኮርሶችን ወደ ቀጣዩ ያሽከርክሩ</p>
        <?php if ($message !== ''): ?>
            <p style="color: <?php echo $messageType === 'success' ? '#166534' : ($messageType === 'info' ? '#1d4ed8' : '#b91c1c'); ?>; font-weight:700;">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </p>
        <?php endif; ?>
        <?php if (empty($courses)): ?>
            <p>ምንም ኮርስ የለም.</p>
        <?php else: ?>
            <div class="feature-grid">
                <?php foreach ($courses as $course): ?>
                    <?php $hasTutorial = !empty($course['tutorial_topic']) || !empty($course['tutorial_text']) || !empty($course['tutorial_video']); ?>
                    <div class="card" style="margin:0;">
                        <h3><?php echo htmlspecialchars($course['course_name'] ?? 'Untitled Course', ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars($course['short_description'] ?? $course['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Instructor:</strong> <?php echo htmlspecialchars($course['instructor'] ?? 'Staff', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Price:</strong> <?php echo number_format((float)($course['price'] ?? 0), 2); ?></p>
                        <p><strong>Notes:</strong> <?php echo $noteCount > 0 ? 'Available' : 'Coming soon'; ?></p>
                        <p><strong>Tutorial:</strong> <?php echo $hasTutorial ? 'Available' : 'Coming soon'; ?></p>
                        <form method="post" style="display:inline-block; margin-top:8px;">
                            <input type="hidden" name="enroll_course_id" value="<?php echo (int)($course['id'] ?? 0); ?>">
                            <button class="button" type="submit">Enroll</button>
                        </form>
                        <a class="button secondary" href="course_details.php?id=<?php echo (int)($course['id'] ?? 0); ?>">View Details</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</body>
</html>
