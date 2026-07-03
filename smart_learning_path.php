<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit;
}

$studentId = (string)$_SESSION['student_id'];
$stmt = $pdo->prepare('SELECT name, email FROM students WHERE student_id = :student_id LIMIT 1');
$stmt->execute([':student_id' => $studentId]);
$student = $stmt->fetch();
$studentName = trim((string)($student['name'] ?? $student['student_name'] ?? 'Student'));

$stmt = $pdo->prepare('SELECT id, course_name, category, level, short_description, thumbnail FROM courses ORDER BY FIELD(level, "Beginner", "Intermediate", "Advanced", "Expert") DESC, course_name ASC');
$stmt->execute();
$courses = $stmt->fetchAll();

$learningPath = [];
foreach ($courses as $course) {
    $category = trim((string)$course['category']);
    $level = trim((string)$course['level']);
    $learningPath[$level][] = $course;
}

$orderedLevels = ['Beginner', 'Intermediate', 'Advanced', 'Expert'];
$hasPath = false;
foreach ($orderedLevels as $levelName) {
    if (!empty($learningPath[$levelName])) {
        $hasPath = true;
        break;
    }
}

function safe($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Learning Path</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 100%);
            color: #0f172a;
        }
        .wrapper {
            max-width: 1180px;
            margin: 0 auto;
            padding: 28px 20px 40px;
        }
        .header-shell {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 18px;
            align-items: center;
            margin-bottom: 30px;
        }
        .hero-card {
            border-radius: 26px;
            background: white;
            border: 1px solid rgba(37, 99, 235, 0.14);
            box-shadow: 0 22px 62px rgba(15, 23, 42, 0.08);
            padding: 32px;
        }
        .hero-card h1 {
            font-size: clamp(2.4rem, 3vw, 3.6rem);
            margin-bottom: 12px;
        }
        .hero-card p {
            color: #475569;
            max-width: 720px;
            line-height: 1.8;
        }
        .path-hero {
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            color: white;
            border-radius: 22px;
            padding: 28px;
            display: grid;
            gap: 18px;
        }
        .path-hero h2 {
            margin: 0;
            font-size: 2rem;
            letter-spacing: -0.03em;
        }
        .path-hero p {
            margin: 0;
            color: rgba(255, 255, 255, 0.88);
        }
        .path-hero .tag {
            display: inline-flex;
            padding: 10px 16px;
            border-radius: 999px;
            background: rgba(255,255,255,0.14);
            font-weight: 700;
            color: white;
            max-width: fit-content;
        }
        .path-grid {
            display: grid;
            gap: 22px;
            margin-top: 26px;
        }
        .path-column {
            background: white;
            border-radius: 24px;
            border: 1px solid rgba(148, 163, 184, 0.24);
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.06);
            padding: 20px;
        }
        .path-column h3 {
            margin-top: 0;
            font-size: 1.12rem;
            color: #1d4ed8;
            font-weight: 700;
        }
        .course-step {
            display: grid;
            grid-template-columns: 4rem 1fr;
            gap: 16px;
            align-items: start;
            padding: 18px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .course-step:last-child { border-bottom: none; }
        .step-badge {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2563eb, #4f46e5);
            color: white;
            display: grid;
            place-items: center;
            font-weight: 800;
            box-shadow: 0 14px 26px rgba(37, 99, 235, 0.24);
        }
        .course-details h4 {
            margin: 0 0 8px;
            font-size: 1rem;
            color: #111827;
        }
        .course-details p {
            margin: 0;
            color: #475569;
            line-height: 1.7;
        }
        .course-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .meta-pill {
            display: inline-flex;
            padding: 6px 12px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 0.85rem;
            font-weight: 700;
        }
        .course-image {
            width: 100%;
            height: 180px;
            border-radius: 20px;
            object-fit: cover;
            background: #e2e8f0;
        }
        .roadmap-notes {
            margin-top: 32px;
            display: grid;
            gap: 18px;
            grid-template-columns: repeat(auto-fit,minmax(260px,1fr));
        }
        .note-card {
            border-radius: 20px;
            background: white;
            border: 1px solid rgba(148, 163, 184, 0.18);
            padding: 20px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
        }
        .note-card h4 {
            margin: 0 0 10px;
            color: #0f172a;
        }
        .note-card p {
            margin: 0;
            color: #475569;
            line-height: 1.75;
        }
        .outline-btn {
            border: 1px solid rgba(37, 99, 235, 0.2);
            background: white;
            color: #2563eb;
            border-radius: 999px;
            padding: 12px 22px;
            font-weight: 700;
        }
        @media (max-width: 880px) {
            .header-shell { grid-template-columns: 1fr; }
            .path-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header-shell">
        <div class="hero-card">
            <span class="tag">Smart Learning Path</span>
            <h1>የእርስዎ አንደኛዊ የመማር መንገድ</h1>
            <p>በዚህ ገጽ ላይ እንዲከተሉ የተሰራ የኮርስ ዳይሬክቶሪ እና የማስተማሪያ መንገድ እንዲገኙ ተዘጋጅቷል። ኮርሶችን ከደረጃ ላይ ወደ ላይ ሲሻገሩ በቀጥታ የእርስዎን ማስተላለፊያ ትንሽ ትንሽ ይከታተላሉ።</p>
            <a class="outline-btn" href="student_dashboard.php">Back to Dashboard</a>
        </div>
        <div class="path-hero">
            <h2>ማሰተኛ እና ሞያማዊ መንገድ</h2>
            <p>የትምህርት ፍትሕ እና እድገት የሚያንሳ ፕሮግራም። ከመጀመሪያ ደረጃ እስከ ሙሉ ዝግጅት ቀጥሏል።</p>
        </div>
    </div>

    <div class="path-grid">
        <?php if (!$hasPath): ?>
            <div class="path-column">
                <h3>ምንም ትምህርት አልተገኘም</h3>
                <p>የሚቀጥለውን የማስተማሪያ መንገድ ለማዘጋጀት እባክዎን ኮርሶችን ይጨምሩ ወይም ከአስተዳዳሪ ጋር ይገናኙ።</p>
            </div>
        <?php else: ?>
            <?php foreach ($orderedLevels as $levelName): ?>
                <?php if (!empty($learningPath[$levelName])): ?>
                    <div class="path-column">
                        <h3><?php echo safe($levelName); ?> Level</h3>
                        <p>ይህ የ<?php echo safe($levelName); ?> ደረጃ ላይ የሚገኙ ኮርሶች ናቸው።</p>
                        <?php foreach ($learningPath[$levelName] as $index => $course): ?>
                            <div class="course-step">
                                <div class="step-badge"><?php echo $index + 1; ?></div>
                                <div class="course-details">
                                    <h4><?php echo safe($course['course_name']); ?></h4>
                                    <p><?php echo safe($course['short_description'] ?: 'ይህ ኮርስ ለእርስዎ ተስፋ እና እውቀት ይሰጣል።'); ?></p>
                                    <div class="course-meta">
                                        <span class="meta-pill"><?php echo safe($course['category'] ?: 'General'); ?></span>
                                        <span class="meta-pill"><?php echo safe($course['level'] ?: 'Level'); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="roadmap-notes">
        <div class="note-card">
            <h4>How the Path Works</h4>
            <p>ይህ የማማሪያ መንገድ የኮርሱ ደረጃዎችን እና የማስተማሪያ የምርጫ አማራጮችን በግልጽ ቅርጸ ታሪክ ይከታተላል። ከታች የሚከተሉ ኮርሶችዎትን እያስተዳደሩ ያስፈልጋሉ።</p>
        </div>
        <div class="note-card">
            <h4>Recommended Sequence</h4>
            <p>በፈጣን ለማማረቅ እና እውቀትን ለማደስ እርሱን ይከተላሉ። ይህ የማማሪያ እርምጃ ከመነሻ ላይ ወደ ላይ ይሄዳል።</p>
        </div>
        <div class="note-card">
            <h4>Personalize Your Roadmap</h4>
            <p>የግል ማስመሪያ ለማግኘት ከሚገባው ኮርስ ምርጥ ይምረጡ እና ከAI Tutor ጋር ይወያዩ። ይህ የሚያስተካክለው እጅግ ተገቢ ነው።</p>
        </div>
    </div>
</div>
</body>
</html>
