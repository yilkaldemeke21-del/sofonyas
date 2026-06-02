<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$message = '';

// Handle delete
if (isset($_GET['delete'])) {
    $course_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM courses WHERE id = :id');
    $stmt->execute([':id' => $course_id]);
    $message = 'ኮርስ ሰርሷል።';
}

// Get all courses
$stmt = $pdo->query('SELECT * FROM courses ORDER BY created_at DESC');
$courses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>ኮርሶችን ተመልከት</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; color: #333; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: white; text-decoration: none; margin-left: 20px; padding: 8px 15px; background: rgba(255,255,255,0.1); border-radius: 5px; }
        .navbar a:hover { background: rgba(255,255,255,0.2); }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h1 { margin-bottom: 20px; }
        .message { padding: 12px; border-radius: 5px; margin-bottom: 20px; background: #d4f1d8; color: #1d6a2b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f1f5ff; font-weight: bold; }
        .action-btn { display: inline-block; padding: 6px 12px; margin: 2px; border-radius: 5px; text-decoration: none; font-size: 12px; cursor: pointer; }
        .edit-btn { background: #667eea; color: white; }
        .delete-btn { background: #e74c3c; color: white; }
        .edit-btn:hover { background: #764ba2; }
        .delete-btn:hover { background: #c0392b; }
        .add-btn { background: #667eea; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; }
        .add-btn:hover { background: #764ba2; }
    </style>
</head>
<body>
<div class="navbar">
    <h2>ኮርሶችን ተመልከት</h2>
    <div>
        <a href="admin_courses.php" class="add-btn">➕ ነው ኮርስ ጨምር</a>
        <a href="admin_dashboard.php">ወደ ዳሽቦርድ ተመለስ</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (empty($courses)): ?>
            <p style="text-align: center; padding: 30px;">ምንም ኮርስ የለም። <a href="admin_courses.php">አሁን ጨምር</a></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ኮርስ ስም</th>
                        <th>ኮድ</th>
                        <th>ዋጋ (ብር)</th>
                        <th>አስተማሪ</th>
                        <th>ታሪክ</th>
                        <th>ድርጊቶች</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $course): ?>
                        <tr>
                            <td>
                                <strong><?php echo safe($course['course_name']); ?></strong>
                                <br><small><?php echo safe($course['description']); ?></small>
                                <?php if (!empty($course['pdf_file'])): ?>
                                    <br><a href="<?php echo safe($course['pdf_file']); ?>" target="_blank">PDF እይ</a>
                                <?php endif; ?>
                            </td>
                            <td><?php echo safe($course['course_code']); ?></td>
                            <td><?php echo number_format($course['price'], 2); ?></td>
                            <td><?php echo safe($course['instructor'] ?? '-'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($course['created_at'])); ?></td>
                            <td>
                                <a href="admin_edit_course.php?id=<?php echo $course['id']; ?>" class="action-btn edit-btn">ማስተካከል</a>
                                <a href="?delete=<?php echo $course['id']; ?>" class="action-btn delete-btn" onclick="return confirm('እርግጠኛ ነህ?');">ሰር</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
