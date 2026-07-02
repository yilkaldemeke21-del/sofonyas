<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit;
}

$studentId = (string)$_SESSION['student_id'];
$stmt = $pdo->prepare('SELECT r.*, c.id AS course_id, c.course_name AS course_title, c.short_description, c.description AS course_description, c.thumbnail, c.instructor, c.tutorial_video FROM registrations r LEFT JOIN courses c ON c.id = r.course_id OR c.course_name = r.course WHERE r.student_id = :student_id ORDER BY r.created_at DESC');
$stmt->execute([':student_id' => $studentId]);
$registrations = $stmt->fetchAll();

function courseProgressLabel(array $progress): string
{
    if ($progress['total'] === 0) {
        return 'No lessons added yet';
    }
    return sprintf('%d / %d lessons complete', $progress['completed'], $progress['total']);
}

?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses</title>
    <link rel="stylesheet" href="sofonyas (1).css">
    <style>
        body { font-family: Arial, sans-serif; background:#f5f7fa; color:#1f2937; margin:0; padding:0; }
        nav ul { display:flex; gap:12px; list-style:none; padding:16px 24px; margin:0; background:#ffffff; border-bottom:1px solid #e2e8f0; }
        nav a { color:#1f2937; text-decoration:none; font-weight:700; }
        .page { max-width: 1100px; margin: 24px auto; padding: 0 20px; }
        .header { margin-bottom: 20px; }
        .course-grid { display:grid; gap:20px; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
        .course-card { background:#ffffff; border-radius:16px; box-shadow:0 14px 30px rgba(15,23,42,0.08); overflow:hidden; }
        .course-card img { width:100%; height: 180px; object-fit:cover; }
        .course-card .content { padding:18px; }
        .course-card h3 { margin:0 0 10px; font-size:1.25rem; }
        .course-card p { margin:0 0 10px; line-height:1.5; }
        .course-card .meta { display:flex; flex-wrap:wrap; gap:8px; margin:12px 0; }
        .pill { display:inline-flex; padding:8px 12px; border-radius:999px; background:#e2e8f0; color:#1d4ed8; font-size:0.85rem; font-weight:700; }
        .course-card .buttons { display:flex; flex-wrap:wrap; gap:10px; margin-top:16px; }
        .btn, .btn.secondary { display:inline-flex; align-items:center; justify-content:center; padding:10px 14px; border-radius:12px; text-decoration:none; font-weight:700; }
        .btn { background:#2563eb; color:#fff; }
        .btn.secondary { background:#f8fafc; color:#0f172a; border:1px solid #e2e8f0; }
        .empty-state { background:#ffffff; border-radius:16px; padding:28px; text-align:center; box-shadow:0 14px 28px rgba(15,23,42,0.06); }
        .empty-state h2 { margin:0 0 12px; }
        .empty-state a { display:inline-block; margin-top:14px; padding:10px 16px; border-radius:12px; background:#2563eb; color:#fff; text-decoration:none; }
    </style>
</head>
<body>
    <nav>
        <ul>
            <li><a href="student_dashboard.php">Dashboard</a></li>
            <li><a href="tutorial.php">Courses</a></li>
            <li><a href="course_details.php">Course Details</a></li>
            <li><a href="my_courses.php">My Courses</a></li>
        </ul>
    </nav>
    <div class="page">
        <div class="header">
            <h1>My Courses</h1>
            <p>የተመዘገቡ ኮርሶችዎን እና የሌሰን ትዕዛዞችን እድገትዎት ይመልከቱ።</p>
        </div>

        <?php if (empty($registrations)): ?>
            <div class="empty-state">
                <h2>No enrolled courses yet.</h2>
                <p>ምንም ኮርስ እስካሁን አልተመዘገበም። ወደ ኮርሶች ገጽ ይሂዱ እና ከነዚህ ውስጥ ይጀምሩ።</p>
                <a href="tutorial.php">Browse Courses</a>
            </div>
        <?php else: ?>
            <div class="course-grid">
                <?php foreach ($registrations as $registration): ?>
                    <?php $courseId = (int)($registration['course_id'] ?? 0); ?>
                    <?php $progress = $courseId > 0 ? getCourseLessonProgress($pdo, $studentId, $courseId) : ['completed' => 0, 'total' => 0]; ?>
                    <?php $courseTitle = !empty($registration['course_title']) ? $registration['course_title'] : $registration['course']; ?>
                    <?php $description = !empty($registration['short_description']) ? $registration['short_description'] : ($registration['course_description'] ?? 'No description available.'); ?>
                    <div class="course-card">
                        <?php if (!empty($registration['thumbnail'])): ?>
                            <img src="<?php echo htmlspecialchars(publicMediaUrl($registration['thumbnail']), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($courseTitle, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php else: ?>
                            <img src="IMG_20241202_031425_251.jpg" alt="Course preview">
                        <?php endif; ?>
                        <div class="content">
                            <h3><?php echo htmlspecialchars($courseTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
                            <div class="meta">
                                <span class="pill"><?php echo htmlspecialchars($registration['payment_status'] === 'paid' ? 'Paid' : 'Unpaid', ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="pill"><?php echo htmlspecialchars(courseProgressLabel($progress), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <p><?php echo renderRichText($description); ?></p>
                            <p><strong>Instructor:</strong> <?php echo htmlspecialchars($registration['instructor'] ?? 'Staff', ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="buttons">
                                <?php if ($courseId > 0): ?>
                                    <?php $nextLessonStmt = $pdo->prepare('SELECT id FROM course_lessons WHERE course_id = :course_id ORDER BY sort_order ASC, id ASC LIMIT 1'); ?>
                                    <?php $nextLessonStmt->execute([':course_id' => $courseId]); ?>
                                    <?php $firstLesson = (int)$nextLessonStmt->fetchColumn(); ?>
                                    <a class="btn" href="<?php echo $firstLesson ? 'course_content.php?course_id=' . $courseId . '&lesson_id=' . $firstLesson : 'course_details.php?id=' . $courseId; ?>">Continue</a>
                                <?php else: ?>
                                    <a class="btn secondary" href="course_details.php?id=<?php echo (int)$courseId; ?>">View Details</a>
                                <?php endif; ?>
                                <a class="btn secondary" href="course_details.php?id=<?php echo $courseId > 0 ? $courseId : 0; ?>">Course Overview</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
