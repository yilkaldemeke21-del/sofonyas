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
    .rich-content h1, .rich-content h2, .rich-content h3 { font-size: 1.05rem; line-height: 1.35; margin: 0.35em 0; }
    .rich-content ul, .rich-content ol { padding-left: 18px; margin: 8px 0 10px; }
    .rich-content li { margin-bottom: 6px; }
    .rich-content p { margin: 0 0 8px; }
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
  <a href="sofonyas (2).html">Home</a>
  <a href="dashboard.php">Dashboard</a>
  <a href="tutorial.php">Tutorial</a>
  <a href="discussion_forum.php">Forum</a>
  <a href="library.php">Library</a>
  <a href="student_login.php">Student Login</a>
  <a href="admin_login.php">Admin Login</a>
  <a href="contact.html">Contact</a>
</nav>
<div class="wrap">
  <div class="card">
    <h1>ተማሪ መማሪያ እና ኮርሶች</h1>
    <p class="muted">እዚህ ከዳታቤዝ የተጫኑ ትምህርቶችን ተመልከት፣ አጭር እና ሙሉ መግለጫዎችን እና የትምህርት እንቅስቃሴዎችን ለተማሪዎች በቀላሉ እንዲያገኙ ይረዳል።</p>
    <a class="btn" href="student_login.php">ለተማሪ ግባ</a>
    <a class="btn" href="exam20.php">Exam Center</a>
  </div>

  <div class="card" id="courses">
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
              <div class="muted rich-content" style="margin-bottom:8px;"><strong>አጭር መግለጫ:</strong> <?php echo renderRichText($course['short_description']); ?></div>
            <?php endif; ?>
            <div class="muted rich-content" style="margin-bottom:8px;"><?php echo !empty($course['description']) ? renderRichText($course['description']) : 'የኮርስ መግለጫ የለም'; ?></div>
            <?php if (!empty($course['category']) || !empty($course['level'])): ?>
              <p><strong>ምድብ / ደረጃ:</strong> <?php echo htmlspecialchars($course['category'] ?: ''); ?><?php echo (!empty($course['category']) && !empty($course['level']) ? ' · ' : ''); ?><?php echo htmlspecialchars($course['level'] ?: ''); ?></p>
            <?php endif; ?>
            <p><strong>ኮድ:</strong> <?php echo htmlspecialchars($course['course_code']); ?></p>
            <p><strong>ዋጋ:</strong> <?php echo number_format($course['price'], 2); ?> ብር</p>
            <?php if (!empty($course['modules'])): ?><div class="rich-content" style="margin-bottom:10px;"><strong>Course Outline:</strong> <?php echo renderRichText($course['modules']); ?></div><?php endif; ?>
            <?php if (!empty($course['quiz'])): ?><div class="rich-content" style="margin-bottom:10px;"><strong>Quiz:</strong> <?php echo renderRichText($course['quiz']); ?></div><?php endif; ?>
            <?php if (!empty($course['assignment'])): ?><div class="rich-content" style="margin-bottom:10px;"><strong>Assignment:</strong> <?php echo renderRichText($course['assignment']); ?></div><?php endif; ?>
            <?php if (!empty($course['certificate_requirements'])): ?><div class="rich-content" style="margin-bottom:10px;"><strong>Certificate Requirements:</strong> <?php echo renderRichText($course['certificate_requirements']); ?></div><?php endif; ?>
            <p>
              <a class="btn" href="student_register.php?course=<?php echo rawurlencode($course['course_name']); ?>&amount=<?php echo (float)$course['price']; ?>">ይመዝገቡ ለዚህ ትምህርት</a>
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
