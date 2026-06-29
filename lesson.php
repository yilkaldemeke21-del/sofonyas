<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit;
}

$courseId = (int)($_GET['course_id'] ?? 0);
$lessonId = (int)($_GET['lesson_id'] ?? 0);

$courseStmt = $pdo->prepare('SELECT * FROM courses WHERE id = :id LIMIT 1');
$courseStmt->execute([':id' => $courseId]);
$course = $courseStmt->fetch();

$lessonStmt = $pdo->prepare('SELECT * FROM course_lessons WHERE id = :id AND course_id = :course_id LIMIT 1');
$lessonStmt->execute([':id' => $lessonId, ':course_id' => $courseId]);
$lesson = $lessonStmt->fetch();

if (!$course) {
    header('Location: courses.php');
    exit;
}

if (!$lesson) {
    $fallbackStmt = $pdo->prepare('SELECT * FROM course_lessons WHERE course_id = :course_id ORDER BY sort_order ASC, id ASC LIMIT 1');
    $fallbackStmt->execute([':course_id' => $courseId]);
    $lesson = $fallbackStmt->fetch();
}

$lessonTitle = $lesson['title'] ?? 'Lesson';
$lessonContent = renderRichText($lesson['content'] ?? $course['tutorial_text'] ?? $course['description'] ?? 'This lesson content will be added soon.');

$noteStmt = $pdo->prepare('SELECT * FROM admin_notes ORDER BY created_at DESC LIMIT 5');
$noteStmt->execute();
$notes = $noteStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lessonTitle, ENT_QUOTES, 'UTF-8'); ?></title>
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
        <h2><?php echo htmlspecialchars($lessonTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
        <p><strong>Course:</strong> <?php echo htmlspecialchars($course['course_name'] ?? 'Course', ENT_QUOTES, 'UTF-8'); ?></p>
        <div><?php echo $lessonContent; ?></div>
        <?php if (!empty($course['tutorial_video'])): ?>
            <p><a class="button" href="<?php echo htmlspecialchars($course['tutorial_video'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Watch Video</a></p>
        <?php endif; ?>
        <p><a href="course_details.php?id=<?php echo (int)$courseId; ?>">Back to Course</a></p>
    </section>

    <section class="card">
        <h3>Related Study Notes</h3>
        <?php if (empty($notes)): ?>
            <p>No study notes are available yet.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($notes as $note): ?>
                    <li style="margin-bottom:8px;">
                        <strong><?php echo htmlspecialchars($note['title'] ?? 'Note', ENT_QUOTES, 'UTF-8'); ?></strong>
                        <?php if (!empty($note['file_path'])): ?>
                            <div><a href="<?php echo htmlspecialchars($note['file_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Open File</a></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</body>
</html>
