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
    $pdo->exec('CREATE TABLE IF NOT EXISTS exam_access_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exam_type VARCHAR(50) NOT NULL,
        access_code VARCHAR(50) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_by VARCHAR(100) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_exam_access_code (exam_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {
    $error = 'Exam access code table setup failed: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_access_code'])) {
        $examType = trim($_POST['exam_type'] ?? 'exam20');
        $code = strtoupper(trim($_POST['access_code'] ?? ''));
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        if ($code === '') {
            $error = 'እባክዎ የፈተና ኮድ ያስገቡ።';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO exam_access_codes (exam_type, access_code, is_active, created_by) VALUES (:exam_type, :access_code, :is_active, :created_by) ON DUPLICATE KEY UPDATE access_code = VALUES(access_code), is_active = VALUES(is_active), created_by = VALUES(created_by), created_at = NOW()');
                $stmt->execute([
                    ':exam_type' => $examType,
                    ':access_code' => $code,
                    ':is_active' => $isActive,
                    ':created_by' => $_SESSION['admin_username'] ?? 'admin'
                ]);
                $message = 'ፈተና ኮድ በተሳካ ሁኔታ ተቀይሯል።';
            } catch (PDOException $e) {
                $error = 'ስህተት: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_student_approval'])) {
        $studentId = trim($_POST['student_id'] ?? '');
        $status = ($_POST['approval_status'] ?? 'pending') === 'approved' ? 'approved' : 'pending';
        $notes = trim($_POST['notes'] ?? '');

        if ($studentId === '') {
            $error = 'ተማሪ መለያ የለም።';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO student_exam_approvals (student_id, exam_type, status, approved_by, approved_at, notes) VALUES (:student_id, :exam_type, :status, :approved_by, :approved_at, :notes) ON DUPLICATE KEY UPDATE status = VALUES(status), approved_by = VALUES(approved_by), approved_at = VALUES(approved_at), notes = VALUES(notes)');
                $stmt->execute([
                    ':student_id' => $studentId,
                    ':exam_type' => 'exam20',
                    ':status' => $status,
                    ':approved_by' => $_SESSION['admin_username'] ?? 'admin',
                    ':approved_at' => $status === 'approved' ? date('Y-m-d H:i:s') : null,
                    ':notes' => $notes,
                ]);
                $message = 'የተማሪ ማረጋገጫ ተስተካክሏል።';
            } catch (PDOException $e) {
                $error = 'ስህተት: ' . $e->getMessage();
            }
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM exam_access_codes WHERE exam_type = :exam_type LIMIT 1');
$stmt->execute([':exam_type' => 'exam20']);
$accessCodeRecord = $stmt->fetch(PDO::FETCH_ASSOC);
$defaultAccessCode = !empty($accessCodeRecord['access_code']) ? $accessCodeRecord['access_code'] : 'SOFI2721';

$stmt = $pdo->query('SELECT s.student_id, s.name, s.email, a.status, a.approved_at, a.notes FROM students s LEFT JOIN student_exam_approvals a ON a.student_id = s.student_id AND a.exam_type = "exam20" ORDER BY s.created_at DESC');
$studentApprovals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>የፈተና ኮድ አስተዳደር</title>
    <style>
        *{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#f5f7fa;color:#1f2937} .topbar{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:14px 18px;display:flex;justify-content:space-between;align-items:center}.topbar a{color:#fff;text-decoration:none;background:rgba(255,255,255,.12);padding:8px 12px;border-radius:6px}.wrap{max-width:900px;margin:24px auto;padding:0 18px}.card{background:#fff;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.08);padding:18px;margin-bottom:18px}.msg{padding:10px 12px;border-radius:6px;margin-bottom:12px}.msg.ok{background:#d4f1d8;color:#1d6a2b}.msg.err{background:#ffe7e7;color:#a41616}label{display:block;margin-bottom:6px;font-weight:700}input,select{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px}button{padding:10px 14px;background:#667eea;color:#fff;border:none;border-radius:6px;font-weight:700;cursor:pointer}button:hover{background:#764ba2}
    </style>
</head>
<body>
<div class="topbar">
    <h2 style="margin:0;">🔐 የፈተና ኮድ አስተዳደር</h2>
    <a href="admin_dashboard.php">ዳሽቦርድ</a>
</div>
<div class="wrap">
    <div class="card">
        <h3>Admin ፈተና ኮድ ይሰጣል</h3>
        <p>ተማሪዎች ፈተና ለመጀመር ከዚህ ኮድ ጋር ይግባሉ።</p>
        <?php if ($message): ?><div class="msg ok"><?php echo safe($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="msg err"><?php echo safe($error); ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="update_access_code" value="1">
            <input type="hidden" name="exam_type" value="exam20">
            <div style="margin-bottom:12px;">
                <label for="access_code">ኮድ</label>
                <input id="access_code" name="access_code" value="<?php echo safe($defaultAccessCode); ?>" placeholder="ለምሳሌ SOFI2721" required>
            </div>
            <div style="margin-bottom:12px;">
                <label for="is_active">ሁኔታ</label>
                <select id="is_active" name="is_active">
                    <option value="1" <?php echo !empty($accessCodeRecord['is_active']) ? 'selected' : ''; ?>>ንቁ</option>
                    <option value="0" <?php echo empty($accessCodeRecord['is_active']) ? 'selected' : ''; ?>>የማይንቀሳቀስ</option>
                </select>
            </div>
            <button type="submit">ኮድ አስቀምጥ</button>
        </form>
    </div>

    <div class="card">
        <h3>የተማሪ ማረጋገጫ</h3>
        <form method="post">
            <input type="hidden" name="update_student_approval" value="1">
            <div style="margin-bottom:12px;">
                <label for="student_id">የተማሪ መለያ</label>
                <input id="student_id" name="student_id" placeholder="ለምሳሌ STU001" required>
            </div>
            <div style="margin-bottom:12px;">
                <label for="approval_status">ሁኔታ</label>
                <select id="approval_status" name="approval_status">
                    <option value="pending">በመጠበቅ ላይ</option>
                    <option value="approved">ተስተካክሏል</option>
                </select>
            </div>
            <div style="margin-bottom:12px;">
                <label for="notes">ማስታወሻ</label>
                <textarea id="notes" name="notes" rows="3" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;"></textarea>
            </div>
            <button type="submit">ማረጋገጫ አስቀምጥ</button>
        </form>
    </div>

    <div class="card">
        <h3>የተማሪ ማረጋገጫ ዝርዝር</h3>
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;">መለያ</th>
                    <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;">ስም</th>
                    <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;">ኢሜይል</th>
                    <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;">ሁኔታ</th>
                    <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;">ማስታወሻ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($studentApprovals as $row): ?>
                <tr>
                    <td style="padding:8px;border-bottom:1px solid #ddd;"><?php echo safe($row['student_id'] ?? ''); ?></td>
                    <td style="padding:8px;border-bottom:1px solid #ddd;"><?php echo safe($row['name'] ?? ''); ?></td>
                    <td style="padding:8px;border-bottom:1px solid #ddd;"><?php echo safe($row['email'] ?? ''); ?></td>
                    <td style="padding:8px;border-bottom:1px solid #ddd;"><?php echo safe($row['status'] ?? 'pending'); ?></td>
                    <td style="padding:8px;border-bottom:1px solid #ddd;"><?php echo safe($row['notes'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
