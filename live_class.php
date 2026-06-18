<?php
session_start();
require_once __DIR__ . '/db.php';

$role = null;
if (isset($_SESSION['admin_id'])) {
    $role = 'admin';
} elseif (isset($_SESSION['student_id'])) {
    $role = 'student';
}

if (!$role) {
    header('Location: student_login.php');
    exit;
}

if ($role === 'student') {
    $studentId = $_SESSION['student_id'];
    $stmt = $pdo->prepare('SELECT name FROM students WHERE student_id = :student_id');
    $stmt->execute([':student_id' => $studentId]);
    $student = $stmt->fetch();
    $user_name = $student['name'] ?? 'Student';
    $back_link = 'student_dashboard.php';
} else {
    $user_name = $_SESSION['admin_username'] ?? 'Admin';
    $back_link = 'admin_dashboard.php';
}

$error = '';
$success = '';

function isGoogleMeetUrl(string $url): bool
{
    return preg_match('/(meet\.google\.com|google\.com\/meet)/i', $url) === 1;
}

function isZoomUrl(string $url): bool
{
    return preg_match('/(zoom\.us|zoom\.com|\\bzoom\\b)/i', $url) === 1;
}

function labelForLink(string $url, string $fallback): string
{
    if (isGoogleMeetUrl($url)) {
        return 'Google Meet';
    }
    if (isZoomUrl($url)) {
        return 'Zoom';
    }
    return $fallback;
}

function ensureLiveClassTables(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS live_class_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        session_date DATETIME DEFAULT NULL,
        stream_url VARCHAR(500) DEFAULT NULL,
        room_url VARCHAR(500) DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT "scheduled",
        created_by VARCHAR(100) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_live_class_sessions_date (session_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $pdo->exec('CREATE TABLE IF NOT EXISTS live_class_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        student_id VARCHAR(100) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        question TEXT NOT NULL,
        answer TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_live_class_questions_session (session_id),
        INDEX idx_live_class_questions_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

try {
    ensureLiveClassTables($pdo);
} catch (Throwable $e) {
    error_log('Live class schema validation failed: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'admin' && isset($_POST['create_session'])) {
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $sessionDate = trim((string)($_POST['session_date'] ?? ''));
    $streamUrl = trim((string)($_POST['stream_url'] ?? ''));
    $roomUrl = trim((string)($_POST['room_url'] ?? ''));

    if ($title === '') {
        $error = 'Please enter a live class title.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO live_class_sessions (title, description, session_date, stream_url, room_url, status, created_by) VALUES (:title, :description, :session_date, :stream_url, :room_url, :status, :created_by)');
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':session_date' => $sessionDate !== '' ? $sessionDate : null,
            ':stream_url' => $streamUrl !== '' ? $streamUrl : null,
            ':room_url' => $roomUrl !== '' ? $roomUrl : null,
            ':status' => 'scheduled',
            ':created_by' => $user_name,
        ]);
        $success = 'Live class session saved successfully.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'student' && isset($_POST['ask_question'])) {
    $sessionId = (int)($_POST['session_id'] ?? 0);
    $question = trim((string)($_POST['question'] ?? ''));

    if ($sessionId > 0 && $question !== '') {
        $stmt = $pdo->prepare('INSERT INTO live_class_questions (session_id, student_id, student_name, question) VALUES (:session_id, :student_id, :student_name, :question)');
        $stmt->execute([
            ':session_id' => $sessionId,
            ':student_id' => $studentId,
            ':student_name' => $user_name,
            ':question' => $question,
        ]);
        $success = 'Your question has been sent to the class panel.';
    } else {
        $error = 'Please enter a valid question before sending it.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'admin' && isset($_POST['answer_question'])) {
    $questionId = (int)($_POST['question_id'] ?? 0);
    $answer = trim((string)($_POST['answer'] ?? ''));
    if ($questionId > 0 && $answer !== '') {
        $stmt = $pdo->prepare('UPDATE live_class_questions SET answer = :answer WHERE id = :id');
        $stmt->execute([
            ':answer' => $answer,
            ':id' => $questionId,
        ]);
        $success = 'Answer saved for the student question.';
    } else {
        $error = 'Please enter an answer before saving.';
    }
}

$stmt = $pdo->query('SELECT * FROM live_class_sessions ORDER BY session_date ASC, id DESC');
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sessionQuestions = [];
if (!empty($sessions)) {
    $qStmt = $pdo->prepare('SELECT * FROM live_class_questions WHERE session_id = :session_id ORDER BY created_at ASC');
    foreach ($sessions as $session) {
        $qStmt->execute([':session_id' => (int)$session['id']]);
        $sessionQuestions[(int)$session['id']] = $qStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>Live Classes</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f5f7fb; color: #1f2937; }
        .topbar { background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%); color: white; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; }
        .topbar a { color: white; text-decoration: none; background: rgba(255,255,255,0.12); padding: 8px 12px; border-radius: 8px; }
        .wrap { max-width: 1120px; margin: 24px auto; padding: 0 18px 40px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; margin-bottom: 22px; }
        .card { background: white; border-radius: 14px; box-shadow: 0 10px 24px rgba(15,23,42,0.08); padding: 18px; }
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; background: #eef2ff; color: #3730a3; font-size: 12px; font-weight: bold; }
        .big { font-size: 28px; font-weight: bold; color: #111827; margin-top: 8px; }
        .schedule { border-left: 4px solid #7c3aed; padding-left: 12px; }
        .chip { display: inline-block; background: #ecfdf5; color: #047857; border-radius: 999px; padding: 6px 10px; margin-right: 8px; font-size: 12px; font-weight: bold; }
        .alert { padding: 12px; border-radius: 10px; margin-bottom: 14px; }
        .alert.error { background: #fee2e2; color: #991b1b; }
        .alert.success { background: #dcfce7; color: #166534; }
        .btn { display: inline-block; padding: 10px 12px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold; }
        .btn.secondary { background: #7c3aed; }
        .btn.outline { background: #fff; color: #2563eb; border: 1px solid #bfdbfe; }
        .session-card { margin-top: 16px; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; }
        .session-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .session-meta { color: #475569; font-size: 14px; }
        .question-list { display: grid; gap: 10px; }
        .question-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; }
        input, textarea, select { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #cbd5e1; margin-top: 6px; }
        textarea { min-height: 90px; }
        label { font-weight: bold; color: #334155; display: block; margin-top: 10px; }
    </style>
</head>
<body>
<div class="topbar">
    <h2 style="margin: 0;">📡 ላይቭ ክላስ</h2>
    <a href="<?php echo $back_link; ?>">ወደ ዳሽቦርድ</a>
</div>
<div class="wrap">
    <div class="card" style="margin-bottom: 18px; background: linear-gradient(135deg, #eff6ff 0%, #f5f3ff 100%);">
        <h3 style="margin-top:0;">Welcome, <?php echo safe($user_name); ?></h3>
        <p style="margin: 8px 0 0; color: #475569;">Join your live session, watch the stream, open Zoom or Google Meet links, and use the Q&A panel for questions.</p>
        <p style="margin-top: 10px;">
            <a href="#live-session" class="btn">Join Live Class</a>
        </p>
    </div>

    <?php if ($error !== ''): ?><div class="alert error"><?php echo safe($error); ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="alert success"><?php echo safe($success); ?></div><?php endif; ?>

    <div class="grid">
        <div class="card">
            <span class="badge">YouTube Live</span>
            <div class="big">Live Stream</div>
            <p style="color:#475569; margin-top:8px;">YouTube ቀጥታ ስትሬም እና የቀጥታ ክፍል ለመጫን ዝግጁ ይሆናል።</p>
        </div>
        <div class="card">
            <span class="badge">Zoom Integration</span>
            <div class="big">Virtual Room</div>
            <p style="color:#475569; margin-top:8px;">Zoom ግቢ እና ማእቀፍ ለማስተባበር የተዋቀረ ነው።</p>
        </div>
        <div class="card">
            <span class="badge">Live Chat</span>
            <div class="big">Student Q&A</div>
            <p style="color:#475569; margin-top:8px;">ተማሪዎች ለክፍል በሚገቡበት ጊዜ ቀጥታ ጥያቄ መጠየቅ ይችላሉ።</p>
        </div>
    </div>

    <?php if ($role === 'admin'): ?>
        <div class="card" style="margin-bottom: 16px;">
            <h3 style="margin-top:0;">Create Live Session</h3>
            <form method="post">
                <input type="hidden" name="create_session" value="1">
                <label for="title">Session Title</label>
                <input id="title" name="title" placeholder="Example: Bible Study Live Class" required>
                <label for="description">Session Description</label>
                <textarea id="description" name="description" placeholder="Explain what the session will cover"></textarea>
                <label for="session_date">Session Date & Time</label>
                <input id="session_date" name="session_date" type="datetime-local">
                <label for="stream_url">YouTube / Stream URL</label>
                <input id="stream_url" name="stream_url" placeholder="https://youtube.com/watch?v=... or https://meet.google.com/...">
                <label for="room_url">Zoom / Google Meet Link</label>
                <input id="room_url" name="room_url" placeholder="https://zoom.us/j/... or https://meet.google.com/...">
                <p style="margin-top: 10px;"><button class="btn" type="submit">Save Session</button></p>
            </form>
        </div>
    <?php endif; ?>

    <div class="card" id="live-session">
        <h3 style="margin-top:0;">Live Session Schedule</h3>
        <?php if (empty($sessions)): ?>
            <p class="schedule">No live sessions are available yet. Please create one from the admin panel.</p>
        <?php else: ?>
            <?php foreach ($sessions as $session): ?>
                <?php $sessionId = (int)$session['id']; ?>
                <div class="session-card">
                    <h4 style="margin:0;"><?php echo safe($session['title'] ?? 'Live Session'); ?></h4>
                    <p class="session-meta" style="margin: 6px 0;">
                        <?php echo !empty($session['session_date']) ? safe(date('D, M d Y H:i', strtotime($session['session_date']))) : 'Time not set'; ?>
                    </p>
                    <?php if (!empty($session['description'])): ?>
                        <p style="margin: 8px 0; color:#475569;"><?php echo safe($session['description']); ?></p>
                    <?php endif; ?>
                    <div class="session-actions">
                        <?php if (!empty($session['stream_url'])): ?>
                            <a class="btn" href="<?php echo safe($session['stream_url']); ?>" target="_blank" rel="noopener">
                                <?php echo safe(labelForLink((string)$session['stream_url'], 'Open Stream Link')); ?>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($session['room_url'])): ?>
                            <a class="btn secondary" href="<?php echo safe($session['room_url']); ?>" target="_blank" rel="noopener">
                                <?php echo safe(labelForLink((string)$session['room_url'], 'Open Meeting Link')); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top: 14px;">
                        <h5 style="margin: 0 0 8px;">Student Q&A</h5>
                        <?php if ($role === 'student'): ?>
                            <form method="post">
                                <input type="hidden" name="ask_question" value="1">
                                <input type="hidden" name="session_id" value="<?php echo $sessionId; ?>">
                                <label for="question_<?php echo $sessionId; ?>">Ask a question</label>
                                <textarea id="question_<?php echo $sessionId; ?>" name="question" placeholder="Type your question here"></textarea>
                                <p style="margin: 10px 0 0;"><button class="btn outline" type="submit">Send Question</button></p>
                            </form>
                        <?php endif; ?>
                        <div class="question-list" style="margin-top: 12px;">
                            <?php foreach (($sessionQuestions[$sessionId] ?? []) as $question): ?>
                                <div class="question-item">
                                    <strong><?php echo safe($question['student_name'] ?? 'Student'); ?></strong>
                                    <p style="margin: 6px 0; color:#0f172a;"><?php echo safe($question['question'] ?? ''); ?></p>
                                    <?php if ($role === 'admin' && !empty($question['answer'])): ?>
                                        <p style="margin: 6px 0 0; color:#166534;"><strong>Answer:</strong> <?php echo safe($question['answer']); ?></p>
                                    <?php elseif ($role === 'admin'): ?>
                                        <form method="post" style="margin-top:8px;">
                                            <input type="hidden" name="answer_question" value="1">
                                            <input type="hidden" name="question_id" value="<?php echo (int)$question['id']; ?>">
                                            <textarea name="answer" placeholder="Write the answer here"></textarea>
                                            <p style="margin: 8px 0 0;"><button class="btn outline" type="submit">Save Answer</button></p>
                                        </form>
                                    <?php elseif (!empty($question['answer'])): ?>
                                        <p style="margin: 6px 0 0; color:#166534;"><strong>Answer:</strong> <?php echo safe($question['answer']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
