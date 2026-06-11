<?php
session_start();
require_once __DIR__ . '/db.php';

$roleLabel = 'Guest';
$backLink = 'dashboard.php';
$dashboardLabel = 'Dashboard';
if (isset($_SESSION['admin_id'])) {
    $roleLabel = 'Admin';
    $backLink = 'admin_dashboard.php';
    $dashboardLabel = 'Admin Dashboard';
} elseif (isset($_SESSION['student_id'])) {
    $roleLabel = 'Student';
    $backLink = 'student_dashboard.php';
    $dashboardLabel = 'Student Dashboard';
}

$statusMessage = '';
$statusType = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $statusMessage = 'የእርስዎ ውይይት ተልኳል። አስተማሪዎች ወይም ተማሪዎች መልስ ይሰጣሉ።';
        $statusType = 'success';
    } elseif ($_GET['status'] === 'error') {
        $statusMessage = 'እባክዎ ርዕስ እና መልእክት ይሙሉ።';
        $statusType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>Discussion Forum</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: linear-gradient(135deg, #eff6ff, #f8fafc); color: #0f172a; }
        .page { max-width: 1100px; margin: 0 auto; padding: 24px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff; padding: 18px 20px; border-radius: 16px; box-shadow: 0 12px 25px rgba(37, 99, 235, 0.18); }
        .topbar h1 { margin: 0 0 6px; font-size: 26px; }
        .topbar p { margin: 0; color: #eff6ff; }
        .chip { display: inline-block; padding: 8px 12px; background: rgba(255,255,255,0.14); border-radius: 999px; font-size: 13px; }
        .btn { display: inline-block; text-decoration: none; padding: 10px 14px; border-radius: 10px; background: #fff; color: #2563eb; font-weight: 700; border: 1px solid #dbeafe; }
        .btn:hover { transform: translateY(-1px); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; margin-top: 20px; }
        .card { background: #fff; border-radius: 14px; padding: 18px; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08); border: 1px solid #e5eefb; }
        .card h2 { margin-top: 0; margin-bottom: 8px; font-size: 18px; color: #1e3a8a; }
        .card p { color: #475569; line-height: 1.5; }
        ul { margin: 0; padding-left: 18px; color: #334155; line-height: 1.6; }
        .pill { display: inline-block; margin-top: 8px; padding: 5px 10px; border-radius: 999px; background: #dbeafe; color: #1d4ed8; font-size: 12px; font-weight: 700; }
        .mini-strip { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }
        .mini-chip { display: inline-flex; align-items: center; gap: 6px; padding: 8px 10px; border-radius: 999px; background: rgba(255,255,255,0.14); color: #eff6ff; font-size: 12px; font-weight: 700; border: 1px solid rgba(255,255,255,0.2); }
        .forum-form { display: flex; flex-direction: column; gap: 10px; }
        .forum-form input,
        .forum-form textarea { width: 100%; border: 1px solid #bfdbfe; border-radius: 10px; padding: 10px 12px; font-size: 14px; color: #0f172a; background: #fff; }
        .forum-form textarea { min-height: 98px; resize: vertical; }
        .action-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 4px; }
        .status-box { margin-bottom: 12px; padding: 10px 12px; border-radius: 10px; font-size: 14px; font-weight: 700; }
        .status-box.success { background: #ecfdf5; color: #166534; border: 1px solid #a7f3d0; }
        .status-box.error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .submit-btn { display: inline-flex; align-items: center; justify-content: center; border: none; border-radius: 10px; padding: 10px 16px; background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff; font-weight: 700; cursor: pointer; box-shadow: 0 10px 18px rgba(37, 99, 235, 0.18); }
        .submit-btn:hover { background: linear-gradient(135deg, #1d4ed8, #6d28d9); }
    </style>
</head>
<body>
<div class="page">
  <div class="topbar">
    <div>
      <h1>💬 መወያያ ፎርም</h1>
      <p>የተማሪዎች፣ አስተማሪዎች እና ኮሚዩኒቲ ውይይት ለመከታተል የተዘጋጀ።</p>
    </div>
    <div style="text-align:right;">
      <span class="chip">Role: <?php echo safe($roleLabel); ?></span>
      <div class="mini-strip" style="justify-content:flex-end;">
        <span class="mini-chip">📚 Students</span>
        <span class="mini-chip">🧑‍🏫 Admin</span>
        <span class="mini-chip">💬 Forum</span>
      </div>
      <div style="margin-top: 8px;"><a class="btn" href="<?php echo safe($backLink); ?>">← ወደ <?php echo safe($dashboardLabel); ?> ተመለስ</a></div>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <h2>👩‍🎓 የተማሪ ዕይታ</h2>
      <p>ተማሪዎች ጥያቄዎችን ይጻፋሉ፣ አስተማሪዎች የኮርስ አስተያየት ይሰጣሉ እና አስተዳዳሪዎች የማህበራዊ ውይይቱን ይቆጣጠራሉ።</p>
      <span class="pill">Student view</span>
    </div>
    <div class="card">
      <h2>👩‍🏫 አስተማሪ / አስተዳዳሪ እይታ</h2>
      <p>የኮርስ ማስታወሻዎች፣ የጉዳይ መመሪያዎች እና የእድገት ምላሾችን እንደ ቁልፍ መልእክቶች ይከታተሉ።</p>
      <span class="pill">Instructor & Admin</span>
    </div>
    <div class="card">
      <h2>🤝 ኮሚዩኒቲ ውይይት</h2>
      <p>እርስዎ እና ተማሪዎች ለመማር ሃሳብ፣ ለተግባር ማብራሪያ እና ለቅርብ እርዳታ ይገናኛሉ።</p>
      <span class="pill">Community support</span>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <h2>📝 ውይይት ለመጫን</h2>
      <p>ጥያቄዎን እና ሀሳብዎን በአጭር መልእክት ያስገቡ፣ ተማሪዎች እና አስተማሪዎች መልስ ይሰጣሉ።</p>
      <?php if ($statusMessage !== ''): ?>
        <div class="status-box <?php echo safe($statusType); ?>"><?php echo safe($statusMessage); ?></div>
      <?php endif; ?>
      <form class="forum-form" method="post" action="submit_discussion.php">
        <label for="topic" style="font-weight:700; color:#1e3a8a;">ርዕስ</label>
        <input id="topic" name="topic" type="text" placeholder="ለምሳሌ፡ የፈተና ምርመራ በትክክል እንዴት እንደሚደረግ?" />
        <label for="message" style="font-weight:700; color:#1e3a8a;">መልእክት</label>
        <textarea id="message" name="message" placeholder="ጥያቄዎን እዚህ ይጻፉ..."></textarea>
        <div class="action-row">
          <span class="pill">Discussion ready to post</span>
          <button class="submit-btn" type="submit">Submit</button>
        </div>
      </form>
    </div>
    <div class="card">
      <h2>አሁን ለምን ይጠቅማል?</h2>
      <ul>
        <li>የተማሪዎች ጥያቄዎች እና ምላሾች</li>
        <li>የአስተማሪዎች ማስታወሻ እና አስተያየት</li>
        <li>የኮሚዩኒቲ ውይይት እና የክፍል ማስተማር</li>
      </ul>
    </div>
    <div class="card">
      <h2>እንዴት መጠቀም ይቻላል?</h2>
      <ul>
        <li>መግቢያ ከዚህ እንደ አስተማሪ ወይም ተማሪ ይቀጥሉ</li>
        <li>በኮርስ ታሪክ ላይ ውይይት ይጀምሩ</li>
        <li>በመድረክ ላይ የአስተማሪ መልዕክት ይከታተሉ</li>
      </ul>
    </div>
  </div>
</div>
</body>
</html>
