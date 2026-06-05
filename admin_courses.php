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

try {
    $pdo->exec('ALTER TABLE courses ADD COLUMN tutorial_topic VARCHAR(255) DEFAULT NULL');
    $pdo->exec('ALTER TABLE courses ADD COLUMN tutorial_text TEXT DEFAULT NULL');
    $pdo->exec('ALTER TABLE courses ADD COLUMN tutorial_image VARCHAR(255) DEFAULT NULL');
    $pdo->exec('ALTER TABLE courses ADD COLUMN tutorial_audio VARCHAR(255) DEFAULT NULL');
    $pdo->exec('ALTER TABLE courses ADD COLUMN tutorial_video VARCHAR(255) DEFAULT NULL');
} catch (PDOException $e) {
    // Ignore if the columns already exist.
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_name = safe($_POST['course_name'] ?? '');
    $course_code = safe($_POST['course_code'] ?? '');
    $description = safe($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $instructor = safe($_POST['instructor'] ?? '');
    $tutorial_topic = safe($_POST['tutorial_topic'] ?? '');
    $tutorial_text = safe($_POST['tutorial_text'] ?? '');
    $tutorial_image = safe($_POST['tutorial_image'] ?? '');
    $tutorial_audio = safe($_POST['tutorial_audio'] ?? '');
    $tutorial_video = safe($_POST['tutorial_video'] ?? '');
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
                'INSERT INTO courses (course_name, course_code, description, price, instructor, pdf_file, tutorial_topic, tutorial_text, tutorial_image, tutorial_audio, tutorial_video) 
                 VALUES (:course_name, :course_code, :description, :price, :instructor, :pdf_file, :tutorial_topic, :tutorial_text, :tutorial_image, :tutorial_audio, :tutorial_video)'
            );
            $stmt->execute([
                ':course_name' => $course_name,
                ':course_code' => $course_code,
                ':description' => $description,
                ':price' => $price,
                ':instructor' => $instructor,
                ':pdf_file' => $pdf_file,
                ':tutorial_topic' => $tutorial_topic,
                ':tutorial_text' => $tutorial_text,
                ':tutorial_image' => $tutorial_image,
                ':tutorial_audio' => $tutorial_audio,
                ':tutorial_video' => $tutorial_video,
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
        <h1>ነው ኮርስ ጨምር</h1>
        
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
                <label for="description">መግለጫ</label>
                <textarea id="description" name="description" placeholder="ስለ ኮርሱ ተጨማሪ መግለጫ..."></textarea>
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
                <label for="tutorial_topic">የትዕይንት ርዕስ / Topic</label>
                <input type="text" id="tutorial_topic" name="tutorial_topic" placeholder="ለምሳሌ፦ ክፍል 1፡ ሥላሴ መሰረት">
            </div>

            <div class="form-group">
                <label for="tutorial_text">የትዕይንት / አጫጭር መመሪያ ጽሑፍ</label>
                <textarea id="tutorial_text" name="tutorial_text" placeholder="በትክክል ለማብራራት ስለ ትምህርቱ ጽሑፍ ያስገቡ..."></textarea>
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
                <label for="course_pdf">PDF እቃ ይጨምሩ</label>
                <input type="file" id="course_pdf" name="course_pdf" accept="application/pdf">
            </div>
            
            <button type="submit">ኮርስ ጨምር</button>
        </form>
    </div>
</div>

</body>
</html>
