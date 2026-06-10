<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit;
}

$studentId = $_SESSION['student_id'];
$stmt = $pdo->prepare('SELECT * FROM students WHERE student_id = :student_id');
$stmt->execute([':student_id' => $studentId]);
$student = $stmt->fetch();

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS certificates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        exam_type VARCHAR(50) NOT NULL,
        score INT NOT NULL DEFAULT 0,
        total_questions INT NOT NULL DEFAULT 0,
        issued_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {
}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        is_read TINYINT(1) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {
}

$stmt = $pdo->prepare('SELECT * FROM registrations WHERE student_id = :student_id ORDER BY created_at DESC');
$stmt->execute([':student_id' => $studentId]);
$registrations = $stmt->fetchAll();

$enrolled_courses = count($registrations);
$paid_courses = 0;
$revenue = 0.0;
foreach ($registrations as $row) {
    if ($row['payment_status'] === 'paid') {
        $paid_courses++;
        $revenue += (float)$row['amount'];
    }
}

$summary = ['total' => $enrolled_courses, 'paid' => $paid_courses, 'unpaid' => $enrolled_courses - $paid_courses, 'revenue' => $revenue];

$stmt = $pdo->prepare('SELECT COUNT(*) as total FROM exam_submissions WHERE student_id = :student_id');
$stmt->execute([':student_id' => $studentId]);
$completed_lessons = (int)$stmt->fetch()['total'];

$stmt = $pdo->prepare('SELECT COUNT(*) as total FROM certificates WHERE student_id = :student_id');
$stmt->execute([':student_id' => $studentId]);
$certificates = (int)$stmt->fetch()['total'];

$progress_percentage = 0;
if ($enrolled_courses > 0) {
    $progress_percentage = (int)min(100, round((($completed_lessons * 20) + ($certificates * 30) + ($paid_courses * 10)) / max(1, $enrolled_courses * 10) ));
}

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE student_id = :student_id ORDER BY created_at DESC LIMIT 5');
$stmt->execute([':student_id' => $studentId]);
$notifications = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM certificates WHERE student_id = :student_id ORDER BY issued_at DESC');
$stmt->execute([':student_id' => $studentId]);
$student_certificates = $stmt->fetchAll();

// Create notifications tables if they don't exist
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS email_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        recipient_email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS course_updates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        update_message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS exam_reminders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        exam_type VARCHAR(100) NOT NULL,
        exam_date DATETIME NOT NULL,
        reminder_message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS event_announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_title VARCHAR(255) NOT NULL,
        event_description TEXT NOT NULL,
        event_date DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {}

// Get student-specific notifications
$stmt = $pdo->prepare('SELECT * FROM email_notifications WHERE recipient_email = :email ORDER BY sent_at DESC LIMIT 5');
$stmt->execute([':email' => $student['email']]);
$student_email_notifications = $stmt->fetchAll();

$stmt = $pdo->query('SELECT cu.*, c.course_name FROM course_updates cu JOIN courses c ON cu.course_id = c.id ORDER BY cu.created_at DESC LIMIT 5');
$student_course_updates = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM exam_reminders WHERE student_id = :student_id ORDER BY created_at DESC LIMIT 5');
$stmt->execute([':student_id' => $studentId]);
$student_exam_reminders = $stmt->fetchAll();

$stmt = $pdo->query('SELECT * FROM event_announcements ORDER BY event_date DESC LIMIT 5');
$student_events = $stmt->fetchAll();

// Create notifications tables if they don't exist
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS email_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        recipient_email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS course_updates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        update_message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS exam_reminders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        exam_type VARCHAR(100) NOT NULL,
        exam_date DATETIME NOT NULL,
        reminder_message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS event_announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_title VARCHAR(255) NOT NULL,
        event_description TEXT NOT NULL,
        event_date DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {}

// Get student-specific notifications
$stmt = $pdo->prepare('SELECT * FROM email_notifications WHERE recipient_email = :email ORDER BY sent_at DESC LIMIT 5');
$stmt->execute([':email' => $student['email']]);
$student_email_notifications = $stmt->fetchAll();

$stmt = $pdo->query('SELECT cu.*, c.course_name FROM course_updates cu JOIN courses c ON cu.course_id = c.id ORDER BY cu.created_at DESC LIMIT 5');
$student_course_updates = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM exam_reminders WHERE student_id = :student_id ORDER BY created_at DESC LIMIT 5');
$stmt->execute([':student_id' => $studentId]);
$student_exam_reminders = $stmt->fetchAll();

$stmt = $pdo->query('SELECT * FROM event_announcements ORDER BY event_date DESC LIMIT 5');
$student_events = $stmt->fetchAll();

if (empty($notifications)) {
    $notifications = [
        ['message' => 'አዲስ ትምህርት ለመጀመር ዝግጁ ነው።', 'created_at' => date('Y-m-d H:i:s')],
        ['message' => 'የኮርስ እድገትዎን በማስተካከል ይቀጥሉ።', 'created_at' => date('Y-m-d H:i:s')],
    ];
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>የተማሪ ዳሽቦርድ</title>
    <style>
        body { font-family: Arial, sans-serif; background: #eef2fb; color: #1f2937; margin: 0; padding: 20px; }
        .container { max-width: 1080px; margin: auto; background: white; border-radius: 12px; box-shadow: 0 10px 30px rgba(15,23,42,0.08); padding: 24px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .header h1 { font-size: 28px; }
        .button { padding: 10px 16px; background: #2563eb; color: white; border: none; border-radius: 8px; text-decoration: none; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .card { padding: 18px; border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .card h2 { margin: 0 0 10px; font-size: 16px; color: #475569; }
        .card p { font-size: 30px; font-weight: bold; margin: 0; color: #1d4ed8; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        th { background: #f1f5f9; }
        .badge { display: inline-flex; padding: 6px 12px; border-radius: 9999px; font-size: 13px; }
        .paid { background: #dcfce7; color: #166534; }
        .unpaid { background: #fee2e2; color: #b91c1c; }
        .action-link { color: #2563eb; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>እንኳን በደህና መጡ, <?php echo safe($student['name']); ?></h1>
            <p>የኢሜይልዎ: <?php echo safe($student['email']); ?></p>
        </div>
        <a class="button" href="student_logout.php">ውጣ</a>
    </div>

    <div class="stats">
        <div class="card">
            <h2>Enrolled Courses</h2>
            <p><?php echo $summary['total']; ?></p>
        </div>
        <div class="card">
            <h2>Progress Percentage</h2>
            <p><?php echo $progress_percentage; ?>%</p>
        </div>
        <div class="card">
            <h2>Completed Lessons</h2>
            <p><?php echo $completed_lessons; ?></p>
        </div>
        <div class="card">
            <h2>Certificates</h2>
            <p><?php echo $certificates; ?></p>
        </div>
        <div class="card">
            <h2>Notifications</h2>
            <p><?php echo count($notifications); ?></p>
        </div>
        <div class="card">
            <h2>ጠቅላላ ገቢ (ብር)</h2>
            <p><?php echo number_format($summary['revenue'], 2); ?></p>
        </div>
    </div>

    <div class="card" style="margin-bottom: 24px;">
        <h2>ቀጥታ ፈተናዎች</h2>
        <p style="margin-bottom: 12px; color: #475569;">የተማሪዎ ፈተናዎችን ከዚህ በኋላ ይመልከቱ።</p>
        <a class="button" href="exam20.php" style="display:inline-block; margin-right:10px;">ፈተና 20</a>
        <a class="button" href="short_exam.php" style="display:inline-block;">አጭር ፈተና</a>
    </div>

    <div class="card" style="margin-bottom: 24px;">
        <h2>� Live Classes</h2>
        <p style="margin-bottom: 12px; color: #475569;">YouTube Live, Zoom, Live Chat እና የክፍል መርሃ ግብር ወደ ቀጥታ ክፍል ለመግባት ይጠቀሙ።</p>
        <a class="button" href="live_class.php" style="display:inline-block;">Join Live Class</a>
    </div>

    <div class="card" style="margin-bottom: 24px;">
        <h2>�📚 ትምህርት / ኮርሶች</h2>
        <p style="margin-bottom: 12px; color: #475569;">ከዚህ በኋላ የተጫኑትን ኮርሶችና የPDF ማዕከሎች እይታ ይመልከቱ።</p>
        <a class="button" href="tutorial.php" style="display:inline-block; margin-right:10px;">ወደ ትምህርት ገጽ</a>
        <a class="button" href="register.php" style="display:inline-block;">Enroll Courses</a>
    </div>

    <div class="card" style="margin-bottom: 24px;">
        <h2>🔔 ማስታወቂያዎች</h2>
        <ul style="margin: 0; padding-left: 18px; color: #475569;">
            <?php foreach ($notifications as $note): ?>
                <li style="margin-bottom: 8px;"><?php echo safe($note['message']); ?> <small style="color:#64748b;">(<?php echo date('Y-m-d H:i', strtotime($note['created_at'])); ?>)</small></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <h2 style="margin-top: 30px; margin-bottom: 16px;">📢 Notifications on Dashboard</h2>

    <div class="card" style="margin-bottom: 24px;">
        <h2>📧 Email Notifications</h2>
        <table>
            <thead>
                <tr>
                    <th>ርዕስ</th>
                    <th>መልእክት</th>
                    <th>ታሪክ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($student_email_notifications)): ?>
                    <tr><td colspan="3" style="text-align: center;">ምንም ኢሜይል ማስታወቂያ የለም</td></tr>
                <?php else: ?>
                    <?php foreach ($student_email_notifications as $email): ?>
                        <tr>
                            <td><?php echo safe($email['subject']); ?></td>
                            <td><?php echo substr(safe($email['message']), 0, 50) . '...'; ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($email['sent_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-bottom: 24px;">
        <h2>📚 Course Updates</h2>
        <table>
            <thead>
                <tr>
                    <th>ኮርስ ስም</th>
                    <th>ዘገባ</th>
                    <th>ታሪክ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($student_course_updates)): ?>
                    <tr><td colspan="3" style="text-align: center;">ምንም የኮርስ ዘገባ የለም</td></tr>
                <?php else: ?>
                    <?php foreach ($student_course_updates as $update): ?>
                        <tr>
                            <td><?php echo safe($update['course_name']); ?></td>
                            <td><?php echo substr(safe($update['update_message']), 0, 50) . '...'; ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($update['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-bottom: 24px;">
        <h2>🔔 Exam Reminders</h2>
        <table>
            <thead>
                <tr>
                    <th>ፈተና ዓይነት</th>
                    <th>የፈተና ቀን</th>
                    <th>ታሪክ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($student_exam_reminders)): ?>
                    <tr><td colspan="3" style="text-align: center;">ምንም የፈተና ማስታወቂያ የለም</td></tr>
                <?php else: ?>
                    <?php foreach ($student_exam_reminders as $reminder): ?>
                        <tr>
                            <td><?php echo safe($reminder['exam_type']); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($reminder['exam_date'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($reminder['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-bottom: 24px;">
        <h2>🎉 Event Announcements</h2>
        <table>
            <thead>
                <tr>
                    <th>ጭብጥ ርዕስ</th>
                    <th>ገለጻ</th>
                    <th>የጭብጥ ቀን</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($student_events)): ?>
                    <tr><td colspan="3" style="text-align: center;">ምንም ጭብጥ ዘገባ የለም</td></tr>
                <?php else: ?>
                    <?php foreach ($student_events as $event): ?>
                        <tr>
                            <td><?php echo safe($event['event_title']); ?></td>
                            <td><?php echo substr(safe($event['event_description']), 0, 50) . '...'; ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($event['event_date'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-bottom: 24px;">
        <h2>📜 Course Completion Certificate</h2>
        <p style="margin-bottom: 12px; color: #475569;">የተሰጡትን ሰርቲፊኬቶች እዚህ ይመልከቱ፣ ወደ PDF ይውርዱ እና የማረጋገጫ ቁጥር ይመልከቱ።</p>
        <?php if (empty($student_certificates)): ?>
            <p style="color:#64748b;">እስካሁን ሰርቲፊኬት አልተሰጠም።</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ፈተና</th>
                        <th>ውጤት</th>
                        <th>Verification Number</th>
                        <th>PDF Certificate Download</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($student_certificates as $cert): ?>
                        <tr>
                            <td><?php echo safe($cert['exam_type']); ?></td>
                            <td><?php echo (int)$cert['score']; ?> / <?php echo (int)$cert['total_questions']; ?></td>
                            <td>VC-<?php echo str_pad((string)$cert['id'], 6, '0', STR_PAD_LEFT); ?></td>
                            <td>
                                <a class="button" href="admin_certificate.php?download=<?php echo (int)$cert['id']; ?>" style="display:inline-block; font-size: 13px;">Download PDF</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <h2>የእርስዎ ተመዝጋቢ ኮርሶች</h2>
    <p style="margin-bottom: 16px; color: #475569;">ከዚህ በኋላ የተመዘገቡትን ኮርሶች እና የክፍያ ሁኔታዎች በቀላሉ ይመልከቱ።</p>
    <?php if (empty($registrations)): ?>
        <p>እስካሁን ምንም ተመዝጋቢ ኮርስ አልተመዘገበም።</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ኮርስ</th>
                    <th>መጠን</th>
                    <th>የክፍያ ሁኔታ</th>
                    <th>ቀን</th>
                    <th>እርምጃ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registrations as $row): ?>
                    <tr>
                        <td><?php echo safe($row['course']); ?></td>
                        <td><?php echo safe($row['amount']); ?> ብር</td>
                        <td><span class="badge <?php echo ($row['payment_status'] === 'paid' ? 'paid' : 'unpaid'); ?>"><?php echo safe($row['payment_status']); ?></span></td>
                        <td><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                        <td>
                            <?php if ($row['payment_status'] !== 'paid'): ?>
                                <a class="action-link" href="payment.php?id=<?php echo urlencode($row['id']); ?>">ክፍያ አረጋግጥ</a>
                            <?php else: ?>
                                ተከፍሏል
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
