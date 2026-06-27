<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$course_id = (int)($_GET['id'] ?? 0);
$course = null;
$error = '';
$success = '';

function ensureCourseTutorialColumns(PDO $pdo): void
{
    $existingColumns = [];
    $columnStmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses'");

    foreach ($columnStmt->fetchAll(PDO::FETCH_COLUMN) as $columnName) {
        $existingColumns[$columnName] = true;
    }

    $columnDefinitions = [
        'tutorial_topic' => 'ALTER TABLE courses ADD COLUMN tutorial_topic VARCHAR(255) DEFAULT NULL',
        'tutorial_text' => 'ALTER TABLE courses ADD COLUMN tutorial_text TEXT DEFAULT NULL',
        'tutorial_image' => 'ALTER TABLE courses ADD COLUMN tutorial_image VARCHAR(255) DEFAULT NULL',
        'tutorial_audio' => 'ALTER TABLE courses ADD COLUMN tutorial_audio VARCHAR(255) DEFAULT NULL',
        'tutorial_video' => 'ALTER TABLE courses ADD COLUMN tutorial_video VARCHAR(255) DEFAULT NULL',
    ];

    foreach ($columnDefinitions as $columnName => $sql) {
        if (!isset($existingColumns[$columnName])) {
            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                // Ignore if another process already updated this schema.
            }
        }
    }
}

ensureCourseTutorialColumns($pdo);

if ($course_id) {
    $stmt = $pdo->prepare('SELECT * FROM courses WHERE id = :id');
    $stmt->execute([':id' => $course_id]);
    $course = $stmt->fetch();
}

if (!$course) {
    header('Location: admin_view_courses.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_name = safe($_POST['course_name'] ?? '');
    $course_code = safe($_POST['course_code'] ?? '');
    $description = sanitizeRichText($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $instructor = safe($_POST['instructor'] ?? '');
    $instructor_bio = sanitizeRichText($_POST['instructor_bio'] ?? '');
    $instructor_image = safe($_POST['instructor_image'] ?? '');
    $tutorial_topic = safe($_POST['tutorial_topic'] ?? '');
    $tutorial_text = sanitizeRichText($_POST['tutorial_text'] ?? '');
    $tutorial_image = safe($_POST['tutorial_image'] ?? '');
    $tutorial_audio = safe($_POST['tutorial_audio'] ?? '');
    $tutorial_video = safe($_POST['tutorial_video'] ?? '');
    $pdf_file = $course['pdf_file'];
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
                    if ($course['pdf_file'] && file_exists(__DIR__ . '/' . $course['pdf_file'])) {
                        @unlink(__DIR__ . '/' . $course['pdf_file']);
                    }
                    $pdf_file = 'uploads/course_pdfs/' . $filename;
                }
            }
        }
    }

    if (!$error && $course_name && $course_code) {
        try {
            $stmt = $pdo->prepare(
                'UPDATE courses SET course_name = :course_name, course_code = :course_code, 
                 description = :description, price = :price, instructor = :instructor, pdf_file = :pdf_file,
                 tutorial_topic = :tutorial_topic, tutorial_text = :tutorial_text, tutorial_image = :tutorial_image, tutorial_audio = :tutorial_audio, tutorial_video = :tutorial_video, instructor_bio = :instructor_bio, instructor_image = :instructor_image
                 WHERE id = :id'
            );
            $stmt->execute([
                ':course_name' => $course_name,
                ':course_code' => $course_code,
                ':description' => $description,
                ':price' => $price,
                ':instructor' => $instructor,
                ':instructor_bio' => $instructor_bio,
                ':instructor_image' => $instructor_image,
                ':pdf_file' => $pdf_file,
                ':tutorial_topic' => $tutorial_topic,
                ':tutorial_text' => $tutorial_text,
                ':tutorial_image' => $tutorial_image,
                ':tutorial_audio' => $tutorial_audio,
                ':tutorial_video' => $tutorial_video,
                ':id' => $course_id,
            ]);
            $success = 'ኮርስ በስኬት ተሻሽሏል።';
            
            // Refresh course data
            $stmt = $pdo->prepare('SELECT * FROM courses WHERE id = :id');
            $stmt->execute([':id' => $course_id]);
            $course = $stmt->fetch();
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
    <title>ኮርስ ማስተካከል</title>
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
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
    <h2>ኮርስ ማስተካከል</h2>
    <a href="admin_view_courses.php">← ወደ ኮርሶች ዝርዝር</a>
</div>

<div class="container">
    <div class="card">
        <h1>ኮርስ #<?php echo $course['id']; ?> ማስተካከል</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="course_name">የኮርስ ስም *</label>
                <input type="text" id="course_name" name="course_name" value="<?php echo safe($course['course_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="course_code">የኮርስ ኮድ *</label>
                <input type="text" id="course_code" name="course_code" value="<?php echo safe($course['course_code']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="description">መግለጫ</label>
                <textarea id="description" name="description" class="rich-editor"><?php echo safe($course['description']); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="price">ዋጋ (ብር) *</label>
                <input type="number" id="price" name="price" min="0" step="0.01" value="<?php echo $course['price']; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="instructor">አስተማሪ</label>
                <input type="text" id="instructor" name="instructor" value="<?php echo safe($course['instructor']); ?>">
            </div>

            <div class="form-group">
                <label for="instructor_bio">አስተማሪ አጭር ማብራሪያ</label>
                <textarea id="instructor_bio" name="instructor_bio" rows="3"><?php echo safe($course['instructor_bio'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="instructor_image">አስተማሪ ምስል URL</label>
                <input type="url" id="instructor_image" name="instructor_image" value="<?php echo safe($course['instructor_image'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="tutorial_topic">የትዕይንት ርዕስ / Topic</label>
                <input type="text" id="tutorial_topic" name="tutorial_topic" value="<?php echo safe($course['tutorial_topic'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="tutorial_text">የትዕይንት / አጫጭር መመሪያ ጽሑፍ</label>
                <textarea id="tutorial_text" name="tutorial_text" class="rich-editor"><?php echo safe($course['tutorial_text'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="tutorial_image">የምስል / Image Link (URL)</label>
                <input type="url" id="tutorial_image" name="tutorial_image" value="<?php echo safe($course['tutorial_image'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="tutorial_audio">የድምፅ / Audio Link (URL)</label>
                <input type="url" id="tutorial_audio" name="tutorial_audio" value="<?php echo safe($course['tutorial_audio'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="tutorial_video">የቪድዮ / Video Link (URL / YouTube)</label>
                <input type="url" id="tutorial_video" name="tutorial_video" value="<?php echo safe($course['tutorial_video'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="course_pdf">PDF እቃ ይጨምሩ</label>
                <input type="file" id="course_pdf" name="course_pdf" accept="application/pdf">
            </div>

            <?php if (!empty($course['pdf_file'])): ?>
                <div class="form-group">
                    <label>አሁን ያለው PDF</label>
                    <p><a href="<?php echo safe($course['pdf_file']); ?>" target="_blank">PDF እይ</a></p>
                </div>
            <?php endif; ?>
            
            <button type="submit">ይህን ኮርስ ይህን ማስተካከል</button>
        </form>
    </div>
</div>

</body>
</html>
