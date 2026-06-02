<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_name = safe($_POST['course_name'] ?? '');
    $course_code = safe($_POST['course_code'] ?? '');
    $description = safe($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $instructor = safe($_POST['instructor'] ?? '');

    if ($course_name && $course_code) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO courses (course_name, course_code, description, price, instructor) 
                 VALUES (:course_name, :course_code, :description, :price, :instructor)'
            );
            $stmt->execute([
                ':course_name' => $course_name,
                ':course_code' => $course_code,
                ':description' => $description,
                ':price' => $price,
                ':instructor' => $instructor,
            ]);
            $success = 'ኮርስ በስኬት ታክሏል።';
        } catch (Exception $e) {
            $error = 'ስህተት: ' . $e->getMessage();
        }
    } else {
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
        
        <form method="post">
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
            
            <button type="submit">ኮርስ ጨምር</button>
        </form>
    </div>
</div>

</body>
</html>
