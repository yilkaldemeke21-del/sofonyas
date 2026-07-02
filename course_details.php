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
        <?php if ($isEnrolled): ?>
            <p style="color:#166534; font-weight:700;">Status: Enrolled</p>
        <?php endif; ?>
        <form method="post" style="display:inline-block; margin:8px 0 12px;">
            <input type="hidden" name="enroll_course_id" value="<?php echo (int)$courseId; ?>">
            <button class="button" type="submit" <?php echo $isEnrolled ? 'disabled style="opacity:.6;cursor:not-allowed;"' : ''; ?>><?php echo $isEnrolled ? 'Enrolled' : 'Enroll Now'; ?></button>
        </form>
        <?php if (!empty($course['pdf_file'])): ?>
            <p><a class="button secondary" href="<?php echo htmlspecialchars($course['pdf_file'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Open PDF</a></p>
        <?php endif; ?>

        <div class="card" style="margin-top: 20px;">
            <h3>AI Study Tools</h3>
            <p>Use smart study support to generate a study guide or a short quiz for this course.</p>
            <?php if (isset($_SESSION['student_id'])): ?>
                <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:12px;">
                    <button class="button" type="button" id="generateStudyGuideButton">Generate Study Guide</button>
                    <button class="button secondary" type="button" id="generateQuizButton">Generate Quiz</button>
                </div>
                <div id="aiResponseMessage" style="margin-top:16px; display:none; padding:12px 14px; border-radius:12px; background:#eff6ff; color:#0f172a; border:1px solid #c7d2fe;"></div>
                <div id="aiStudyGuidePanel" style="display:none; margin-top:16px;"></div>
                <div id="aiQuizPanel" style="display:none; margin-top:16px;"></div>
            <?php else: ?>
                <p class="muted">እባኮትን እንደ ተማሪ ይግቡ እና ከዚያ በኋላ AI ማስተዋወቂያዎችን ይጠቀሙ።</p>
            <?php endif; ?>
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
                        <div class="rich-content"><?php echo renderRichText($note['description'] ?? ''); ?></div>
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
                        <?php if ($isEnrolled): ?>
                            <div><a href="course_content.php?course_id=<?php echo (int)$courseId; ?>&lesson_id=<?php echo (int)$lesson['id']; ?>">Open Lesson</a></div>
                        <?php else: ?>
                            <div style="color:#64748b;">Enroll to access this lesson.</div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <script>
        (function () {
            const courseId = <?php echo (int)$courseId; ?>;
            const showMessage = (message) => {
                const responseBox = document.getElementById('aiResponseMessage');
                if (!responseBox) return;
                responseBox.style.display = 'block';
                responseBox.textContent = message;
            };

            const renderStudyGuide = (guide) => {
                const panel = document.getElementById('aiStudyGuidePanel');
                const responseBox = document.getElementById('aiResponseMessage');
                if (!panel || !responseBox) return;
                panel.style.display = 'block';
                responseBox.style.display = 'none';

                const objectives = guide.learning_objectives.map(item => `<li>${item}</li>`).join('');
                const plan = guide.recommended_plan.map(item => `<li>${item}</li>`).join('');
                const topics = guide.key_topics.map(item => `<li>${item}</li>`).join('');
                const checklist = guide.revision_checklist.map(item => `<li>${item}</li>`).join('');

                panel.innerHTML = `
                    <div style="padding:16px; border-radius:16px; border:1px solid #c7d2fe; background:#f8fbff;">
                        <h3 style="margin-top:0;">${guide.title}</h3>
                        <p>${guide.introduction}</p>
                        <h4>Course summary</h4>
                        <p>${guide.course_summary}</p>
                        <h4>Learning objectives</h4>
                        <ul>${objectives}</ul>
                        <h4>Recommended study plan</h4>
                        <ul>${plan}</ul>
                        <h4>Key topics</h4>
                        <ul>${topics}</ul>
                        <h4>Revision checklist</h4>
                        <ul>${checklist}</ul>
                    </div>
                `;
            };

            const renderQuiz = (quiz) => {
                const panel = document.getElementById('aiQuizPanel');
                const responseBox = document.getElementById('aiResponseMessage');
                if (!panel || !responseBox) return;
                panel.style.display = 'block';
                responseBox.style.display = 'none';

                const questions = quiz.questions.map((item, index) => {
                    const options = item.options.map(option => `<li>${option}</li>`).join('');
                    return `
                        <div style="margin-bottom:18px; padding:16px; border-radius:14px; border:1px solid #e0e7ff; background:#ffffff;">
                            <h4>Question ${index + 1}</h4>
                            <p>${item.question}</p>
                            <ol>${options}</ol>
                        </div>
                    `;
                }).join('');

                panel.innerHTML = `
                    <div style="padding:16px; border-radius:16px; border:1px solid #c7d2fe; background:#f8fbff;">
                        <h3 style="margin-top:0;">Quiz for ${quiz.course_name}</h3>
                        ${questions}
                    </div>
                `;
            };

            const apiCall = (action, onSuccess) => {
                showMessage('Loading AI content...');
                fetch(`ai_tools.php?action=${encodeURIComponent(action)}&course_id=${encodeURIComponent(courseId)}`, {
                    credentials: 'same-origin',
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (!data.success) {
                            showMessage(data.message || 'Failed to generate AI content.');
                            return;
                        }
                        onSuccess(data[action === 'generate_study_guide' ? 'study_guide' : 'quiz']);
                    })
                    .catch(() => {
                        showMessage('Unable to connect to AI tools.');
                    });
            };

            const studyGuideButton = document.getElementById('generateStudyGuideButton');
            const quizButton = document.getElementById('generateQuizButton');
            if (studyGuideButton) {
                studyGuideButton.addEventListener('click', () => apiCall('generate_study_guide', renderStudyGuide));
            }
            if (quizButton) {
                quizButton.addEventListener('click', () => apiCall('generate_quiz', renderQuiz));
            }
        })();
    </script>
</body>
</html>
