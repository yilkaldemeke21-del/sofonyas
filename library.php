<?php
session_start();
require_once __DIR__ . '/db.php';

$courseCount = (int)$pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
$studentCount = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
$registrationCount = (int)$pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn();
$paidCount = (int)$pdo->query('SELECT COUNT(*) FROM registrations WHERE payment_status = "paid"')->fetchColumn();
$revenue = (float)$pdo->query('SELECT COALESCE(SUM(amount),0) FROM registrations WHERE payment_status = "paid"')->fetchColumn();

$search = trim($_GET['q'] ?? '');
$searchLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';

$searchCourses = [];
$searchLessons = [];
$searchPdfs = [];

if ($search !== '') {
    $stmt = $pdo->prepare('SELECT * FROM courses WHERE course_name LIKE :term1 OR course_code LIKE :term2 OR short_description LIKE :term3 OR description LIKE :term4 OR tutorial_topic LIKE :term5 OR instructor LIKE :term6 ORDER BY created_at DESC LIMIT 100');
    $stmt->execute([
        ':term1' => $searchLike,
        ':term2' => $searchLike,
        ':term3' => $searchLike,
        ':term4' => $searchLike,
        ':term5' => $searchLike,
        ':term6' => $searchLike,
    ]);
    $searchCourses = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT cl.*, c.course_name FROM course_lessons cl JOIN courses c ON c.id = cl.course_id WHERE cl.title LIKE :term1 OR c.course_name LIKE :term2 OR c.description LIKE :term3 ORDER BY c.course_name, cl.sort_order LIMIT 100');
    $stmt->execute([
        ':term1' => $searchLike,
        ':term2' => $searchLike,
        ':term3' => $searchLike,
    ]);
    $searchLessons = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT * FROM courses WHERE pdf_file IS NOT NULL AND pdf_file <> "" AND (course_name LIKE :term1 OR description LIKE :term2 OR pdf_file LIKE :term3 OR tutorial_topic LIKE :term4) ORDER BY created_at DESC LIMIT 100');
    $stmt->execute([
        ':term1' => $searchLike,
        ':term2' => $searchLike,
        ':term3' => $searchLike,
        ':term4' => $searchLike,
    ]);
    $searchPdfs = $stmt->fetchAll();
}

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
    <?php echo renderSeoMeta(['title' => 'Library Dashboard', 'description' => 'Search courses, lessons, and study resources from the Sofoniyas learning platform.']); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
        @media (max-width: 980px) { .grid { grid-template-columns: 1fr; } .topbar { flex-direction: column; align-items: flex-start; } }
        @media (max-width: 768px) { .page { padding: 12px; } .stats { grid-template-columns: 1fr 1fr; } .actions { flex-direction: column; align-items: stretch; } .actions form { width: 100%; } .actions input { min-width: 0; width: 100%; } }
        @media (max-width: 576px) { .stats { grid-template-columns: 1fr; } .btn { width: 100%; text-align: center; } }
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
    <form method="get" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-left:auto;">
      <input type="search" name="q" value="<?php echo safe($search); ?>" placeholder="Search courses, lessons, PDFs" style="min-width:280px; padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; font-size:14px;">
      <button type="submit" class="btn primary" style="border:none; cursor:pointer;">Search</button>
      <?php if ($search !== ''): ?><a class="btn" href="library.php">Clear</a><?php endif; ?>
    </form>
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

  <?php if ($search !== ''): ?>
  <div class="card" style="margin-bottom:18px;">
    <h2 style="margin-bottom:8px;">Search Results for “<?php echo safe($search); ?>”</h2>
    <p class="muted">Courses, lessons, and PDFs are matched from the current LMS content.</p>
  </div>

  <div class="grid">
    <div class="card">
      <h2>📘 Courses</h2>
      <?php if (empty($searchCourses)): ?>
        <p class="muted">No course matched your keyword.</p>
      <?php else: ?>
        <ul style="padding-left:18px; margin:0; color:#334155;">
          <?php foreach ($searchCourses as $course): ?>
            <li style="margin-bottom:10px;"><strong><?php echo safe($course['course_name']); ?></strong> — <?php echo safe($course['course_code']); ?><br><small class="muted"><?php echo safe($course['short_description'] ?: $course['description'] ?: 'No description available'); ?></small></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>📗 Lessons</h2>
      <?php if (empty($searchLessons)): ?>
        <p class="muted">No lesson matched your keyword.</p>
      <?php else: ?>
        <ul style="padding-left:18px; margin:0; color:#334155;">
          <?php foreach ($searchLessons as $lesson): ?>
            <li style="margin-bottom:10px;"><strong><?php echo safe($lesson['title']); ?></strong><br><small class="muted">Course: <?php echo safe($lesson['course_name']); ?></small></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <div class="card" style="margin-top:18px;">
    <h2>📄 PDFs</h2>
    <?php if (empty($searchPdfs)): ?>
      <p class="muted">No PDF matched your keyword.</p>
    <?php else: ?>
      <ul style="padding-left:18px; margin:0; color:#334155;">
        <?php foreach ($searchPdfs as $pdf): ?>
          <li style="margin-bottom:10px;"><strong><?php echo safe($pdf['course_name']); ?></strong><br><a href="<?php echo safe(publicMediaUrl($pdf['pdf_file'])); ?>" target="_blank" rel="noopener">Open PDF</a> · <?php echo safe($pdf['instructor'] ?: 'Instructor'); ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
  <?php endif; ?>

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
