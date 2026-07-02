<?php
session_start();
require_once __DIR__ . '/db.php';

$message = '';
if (isset($_POST['save_lesson']) && isset($_SESSION['student_id'])) {
    $lessonId = (int)($_POST['lesson_id'] ?? 0);
    $courseId = (int)($_POST['course_id'] ?? 0);
    $lessonTitle = trim((string)($_POST['lesson_title'] ?? ''));
    $courseName = trim((string)($_POST['course_name'] ?? ''));
    $instructor = trim((string)($_POST['instructor'] ?? ''));

    if ($lessonId > 0 && $courseId > 0 && $lessonTitle !== '' && $courseName !== '') {
        $stmt = $pdo->prepare('SELECT id FROM lesson_bookmarks WHERE student_id = :student_id AND lesson_id = :lesson_id LIMIT 1');
        $stmt->execute([':student_id' => $_SESSION['student_id'], ':lesson_id' => $lessonId]);
        if ($stmt->fetch()) {
            $message = 'This lesson is already saved.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO lesson_bookmarks (student_id, lesson_id, course_id, lesson_title, course_name, instructor) VALUES (:student_id, :lesson_id, :course_id, :lesson_title, :course_name, :instructor)');
            $stmt->execute([
                ':student_id' => $_SESSION['student_id'],
                ':lesson_id' => $lessonId,
                ':course_id' => $courseId,
                ':lesson_title' => $lessonTitle,
                ':course_name' => $courseName,
                ':instructor' => $instructor,
            ]);
            $message = 'Lesson saved to your dashboard.';
        }
    } else {
        $message = 'Invalid lesson details.';
    }
} elseif (isset($_POST['save_lesson']) && !isset($_SESSION['student_id'])) {
    $message = 'Please login as a student before saving a lesson.';
}

$courseId = (int)($_GET['course_id'] ?? 0);
$lessonId = (int)($_GET['lesson_id'] ?? 0);
$isEnrolled = false;

$stmt = $pdo->query('SELECT * FROM courses ORDER BY created_at DESC LIMIT 12');
$courses = $stmt->fetchAll();

$enrolledCourseKeys = [];
$enrolledStmt = $pdo->prepare('SELECT course_id, course FROM registrations WHERE student_id = :student_id');
$enrolledStmt->execute([':student_id' => $_SESSION['student_id']]);
foreach ($enrolledStmt->fetchAll() as $entry) {
    if (!empty($entry['course_id'])) {
        $enrolledCourseKeys[(int)$entry['course_id']] = true;
    }
    if (!empty($entry['course'])) {
        $enrolledCourseKeys['name:' . $entry['course']] = true;
    }
}

$moduleMap = [];
$lessonMap = [];

$moduleStmt = $pdo->query('SELECT * FROM course_modules ORDER BY course_id, sort_order, id');
foreach ($moduleStmt->fetchAll() as $module) {
  $moduleMap[(int)$module['course_id']][] = $module;
}

$lessonStmt = $pdo->query('SELECT * FROM course_lessons ORDER BY course_id, module_id, sort_order, id');
foreach ($lessonStmt->fetchAll() as $lesson) {
  $lessonMap[(int)$lesson['course_id']][] = $lesson;
}

$firstLessonMap = [];
foreach ($lessonMap as $courseIdKey => $lessons) {
  if (!empty($lessons)) {
    $firstLessonMap[(int)$courseIdKey] = (int)$lessons[0]['id'];
  }
}

$selectedCourse = null;
$selectedLesson = null;
$courseModules = [];
$allLessons = [];
$lessonIndex = null;
$prevLesson = null;
$nextLesson = null;
$showLessonView = false;

if ($courseId > 0) {
  $courseStmt = $pdo->prepare('SELECT * FROM courses WHERE id = :id LIMIT 1');
  $courseStmt->execute([':id' => $courseId]);
  $selectedCourse = $courseStmt->fetch();

  if ($selectedCourse) {
      $isEnrolled = isset($_SESSION['student_id']) && isStudentEnrolled($pdo, (string)$_SESSION['student_id'], $courseId);
      $moduleStmt = $pdo->prepare('SELECT * FROM course_modules WHERE course_id = :course_id ORDER BY sort_order ASC, id ASC');
      $moduleStmt->execute([':course_id' => $courseId]);
      $courseModules = $moduleStmt->fetchAll();

      $lessonStmt = $pdo->prepare('SELECT cl.*, cm.name AS module_name FROM course_lessons cl LEFT JOIN course_modules cm ON cm.id = cl.module_id WHERE cl.course_id = :course_id ORDER BY COALESCE(cl.module_id, 999999) ASC, cl.sort_order ASC, cl.id ASC');
      $lessonStmt->execute([':course_id' => $courseId]);
      $allLessons = $lessonStmt->fetchAll();
    if ($lessonId > 0) {
      foreach ($allLessons as $lesson) {
        if ((int)$lesson['id'] === $lessonId) {
          $selectedLesson = $lesson;
          break;
        }
      }
    }

    if (!$selectedLesson && !empty($allLessons)) {
      $selectedLesson = $allLessons[0];
    }

    if ($selectedLesson && $isEnrolled) {
      $showLessonView = true;
      $currentLessonId = (int)$selectedLesson['id'];
      foreach ($allLessons as $index => $lesson) {
        if ((int)$lesson['id'] === $currentLessonId) {
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
    }
  }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ተማሪ መማሪያ / ኮርሶች</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-family: Arial, sans-serif; margin: 0; background: #f4f7fb; color: #1f2937; }
    .rich-content h1, .rich-content h2, .rich-content h3, .rich-content h4 { font-size: 1.02rem; line-height: 1.35; margin: 0.35em 0; }
    .rich-content ul, .rich-content ol { padding-left: 18px; margin: 8px 0 10px; }
    .rich-content li { margin-bottom: 6px; }
    .rich-content p { margin: 0 0 8px; }
    .outline-card { border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; padding: 10px 12px; margin-top: 8px; }
    .module-card { border: 1px solid #dbeafe; border-radius: 10px; background: linear-gradient(135deg,#eff6ff,#fff); padding: 10px; margin-bottom: 8px; }
    .module-title { display: flex; justify-content: space-between; align-items: center; gap: 10px; font-weight: 700; color: #1e3a8a; margin-bottom: 6px; }
    .lesson-chip { display: inline-flex; align-items: center; gap: 6px; background: #fff; border: 1px solid #dbeafe; border-radius: 999px; padding: 6px 10px; margin: 4px 6px 0 0; font-size: 12px; color: #1f2937; }
    .tag { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 999px; background: #eff6ff; color: #1d4ed8; font-size: 11px; font-weight: 700; }
    nav { background: #111827; padding: 12px 18px; }
    nav a { color: #fff; text-decoration: none; margin-right: 14px; font-weight: 700; }
    .wrap { max-width: 1100px; margin: 0 auto; padding: 24px; }
    .card { background: #fff; border-radius: 12px; padding: 18px; box-shadow: 0 8px 18px rgba(15,23,42,0.08); margin-bottom: 18px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
    .btn { display: inline-block; background: #2563eb; color: #fff; padding: 10px 14px; border-radius: 8px; text-decoration: none; margin-right: 8px; }
    .muted { color: #475569; }
    .video-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; background: #fff; margin-top: 12px; }
    .video-frame { width: 100%; border-radius: 10px; background: #0f172a; }
    .video-meta { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
    .video-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .video-actions .btn { margin-right: 0; padding: 8px 12px; font-size: 13px; }
    .pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; background: #eff6ff; color: #1d4ed8; font-size: 12px; font-weight: 700; }
    .progress-track { width: 100%; height: 8px; background: #e5e7eb; border-radius: 999px; overflow: hidden; margin-top: 8px; }
    .progress-fill { height: 100%; background: linear-gradient(90deg,#2563eb,#38bdf8); border-radius: 999px; width: 0%; }
    .history-panel { margin-top: 12px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; }
    .history-panel h5 { margin: 0 0 8px; font-size: 14px; color: #0f172a; }
    .history-list { display: flex; flex-direction: column; gap: 8px; }
    .history-item { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 8px 10px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; }
    .history-item small { color: #475569; }
    @media (max-width: 980px) { nav { padding: 10px 12px; } nav a { display: inline-block; margin: 4px 10px 4px 0; } .wrap { padding: 14px; } .grid { grid-template-columns: 1fr; } }
    @media (max-width: 576px) { .btn { display: block; width: 100%; margin: 6px 0; text-align: center; } .card { padding: 14px; } }
  </style>
</head>
<body>
<nav>
  <a href="sofonyas (2).html">Home</a>
  <a href="dashboard.php">Dashboard</a>
  <a href="tutorial.php">Tutorial</a>
  <a href="discussion_forum.php">Forum</a>
  <a href="library.php">Library</a>
  <a href="student_login.php">Student Login</a>
  <a href="admin_login.php">Admin Login</a>
  <a href="contact.html">Contact</a>
</nav>
<div class="wrap">
  <div class="card">
    <h1>ተማሪ መማሪያ እና ኮርሶች</h1>
    <p class="muted">እዚህ ከዳታቤዝ የተጫኑ ትምህርቶችን ተመልከት፣ አጭር እና ሙሉ መግለጫዎችን እና የትምህርት እንቅስቃሴዎችን ለተማሪዎች በቀላሉ እንዲያገኙ ይረዳል።</p>
    <?php if ($message !== ''): ?>
      <p class="pill" style="background:#ecfdf5;color:#166534;border:1px solid #a7f3d0; margin-top:10px;"><?php echo safe($message); ?></p>
    <?php endif; ?>
    <p style="margin-top:12px;">
      <a class="btn" href="student_login.php">ለተማሪ ግባ</a>
      <a class="btn" href="student_dashboard.php">ዳሽቦርድ</a>
      <a class="btn" href="exam20.php">የፈተና ማዕከል</a>
    </p>
  </div>

  <div class="card" id="courses">
    <?php if ($showLessonView && $selectedCourse && $selectedLesson): ?>
      <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div>
          <h2 style="margin:0 0 8px;"><?php echo htmlspecialchars($selectedCourse['course_name'] ?? 'Tutorial'); ?></h2>
          <p class="muted" style="margin:0;"><?php echo !empty($selectedCourse['short_description']) ? renderRichText($selectedCourse['short_description']) : 'ይህ ኮርስ በተለያዩ ስልቶች ይወስዳል።'; ?></p>
        </div>
        <a class="btn" href="tutorial.php">Back to Courses</a>
      </div>
      <div style="display:grid; grid-template-columns: 320px 1fr; gap:16px; margin-top:16px;">
        <div class="outline-card" style="background:#f8fafc;">
          <h4 style="margin-top:0;">Lesson outline</h4>
          <?php if (!empty($courseModules)): ?>
            <?php foreach ($courseModules as $module): ?>
              <div class="module-card">
                <div class="module-title">
                  <span><?php echo htmlspecialchars($module['name']); ?></span>
                  <span class="tag">Module</span>
                </div>
                <div>
                  <?php foreach ($allLessons as $lesson): ?>
                    <?php if ((int)$lesson['module_id'] === (int)$module['id']): ?>
                      <a class="lesson-chip" href="tutorial.php?course_id=<?php echo (int)$selectedCourse['id']; ?>&lesson_id=<?php echo (int)$lesson['id']; ?>" style="display:inline-flex; text-decoration:none; margin:4px 6px 0 0;">
                        📘 <?php echo htmlspecialchars($lesson['title'] ?? 'Lesson'); ?>
                      </a>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
          <?php $orphanLessons = array_values(array_filter($allLessons, fn($lesson) => empty($lesson['module_id']))); ?>
          <?php if (!empty($orphanLessons)): ?>
            <div class="module-card">
              <div class="module-title"><span>General Lessons</span><span class="tag">Standalone</span></div>
              <div>
                <?php foreach ($orphanLessons as $lesson): ?>
                  <a class="lesson-chip" href="tutorial.php?course_id=<?php echo (int)$selectedCourse['id']; ?>&lesson_id=<?php echo (int)$lesson['id']; ?>" style="display:inline-flex; text-decoration:none; margin:4px 6px 0 0;">
                    📗 <?php echo htmlspecialchars($lesson['title'] ?? 'Lesson'); ?>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
        <div class="outline-card" style="background:#fff;">
          <?php if (!empty($selectedCourse['thumbnail'])): ?>
            <img src="<?php echo htmlspecialchars($selectedCourse['thumbnail']); ?>" alt="<?php echo htmlspecialchars($selectedCourse['course_name']); ?>" style="width:100%; max-height:220px; object-fit:cover; border-radius:12px; margin-bottom:12px;">
          <?php endif; ?>
          <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
            <?php if (!empty($selectedCourse['instructor_image'])): ?>
              <img src="<?php echo htmlspecialchars($selectedCourse['instructor_image']); ?>" alt="Instructor" style="width:64px; height:64px; object-fit:cover; border-radius:50%; border:2px solid #dbeafe;">
            <?php endif; ?>
            <div>
              <h3 style="margin:0;"><?php echo htmlspecialchars($selectedCourse['instructor'] ?? 'Instructor'); ?></h3>
              <p class="muted" style="margin:4px 0 0;"><?php echo htmlspecialchars($selectedCourse['course_name'] ?? 'Course'); ?></p>
            </div>
          </div>
          <?php if (!empty($selectedCourse['instructor_bio'])): ?>
            <div class="muted rich-content" style="margin-bottom:12px;"><strong>Instructor Bio:</strong> <?php echo renderRichText($selectedCourse['instructor_bio']); ?></div>
          <?php endif; ?>
          <h3 style="margin-top:0; margin-bottom:8px;"><?php echo htmlspecialchars($selectedLesson['title'] ?? 'Lesson'); ?></h3>
          <p class="muted" style="margin:0 0 10px;"><strong>Module:</strong> <?php echo htmlspecialchars($selectedLesson['module_name'] ?: 'General'); ?> · <strong>Lesson:</strong> <?php echo ($lessonIndex !== null ? $lessonIndex + 1 : 0) . ' / ' . count($allLessons); ?></p>
          <div class="rich-content" style="margin-bottom:12px;">
            <?php echo renderRichText($selectedLesson['content'] ?? $selectedCourse['tutorial_text'] ?? $selectedCourse['description'] ?? 'This lesson content will be added soon.'); ?>
          </div>
          <div class="video-actions" style="margin-top:10px;">
            <?php if ($prevLesson): ?>
              <a class="btn" href="tutorial.php?course_id=<?php echo (int)$selectedCourse['id']; ?>&lesson_id=<?php echo (int)$prevLesson['id']; ?>">← Previous</a>
            <?php endif; ?>
            <?php if ($nextLesson): ?>
              <a class="btn" href="tutorial.php?course_id=<?php echo (int)$selectedCourse['id']; ?>&lesson_id=<?php echo (int)$nextLesson['id']; ?>">Next →</a>
            <?php else: ?>
              <div class="pill" style="background:#ecfdf5;color:#166534;border:1px solid #a7f3d0;">Course tutorial completed</div>
            <?php endif; ?>
            <a class="btn" href="student_dashboard.php">Back to dashboard</a>
          </div>
        </div>
      </div>
    <?php elseif ($selectedCourse): ?>
      <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div>
          <h2 style="margin:0 0 8px;"><?php echo htmlspecialchars($selectedCourse['course_name'] ?? 'Course'); ?></h2>
          <p class="muted" style="margin:0;"><?php echo !empty($selectedCourse['short_description']) ? renderRichText($selectedCourse['short_description']) : 'ይህ ኮርስ በተለያዩ ስልቶች ይወስዳል።'; ?></p>
        </div>
        <a class="btn" href="tutorial.php">Back to Courses</a>
      </div>
      <div class="outline-card" style="margin-top:16px; background:#f8fafc;">
        <?php if (!empty($selectedCourse['thumbnail'])): ?>
          <img src="<?php echo htmlspecialchars($selectedCourse['thumbnail']); ?>" alt="<?php echo htmlspecialchars($selectedCourse['course_name']); ?>" style="width:100%; max-height:220px; object-fit:cover; border-radius:12px; margin-bottom:12px;">
        <?php endif; ?>
        <?php if (empty($isEnrolled)): ?>
          <div class="pill warning" style="margin-bottom:12px; display:inline-flex;">ይህን ኮርስ ለማግኘት እባክዎ ይመዝገቡ።</div>
          <p class="muted" style="margin-top:10px;">የትምህርት ክፍሎችን ለማግኘት ኮርሱን መመዝገብ አለብዎት።</p>
          <a class="btn" href="course_details.php?id=<?php echo (int)$selectedCourse['id']; ?>">Enroll Now</a>
        <?php else: ?>
          <div class="pill" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;">No lessons have been added for this course yet.</div>
          <p class="muted" style="margin-top:10px;">The course is ready for students, and the lesson content will appear here once it is published.</p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <h2>የሚገኙ ኮርሶች</h2>
      <?php if (empty($courses)): ?>
        <p class="muted">እስካሁን ምንም ኮርስ አልተጨመረም።</p>
      <?php else: ?>
        <div class="grid">
          <?php foreach ($courses as $course): ?>
            <div style="border:1px solid #e5e7eb; border-radius:10px; padding:14px; background:#f8fafc;">
              <h3 style="margin-top:0;"><?php echo htmlspecialchars($course['course_name']); ?></h3>
              <?php if (!empty($course['tutorial_video'])): ?>
                <div class="video-card" data-course-id="<?php echo (int)$course['id']; ?>" data-course-title="<?php echo htmlspecialchars($course['course_name']); ?>" data-video-url="<?php echo htmlspecialchars($course['tutorial_video']); ?>">
                  <?php if (preg_match('/youtube\.com|youtu\.be/i', (string)$course['tutorial_video'])): ?>
                    <iframe class="video-frame" height="220" src="<?php echo htmlspecialchars($course['tutorial_video']); ?>" title="Lesson video" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                  <?php else: ?>
                    <video class="video-frame" controls preload="metadata" playsinline>
                      <source src="<?php echo htmlspecialchars($course['tutorial_video']); ?>">
                      Your browser does not support the video tag.
                    </video>
                  <?php endif; ?>
                  <div class="video-meta">
                    <div class="video-actions">
                      <button class="btn" type="button" data-play-btn>▶ Play</button>
                      <button class="btn" type="button" data-resume-btn>↻ Resume</button>
                    </div>
                    <span class="pill" data-status-label>Start video</span>
                  </div>
                  <div class="progress-track"><div class="progress-fill" data-progress-fill></div></div>
                  <div class="video-meta" style="margin-top:8px;">
                    <span class="pill" data-completion-label>Completion 0%</span>
                  </div>
                  <div class="history-panel" data-history-panel>
                    <h5>Watch History</h5>
                    <div class="history-list" data-history-list></div>
                  </div>
                  <p class="muted" style="margin-top:8px;">Play, resume, or revisit your last watched position for this lesson.</p>
                </div>
              <?php endif; ?>
              <?php if (!empty($course['thumbnail'])): ?>
                <img src="<?php echo htmlspecialchars($course['thumbnail']); ?>" alt="<?php echo htmlspecialchars($course['course_name']); ?>" style="width:100%; max-height:180px; object-fit:cover; border-radius:8px; margin-bottom:10px;">
              <?php endif; ?>
              <?php if (!empty($course['short_description'])): ?>
                <div class="muted rich-content" style="margin-bottom:8px;"><strong>አጭር መግለጫ:</strong> <?php echo renderRichText($course['short_description']); ?></div>
              <?php endif; ?>
              <div class="muted rich-content" style="margin-bottom:8px;"><?php echo !empty($course['description']) ? renderRichText($course['description']) : 'የኮርስ መግለጫ የለም'; ?></div>
              <?php if (!empty($course['category']) || !empty($course['level'])): ?>
                <p><strong>ምድብ / ደረጃ:</strong> <?php echo htmlspecialchars($course['category'] ?: ''); ?><?php echo (!empty($course['category']) && !empty($course['level']) ? ' · ' : ''); ?><?php echo htmlspecialchars($course['level'] ?: ''); ?></p>
              <?php endif; ?>
              <p><strong>ኮድ:</strong> <?php echo htmlspecialchars($course['course_code']); ?></p>
              <p><strong>ዋጋ:</strong> <?php echo number_format($course['price'], 2); ?> ብር</p>
              <?php if (!empty($moduleMap[$course['id']]) || !empty($lessonMap[$course['id']])): ?>
                <div class="outline-card" style="margin-bottom:10px;">
                  <strong>Course → Module → Lesson</strong>
                  <div style="margin-top:8px;">
                    <?php foreach ($moduleMap[$course['id']] ?? [] as $module): ?>
                      <div class="module-card">
                        <div class="module-title">
                          <span><?php echo htmlspecialchars($module['name']); ?></span>
                          <span class="tag">Module</span>
                        </div>
                        <div>
                          <?php $moduleLessons = array_values(array_filter($lessonMap[$course['id']] ?? [], fn($lesson) => (int)$lesson['module_id'] === (int)$module['id'])); ?>
                          <?php if ($moduleLessons): ?>
                            <?php foreach ($moduleLessons as $lesson): ?>
                              <span class="lesson-chip">📘 <?php echo htmlspecialchars($lesson['title']); ?></span>
                              <form method="post" style="display:inline-block; margin-left: 4px;">
                                <input type="hidden" name="save_lesson" value="1">
                                <input type="hidden" name="lesson_id" value="<?php echo (int)$lesson['id']; ?>">
                                <input type="hidden" name="course_id" value="<?php echo (int)$course['id']; ?>">
                                <input type="hidden" name="lesson_title" value="<?php echo safe($lesson['title']); ?>">
                                <input type="hidden" name="course_name" value="<?php echo safe($course['course_name']); ?>">
                                <input type="hidden" name="instructor" value="<?php echo safe($course['instructor'] ?? ''); ?>">
                                <button type="submit" class="btn" style="padding:6px 10px; font-size:12px; border:none; cursor:pointer;">Save</button>
                              </form>
                            <?php endforeach; ?>
                          <?php else: ?>
                            <span class="muted">No lessons added yet.</span>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                    <?php $orphanLessons = array_values(array_filter($lessonMap[$course['id']] ?? [], fn($lesson) => (int)$lesson['module_id'] === 0 || !array_filter($moduleMap[$course['id']] ?? [], fn($m) => (int)$m['id'] === (int)$lesson['module_id']))); ?>
                    <?php if (!empty($orphanLessons)): ?>
                      <div class="module-card">
                        <div class="module-title"><span>General Lessons</span><span class="tag">Standalone</span></div>
                        <div>
                          <?php foreach ($orphanLessons as $lesson): ?>
                            <span class="lesson-chip">📗 <?php echo htmlspecialchars($lesson['title']); ?></span>
                            <form method="post" style="display:inline-block; margin-left: 4px;">
                              <input type="hidden" name="save_lesson" value="1">
                              <input type="hidden" name="lesson_id" value="<?php echo (int)$lesson['id']; ?>">
                              <input type="hidden" name="course_id" value="<?php echo (int)$course['id']; ?>">
                              <input type="hidden" name="lesson_title" value="<?php echo safe($lesson['title']); ?>">
                              <input type="hidden" name="course_name" value="<?php echo safe($course['course_name']); ?>">
                              <input type="hidden" name="instructor" value="<?php echo safe($course['instructor'] ?? ''); ?>">
                              <button type="submit" class="btn" style="padding:6px 10px; font-size:12px; border:none; cursor:pointer;">Save</button>
                            </form>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>
              <?php if (!empty($course['modules'])): ?><div class="rich-content" style="margin-bottom:10px;"><strong>Course Outline:</strong> <?php echo renderRichText($course['modules']); ?></div><?php endif; ?>
              <?php if (!empty($course['quiz'])): ?><div class="rich-content" style="margin-bottom:10px;"><strong>Quiz:</strong> <?php echo renderRichText($course['quiz']); ?></div><?php endif; ?>
              <?php if (!empty($course['assignment'])): ?><div class="rich-content" style="margin-bottom:10px;"><strong>Assignment:</strong> <?php echo renderRichText($course['assignment']); ?></div><?php endif; ?>
              <?php if (!empty($course['certificate_requirements'])): ?><div class="rich-content" style="margin-bottom:10px;"><strong>Certificate Requirements:</strong> <?php echo renderRichText($course['certificate_requirements']); ?></div><?php endif; ?>
              <p>
                <?php $firstLessonId = $firstLessonMap[$course['id']] ?? 0; ?>
                <a class="btn" href="tutorial.php?course_id=<?php echo (int)$course['id']; ?><?php echo $firstLessonId ? '&lesson_id=' . (int)$firstLessonId : ''; ?>">Start tutorial</a>
                <a class="btn" href="student_register.php?course=<?php echo rawurlencode($course['course_name']); ?>&amount=<?php echo (float)$course['price']; ?>">ይመዝገቡ ለዚህ ትምህርት</a>
                <form method="post" style="display:inline-block; margin-left: 4px;">
                  <input type="hidden" name="save_lesson" value="1">
                  <input type="hidden" name="lesson_id" value="<?php echo (int)$course['id']; ?>">
                  <input type="hidden" name="course_id" value="<?php echo (int)$course['id']; ?>">
                  <input type="hidden" name="lesson_title" value="<?php echo safe($course['course_name']); ?>">
                  <input type="hidden" name="course_name" value="<?php echo safe($course['course_name']); ?>">
                  <input type="hidden" name="instructor" value="<?php echo safe($course['instructor'] ?? ''); ?>">
                  <button type="submit" class="btn" style="border: none; cursor: pointer;">ትምህርት አስቀምጥ</button>
                </form>
                <?php if (!empty($course['pdf_file'])): ?>
                  <a class="btn" href="<?php echo htmlspecialchars(publicMediaUrl($course['pdf_file'])); ?>" target="_blank">PDF እይ</a>
                <?php endif; ?>
              </p>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<script>
  (function () {
    const prefix = 'lms_video_progress_';
    const historyKey = 'lms_watch_history';

    function formatTime(seconds) {
      if (!Number.isFinite(seconds) || seconds <= 0) return '0:00';
      const mins = Math.floor(seconds / 60);
      const secs = Math.floor(seconds % 60);
      return mins + ':' + (secs < 10 ? '0' + secs : secs);
    }

    function saveHistory(card, saved) {
      const history = JSON.parse(localStorage.getItem(historyKey) || '[]');
      const courseId = card.getAttribute('data-course-id');
      const courseTitle = card.getAttribute('data-course-title') || 'Lesson';
      const videoUrl = card.getAttribute('data-video-url') || '';
      const entry = {
        courseId: courseId,
        title: courseTitle,
        videoUrl: videoUrl,
        percent: saved.percent || 0,
        currentTime: saved.currentTime || 0,
        updatedAt: Date.now()
      };
      const filtered = history.filter(function (item) { return item.courseId !== courseId; });
      filtered.unshift(entry);
      localStorage.setItem(historyKey, JSON.stringify(filtered.slice(0, 6)));
    }

    function updateHistory(card) {
      const list = card.querySelector('[data-history-list]');
      if (!list) return;
      const history = JSON.parse(localStorage.getItem(historyKey) || '[]');
      const courseId = card.getAttribute('data-course-id');
      const relevant = history.filter(function (item) { return item.courseId === courseId; });
      if (!relevant.length) {
        list.innerHTML = '<small class="muted">No watch history yet.</small>';
        return;
      }
      list.innerHTML = '';
      relevant.slice(0, 3).forEach(function (item) {
        const row = document.createElement('div');
        row.className = 'history-item';
        row.innerHTML = '<div><strong>' + item.percent + '%</strong><br><small>' + formatTime(item.currentTime) + ' watched</small></div><a class="btn" href="' + (item.videoUrl || '#') + '" target="_blank" rel="noopener">Open</a>';
        list.appendChild(row);
      });
    }

    function updateUi(card, saved) {
      const label = card.querySelector('[data-status-label]');
      const completion = card.querySelector('[data-completion-label]');
      const fill = card.querySelector('[data-progress-fill]');
      const playBtn = card.querySelector('[data-play-btn]');
      const resumeBtn = card.querySelector('[data-resume-btn]');
      const percent = Math.min(100, Math.max(0, Number(saved && saved.percent ? saved.percent : 0) || 0));
      if (fill) fill.style.width = percent + '%';
      if (completion) completion.textContent = 'Completion ' + percent + '%';
      if (label) label.textContent = percent >= 95 ? 'Completed' : (saved && saved.currentTime ? 'Resume video' : 'Start video');
      if (playBtn) playBtn.disabled = false;
      if (resumeBtn) resumeBtn.style.display = saved && saved.currentTime ? 'inline-block' : 'none';
    }

    document.querySelectorAll('.video-card').forEach(function (card) {
      const courseId = card.getAttribute('data-course-id');
      const storageKey = prefix + courseId;
      const video = card.querySelector('video');
      const iframe = card.querySelector('iframe');
      const playBtn = card.querySelector('[data-play-btn]');
      const resumeBtn = card.querySelector('[data-resume-btn]');
      let saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
      updateUi(card, saved);
      updateHistory(card);

      function saveProgress() {
        if (!video || !isFinite(video.duration) || !video.duration) return;
        const percent = Math.min(100, Math.max(0, Math.round((video.currentTime / video.duration) * 100)));
        const progress = { currentTime: video.currentTime, percent: percent, updatedAt: Date.now(), completed: percent >= 95 };
        localStorage.setItem(storageKey, JSON.stringify(progress));
        saveHistory(card, progress);
        updateUi(card, progress);
        updateHistory(card);
      }

      if (video) {
        if (saved && Number(saved.currentTime) > 0) {
          video.addEventListener('loadedmetadata', function () {
            if (video.duration && saved.currentTime < video.duration) {
              video.currentTime = saved.currentTime;
            }
          }, { once: true });
        }

        video.addEventListener('timeupdate', function () {
          if (video.duration) saveProgress();
        });

        video.addEventListener('ended', function () {
          const percent = 100;
          const progress = { currentTime: video.duration || 0, percent: percent, updatedAt: Date.now(), completed: true };
          localStorage.setItem(storageKey, JSON.stringify(progress));
          saveHistory(card, progress);
          updateUi(card, progress);
          updateHistory(card);
        });
      }

      if (playBtn) {
        playBtn.addEventListener('click', function () {
          if (video) {
            if (video.currentTime > 0 && video.duration) {
              video.currentTime = 0;
            }
            video.play().catch(function () {});
          } else if (iframe) {
            window.open(iframe.getAttribute('src') || card.getAttribute('data-video-url'), '_blank', 'noopener');
          }
        });
      }

      if (resumeBtn) {
        resumeBtn.addEventListener('click', function () {
          if (video && saved && saved.currentTime) {
            video.currentTime = saved.currentTime;
            video.play().catch(function () {});
          } else if (iframe) {
            window.open(iframe.getAttribute('src') || card.getAttribute('data-video-url'), '_blank', 'noopener');
          }
        });
      }
    });
  })();
</script>
</body>
</html>
