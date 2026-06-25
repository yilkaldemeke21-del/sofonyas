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

$courseId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM courses WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $courseId]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: courses.php');
    exit;
}

$lessonStmt = $pdo->prepare('SELECT * FROM course_lessons WHERE course_id = :course_id ORDER BY sort_order ASC, id ASC');
$lessonStmt->execute([':course_id' => $courseId]);
$lessons = $lessonStmt->fetchAll();

$noteStmt = $pdo->prepare('SELECT * FROM admin_notes ORDER BY created_at DESC LIMIT 6');
$noteStmt->execute();
$notes = $noteStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['course_name'] ?? 'Course', ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="sofonyas (1).css">
</head>
<body>
    <nav>
        <ul>
            <li><a href="student_dashboard.php">Dashboard</a></li>
            <li><a href="courses.php">Courses</a></li>
        </ul>
    </nav>

    <section class="card" style="margin-top:24px;">
        <h2><?php echo htmlspecialchars($course['course_name'] ?? 'Course', ENT_QUOTES, 'UTF-8'); ?></h2>
        <?php if ($message !== ''): ?>
            <p style="color: <?php echo $messageType === 'success' ? '#166534' : ($messageType === 'info' ? '#1d4ed8' : '#b91c1c'); ?>; font-weight:700;">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </p>
        <?php endif; ?>
        <p><?php echo nl2br(htmlspecialchars($course['description'] ?? $course['short_description'] ?? '', ENT_QUOTES, 'UTF-8')); ?></p>
        <p><strong>Instructor:</strong> <?php echo htmlspecialchars($course['instructor'] ?? 'Staff', ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>Price:</strong> <?php echo number_format((float)($course['price'] ?? 0), 2); ?></p>
        <form method="post" style="display:inline-block; margin:8px 0 12px;">
            <input type="hidden" name="enroll_course_id" value="<?php echo (int)$courseId; ?>">
            <button class="button" type="submit">Enroll Now</button>
        </form>
        <?php if (!empty($course['pdf_file'])): ?>
            <p><a class="button secondary" href="<?php echo htmlspecialchars($course['pdf_file'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Open PDF</a></p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h3>ቱቶሪያል</h3>
        <?php if (!empty($course['tutorial_topic']) || !empty($course['tutorial_text']) || !empty($course['tutorial_video'])): ?>
            <p><strong><?php echo htmlspecialchars($course['tutorial_topic'] ?? 'Tutorial', ENT_QUOTES, 'UTF-8'); ?></strong></p>
            <div><?php echo nl2br(htmlspecialchars($course['tutorial_text'] ?? '', ENT_QUOTES, 'UTF-8')); ?></div>
            <?php if (!empty($course['tutorial_video'])): ?>
                <p><a class="button" href="<?php echo htmlspecialchars($course['tutorial_video'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">ቪዲዮዉን ይክፈቱ</a></p>
            <?php endif; ?>
        <?php else: ?>
            <p>ይህ ቱቶሪያል አሁን የተጨመረ ነው.</p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h3>የጥናት ጽሑፎች</h3>
        <?php if (empty($notes)): ?>
            <p>ምንም የጥናት ጽሑፎች የሉም </p>
        <?php else: ?>
            <ul>
                <?php foreach ($notes as $note): ?>
                    <li style="margin-bottom:10px;">
                        <strong><?php echo htmlspecialchars($note['title'] ?? 'Note', ENT_QUOTES, 'UTF-8'); ?></strong>
                        <div><?php echo nl2br(htmlspecialchars($note['description'] ?? '', ENT_QUOTES, 'UTF-8')); ?></div>
                        <?php if (!empty($note['file_path'])): ?>
                            <div><a href="<?php echo htmlspecialchars($note['file_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Open File</a></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card">
        <h3>Lessons</h3>
        <?php if (empty($lessons)): ?>
            <p>No lessons are available yet for this course.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($lessons as $lesson): ?>
                    <li style="margin-bottom:10px;">
                        <strong><?php echo htmlspecialchars($lesson['title'] ?? 'Lesson', ENT_QUOTES, 'UTF-8'); ?></strong>
                        <div><a href="lesson.php?course_id=<?php echo (int)$courseId; ?>&lesson_id=<?php echo (int)$lesson['id']; ?>">Open Lesson</a></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</body>
</html>
