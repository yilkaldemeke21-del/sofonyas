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
        expiry_date DATETIME DEFAULT NULL,
        created_by VARCHAR(100) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_exam_access_code (exam_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {
    $error = 'Exam access code table setup failed: ' . $e->getMessage();
}

// Ensure expiry_date column exists for older installations
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM exam_access_codes LIKE 'expiry_date'")->fetch(PDO::FETCH_ASSOC);
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE exam_access_codes ADD COLUMN expiry_date DATETIME DEFAULT NULL AFTER is_active");
    }
} catch (PDOException $e) {
    // ignore
}

// Ensure seat_number column exists for student approvals
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM student_exam_approvals LIKE 'seat_number'")->fetch(PDO::FETCH_ASSOC);
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE student_exam_approvals ADD COLUMN seat_number VARCHAR(50) DEFAULT NULL AFTER exam_type");
    }
} catch (PDOException $e) {
    // ignore
}

$selectedExamType = trim($_GET['exam_type'] ?? ($_POST['exam_type'] ?? 'exam20'));
$selectedExamType = in_array($selectedExamType, ['exam20', 'mid_exam', 'final_exam'], true) ? $selectedExamType : 'exam20';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['update_access_code'])) {
        $examType = trim($_POST['exam_type'] ?? 'exam20');
        $examType = in_array($examType, ['exam20', 'mid_exam', 'final_exam'], true) ? $examType : 'exam20';
        $code = strtoupper(trim($_POST['access_code'] ?? ''));
            $expiryDateRaw = trim($_POST['expiry_date'] ?? '');
            $expiryDate = $expiryDateRaw !== '' ? date('Y-m-d H:i:s', strtotime($expiryDateRaw)) : null;
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        if ($code === '') {
            $error = 'እባክዎ የፈተና ኮድ ያስገቡ።';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO exam_access_codes (exam_type, access_code, is_active, expiry_date, created_by) VALUES (:exam_type, :access_code, :is_active, :expiry_date, :created_by) ON DUPLICATE KEY UPDATE access_code = VALUES(access_code), is_active = VALUES(is_active), expiry_date = VALUES(expiry_date), created_by = VALUES(created_by), created_at = NOW()');
                $stmt->execute([
                    ':exam_type' => $examType,
                    ':access_code' => $code,
                    ':is_active' => $isActive,
                    ':expiry_date' => $expiryDate,
                    ':created_by' => $_SESSION['admin_username'] ?? 'admin'
                ]);
                $message = 'ፈተና ኮድ በተሳካ ሁኔታ ተቀይሯል።';
                $selectedExamType = $examType;
            } catch (PDOException $e) {
                $error = 'ስህተት: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_student_approval'])) {
        $studentId = trim($_POST['student_id'] ?? '');
        $examType = trim($_POST['exam_type'] ?? 'exam20');
        $examType = in_array($examType, ['exam20', 'mid_exam', 'final_exam'], true) ? $examType : 'exam20';
        $status = ($_POST['approval_status'] ?? 'pending') === 'approved' ? 'approved' : 'pending';
        $notes = trim($_POST['notes'] ?? '');

        if ($studentId === '') {
            $error = 'ተማሪ መለያ የለም።';
        } else {
            $seatNumber = trim($_POST['seat_number'] ?? '');
            try {
                $stmt = $pdo->prepare('INSERT INTO student_exam_approvals (student_id, exam_type, status, approved_by, approved_at, notes, seat_number) VALUES (:student_id, :exam_type, :status, :approved_by, :approved_at, :notes, :seat_number) ON DUPLICATE KEY UPDATE status = VALUES(status), approved_by = VALUES(approved_by), approved_at = VALUES(approved_at), notes = VALUES(notes), seat_number = VALUES(seat_number)');
                $stmt->execute([
                    ':student_id' => $studentId,
                    ':exam_type' => $examType,
                    ':status' => $status,
                    ':approved_by' => $_SESSION['admin_username'] ?? 'admin',
                    ':approved_at' => $status === 'approved' ? date('Y-m-d H:i:s') : null,
                    ':notes' => $notes,
                    ':seat_number' => $seatNumber,
                ]);
                $message = 'የተማሪ ማረጋገጫ ተስተካክሏል።';
            } catch (PDOException $e) {
                $error = 'ስህተት: ' . $e->getMessage();
            }
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM exam_access_codes WHERE exam_type = :exam_type LIMIT 1');
$stmt->execute([':exam_type' => $selectedExamType]);
$accessCodeRecord = $stmt->fetch(PDO::FETCH_ASSOC);
$defaultAccessCode = !empty($accessCodeRecord['access_code']) ? $accessCodeRecord['access_code'] : 'SOFI2721';

$codesStmt = $pdo->query('SELECT * FROM exam_access_codes ORDER BY exam_type ASC, created_at DESC');
$allAccessCodes = $codesStmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query('SELECT a.student_id, s.name, s.email, a.exam_type, a.status, a.seat_number, a.approved_at, a.notes FROM student_exam_approvals a LEFT JOIN students s ON s.student_id = a.student_id ORDER BY a.created_at DESC');
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
            <div style="margin-bottom:12px;">
                <label for="exam_type">የፈተና አይነት</label>
                <select id="exam_type" name="exam_type" required>
                    <option value="exam20" <?php echo $selectedExamType === 'exam20' ? 'selected' : ''; ?>>Exam 20</option>
                    <option value="mid_exam" <?php echo $selectedExamType === 'mid_exam' ? 'selected' : ''; ?>>Mid Exam</option>
                    <option value="final_exam" <?php echo $selectedExamType === 'final_exam' ? 'selected' : ''; ?>>Final Exam</option>
                </select>
            </div>
            <div style="margin-bottom:12px;display:flex;gap:8px;align-items:center;">
                <div style="flex:1;">
                    <label for="access_code">ኮድ</label>
                    <input id="access_code" name="access_code" value="<?php echo safe($defaultAccessCode); ?>" placeholder="ለምሳሌ SOFI2721" required>
                </div>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <button type="button" onclick="generateCode()" title="Generate random code">Generate Code</button>
                    <button type="button" onclick="copyLink()" title="Copy full link">Copy Link</button>
                </div>
            </div>
            <div style="margin-bottom:12px;">
                <label for="expiry_date">Expiry Date (optional)</label>
                <input id="expiry_date" name="expiry_date" type="datetime-local" value="<?php echo !empty($accessCodeRecord['expiry_date']) ? date('Y-m-d\TH:i', strtotime($accessCodeRecord['expiry_date'])) : ''; ?>" />
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
        <div style="margin-top:18px;">
            <h4>የአሁኑ ፈተና ኮዶች</h4>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;">የፈተና አይነት</th>
                        <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;">ኮድ</th>
                        <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;">ሁኔታ</th>
                        <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;">Expiry</th>
                        <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;">የተጨመረበት ቀን</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allAccessCodes as $codeRow): ?>
                    <tr>
                        <td style="padding:8px;border-bottom:1px solid #ddd;"><?php echo safe($codeRow['exam_type']); ?></td>
                        <td style="padding:8px;border-bottom:1px solid #ddd;"><?php echo safe($codeRow['access_code']); ?></td>
                        <td style="padding:8px;border-bottom:1px solid #ddd;"><?php echo !empty($codeRow['is_active']) ? 'Active' : 'Inactive'; ?></td>
                        <td style="padding:8px;border-bottom:1px solid #ddd;"><?php echo !empty($codeRow['expiry_date']) ? safe($codeRow['expiry_date']) : '-'; ?></td>
                        <td style="padding:8px;border-bottom:1px solid #ddd;"><?php echo safe($codeRow['created_at']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
                <label for="exam_type">የፈተና አይነት</label>
                <select id="exam_type" name="exam_type">
                    <option value="exam20">Exam 20</option>
                    <option value="mid_exam">Mid Exam</option>
                    <option value="final_exam">Final Exam</option>
                </select>
            </div>
            <div style="margin-bottom:12px;">
                <label for="approval_status">ሁኔታ</label>
                <select id="approval_status" name="approval_status">
                    <option value="pending">በመጠበቅ ላይ</option>
                    <option value="approved">ተስተካክሏል</option>
                </select>
            </div>
            <div style="margin-bottom:12px;">
                <label for="seat_number">Seat Number (optional)</label>
                <input id="seat_number" name="seat_number" placeholder="e.g., A12" />
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
                    <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;">Seat</th>
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
                    <td style="padding:8px;border-bottom:1px solid #ddd;"><?php echo safe($row['seat_number'] ?? '-'); ?></td>
                    <td style="padding:8px;border-bottom:1px solid #ddd;"><?php echo safe($row['exam_type'] ?? 'exam20'); ?></td>
                    <td style="padding:8px;border-bottom:1px solid #ddd;"><?php echo safe($row['status'] ?? 'pending'); ?></td>
                    <td style="padding:8px;border-bottom:1px solid #ddd;"><?php echo safe($row['notes'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
    <script>
    function generateCode() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        let result = '';
        for (let i = 0; i < 8; i++) result += chars.charAt(Math.floor(Math.random() * chars.length));
        document.getElementById('access_code').value = 'SOFI' + result;
    }

    function copyLink() {
        const examType = document.getElementById('exam_type').value;
        const code = document.getElementById('access_code').value.trim();
        if (!code) return alert('Please enter or generate an access code first.');
        let page = 'exam20.php';
        if (examType === 'mid_exam') page = 'mid_exam.php';
        if (examType === 'final_exam') page = 'final_exam.php';
        const url = window.location.origin + '/' + page + '?access_code=' + encodeURIComponent(code);
        navigator.clipboard.writeText(url).then(() => {
            alert('Link copied to clipboard:\n' + url);
        }).catch(() => {
            prompt('Copy this link', url);
        });
    }
    </script>
</body>
</html>
