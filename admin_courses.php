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
        description TEXT,
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        instructor VARCHAR(255),
        pdf_file VARCHAR(255) DEFAULT NULL,
        tutorial_topic VARCHAR(255) DEFAULT NULL,
        tutorial_text TEXT DEFAULT NULL,
        tutorial_image VARCHAR(255) DEFAULT NULL,
        tutorial_audio VARCHAR(255) DEFAULT NULL,
        tutorial_video VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {
    // Ignore if the table already exists.
}

ensureCourseColumns($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_name = safe($_POST['course_name'] ?? '');
    $course_code = safe($_POST['course_code'] ?? '');
    $short_description = safe($_POST['short_description'] ?? '');
    $description = safe($_POST['description'] ?? '');
    $category = safe($_POST['category'] ?? '');
    $level = safe($_POST['level'] ?? '');
    $thumbnail = safe($_POST['thumbnail'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $instructor = safe($_POST['instructor'] ?? '');
    $tutorial_topic = safe($_POST['tutorial_topic'] ?? '');
    $tutorial_text = safe($_POST['tutorial_text'] ?? '');
    $tutorial_image = safe($_POST['tutorial_image'] ?? '');
    $tutorial_audio = safe($_POST['tutorial_audio'] ?? '');
    $tutorial_video = safe($_POST['tutorial_video'] ?? '');
    $modules = safe($_POST['modules'] ?? '');
    $quiz = safe($_POST['quiz'] ?? '');
    $assignment = safe($_POST['assignment'] ?? '');
    $certificate_requirements = safe($_POST['certificate_requirements'] ?? '');
    $pdf_file = null;
    $uploadDir = __DIR__ . '/uploads/course_pdfs';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!empty($_FILES['course_pdf']['name'])) {
        $file = $_FILES['course_pdf'];
        $allowedExt = ['pdf'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'PDF የወረደ ጊዜ ስህተት ነበር።';
        } elseif (!in_array($fileExt, $allowedExt, true)) {
            $error = 'እባክዎ ፒዲኤፍ ብቻ ይምረጡ።';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            if ($mimeType !== 'application/pdf') {
                $error = 'ፋይሉ ፒዲኤፍ አይደለም።';
            } else {
                $filename = time() . '_' . bin2hex(random_bytes(5)) . '.' . $fileExt;
                $destination = $uploadDir . '/' . $filename;

                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $error = 'PDF ፋይሉን ማንቀሳቀስ አልተቻለም።';
                } else {
                    $pdf_file = 'uploads/course_pdfs/' . $filename;
                }
            }
        }
    }

    if (!$error && $course_name && $course_code) {
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
            $success = 'ኮርስ በስኬት ታክሏል።';
        } catch (Exception $e) {
            $error = 'ስህተት: ' . $e->getMessage();
        }
    } elseif (!$error) {
        $error = 'እባክዎ ሁሉንም አስገዳጅ መስኮች ይሙሉ።';
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>ኮርስ ጨምር</title>
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
                        editor.on('change', function () { editor.save(); });
                    }
                });
            }
        });
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; color: #333; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; }
        .navbar a { color: white; text-decoration: none; margin-right: 15px; }
        .container { max-width: 600px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h1 { margin-bottom: 20px; color: #667eea; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        textarea { resize: vertical; min-height: 100px; }
        input:focus, textarea:focus { outline: none; border-color: #667eea; box-shadow: 0 0 5px rgba(102, 126, 234, 0.3); }
        button { width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; font-size: 16px; }
        button:hover { background: #764ba2; }
        .error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 5px; margin-bottom: 15px; }
        .success { background: #d4f1d8; color: #1d6a2b; padding: 12px; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="navbar">
    <h2>ኮርስ ማስተዳደር</h2>
    <a href="admin_dashboard.php">← ወደ ዳሽቦርድ</a>
</div>

<div class="container">
    <div class="card">
        <h1>ኮርስ እና ትምህርት አክል</h1>
        <p style="margin-bottom:18px;color:#475569;">እዚህ ኮርስ ትምህርት፣ ሞዱሎች፣ ፈተና እና አስተዳደር ይጨምራሉ።</p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="course_name">የኮርስ ስም *</label>
                <input type="text" id="course_name" name="course_name" required placeholder="ይ.ቤ: ነገረ ሃይማኖት">
            </div>
            
            <div class="form-group">
                <label for="course_code">የኮርስ ኮድ *</label>
                <input type="text" id="course_code" name="course_code" required placeholder="ይ.ቤ: REL-101">
            </div>
            
            <div class="form-group">
                <label for="short_description">አጭር መግለጫ</label>
                <textarea id="short_description" name="short_description" class="rich-editor" placeholder="ኮርሱ በአጭሩ ምን ይማራል?"></textarea>
            </div>

            <div class="form-group">
                <label for="description">ሙሉ መግለጫ</label>
                <textarea id="description" name="description" class="rich-editor" placeholder="ስለ ኮርሱ ተጨማሪ መግለጫ..."></textarea>
            </div>

            <div class="form-group">
                <label for="category">የትምህርት ምድብ</label>
                <input type="text" id="category" name="category" placeholder="ለምሳሌ፦ ነገረ ቅባት, ነገረ ሃይማኖት, ነገረ ማርያም">
            </div>

            <div class="form-group">
                <label for="level">የትምህርት ደረጃ</label>
                <select id="level" name="level">
                    <option value="ጀማሪ">Beginner</option>
                    <option value="መካከለኛ">Intermediate</option>
                    <option value="ከፍተኛ">Advanced</option>
                </select>
            </div>

            <div class="form-group">
                <label for="thumbnail">ምስል / Thumbnail (URL)</label>
                <input type="url" id="thumbnail" name="thumbnail" placeholder="https://.../thumbnail.jpg">
            </div>
            
            <div class="form-group">
                <label for="price">ዋጋ (ብር) *</label>
                <input type="number" id="price" name="price" min="0" step="0.01" required placeholder="0.00">
            </div>
            
            <div class="form-group">
                <label for="instructor">አስተማሪ</label>
                <input type="text" id="instructor" name="instructor" placeholder="አስተማሪ ስም">
            </div>

            <div class="form-group">
                <label for="tutorial_topic">የትምህርት ርዕስ / Lesson Title</label>
                <input type="text" id="tutorial_topic" name="tutorial_topic" placeholder="ለምሳሌ፦ ክፍል 1 - ስለ መንፈሳዊ ሕይወት መሰረት">
            </div>

            <div class="form-group">
                <label for="tutorial_text">የትምህርት እና መመሪያ ጽሑፍ</label>
                <textarea id="tutorial_text" name="tutorial_text" class="rich-editor" placeholder="Introduction&#10;Tags&#10;Headings&#10;Paragraphs&#10;Lists&#10;Links&#10;Images&#10;Tables&#10;Forms&#10;Semantic&#10;Exercises&#10;ይህን ያስገቡ እንደ የትምህርት ማስተማሪያ መመሪያ..."></textarea>
            </div>

            <div class="form-group">
                <label for="tutorial_image">የምስል / Image Link (URL)</label>
                <input type="url" id="tutorial_image" name="tutorial_image" placeholder="https://.../image.jpg ወይም https://.../photo.png">
            </div>

            <div class="form-group">
                <label for="tutorial_audio">የድምፅ / Audio Link (URL)</label>
                <input type="url" id="tutorial_audio" name="tutorial_audio" placeholder="https://.../audio.mp3 ወይም Google Drive link">
            </div>

            <div class="form-group">
                <label for="tutorial_video">የቪድዮ / Video Link (URL / YouTube)</label>
                <input type="url" id="tutorial_video" name="tutorial_video" placeholder="https://.../video.mp4 ወይም YouTube link">
            </div>

            <div class="form-group">
                <label for="modules">ሞጁሎች / Course Outline</label>
                <textarea id="modules" name="modules" class="rich-editor" placeholder="Module 1: Introduction&#10;Module 2: Tags and Headings&#10;Module 3: Paragraphs and Lists&#10;Module 4: Links, Images and Tables&#10;Module 5: Forms and Semantic Elements&#10;Module 6: Exercises and Practice"></textarea>
            </div>

            <div class="form-group">
                <label for="quiz">Quiz / ጥያቄዎች</label>
                <textarea id="quiz" name="quiz" class="rich-editor" placeholder="1. ተጨማሪ ምን ይባላል?&#10;2. Headings እንዴት ይጠቀማሉ?&#10;3. Semantic ቁልፍ ቃል ምን ይለዋል?&#10;4. እንደ እርስዎ የሚፈለገውን መጨረሻ ለተማሪዎች ትገልጣላችሁ..."></textarea>
            </div>

            <div class="form-group">
                <label for="assignment">Assignment / ስራ</label>
                <textarea id="assignment" name="assignment" class="rich-editor" placeholder="ተማሪዎች እንደ አንድ ገጽ መሰረት አጭር ገጽ ይፍጠሩ፤ በእሱ ላይ Tags, Headings, Paragraphs, Lists, Links, Images, Tables, Forms ይደረጋሉ።"></textarea>
            </div>

            <div class="form-group">
                <label for="certificate_requirements">Certificate Requirements</label>
                <textarea id="certificate_requirements" name="certificate_requirements" class="rich-editor" placeholder="80% ውጤት&#10;Quiz ማለፍ&#10;Assignment ማጠናቀቅ"></textarea>
            </div>

            <div class="form-group">
                <label for="course_pdf">PDF እቃ ይጨምሩ</label>
                <input type="file" id="course_pdf" name="course_pdf" accept="application/pdf">
            </div>
            
            <button type="submit">ኮርስ ጨምር</button>
        </form>
    </div>
</div>

</body>
</html>
