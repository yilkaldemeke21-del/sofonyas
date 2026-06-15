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

$stmt = $pdo->query('SELECT * FROM courses ORDER BY created_at DESC LIMIT 6');
$available_courses = $stmt->fetchAll();

$completed_courses = count($student_certificates);
$in_progress_courses = max(0, $enrolled_courses - $completed_courses);

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT "pending",
        due_date DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS quiz_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        quiz_name VARCHAR(255) NOT NULL,
        score INT NOT NULL DEFAULT 0,
        total_questions INT NOT NULL DEFAULT 0,
        status VARCHAR(30) NOT NULL DEFAULT "pending",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS student_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        note_text TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS saved_courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        course_name VARCHAR(255) NOT NULL,
        instructor VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {}

$stmt = $pdo->prepare('SELECT * FROM assignments WHERE student_id = :student_id ORDER BY due_date ASC LIMIT 5');
$stmt->execute([':student_id' => $studentId]);
$assignments = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM quiz_results WHERE student_id = :student_id ORDER BY created_at DESC LIMIT 5');
$stmt->execute([':student_id' => $studentId]);
$quiz_results = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM student_notes WHERE student_id = :student_id ORDER BY created_at DESC LIMIT 5');
$stmt->execute([':student_id' => $studentId]);
$student_notes = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM saved_courses WHERE student_id = :student_id ORDER BY created_at DESC LIMIT 6');
$stmt->execute([':student_id' => $studentId]);
$saved_courses = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT lb.*, c.course_name, c.instructor FROM lesson_bookmarks lb JOIN courses c ON c.id = lb.course_id WHERE lb.student_id = :student_id ORDER BY lb.created_at DESC LIMIT 8');
$stmt->execute([':student_id' => $studentId]);
$saved_lessons = $stmt->fetchAll();

$avg_quiz_score = 0;
$stmt = $pdo->prepare('SELECT AVG(score) as avg_score FROM quiz_results WHERE student_id = :student_id');
$stmt->execute([':student_id' => $studentId]);
$avg_row = $stmt->fetch();
if (!empty($avg_row['avg_score'])) {
    $avg_quiz_score = (int)round((float)$avg_row['avg_score']);
}

if (empty($quiz_results)) {
    $quiz_results = [
        ['quiz_name' => 'ኩይዝ ማያሳ', 'score' => 86, 'አጠቃላይ ጥያቄ' => 100, 'status' => 'አልፈሃል', 'created_at' => date('Y-m-d H:i:s')],
    ];
}

if (empty($assignments)) {
    $assignments = [
        ['title' => 'Module Reflection', 'description' => 'Submit your short reflection on the completed lesson.', 'status' => 'Pending', 'due_date' => date('Y-m-d H:i:s', strtotime('+2 days'))],
    ];
}

if (empty($student_notes)) {
    $student_notes = [
        ['note_text' => 'Focus on completing the next lesson and revisit the quiz review section before the next exam.'],
    ];
}

if (empty($saved_courses)) {
    $saved_courses = [
        ['course_name' => 'ነገረ ሃይማኖት', 'instructor' => 'ዲ/ን ሶፎንያስ ደመቀ'],
        ['course_name' => 'ሐዋርያዊ ተልዕኮ', 'instructor' => 'ዲ/ን ሶፎንያስ ደመቀ'],
    ];
}

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የተማሪ ዳሽቦርድ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --brand: #2563eb; --brand-2: #7c3aed; --success: #16a34a; --ink: #0f172a; --muted: #475569; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background:
            radial-gradient(circle at top, rgba(191,219,254,0.35), transparent 20%),
            linear-gradient(135deg,#eef4ff 0%, #f8fafc 100%);
            color: var(--ink); margin: 0; padding: 20px; }
        .container { max-width: 1320px; margin: auto; background: rgba(255,255,255,0.92); border: 1px solid rgba(148,163,184,0.18); border-radius: 24px; box-shadow: 0 18px 45px rgba(15,23,42,0.12); padding: 24px; backdrop-filter: blur(8px); }
        .header { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; margin-bottom: 18px; }
        .header h1 { font-size: 28px; color: #111827; margin: 0 0 6px; }
        .header p { color: var(--muted); margin: 0; }
        .live-pill { display: inline-flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 999px; background: linear-gradient(135deg, rgba(37,99,235,0.12), rgba(124,58,237,0.12)); color: #1e3a8a; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 10px; }
        .live-pill span.dot { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 0 0 rgba(34,197,94,0.35); animation: pulse 1.8s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(34,197,94,0.35); } 70% { box-shadow: 0 0 0 10px rgba(34,197,94,0); } 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); } }
        .button { padding: 10px 14px; background: linear-gradient(135deg,#2563eb,#4f46e5); color: white; border: none; border-radius: 10px; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 6px; box-shadow: 0 10px 18px rgba(37,99,235,0.20); transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease; }
        .button:hover { transform: translateY(-1px); box-shadow: 0 14px 24px rgba(37,99,235,0.28); }
        .button.secondary { background: linear-gradient(135deg,#111827,#1f2937); }
        .quick-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .card { padding: 18px; border-radius: 18px; background: linear-gradient(145deg,#ffffff,#f8fbff); border: 1px solid #e5e7eb; box-shadow: 0 8px 18px rgba(148,163,184,0.12); transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease; }
        .card:hover { transform: translateY(-2px); border-color: rgba(37,99,235,0.35); box-shadow: 0 14px 28px rgba(37,99,235,0.14); }
        .card h2 { margin: 0 0 8px; font-size: 15px; color: #475569; font-weight: 700; }
        .card p { font-size: 28px; font-weight: 800; margin: 0; color: #1d4ed8; }
        .grid-2, .grid-3 { display: grid; gap: 18px; margin-bottom: 24px; }
        .grid-2 { grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
        .grid-3 { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
        .course-card, .mini-card { background: linear-gradient(135deg,#ffffff,#f8fbff); border: 1px solid #e5e7eb; border-radius: 14px; padding: 14px; }
        .course-card img { width: 100%; border-radius: 10px; height: 110px; object-fit: cover; background: linear-gradient(135deg,#dbeafe,#c4b5fd); }
        .course-card h3, .mini-card h3 { font-size: 16px; color: #111827; margin: 10px 0 6px; }
        .muted { color: #475569; font-size: 13px; }
        .rich-content h1, .rich-content h2, .rich-content h3 { font-size: 1.02rem; line-height: 1.35; margin: 0.35em 0; }
        .rich-content ul, .rich-content ol { padding-left: 18px; margin: 8px 0 10px; }
        .rich-content li { margin-bottom: 6px; }
        .rich-content p { margin: 0 0 8px; line-height: 1.5; }
        .rich-content strong, .rich-content b { font-weight: 800; }
        .rich-content em, .rich-content i { font-style: italic; }
        .rich-content u { text-decoration: underline; }
        .progress-track { width: 100%; height: 8px; background: #e5e7eb; border-radius: 999px; overflow: hidden; margin: 8px 0; }
        .progress-fill { height: 100%; background: linear-gradient(90deg,#2563eb,#38bdf8); border-radius: 999px; }
        .pill { display: inline-flex; align-items: center; padding: 5px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .pill.success { background: #dcfce7; color: #166534; }
        .pill.warning { background: #fef3c7; color: #b45309; }
        .pill.info { background: #dbeafe; color: #1d4ed8; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
        th { background: #f8fafc; color: #334155; }
        .badge { display: inline-flex; padding: 6px 10px; border-radius: 9999px; font-size: 12px; font-weight: 700; }
        .paid { background: #dcfce7; color: #166534; }
        .unpaid { background: #fee2e2; color: #b91c1c; }
        .action-link { color: #2563eb; text-decoration: none; font-weight: 700; }
        .section-title { font-size: 20px; color: #111827; margin: 0 0 6px; }
        .section-sub { color: #475569; margin-bottom: 12px; }
        .profile-box { display:flex; gap:16px; align-items:center; }
        .avatar { width: 64px; height: 64px; border-radius: 50%; display:grid; place-items:center; background: linear-gradient(135deg,#2563eb,#7c3aed); color:white; font-size: 24px; font-weight: 800; }
        @media (max-width: 991px) { .header { flex-direction: column; } .quick-actions { justify-content: flex-start; } }
        @media (max-width: 768px) { body { padding: 10px; } .container { padding: 14px; border-radius: 18px; } .stats, .grid-2, .grid-3 { grid-template-columns: 1fr; } }
        @media (max-width: 576px) { .header h1 { font-size: 24px; } .button { width: 100%; } .quick-actions { width: 100%; } .quick-actions a { flex: 1 1 auto; text-align: center; } }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <div class="live-pill"><span class="dot"></span> የላይቭ ትምህርት• <span id="liveClock">--:--</span></div>
            <h1>እንኳን በደህና መጡ, <?php echo safe($student['name']); ?></h1>
            <p>የኢሜይልዎ: <?php echo safe($student['email']); ?> • በአሁኑ ጊዜ ኮርሶችዎን ቀጥለው እና እድገትዎን ተመልከቱ።</p>
        </div>
        <div class="quick-actions">
            <a class="button" href="sofonyas%20(2).html">መጀመሪያ</a>
            <a class="button" href="tutorial.php">ኮርሶች</a>
            <a class="button" href="discussion_forum.php">ፎርም</a>
            <a class="button" href="library.php">ላይብራሪ</a>
            <a class="button secondary" href="student_logout.php">ውጣ</a>
        </div>
    </div>

    <div class="stats">
        <div class="card"><h2>ጠቅላላ የተመዘገቡ Courses</h2><p><?php echo $summary['total']; ?></p></div>
        <div class="card"><h2>የተጠናቀቁ Courses</h2><p><?php echo $completed_courses; ?></p></div>
        <div class="card"><h2>በመማር ላይ ያሉ ኮርሶች </h2><p><?php echo $in_progress_courses; ?></p></div>
        <div class="card"><h2>የተገኙ ሰርተፊኬት</h2><p><?php echo $certificates; ?></p></div>
        <div class="card"><h2>የQuiz አማካይ ውጤት</h2><p><?php echo $avg_quiz_score; ?>%</p></div>
        <div class="card"><h2>የትምህርት የእድገት ማሽሽያ (%)</h2><p><?php echo $progress_percentage; ?>%</p></div>
    </div>

    <div class="card" style="margin-bottom: 24px;">
        <h2 class="section-title">🧩 Enroll ኮርስ</h2>
        <p class="section-sub">እዚህ ኮርስ ፎቶ፣ ስም፣ አጭር መግለጫ እና ሙሉ አንቀጽ መግለጫ ከዳታቤዝ ጋር ይታያል። በአንድ ጠቅታ እንዲመዘገቡ እንደሚችሉ ይምረጡ።</p>
        <div class="grid-3">
            <?php if (empty($available_courses)): ?>
                <p class="muted">እስካሁን ምንም ኮርስ አልተጨመረም።</p>
            <?php else: ?>
                <?php foreach ($available_courses as $course): ?>
                    <div class="course-card">
                        <?php if (!empty($course['thumbnail'])): ?>
                            <img src="<?php echo safe(publicMediaUrl($course['thumbnail'])); ?>" alt="<?php echo safe($course['course_name']); ?>">
                        <?php else: ?>
                            <img src="https://images.unsplash.com/photo-1516321497487-e288fb19713f?auto=format&fit=crop&w=900&q=80" alt="Course preview">
                        <?php endif; ?>
                        <h3><?php echo safe($course['course_name']); ?></h3>
                        <div class="muted rich-content" style="margin-bottom:8px;"><?php echo renderRichText($course['short_description'] ?? ''); ?></div>
                        <div class="muted rich-content" style="margin-bottom:8px;"><?php echo renderRichText($course['description'] ?? ''); ?></div>
                        <p class="muted">ኢንስትራክተር: <?php echo safe($course['instructor'] ?? 'Admin Instructor'); ?></p>
                        <p class="muted">ዋጋ: <?php echo number_format((float)($course['price'] ?? 0), 2); ?> ብር</p>
                        <p class="muted"></p> ዋጋ: <?php echo number_format((float)($course['price'] ?? 0), 2); ?> ብር</p>
                        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">
                            <a class="button" href="student_register.php?course=<?php echo rawurlencode($course['course_name']); ?>&amount=<?php echo (float)($course['price'] ?? 0); ?>">Enroll Now</a>
                            <a class="button secondary" href="tutorial.php#courses">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <h2 class="section-title">📘 የእኔ ኮርሶች</h2>
            <p class="section-sub">ተማሪው የተመዘገበባቸው ኮርሶች እና እድገት መረጃ</p>
            <?php if (empty($registrations)): ?>
                <p class="muted">ምንም ተመዝጋቢ ኮርስ የለም።</p>
            <?php else: ?>
                <?php foreach (array_slice($registrations, 0, 3) as $row): ?>
                    <div class="course-card" style="margin-bottom: 12px;">
                        <img src="https://images.unsplash.com/photo-1516321497487-e288fb19713f?auto=format&fit=crop&w=900&q=80" alt="Course preview">
                        <h3><?php echo safe($row['course']); ?></h3>
                        <p class="muted">ኢንስትራክተር: <?php echo safe($student['name']); ?></p>
                        <div class="progress-track"><div class="progress-fill" style="width: <?php echo min(100, 45 + (int)$row['id'] % 30); ?>%;"></div></div>
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                            <span class="pill info">Progress <?php echo min(100, 45 + (int)$row['id'] % 30); ?>%</span>
                            <a class="button" href="tutorial.php">ትምህርት ቀጥል</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 class="section-title">🎯 ትምህርት ቀጥል</h2>
            <p class="section-sub">ተማሪው ያቆመበትን lesson እንዲቀጥል ይረዳል።</p>
            <div class="mini-card">
                <h3>የሚቀጥለዉ ኮርስ</h3>
                <p class="muted">ነገረ ሃይማኖት፣ነገረ ቅዱሳን፣ሐዋርያዊ ተልዕኮ፣አገልግሎት እና መንፈሳዊ ሕይወት.</p>
                <div class="progress-track"><div class="progress-fill" style="width: <?php echo $progress_percentage; ?>%;"></div></div>
                <a class="button" href="tutorial.php" style="margin-top:8px;">ካቆምክበት ቀጥል</a>
            </div>
        </div>
    </div>

    <div class="grid-3">
        <div class="card">
            <h2 class="section-title">📈 የትምህርት ፕሮግረስ</h2>
            <p class="muted">ኮርስ ፍጻሜ%</p>
            <div class="progress-track"><div class="progress-fill" style="width: <?php echo $progress_percentage; ?>%;"></div></div>
            <p class="muted">ሞጁል ፍጻሜ %: <?php echo min(100, $progress_percentage + 5); ?>%</p>
            <p class="muted">ሌሰን ፍጻሜ %: <?php echo min(100, $progress_percentage + 10); ?>%</p>
        </div>
        <div class="card">
            <h2 class="section-title">📝የፈተና ዉጤት</h2>
            <p class="section-sub">ነጥብ፣ሁኔታ እና ያለፈው የፈተና ዉጤት መረጃ</p>
            <?php foreach ($quiz_results as $quiz): ?>
                <div class="mini-card" style="margin-bottom: 10px;">
                    <h3><?php echo safe($quiz['quiz_name']); ?></h3>
                    <p class="muted">Score: <?php echo (int)($quiz['score'] ?? 0); ?> / <?php echo (int)($quiz['total_questions'] ?? 100); ?></p>
                    <span class="pill <?php echo ((string)($quiz['status'] ?? 'Passed') === 'Passed' || (int)($quiz['score'] ?? 0) >= 50) ? 'success' : 'warning'; ?>"><?php echo safe($quiz['status'] ?? 'Passed'); ?></span>
                    <p class="muted" style="margin-top:8px;">Date: <?php echo date('M d, Y', strtotime($quiz['created_at'] ?? date('Y-m-d H:i:s'))); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="card">
            <h2 class="section-title">🧾 ሠርተፊኬቶች</h2>
            <p class="section-sub">የተጠናቀቁ ኮርሶች እና ሠርተፊኬት መመልከቻ </p>
            <?php if (empty($student_certificates)): ?>
                <p class="muted">ምንም ሠርተፊኬት የለም.</p>
            <?php else: ?>
                <?php foreach ($student_certificates as $cert): ?>
                    <div class="mini-card" style="margin-bottom: 10px;">
                        <h3><?php echo safe($cert['exam_type']); ?></h3>
                        <p class="muted">ነጥብ: <?php echo (int)$cert['score']; ?> / <?php echo (int)$cert['total_questions']; ?></p>
                        <a class="button" href="admin_certificate.php?download=<?php echo (int)$cert['id']; ?>">Download PDF</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <h2 class="section-title">📌 አሳይመንት</h2>
            <p class="section-sub">በጅምር ላይ ያለ፣የገባ፣እና የተሰጠዉ የስራ ተግባር(graded tasks)</p>
            <div class="grid-3">
                <div class="mini-card"><h3>Pending</h3><p style="font-size:24px; font-weight:800; color:#1d4ed8;">1</p></div>
                <div class="mini-card"><h3>Submitted</h3><p style="font-size:24px; font-weight:800; color:#1d4ed8;">2</p></div>
                <div class="mini-card"><h3>Graded</h3><p style="font-size:24px; font-weight:800; color:#1d4ed8;">1</p></div>
            </div>
            <?php foreach ($assignments as $item): ?>
                <div class="mini-card" style="margin-top: 10px;">
                    <h3><?php echo safe($item['title']); ?></h3>
                    <div class="muted rich-content"><?php echo renderRichText($item['description'] ?? ''); ?></div>
                    <p class="muted">Status: <?php echo safe($item['status']); ?> | Due: <?php echo date('M d, Y', strtotime($item['due_date'])); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="card">
            <h2 class="section-title">📢 መግለጫ ማስታወቂያ</h2>
            <p class="section-sub">የአድሚኑ ወይም የኢንስትራክተሩ መልእክት</p>
            <ul style="padding-left:18px; color:#334155; margin:0;">
                <?php foreach ($student_events as $event): ?>
                    <li style="margin-bottom:8px;"><strong><?php echo safe($event['event_title']); ?></strong> — <span class="rich-content"><?php echo renderRichText($event['event_description'] ?? ''); ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <h2 class="section-title">🔔 ማንቂያ ማስታወቂያ</h2>
            <p class="section-sub">አዲስ ኮርስ፣ኩይዝ፣አሳይመንት እና ሠርተፊኬት ማንቂያ</p>
            <ul style="padding-left:18px; color:#334155; margin:0;">
                <?php foreach ($notifications as $note): ?>
                    <li style="margin-bottom:8px;"><span class="rich-content"><?php echo renderRichText($note['message'] ?? ''); ?></span> <small style="color:#64748b;">(<?php echo date('Y-m-d H:i', strtotime($note['created_at'])); ?>)</small></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="card">
            <h2 class="section-title">📅 Calendar</h2>
            <p class="section-sub">የፈተና ቀን፣መመሪያዎች እና ላይቭ ክላስ ስኬጁል</p>
            <?php if (!empty($student_exam_reminders)): ?>
                <?php foreach ($student_exam_reminders as $reminder): ?>
                    <div class="mini-card" style="margin-bottom:10px;">
                        <h3><?php echo safe($reminder['exam_type']); ?></h3>
                        <p class="muted">Exam Date: <?php echo date('M d, Y H:i', strtotime($reminder['exam_date'])); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="muted">ምንም የፈተና መርሃ ግብር የለም</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <h2 class="section-title">👤 የተማሪዎች ፕሮፋይል</h2>
            <div class="profile-box">
                <div class="avatar"><?php echo strtoupper(substr(safe($student['name']),0,1)); ?></div>
                <div>
                    <p style="margin:0; font-weight:800; color:#111827;"><?php echo safe($student['name']); ?></p>
                    <p class="muted">ኢሜይል: <?php echo safe($student['email']); ?></p>
                    <p class="muted">ስ.ቁጥር: <?php echo safe($student['phone'] ?? 'N/A'); ?></p>
                    <p class="muted">ሀገር: <?php echo safe($student['country'] ?? 'ኢትዮጵያ'); ?></p>
                </div>
            </div>
        </div>
        <div class="card">
            <h2 class="section-title">⚙️ አካውንት ማስተካከያ</h2>
            <p class="section-sub">ፓስዋርድ መቀየር፣ፕሮፋይል መቀየር እና ፎቶ መጨመር</p>
            <a class="button secondary" href="student_register.php" style="margin-right:8px;">ፕሮፋይል ቀይር</a>
            <a class="button" href="student_logout.php">ዉጣ</a>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <h2 class="section-title">💬 መወያያ ፎርም</h2>
            <p class="section-sub">ተማሪዎች ማንኛውንም ጥያቄ መጥየቅ፣ሀሳባቸውን መግለጥ እና ከዉይይቱ ንቁ ተሳትፎ ማድረግ ይችላሉ።</p>
            <a class="button" href="discussion_forum.php">መወያያ ፎርሙን ክፈት</a>
        </div>
        <div class="card">
            <h2 class="section-title">🗒️ ማስታወሻ ክፍል</h2>
            <p class="section-sub">እባክዎ ከዚህ የሚለቀቁ ትምህርቶችን በአግባቡ ይያዙ</p>
            <?php foreach ($student_notes as $note): ?>
                <div class="mini-card" style="margin-bottom:10px;">
                    <div class="muted rich-content"><?php echo renderRichText($note['note_text'] ?? ''); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <h2 class="section-title">⭐ የተቀመጡ ኮርሶች</h2>
        <p class="section-sub">የተቀመጡ ትምህርቶችን እና የተወደዱ ኮርሶችን እዚህ ይመልከቱ።</p>
        <div class="grid-3">
            <?php foreach ($saved_lessons as $item): ?>
                <div class="mini-card">
                    <h3><?php echo safe($item['lesson_title']); ?></h3>
                    <p class="muted">ኢንስትራክተር: <?php echo safe($item['instructor'] ?? 'Staff'); ?></p>
                    <a class="button" href="tutorial.php">ሌሰን ክፈት</a>
                </div>
            <?php endforeach; ?>
            <?php foreach ($saved_courses as $item): ?>
                <div class="mini-card">
                    <h3><?php echo safe($item['course_name']); ?></h3>
                    <p class="muted">ኢንስትራክተር: <?php echo safe($item['instructor'] ?? 'Staff'); ?></p>
                    <a class="button" href="tutorial.php">ኮርስ ተመልከት</a>
                </div>
            <?php endforeach; ?>
            <?php if (empty($saved_lessons) && empty($saved_courses)): ?>
                <div class="mini-card">
                    <h3>ምንም ሴቭ የተደረገ ዝርዝር የለም</h3>
                    <p class="muted">ከዚህ ሴቭ ተደርገው የተቀመጡ የትምህርት ክፍሎችን የገጽ ማስታወሽ በተን ተጠቀም.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="margin-top: 24px;">
        <h2 class="section-title">📚 የተመዘገቡ ኮርሶች</h2>
        <p class="section-sub">የኮርስ ዝርዝር፣ዋጋ፣የክፍያ ሁኔታ እና ቀጣይ እርምጃ</p>
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
                            <td><?php if ($row['payment_status'] !== 'paid'): ?><a class="action-link" href="payment.php?id=<?php echo urlencode($row['id']); ?>">ክፍያ አረጋግጥ</a><?php else: ?>ተከፍሏል<?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<script>
  (function () {
    var clock = document.getElementById('liveClock');
    if (!clock) return;
    function updateClock() {
      var now = new Date();
      clock.textContent = now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
    updateClock();
    setInterval(updateClock, 1000);
  })();
</script>
</body>
</html>
