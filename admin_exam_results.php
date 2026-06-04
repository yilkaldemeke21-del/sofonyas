<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$pdo->exec('CREATE TABLE IF NOT EXISTS exam_submissions (id INT AUTO_INCREMENT PRIMARY KEY, student_id VARCHAR(100) NOT NULL, student_name VARCHAR(255) NOT NULL, exam_type VARCHAR(50) NOT NULL, score INT NOT NULL DEFAULT 0, total_questions INT NOT NULL DEFAULT 0, answers JSON DEFAULT NULL, submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP)');

$stmt = $pdo->query('SELECT es.*, s.email FROM exam_submissions es LEFT JOIN students s ON s.student_id = es.student_id ORDER BY es.submitted_at DESC');
$submissions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>የፈተና ውጤቶች</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f7fa; color: #1f2937; margin: 0; }
        .wrap { max-width: 1200px; margin: 24px auto; padding: 0 18px; }
        .card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 10px; text-align: left; vertical-align: top; }
        th { background: #eef2ff; }
        a { color: #2563eb; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h2>የፈተና ውጤቶች</h2>
        <p>ተማሪዎች የሰጡትን ፈተና ውጤት እዚህ ማየት ይችላሉ።</p>
        <table>
            <thead>
                <tr>
                    <th>ተማሪ</th>
                    <th>ኢሜይል</th>
                    <th>ፈተና</th>
                    <th>ውጤት</th>
                    <th>ቀን</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($submissions)): ?>
                    <tr><td colspan="5">ምንም የፈተና ውጤት የለም።</td></tr>
                <?php else: foreach ($submissions as $row): ?>
                    <tr>
                        <td><?php echo safe($row['student_name']); ?></td>
                        <td><?php echo safe($row['email'] ?? '-'); ?></td>
                        <td><?php echo safe($row['exam_type']); ?></td>
                        <td><?php echo (int)$row['score']; ?> / <?php echo (int)$row['total_questions']; ?></td>
                        <td><?php echo safe($row['submitted_at']); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
