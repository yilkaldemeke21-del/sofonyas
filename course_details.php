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

$moduleStmt = $pdo->prepare('SELECT * FROM course_modules WHERE course_id = :course_id ORDER BY sort_order ASC, id ASC');
$moduleStmt->execute([':course_id' => $courseId]);
$courseModules = $moduleStmt->fetchAll();

$moduleLessonStmt = $pdo->prepare('SELECT * FROM course_lessons WHERE course_id = :course_id ORDER BY COALESCE(module_id, 999999) ASC, sort_order ASC, id ASC');
$moduleLessonStmt->execute([':course_id' => $courseId]);
$moduleLessons = $moduleLessonStmt->fetchAll();

$enrollmentCountStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM registrations WHERE course = :course_name');
$enrollmentCountStmt->execute([':course_name' => $course['course_name']]);
$enrollmentCount = (int)($enrollmentCountStmt->fetch()['total'] ?? 0);

$registrationCheckStmt = $pdo->prepare('SELECT id FROM registrations WHERE student_id = :student_id AND course = :course_name LIMIT 1');
$registrationCheckStmt->execute([':student_id' => $_SESSION['student_id'], ':course_name' => $course['course_name']]);
$isEnrolled = (bool)$registrationCheckStmt->fetch();

$progressPercent = 0;
$continueLessonId = null;
$completedLessons = 0;
$bookmarkedLessonIds = [];
if (!empty($lessons)) {
    $bookmarkCountStmt = $pdo->prepare('SELECT lesson_id FROM lesson_bookmarks WHERE student_id = :student_id AND course_id = :course_id');
    $bookmarkCountStmt->execute([':student_id' => $_SESSION['student_id'], ':course_id' => $courseId]);
    $bookmarkedLessonIds = $bookmarkCountStmt->fetchAll(PDO::FETCH_COLUMN);
    $completedLessons = count($bookmarkedLessonIds);

    if ($isEnrolled) {
        $progressPercent = min(100, (int)round(($completedLessons / max(1, count($lessons))) * 100));
        $lessonIndexMap = [];
        foreach ($lessons as $index => $lessonItem) {
            $lessonIndexMap[(int)$lessonItem['id']] = $index;
        }

        $maxCompletedIndex = -1;
        foreach ($bookmarkedLessonIds as $lessonId) {
            $lessonId = (int)$lessonId;
            if (isset($lessonIndexMap[$lessonId]) && $lessonIndexMap[$lessonId] > $maxCompletedIndex) {
                $maxCompletedIndex = $lessonIndexMap[$lessonId];
            }
        }

        if ($maxCompletedIndex >= 0) {
            $nextIndex = $maxCompletedIndex + 1;
            if (isset($lessons[$nextIndex])) {
                $continueLessonId = (int)$lessons[$nextIndex]['id'];
            } else {
                $continueLessonId = (int)$lessons[$maxCompletedIndex]['id'];
            }
        } else {
            $continueLessonId = (int)$lessons[0]['id'];
        }
    }
}

$ratingValue = 5.0;
$ratingStars = str_repeat('★', (int)floor($ratingValue)) . str_repeat('☆', 5 - (int)floor($ratingValue));

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
    <style>
        .course-hero { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 18px; align-items: center; }
        .course-badge-row { display:flex; flex-wrap:wrap; gap:8px; margin:10px 0; }
        .badge { display:inline-flex; align-items:center; gap:6px; padding:7px 10px; border-radius:999px; background:#eff6ff; color:#1d4ed8; font-weight:700; font-size:13px; }
        .stats-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:12px; margin: 14px 0 16px; }
        .stat-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:12px; }
        .stat-box strong { display:block; font-size:20px; color:#0f172a; }
        .stat-box span { color:#64748b; font-size:13px; }
        .progress-track { width:100%; height:10px; background:#e2e8f0; border-radius:999px; overflow:hidden; margin:8px 0 6px; }
        .progress-fill { height:100%; background:linear-gradient(90deg, #2563eb, #7c3aed); border-radius:999px; }
        .action-row { display:flex; flex-wrap:wrap; gap:10px; margin-top:12px; }
        .module-list { display:grid; gap:12px; }
        .module-card { border:1px solid #e2e8f0; border-radius:14px; padding:12px 14px; background:#fbfdff; }
        .module-card h4 { margin:0 0 8px; color:#1d4ed8; }
        .module-card ul { margin:0; padding-left:18px; }
        .module-card li { margin-bottom:6px; }
        @media (max-width: 800px) { .course-hero { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <nav>
        <ul>
            <li><a href="student_dashboard.php">Dashboard</a></li>
            <li><a href="courses.php">Courses</a></li>
        </ul>
    </nav>

    <section class="card" style="margin-top:24px;">
        <div class="course-hero">
            <div>
                <h2><?php echo htmlspecialchars($course['course_name'] ?? 'Course', ENT_QUOTES, 'UTF-8'); ?></h2>
                <?php if ($message !== ''): ?>
                    <p style="color: <?php echo $messageType === 'success' ? '#166534' : ($messageType === 'info' ? '#1d4ed8' : '#b91c1c'); ?>; font-weight:700;">
                        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                <?php endif; ?>
                <div class="course-badge-row">
                    <span class="badge">📚 <?php echo htmlspecialchars($course['category'] ?? 'General', ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="badge">👥 <?php echo (int)$enrollmentCount; ?> Enrolled</span>
                    <span class="badge">⭐ <?php echo htmlspecialchars($ratingStars, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <p><?php echo nl2br(htmlspecialchars($course['description'] ?? $course['short_description'] ?? '', ENT_QUOTES, 'UTF-8')); ?></p>
                <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-start; margin-bottom:10px;">
                    <?php if (!empty($course['instructor_image'])): ?>
                        <img src="<?php echo htmlspecialchars($course['instructor_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="Instructor image" style="width:72px; height:72px; object-fit:cover; border-radius:50%; border:2px solid #e2e8f0;">
                    <?php endif; ?>
                    <div>
                        <p><strong>Instructor:</strong> <?php echo htmlspecialchars($course['instructor'] ?? 'Staff', ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php if (!empty($course['instructor_bio'])): ?>
                            <p style="margin:6px 0 0;"><?php echo nl2br(htmlspecialchars($course['instructor_bio'], ENT_QUOTES, 'UTF-8')); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <p><strong>Price:</strong> <?php echo number_format((float)($course['price'] ?? 0), 2); ?></p>
                <div class="stats-grid">
                    <div class="stat-box">
                        <strong><?php echo (int)$progressPercent; ?>%</strong>
                        <span>Course Progress</span>
                        <div class="progress-track"><div class="progress-fill" style="width:<?php echo (int)$progressPercent; ?>%"></div></div>
                    </div>
                    <div class="stat-box">
                        <strong><?php echo (int)$enrollmentCount; ?></strong>
                        <span>Students Joined</span>
                    </div>
                    <div class="stat-box">
                        <strong><?php echo htmlspecialchars($course['category'] ?? 'General', ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>Category</span>
                    </div>
                    <div class="stat-box">
                        <strong><?php echo htmlspecialchars($ratingStars, ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>Rating</span>
                    </div>
                </div>
                <div class="action-row">
                    <form method="post" style="display:inline-block; margin:0;">
                        <input type="hidden" name="enroll_course_id" value="<?php echo (int)$courseId; ?>">
                        <button class="button" type="submit">Enroll Now</button>
                    </form>
                    <?php if (!empty($lessons) && $continueLessonId): ?>
                        <a class="button secondary" href="course_content.php?course_id=<?php echo (int)$courseId; ?>&lesson_id=<?php echo (int)$continueLessonId; ?>">Continue Course</a>
                    <?php endif; ?>
                    <?php if (!empty($course['pdf_file'])): ?>
                        <a class="button secondary" href="<?php echo htmlspecialchars($course['pdf_file'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Open PDF</a>
                    <?php endif; ?>
                </div>
                <?php if (!empty($course['thumbnail']) || !empty($course['tutorial_image']) || !empty($course['tutorial_audio']) || !empty($course['tutorial_video'])): ?>
                    <div class="course-media" style="margin-top:16px; display:grid; gap:12px;">
                        <?php if (!empty($course['thumbnail'])): ?>
                            <div><img src="<?php echo htmlspecialchars($course['thumbnail'], ENT_QUOTES, 'UTF-8'); ?>" alt="Course thumbnail" style="max-width:100%; border-radius:14px;"></div>
                        <?php endif; ?>
                        <?php if (!empty($course['tutorial_image'])): ?>
                            <div><img src="<?php echo htmlspecialchars($course['tutorial_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="Tutorial image" style="max-width:100%; border-radius:14px;"></div>
                        <?php endif; ?>
                        <?php if (!empty($course['tutorial_audio'])): ?>
                            <div>
                                <audio controls style="width:100%;">
                                    <source src="<?php echo htmlspecialchars($course['tutorial_audio'], ENT_QUOTES, 'UTF-8'); ?>">
                                </audio>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($course['tutorial_video'])): ?>
                            <div>
                                <?php if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)/i', $course['tutorial_video'])): ?>
                                    <iframe width="100%" height="220" src="<?php echo htmlspecialchars((strpos($course['tutorial_video'], 'youtu.be') !== false ? 'https://www.youtube.com/embed/' . preg_replace('/^.*(?:youtu\.be\/|v=)([^&\n?]+).*$/', '$1', $course['tutorial_video']) : 'https://www.youtube.com/embed/' . preg_replace('/^.*v=([^&\n?]+).*$/', '$1', $course['tutorial_video'])), ENT_QUOTES, 'UTF-8'); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                                <?php else: ?>
                                    <video controls style="width:100%; border-radius:14px;"><source src="<?php echo htmlspecialchars($course['tutorial_video'], ENT_QUOTES, 'UTF-8'); ?>"></video>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card" style="margin:0; background:#f8fafc;">
                <h3>Course Modules</h3>
                <?php $moduleGroups = []; foreach ($courseModules as $module) { $moduleGroups[$module['id']] = []; } foreach ($moduleLessons as $lesson) { if (!empty($lesson['module_id']) && isset($moduleGroups[$lesson['module_id']])) { $moduleGroups[$lesson['module_id']][] = $lesson; } } ?>
                <?php if (empty($courseModules) && empty($moduleLessons)): ?>
                    <p>No modules have been added yet for this course.</p>
                <?php else: ?>
                    <div class="module-list">
                        <?php foreach ($courseModules as $module): ?>
                            <div class="module-card">
                                <h4><?php echo htmlspecialchars($module['name'] ?? 'Module', ENT_QUOTES, 'UTF-8'); ?></h4>
                                <?php if (!empty($moduleGroups[$module['id']] ?? [])): ?>
                                    <ul>
                                        <?php foreach ($moduleGroups[$module['id']] as $lesson): ?>
                                            <li><a href="course_content.php?course_id=<?php echo (int)$courseId; ?>&lesson_id=<?php echo (int)$lesson['id']; ?>"><?php echo htmlspecialchars($lesson['title'] ?? 'Lesson', ENT_QUOTES, 'UTF-8'); ?></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p style="margin:0; color:#64748b;">No lessons in this module yet.</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php $orphanLessons = []; foreach ($moduleLessons as $lesson) { if (empty($lesson['module_id'])) { $orphanLessons[] = $lesson; } } if (!empty($orphanLessons)): ?>
                            <div class="module-card">
                                <h4>General Lessons</h4>
                                <ul>
                                    <?php foreach ($orphanLessons as $lesson): ?>
                                        <li><a href="course_content.php?course_id=<?php echo (int)$courseId; ?>&lesson_id=<?php echo (int)$lesson['id']; ?>"><?php echo htmlspecialchars($lesson['title'] ?? 'Lesson', ENT_QUOTES, 'UTF-8'); ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
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
                        <div><a href="course_content.php?course_id=<?php echo (int)$courseId; ?>&lesson_id=<?php echo (int)$lesson['id']; ?>">Open Lesson</a></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</body>
</html>
