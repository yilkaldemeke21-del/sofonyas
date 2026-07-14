<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit;
}

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
        $checkStmt = $pdo->prepare('SELECT id FROM registrations WHERE student_id = :student_id AND (course_id = :course_id OR course = :course) LIMIT 1');
        $checkStmt->execute([':student_id' => $_SESSION['student_id'], ':course_id' => $courseId, ':course' => $course['course_name']]);
        if ($checkStmt->fetch()) {
            $message = 'You are already enrolled in this course.';
            $messageType = 'info';
        } else {
            $recordId = uniqid('reg_', true);
            $insertStmt = $pdo->prepare('INSERT INTO registrations (id, name, email, student_id, course, course_id, amount, payment_status, created_at) VALUES (:id, :name, :email, :student_id, :course, :course_id, :amount, :payment_status, :created_at)');
            $insertStmt->execute([
                ':id' => $recordId,
                ':name' => $studentName,
                ':email' => $studentEmail,
                ':student_id' => $_SESSION['student_id'],
                ':course' => $course['course_name'],
                ':course_id' => $courseId,
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

$courseOverviewContent = trim((string)($course['description'] ?? ''));
if ($courseOverviewContent === '') {
    $courseOverviewContent = trim((string)($course['tutorial_text'] ?? ''));
}
if ($courseOverviewContent === '') {
    $courseOverviewContent = trim((string)($course['short_description'] ?? ''));
}

$courseTutorialContent = trim((string)($course['tutorial_text'] ?? ''));
if ($courseTutorialContent === '') {
    $courseTutorialContent = $courseOverviewContent;
}

$courseSummaryCopy = trim((string)($course['short_description'] ?? ''));
if ($courseSummaryCopy === '') {
    $courseSummaryCopy = $courseOverviewContent;
}

$isEnrolled = false;
$registrationRecord = null;
if (!empty($_SESSION['student_id']) && $course) {
    $isEnrolled = isStudentEnrolled($pdo, (string)$_SESSION['student_id'], $courseId);
    $registrationRecord = $isEnrolled ? getStudentRegistration($pdo, (string)$_SESSION['student_id'], $courseId) : null;
}

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
    <style>
        body { background: #f8fafc; color: #0f172a; font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        nav ul { display:flex; flex-wrap:wrap; gap:18px; list-style:none; padding:0; margin:0 0 24px; }
        nav a { color:#2563eb; text-decoration:none; font-weight:700; }
        .card { background:#ffffff; border:1px solid #e2e8f0; border-radius:22px; box-shadow:0 10px 30px rgba(15,23,42,0.06); padding:24px; margin-bottom:24px; }
        .pill { display:inline-flex; align-items:center; justify-content:center; padding:8px 12px; border-radius:999px; font-size:12px; font-weight:700; color:#1d4ed8; background:#dbeafe; }
        .button { display:inline-flex; align-items:center; justify-content:center; padding:12px 18px; border:none; border-radius:14px; background:#2563eb; color:#fff; text-decoration:none; font-weight:700; cursor:pointer; }
        .button.secondary { background:#111827; }
        .action-link { color:#2563eb; text-decoration:none; font-weight:700; }
        .muted { color:#64748b; }
        .rich-content { line-height:1.75; color:#334155; }
        .rich-content h3, .rich-content h4, .rich-content h5 { margin-top:18px; margin-bottom:10px; }
        .rich-content p { margin:0 0 14px; }
        .rich-content ul, .rich-content ol { margin:12px 0 18px 20px; }
        .tab-menu { border-bottom:1px solid #e2e8f0; padding-bottom:14px; }
        .tab-menu .tab-btn { cursor:pointer; border:1px solid transparent; background:transparent; color:#2563eb; padding:10px 14px; border-radius:999px; font-weight:700; transition:background 0.18s ease,border-color 0.18s ease,box-shadow 0.18s ease; }
        .tab-menu .tab-btn.active { background:#eef2ff; border-color:#c7d2fe; box-shadow:0 8px 18px rgba(37,99,235,0.12); }
        .tab-pane { display:none; padding-top:18px; }
        .tab-pane.active { display:block; }
        @media (max-width: 860px) { nav ul { justify-content:center; } .card { padding:18px; } }
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
        <div style="display:grid; gap:24px; grid-template-columns: minmax(280px, 360px) 1fr; align-items:start;">
            <div>
                <img src="<?php echo htmlspecialchars(publicMediaUrl($course['thumbnail'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($course['course_name'] ?? 'Course', ENT_QUOTES, 'UTF-8'); ?>" style="width:100%; border-radius:18px; object-fit:cover; max-height:420px; box-shadow:0 18px 40px rgba(15,23,42,0.08);">

                <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:18px;">
                    <span class="pill info"><?php echo safe($course['category'] ?? 'General'); ?></span>
                    <span class="pill success"><?php echo safe($course['level'] ?? 'Beginner'); ?></span>
                    <span class="pill" style="background:#eef2ff; color:#1d4ed8;"><?php echo number_format((float)($course['price'] ?? 0), 2); ?> ብር</span>
                </div>

                <?php if (!empty($course['pdf_file'])): ?>
                    <p style="margin-top:16px;"><a class="button secondary" href="<?php echo htmlspecialchars(publicMediaUrl($course['pdf_file']), ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Open Course PDF</a></p>
                <?php endif; ?>

                <?php if (!empty($course['tutorial_image'])): ?>
                    <p style="margin-top:14px;"><a class="action-link" href="<?php echo htmlspecialchars(publicMediaUrl($course['tutorial_image']), ENT_QUOTES, 'UTF-8'); ?>" target="_blank">View Tutorial Image</a></p>
                <?php endif; ?>
                <?php if (!empty($course['tutorial_audio'])): ?>
                    <p><a class="action-link" href="<?php echo htmlspecialchars(publicMediaUrl($course['tutorial_audio']), ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Listen to Tutorial Audio</a></p>
                <?php endif; ?>
                <?php if (!empty($course['tutorial_video'])): ?>
                    <p><a class="action-link" href="<?php echo htmlspecialchars(publicMediaUrl($course['tutorial_video']), ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Watch Tutorial Video</a></p>
                <?php endif; ?>
            </div>

            <div>
                <h2><?php echo htmlspecialchars($course['course_name'] ?? 'Course', ENT_QUOTES, 'UTF-8'); ?></h2>
                <?php if ($message !== ''): ?>
                    <p style="color: <?php echo $messageType === 'success' ? '#166534' : ($messageType === 'info' ? '#1d4ed8' : '#b91c1c'); ?>; font-weight:700;">
                        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                <?php endif; ?>
                <p class="muted" style="margin-top:0; line-height:1.75;"><?php echo renderSafeCourseContent($courseSummaryCopy !== '' ? $courseSummaryCopy : 'No course summary is available yet.'); ?></p>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap:14px; margin:18px 0;">
                    <div class="card" style="padding:12px 14px; background:#f8fafc; border:1px solid #e2e8f0;"><strong>Instructor</strong><br><?php echo safe($course['instructor'] ?? 'Staff'); ?></div>
                    <div class="card" style="padding:12px 14px; background:#f8fafc; border:1px solid #e2e8f0;"><strong>Price</strong><br><?php echo number_format((float)($course['price'] ?? 0), 2); ?> ብር</div>
                    <div class="card" style="padding:12px 14px; background:#f8fafc; border:1px solid #e2e8f0;"><strong>Status</strong><br><?php echo $isEnrolled ? 'Enrolled' : 'Not enrolled'; ?></div>
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:10px;">
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="enroll_course_id" value="<?php echo (int)$courseId; ?>">
                        <button class="button" type="submit" <?php echo $isEnrolled ? 'disabled style="opacity:.6;cursor:not-allowed;"' : ''; ?>><?php echo $isEnrolled ? 'Enrolled' : 'Enroll Now'; ?></button>
                    </form>
                    <a class="button secondary" href="student_dashboard.php">Back to Dashboard</a>
                </div>
                <div style="margin-top:18px;">
                    <p class="rich-content"><?php echo renderSafeCourseContent($courseOverviewContent !== '' ? $courseOverviewContent : 'No course description is available yet.'); ?></p>
                </div>
            </div>
        </div>
    </section>

    <section class="card" style="margin-top:24px;">
        <div class="tab-menu" style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:18px;">
            <button type="button" class="tab-btn active" data-tab="overview">Overview</button>
            <button type="button" class="tab-btn" data-tab="tutorial">Tutorial</button>
            <button type="button" class="tab-btn" data-tab="modules">Modules</button>
            <button type="button" class="tab-btn" data-tab="quiz">Quiz</button>
            <button type="button" class="tab-btn" data-tab="assignment">Assignment</button>
            <button type="button" class="tab-btn" data-tab="requirements">Requirements</button>
        </div>

        <div class="tab-pane active" id="overview">
            <h3>Course Overview</h3>
            <div class="rich-content"><?php echo renderSafeCourseContent($courseOverviewContent !== '' ? $courseOverviewContent : 'No overview available.'); ?></div>
            <?php if (!empty($course['tutorial_topic'])): ?>
                <p style="margin-top:16px;"><strong>Tutorial topic:</strong> <?php echo safe($course['tutorial_topic']); ?></p>
            <?php endif; ?>
            <?php if (!empty($course['certificate_requirements'])): ?>
                <div style="margin-top:18px;"><strong>Certificate Requirements</strong><div class="rich-content"><?php echo renderSafeCourseContent($course['certificate_requirements']); ?></div></div>
            <?php endif; ?>
        </div>

        <div class="tab-pane" id="tutorial">
            <h3>Course Tutorial</h3>
            <?php if (!empty($courseTutorialContent) || !empty($course['tutorial_topic'])): ?>
                <?php if (!empty($course['tutorial_topic'])): ?><h4><?php echo safe($course['tutorial_topic']); ?></h4><?php endif; ?>
                <div class="rich-content"><?php echo renderSafeCourseContent($courseTutorialContent !== '' ? $courseTutorialContent : 'No tutorial content available.'); ?></div>
            <?php else: ?>
                <p class="muted">Tutorial content has not been added yet.</p>
            <?php endif; ?>
            <?php if (!empty($course['tutorial_image'])): ?>
                <p style="margin-top:14px;"><a class="action-link" href="<?php echo htmlspecialchars(publicMediaUrl($course['tutorial_image']), ENT_QUOTES, 'UTF-8'); ?>" target="_blank">View Tutorial Image</a></p>
            <?php endif; ?>
            <?php if (!empty($course['tutorial_audio'])): ?>
                <p><a class="action-link" href="<?php echo htmlspecialchars(publicMediaUrl($course['tutorial_audio']), ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Listen to Tutorial Audio</a></p>
            <?php endif; ?>
            <?php if (!empty($course['tutorial_video'])): ?>
                <p><a class="action-link" href="<?php echo htmlspecialchars(publicMediaUrl($course['tutorial_video']), ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Watch Tutorial Video</a></p>
            <?php endif; ?>
        </div>

        <div class="tab-pane" id="modules">
            <h3>Course Modules</h3>
            <?php if (!empty($course['modules'])): ?>
                <div class="rich-content"><?php echo renderSafeCourseContent($course['modules'] ?? 'No module outline has been provided yet.'); ?></div>
            <?php else: ?>
                <p class="muted">No module outline has been provided yet.</p>
            <?php endif; ?>
        </div>

        <div class="tab-pane" id="quiz">
            <h3>Quiz Structure</h3>
            <?php if (!empty($course['quiz'])): ?>
                <div class="rich-content"><?php echo renderSafeCourseContent($course['quiz'] ?? 'No quiz information is available yet.'); ?></div>
            <?php else: ?>
                <p class="muted">Quiz instructions or sample questions are not available.</p>
            <?php endif; ?>
        </div>

        <div class="tab-pane" id="assignment">
            <h3>Assignment Details</h3>
            <?php if (!empty($course['assignment'])): ?>
                <div class="rich-content"><?php echo renderSafeCourseContent($course['assignment'] ?? 'No assignment details are available yet.'); ?></div>
            <?php else: ?>
                <p class="muted">Assignment details are not yet available.</p>
            <?php endif; ?>
        </div>

        <div class="tab-pane" id="requirements">
            <h3>Certificate Requirements</h3>
            <?php if (!empty($course['certificate_requirements'])): ?>
                <div class="rich-content"><?php echo renderSafeCourseContent($course['certificate_requirements'] ?? 'No certificate requirements have been specified.'); ?></div>
            <?php else: ?>
                <p class="muted">No certificate requirements have been specified.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="card" style="margin-top:24px;">
        <h3>Lessons</h3>
        <?php if (empty($lessons)): ?>
            <p>No lessons are available yet for this course.</p>
        <?php else: ?>
            <ul style="padding-left:18px; margin:0;">
                <?php foreach ($lessons as $lesson): ?>
                    <li style="margin-bottom:12px;">
                        <strong><?php echo htmlspecialchars($lesson['title'] ?? 'Lesson', ENT_QUOTES, 'UTF-8'); ?></strong>
                        <?php if ($isEnrolled): ?>
                            <div style="margin-top:6px;"><a class="action-link" href="course_content.php?course_id=<?php echo (int)$courseId; ?>&lesson_id=<?php echo (int)$lesson['id']; ?>">Open Lesson</a></div>
                        <?php else: ?>
                            <div style="margin-top:6px; color:#64748b;">Enroll to access this lesson.</div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <script>
        document.querySelectorAll('.tab-menu .tab-btn').forEach((button) => {
            button.addEventListener('click', () => {
                const tab = button.dataset.tab;
                if (!tab) return;
                document.querySelectorAll('.tab-menu .tab-btn').forEach((btn) => btn.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach((pane) => pane.classList.remove('active'));
                button.classList.add('active');
                const target = document.getElementById(tab);
                if (target) {
                    target.classList.add('active');
                }
            });
        });

        const defaultTab = document.querySelector('.tab-menu .tab-btn.active');
        if (!defaultTab && document.querySelector('.tab-menu .tab-btn')) {
            document.querySelector('.tab-menu .tab-btn').click();
        }
    </script>
</body>
</html>
