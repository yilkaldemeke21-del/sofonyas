<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$total_students = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
$total_courses = (int)$pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
$total_registrations = (int)$pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn();
$paid_registrations = (int)$pdo->query('SELECT COUNT(*) FROM registrations WHERE payment_status = "paid"')->fetchColumn();
$unpaid_registrations = (int)$pdo->query('SELECT COUNT(*) FROM registrations WHERE payment_status = "unpaid"')->fetchColumn();

$avg_quiz_score = $pdo->query('SELECT AVG(score / NULLIF(total_questions, 0) * 100) AS avg_score FROM exam_submissions')->fetchColumn();
$avg_quiz_score = $avg_quiz_score !== null ? (int)round($avg_quiz_score) : 0;

$completed_lessons = (int)$pdo->query('SELECT COUNT(*) FROM lesson_progress')->fetchColumn();
$total_lessons = (int)$pdo->query('SELECT COUNT(*) FROM course_lessons')->fetchColumn();
$lesson_completion_rate = $total_lessons > 0 ? (int)round(($completed_lessons / $total_lessons) * 100) : 0;

$active_students_last_week = (int)$pdo->query('SELECT COUNT(DISTINCT student_id) FROM registrations WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)')->fetchColumn();
$active_student_ratio = $total_students > 0 ? min(100, (int)round(($active_students_last_week / $total_students) * 100)) : 0;

$trend_registrations = $pdo->query('SELECT DATE(created_at) AS day, COUNT(*) AS total FROM registrations WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY DATE(created_at) ASC')->fetchAll(PDO::FETCH_ASSOC);
$trend_exams = $pdo->query('SELECT DATE(submitted_at) AS day, COUNT(*) AS total FROM exam_submissions WHERE submitted_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(submitted_at) ORDER BY DATE(submitted_at) ASC')->fetchAll(PDO::FETCH_ASSOC);

$top_courses = $pdo->query('SELECT COALESCE(course, "Unknown") AS course_name, COUNT(*) AS total FROM registrations WHERE course IS NOT NULL AND TRIM(course) <> "" GROUP BY course_name ORDER BY total DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
$top_students = $pdo->query('SELECT es.student_id, es.student_name, ROUND(AVG(es.score / NULLIF(es.total_questions, 0) * 100), 0) AS avg_score, COUNT(*) AS attempts FROM exam_submissions es GROUP BY es.student_id, es.student_name ORDER BY avg_score DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);

$latest_activity = $pdo->query('SELECT created_at, CONCAT("Registration: ", name, " (", course, ")") AS description FROM registrations ORDER BY created_at DESC LIMIT 4')->fetchAll(PDO::FETCH_ASSOC);
$latest_exams = $pdo->query('SELECT submitted_at AS created_at, CONCAT("Quiz: ", student_name, " – ", score, "/", total_questions) AS description FROM exam_submissions ORDER BY submitted_at DESC LIMIT 4')->fetchAll(PDO::FETCH_ASSOC);

$activity_feed = array_merge($latest_activity, $latest_exams);
usort($activity_feed, static function ($a, $b) {
    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});
$activity_feed = array_slice($activity_feed, 0, 6);

$week_prior_registrations = (int)$pdo->query('SELECT COUNT(*) FROM registrations WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY)')->fetchColumn();
$registration_growth = $week_prior_registrations > 0 ? (int)round((($active_students_last_week - $week_prior_registrations) / $week_prior_registrations) * 100) : ($active_students_last_week > 0 ? 100 : 0);

$ai_smart_score = (int)round(($avg_quiz_score + $lesson_completion_rate + $active_student_ratio) / 3);
$top_course_name = $top_courses[0]['course_name'] ?? 'ፈለጉ ኮርስ የለም';
$top_course_count = $top_courses[0]['total'] ?? 0;

$smart_insights = [];
if ($avg_quiz_score < 70) {
    $smart_insights[] = 'AI Alert: Average quiz score is below 70%, consider improving lesson summaries and review materials.';
} else {
    $smart_insights[] = 'AI Insight: Quiz performance remains strong, maintain current assessment pacing.';
}
if ($lesson_completion_rate < 55) {
    $smart_insights[] = 'AI Recommendation: Increase course reminders and progress nudges to boost lesson completion.';
} else {
    $smart_insights[] = 'AI Insight: Lesson completion rate is healthy, keep learners engaged with short checkpoints.';
}
if ($registration_growth > 15) {
    $smart_insights[] = 'AI Trend: Registration growth is positive, consider launching new related courses soon.';
} elseif ($registration_growth < 0) {
    $smart_insights[] = 'AI Trend: Registration momentum has slowed; promote the most popular course to regain growth.';
} else {
    $smart_insights[] = 'AI Trend: Registration activity is stable; watch student engagement over the next week.';
}
$smart_insights[] = sprintf('Top course currently: %s with %d recent enrollments.', safe($top_course_name), $top_course_count);

function safe($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Smart Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bg: #f3f6fb;
            --surface: #ffffff;
            --surface-alt: #eef2ff;
            --text: #111827;
            --muted: #475569;
            --border: #dbeafe;
            --primary: #2563eb;
            --secondary: #7c3aed;
            --success: #16a34a;
            --danger: #dc2626;
            --radius: 16px;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: var(--bg); color: var(--text); }
        .navbar { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .navbar h1 { margin: 0; font-size: clamp(1.5rem, 2vw, 2.4rem); }
        .navbar a { color: white; text-decoration: none; margin-left: 14px; padding: 10px 14px; background: rgba(255,255,255,0.14); border-radius: 999px; font-weight: 700; }
        .page-wrap { max-width: 1240px; margin: 24px auto; padding: 0 20px; }
        .hero-card { background: linear-gradient(135deg, #eff6ff, #eef2ff); padding: 26px; border-radius: var(--radius); border: 1px solid #dbeafe; margin-bottom: 22px; }
        .hero-card h2 { margin: 0 0 8px; }
        .hero-card p { margin: 0; color: var(--muted); line-height: 1.7; }
        .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-bottom: 22px; }
        .metric-card, .insight-card, .table-card, .chart-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 22px; box-shadow: 0 12px 30px rgba(15,23,42,0.06); }
        .metric-card h3, .chart-card h3, .insight-card h3, .table-card h3 { margin-top: 0; margin-bottom: 12px; font-size: 1rem; }
        .metric-card .value { font-size: 2rem; font-weight: 800; color: var(--primary); }
        .small-meta { color: var(--muted); font-size: 0.95rem; margin-top: 8px; }
        .progress-track { height: 12px; width: 100%; background: #e2e8f0; border-radius: 999px; overflow: hidden; margin-top: 12px; }
        .progress-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #2563eb, #4f46e5); }
        .insight-card ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
        .insight-card li { padding: 14px 16px; border-radius: 14px; background: #f8fafc; border: 1px solid #dbeafe; }
        .table-card table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .table-card th, .table-card td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #f3f4f6; }
        .table-card th { color: #334155; font-weight: 700; }
        .badge-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; background: #eef2ff; color: #1e40af; font-size: 0.85rem; font-weight: 700; }
        .activity-list { list-style: none; margin: 0; padding: 0; }
        .activity-list li { padding: 14px 0; border-bottom: 1px solid #f3f4f6; }
        .activity-list li:last-child { border-bottom: none; }
        .activity-time { color: var(--muted); font-size: 0.9rem; }
        .chat-float {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 12px;
        }
        .chat-button {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            box-shadow: 0 18px 40px rgba(37, 99, 235, 0.24);
            cursor: pointer;
            font-size: 1.4rem;
            display: grid;
            place-items: center;
        }
        .chat-panel {
            width: min(340px, calc(100vw - 40px));
            max-height: 500px;
            background: white;
            border-radius: 24px;
            box-shadow: 0 28px 70px rgba(15, 23, 42, 0.18);
            overflow: hidden;
            border: 1px solid #e2e8f0;
            display: none;
            flex-direction: column;
        }
        .chat-panel.active {
            display: flex;
        }
        .chat-panel-header {
            padding: 16px 18px;
            background: linear-gradient(135deg, #eef2ff, #ede9fe);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .chat-panel-header h4 { margin: 0; font-size: 1rem; color: #111827; }
        .chat-panel-header button { border: none; background: transparent; color: #475569; font-size: 1.1rem; cursor: pointer; }
        .chat-messages { padding: 16px; display: flex; flex-direction: column; gap: 12px; overflow-y: auto; max-height: 320px; background: #f8fafc; }
        .chat-message { max-width: 85%; padding: 12px 14px; border-radius: 18px; line-height: 1.5; }
        .chat-message.user { background: #2563eb; color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
        .chat-message.ai { background: #eef2ff; color: #0f172a; align-self: flex-start; border-bottom-left-radius: 4px; }
        .chat-input-row { display: flex; gap: 10px; padding: 14px 16px; background: #ffffff; border-top: 1px solid #e2e8f0; }
        .chat-input { flex: 1; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 999px; outline: none; }
        .chat-send { border: none; background: var(--primary); color: white; padding: 0 18px; border-radius: 999px; cursor: pointer; font-weight: 700; }
        @media (max-width: 768px) { .navbar { flex-direction: column; align-items: flex-start; gap: 12px; } }
    </style>
</head>
<body>
    <header class="navbar">
        <div>
            <h1>AI Smart Dashboard</h1>
            <div style="font-size:0.95rem; color: rgba(255,255,255,0.86); margin-top: 6px;">Professional AI-powered analytics for your admin panel</div>
        </div>
        <div>
            <a href="admin_dashboard.php">Back to Dashboard</a>
            <a href="admin_website_settings.php">Website Settings</a>
        </div>
    </header>

    <main class="page-wrap">
        <section class="hero-card">
            <h2>Welcome to the AI-driven analytics hub</h2>
            <p>Monitor student progress, quiz performance, engagement trends, and AI-based insights in one clean professional interface.</p>
        </section>

        <div class="grid-3">
            <div class="metric-card">
                <h3>Total Students</h3>
                <div class="value"><?php echo safe($total_students); ?></div>
                <div class="small-meta">Registered learners across all courses</div>
            </div>
            <div class="metric-card">
                <h3>Active This Week</h3>
                <div class="value"><?php echo safe($active_students_last_week); ?></div>
                <div class="small-meta">Students with recent activity in the last 7 days</div>
            </div>
            <div class="metric-card">
                <h3>AI Smart Score</h3>
                <div class="value"><?php echo safe($ai_smart_score); ?><span style="font-size:1rem;color:var(--muted);">/100</span></div>
                <div class="small-meta">Composite performance signal from course engagement and quiz results</div>
            </div>
        </div>

        <div class="grid-3">
            <div class="metric-card">
                <h3>Course Registrations</h3>
                <div class="value"><?php echo safe($total_registrations); ?></div>
                <div class="small-meta">Paid: <?php echo safe($paid_registrations); ?> · Unpaid: <?php echo safe($unpaid_registrations); ?></div>
            </div>
            <div class="metric-card">
                <h3>Average Quiz Score</h3>
                <div class="value"><?php echo safe($avg_quiz_score); ?>%</div>
                <div class="small-meta">Latest exam performance across the platform</div>
                <div class="progress-track"><div class="progress-fill" style="width: <?php echo safe(min(100, $avg_quiz_score)); ?>%;"></div></div>
            </div>
            <div class="metric-card">
                <h3>Completion Rate</h3>
                <div class="value"><?php echo safe($lesson_completion_rate); ?>%</div>
                <div class="small-meta">Lesson progress completion across available course lessons</div>
                <div class="progress-track"><div class="progress-fill" style="width: <?php echo safe(min(100, $lesson_completion_rate)); ?>%;"></div></div>
            </div>
        </div>

        <section class="chart-card" style="margin-bottom: 22px;">
            <h3>Registration Trend (Last 7 Days)</h3>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(60px, 1fr)); gap: 10px; align-items: end; min-height: 170px;">
                <?php foreach ($trend_registrations as $item): ?>
                    <?php $height = max(10, min(100, (int)$item['total'] * 10)); ?>
                    <div style="display:flex; flex-direction:column; align-items:center; gap:8px;">
                        <div style="width:100%; background:#e2e8f0; border-radius:12px; overflow:hidden; height: calc(130px * <?php echo $height / 100; ?>); min-height: 24px; box-shadow: inset 0 0 0 rgba(37,99,235,0.15);"></div>
                        <span style="font-size:0.75rem; color:var(--muted);"><?php echo date('D', strtotime($item['day'])); ?></span>
                        <span style="font-size:0.82rem; font-weight:700; color:var(--text);"><?php echo safe($item['total']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="grid-3" style="margin-bottom: 22px;">
            <div class="table-card">
                <h3>Top Courses</h3>
                <table>
                    <thead>
                        <tr><th>Course</th><th>Enrollments</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top_courses)): ?>
                            <tr><td colspan="2">No course data available</td></tr>
                        <?php else: foreach ($top_courses as $course): ?>
                            <tr>
                                <td><?php echo safe($course['course_name']); ?></td>
                                <td><?php echo safe($course['total']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-card">
                <h3>Top Students</h3>
                <table>
                    <thead>
                        <tr><th>Student</th><th>Avg Score</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top_students)): ?>
                            <tr><td colspan="2">No exam data available</td></tr>
                        <?php else: foreach ($top_students as $student): ?>
                            <tr>
                                <td><?php echo safe($student['student_name']); ?></td>
                                <td><?php echo safe((int)$student['avg_score']); ?>%</td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="insight-card">
                <h3>AI Smart Insights</h3>
                <ul>
                    <?php foreach ($smart_insights as $insight): ?>
                        <li><?php echo safe($insight); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <section class="chart-card" style="margin-bottom: 22px;">
            <h3>Recent Activity</h3>
            <ul class="activity-list">
                <?php if (empty($activity_feed)): ?>
                    <li>No recent activity available.</li>
                <?php else: foreach ($activity_feed as $event): ?>
                    <li>
                        <div><?php echo safe($event['description']); ?></div>
                        <div class="activity-time"><?php echo date('M d, Y H:i', strtotime($event['created_at'])); ?></div>
                    </li>
                <?php endforeach; endif; ?>
            </ul>
        </section>
    </main>

    <div class="chat-float">
        <div class="chat-panel" id="aiChatPanel" aria-hidden="true">
            <div class="chat-panel-header">
                <h4>AI Assistant</h4>
                <button type="button" id="closeChatBtn" aria-label="Close chat">×</button>
            </div>
            <div class="chat-messages" id="chatMessages">
                <div class="chat-message ai">ሰላም! እርዎን እንኳን በደህና መጡ። የዛሬ እውቅናዎን ለማግኘት ምን ማግኘት ይፈልጋሉ?</div>
            </div>
            <div class="chat-input-row">
                <input type="text" id="chatInput" class="chat-input" placeholder="Ask AI anything..." aria-label="Chat message">
                <button type="button" class="chat-send" id="sendChatBtn">Send</button>
            </div>
        </div>
        <button type="button" class="chat-button" id="openChatBtn" aria-label="Open AI chat">💬</button>
    </div>

    <script>
        const openChatBtn = document.getElementById('openChatBtn');
        const closeChatBtn = document.getElementById('closeChatBtn');
        const aiChatPanel = document.getElementById('aiChatPanel');
        const chatMessages = document.getElementById('chatMessages');
        const chatInput = document.getElementById('chatInput');
        const sendChatBtn = document.getElementById('sendChatBtn');

        const appendMessage = (text, type = 'ai') => {
            const message = document.createElement('div');
            message.className = `chat-message ${type}`;
            message.textContent = text;
            chatMessages.appendChild(message);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        };

        const sendUserMessage = () => {
            const text = chatInput.value.trim();
            if (!text) return;
            appendMessage(text, 'user');
            chatInput.value = '';
            setTimeout(() => {
                appendMessage('እባክዎን ይጥይቁ... በጥሩ ሁኔታ እገልጻለሁ።', 'ai');
            }, 600);
        };

        openChatBtn.addEventListener('click', () => {
            aiChatPanel.classList.toggle('active');
            aiChatPanel.setAttribute('aria-hidden', aiChatPanel.classList.contains('active') ? 'false' : 'true');
        });

        closeChatBtn.addEventListener('click', () => {
            aiChatPanel.classList.remove('active');
            aiChatPanel.setAttribute('aria-hidden', 'true');
        });

        sendChatBtn.addEventListener('click', sendUserMessage);
        chatInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                sendUserMessage();
            }
        });
    </script>
</body>
</html>
