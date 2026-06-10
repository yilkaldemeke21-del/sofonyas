<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$candidatePasswords = [
    '123456', '123456789', '12345', 'password', 'Password1', 'password123', 'Password123',
    'student', 'student123', 'Student123', 'student2024', 'student2025', 'student2026',
    'yilkal', 'Yilkal', 'Yilkal123', 'yilkal123', 'Yilkal2024', 'yilkal2025',
    'demeke', 'Demeke', 'demeke123', 'Demeke123', 'yilkaldemeke', 'yilkaldemeke21',
    'YilkalDemeke', 'YilkalDemeke21', '27', '027', 'sofonyas', 'Sofonyas', 'sofonyas123',
    'Sofonyas123', 'admin', 'admin123', 'Admin123', 'welcome', 'Welcome1', 'qwerty',
    'Qwerty123', 'abc123', 'Abc123', '1234', '0000', 'secret', 'test', 'test123', 'demo',
    'demo123', 'school', 'school123'
];

function recoverKnownPassword($passwordHash, $candidates) {
    foreach ($candidates as $candidate) {
        if (password_verify($candidate, $passwordHash)) {
            return $candidate;
        }
    }

    return null;
}

$stmt = $pdo->query('SELECT id, name, email, student_id, password_hash, created_at FROM students ORDER BY created_at DESC');
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>የይለፍ ቃል ማስቀመጫ</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f7fa; color: #333; margin: 0; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: white; text-decoration: none; margin-left: 15px; padding: 8px 14px; background: rgba(255,255,255,0.1); border-radius: 5px; }
        .navbar a:hover { background: rgba(255,255,255,0.2); }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h1 { margin-bottom: 10px; }
        p.note { color: #4b5563; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; word-break: break-word; }
        th { background: #eef2ff; font-size: 13px; }
        td { font-size: 14px; }
        .hash { font-family: Consolas, monospace; font-size: 12px; color: #111827; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 12px; font-weight: 700; background: #ecfdf5; color: #047857; }
        .badge.muted { background: #f3f4f6; color: #4b5563; }
    </style>
</head>
<body>
<div class="navbar">
    <div>
        <a href="admin_dashboard.php">← ዳሽቦርድ</a>
    </div>
    <div>
        <span>እንኳን ደህና መጡ, <?php echo safe($_SESSION['admin_username']); ?>!</span>
        <a href="admin_logout.php">ውጣ</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <h1>የይለፍ ቃል ማስቀመጫ</h1>
        <p class="note">ይህ ገጽ ለዳሞ እና ለሙከራ መለያዎች የታወቁትን የይለፍ ቃል ተጨማሪ ያሳያል። ሌሎች ሃሽዎች የተጠበቁ ስለሆኑ እውነተኛ ይለፍ ቃል አይታይም።</p>

        <?php if (empty($students)): ?>
            <p>ምንም የተማሪ መረጃ የለም።</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ስም</th>
                        <th>ኢሜይል</th>
                        <th>የተማሪ መለያ</th>
                        <th>የተቀመጠ ሃሽ</th>
                        <th>የታወቀ የይለፍ ቃል</th>
                        <th>ተጨማሪ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <?php $recoveredPassword = recoverKnownPassword($student['password_hash'], $candidatePasswords); ?>
                        <tr>
                            <td><?php echo safe($student['name']); ?></td>
                            <td><?php echo safe($student['email']); ?></td>
                            <td><?php echo safe($student['student_id']); ?></td>
                            <td class="hash"><?php echo safe($student['password_hash']); ?></td>
                            <td>
                                <?php if ($recoveredPassword): ?>
                                    <span class="badge"><?php echo safe($recoveredPassword); ?></span>
                                <?php else: ?>
                                    <span class="badge muted">አልታወቀም</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
