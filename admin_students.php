<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$message = '';

if (isset($_GET['delete'])) {
    $student_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM students WHERE id = :id');
    $stmt->execute([':id' => $student_id]);
    $message = 'ተማሪው ተሰርዟል።';
}

$stmt = $pdo->query('SELECT * FROM students ORDER BY created_at DESC');
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>የተማሪዎች አስተዳደር</title>
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
        .action-btn { display: inline-block; padding: 6px 12px; margin: 2px; border-radius: 5px; text-decoration: none; font-size: 12px; }
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
    <h2>የተማሪዎች አስተዳደር</h2>
    <div>
        <a href="admin_dashboard.php">ዳሽቦርድ</a>
        <a href="admin_edit_student.php" class="add-btn">➕ አዲስ ተማሪ ጨምር</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <?php if ($message): ?>
            <div class="message"><?php echo safe($message); ?></div>
        <?php endif; ?>

        <?php if (empty($students)): ?>
            <p style="text-align: center; padding: 30px;">ምንም ተማሪ የለም። እባክዎ አዲስ ተማሪ ያክሉ።</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ስም</th>
                        <th>ኢሜይል</th>
                        <th>የተማሪ መለያ</th>
                        <th>የክፍያ ሁኔታ</th>
                        <th>ተጨማሪ</th>
                        <th>ድርጊቶች</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo safe($student['name']); ?></td>
                            <td><?php echo safe($student['email']); ?></td>
                            <td><?php echo safe($student['student_id']); ?></td>
                            <td><?php echo safe($student['created_at']); ?></td>
                            <td><?php echo safe($student['password_hash'] ? 'አለ' : 'የለም'); ?></td>
                            <td>
                                <a class="action-btn edit-btn" href="admin_edit_student.php?id=<?php echo $student['id']; ?>">ማስተካከል</a>
                                <a class="action-btn delete-btn" href="admin_students.php?delete=<?php echo $student['id']; ?>" onclick="return confirm('ይህን ተማሪ ሰርዝ?');">ሰርዝ</a>
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
