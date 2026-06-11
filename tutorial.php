<?php
session_start();
require_once __DIR__ . '/db.php';

$stmt = $pdo->query('SELECT * FROM courses ORDER BY created_at DESC LIMIT 12');
$courses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="am">
<head>
  <meta charset="UTF-8">
  <title>ተማሪ መማሪያ / ኮርሶች</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 0; background: #f4f7fb; color: #1f2937; }
    nav { background: #111827; padding: 12px 18px; }
    nav a { color: #fff; text-decoration: none; margin-right: 14px; font-weight: 700; }
    .wrap { max-width: 1100px; margin: 0 auto; padding: 24px; }
    .card { background: #fff; border-radius: 12px; padding: 18px; box-shadow: 0 8px 18px rgba(15,23,42,0.08); margin-bottom: 18px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
    .btn { display: inline-block; background: #2563eb; color: #fff; padding: 10px 14px; border-radius: 8px; text-decoration: none; margin-right: 8px; }
    .muted { color: #475569; }
  </style>
</head>
<body>
<nav>
  <a href="dashboard.php">Home</a>
  <a href="tutorial.php">Tutorial</a>
  <a href="exam20.php">Exam Center</a>
  <a href="student_login.php">Student Login</a>
  <a href="contact.html">Contact</a>
</nav>
<div class="wrap">
  <div class="card">
    <h1>ተማሪ መማሪያ እና ኮርሶች</h1>
    <p class="muted">እዚህ ከዳታቤዝ የተጫኑ ኮርሶችን ማየት እና የፈተና ማዕከል ለመድረስ ይችላሉ።</p>
    <a class="btn" href="student_login.php">ለተማሪ ግባ</a>
    <a class="btn" href="exam20.php">Exam Center</a>
  </div>

  <div class="card">
    <h2>የሚገኙ ኮርሶች</h2>
    <?php if (empty($courses)): ?>
      <p class="muted">እስካሁን ምንም ኮርስ አልተጨመረም።</p>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($courses as $course): ?>
          <div style="border:1px solid #e5e7eb; border-radius:10px; padding:14px; background:#f8fafc;">
            <h3 style="margin-top:0;"><?php echo htmlspecialchars($course['course_name']); ?></h3>
            <?php if (!empty($course['thumbnail'])): ?>
              <img src="<?php echo htmlspecialchars($course['thumbnail']); ?>" alt="<?php echo htmlspecialchars($course['course_name']); ?>" style="width:100%; max-height:180px; object-fit:cover; border-radius:8px; margin-bottom:10px;">
            <?php endif; ?>
            <?php if (!empty($course['short_description'])): ?>
              <p class="muted" style="margin-bottom:8px;"><strong>አጭር መግለጫ:</strong> <?php echo htmlspecialchars($course['short_description']); ?></p>
            <?php endif; ?>
            <p class="muted" style="margin-bottom:8px;"><?php echo htmlspecialchars($course['description'] ?: 'የኮርስ መግለጫ የለም'); ?></p>
            <?php if (!empty($course['category']) || !empty($course['level'])): ?>
              <p><strong>ምድብ / ደረጃ:</strong> <?php echo htmlspecialchars($course['category'] ?: ''); ?><?php echo (!empty($course['category']) && !empty($course['level']) ? ' · ' : ''); ?><?php echo htmlspecialchars($course['level'] ?: ''); ?></p>
            <?php endif; ?>
            <p><strong>ኮድ:</strong> <?php echo htmlspecialchars($course['course_code']); ?></p>
            <p><strong>ዋጋ:</strong> <?php echo number_format($course['price'], 2); ?> ብር</p>
            <?php if (!empty($course['modules'])): ?><p><strong>Modules:</strong> <?php echo nl2br(htmlspecialchars($course['modules'])); ?></p><?php endif; ?>
            <?php if (!empty($course['quiz'])): ?><p><strong>Quiz:</strong> <?php echo nl2br(htmlspecialchars($course['quiz'])); ?></p><?php endif; ?>
            <?php if (!empty($course['assignment'])): ?><p><strong>Assignment:</strong> <?php echo nl2br(htmlspecialchars($course['assignment'])); ?></p><?php endif; ?>
            <?php if (!empty($course['certificate_requirements'])): ?><p><strong>Certificate Requirements:</strong> <?php echo nl2br(htmlspecialchars($course['certificate_requirements'])); ?></p><?php endif; ?>
            <p>
              <a class="btn" href="student_register.php?course=<?php echo rawurlencode($course['course_name']); ?>&amount=<?php echo (float)$course['price']; ?>">ይመዝገቡ</a>
              <?php if (!empty($course['pdf_file'])): ?>
                <a class="btn" href="<?php echo htmlspecialchars($course['pdf_file']); ?>" target="_blank">PDF እይ</a>
              <?php endif; ?>
            </p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
