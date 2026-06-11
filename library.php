<?php
session_start();
require_once __DIR__ . '/db.php';

$courseCount = (int)$pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
$studentCount = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
$registrationCount = (int)$pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn();
$paidCount = (int)$pdo->query('SELECT COUNT(*) FROM registrations WHERE payment_status = "paid"')->fetchColumn();
$revenue = (float)$pdo->query('SELECT COALESCE(SUM(amount),0) FROM registrations WHERE payment_status = "paid"')->fetchColumn();

$recentCourses = $pdo->query('SELECT * FROM courses ORDER BY created_at DESC LIMIT 6')->fetchAll();
$recentRegistrations = $pdo->query('SELECT * FROM registrations ORDER BY created_at DESC LIMIT 8')->fetchAll();

$roleLabel = 'Guest';
if (isset($_SESSION['admin_id'])) {
    $roleLabel = 'Admin';
    $backLink = 'admin_dashboard.php';
} elseif (isset($_SESSION['student_id'])) {
    $roleLabel = 'Student';
    $backLink = 'student_dashboard.php';
} else {
    $backLink = 'tutorial.php';
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>Library Dashboard</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; background: linear-gradient(135deg, #eff6ff, #f8fafc); color: #0f172a; }
        .page { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff; padding: 18px 20px; border-radius: 16px; box-shadow: 0 12px 25px rgba(37, 99, 235, 0.18); }
        .topbar h1 { margin: 0 0 6px; font-size: 26px; }
        .topbar p { margin: 0; color: #eff6ff; }
        .chip { display: inline-block; padding: 8px 12px; background: rgba(255,255,255,0.14); border-radius: 999px; font-size: 13px; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
        .btn { display: inline-block; text-decoration: none; padding: 10px 14px; border-radius: 10px; background: #fff; color: #2563eb; font-weight: 700; border: 1px solid #dbeafe; }
        .btn.primary { background: #2563eb; color: #fff; border-color: #2563eb; }
        .btn:hover { transform: translateY(-1px); }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin: 24px 0; }
        .card { background: #fff; border-radius: 14px; padding: 16px; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08); border: 1px solid #e5eefb; }
        .card h2 { margin: 0 0 8px; font-size: 15px; color: #475569; }
        .card .value { font-size: 28px; font-weight: 800; color: #1d4ed8; }
        .grid { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 18px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { background: #f8fbff; color: #334155; font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; }
        .badge { display: inline-block; padding: 5px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .badge.paid { background: #dcfce7; color: #166534; }
        .badge.unpaid { background: #fee2e2; color: #b91c1c; }
        .muted { color: #475569; }
        @media (max-width: 980px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="page">
  <div class="topbar">
    <div>
      <h1>📚 Library Dashboard</h1>
      <p>የቤተ መጽሐፍ እና የኮርስ እይታ። ኮርሶችን፣ ተመዝጋቢዎችን እና የክፍያ ሁኔታዎችን በአጭር ሁኔታ ይከታተሉ።</p>
    </div>
    <div style="text-align:right;">
      <span class="chip">Role: <?php echo safe($roleLabel); ?></span>
      <div style="margin-top: 8px;"><a class="btn" href="<?php echo safe($backLink); ?>">← ወደ ዳሽቦርዱ ተመለስ</a></div>
    </div>
  </div>

  <div class="actions">
    <a class="btn primary" href="tutorial.php">📖 ትምህርት / ኮርሶች</a>
    <a class="btn" href="register.php">📝 ኮርስ መመዝገብ</a>
    <?php if (isset($_SESSION['admin_id'])): ?>
      <a class="btn" href="admin_courses.php">➕ ኮርስ ጨምር</a>
      <a class="btn" href="admin_view_courses.php">🛠️ ኮርሶችን አስተካክል</a>
    <?php endif; ?>
  </div>

  <div class="stats">
    <div class="card"><h2>ጠቅላላ ኮርሶች</h2><div class="value"><?php echo $courseCount; ?></div></div>
    <div class="card"><h2>ጠቅላላ ተማሪዎች</h2><div class="value"><?php echo $studentCount; ?></div></div>
    <div class="card"><h2>ጠቅላላ ምዝገቦች</h2><div class="value"><?php echo $registrationCount; ?></div></div>
    <div class="card"><h2>ክፍያ የተከፈለ</h2><div class="value"><?php echo $paidCount; ?></div></div>
    <div class="card"><h2>ጠቅላላ ገቢ (ብር)</h2><div class="value"><?php echo number_format($revenue, 2); ?></div></div>
  </div>

  <div class="grid">
    <div class="card">
      <h2>የቅርብ ኮርሶች</h2>
      <?php if (empty($recentCourses)): ?>
        <p class="muted">እስካሁን ምንም ኮርስ አልተጨመረም።</p>
      <?php else: ?>
        <table>
          <thead>
            <tr><th>ኮርስ</th><th>ኮድ</th><th>ዋጋ</th><th>መረጃ</th></tr>
          </thead>
          <tbody>
            <?php foreach ($recentCourses as $course): ?>
              <tr>
                <td><?php echo safe($course['course_name']); ?></td>
                <td><?php echo safe($course['course_code']); ?></td>
                <td><?php echo number_format((float)$course['price'], 2); ?> ብር</td>
                <td><?php echo safe($course['description'] ?: 'ምንም መግለጫ አልተሰጠም'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>የቅርብ ምዝገቦች</h2>
      <?php if (empty($recentRegistrations)): ?>
        <p class="muted">እስካሁን ምንም ምዝገባ የለም።</p>
      <?php else: ?>
        <table>
          <thead>
            <tr><th>ተማሪ</th><th>ኮርስ</th><th>ሁኔታ</th></tr>
          </thead>
          <tbody>
            <?php foreach ($recentRegistrations as $entry): ?>
              <tr>
                <td><?php echo safe($entry['name']); ?><br><small class="muted"><?php echo safe($entry['email']); ?></small></td>
                <td><?php echo safe($entry['course']); ?></td>
                <td><span class="badge <?php echo ($entry['payment_status'] === 'paid' ? 'paid' : 'unpaid'); ?>"><?php echo safe($entry['payment_status']); ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
