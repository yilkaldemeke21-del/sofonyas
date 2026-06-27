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

$lessonListStmt = $pdo->prepare('SELECT cl.*, cm.name AS module_name FROM course_lessons cl LEFT JOIN course_modules cm ON cm.id = cl.module_id WHERE cl.course_id = :course_id ORDER BY COALESCE(cl.module_id, 999999) ASC, cl.sort_order ASC, cl.id ASC');
$lessonListStmt->execute([':course_id' => $courseId]);
$allLessons = $lessonListStmt->fetchAll();

if (!$lesson) {
    $lesson = $allLessons[0] ?? null;
}

if (!$lesson) {
    header('Location: course_details.php?id=' . $courseId);
    exit;
}

$currentLessonId = (int)$lesson['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: course_content.php?course_id=' . $courseId . '&lesson_id=' . $currentLessonId);
    exit;
}
$lessonIndex = null;
$prevLesson = null;
$nextLesson = null;
foreach ($allLessons as $index => $item) {
    if ((int)$item['id'] === $currentLessonId) {
        $lessonIndex = $index;
        if (isset($allLessons[$index - 1])) {
            $prevLesson = $allLessons[$index - 1];
        }
        if (isset($allLessons[$index + 1])) {
            $nextLesson = $allLessons[$index + 1];
        }
        break;
    }
}

$lessonTitle = $lesson['title'] ?? 'Lesson';
$lessonContent = renderRichText($lesson['content'] ?? $course['tutorial_text'] ?? $course['description'] ?? 'This lesson content will be added soon.');
$lessonModuleName = $lesson['module_name'] ?: 'General';
$lessonPosition = $lessonIndex !== null ? $lessonIndex + 1 : 0;
$totalLessons = count($allLessons);

$bookmarkMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lesson']) && isset($_SESSION['student_id'])) {
    $stmt = $pdo->prepare('SELECT id FROM lesson_bookmarks WHERE student_id = :student_id AND lesson_id = :lesson_id LIMIT 1');
    $stmt->execute([':student_id' => $_SESSION['student_id'], ':lesson_id' => $currentLessonId]);
    if ($stmt->fetch()) {
        $bookmarkMessage = 'This lesson is already saved to your dashboard.';
    } else {
        $saveStmt = $pdo->prepare('INSERT INTO lesson_bookmarks (student_id, lesson_id, course_id, lesson_title, course_name, instructor) VALUES (:student_id, :lesson_id, :course_id, :lesson_title, :course_name, :instructor)');
        $saveStmt->execute([
            ':student_id' => $_SESSION['student_id'],
            ':lesson_id' => $currentLessonId,
            ':course_id' => $courseId,
            ':lesson_title' => $lessonTitle,
            ':course_name' => $course['course_name'] ?? '',
            ':instructor' => $course['instructor'] ?? '',
        ]);
        $bookmarkMessage = 'Lesson saved. You can continue from your dashboard anytime.';
    }
}

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
        <p><strong>Module:</strong> <?php echo htmlspecialchars($lessonModuleName, ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>Lesson:</strong> <?php echo htmlspecialchars($lessonPosition . ' of ' . $totalLessons, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if ($bookmarkMessage !== ''): ?>
            <div style="margin:12px 0; padding:12px; background:#e0f2fe; color:#055160; border-radius:10px;">
                <?php echo htmlspecialchars($bookmarkMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        <div><?php echo $lessonContent; ?></div>
        <?php if (!empty($course['tutorial_video'])): ?>
            <p><a class="button" href="<?php echo htmlspecialchars($course['tutorial_video'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Watch Video</a></p>
        <?php endif; ?>
        <div style="display:flex; flex-wrap:wrap; gap:10px; margin:14px 0;">
            <form method="post" style="display:inline-block; margin:0;">
                <input type="hidden" name="save_lesson" value="1">
                <button class="button" type="submit">Save Lesson</button>
            </form>
            <?php if ($prevLesson): ?>
                <a class="button secondary" href="lesson.php?course_id=<?php echo (int)$courseId; ?>&lesson_id=<?php echo (int)$prevLesson['id']; ?>">Previous Lesson</a>
            <?php endif; ?>
            <?php if ($nextLesson): ?>
                <a class="button secondary" href="lesson.php?course_id=<?php echo (int)$courseId; ?>&lesson_id=<?php echo (int)$nextLesson['id']; ?>">Next Lesson</a>
            <?php endif; ?>
            <a href="course_details.php?id=<?php echo (int)$courseId; ?>" class="button secondary">Back to Course</a>
        </div>
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
