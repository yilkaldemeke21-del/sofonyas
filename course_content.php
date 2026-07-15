<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit;
}

$courseId = (int)($_GET['course_id'] ?? 0);
$lessonId = (int)($_GET['lesson_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM courses WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $courseId]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: courses.php');
    exit;
}

$isEnrolled = isStudentEnrolled($pdo, (string)$_SESSION['student_id'], $courseId);
if (!$isEnrolled) {
    header('Location: course_details.php?id=' . $courseId);
    exit;
}

$moduleStmt = $pdo->prepare('SELECT * FROM course_modules WHERE course_id = :course_id ORDER BY sort_order ASC, id ASC');
$moduleStmt->execute([':course_id' => $courseId]);
$modules = $moduleStmt->fetchAll();

$lessonStmt = $pdo->prepare('SELECT cl.*, cm.name AS module_name FROM course_lessons cl LEFT JOIN course_modules cm ON cm.id = cl.module_id WHERE cl.course_id = :course_id ORDER BY COALESCE(cl.module_id, 999999) ASC, cl.sort_order ASC, cl.id ASC');
$lessonStmt->execute([':course_id' => $courseId]);
$lessons = $lessonStmt->fetchAll();

$selectedLesson = null;
if ($lessonId > 0) {
    foreach ($lessons as $lesson) {
        if ((int)$lesson['id'] === $lessonId) {
            $selectedLesson = $lesson;
            break;
        }
    }
}

if (!$selectedLesson) {
    $selectedLesson = $lessons[0] ?? null;
}

if (!$selectedLesson) {
    header('Location: course_details.php?id=' . $courseId);
    exit;
}

$currentLessonId = (int)$selectedLesson['id'];
$lessonIndex = null;
$prevLesson = null;
$nextLesson = null;
foreach ($lessons as $index => $lesson) {
    if ((int)$lesson['id'] === $currentLessonId) {
        $lessonIndex = $index;
        if (isset($lessons[$index - 1])) {
            $prevLesson = $lessons[$index - 1];
        }
        if (isset($lessons[$index + 1])) {
            $nextLesson = $lessons[$index + 1];
        }
        break;
    }
}

$bookmarkMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lesson'])) {
    $bookmarkStmt = $pdo->prepare('SELECT id FROM lesson_bookmarks WHERE student_id = :student_id AND lesson_id = :lesson_id LIMIT 1');
    $bookmarkStmt->execute([':student_id' => $_SESSION['student_id'], ':lesson_id' => $currentLessonId]);
    if ($bookmarkStmt->fetch()) {
        $bookmarkMessage = 'This lesson is already saved to your dashboard.';
    } else {
        $saveStmt = $pdo->prepare('INSERT INTO lesson_bookmarks (student_id, lesson_id, course_id, lesson_title, course_name, instructor) VALUES (:student_id, :lesson_id, :course_id, :lesson_title, :course_name, :instructor)');
        $saveStmt->execute([
            ':student_id' => $_SESSION['student_id'],
            ':lesson_id' => $currentLessonId,
            ':course_id' => $courseId,
            ':lesson_title' => $selectedLesson['title'] ?? 'Lesson',
            ':course_name' => $course['course_name'] ?? '',
            ':instructor' => $course['instructor'] ?? '',
        ]);
        $bookmarkMessage = 'Lesson saved. You can continue from your dashboard anytime.';
    }
}

$lessonContent = renderRichText($selectedLesson['content'] ?? $course['tutorial_text'] ?? $course['description'] ?? 'This lesson content will be added soon.');
$lessonModuleName = $selectedLesson['module_name'] ?: 'General';
$lessonPosition = $lessonIndex !== null ? $lessonIndex + 1 : 0;
$totalLessons = count($lessons);
recordLessonProgress($pdo, (string)$_SESSION['student_id'], $courseId, $currentLessonId);

$progressData = getCourseLessonProgress($pdo, (string)$_SESSION['student_id'], $courseId);
$completedLessons = $progressData['completed'] ?? 0;
$progressPercent = $progressData['total'] > 0 ? min(100, (int)round(($completedLessons / $progressData['total']) * 100)) : 0;

?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['course_name'] ?? 'Course Content', ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="sofonyas (1).css">
    <style>
        body { font-family: Arial, sans-serif; background:#f5f7fa; color:#1f2937; margin:0; overflow-x:hidden; }
        nav ul { display:flex; gap:12px; list-style:none; padding:20px; background:#ffffff; margin:0; flex-wrap:wrap; }
        nav a { color:#334155; text-decoration:none; font-weight:700; }
        .layout { max-width: 1200px; margin: 24px auto; padding: 0 20px; display:grid; grid-template-columns: 320px 1fr; gap:20px; }
        .sidebar, .main-panel { background:white; border-radius:18px; padding:20px; box-shadow:0 12px 30px rgba(15,23,42,0.08); min-width:0; }
        .sidebar h2, .main-panel h2 { margin-top:0; }
        .module-group { margin-bottom:18px; }
        .module-group h3 { font-size:1rem; margin-bottom:10px; color:#1d4ed8; }
        .lesson-list { list-style:none; padding-left:0; margin:0; }
        .lesson-list li { margin-bottom:8px; }
        .lesson-link { display:block; padding:10px 12px; border-radius:12px; background:#f8fafc; color:#0f172a; text-decoration:none; transition:background .18s ease; word-break:break-word; overflow-wrap:anywhere; }
        .lesson-link:hover { background:#e2e8f0; }
        .lesson-link.active { background:#c7d2fe; color:#1e3a8a; font-weight:700; }
        .meta-row { display:flex; flex-wrap:wrap; gap:12px; margin:10px 0 18px; }
        .meta-box { background:#eff6ff; border-radius:14px; padding:12px 14px; min-width:120px; }
        .action-row { display:flex; flex-wrap:wrap; gap:10px; margin-top:16px; }
        .button, .button.secondary { display:inline-flex; align-items:center; justify-content:center; padding:12px 16px; border-radius:12px; text-decoration:none; font-weight:700; border:none; cursor:pointer; }
        .button { background:#2563eb; color:white; }
        .button.secondary { background:#e2e8f0; color:#1f2937; }
        .button:hover { opacity:0.95; }
        .section { margin-bottom:20px; }
        .section h2, .sidebar h2, .main-panel h2, .module-group h3 { overflow-wrap:anywhere; word-break:break-word; }
        .lesson-content-wrap, .lesson-content-wrap p, .lesson-content-wrap li, .lesson-content-wrap h1, .lesson-content-wrap h2, .lesson-content-wrap h3, .lesson-content-wrap h4 { overflow-wrap:anywhere; word-break:break-word; }
        @media (max-width: 860px) { nav ul { justify-content:center; } .layout { grid-template-columns: 1fr; } .sidebar, .main-panel { padding:16px; } .action-row { justify-content:center; } .section h2, .sidebar h2, .main-panel h2 { text-align:center; } }
    </style>
</head>
<body>
    <nav>
        <ul>
            <li><a href="student_dashboard.php">Dashboard</a></li>
            <li><a href="courses.php">Courses</a></li>
            <li><a href="course_details.php?id=<?php echo (int)$courseId; ?>">Course Overview</a></li>
        </ul>
    </nav>
    <div class="layout">
        <aside class="sidebar">
            <h2><?php echo htmlspecialchars($course['course_name'] ?? 'Course Content', ENT_QUOTES, 'UTF-8'); ?></h2>
            <p><?php echo htmlspecialchars($course['short_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="meta-row">
                <div class="meta-box"><strong><?php echo $lessonPosition; ?>/<?php echo $totalLessons; ?></strong><br>Lessons</div>
                <div class="meta-box"><strong><?php echo $progressPercent; ?>%</strong><br>Progress</div>
            </div>
            <?php if (empty($modules) && !empty($lessons)): ?>
                <div class="module-group">
                    <h3>Lessons</h3>
                    <ul class="lesson-list">
                        <?php foreach ($lessons as $lesson): ?>
                            <li>
                                <a class="lesson-link<?php echo (int)$lesson['id'] === $currentLessonId ? ' active' : ''; ?>" href="course_content.php?course_id=<?php echo (int)$courseId; ?>&lesson_id=<?php echo (int)$lesson['id']; ?>">
                                    <?php echo htmlspecialchars($lesson['title'] ?? 'Lesson', ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <?php foreach ($modules as $module): ?>
                    <div class="module-group">
                        <h3><?php echo htmlspecialchars($module['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <ul class="lesson-list">
                            <?php foreach ($lessons as $lesson): ?>
                                <?php if ((int)$lesson['module_id'] === (int)$module['id']): ?>
                                    <li>
                                        <a class="lesson-link<?php echo (int)$lesson['id'] === $currentLessonId ? ' active' : ''; ?>" href="course_content.php?course_id=<?php echo (int)$courseId; ?>&lesson_id=<?php echo (int)$lesson['id']; ?>">
                                            <?php echo htmlspecialchars($lesson['title'] ?? 'Lesson', ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
                <?php $orphanLessons = array_filter($lessons, fn($lesson) => empty($lesson['module_id'])); ?>
                <?php if (!empty($orphanLessons)): ?>
                    <div class="module-group">
                        <h3>General Lessons</h3>
                        <ul class="lesson-list">
                            <?php foreach ($orphanLessons as $lesson): ?>
                                <li>
                                    <a class="lesson-link<?php echo (int)$lesson['id'] === $currentLessonId ? ' active' : ''; ?>" href="course_content.php?course_id=<?php echo (int)$courseId; ?>&lesson_id=<?php echo (int)$lesson['id']; ?>">
                                        <?php echo htmlspecialchars($lesson['title'] ?? 'Lesson', ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </aside>
        <main class="main-panel">
            <div class="section">
                <h2><?php echo htmlspecialchars($selectedLesson['title'] ?? 'Lesson', ENT_QUOTES, 'UTF-8'); ?></h2>
                <p><strong>Module:</strong> <?php echo htmlspecialchars($lessonModuleName, ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Lesson:</strong> <?php echo htmlspecialchars($lessonPosition . ' of ' . $totalLessons, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if ($bookmarkMessage): ?>
                    <div style="margin:12px 0 18px; padding:12px; background:#e0f2fe; color:#0c4a6e; border-radius:12px;">
                        <?php echo htmlspecialchars($bookmarkMessage, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <div class="lesson-content-wrap"><?php echo $lessonContent; ?></div>
            </div>
            <div class="action-row">
                <form method="post" style="margin:0;">
                    <input type="hidden" name="save_lesson" value="1">
                    <button class="button" type="submit">Save Lesson</button>
                </form>
                <?php if ($prevLesson): ?>
                    <a class="button secondary" href="course_content.php?course_id=<?php echo (int)$courseId; ?>&lesson_id=<?php echo (int)$prevLesson['id']; ?>">Previous Lesson</a>
                <?php endif; ?>
                <?php if ($nextLesson): ?>
                    <a class="button secondary" href="course_content.php?course_id=<?php echo (int)$courseId; ?>&lesson_id=<?php echo (int)$nextLesson['id']; ?>">Next Lesson</a>
                <?php endif; ?>
                <a class="button secondary" href="course_details.php?id=<?php echo (int)$courseId; ?>">ወደ ዕይታ ተመለስ</a>
            </div>
        </main>
    </div>
</body>
</html>
