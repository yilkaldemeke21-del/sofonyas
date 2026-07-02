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
$student = is_array($student) ? $student : [];
$studentName = $student['name'] ?? $student['student_name'] ?? 'ተማሪ';
$studentEmail = $student['email'] ?? '';

$chatMessageText = '';
$chatMessageError = '';
$chatSuccessMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_message'])) {
    $chatMessageText = trim((string)$_POST['chat_message']);

    if ($chatMessageText !== '') {
        $stmt = $pdo->prepare('INSERT INTO site_chat_messages (sender_type, sender_name, message, status) VALUES (:sender_type, :sender_name, :message, :status)');
        $stmt->execute([
            ':sender_type' => 'student',
            ':sender_name' => $studentName,
            ':message' => $chatMessageText,
            ':status' => 'new',
        ]);
        $chatSuccessMessage = 'የእርስዎ መልእክት ተላከ። አስተዳዳሪ በቅርቡ ይመልሳል።';
    } else {
        $chatMessageError = 'እባክዎ መልእክት ያስገቡ።';
    }
}

$stmt = $pdo->query('SELECT * FROM site_chat_messages ORDER BY created_at DESC LIMIT 8');
$chatMessages = $stmt->fetchAll();

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

$stmt = $pdo->prepare('SELECT r.*, c.id AS course_id, c.course_code, c.short_description, c.description, c.instructor, c.thumbnail, c.price AS course_price, c.category, c.level FROM registrations r LEFT JOIN courses c ON c.id = r.course_id OR c.course_name = r.course WHERE r.student_id = :student_id ORDER BY r.created_at DESC');
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

$stmt = $pdo->prepare('SELECT COUNT(*) as total FROM lesson_progress WHERE student_id = :student_id');
$stmt->execute([':student_id' => $studentId]);
$completed_lessons = (int)$stmt->fetch()['total'];

$stmt = $pdo->prepare('SELECT COUNT(*) as total FROM certificates WHERE student_id = :student_id');
$stmt->execute([':student_id' => $studentId]);
$certificates = (int)$stmt->fetch()['total'];

$progress_percentage = 0;
if ($enrolled_courses > 0) {
    $progress_percentage = (int)min(100, round((($completed_lessons * 20) + ($certificates * 30) + ($paid_courses * 10)) / max(1, $enrolled_courses * 10) ));
}

$quizAttempts = 0;
$perfect_quiz_scores = 0;
$quiz_total_score = 0;
$quiz_total_questions = 0;
$avg_quiz_percentage = 0;
$examAttempts = 0;
$exam_total_score = 0;
$exam_total_questions = 0;
$exam_percentage = 0;

try {
    $stmt = $pdo->prepare('SELECT COUNT(*) as total, SUM(score) as score_sum, SUM(total_questions) as total_questions, SUM(CASE WHEN score = total_questions AND total_questions > 0 THEN 1 ELSE 0 END) as perfect_scores FROM quiz_results WHERE student_id = :student_id');
    $stmt->execute([':student_id' => $studentId]);
    $quizData = $stmt->fetch();
    $quizAttempts = (int)$quizData['total'];
    $quiz_total_score = (int)$quizData['score_sum'];
    $quiz_total_questions = (int)$quizData['total_questions'];
    $perfect_quiz_scores = (int)$quizData['perfect_scores'];
    if ($quiz_total_questions > 0) {
        $avg_quiz_percentage = (int)round(($quiz_total_score / $quiz_total_questions) * 100);
    }
} catch (Throwable $e) {
    $avg_quiz_percentage = 0;
}

try {
    $stmt = $pdo->prepare('SELECT COUNT(*) as total, SUM(score) as score_sum, SUM(total_questions) as total_questions FROM exam_submissions WHERE student_id = :student_id');
    $stmt->execute([':student_id' => $studentId]);
    $examData = $stmt->fetch();
    $examAttempts = (int)$examData['total'];
    $exam_total_score = (int)$examData['score_sum'];
    $exam_total_questions = (int)$examData['total_questions'];
    if ($exam_total_questions > 0) {
        $exam_percentage = (int)round(($exam_total_score / $exam_total_questions) * 100);
    }
} catch (Throwable $e) {
    $exam_percentage = 0;
}

$activityDates = [];
try {
    $stmt = $pdo->prepare('SELECT activity_date FROM (
        SELECT DATE(completed_at) AS activity_date FROM lesson_progress WHERE student_id = :student_id
        UNION
        SELECT DATE(created_at) AS activity_date FROM quiz_results WHERE student_id = :student_id
        UNION
        SELECT DATE(submitted_at) AS activity_date FROM exam_submissions WHERE student_id = :student_id
    ) AS activity_dates ORDER BY activity_date DESC');
    $stmt->execute([':student_id' => $studentId]);
    $activityDates = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'activity_date');
} catch (Throwable $e) {
    $activityDates = [];
}

$activitySet = array_fill_keys($activityDates, true);
$learningStreak = 0;
$today = new DateTime('today');
for ($day = 0; $day < 14; $day++) {
    $dateKey = $today->modify($day === 0 ? 'today' : '-1 day')->format('Y-m-d');
    if (isset($activitySet[$dateKey])) {
        $learningStreak++;
    } else {
        break;
    }
}

$gamificationPoints = (int)($completed_lessons * 10 + $certificates * 80 + $paid_courses * 30 + $learningStreak * 25 + round($avg_quiz_percentage * 2.4) + round($exam_percentage * 3) + $perfect_quiz_scores * 40 + $quizAttempts * 5 + $examAttempts * 8);

$gamificationLevel = 'Novice';
$levelThresholds = [
    'Novice' => 0,
    'Apprentice' => 100,
    'Learner' => 250,
    'Scholar' => 500,
    'Master' => 900,
    'Champion' => 1500,
];
foreach ($levelThresholds as $levelName => $threshold) {
    if ($gamificationPoints >= $threshold) {
        $gamificationLevel = $levelName;
    }
}

$nextLevelName = '';
$pointsToNextLevel = 0;
foreach ($levelThresholds as $levelName => $threshold) {
    if ($gamificationPoints < $threshold) {
        $nextLevelName = $levelName;
        $pointsToNextLevel = $threshold - $gamificationPoints;
        break;
    }
}
if ($nextLevelName === '') {
    $nextLevelName = 'Champion';
}

$gamificationBadges = [];
if ($enrolled_courses > 0) {
    $gamificationBadges[] = ['name' => 'የመጀመሪያ ኮርስ ተመዝገበ', 'description' => 'ነዳጅ መጀመሪያ ኮርስዎን አሳዩ።'];
}
if ($completed_lessons >= 1) {
    $gamificationBadges[] = ['name' => 'Lesson Starter', 'description' => 'ከመጀመሪያ የትምህርት ክፍል ተጠናቀቀ.'];
}
if ($quizAttempts >= 3) {
    $gamificationBadges[] = ['name' => 'Quiz Challenger', 'description' => 'በQuiz ላይ 3 ወይም ከዚያ በላይ ጥያቄ ሰርተዋል.'];
}
if ($examAttempts >= 1) {
    $gamificationBadges[] = ['name' => 'Exam Finisher', 'description' => 'ከፈተና ጋር ስራ የጨረሱ.'];
}
if ($certificates >= 1) {
    $gamificationBadges[] = ['name' => 'Certificate Collector', 'description' => 'ከፈለጉት ትምህርቶች ሠርተፊኬት አንዱ አገኙ.'];
}
if ($perfect_quiz_scores >= 1) {
    $gamificationBadges[] = ['name' => 'Perfect Score', 'description' => 'ኩይዝ ውስጥ 100% ውጤት ሰርተዋል.'];
}
if ($paid_courses >= 1) {
    $gamificationBadges[] = ['name' => 'Payment Pro', 'description' => 'ከኮርስ ክፍያ ደረሱ.'];
}
if ($learningStreak >= 3) {
    $gamificationBadges[] = ['name' => 'Learning Streak', 'description' => 'ለቀጣይ 3 ቀናት ዕለታዊ እንቅስቃሴ አደረጉ.'];
}
if ($gamificationPoints >= 900) {
    $gamificationBadges[] = ['name' => 'Master Learner', 'description' => 'እርስዎ ከ900 የጎን ነው.'];
}
if (empty($gamificationBadges)) {
    $gamificationBadges[] = ['name' => 'Welcome Starter', 'description' => 'እርስዎ የመጀመሪያ እርምጃዎችን እየወሰነ ነው.'];
}

try {
    $pdo->prepare('INSERT INTO student_gamification (student_id, points, level, streak_days, last_activity) VALUES (:student_id, :points, :level, :streak_days, :last_activity) ON DUPLICATE KEY UPDATE points = VALUES(points), level = VALUES(level), streak_days = VALUES(streak_days), last_activity = VALUES(last_activity), updated_at = NOW()')
        ->execute([
            ':student_id' => $studentId,
            ':points' => $gamificationPoints,
            ':level' => $gamificationLevel,
            ':streak_days' => $learningStreak,
            ':last_activity' => date('Y-m-d H:i:s'),
        ]);

    foreach ($gamificationBadges as $badge) {
        $badgeKey = strtolower(preg_replace('/[^a-z0-9]+/', '_', $badge['name']));
        $pdo->prepare('INSERT IGNORE INTO student_badges (student_id, badge_key, badge_name, description) VALUES (:student_id, :badge_key, :badge_name, :description)')
            ->execute([
                ':student_id' => $studentId,
                ':badge_key' => $badgeKey,
                ':badge_name' => $badge['name'],
                ':description' => $badge['description'],
            ]);
    }
} catch (Throwable $e) {
    // Preserve dashboard display even if gamification persistence fails.
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

$stmt = $pdo->query('SELECT qs.id, qs.title, qs.instruction, COUNT(q.id) AS question_count FROM question_sections qs LEFT JOIN questions q ON q.section_id = qs.id GROUP BY qs.id ORDER BY qs.created_at ASC');
$exam_sections = $stmt->fetchAll();

$activeExamLinks = [];
function normalizeExamType(string $examType): string
{
    $value = strtolower(trim($examType));
    $value = preg_replace('/\s+/', '_', $value);
    if (in_array($value, ['mid', 'midexam', 'mid_exam'], true)) {
        return 'mid_exam';
    }
    if (in_array($value, ['final', 'finalexam', 'final_exam'], true)) {
        return 'final_exam';
    }
    return $value;
}

foreach (['mid_exam', 'final_exam'] as $type) {
    $stmt = $pdo->prepare('SELECT * FROM quiz_link_generators WHERE REPLACE(LOWER(exam_type), \' \' , \'_\') = :exam_type ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([':exam_type' => $type]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($link) {
        $expiresAt = strtotime($link['created_at'] ?? '0') + max(1, (int)$link['expiry_minutes']) * 60;
        if (time() <= $expiresAt) {
            $activeExamLinks[$type] = $link;
        }
    }
}

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
$studentEmail = $student['email'] ?? '';
$stmt = $pdo->prepare('SELECT * FROM email_notifications WHERE recipient_email = :email ORDER BY sent_at DESC LIMIT 5');
$stmt->execute([':email' => $studentEmail]);
$student_email_notifications = $stmt->fetchAll();

$stmt = $pdo->query('SELECT cu.*, c.course_name FROM course_updates cu JOIN courses c ON cu.course_id = c.id ORDER BY cu.created_at DESC LIMIT 5');
$student_course_updates = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM exam_reminders WHERE student_id = :student_id OR student_id = "ALL" ORDER BY created_at DESC LIMIT 5');
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
$studentEmail = $student['email'] ?? '';
$stmt = $pdo->prepare('SELECT * FROM email_notifications WHERE recipient_email = :email ORDER BY sent_at DESC LIMIT 5');
$stmt->execute([':email' => $studentEmail]);
$student_email_notifications = $stmt->fetchAll();

$stmt = $pdo->query('SELECT cu.*, c.course_name FROM course_updates cu JOIN courses c ON cu.course_id = c.id ORDER BY cu.created_at DESC LIMIT 5');
$student_course_updates = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM exam_reminders WHERE student_id = :student_id OR student_id = "ALL" ORDER BY created_at DESC LIMIT 5');
$stmt->execute([':student_id' => $studentId]);
$student_exam_reminders = $stmt->fetchAll();

$stmt = $pdo->query('SELECT * FROM event_announcements ORDER BY event_date DESC LIMIT 5');
$student_events = $stmt->fetchAll();

$stmt = $pdo->query('SELECT * FROM live_class_sessions ORDER BY session_date ASC, id DESC LIMIT 4');
$live_sessions = $stmt->fetchAll();

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
        .header p { color: var(--muted); margin: 0; font-size: 15px; line-height: 1.55; }
        .live-pill { display: inline-flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 999px; background: linear-gradient(135deg, rgba(37,99,235,0.12), rgba(124,58,237,0.12)); color: #1e3a8a; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 10px; }
        .live-pill span.dot { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 0 0 rgba(34,197,94,0.35); animation: pulse 1.8s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(34,197,94,0.35); } 70% { box-shadow: 0 0 0 10px rgba(34,197,94,0); } 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); } }
        :root {
            --page-bg: #f7f9fc;
            --surface: #ffffff;
            --surface-2: #f8fbff;
            --surface-3: #f8fafc;
            --text: #0f172a;
            --muted: #475569;
            --border: #e5e7eb;
            --primary: #2563eb;
            --primary-2: #4f46e5;
            --shadow: rgba(148,163,184,0.12);
        }
        body {
            background: var(--page-bg);
            color: var(--text);
            transition: background 0.2s ease, color 0.2s ease;
        }
        body[data-theme="dark"] {
            --page-bg: #0f172a;
            --surface: #111827;
            --surface-2: #0b1220;
            --surface-3: #0f172a;
            --text: #e5eefb;
            --muted: #94a3b8;
            --border: #1f2937;
            --primary: #8b5cf6;
            --primary-2: #6d28d9;
            --shadow: rgba(15,23,42,0.45);
        }
        .button { padding: 10px 14px; background: linear-gradient(135deg,#2563eb,#4f46e5); color: white; border: none; border-radius: 10px; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 6px; box-shadow: 0 10px 18px rgba(37,99,235,0.20); transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease; }
        .button:hover { transform: translateY(-1px); box-shadow: 0 14px 24px rgba(37,99,235,0.28); }
        .button.secondary { background: linear-gradient(135deg,#111827,#1f2937); }
        .theme-toggle {
            background: linear-gradient(135deg, rgba(37,99,235,0.12), rgba(124,58,237,0.12));
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 10px 14px;
            cursor: pointer;
            font-weight: 700;
        }
        .quick-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; }
        .account-actions { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-top: 12px; }
        .account-actions .button { min-width: 150px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .card { padding: 18px; border-radius: 18px; background: linear-gradient(145deg, var(--surface), var(--surface-2)); border: 1px solid var(--border); box-shadow: 0 8px 18px var(--shadow); transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease; }
        .card:hover { transform: translateY(-2px); border-color: rgba(37,99,235,0.35); box-shadow: 0 14px 28px rgba(37,99,235,0.14); }
        .card h2 { margin: 0 0 8px; font-size: 15px; color: var(--muted); font-weight: 700; }
        .card p { font-size: 17px; font-weight: 800; margin: 0; color: var(--primary); }
        .section-title { font-size: 20px; color: #ca1484; margin: 0 0 6px; font-weight: 800; }
        .section-sub { color: #475569; margin-bottom: 12px; font-size: 14px; line-height: 1.55; }
        .grid-2, .grid-3 { display: grid; gap: 18px; margin-bottom: 24px; }
        .grid-2 { grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
        .grid-3 { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
        .course-card, .mini-card { background: linear-gradient(135deg, var(--surface), var(--surface-3)); border: 1px solid var(--border); border-radius: 14px; padding: 14px; color: var(--text); }
        .course-card img { width: 220px; border-radius: 50px; height: 310px; object-fit: cover; background: linear-gradient(135deg,#dbeafe,#c4b5fd); }
        .course-card h3, .mini-card h3 { font-size: 20px; color: var(--text); margin: 10px 0 6px; font-weight: 800; line-height: 1.35; }
        .muted { color: var(--muted); font-size: 16px; line-height: 1.35; font-weight: 500; }
        .rich-content h1, .rich-content h2, .rich-content h3 { font-size: 16px; line-height: 1.35; margin: 0.35em 0; }
        .rich-content ul, .rich-content ol { padding-left: 18px; margin: 8px 0 10px; }
        .rich-content li { margin-bottom: 6px; }
        .rich-content p { margin: 0 0 8px; line-height: 1.5; }
        .rich-content strong, .rich-content b { font-weight: 800; }
        .rich-content em, .rich-content i { font-style: italic; }
        .rich-content u { text-decoration: underline; }
        .progress-track { width: 100%; height: 8px; background: #e5e7eb; border-radius: 999px; overflow: hidden; margin: 8px 0; }
        .progress-fill { font-size: 16px; height: 100%; background: linear-gradient(90deg,#2563eb,#38bdf8); border-radius: 999px; }
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
        .instructor-line {font-size: 16px; font-weight: 600; color: #6b16cb; }
        .info-note { font-size: 14px; line-height: 1.5; color: #334155; }
        .profile-box { display:flex; gap:16px; align-items:center; }
        .avatar { width: 64px; height: 64px; border-radius: 50%; display:grid; place-items:center; background: linear-gradient(135deg,#2563eb,#7c3aed); color:white; font-size: 24px; font-weight: 800; }
        @media (max-width: 991px) { .header { flex-direction: column; } .quick-actions { justify-content: flex-start; } }
        @media (max-width: 768px) { body { padding: 10px; } .container { padding: 14px; border-radius: 18px; } .stats, .grid-2, .grid-3 { grid-template-columns: 1fr; } }
        @media (max-width: 576px) { .header h1 { font-size: 24px; } .button { width: 100%; } .quick-actions { width: 100%; } .quick-actions a { flex: 1 1 auto; text-align: center; } .account-actions { flex-direction: column; align-items: stretch; } .account-actions .button { width: 100%; min-width: 0; } }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <div class="live-pill"><span class="dot"></span> የላይቭ ትምህርት• <span id="liveClock">--:--</span></div>
            <h1>እንኳን በደህና መጡ, <?php echo safe($studentName); ?></h1>
            <p>የኢሜይልዎ: <?php echo safe($studentEmail); ?> • በአሁኑ ጊዜ ኮርሶችዎን ቀጥለው እና እድገትዎን ተመልከቱ።</p>
        </div>
        <div class="quick-actions">
            <button class="theme-toggle" id="themeToggle" type="button" aria-label="Toggle theme">🌙 Dark</button>
            <a class="button" href="sofonyas%20(2).html">መጀመሪያ</a>
            <a class="button" href="tutorial.php">ኮርሶች</a>
            <a class="button" href="my_courses.php">My Courses</a>
            <a class="button" href="live_class.php">Live Class</a>
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

    <div class="card" id="aiRecommendationCard" style="margin-bottom: 24px;">
        <h2 class="section-title">🤖 AI Course Recommendations</h2>
        <p class="section-sub">የትምህርት ማስረጃዎችን እና ኮርስ ማጣሪያዎችን እንዲሁም የሚገባዎትን ኮርሶች ይገናኙ።</p>
        <div id="aiRecommendations" style="display:grid; gap:14px; margin-top: 16px;">
            <p class="muted">Loading personalized recommendations...</p>
        </div>
    </div>

    <div class="grid-3" style="margin-bottom: 24px;">
        <div class="card">
            <h2 class="section-title">🎖️ Gamification Points</h2>
            <p class="section-sub">የተማሪ እርምጃ እና ማስተዋል ስሜት</p>
            <p class="muted" style="font-size: 28px; font-weight: 800; color: #2563eb; margin: 14px 0 8px;"><?php echo $gamificationPoints; ?> pts</p>
            <div class="progress-track"><div class="progress-fill" style="width: <?php echo min(100, $gamificationPoints / 15); ?>%;"></div></div>
            <p class="muted" style="margin-top: 10px;">Level: <strong><?php echo safe($gamificationLevel); ?></strong></p>
            <?php if ($pointsToNextLevel > 0): ?>
                <p class="muted"><?php echo $pointsToNextLevel; ?> points to <?php echo safe($nextLevelName); ?></p>
            <?php else: ?>
                <p class="muted">You are at the top level.</p>
            <?php endif; ?>
        </div>
        <div class="card">
            <h2 class="section-title">🔥 Learning Streak</h2>
            <p class="section-sub">ቀን በቀን የመማር ስኬት</p>
            <p class="muted" style="font-size: 28px; font-weight: 800; color: #7c3aed; margin: 14px 0 8px;"><?php echo $learningStreak; ?> days</p>
            <p class="muted">Keep the momentum going with daily activity from lessons, quizzes, or exams.</p>
            <div style="margin-top: 12px;"><span class="pill success">Active days tracked</span></div>
        </div>
        <div class="card">
            <h2 class="section-title">🏅 Current Badges</h2>
            <p class="section-sub">እንደ እርስዎ ተገናኝቷል ምልክቶች</p>
            <div style="display:grid; gap: 10px; margin-top: 12px;">
                <?php foreach ($gamificationBadges as $badge): ?>
                    <div class="mini-card" style="padding: 12px 14px; border-radius: 16px; background: #eef2ff; border: 1px solid #c7d2fe;">
                        <strong><?php echo safe($badge['name']); ?></strong>
                        <p class="muted" style="margin: 6px 0 0; font-size: 14px; line-height: 1.5;"><?php echo safe($badge['description']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 24px;">
        <h2 class="section-title">🧠 Exam Portal</h2>
        <p class="section-sub">በአድሚኑ የተፈጠሩ ሴክሽኖችን እና ጥያቄዎችን እዚህ ይመልከቱ። እባኮትን በዚህ ወቅት የሚገኙትን የፈተና ሊንኮች ተጠቀሙ።</p>
        <?php if (empty($exam_sections)): ?>
            <p class="muted">ጥያቄ ክፍሎች አልተሰሩም። እባኮትን ከአድሚኑ ጋር እየተገናኙ ክፍሎችን ያክሉ።</p>
        <?php else: ?>
            <div class="grid-3" style="margin-bottom: 18px;">
                <?php foreach ($exam_sections as $section): ?>
                    <div class="mini-card">
                        <span class="pill info">Section</span>
                        <h3><?php echo safe($section['title'] ?? 'Untitled Section'); ?></h3>
                        <p class="muted"><?php echo safe($section['instruction'] ?: 'No instruction provided.'); ?></p>
                        <p class="muted"><strong><?php echo (int)$section['question_count']; ?></strong> Questions</p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div style="display:flex; flex-wrap:wrap; gap:12px;">
            <a class="button" href="<?php echo safe($activeExamLinks['mid_exam']['link_url'] ?? 'mid_exam.php'); ?>">Start Mid Exam</a>
            <a class="button secondary" href="<?php echo safe($activeExamLinks['final_exam']['link_url'] ?? 'final_exam.php'); ?>">Start Final Exam</a>
        </div>
        <div style="margin-top: 12px; display:flex; gap:12px; flex-wrap:wrap;">
            <?php if (empty($activeExamLinks['mid_exam'])): ?><span class="pill warning">No active mid exam link</span><?php endif; ?>
            <?php if (empty($activeExamLinks['final_exam'])): ?><span class="pill warning">No active final exam link</span><?php endif; ?>
        </div>
    </div>

    <div class="card" style="margin-bottom: 24px;">
        <h2 class="section-title">📡 Live Classes</h2>
        <p class="section-sub">እዚህ ከዳታቤዝ ጋር ተገናኝተው የላይቭ ስትሬም ወይም የቪርቹዋል ትምህርት ክፍል ማገናኘት ይችላሉ።</p>
        <?php if (empty($live_sessions)): ?>
            <p class="muted">አሁን ምንም ላይቭ ክላስ አልተዘጋጀም።</p>
        <?php else: ?>
            <div class="grid-3">
                <?php foreach ($live_sessions as $session): ?>
                    <div class="mini-card">
                        <span class="pill success">Live</span>
                        <h3><?php echo safe($session['title'] ?? 'Live Session'); ?></h3>
                        <p class="muted" style="margin-top:6px;"><?php echo safe(!empty($session['description']) ? $session['description'] : 'Join this live learning session now.'); ?></p>
                        <p class="muted"><strong>Time:</strong> <?php echo !empty($session['session_date']) ? safe(date('D, M d Y H:i', strtotime($session['session_date']))) : 'Time not set'; ?></p>
                        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">
                            <?php if (!empty($session['stream_url'])): ?>
                                <a class="button" href="<?php echo safe($session['stream_url']); ?>" target="_blank" rel="noopener">
                                    <?php echo safe((strpos(strtolower((string)$session['stream_url']), 'meet.google.com') !== false || strpos(strtolower((string)$session['stream_url']), 'google.com/meet') !== false) ? 'Join Google Meet' : (strpos(strtolower((string)$session['stream_url']), 'zoom') !== false ? 'Join Zoom' : 'Open Stream Link')); ?>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($session['room_url'])): ?>
                                <a class="button secondary" href="<?php echo safe($session['room_url']); ?>" target="_blank" rel="noopener">
                                    <?php echo safe((strpos(strtolower((string)$session['room_url']), 'meet.google.com') !== false || strpos(strtolower((string)$session['room_url']), 'google.com/meet') !== false) ? 'Join Google Meet' : (strpos(strtolower((string)$session['room_url']), 'zoom') !== false ? 'Join Zoom' : 'Open Meeting Link')); ?>
                                </a>
                            <?php endif; ?>
                            <?php if (empty($session['stream_url']) && empty($session['room_url'])): ?>
                                <span class="pill warning">No link yet</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
                            <img src="IMG_20241202_031425_251.jpg" alt="Course preview">
                        <?php endif; ?>
                        <h3><?php echo safe($course['course_name']); ?></h3>
                        <div class="muted rich-content" style="margin-bottom:8px;"><?php echo renderRichText($course['short_description'] ?? ''); ?></div>
                        <div class="muted rich-content" style="margin-bottom:8px;"><?php echo renderRichText($course['description'] ?? ''); ?></div>
                        <p class="muted"><span class="instructor-line">ኢንስትራክተር:</span> <?php echo safe($course['instructor'] ?? 'Admin Instructor'); ?></p>
                        <p class="muted"><span class="instructor-line">ዋጋ:</span> <?php echo number_format((float)($course['price'] ?? 0), 2); ?> ብር</p>
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
                    <?php $courseName = $row['course'] ?: ($row['course_name'] ?? 'Course'); ?>
                    <?php $courseThumb = !empty($row['thumbnail']) ? publicMediaUrl($row['thumbnail']) : 'IMG_20241202_031425_251.jpg'; ?>
                    <?php $courseDesc = $row['short_description'] ?: $row['description'] ?: 'ይህ ኮርስ ለእርስዎ ተዘጋጅቷል።'; ?>
                    <?php $progressValue = (!empty($row['payment_status']) && $row['payment_status'] === 'paid') ? 65 : 35; ?>
                    <div class="course-card" style="margin-bottom: 12px;">
                        <img src="<?php echo safe($courseThumb); ?>" alt="<?php echo safe($courseName); ?>">
                        <h3><?php echo safe($courseName); ?></h3>
                        <div class="muted rich-content" style="margin-bottom:8px;"><?php echo renderRichText($courseDesc); ?></div>
                        <p class="muted">ኢንስትራክተር: <?php echo safe($row['instructor'] ?? 'Admin Instructor'); ?></p>
                        <div class="progress-track"><div class="progress-fill" style="width: <?php echo $progressValue; ?>%;"></div></div>
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                            <span class="pill info">Progress <?php echo $progressValue; ?>%</span>
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
            <h2 class="section-title">📝 የተማሪ ውጤቶች</h2>
            <p class="section-sub">Quiz Name · Date · Taken Score · Percentage · Status · View · Details · Print Result</p>
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:14px;">
                    <thead>
                        <tr style="border-bottom:1px solid #e2e8f0;">
                            <th style="text-align:left; padding:8px 6px;">Quiz Name</th>
                            <th style="text-align:left; padding:8px 6px;">Date</th>
                            <th style="text-align:left; padding:8px 6px;">Taken Score</th>
                            <th style="text-align:left; padding:8px 6px;">Percentage</th>
                            <th style="text-align:left; padding:8px 6px;">Status</th>
                            <th style="text-align:left; padding:8px 6px;">View</th>
                            <th style="text-align:left; padding:8px 6px;">Details</th>
                            <th style="text-align:left; padding:8px 6px;">Print</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($quiz_results)): ?>
                            <tr><td colspan="8" class="muted" style="padding:10px 6px;">No results yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($quiz_results as $quiz): ?>
                                <?php $score = (int)($quiz['score'] ?? 0); $total = max(1, (int)($quiz['total_questions'] ?? 100)); $percentage = (int)round(($score / $total) * 100); $status = (string)($quiz['status'] ?? 'Passed'); $statusClass = ($status === 'Passed' || $score >= 50) ? 'success' : 'warning'; ?>
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:8px 6px;"><strong><?php echo safe($quiz['quiz_name']); ?></strong></td>
                                    <td style="padding:8px 6px;"><?php echo date('M d, Y', strtotime($quiz['created_at'] ?? date('Y-m-d H:i:s'))); ?></td>
                                    <td style="padding:8px 6px;"><?php echo $score; ?> / <?php echo $total; ?></td>
                                    <td style="padding:8px 6px;"><?php echo $percentage; ?>%</td>
                                    <td style="padding:8px 6px;"><span class="pill <?php echo $statusClass; ?>"><?php echo safe($status); ?></span></td>
                                    <td style="padding:8px 6px;"><a class="button secondary" href="results.php?view=<?php echo (int)($quiz['id'] ?? 0); ?>">View</a></td>
                                    <td style="padding:8px 6px;"><a class="button secondary" href="results.php?details=<?php echo (int)($quiz['id'] ?? 0); ?>">Details</a></td>
                                    <td style="padding:8px 6px;"><a class="button" href="results.php?print=<?php echo (int)($quiz['id'] ?? 0); ?>">Print</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <a class="button" href="results.php" style="margin-top:12px;">Open Full Results</a>
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
                        <p class="muted" style="margin-top:6px; white-space:pre-wrap;"><?php echo nl2br(safe($reminder['reminder_message'])); ?></p>
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
                <div class="avatar"><?php echo strtoupper(substr(safe($studentName),0,1)); ?></div>
                <div>
                    <p style="margin:0; font-weight:800; color:#111827;"><?php echo safe($studentName); ?></p>
                    <p class="muted">ኢሜይል: <?php echo safe($studentEmail); ?></p>
                    <p class="muted">ስ.ቁጥር: <?php echo safe($student['phone'] ?? 'N/A'); ?></p>
                    <p class="muted">ሀገር: <?php echo safe($student['country'] ?? 'ኢትዮጵያዊ'); ?></p>
                </div>
            </div>
        </div>
        <div class="card">
            <h2 class="section-title">⚙️ አካውንት ማስተካከያ</h2>
            <p class="section-sub">ፓስዋርድ መቀየር፣ ፕሮፋይል መቀየር እና ፎቶ መጨመር</p>
            <div class="account-actions" style="margin-top: 12px;">
                <a class="button secondary" href="student_register.php">ፕሮፋይል ቀይር</a>
                <a class="button" href="student_logout.php">ዉጣ</a>
            </div>
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
                    <a class="button" href="tutorial.php" style="margin-top: 12px;">ሌሰን ክፈት</a>
                </div>
            <?php endforeach; ?>
            <?php foreach ($saved_courses as $item): ?>
                <div class="mini-card">
                    <h3><?php echo safe($item['course_name']); ?></h3>
                    <p class="muted">ኢንስትራክተር: <?php echo safe($item['instructor'] ?? 'Staff'); ?></p>
                    <a class="button" href="tutorial.php" style="margin-top: 12px;">ኮርስ ተመልከት</a>
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

    <div class="card" style="margin-bottom: 24px;">
        <h2 class="section-title">💬 የቻት አስተዳደር</h2>
        <p class="section-sub">እዚህ ከተማሪዎች የሚላክ መልእክት በተመሳሳይ ዳታቤዝ ላይ ወደ admin_chat_management.php ይቀርባል።</p>
        <?php if ($chatSuccessMessage !== ''): ?>
            <div style="margin-bottom: 12px; padding: 10px 12px; border-radius: 10px; background: #ecfdf5; color: #166534; border: 1px solid #a7f3d0; font-weight: 700;">
                <?php echo safe($chatSuccessMessage); ?>
            </div>
        <?php endif; ?>
        <?php if ($chatMessageError !== ''): ?>
            <div style="margin-bottom: 12px; padding: 10px 12px; border-radius: 10px; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; font-weight: 700;">
                <?php echo safe($chatMessageError); ?>
            </div>
        <?php endif; ?>
        <form method="post" style="display:flex; flex-direction:column; gap:10px;">
            <textarea name="chat_message" rows="4" maxlength="1000" required placeholder="ስለ ኮርስዎ፣ እድገትዎ ወይም ማንኛውንም ጥያቄ ይጻፉ..." style="padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 12px; resize: vertical;"></textarea>
            <button class="button" type="submit">📤 መልእክት ላክ</button>
        </form>
        <div style="margin-top: 16px; display:flex; flex-direction:column; gap:10px;">
            <?php if (empty($chatMessages)): ?>
                <div class="mini-card"><p class="muted">እስካሁን ምንም የቻት መልእክት የለም።</p></div>
            <?php else: ?>
                <?php foreach ($chatMessages as $chatItem): ?>
                    <div class="mini-card" style="border-left: 4px solid <?php echo ($chatItem['reply_message'] !== null && $chatItem['reply_message'] !== '') ? '#8b5cf6' : '#2563eb'; ?>;">
                        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; margin-bottom:6px;">
                            <strong><?php echo safe($chatItem['sender_name']); ?></strong>
                            <span class="pill <?php echo ($chatItem['status'] === 'replied') ? 'success' : 'info'; ?>"><?php echo safe($chatItem['status']); ?></span>
                        </div>
                        <p class="muted" style="margin:0 0 8px;"><?php echo safe($chatItem['message']); ?></p>
                        <?php if (!empty($chatItem['reply_message'])): ?>
                            <div style="padding: 10px 12px; border-radius: 10px; background: #f5f3ff; color: #5b21b6; border-left: 3px solid #8b5cf6;">
                                <strong>Admin reply:</strong> <?php echo safe($chatItem['reply_message']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
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
                        <th>የክፍያ መንገድ</th>
                        <th>የክፍያ ሁኔታ</th>
                        <th>ቀን</th>
                        <th>እርምጃ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registrations as $row): ?>
                        <?php $courseName = $row['course'] ?: ($row['course_name'] ?? 'Course'); ?>
                        <tr>
                            <td>
                                <strong><?php echo safe($courseName); ?></strong>
                                <?php if (!empty($row['short_description']) || !empty($row['description'])): ?>
                                    <br><small class="muted"><?php echo safe(substr(strip_tags($row['short_description'] ?: $row['description'] ?: ''), 0, 90)); ?><?php echo (strlen(strip_tags($row['short_description'] ?: $row['description'] ?: '')) > 90) ? '...' : ''; ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo safe($row['amount'] ?? $row['course_price'] ?? 0); ?> ብር</td>
                            <td><?php echo safe($row['payment_method'] ? ucwords(str_replace('_', ' ', $row['payment_method'])) : '—'); ?></td>
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
    <script>
        (function () {
            const storageKey = 'sofnyas-theme';
            const toggle = document.getElementById('themeToggle');
            const applyTheme = (theme) => {
                document.body.setAttribute('data-theme', theme);
                if (toggle) {
                    toggle.textContent = theme === 'dark' ? '☀️ Light' : '🌙 Dark';
                }
            };
            const savedTheme = localStorage.getItem(storageKey);
            if (savedTheme === 'dark' || savedTheme === 'light') {
                applyTheme(savedTheme);
            } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                applyTheme('dark');
            } else {
                applyTheme('light');
            }
            if (toggle) {
                toggle.addEventListener('click', () => {
                    const currentTheme = document.body.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                    const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
                    applyTheme(nextTheme);
                    localStorage.setItem(storageKey, nextTheme);
                });
            }
        })();
    </script>
</body>
</html>
