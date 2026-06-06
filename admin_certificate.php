<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$message = '';
$error = '';

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS certificates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        exam_type VARCHAR(50) NOT NULL,
        score INT NOT NULL DEFAULT 0,
        total_questions INT NOT NULL DEFAULT 0,
        issued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_certificate (student_id, exam_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {
    $error = 'Certificate table setup failed: ' . $e->getMessage();
}

if (isset($_GET['issue'])) {
    $submission_id = (int)$_GET['issue'];
    try {
        $stmt = $pdo->prepare('SELECT student_id, student_name, exam_type, score, total_questions FROM exam_submissions WHERE id = :id');
        $stmt->execute([':id' => $submission_id]);
        $row = $stmt->fetch();

        if (!$row) {
            $error = 'ይህ ፈተና ውጤት አልተገኘም።';
        } else {
            $stmt = $pdo->prepare('INSERT IGNORE INTO certificates (student_id, student_name, exam_type, score, total_questions) VALUES (:student_id, :student_name, :exam_type, :score, :total_questions)');
            $stmt->execute([
                ':student_id' => $row['student_id'],
                ':student_name' => $row['student_name'],
                ':exam_type' => $row['exam_type'],
                ':score' => (int)$row['score'],
                ':total_questions' => (int)$row['total_questions'],
            ]);
            $message = 'ሰርቲፊኬት ተለቋል።';
        }
    } catch (PDOException $e) {
        $error = 'ስህተት: ' . $e->getMessage();
    }
}

if (isset($_GET['delete'])) {
    $cert_id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare('DELETE FROM certificates WHERE id = :id');
        $stmt->execute([':id' => $cert_id]);
        $message = 'ሰርቲፊኬት ተሰርዟል።';
    } catch (PDOException $e) {
        $error = 'ስህተት: ' . $e->getMessage();
    }
}

try {
    $stmt = $pdo->query('SELECT es.*, s.email FROM exam_submissions es LEFT JOIN students s ON s.student_id = es.student_id ORDER BY es.submitted_at DESC');
    $submissions = $stmt->fetchAll();
} catch (PDOException $e) {
    $submissions = [];
    $error = 'DB ችግኝ አለ። ' . $e->getMessage();
}

try {
    $stmt = $pdo->query('SELECT COUNT(*) as total_certificates FROM certificates');
    $dashboard_certificate_count = (int)($stmt->fetch()['total_certificates'] ?? 0);
} catch (PDOException $e) {
    $dashboard_certificate_count = 0;
}

try {
    $stmt = $pdo->query('SELECT * FROM certificates ORDER BY issued_at DESC');
    $certificates = $stmt->fetchAll();
} catch (PDOException $e) {
    $certificates = [];
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>የሰርቲፊኬት አስተዳደር</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f5f7fa; color: #1f2937; }
        .topbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; }
        .topbar a { color: white; text-decoration: none; background: rgba(255,255,255,0.12); padding: 8px 12px; border-radius: 6px; }
        .wrap { max-width: 1200px; margin: 24px auto; padding: 0 18px; }
        .card { background: white; border-radius: 10px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); padding: 18px; margin-bottom: 18px; }
        .msg { padding: 10px 12px; border-radius: 6px; margin-bottom: 12px; }
        .msg.ok { background: #d4f1d8; color: #1d6a2b; }
        .msg.err { background: #ffe7e7; color: #a41616; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 10px; text-align: left; vertical-align: top; }
        th { background: #eef2ff; }
        .btn { display: inline-block; padding: 7px 10px; border-radius: 6px; text-decoration: none; font-size: 12px; }
        .btn.issue { background: #667eea; color: white; }
        .btn.del { background: #e74c3c; color: white; }
    </style>
</head>
<body>
<div class="topbar">
    <h2 style="margin: 0;">📜 የሰርቲፊኬት አስተዳደር</h2>
    <a href="admin_dashboard.php">ወደ ዳሽቦርድ</a>
</div>
<div class="wrap">
    <div class="card">
        <h3>እንዴት ይሰራል</h3>
        <p>ከፈተና ውጤቶች ውስጥ የሚገኙትን ተማሪዎች በአንድ ጠቅላላ ቁጥር ሰርቲፊኬት ለመስጠት እዚህ ይቆጣጠሩ። እንዲሁም የተሰጡትን ሰርቲፊኬቶች እዚህ ማስተካከል ወይም መሰረዝ ይችላሉ።</p>
        <p><strong>የአሁኑ ሰርቲፊኬት ቆጣሪ:</strong> <?php echo $dashboard_certificate_count; ?></p>
        <?php if ($message): ?><div class="msg ok"><?php echo safe($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="msg err"><?php echo safe($error); ?></div><?php endif; ?>
    </div>

    <div class="card">
        <h3>ለማስተላለፍ ዝግጁ የሆኑ ውጤቶች</h3>
        <table>
            <thead>
                <tr>
                    <th>ተማሪ</th>
                    <th>ኢሜይል</th>
                    <th>ፈተና</th>
                    <th>ውጤት</th>
                    <th>ድርጊት</th>
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
                        <td><a class="btn issue" href="admin_certificate.php?issue=<?php echo (int)$row['id']; ?>">ሰርቲፊኬት ለመስጠት</a></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>የተሰጡ ሰርቲፊኬቶች</h3>
        <table>
            <thead>
                <tr>
                    <th>ተማሪ</th>
                    <th>ፈተና</th>
                    <th>ውጤት</th>
                    <th>ቀን</th>
                    <th>ድርጊት</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($certificates)): ?>
                    <tr><td colspan="5">ምንም ሰርቲፊኬት አልተሰጠም።</td></tr>
                <?php else: foreach ($certificates as $row): ?>
                    <tr>
                        <td><?php echo safe($row['student_name']); ?></td>
                        <td><?php echo safe($row['exam_type']); ?></td>
                        <td><?php echo (int)$row['score']; ?> / <?php echo (int)$row['total_questions']; ?></td>
                        <td><?php echo safe($row['issued_at']); ?></td>
                        <td>
                            <a class="btn issue" href="admin_edit_certificate.php?id=<?php echo (int)$row['id']; ?>">አስተካከል</a>
                            <a class="btn del" href="admin_certificate.php?delete=<?php echo (int)$row['id']; ?>" onclick="return confirm('ይህን ሰርቲፊኬት ሰርዝ?');">ሰርዝ</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
