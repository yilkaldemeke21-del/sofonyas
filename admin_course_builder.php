<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$error = '';
$success = '';

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_name VARCHAR(255) NOT NULL,
        course_code VARCHAR(50) NOT NULL UNIQUE,
        short_description TEXT DEFAULT NULL,
        description TEXT DEFAULT NULL,
        category VARCHAR(100) DEFAULT NULL,
        level VARCHAR(50) DEFAULT NULL,
        thumbnail VARCHAR(255) DEFAULT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        instructor VARCHAR(255) DEFAULT NULL,
        pdf_file VARCHAR(255) DEFAULT NULL,
        tutorial_topic VARCHAR(255) DEFAULT NULL,
        tutorial_text TEXT DEFAULT NULL,
        tutorial_image VARCHAR(255) DEFAULT NULL,
        tutorial_audio VARCHAR(255) DEFAULT NULL,
        tutorial_video VARCHAR(255) DEFAULT NULL,
        modules TEXT DEFAULT NULL,
        quiz TEXT DEFAULT NULL,
        assignment TEXT DEFAULT NULL,
        certificate_requirements TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {
}

ensureCourseColumns($pdo);

function normalizeText($value): string
{
    return preg_replace("/\r\n|\r/", "\n", (string)($value ?? ''));
}

function generateUniqueCourseCode(PDO $pdo, string $baseCode): string
{
    $baseCode = trim((string)$baseCode);
    if ($baseCode === '') {
        $baseCode = 'COURSE-' . date('YmdHis');
    }

    $candidate = strtoupper(preg_replace('/[^A-Z0-9_-]+/i', '-', $baseCode));
    $candidate = trim((string)$candidate, '-');
    if ($candidate === '') {
        $candidate = 'COURSE-' . date('YmdHis');
    }

    $suffix = 1;
    while (true) {
        $stmt = $pdo->prepare('SELECT id FROM courses WHERE course_code = :course_code LIMIT 1');
        $stmt->execute([':course_code' => $candidate]);

        if (!$stmt->fetch()) {
            return $candidate;
        }

        $candidate = $candidate . '-' . $suffix;
        $suffix++;
    }
}

function uploadMediaFile(array $file, string $folder, array $allowedExt, array $allowedMime, string &$error): ?string
{
    if (!isset($file['name']) || $file['name'] === '' || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        $error = 'ይህ ፋይል ተፈቅዶ ያልሆነ አይነት ነው።';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMime, true) && !in_array(strtok($mime, '/'), ['image', 'audio', 'video'], true)) {
        $error = 'ፋይሉ ተቀባይነት ያለው ፋይል አይደለም።';
        return null;
    }

    $uploadDir = __DIR__ . '/uploads/course_media/' . $folder;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $error = 'ፋይሉን ማከማቸት አልተሳካም።';
        return null;
    }

    return 'uploads/course_media/' . $folder . '/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_name = trim($_POST['course_name'] ?? '');
    $course_code = trim($_POST['course_code'] ?? '');
    $course_code = strtoupper($course_code);
    $short_description = normalizeText($_POST['short_description'] ?? '');
    $description = normalizeText($_POST['description'] ?? '');
    $category = normalizeText($_POST['category'] ?? '');
    $level = normalizeText($_POST['level'] ?? '');
    $thumbnail = normalizeText($_POST['thumbnail'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $instructor = normalizeText($_POST['instructor'] ?? '');
    $tutorial_topic = normalizeText($_POST['tutorial_topic'] ?? '');
    $tutorial_text = normalizeText($_POST['tutorial_text'] ?? '');
    $tutorial_image = normalizeText($_POST['tutorial_image'] ?? '');
    $tutorial_audio = normalizeText($_POST['tutorial_audio'] ?? '');
    $tutorial_video = normalizeText($_POST['tutorial_video'] ?? '');
    $pdf_file = normalizeText($_POST['pdf_file'] ?? '');
    $modules = normalizeText($_POST['modules'] ?? '');
    $builderModules = normalizeText($_POST['builder_modules'] ?? '');
    if ($builderModules !== '') {
        $modules = $builderModules;
    }

    $quiz = normalizeText($_POST['quiz'] ?? '');
    $builderQuiz = normalizeText($_POST['builder_quiz'] ?? '');
    if ($builderQuiz !== '') {
        $quiz = $builderQuiz;
    }

    $assignment = normalizeText($_POST['assignment'] ?? '');
    $builderAssignment = normalizeText($_POST['builder_assignment'] ?? '');
    if ($builderAssignment !== '') {
        $assignment = $builderAssignment;
    }

    $certificate_requirements = normalizeText($_POST['certificate_requirements'] ?? '');

    if ($course_name === '' || $course_code === '') {
        $error = 'የኮርስ ስም እና ኮድ ማስገባት አለብዎት።';
    } else {
        $existingCode = $pdo->prepare('SELECT id FROM courses WHERE course_code = :course_code LIMIT 1');
        $existingCode->execute([':course_code' => $course_code]);
        if ($existingCode->fetch()) {
            $course_code = generateUniqueCourseCode($pdo, $course_code);
            $success = 'የእርስዎ ኮርስ ኮድ ቀድሞ ነበር፣ በራስ-ሰር የተለየ ኮድ ተመርጧል: ' . safe($course_code);
        }
        $uploadedThumbnail = uploadMediaFile($_FILES['thumbnail_file'] ?? null, 'images', ['jpg', 'jpeg', 'png', 'gif', 'webp'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], $error);
        if ($uploadedThumbnail !== null) {
            $thumbnail = $uploadedThumbnail;
        }

        $uploadedPdf = uploadMediaFile($_FILES['course_pdf'] ?? null, 'pdfs', ['pdf'], ['application/pdf'], $error);
        if ($uploadedPdf !== null) {
            $pdf_file = $uploadedPdf;
        }

        $uploadedLessonImage = uploadMediaFile($_FILES['lesson_image_file'] ?? null, 'images', ['jpg', 'jpeg', 'png', 'gif', 'webp'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], $error);
        if ($uploadedLessonImage !== null) {
            $tutorial_image = $uploadedLessonImage;
        }

        $uploadedAudio = uploadMediaFile($_FILES['lesson_audio_file'] ?? null, 'audio', ['mp3', 'wav', 'ogg', 'm4a'], ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4'], $error);
        if ($uploadedAudio !== null) {
            $tutorial_audio = $uploadedAudio;
        }

        $uploadedVideo = uploadMediaFile($_FILES['lesson_video_file'] ?? null, 'video', ['mp4', 'mov', 'avi', 'webm', 'mkv'], ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm', 'video/x-matroska'], $error);
        if ($uploadedVideo !== null) {
            $tutorial_video = $uploadedVideo;
        }

        if ($error !== '') {
            // keep the form visible and show the upload validation error
        } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO courses (course_name, course_code, short_description, description, category, level, thumbnail, price, instructor, pdf_file, tutorial_topic, tutorial_text, tutorial_image, tutorial_audio, tutorial_video, modules, quiz, assignment, certificate_requirements)
                 VALUES (:course_name, :course_code, :short_description, :description, :category, :level, :thumbnail, :price, :instructor, :pdf_file, :tutorial_topic, :tutorial_text, :tutorial_image, :tutorial_audio, :tutorial_video, :modules, :quiz, :assignment, :certificate_requirements)'
            );
            $stmt->execute([
                ':course_name' => $course_name,
                ':course_code' => $course_code,
                ':short_description' => $short_description,
                ':description' => $description,
                ':category' => $category,
                ':level' => $level,
                ':thumbnail' => $thumbnail,
                ':price' => $price,
                ':instructor' => $instructor,
                ':pdf_file' => $pdf_file,
                ':tutorial_topic' => $tutorial_topic,
                ':tutorial_text' => $tutorial_text,
                ':tutorial_image' => $tutorial_image,
                ':tutorial_audio' => $tutorial_audio,
                ':tutorial_video' => $tutorial_video,
                ':modules' => $modules,
                ':quiz' => $quiz,
                ':assignment' => $assignment,
                ':certificate_requirements' => $certificate_requirements,
            ]);

            $success = 'ኮርሱ በስኬት ተፈጥሯል እና ወደ ዳታቤዝ ተመዝግቧል።';
        } catch (PDOException $e) {
            $error = 'ዳታቤዝ መጻፍ አልተሳካም: ' . $e->getMessage();
        }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>Admin Course Builder</title>
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function escapeHtml(value) {
                return String(value)
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#39;');
            }

            function renderLessonBuilderMarkup() {
                const wrapper = document.getElementById('lessonBuilderList');
                if (!wrapper) return;

                const cards = Array.from(wrapper.querySelectorAll('.builder-card'));
                const modules = cards.map((card) => {
                    const moduleTitle = card.querySelector('.module-title').value.trim();
                    const lessonRows = Array.from(card.querySelectorAll('.lesson-row'));
                    const lessons = lessonRows.map((row) => {
                        const title = row.querySelector('.lesson-title').value.trim();
                        return title ? '<li>' + escapeHtml(title) + '</li>' : '';
                    }).filter(Boolean);

                    return {
                        title: moduleTitle || 'Untitled Module',
                        lessons: lessons
                    };
                }).filter((item) => item.title || item.lessons.length);

                const markup = modules.map((module) => {
                    const lessonsMarkup = module.lessons.length ? '<ul>' + module.lessons.join('') + '</ul>' : '<p class="small">No lessons added yet.</p>';
                    return '<section class="builder-output-card"><h4>' + escapeHtml(module.title) + '</h4>' + lessonsMarkup + '</section>';
                }).join('');

                document.getElementById('builder_modules').value = markup;
            }

            function renderQuizBuilderMarkup() {
                const wrapper = document.getElementById('quizBuilderList');
                if (!wrapper) return;

                const questionCards = Array.from(wrapper.querySelectorAll('.quiz-card'));
                const items = questionCards.map((card, index) => {
                    const title = card.querySelector('.quiz-question').value.trim() || 'Question ' + (index + 1);
                    const optionA = card.querySelector('.quiz-a').value.trim();
                    const optionB = card.querySelector('.quiz-b').value.trim();
                    const optionC = card.querySelector('.quiz-c').value.trim();
                    const optionD = card.querySelector('.quiz-d').value.trim();
                    const answer = card.querySelector('.quiz-answer').value.trim();
                    const note = card.querySelector('.quiz-note').value.trim();
                    const options = [optionA, optionB, optionC, optionD].filter(Boolean);
                    const optionsMarkup = options.map((option) => '<li>' + escapeHtml(option) + '</li>').join('');
                    const answerMarkup = answer ? '<p><strong>Answer:</strong> ' + escapeHtml(answer) + '</p>' : '';
                    const noteMarkup = note ? '<p class="small">' + escapeHtml(note) + '</p>' : '';
                    return '<article class="builder-output-card"><h4>' + escapeHtml(title) + '</h4><ol>' + optionsMarkup + '</ol>' + answerMarkup + noteMarkup + '</article>';
                }).filter(Boolean);

                document.getElementById('builder_quiz').value = items.length ? items.join('') : '';
            }

            function renderAssignmentBuilderMarkup() {
                const wrapper = document.getElementById('assignmentBuilderList');
                if (!wrapper) return;

                const cards = Array.from(wrapper.querySelectorAll('.assignment-card'));
                const items = cards.map((card) => {
                    const title = card.querySelector('.assignment-title').value.trim() || 'Assignment';
                    const description = card.querySelector('.assignment-description').value.trim();
                    const dueDate = card.querySelector('.assignment-due').value.trim();
                    const points = card.querySelector('.assignment-points').value.trim();
                    const meta = [dueDate ? 'Due: ' + dueDate : '', points ? 'Points: ' + points : ''].filter(Boolean).join(' • ');
                    return '<article class="builder-output-card"><h4>' + escapeHtml(title) + '</h4><p>' + escapeHtml(description || 'Assignment instructions will appear here.') + '</p>' + (meta ? '<p class="small">' + escapeHtml(meta) + '</p>' : '') + '</article>';
                }).filter(Boolean);

                document.getElementById('builder_assignment').value = items.length ? items.join('') : '';
            }

            function addLessonCard() {
                const wrapper = document.getElementById('lessonBuilderList');
                const card = document.createElement('article');
                card.className = 'builder-card';
                card.draggable = true;
                card.innerHTML = '<div class="builder-card-header"><strong>Module</strong><button type="button" class="ghost-btn remove-card">Remove</button></div><label class="small">Module Title</label><input class="module-title" type="text" placeholder="Module title"><label class="small">Lesson Items</label><div class="lesson-list"></div><button type="button" class="ghost-btn add-lesson">+ Add Lesson</button>';
                wrapper.appendChild(card);
                addLessonRow(card.querySelector('.lesson-list'));
                bindBuilderEvents(card);
                renderLessonBuilderMarkup();
            }

            function addLessonRow(container) {
                const row = document.createElement('div');
                row.className = 'lesson-row';
                row.draggable = true;
                row.innerHTML = '<input type="text" class="lesson-title" placeholder="Lesson title or topic"><button type="button" class="ghost-btn remove-row">Remove</button>';
                container.appendChild(row);
                bindBuilderEvents(row);
                renderLessonBuilderMarkup();
            }

            function addQuizCard() {
                const wrapper = document.getElementById('quizBuilderList');
                const card = document.createElement('article');
                card.className = 'quiz-card';
                card.innerHTML = '<div class="builder-card-header"><strong>Question</strong><button type="button" class="ghost-btn remove-card">Remove</button></div><input type="text" class="quiz-question" placeholder="Question title"><input type="text" class="quiz-a" placeholder="Option A"><input type="text" class="quiz-b" placeholder="Option B"><input type="text" class="quiz-c" placeholder="Option C"><input type="text" class="quiz-d" placeholder="Option D"><input type="text" class="quiz-answer" placeholder="Correct answer"><textarea class="quiz-note" placeholder="Hint or explanation"></textarea>';
                wrapper.appendChild(card);
                bindBuilderEvents(card);
                renderQuizBuilderMarkup();
            }

            function addAssignmentCard() {
                const wrapper = document.getElementById('assignmentBuilderList');
                const card = document.createElement('article');
                card.className = 'assignment-card';
                card.innerHTML = '<div class="builder-card-header"><strong>Assignment</strong><button type="button" class="ghost-btn remove-card">Remove</button></div><input type="text" class="assignment-title" placeholder="Assignment title"><textarea class="assignment-description" placeholder="Assignment instructions, objectives, and rubric"></textarea><input type="text" class="assignment-due" placeholder="Due date"><input type="text" class="assignment-points" placeholder="Points or weight"></textarea>';
                wrapper.appendChild(card);
                bindBuilderEvents(card);
                renderAssignmentBuilderMarkup();
            }

            let draggedLessonCard = null;

            function bindBuilderEvents(node) {
                if (!node) return;

                node.querySelectorAll('.remove-card').forEach(function (button) {
                    button.addEventListener('click', function () {
                        node.remove();
                        renderLessonBuilderMarkup();
                        renderQuizBuilderMarkup();
                        renderAssignmentBuilderMarkup();
                    });
                });

                node.querySelectorAll('.remove-row').forEach(function (button) {
                    button.addEventListener('click', function () {
                        button.closest('.lesson-row').remove();
                        renderLessonBuilderMarkup();
                    });
                });

                node.querySelectorAll('.add-lesson').forEach(function (button) {
                    button.addEventListener('click', function () {
                        addLessonRow(button.closest('.builder-card').querySelector('.lesson-list'));
                    });
                });

                node.querySelectorAll('input, textarea').forEach(function (field) {
                    field.addEventListener('input', function () {
                        renderLessonBuilderMarkup();
                        renderQuizBuilderMarkup();
                        renderAssignmentBuilderMarkup();
                    });
                });

                if (node.classList.contains('builder-card')) {
                    node.addEventListener('dragstart', function (event) {
                        draggedLessonCard = node;
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', 'lesson-card');
                        node.classList.add('dragging');
                    });
                    node.addEventListener('dragend', function () {
                        draggedLessonCard = null;
                        node.classList.remove('dragging');
                    });
                    node.addEventListener('dragover', function (event) {
                        if (draggedLessonCard && draggedLessonCard !== node) {
                            event.preventDefault();
                            event.dataTransfer.dropEffect = 'move';
                        }
                    });
                    node.addEventListener('drop', function (event) {
                        event.preventDefault();
                        if (!draggedLessonCard || draggedLessonCard === node) return;
                        const list = node.parentElement;
                        if (!list) return;
                        const draggedIndex = Array.from(list.children).indexOf(draggedLessonCard);
                        const targetIndex = Array.from(list.children).indexOf(node);
                        if (draggedIndex < targetIndex) {
                            list.insertBefore(draggedLessonCard, node.nextSibling);
                        } else {
                            list.insertBefore(draggedLessonCard, node);
                        }
                        draggedLessonCard = null;
                        renderLessonBuilderMarkup();
                    });
                }
            }

            document.getElementById('addLessonModuleBtn')?.addEventListener('click', addLessonCard);
            document.getElementById('addQuizItemBtn')?.addEventListener('click', addQuizCard);
            document.getElementById('addAssignmentItemBtn')?.addEventListener('click', addAssignmentCard);

            document.querySelector('form')?.addEventListener('submit', function () {
                renderLessonBuilderMarkup();
                renderQuizBuilderMarkup();
                renderAssignmentBuilderMarkup();
            });

            addLessonCard();
            addQuizCard();
            addAssignmentCard();

            function initRichEditors() {
                if (window.tinymce) {
                    tinymce.init({
                        selector: '.rich-editor',
                        plugins: 'advlist autolink link image lists table wordcount code',
                        toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link image table | removeformat',
                        menubar: false,
                        branding: false,
                        promotion: false,
                        height: 180,
                        forced_root_block: 'p',
                        setup: function (editor) {
                            editor.on('change', function () {
                                editor.save();
                            });
                        }
                    });
                } else {
                    document.querySelectorAll('.rich-editor').forEach(function (field) {
                        field.setAttribute('placeholder', 'Use bold/italic formatting from the toolbar when the editor loads.');
                    });
                }
            }

            initRichEditors();
        });
    </script>
    <style>
        * { box-sizing: border-box; font-family: Arial, sans-serif; }
        body { margin: 0; background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 45%, #eef2ff 100%); color: #111827; }
        .nav { background: linear-gradient(135deg, #111827, #1d4ed8); color: #fff; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; }
        .nav a { color: #fff; text-decoration: none; font-weight: 700; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 20px; }
        .panel { background: rgba(255,255,255,0.96); border-radius: 18px; box-shadow: 0 18px 36px rgba(15,23,42,0.10); border: 1px solid #dbeafe; padding: 22px; margin-bottom: 18px; }
        .chip-row { display: flex; flex-wrap: wrap; gap: 10px; margin: 12px 0 18px; }
        .chip { display: inline-block; padding: 8px 10px; border-radius: 999px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; text-decoration: none; font-weight: 700; font-size: 13px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; }
        .field-block { display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px; }
        .field-block label { display: block; font-weight: 700; color: #334155; margin-bottom: 0; }
        .field-block input, .field-block select, .field-block textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 10px; padding: 10px 12px; font-size: 14px; margin-bottom: 0; background: #fff; }
        textarea { min-height: 92px; }
        button { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; border: none; border-radius: 10px; padding: 11px 14px; font-weight: 700; cursor: pointer; box-shadow: 0 10px 18px rgba(37,99,235,0.18); }
        button:hover { opacity: 0.96; }
        .alert { padding: 12px; border-radius: 10px; margin-bottom: 14px; }
        .alert.error { background: #fee2e2; color: #991b1b; }
        .alert.success { background: #dcfce7; color: #166534; }
        .small { color: #475569; font-size: 13px; }
        .hero { display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; }
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 8px 10px; border-radius: 999px; background: #ecfdf5; color: #166534; border: 1px solid #a7f3d0; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; }
        .publish-box { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; background: linear-gradient(135deg, #eff6ff, #eef2ff); border: 1px solid #bfdbfe; border-radius: 14px; padding: 14px; margin-top: 8px; }
        .publish-box strong { color: #1e3a8a; }
        .publish-btn { background: linear-gradient(135deg, #16a34a, #15803d); box-shadow: 0 12px 24px rgba(22,163,74,0.22); border-radius: 12px; padding: 12px 16px; }
        .publish-btn:hover { opacity: 0.98; transform: translateY(-1px); }
        .toolbar-note { color: #1e3a8a; font-size: 13px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 8px 10px; margin-bottom: 10px; }
        .builder-stack { display: grid; gap: 12px; }
        .builder-card, .quiz-card, .assignment-card { border: 1px solid #bfdbfe; background: linear-gradient(135deg, #f8fbff, #fff); border-radius: 14px; padding: 12px; box-shadow: 0 8px 18px rgba(148,163,184,0.12); }
        .builder-card-header { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 8px; }
        .ghost-btn { border: 1px solid #cbd5e1; background: #fff; color: #334155; border-radius: 8px; padding: 6px 10px; cursor: pointer; font-size: 12px; font-weight: 700; }
        .lesson-row { display: flex; gap: 8px; align-items: center; margin-top: 8px; }
        .lesson-row input, .quiz-card input, .quiz-card textarea, .assignment-card input, .assignment-card textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 10px; padding: 8px 10px; font-size: 13px; margin-bottom: 6px; }
        .builder-output-card { border: 1px solid #dbeafe; background: #f8fbff; border-radius: 12px; padding: 10px; margin-top: 8px; }
        .builder-output-card h4 { margin: 0 0 6px; font-size: 15px; color: #1e3a8a; }
        .builder-output-card ul, .builder-output-card ol { margin: 0 0 6px 16px; padding-left: 0; }
        .dragging { opacity: 0.5; }
        .rich-editor h1, .rich-editor h2 { font-size: 1.02rem; line-height: 1.35; margin: 0.35em 0; }
        .rich-editor ul, .rich-editor ol { padding-left: 18px; margin: 8px 0 10px; }
        .rich-editor li { margin-bottom: 6px; }
        .rich-editor p { margin: 0 0 8px; }
    </style>
</head>
<body>
<div class="nav">
    <div>
        <strong>Admin Course Builder</strong>
        <div class="small">Create the course, add modules, lessons, topics, content, quizzes, and publish to the database.</div>
    </div>
    <a href="admin_dashboard.php">← ወደ ዳሽቦርድ</a>
</div>
<div class="wrap">
    <div class="panel">
        <div class="hero">
            <div>
                <h2 style="margin: 0 0 6px;">Course Builder Workflow</h2>
                <p class="small">Create the course, add lesson content, and publish it to the database. Use the rich-text toolbar for bold, italic, and formatted content.</p>
            </div>
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <span class="badge">Bold • Italic • Ready</span>
                <a class="chip" href="#publish">Publish Course</a>
            </div>
        </div>
        <div class="chip-row">
            <a class="chip" href="#course">Add Course</a>
            <a class="chip" href="#module">Add Module</a>
            <a class="chip" href="#lesson">Add Lesson</a>
            <a class="chip" href="#topic">Add Topic</a>
            <a class="chip" href="#content">Add Content</a>
            <a class="chip" href="#quiz">Add Quiz</a>
        </div>

        <?php if ($error !== ''): ?><div class="alert error"><?php echo safe($error); ?></div><?php endif; ?>
        <?php if ($success !== ''): ?><div class="alert success"><?php echo safe($success); ?></div><?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <div class="grid">
                <div>
                    <div class="toolbar-note">Tip: Use the bold and italic buttons in the editor toolbar to style lesson notes, topics, and quiz explanations.</div>
                    <div class="field-block">
                        <label for="course_name">Course Name *</label>
                        <input id="course_name" name="course_name" required placeholder="Example: Faith Foundations">
                    </div>
                    <div class="field-block">
                        <label for="course_code">Course Code *</label>
                        <input id="course_code" name="course_code" required placeholder="Example: REL-101">
                    </div>
                    <div class="field-block">
                        <label for="price">Price (ETB)</label>
                        <input id="price" name="price" type="number" step="0.01" value="0.00">
                    </div>
                    <div class="field-block">
                        <label for="instructor">Instructor</label>
                        <input id="instructor" name="instructor" placeholder="Instructor name">
                    </div>
                </div>
                <div>
                    <div class="field-block">
                        <label for="category">Category</label>
                        <input id="category" name="category" placeholder="Bible, Leadership, Theology">
                    </div>
                    <div class="field-block">
                        <label for="level">Level</label>
                        <select id="level" name="level">
                            <option value="Beginner">Beginner</option>
                            <option value="Intermediate">Intermediate</option>
                            <option value="Advanced">Advanced</option>
                        </select>
                    </div>
                    <div class="field-block">
                        <label for="thumbnail_file">Thumbnail Image (upload)</label>
                        <input id="thumbnail_file" name="thumbnail_file" type="file" accept="image/*">
                    </div>
                    <div class="field-block">
                        <label for="thumbnail">Thumbnail URL (optional)</label>
                        <input id="thumbnail" name="thumbnail" placeholder="https://.../thumbnail.jpg">
                    </div>
                    <div class="field-block">
                        <label for="course_pdf">PDF File (upload)</label>
                        <input id="course_pdf" name="course_pdf" type="file" accept="application/pdf">
                    </div>
                    <div class="field-block">
                        <label for="pdf_file">PDF URL (optional)</label>
                        <input id="pdf_file" name="pdf_file" placeholder="uploads/course_pdfs/example.pdf">
                    </div>
                </div>
            </div>

            <div class="panel" id="module" style="padding:16px; margin-top: 14px;">
                <h3 style="margin-top:0;">1. Drag & Drop Lesson Builder</h3>
                <p class="small">Build lesson modules visually, reorder them by dragging, and save the result as the course outline.</p>
                <div class="builder-stack" id="lessonBuilderList"></div>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">
                    <button type="button" id="addLessonModuleBtn" class="ghost-btn" style="background:#eff6ff; border-color:#bfdbfe;">+ Add Module</button>
                    <span class="small">Tip: Drag lesson cards to reorder the learning flow before you publish.</span>
                </div>
                <textarea id="builder_modules" name="builder_modules" hidden></textarea>
                <div class="field-block" style="margin-top:12px;">
                    <label for="modules">Optional Rich Text Outline</label>
                    <textarea id="modules" name="modules" class="rich-editor" placeholder="Alternative outline text if you prefer the classic editor format."></textarea>
                </div>
                <p class="small">The builder output is saved automatically when you publish.</p>
            </div>

            <div class="panel" id="lesson" style="padding:16px;">
                <h3 style="margin-top:0;">2. Add Lesson</h3>
                <div class="field-block">
                    <label for="tutorial_topic">Lesson Title</label>
                    <input id="tutorial_topic" name="tutorial_topic" placeholder="Lesson 1 - What is Spiritual Growth?">
                </div>
                <div class="field-block">
                    <label for="tutorial_text">Lesson Content / Explanation</label>
                    <textarea id="tutorial_text" name="tutorial_text" class="rich-editor" placeholder="Describe the lesson topic, key points, and practical instruction here."></textarea>
                </div>
                <div class="field-block">
                    <label for="lesson_image_file">Lesson Image (upload)</label>
                    <input id="lesson_image_file" name="lesson_image_file" type="file" accept="image/*">
                </div>
                <div class="field-block">
                    <label for="tutorial_image">Lesson Image URL (optional)</label>
                    <input id="tutorial_image" name="tutorial_image" placeholder="https://.../lesson.jpg">
                </div>
                <div class="field-block">
                    <label for="lesson_audio_file">Lesson Audio (upload)</label>
                    <input id="lesson_audio_file" name="lesson_audio_file" type="file" accept="audio/*">
                </div>
                <div class="field-block">
                    <label for="tutorial_audio">Lesson Audio URL (optional)</label>
                    <input id="tutorial_audio" name="tutorial_audio" placeholder="https://.../lesson.mp3">
                </div>
                <div class="field-block">
                    <label for="lesson_video_file">Lesson Video (upload)</label>
                    <input id="lesson_video_file" name="lesson_video_file" type="file" accept="video/*">
                </div>
                <div class="field-block">
                    <label for="tutorial_video">Lesson Video URL (optional)</label>
                    <input id="tutorial_video" name="tutorial_video" placeholder="https://.../lesson.mp4 or YouTube link">
                </div>
            </div>

            <div class="panel" id="topic" style="padding:16px;">
                <h3 style="margin-top:0;">3. Add Topic</h3>
                <div class="field-block">
                    <label for="short_description">Short Topic Summary</label>
                    <textarea id="short_description" name="short_description" class="rich-editor" placeholder="A short summary of the topic or lesson focus."></textarea>
                </div>
                <div class="field-block">
                    <label for="description">Full Topic / Content Notes</label>
                    <textarea id="description" name="description" class="rich-editor" placeholder="Write the full paragraph or topic explanation here."></textarea>
                </div>
            </div>

            <div class="panel" id="content" style="padding:16px;">
                <h3 style="margin-top:0;">4. Assignment Builder</h3>
                <p class="small">Create assignment tasks and rubric notes in a structured format that can be published directly to the course.</p>
                <div class="builder-stack" id="assignmentBuilderList"></div>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">
                    <button type="button" id="addAssignmentItemBtn" class="ghost-btn" style="background:#eff6ff; border-color:#bfdbfe;">+ Add Assignment</button>
                </div>
                <textarea id="builder_assignment" name="builder_assignment" hidden></textarea>
                <div class="field-block" style="margin-top:12px;">
                    <label for="assignment">Optional Rich Text Assignment Notes</label>
                    <textarea id="assignment" name="assignment" class="rich-editor" placeholder="Add lesson notes, paragraph text, assignment instructions, or learner activities here."></textarea>
                </div>
            </div>

            <div class="panel" id="quiz" style="padding:16px;">
                <h3 style="margin-top:0;">5. Quiz Builder</h3>
                <p class="small">Add quiz questions with options, answers, and hints. The structured quiz is saved automatically when you publish.</p>
                <div class="builder-stack" id="quizBuilderList"></div>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">
                    <button type="button" id="addQuizItemBtn" class="ghost-btn" style="background:#eff6ff; border-color:#bfdbfe;">+ ጥያቄ ጨምር</button>
                </div>
                <textarea id="builder_quiz" name="builder_quiz" hidden></textarea>
                <div class="field-block" style="margin-top:12px;">
                    <label for="quiz">Optional Rich Text Quiz Questions</label>
                    <textarea id="quiz" name="quiz" class="rich-editor" placeholder="1. What is the main topic?&#10;2. Which action is most important?&#10;3. What should learners practice?"></textarea>
                </div>
                <div class="field-block">
                    <label for="certificate_requirements">Certificate Requirements</label>
                    <textarea id="certificate_requirements" name="certificate_requirements" class="rich-editor" placeholder="80% score&#10;Complete quiz&#10;Submit assignment"></textarea>
                </div>
            </div>

            <div class="panel" id="publish" style="padding:16px;">
                <h3 style="margin-top:0;">6.አረጋግጥ</h3>
                <div class="publish-box">
                    <div>
                        <strong>Ready to save this course?</strong>
                        <p class="small" style="margin: 4px 0 0;">እርስዎ ኮርስ አትም የሚለዉን ሲጫኑ ሁሉም ሞጂሎች፣ኮርሶች፣ሌሰኖች እና አሳይመንት ማብራሪያወች ዳታቤዙ ላይ ይቀመጣሉ</p>
                    </div>
                    <button class="publish-btn" type="submit">ኮርስ አትም</button>
                </div>
            </div>
        </form>
    </div>
</div>
</body>
</html>
