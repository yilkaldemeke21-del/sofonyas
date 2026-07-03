<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$message = '';
$error = '';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

if (isset($_GET['delete'])) {
    $student_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM students WHERE id = :id');
    $stmt->execute([':id' => $student_id]);
    $message = 'ተማሪው ተሰርዟል።';
}

try {
    $totalStudents = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
    $totalPages = max(1, (int)ceil($totalStudents / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare('SELECT * FROM students ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    $students = [];
    $error = 'DB ችግኝ አለ። ' . $e->getMessage();
}
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
        <?php if ($error): ?>
            <div class="message" style="background:#ffe7e7;color:#a41616;"><?php echo safe($error); ?></div>
        <?php endif; ?>

        <?php if (empty($students)): ?>
            <p style="text-align: center; padding: 30px;">ምንም ተማሪ የለም። እባክዎ አዲስ ተማሪ ያክሉ።</p>
        <?php else: ?>
            <div style="margin-bottom: 12px; color:#475569;">Showing <?php echo count($students); ?> of <?php echo $totalStudents; ?> students</div>
            <table>
                <thead>
                    <tr>
                        <th>ስም</th>
                        <th>ኢሜይል</th>
                        <th>አገር</th>
                        <th>ከተማ</th>
                        <th>የተማሪ መለያ</th>
                        <th>የተመዝገበበት ቀን</th>
                        <th>ድርጊቶች</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo safe($student['name']); ?></td>
                            <td><?php echo safe($student['email']); ?></td>
                            <td><?php echo safe($student['country'] ?? 'N/A'); ?></td>
                            <td><?php echo safe($student['city'] ?? 'N/A'); ?></td>
                            <td><?php echo safe($student['student_id']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                            <td>
                                <a class="action-btn edit-btn" href="admin_edit_student.php?id=<?php echo $student['id']; ?>">ማስተካከል</a>
                                <a class="action-btn delete-btn" href="admin_students.php?delete=<?php echo $student['id']; ?>" onclick="return confirm('ይህን ተማሪ ሰርዝ?');">ሰርዝ</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top: 16px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:flex-end;">
                <?php if ($page > 1): ?>
                    <a class="action-btn edit-btn" href="admin_students.php?page=<?php echo max(1, $page - 1); ?>">◀ ቀዳሚ</a>
                <?php endif; ?>
                <span style="color:#475569;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                <?php if ($page < $totalPages): ?>
                    <a class="action-btn edit-btn" href="admin_students.php?page=<?php echo min($totalPages, $page + 1); ?>">ቀጣይ ▶</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
