<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit;
}

$message = '';
$messageType = 'info';
$studentId = (string)$_SESSION['student_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['enroll_course_id'])) {
    $courseId = (int)$_POST['enroll_course_id'];
    if ($courseId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM courses WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $courseId]);
        $course = $stmt->fetch();

        if ($course) {
            if (isStudentEnrolled($pdo, $studentId, $courseId)) {
                $message = 'You are already enrolled in this course.';
                $messageType = 'info';
            } else {
                $recordId = uniqid('reg_', true);
                $insertStmt = $pdo->prepare('INSERT INTO registrations (id, name, email, student_id, course, course_id, amount, payment_status, created_at) VALUES (:id, :name, :email, :student_id, :course, :course_id, :amount, :payment_status, :created_at)');
                $insertStmt->execute([
                    ':id' => $recordId,
                    ':name' => $_SESSION['student_name'] ?? '',
                    ':email' => $_SESSION['student_email'] ?? '',
                    ':student_id' => $studentId,
                    ':course' => $course['course_name'],
                    ':course_id' => $courseId,
                    ':amount' => $course['price'] ?? 0,
                    ':payment_status' => 'unpaid',
                    ':created_at' => date('Y-m-d H:i:s'),
                ]);
                $message = 'Enrollment request submitted successfully.';
                $messageType = 'success';
            }
        } else {
            $message = 'Selected course is invalid.';
            $messageType = 'error';
        }
    } else {
        $message = 'Selected course is invalid.';
        $messageType = 'error';
    }
}

$stmt = $pdo->query('SELECT * FROM courses ORDER BY created_at DESC');
$courses = $stmt->fetchAll();
$noteStmt = $pdo->query('SELECT COUNT(*) AS total FROM admin_notes');
$noteCount = (int)($noteStmt->fetch()['total'] ?? 0);

$enrolledCourseKeys = [];
$enrolledStmt = $pdo->prepare('SELECT course_id, course FROM registrations WHERE student_id = :student_id');
$enrolledStmt->execute([':student_id' => $studentId]);
foreach ($enrolledStmt->fetchAll() as $entry) {
    if (!empty($entry['course_id'])) {
        $enrolledCourseKeys[(int)$entry['course_id']] = true;
    }
    if (!empty($entry['course'])) {
        $enrolledCourseKeys['name:' . $entry['course']] = true;
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Courses</title>
    <link rel="stylesheet" href="sofonyas (1).css">
    <style>
        body { font-family: Arial, sans-serif; background:#f5f7fa; color:#1f2937; margin:0; padding:0; }
        nav ul { display:flex; gap:12px; list-style:none; padding:18px 24px; margin:0; background:#ffffff; border-bottom:1px solid #e2e8f0; }
        nav a { color:#111827; text-decoration:none; font-weight:700; }
        nav a.active { color:#2563eb; }
        .page { max-width: 1100px; margin: 24px auto; padding: 0 20px; }
        .card { background:#ffffff; border-radius:16px; box-shadow:0 14px 30px rgba(15,23,42,0.06); padding:24px; }
        .feature-grid { display:grid; gap:20px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); margin-top:18px; }
        .course-card { padding:20px; border:1px solid #e2e8f0; border-radius:16px; background:#f8fafc; }
        .course-card h3 { margin-top:0; font-size:1.25rem; }
        .course-card p { margin:0.75rem 0; line-height:1.5; }
        .button, .secondary { display:inline-flex; align-items:center; justify-content:center; padding:10px 16px; border-radius:12px; font-weight:700; text-decoration:none; }
        .button { background:#2563eb; color:#fff; border:none; }
        .secondary { background:#f8fafc; color:#111827; border:1px solid #cbd5e1; }
        .message { margin-bottom:20px; padding:14px; border-radius:12px; }
        .message.error { background:#fee2e2; color:#991b1b; }
        .message.success { background:#ecfdf5; color:#14532d; }
        .message.info { background:#dbeafe; color:#1e40af; }
    </style>
</head>
<body>
    <nav>
        <ul>
            <li><a href="student_dashboard.php">ዳሽቦርድ</a></li>
            <li><a href="my_courses.php">My Courses</a></li>
            <li><a href="courses.php" class="active">ኮርሶች</a></li>
        </ul>
    </nav>
    <div class="page">
        <section class="card">
            <h2>Available Courses</h2>
            <p>ሌሰኖችን ተመልከት, ቱቶሪያሎችን ተመልከት, እንዲሁም ከፈለጉ ኮርሶችን ወደ ቀጣዩ ያሽከርክሩ።</p>
            <?php if ($message !== ''): ?>
                <div class="message <?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if (empty($courses)): ?>
            <section class="card">
                <p>ምንም ኮርስ የለም.</p>
            </section>
        <?php else: ?>
            <div class="feature-grid">
                <?php foreach ($courses as $course): ?>
                    <?php $courseIsEnrolled = isset($enrolledCourseKeys[(int)($course['id'] ?? 0)]) || isset($enrolledCourseKeys['name:' . ($course['course_name'] ?? '')]); ?>
                    <div class="course-card">
                        <h3><?php echo safe($course['course_name'] ?? 'Untitled Course'); ?></h3>
                        <p><?php echo safe($course['short_description'] ?? $course['description'] ?? ''); ?></p>
                        <p><strong>Instructor:</strong> <?php echo safe($course['instructor'] ?? 'Staff'); ?></p>
                        <p><strong>Price:</strong> <?php echo number_format((float)($course['price'] ?? 0), 2); ?> ብር</p>
                        <p><strong>Notes:</strong> <?php echo $noteCount > 0 ? 'Available' : 'Coming soon'; ?></p>
                        <p><strong>Tutorial:</strong> <?php echo !empty($course['tutorial_topic']) || !empty($course['tutorial_text']) || !empty($course['tutorial_video']) ? 'Available' : 'Coming soon'; ?></p>
                        <div style="margin-top:16px; display:flex; flex-wrap:wrap; gap:10px;">
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="enroll_course_id" value="<?php echo (int)($course['id'] ?? 0); ?>">
                                <button class="button" type="submit" <?php echo $courseIsEnrolled ? 'disabled style="opacity:.6;cursor:not-allowed;"' : ''; ?>><?php echo $courseIsEnrolled ? 'Enrolled' : 'Enroll'; ?></button>
                            </form>
                            <a class="secondary" href="course_details.php?id=<?php echo (int)($course['id'] ?? 0); ?>">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
