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

$recent_students = $pdo->query('SELECT * FROM students ORDER BY created_at DESC LIMIT 5')->fetchAll();
$recent_courses = $pdo->query('SELECT * FROM courses ORDER BY created_at DESC LIMIT 5')->fetchAll();
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>አስተዳዳሪ ዳሽቦርድ</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; color: #333; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .navbar h1 { font-size: 24px; }
        .navbar a { color: white; text-decoration: none; margin-left: 20px; padding: 8px 15px; background: rgba(255,255,255,0.1); border-radius: 5px; }
        .navbar a:hover { background: rgba(255,255,255,0.2); }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #667eea; }
        .stat-card h3 { color: #666; font-size: 14px; margin-bottom: 10px; }
        .stat-card .value { font-size: 32px; font-weight: bold; color: #667eea; }
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
    <h2 style="margin-bottom: 20px;">የዲያቆን ሶፎንያስ ዌብሳይት(የቤተ ገብርኤል አጠቃላይ መረጃ) </h2>
    
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
        </div>
    </div>

    <h2 style="margin-bottom: 20px;">ድርጊቶች</h2>
    <div class="actions">
        <a href="admin_courses.php" class="action-btn">📹 ቪዲዮዎችን ጨምር</a>
        <a href="admin_questions.php" class="action-btn">🧪Quizzes ጨምር </a>
        <a href="admin_exam_results.php" class="action-btn">📊 ሪፖርቶችን ተመልከት</a>
        <a href="admin_certificate.php" class="action-btn">📜 ሰርቲፊኬት አስተዳድር</a>
        <a href="live_class.php" class="action-btn">📡 Live Classes</a>
        <a href="admin_courses.php" class="action-btn">➕ ኮርስ ጨምር</a>
        <a href="tutorial.php" class="action-btn">📚 ትምህርት / ኮርሶች</a>
        <a href="admin_view_courses.php" class="action-btn">📚 ኮርሶችን አስተካክል / ሰርዝ</a>
        <a href="admin_students.php" class="action-btn">👥 ተማሪዎችን አስተዳድር</a>
        <a href="admin_questions.php" class="action-btn">🧠 ጥያቄዎችን አስተዳድር</a>
        <a href="admin_registrations.php" class="action-btn">📝 ምዝገቦችን አስተዳድር</a>
        <a href="admin_exam_results.php" class="action-btn">📊 የፈተና ውጤቶች</a>
    </div>

    <div class="table-section">
        <h3>የቅርብ ምዝገቦች</h3>
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
</div>

</body>
</html>
