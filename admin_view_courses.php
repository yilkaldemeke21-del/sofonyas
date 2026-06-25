<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$message = '';
$error = '';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 8;

function is_youtube_url($url) {
    return preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)/i', $url) === 1;
}

function youtube_embed_url($url) {
    $videoId = '';
    if (preg_match('/youtube\.com\/watch\?v=([^&\s]+)/i', $url, $m)) {
        $videoId = $m[1];
    } elseif (preg_match('/youtu\.be\/([^?&\s]+)/i', $url, $m)) {
        $videoId = $m[1];
    }
    return $videoId ? 'https://www.youtube.com/embed/' . $videoId : '';
}

// Handle delete
if (isset($_GET['delete'])) {
    $course_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM courses WHERE id = :id');
    $stmt->execute([':id' => $course_id]);
    $message = 'ኮርስ ሰርሷል።';
}

// Get paginated courses
try {
    $totalCourses = (int)$pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
    $totalPages = max(1, (int)ceil($totalCourses / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare('SELECT * FROM courses ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $courses = $stmt->fetchAll();
} catch (PDOException $e) {
    $courses = [];
    $error = 'DB ችግኝ አለ። ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>ኮርሶችን ተመልከት</title>
    <style>
        :root {
            --primary: #5b7cfa;
            --primary-dark: #4c67de;
            --accent: #8b5cf6;
            --success: #1f9d61;
            --danger: #e24d5b;
            --text: #15304a;
            --muted: #5f7085;
            --line: #e5ebf4;
            --panel: rgba(255, 255, 255, 0.92);
            --shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            background:
                radial-gradient(circle at top, #eef4ff 0%, #f6f8fc 35%, #edf2f7 100%);
            color: var(--text);
            min-height: 100vh;
        }

        .page-shell { min-height: 100vh; }

        .navbar {
            background: linear-gradient(135deg, #1f2937 0%, #334155 45%, #5b7cfa 100%);
            color: white;
            padding: 18px 24px;
            box-shadow: 0 10px 30px rgba(30, 41, 59, 0.25);
        }

        .navbar-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .brand-wrap h2 {
            font-size: 1.35rem;
            margin-bottom: 4px;
        }

        .brand-wrap p {
            color: #dbe4ff;
            font-size: 0.95rem;
        }

        .nav-actions { display: flex; gap: 10px; flex-wrap: wrap; }

        .nav-btn, .ghost-btn, .primary-btn, .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border-radius: 999px;
            font-weight: 700;
            transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        }

        .nav-btn, .ghost-btn, .primary-btn {
            padding: 10px 14px;
            font-size: 0.95rem;
        }

        .nav-btn, .primary-btn {
            background: rgba(255, 255, 255, 0.16);
            color: white;
            border: 1px solid rgba(255,255,255,0.18);
        }

        .ghost-btn {
            background: white;
            color: #334155;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .nav-btn:hover, .ghost-btn:hover, .primary-btn:hover { transform: translateY(-1px); }
        .nav-btn:hover { background: rgba(255,255,255,0.22); }
        .ghost-btn:hover { box-shadow: 0 10px 20px rgba(148,163,184,0.18); }
        .primary-btn:hover { background: rgba(255,255,255,0.22); }

        .container { max-width: 1400px; margin: 24px auto 40px; padding: 0 20px; }

        .hero-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.98), rgba(245,247,255,0.96));
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 24px;
            box-shadow: var(--shadow);
            padding: 24px;
            display: flex;
            justify-content: space-between;
            align-items: end;
            gap: 18px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .eyebrow {
            display: inline-block;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            font-size: 0.72rem;
            color: var(--primary-dark);
            background: #edf2ff;
            border-radius: 999px;
            padding: 6px 10px;
            margin-bottom: 8px;
        }

        .hero-card h1 { font-size: 1.6rem; margin-bottom: 6px; color: #0f172a; }
        .hero-card p { color: var(--muted); line-height: 1.5; }

        .badge-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
        .badge {
            background: #eef4ff;
            color: #3652a0;
            border: 1px solid #dee7ff;
            border-radius: 999px;
            padding: 7px 10px;
            font-size: 0.88rem;
            font-weight: 700;
        }

        .card {
            background: var(--panel);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 24px;
            box-shadow: var(--shadow);
            padding: 18px;
            backdrop-filter: blur(12px);
        }

        .message {
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 16px;
            background: #eafaf2;
            color: #166534;
            border: 1px solid #c8eed8;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 32px 18px;
            color: var(--muted);
            border: 1px dashed var(--line);
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(248,250,255,0.98));
        }

        .empty-state a { color: var(--primary-dark); text-decoration: none; font-weight: 700; }
        .empty-state a:hover { text-decoration: underline; }

        .table-shell { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 980px; }

        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid var(--line);
            vertical-align: top;
        }

        th {
            background: linear-gradient(180deg, #f8fbff 0%, #eef4ff 100%);
            color: #334155;
            font-weight: 800;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        tbody tr:hover { background: rgba(245,247,255,0.8); }

        .course-name {
            color: #172554;
            font-size: 1rem;
            line-height: 1.35;
        }

        .course-meta { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            padding: 5px 8px;
            font-size: 0.82rem;
            color: #334155;
        }

        .muted { color: var(--muted); font-size: 0.92rem; }

        .action-group { display: flex; flex-wrap: wrap; gap: 8px; }
        .action-btn {
            padding: 8px 10px;
            font-size: 0.82rem;
            color: white;
            border: none;
            box-shadow: 0 6px 16px rgba(15,23,42,0.08);
        }
        .action-btn:hover { transform: translateY(-1px); }

        .edit-btn { background: linear-gradient(135deg, #5b7cfa, #7c3aed); }
        .delete-btn { background: linear-gradient(135deg, #ef4444, #dc2626); }

        .media-block { margin-top: 10px; }
        .media-label { display: inline-block; color: #0f172a; font-weight: 700; margin-bottom: 6px; }
        .media-preview { display: inline-block; margin-top: 6px; max-width: 100%; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 10px 20px rgba(15,23,42,0.08); }
        .media-link { display: inline-block; margin-top: 6px; color: var(--primary-dark); text-decoration: none; font-weight: 700; }
        .media-link:hover { text-decoration: underline; }
        .media-frame { width: 100%; max-width: 340px; height: 190px; border: 0; border-radius: 12px; box-shadow: 0 10px 20px rgba(15,23,42,0.08); }

        @media (max-width: 1024px) {
            .hero-card { align-items: flex-start; }
        }

        @media (max-width: 768px) {
            .navbar { padding: 14px 14px 18px; }
            .container { padding: 0 12px; }
            .hero-card { padding: 16px; border-radius: 18px; }
            .nav-actions { width: 100%; }
            .nav-btn, .ghost-btn, .primary-btn { width: 100%; }
            .card { padding: 14px; border-radius: 18px; }
            table { min-width: 900px; }
        }
    </style>
</head>
<body>
<div class="page-shell">
    <div class="navbar">
        <div class="navbar-inner">
            <div class="brand-wrap">
                <h2>ኮርሶችን ተመልከት</h2>
                <p>የኮርስ ምዝገባዎችን፣ ማስተማር ቁሳቁሶችን እና ድርጊቶቹን በአስተያየት የሚታዩ መልክ እንዲሆኑ ለተገኙ አስተዳዳሪዎች የተዘጋጀ።</p>
            </div>
            <div class="nav-actions">
                <a href="admin_courses.php" class="nav-btn">➕ ኮርስ ጨምር</a>
                <a href="admin_dashboard.php" class="ghost-btn">← ወደ ዳሽቦርድ</a>
            </div>
        </div>
    </div>

    <div class="container">
        <section class="hero-card">
            <div>
                <span class="eyebrow">የአድሚኑ አጭር መግለጫ</span>
                <h1>የኮርስ ማስተዳደሪያ ዳሽቦርድ</h1>
                <p>እዚህ ላይ ኮርሶችን መከለስ፣የማስተማሪያ ማቴሪያሎችን ሙሉ በሙሉ በፍጥነት ማሽሽል እና ማጥፋት ይቻላል።</p>
                <div class="badge-row">
                    <span class="badge">📚 ኮርሶች</span>
                    <span class="badge">🧩 ልዩ ልዩ ማስረጃዎች</span>
                    <span class="badge">🛠️ አስተካክል/ አጥፋ</span>
                </div>
            </div>
            <a href="admin_courses.php" class="primary-btn">+ አዲስ ኮርስ ጨምር</a>
        </section>

        <div class="card">
        <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message" style="background:#ffe7e7;color:#a41616;"><?php echo safe($error); ?></div>
        <?php endif; ?>
        
        <?php if (empty($courses)): ?>
            <div class="empty-state">
                <p>ምንም ኮርስ የለም። <a href="admin_courses.php">አሁን ጨምር</a></p>
            </div>
        <?php else: ?>
            <div style="margin-bottom: 12px; color:#475569;">Showing <?php echo count($courses); ?> of <?php echo $totalCourses; ?> courses</div>
            <div class="table-shell">
            <table>
                <thead>
                    <tr>
                        <th>ኮርስ ስም</th>
                        <th>ኮድ</th>
                        <th>ዋጋ (ብር)</th>
                        <th>አስተማሪ</th>
                        <th>ታሪክ</th>
                        <th>ድርጊቶች</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $course): ?>
                        <tr>
                            <td>
                                <strong class="course-name"><?php echo safe($course['course_name']); ?></strong>
                                <?php if (!empty($course['short_description'])): ?>
                                    <br><span class="muted"><?php echo safe($course['short_description']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($course['description'])): ?>
                                    <br><span class="muted rich-content"><?php echo renderRichText($course['description']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($course['category']) || !empty($course['level'])): ?>
                                    <div class="course-meta">
                                        <?php if (!empty($course['category'])): ?><span class="chip">📚 <?php echo safe($course['category']); ?></span><?php endif; ?>
                                        <?php if (!empty($course['level'])): ?><span class="chip">📈 <?php echo safe($course['level']); ?></span><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($course['modules'])): ?>
                                    <br><span class="muted rich-content"><strong>Modules:</strong> <?php echo renderRichText($course['modules']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($course['quiz'])): ?>
                                    <br><span class="muted rich-content"><strong>Quiz:</strong> <?php echo renderRichText($course['quiz']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($course['assignment'])): ?>
                                    <br><span class="muted rich-content"><strong>Assignment:</strong> <?php echo renderRichText($course['assignment']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($course['certificate_requirements'])): ?>
                                    <br><span class="muted rich-content"><strong>Certificate Requirements:</strong> <?php echo renderRichText($course['certificate_requirements']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($course['pdf_file'])): ?>
                                    <br><a class="media-link" href="<?php echo safe($course['pdf_file']); ?>" target="_blank">📄 PDF እይ</a>
                                <?php endif; ?>
                                <?php if (!empty($course['tutorial_topic'])): ?>
                                    <br><span class="muted"><strong>Topic:</strong> <?php echo safe($course['tutorial_topic']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($course['tutorial_text'])): ?>
                                    <br><span class="muted rich-content"><strong>Text:</strong> <?php echo renderRichText($course['tutorial_text']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($course['tutorial_image'])): ?>
                                    <div class="media-block">
                                        <strong>Image:</strong><br>
                                        <img class="media-preview" src="<?php echo safe($course['tutorial_image']); ?>" alt="Tutorial image" style="max-width: 180px;">
                                        <br><a class="media-link" href="<?php echo safe($course['tutorial_image']); ?>" target="_blank">Open image</a>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($course['tutorial_audio'])): ?>
                                    <div class="media-block">
                                        <strong>Audio:</strong><br>
                                        <audio controls class="media-preview" style="max-width: 280px; background:#fff;">
                                            <source src="<?php echo safe($course['tutorial_audio']); ?>">
                                            እርስዎ የአውዶ ተጫዋች የለዎት።
                                        </audio>
                                        <br><a class="media-link" href="<?php echo safe($course['tutorial_audio']); ?>" target="_blank">Open audio</a>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($course['tutorial_video'])): ?>
                                    <div class="media-block">
                                        <strong>Video:</strong><br>
                                        <?php if (is_youtube_url($course['tutorial_video'])): 
                                            $embedUrl = youtube_embed_url($course['tutorial_video']);
                                        ?>
                                            <iframe class="media-frame" src="<?php echo safe($embedUrl); ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                                        <?php else: ?>
                                            <video class="media-preview" controls style="max-width: 320px;">
                                                <source src="<?php echo safe($course['tutorial_video']); ?>">
                                                እርስዎ የቪድዮ ተጫዋች የለዎት።
                                            </video>
                                        <?php endif; ?>
                                        <br><a class="media-link" href="<?php echo safe($course['tutorial_video']); ?>" target="_blank">Open video</a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><span class="chip">#<?php echo safe($course['course_code']); ?></span></td>
                            <td><strong><?php echo number_format((float)$course['price'], 2); ?> ብር</strong></td>
                            <td><?php echo safe($course['instructor'] ?? '-'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($course['created_at'])); ?></td>
                            <td>
                                <div class="action-group">
                                    <a href="admin_edit_course.php?id=<?php echo $course['id']; ?>" class="action-btn edit-btn">✏️ ማስተካከል</a>
                                    <a href="?delete=<?php echo $course['id']; ?>" class="action-btn delete-btn" onclick="return confirm('እርግጠኛ ነህ?');">🗑️ ሰር</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div style="margin-top: 16px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:flex-end;">
                <?php if ($page > 1): ?>
                    <a href="admin_view_courses.php?page=<?php echo max(1, $page - 1); ?>" class="action-btn edit-btn">◀ ቀዳሚ</a>
                <?php endif; ?>
                <span style="color:#475569;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="admin_view_courses.php?page=<?php echo min($totalPages, $page + 1); ?>" class="action-btn edit-btn">ቀጣይ ▶</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
