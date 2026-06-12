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
    $quiz = normalizeText($_POST['quiz'] ?? '');
    $assignment = normalizeText($_POST['assignment'] ?? '');
    $certificate_requirements = normalizeText($_POST['certificate_requirements'] ?? '');

    if ($course_name === '' || $course_code === '') {
        $error = 'የኮርስ ስም እና ኮድ ማስገባት አለብዎት።';
    } else {
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
            if (window.tinymce) {
                tinymce.init({
                    selector: '.rich-editor',
                    plugins: 'advlist autolink link image lists table wordcount code',
                    toolbar: 'undo redo | blocks | bold italic | bullist numlist | link image table | removeformat',
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
            }
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
        label { display: block; font-weight: 700; color: #334155; margin-bottom: 6px; }
        input, select, textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 10px; padding: 10px 12px; font-size: 14px; margin-bottom: 12px; background: #fff; }
        textarea { min-height: 92px; }
        button { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; border: none; border-radius: 10px; padding: 11px 14px; font-weight: 700; cursor: pointer; box-shadow: 0 10px 18px rgba(37,99,235,0.18); }
        button:hover { opacity: 0.96; }
        .alert { padding: 12px; border-radius: 10px; margin-bottom: 14px; }
        .alert.error { background: #fee2e2; color: #991b1b; }
        .alert.success { background: #dcfce7; color: #166534; }
        .small { color: #475569; font-size: 13px; }
        .hero { display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; }
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
                <p class="small">This page replaces the placeholder admin actions with a real course-creation flow that saves into the database.</p>
            </div>
            <a class="chip" href="#publish">Publish Course</a>
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
                    <label for="course_name">Course Name *</label>
                    <input id="course_name" name="course_name" required placeholder="Example: Faith Foundations">
                    <label for="course_code">Course Code *</label>
                    <input id="course_code" name="course_code" required placeholder="Example: REL-101">
                    <label for="price">Price (ETB)</label>
                    <input id="price" name="price" type="number" step="0.01" value="0.00">
                    <label for="instructor">Instructor</label>
                    <input id="instructor" name="instructor" placeholder="Instructor name">
                </div>
                <div>
                    <label for="category">Category</label>
                    <input id="category" name="category" placeholder="Bible, Leadership, Theology">
                    <label for="level">Level</label>
                    <select id="level" name="level">
                        <option value="Beginner">Beginner</option>
                        <option value="Intermediate">Intermediate</option>
                        <option value="Advanced">Advanced</option>
                    </select>
                    <label for="thumbnail_file">Thumbnail Image (upload)</label>
                    <input id="thumbnail_file" name="thumbnail_file" type="file" accept="image/*">
                    <label for="thumbnail">Thumbnail URL (optional)</label>
                    <input id="thumbnail" name="thumbnail" placeholder="https://.../thumbnail.jpg">
                    <label for="course_pdf">PDF File (upload)</label>
                    <input id="course_pdf" name="course_pdf" type="file" accept="application/pdf">
                    <label for="pdf_file">PDF URL (optional)</label>
                    <input id="pdf_file" name="pdf_file" placeholder="uploads/course_pdfs/example.pdf">
                </div>
            </div>

            <div class="panel" id="module" style="padding:16px; margin-top: 14px;">
                <h3 style="margin-top:0;">1. Add Module</h3>
                <label for="modules">Module / Course Outline</label>
                <textarea id="modules" name="modules" class="rich-editor" placeholder="Module 1: Introduction&#10;Module 2: Core Concepts&#10;Module 3: Practice and Review"></textarea>
                <p class="small">This is saved into the course record as the module outline.</p>
            </div>

            <div class="panel" id="lesson" style="padding:16px;">
                <h3 style="margin-top:0;">2. Add Lesson</h3>
                <label for="tutorial_topic">Lesson Title</label>
                <input id="tutorial_topic" name="tutorial_topic" placeholder="Lesson 1 - What is Spiritual Growth?">
                <label for="tutorial_text">Lesson Content / Explanation</label>
                <textarea id="tutorial_text" name="tutorial_text" class="rich-editor" placeholder="Describe the lesson topic, key points, and practical instruction here."></textarea>
                <label for="lesson_image_file">Lesson Image (upload)</label>
                <input id="lesson_image_file" name="lesson_image_file" type="file" accept="image/*">
                <label for="tutorial_image">Lesson Image URL (optional)</label>
                <input id="tutorial_image" name="tutorial_image" placeholder="https://.../lesson.jpg">
                <label for="lesson_audio_file">Lesson Audio (upload)</label>
                <input id="lesson_audio_file" name="lesson_audio_file" type="file" accept="audio/*">
                <label for="tutorial_audio">Lesson Audio URL (optional)</label>
                <input id="tutorial_audio" name="tutorial_audio" placeholder="https://.../lesson.mp3">
                <label for="lesson_video_file">Lesson Video (upload)</label>
                <input id="lesson_video_file" name="lesson_video_file" type="file" accept="video/*">
                <label for="tutorial_video">Lesson Video URL (optional)</label>
                <input id="tutorial_video" name="tutorial_video" placeholder="https://.../lesson.mp4 or YouTube link">
            </div>

            <div class="panel" id="topic" style="padding:16px;">
                <h3 style="margin-top:0;">3. Add Topic</h3>
                <label for="short_description">Short Topic Summary</label>
                <textarea id="short_description" name="short_description" class="rich-editor" placeholder="A short summary of the topic or lesson focus."></textarea>
                <label for="description">Full Topic / Content Notes</label>
                <textarea id="description" name="description" class="rich-editor" placeholder="Write the full paragraph or topic explanation here."></textarea>
            </div>

            <div class="panel" id="content" style="padding:16px;">
                <h3 style="margin-top:0;">4. Add Paragraph / Content</h3>
                <label for="assignment">Content / Paragraph / Assignment Notes</label>
                <textarea id="assignment" name="assignment" class="rich-editor" placeholder="Add lesson notes, paragraph text, assignment instructions, or learner activities here."></textarea>
            </div>

            <div class="panel" id="quiz" style="padding:16px;">
                <h3 style="margin-top:0;">5. Add Quiz</h3>
                <label for="quiz">Quiz Questions</label>
                <textarea id="quiz" name="quiz" class="rich-editor" placeholder="1. What is the main topic?&#10;2. Which action is most important?&#10;3. What should learners practice?"></textarea>
                <label for="certificate_requirements">Certificate Requirements</label>
                <textarea id="certificate_requirements" name="certificate_requirements" class="rich-editor" placeholder="80% score&#10;Complete quiz&#10;Submit assignment"></textarea>
            </div>

            <div class="panel" id="publish" style="padding:16px;">
                <h3 style="margin-top:0;">6. Publish</h3>
                <p class="small">When you click Publish, the course and all module/lesson/content/quiz details are saved to the database.</p>
                <button type="submit">Publish Course</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
