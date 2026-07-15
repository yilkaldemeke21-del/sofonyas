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

if (!$course) {
    header('Location: courses.php');
    exit;
}

if (!isStudentEnrolled($pdo, (string)$_SESSION['student_id'], $courseId)) {
    header('Location: course_details.php?id=' . $courseId);
    exit;
}

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
    <style>
        body { font-family: Arial, sans-serif; background:#f5f7fa; color:#1f2937; margin:0; overflow-x:hidden; }
        nav ul { display:flex; gap:12px; list-style:none; padding:20px; background:#ffffff; margin:0; flex-wrap:wrap; }
        nav a { color:#334155; text-decoration:none; font-weight:700; }
        .card { background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; box-shadow:0 12px 30px rgba(15,23,42,0.08); padding:20px; margin:24px auto 0; max-width: 1100px; }
        .lesson-title { margin:0 0 10px; text-align:center; font-size: clamp(1.35rem, 2vw, 2rem); line-height:1.3; word-break:break-word; overflow-wrap:anywhere; }
        .lesson-subtitle { margin:0 0 14px; text-align:center; color:#475569; word-break:break-word; overflow-wrap:anywhere; }
        .lesson-content { word-break:break-word; overflow-wrap:anywhere; line-height:1.75; }
        .lesson-content h1, .lesson-content h2, .lesson-content h3, .lesson-content h4, .lesson-content p, .lesson-content li { overflow-wrap:anywhere; word-break:break-word; }
        .lesson-actions { display:flex; flex-wrap:wrap; gap:12px; margin:18px 0 10px; }
        .button, .button.secondary { display:inline-flex; align-items:center; justify-content:center; padding:12px 16px; border-radius:12px; text-decoration:none; font-weight:700; border:none; cursor:pointer; }
        .button { background:#2563eb; color:white; }
        .button.secondary { background:#e2e8f0; color:#1f2937; }
        .lesson-notes-box { margin-top:24px; padding:18px; background:#f8fafc; border:1px solid #d1d5db; border-radius:16px; }
        .lesson-notes-box h3 { margin-bottom:12px; text-align:center; }
        .lesson-notes-box p { margin-bottom:16px; color:#475569; }
        .lesson-notes-actions { display:flex; flex-wrap:wrap; gap:12px; margin-bottom:16px; }
        @media (max-width: 860px) { nav ul { justify-content:center; } .card { padding:16px; } .lesson-title, .lesson-notes-box h3 { text-align:center; } .lesson-actions, .lesson-notes-actions { justify-content:center; } }
    </style>
</head>
<body>
    <nav>
        <ul>
            <li><a href="student_dashboard.php">Dashboard</a></li>
            <li><a href="courses.php">Courses</a></li>
        </ul>
    </nav>

    <section class="card">
        <h2 class="lesson-title"><?php echo htmlspecialchars($lessonTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="lesson-subtitle"><strong>Course:</strong> <?php echo htmlspecialchars($course['course_name'] ?? 'Course', ENT_QUOTES, 'UTF-8'); ?></p>
        <div id="lessonText" class="lesson-content"><?php echo $lessonContent; ?></div>
        <?php if (!empty($course['tutorial_video'])): ?>
            <p><a class="button" href="<?php echo htmlspecialchars($course['tutorial_video'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Watch Video</a></p>
        <?php endif; ?>
        <div class="lesson-notes-box">
            <h3>🤖 AI Notes Generator & Voice Teacher</h3>
            <p>ከዚህ ትምህርት የተለያዩ አጭር ማስታወሻዎችን ይፈጥሩ እና ድምጽ በመጠቀም ይማሩ።</p>
            <div class="lesson-notes-actions">
                <button id="generateNotesButton" class="button" type="button">Generate Notes</button>
                <button id="readAloudButton" class="button secondary" type="button">Play Voice Teacher</button>
            </div>
            <div id="voiceStatus" style="color:#334155; margin-bottom: 16px;">Ready to read your lesson aloud.</div>
            <div id="aiNotesContainer" style="display:none;">
                <h4 style="margin-bottom: 12px;">AI Generated Notes</h4>
                <ul id="aiNotesList" style="list-style: disc; padding-left: 20px; color:#1f2937;"></ul>
            </div>
        </div>
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
    <script>
        (function () {
            const lessonTextEl = document.getElementById('lessonText');
            const generateNotesButton = document.getElementById('generateNotesButton');
            const readAloudButton = document.getElementById('readAloudButton');
            const aiNotesContainer = document.getElementById('aiNotesContainer');
            const aiNotesList = document.getElementById('aiNotesList');
            const voiceStatus = document.getElementById('voiceStatus');
            const lessonText = lessonTextEl ? lessonTextEl.innerText.trim() : '';

            function splitLessonText(text) {
                const normalized = text.replace(/\s+/g, ' ').trim();
                if (!normalized) {
                    return [];
                }
                return normalized.split(/(?<=[.!?።፥፣])\s+/u).map(sentence => sentence.trim()).filter(Boolean);
            }

            function generateNotes() {
                const sentences = splitLessonText(lessonText);
                const notes = [];
                if (sentences.length === 0) {
                    notes.push('No lesson content available to summarize.');
                } else {
                    for (let i = 0; i < sentences.length && notes.length < 4; i += 1) {
                        if (sentences[i].length > 20) {
                            notes.push(sentences[i]);
                        }
                    }
                    if (notes.length === 0) {
                        notes.push(...sentences.slice(0, 4));
                    }
                }
                aiNotesList.innerHTML = notes.map(note => '<li style="margin-bottom: 10px; line-height: 1.6;">' + note + '</li>').join('');
                aiNotesContainer.style.display = 'block';
                voiceStatus.textContent = 'AI notes generated from the current lesson.';
            }

            function speakLesson() {
                if (!window.speechSynthesis) {
                    voiceStatus.textContent = 'Speech synthesis is not supported by this browser.';
                    return;
                }

                if (speechSynthesis.speaking) {
                    speechSynthesis.cancel();
                    readAloudButton.textContent = 'Play Voice Teacher';
                    voiceStatus.textContent = 'Voice reading stopped.';
                    return;
                }

                if (!lessonText) {
                    voiceStatus.textContent = 'Lesson text is not available for speech.';
                    return;
                }

                const utterance = new SpeechSynthesisUtterance(lessonText);
                utterance.lang = 'am-ET';
                utterance.rate = 0.95;
                utterance.pitch = 1.0;
                utterance.onstart = function () {
                    readAloudButton.textContent = 'Stop Voice Teacher';
                    voiceStatus.textContent = 'Playing lesson content aloud...';
                };
                utterance.onend = function () {
                    readAloudButton.textContent = 'Play Voice Teacher';
                    voiceStatus.textContent = 'Voice reading finished.';
                };
                utterance.onerror = function () {
                    readAloudButton.textContent = 'Play Voice Teacher';
                    voiceStatus.textContent = 'Failed to read lesson content.';
                };
                speechSynthesis.speak(utterance);
            }

            if (generateNotesButton) {
                generateNotesButton.addEventListener('click', generateNotes);
            }
            if (readAloudButton) {
                readAloudButton.addEventListener('click', speakLesson);
            }
        }());
    </script>
</body>
</html>
