<?php
session_start();
require_once __DIR__ . '/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

// Get statistics
$stmt = $pdo->query('SELECT COUNT(*) as total FROM registrations');
$total_registrations = $stmt->fetch()['total'];

$stmt = $pdo->query('SELECT COUNT(*) as paid FROM registrations WHERE payment_status = "paid"');
$paid_count = $stmt->fetch()['paid'];

$stmt = $pdo->query('SELECT COUNT(*) as unpaid FROM registrations WHERE payment_status = "unpaid"');
$unpaid_count = $stmt->fetch()['unpaid'];

$stmt = $pdo->query('SELECT COUNT(*) as courses FROM courses');
$total_courses = $stmt->fetch()['courses'];

$stmt = $pdo->query('SELECT COUNT(*) as students FROM students');
$total_students = $stmt->fetch()['students'];

try {
    $stmt = $pdo->query('SELECT COUNT(*) as certificates FROM certificates');
    $total_certificates = (int)($stmt->fetch()['certificates'] ?? 0);
} catch (PDOException $e) {
    $total_certificates = 0;
}

$stmt = $pdo->query('SELECT SUM(amount) as total_revenue FROM registrations WHERE payment_status = "paid"');
$result = $stmt->fetch();
$total_revenue = $result['total_revenue'] ?? 0;

$most_popular = $pdo->query('SELECT course, COUNT(*) as total FROM registrations WHERE course IS NOT NULL AND TRIM(course) <> "" GROUP BY course ORDER BY total DESC, course ASC LIMIT 1')->fetch();
$most_popular_course = $most_popular['course'] ?? 'ምንም ኮርስ የለም';
$most_popular_count = (int)($most_popular['total'] ?? 0);

$active_students = (int)$pdo->query('SELECT COUNT(*) as total FROM students WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)')->fetch()['total'];

$weekly_registrations = $pdo->query('SELECT DATE(created_at) AS day, COUNT(*) AS total FROM registrations WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY day ASC')->fetchAll();

$course_breakdown = $pdo->query('SELECT course, COUNT(*) as total FROM registrations WHERE course IS NOT NULL AND TRIM(course) <> "" GROUP BY course ORDER BY total DESC LIMIT 5')->fetchAll();

$recent_students = $pdo->query('SELECT * FROM students ORDER BY created_at DESC LIMIT 5')->fetchAll();
$recent_courses = $pdo->query('SELECT * FROM courses ORDER BY created_at DESC LIMIT 5')->fetchAll();

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

// Get recent notifications
$recent_email_notifications = $pdo->query('SELECT * FROM email_notifications ORDER BY sent_at DESC LIMIT 5')->fetchAll();
$recent_course_updates = $pdo->query('SELECT cu.*, c.course_name FROM course_updates cu JOIN courses c ON cu.course_id = c.id ORDER BY cu.created_at DESC LIMIT 5')->fetchAll();
$recent_exam_reminders = $pdo->query('SELECT * FROM exam_reminders ORDER BY created_at DESC LIMIT 5')->fetchAll();
$recent_events = $pdo->query('SELECT * FROM event_announcements ORDER BY event_date DESC LIMIT 5')->fetchAll();

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

// Get recent notifications
$recent_email_notifications = $pdo->query('SELECT * FROM email_notifications ORDER BY sent_at DESC LIMIT 5')->fetchAll();
$recent_course_updates = $pdo->query('SELECT cu.*, c.course_name FROM course_updates cu JOIN courses c ON cu.course_id = c.id ORDER BY cu.created_at DESC LIMIT 5')->fetchAll();
$recent_exam_reminders = $pdo->query('SELECT * FROM exam_reminders ORDER BY created_at DESC LIMIT 5')->fetchAll();
$recent_events = $pdo->query('SELECT * FROM event_announcements ORDER BY event_date DESC LIMIT 5')->fetchAll();
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>አስተዳዳሪ ዳሽቦርድ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; color: #333; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .navbar h1 { font-size: 24px; }
        .navbar a { color: white; text-decoration: none; margin-left: 20px; padding: 8px 15px; background: rgba(255,255,255,0.1); border-radius: 5px; }
        .navbar a:hover { background: rgba(255,255,255,0.2); }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-left: 4px solid #667eea; }
        .stat-card h3 { color: #666; font-size: 14px; margin-bottom: 10px; }
        .stat-card .value { font-size: 32px; font-weight: bold; color: #667eea; }
        .stat-card .meta { color: #777; font-size: 12px; margin-top: 8px; }
        .analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .chart-card { background: white; padding: 18px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .chart-card h3 { margin-bottom: 12px; color: #344054; font-size: 17px; }
        .chart-bars { display: flex; align-items: flex-end; gap: 10px; min-height: 160px; }
        .bar-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; gap: 6px; height: 160px; }
        .bar-track { height: 110px; width: 100%; display: flex; align-items: flex-end; justify-content: center; }
        .bar { width: 100%; border-radius: 8px 8px 0 0; background: linear-gradient(180deg, #8b7cf6 0%, #667eea 100%); min-height: 6px; }
        .bar-label { font-size: 11px; color: #667; text-transform: uppercase; }
        .bar-value { font-size: 12px; font-weight: 700; color: #1f2937; }
        .progress-list { display: flex; flex-direction: column; gap: 10px; }
        .progress-row { display: flex; flex-direction: column; gap: 6px; }
        .progress-top { display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #334155; }
        .progress-track { width: 100%; background: #edf2ff; border-radius: 999px; height: 8px; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #667eea 0%, #8b7cf6 100%); }
        .mini-badge { display: inline-block; padding: 4px 8px; border-radius: 999px; background: #eef2ff; color: #4f46e5; font-size: 11px; font-weight: 700; }
        .actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .action-btn { background: white; padding: 15px; border-radius: 8px; text-align: center; cursor: pointer; border: 2px solid #667eea; transition: all 0.3s; text-decoration: none; color: #667eea; font-weight: bold; }
        .action-btn:hover { background: #667eea; color: white; }
        .table-section { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f1f5ff; font-weight: bold; }
        .badge { padding: 5px 10px; border-radius: 5px; font-size: 12px; }
        .paid { background: #d4f1d8; color: #1d6a2b; }
        .unpaid { background: #ffe6e6; color: #a41616; }
        @media (max-width: 992px) { .navbar { flex-direction: column; align-items: flex-start; gap: 10px; } .navbar div { width: 100%; } .navbar a { margin-left: 0; margin-right: 10px; } }
        @media (max-width: 768px) { .container { padding: 0 12px; } .stats-grid, .analytics-grid, .actions { grid-template-columns: 1fr; } .table-section { overflow-x: auto; } }
        @media (max-width: 576px) { .navbar h1 { font-size: 20px; } .navbar a { display: inline-block; margin-top: 6px; } }
    </style>
</head>
<body>
<div class="navbar">
    <h1>🎓 ቤተ ገብርኤል - ዳሽቦርድ</h1>
    <div>
        <span>እንኳን ደህና መጡ, <?php echo safe($_SESSION['admin_username']); ?>!</span>
        <a href="admin_logout.php">ውጣ</a>
    </div>
</div>

<div class="container">
    <h2 style="margin-bottom: 20px;">የዲያቆን ሶፎንያስ ዌቭሳይት ደመቀ(የቤተ ገብርኤል አጠቃላይ መረጃ) </h2>
    
    <div class="stats-grid">
        <div class="stat-card">
            <h3>ጠቅላላ ምዝገቦች</h3>
            <div class="value"><?php echo $total_registrations; ?></div>
        </div>
        <div class="stat-card">
            <h3>ክፍያ የከፈለ</h3>
            <div class="value"><?php echo $paid_count; ?></div>
        </div>
        <div class="stat-card">
            <h3>ክፍያ ያልከፈለ</h3>
            <div class="value"><?php echo $unpaid_count; ?></div>
        </div>
        <div class="stat-card">
            <h3>ጠቅላላ ኮርሶች</h3>
            <div class="value"><?php echo $total_courses; ?></div>
        </div>
        <div class="stat-card">
            <h3>ጠቅላላ ተማሪዎች</h3>
            <div class="value"><?php echo $total_students; ?></div>
        </div>
        <div class="stat-card">
            <h3>ጠቅላላ ሰርቲፊኬቶች</h3>
            <div class="value"><?php echo $total_certificates; ?></div>
        </div>
        <div class="stat-card">
            <h3>ጠቅላላ ገቢ (ብር)</h3>
            <div class="value"><?php echo number_format($total_revenue, 2); ?></div>
            <div class="meta">ከፍለው የተመዘገቡ ብቻ</div>
        </div>
        <div class="stat-card">
            <h3>ታዋቂው ኮርስ</h3>
            <div class="value" style="font-size: 18px; line-height: 1.4;"><?php echo safe($most_popular_course); ?></div>
            <div class="meta"><?php echo $most_popular_count; ?> የተመዘገቡ</div>
        </div>
        <div class="stat-card">
            <h3>ንቁ ተማሪዎች</h3>
            <div class="value"><?php echo $active_students; ?></div>
            <div class="meta">ላለፉት 30 ቀናት</div>
        </div>
    </div>

    <div class="analytics-grid">
        <div class="chart-card">
            <h3>📈 የ7 ቀን ምዝገባ አዝማሚያ</h3>
            <?php if (empty($weekly_registrations)): ?>
                <p style="color:#667;">ምንም ዳታ የለም።</p>
            <?php else: ?>
                <div class="chart-bars">
                    <?php foreach ($weekly_registrations as $item):
                        $height = $item['total'] > 0 ? min(100, 8 + (int)$item['total'] * 18) : 8;
                    ?>
                        <div class="bar-wrap">
                            <div class="bar-track">
                                <div class="bar" style="height: <?php echo $height; ?>%;"></div>
                            </div>
                            <div class="bar-label"><?php echo date('D', strtotime($item['day'])); ?></div>
                            <div class="bar-value"><?php echo (int)$item['total']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="chart-card">
            <h3>🏆 ታዋቂ ኮርሶች ድርሻ</h3>
            <div class="progress-list">
                <?php if (empty($course_breakdown)): ?>
                    <p style="color:#667;">ኮርስ አሁን የለም።</p>
                <?php else:
                    $maxCourseCount = max(array_column($course_breakdown, 'total'));
                    foreach ($course_breakdown as $item):
                        $pct = $maxCourseCount > 0 ? (int)(($item['total'] / $maxCourseCount) * 100) : 0;
                ?>
                    <div class="progress-row">
                        <div class="progress-top">
                            <span><?php echo safe($item['course']); ?></span>
                            <span class="mini-badge"><?php echo (int)$item['total']; ?></span>
                        </div>
                        <div class="progress-track"><div class="progress-fill" style="width: <?php echo $pct; ?>%;"></div></div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <h2 style="margin-bottom: 20px;">ድርጊቶች</h2>
    <div class="actions">
        <a href="admin_course_builder.php" class="action-btn">🛠️ ኮርስ ብሉደር ክፈት</a>
        <a href="admin_courses.php" class="action-btn">📹 ቪዲዮዎችን ጨምር</a>
        <a href="admin_questions.php" class="action-btn">🧪Quizzes ጨምር </a>
        <a href="admin_exam_results.php" class="action-btn">📊 ሪፖርቶችን ተመልከት</a>
        <a href="admin_certificate.php" class="action-btn">📜 ሰርቲፊኬት አስተዳድር</a>
        <a href="live_class.php" class="action-btn">📡 ላይቭ ክላስ</a>
        <a href="discussion_forum.php" class="action-btn">💬 ዲስኬሽን ፎርም</a>
        <a href="library.php" class="action-btn">📚 ላይበራሪ ዳሽቦርድ</a>
        <a href="admin_course_builder.php" class="action-btn">➕ ኮርስ ብሉደር / ፋብሪካ ስራ</a>
        <a href="admin_view_courses.php" class="action-btn">📚 ኮርሶች / ትምህርት ዝርዝር</a>
        <a href="admin_view_courses.php" class="action-btn">📚 ኮርሶችን አስተካክል / ሰርዝ</a>
        <a href="admin_students.php" class="action-btn">👥 ተማሪዎችን አስተዳድር</a>
        <a href="password_store.php" class="action-btn">🔐 የይለፍ ቃል ማስቀመጫ</a>
        <a href="admin_questions.php" class="action-btn">🧠 ጥያቄዎችን አስተዳድር</a>
        <a href="admin_registrations.php" class="action-btn">📝 ምዝገቦችን አስተዳድር</a>
        <a href="admin_exam_results.php" class="action-btn">📊 የፈተና ውጤቶች</a>
    </div>

    <div class="table-section">
        <h3>የቅርብ መዝገቦች</h3>
        <table>
            <thead>
                <tr>
                    <th>ስም</th>
                    <th>ኢሜይል</th>
                    <th>መለያ</th>
                    <th>ኮርስ</th>
                    <th>ክፍያ ሁኔታ</th>
                    <th>ታሪክ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->query('SELECT * FROM registrations ORDER BY created_at DESC LIMIT 10');
                $recent = $stmt->fetchAll();
                
                if (empty($recent)): ?>
                    <tr><td colspan="6" style="text-align: center;">ምንም ምዝገባ የለም</td></tr>
                <?php else:
                    foreach ($recent as $row): ?>
                        <tr>
                            <td><?php echo safe($row['name']); ?></td>
                            <td><?php echo safe($row['email']); ?></td>
                            <td><?php echo safe($row['student_id']); ?></td>
                            <td><?php echo safe($row['course']); ?></td>
                            <td><span class="badge <?php echo ($row['payment_status'] === 'paid' ? 'paid' : 'unpaid'); ?>"><?php echo safe($row['payment_status']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                        </tr>
                    <?php endforeach;
                endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-section" style="margin-top: 30px;">
        <h3>የቅርብ ተማሪዎች</h3>
        <table>
            <thead>
                <tr>
                    <th>ስም</th>
                    <th>ኢሜይል</th>
                    <th>መለያ</th>
                    <th>ታሪክ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_students)): ?>
                    <tr><td colspan="4" style="text-align: center;">ምንም ተማሪ የለም</td></tr>
                <?php else: ?>
                    <?php foreach ($recent_students as $student): ?>
                        <tr>
                            <td><?php echo safe($student['name']); ?></td>
                            <td><?php echo safe($student['email']); ?></td>
                            <td><?php echo safe($student['student_id']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-section" style="margin-top: 30px;">
        <h3>የቅርብ ኮርሶች</h3>
        <table>
            <thead>
                <tr>
                    <th>ኮርስ ስም</th>
                    <th>ኮድ</th>
                    <th>ዋጋ</th>
                    <th>ታሪክ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_courses)): ?>
                    <tr><td colspan="4" style="text-align: center;">ምንም ኮርስ የለም</td></tr>
                <?php else: ?>
                    <?php foreach ($recent_courses as $course): ?>
                        <tr>
                            <td><?php echo safe($course['course_name']); ?></td>
                            <td><?php echo safe($course['course_code']); ?></td>
                            <td><?php echo number_format($course['price'], 2); ?> ብር</td>
                            <td><?php echo date('M d, Y', strtotime($course['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <h2 style="margin-bottom: 20px; margin-top: 30px;">📢 በዳሽቦርድ ማስታወቂያ</h2>

    <div class="table-section">
        <h3>📧 የኢሜይል ማስታወቂያ</h3>
        <table>
            <thead>
                <tr>
                    <th>ተቀባይ</th>
                    <th>ርእስ</th>
                    <th>መልዕክት</th>
                    <th>ታሪክ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_email_notifications)): ?>
                    <tr><td colspan="4" style="text-align: center;">ምንም ኢሜይል ማስታወቂያ የለም</td></tr>
                <?php else: ?>
                    <?php foreach ($recent_email_notifications as $email): ?>
                        <tr>
                            <td><?php echo safe($email['recipient_email']); ?></td>
                            <td><?php echo safe($email['subject']); ?></td>
                            <td><?php echo substr(safe($email['message']), 0, 50) . '...'; ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($email['sent_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-section" style="margin-top: 30px;">
        <h3>📚 የተሻሻሉ ኮርሶች</h3>
        <table>
            <thead>
                <tr>
                    <th>ኮርስ ስም</th>
                    <th>ዝርዝር</th>
                    <th>ታሪክ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_course_updates)): ?>
                    <tr><td colspan="3" style="text-align: center;">ምንም የኮርስ ዝርዝር የተሻሻለ የለም</td></tr>
                <?php else: ?>
                    <?php foreach ($recent_course_updates as $update): ?>
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

    <div class="table-section" style="margin-top: 30px;">
        <h3>🔔 ፈተናዎችን ማስታወሻ</h3>
        <table>
            <thead>
                <tr>
                    <th>ተማሪ መለያ</th>
                    <th>ፈተና ዓይነት</th>
                    <th>ፈተና ቀን</th>
                    <th>ታሪክ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_exam_reminders)): ?>
                    <tr><td colspan="4" style="text-align: center;">ምንም ፈተና ማስታወቂያ የለም</td></tr>
                <?php else: ?>
                    <?php foreach ($recent_exam_reminders as $reminder): ?>
                        <tr>
                            <td><?php echo safe($reminder['student_id']); ?></td>
                            <td><?php echo safe($reminder['exam_type']); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($reminder['exam_date'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($reminder['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-section" style="margin-top: 30px;">
        <h3>🎉 ሁኔታዎችን ማስታወቂያ እና መግለጫ</h3>
        <table>
            <thead>
                <tr>
                    <th>ፍጥረታዊ ርዕስ</th>
                    <th>መግለጫ</th>
                    <th>ፍጥረታዊ ቀን</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_events)): ?>
                    <tr><td colspan="3" style="text-align: center;">ምንም ፍጥረታዊ ዝበሌ የለም</td></tr>
                <?php else: ?>
                    <?php foreach ($recent_events as $event): ?>
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
</div>

</body>
</html>
