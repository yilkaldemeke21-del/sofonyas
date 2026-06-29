<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$adminId = (int)$_SESSION['admin_id'];
$message = '';
$messageType = '';

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS admin_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        file_path VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {
    $message = 'Note table could not be prepared: ' . $e->getMessage();
    $messageType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $filePath = null;

    if ($title === '') {
        $message = 'Please enter a note title.';
        $messageType = 'error';
    } else {
        $uploadDir = __DIR__ . '/uploads/admin_notes';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (!empty($_FILES['note_file']['name'])) {
            $file = $_FILES['note_file'];
            $allowedExt = ['pdf', 'doc', 'docx', 'txt'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($file['error'] !== UPLOAD_ERR_OK || !in_array($ext, $allowedExt, true)) {
                $message = 'Please upload a valid note file.';
                $messageType = 'error';
            } else {
                $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destination = $uploadDir . '/' . $filename;
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $message = 'The file could not be uploaded.';
                    $messageType = 'error';
                } else {
                    $filePath = 'uploads/admin_notes/' . $filename;
                }
            }
        }

        if ($messageType !== 'error') {
            $stmt = $pdo->prepare('INSERT INTO admin_notes (admin_id, title, description, file_path) VALUES (:admin_id, :title, :description, :file_path)');
            $stmt->execute([
                ':admin_id' => $adminId,
                ':title' => $title,
                ':description' => $description,
                ':file_path' => $filePath,
            ]);
            $message = 'Note saved successfully.';
            $messageType = 'success';
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM admin_notes WHERE admin_id = :admin_id ORDER BY created_at DESC LIMIT 10');
$stmt->execute([':admin_id' => $adminId]);
$notes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Note</title>
    <link rel="stylesheet" href="sofonyas (1).css">
</head>
<body>
    <nav>
        <ul>
            <li><a href="admin_dashboard.php">Admin Dashboard</a></li>
            <li><a href="admin_courses.php">Courses</a></li>
            <li><a href="upload_note.php" class="active">Upload Note</a></li>
        </ul>
    </nav>

    <section class="card" style="margin-top:24px;">
        <h2>Upload Study Note</h2>
        <p>Share class notes and PDF files with your students.</p>
        <?php if ($message !== ''): ?>
            <p style="color: <?php echo $messageType === 'success' ? '#166534' : '#b91c1c'; ?>; font-weight:700;">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </p>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <label for="title">Note Title</label>
            <input id="title" name="title" required>
            <br><br>
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1;"></textarea>
            <br><br>
            <label for="note_file">Upload File</label>
            <input id="note_file" name="note_file" type="file">
            <br><br>
            <button class="button" type="submit">Save Note</button>
        </form>
    </section>

    <section class="card">
        <h3>Recent Notes</h3>
        <?php if (empty($notes)): ?>
            <p>No notes have been uploaded yet.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($notes as $note): ?>
                    <li style="margin-bottom:10px;">
                        <strong><?php echo htmlspecialchars($note['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <div class="rich-content"><?php echo renderRichText($note['description'] ?? ''); ?></div>
                        <?php if (!empty($note['file_path'])): ?>
                            <a href="<?php echo htmlspecialchars($note['file_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Open File</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</body>
</html>
