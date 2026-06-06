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
        .wrap { max-width: 1100px; margin: 24px auto; padding: 0 18px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; margin-bottom: 22px; }
        .card { background: white; border-radius: 14px; box-shadow: 0 10px 24px rgba(15,23,42,0.08); padding: 18px; }
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; background: #eef2ff; color: #3730a3; font-size: 12px; font-weight: bold; }
        .big { font-size: 28px; font-weight: bold; color: #111827; margin-top: 8px; }
        ul { padding-left: 18px; color: #475569; line-height: 1.6; }
        .schedule { border-left: 4px solid #7c3aed; padding-left: 12px; }
        .chip { display: inline-block; background: #ecfdf5; color: #047857; border-radius: 999px; padding: 6px 10px; margin-right: 8px; font-size: 12px; font-weight: bold; }
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
        <p style="margin: 8px 0 0; color: #475569;">Join your live session, watch the YouTube stream, open the Zoom room, and use the chat panel for questions.</p>
        <p style="margin-top: 10px;">
            <a href="#live-session" style="display:inline-block; padding:10px 14px; background:#2563eb; color:#fff; text-decoration:none; border-radius:8px; font-weight:bold;">Join Live Class</a>
        </p>
    </div>

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

    <div class="card">
        <h3 style="margin-top:0;">## Live Classes</h3>
        <ul>
            <li>YouTube Live Integration</li>
            <li>Zoom Integration</li>
            <li>Live Chat</li>
            <li>Class Schedule</li>
        </ul>
        <p style="margin-top:10px; color:#475569;">ይህ ገጽ በዳሽቦርድ ላይ ከአዲስ አስተዳደር ክፍል ጋር ተገናኝቷል።</p>
    </div>

    <div class="card" id="live-session">
        <h3 style="margin-top:0;">Class Schedule</h3>
        <p class="schedule">Monday – 10:00 AM: ነገረ ሃይማኖት Live Session</p>
        <p class="schedule">Wednesday – 2:00 PM: ሐዋርያዊ ተልዕኮ Live Session</p>
        <p class="schedule">Friday – 4:00 PM: ነገረ ቅባት Live Q&A</p>
        <p style="margin-top:10px;">
            <span class="chip">YouTube</span>
            <span class="chip">Zoom</span>
            <span class="chip">Chat</span>
            <span class="chip">Schedule</span>
        </p>
    </div>
</div>
</body>
</html>
