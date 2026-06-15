<?php
session_start();
require_once __DIR__ . '/db.php';

$stmt = $pdo->query('SELECT * FROM courses ORDER BY created_at DESC');
$courses = $stmt->fetchAll();

$pdfItems = [];
$audioItems = [];
$videoItems = [];
$assignmentItems = [];

foreach ($courses as $course) {
    if (!empty($course['pdf_file'])) {
        $pdfItems[] = $course;
    }
    if (!empty($course['tutorial_audio'])) {
        $audioItems[] = $course;
    }
    if (!empty($course['tutorial_video'])) {
        $videoItems[] = $course;
    }
    if (!empty(trim((string)($course['assignment'] ?? '')))) {
        $assignmentItems[] = $course;
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>Download Center</title>
    <style>
        * { box-sizing: border-box; font-family: Arial, sans-serif; }
        body { margin: 0; background: linear-gradient(135deg, #eff6ff, #f8fafc); color: #0f172a; }
        .page { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 14px; background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff; border-radius: 18px; padding: 18px 20px; box-shadow: 0 16px 32px rgba(37, 99, 235, 0.18); }
        .topbar h1 { margin: 0 0 6px; font-size: 26px; }
        .topbar p { margin: 0; color: #eff6ff; }
        .chip { display: inline-block; padding: 8px 12px; border-radius: 999px; background: rgba(255,255,255,0.15); color: #fff; font-size: 12px; font-weight: 700; }
        .btn { display: inline-block; text-decoration: none; padding: 10px 14px; border-radius: 10px; background: #fff; color: #2563eb; font-weight: 700; border: 1px solid #dbeafe; }
        .btn.primary { background: #2563eb; color: #fff; border-color: #2563eb; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; margin-top: 18px; }
        .card { background: #fff; border-radius: 16px; border: 1px solid #e5eefb; box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08); padding: 16px; }
        .card h2 { margin: 0 0 8px; font-size: 18px; color: #111827; }
        .muted { color: #475569; }
        .item { border: 1px solid #e5eefb; border-radius: 12px; padding: 12px; background: #f8fbff; margin-bottom: 10px; }
        .item h3 { margin: 0 0 6px; font-size: 15px; color: #111827; }
        .pill { display: inline-flex; align-items: center; padding: 5px 10px; border-radius: 999px; background: #eff6ff; color: #1d4ed8; font-size: 12px; font-weight: 700; margin-bottom: 6px; }
        audio, video { width: 100%; border-radius: 10px; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
    </style>
</head>
<body>
<div class="page">
  <div class="topbar">
    <div>
      <h1>📥 የማዉረጃ ማዕከል</h1>
      <p>ከዚህ ላይ በፍጥነት ፒዲኤፍ፣ኦዲዮ፣ቪዲዮ እና አሳይመንት ስብስቦችን ማግኘት ይቻላል።</p>
    </div>
    <div style="text-align:right;">
      <span class="chip">የተማሪ የትምህርት መረጃዎች</span>
      <div class="actions" style="margin-top:8px; justify-content:flex-end;">
        <a class="btn primary" href="student_dashboard.php">የተማሪዎች ዳሽቦርድ</a>
        <a class="btn" href="library.php">ላይብራሪ</a>
      </div>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <h2>📄 ፒዲኤፎች</h2>
      <p class="muted">የኮርስ ጽሑፎችን፣ጠቃሚ ወረቀቶችን እና አጋዥ ፋይሎችን ማውረድ ይቻላል.</p>
      <?php if (empty($pdfItems)): ?>
        <p class="muted">No PDF files are available yet.</p>
      <?php else: foreach ($pdfItems as $course): ?>
        <div class="item">
          <h3><?php echo safe($course['course_name']); ?></h3>
          <p class="muted"><?php echo safe($course['instructor'] ?? 'Instructor'); ?></p>
          <a class="btn primary" href="<?php echo safe(publicMediaUrl($course['pdf_file'])); ?>" target="_blank" rel="noopener">ክፈት / ፒዲኤፍ አዉርድ</a>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <div class="card">
      <h2>🎧 ኦዲዮ</h2>
      <p class="muted">እባክዎ ይህንን ኦዲዮ አዳምጠው አጭር ማስታወሻ ይያዙ.</p>
      <?php if (empty($audioItems)): ?>
        <p class="muted">ምንም ኦዲዮ ፋይል የለም.</p>
      <?php else: foreach ($audioItems as $course): ?>
        <div class="item">
          <h3><?php echo safe($course['course_name']); ?></h3>
          <p class="muted"><?php echo safe($course['tutorial_topic'] ?: 'Lesson audio'); ?></p>
          <audio controls preload="metadata">
            <source src="<?php echo safe(publicMediaUrl($course['tutorial_audio'])); ?>">
            Your browser does not support the audio element.
          </audio>
          <p style="margin-top:8px;"><a class="btn" href="<?php echo safe(publicMediaUrl($course['tutorial_audio'])); ?>" target="_blank" rel="noopener">Open Audio Link</a></p>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <div class="card">
      <h2>🎥 ቪዲዮ</h2>
      <p class="muted">እባክዎ ይህንን የቪዲኦ ትምህርት በቀጥታ ከፍተው ይመልከቱ</p>
      <?php if (empty($videoItems)): ?>
        <p class="muted">ምንም ቪዲዮ ፋይል የለም.</p>
      <?php else: foreach ($videoItems as $course): ?>
        <div class="item">
          <h3><?php echo safe($course['course_name']); ?></h3>
          <p class="muted"><?php echo safe($course['tutorial_topic'] ?: 'Lesson video'); ?></p>
          <?php if (preg_match('/youtube\.com|youtu\.be/i', (string)$course['tutorial_video'])): ?>
            <iframe style="width:100%; border-radius:10px; min-height:180px; border:0;" src="<?php echo safe($course['tutorial_video']); ?>" allowfullscreen></iframe>
          <?php else: ?>
            <video controls preload="metadata">
              <source src="<?php echo safe(publicMediaUrl($course['tutorial_video'])); ?>">
              Your browser does not support the video element.
            </video>
          <?php endif; ?>
          <p style="margin-top:8px;"><a class="btn" href="<?php echo safe(publicMediaUrl($course['tutorial_video'])); ?>" target="_blank" rel="noopener">Open Video Link</a></p>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <div class="card">
      <h2>📝 አሳይመንቶች</h2>
      <p class="muted">እባክዎ ከእያንዳንዱ ኮርስ ጋር የተያያዙትን የአሳይመንት መመሪያዎች ይመልከቱ </p>
      <?php if (empty($assignmentItems)): ?>
        <p class="muted">ምንም የተጨመረ አሳይመንት የለም</p>
      <?php else: foreach ($assignmentItems as $course): ?>
        <div class="item">
          <span class="pill">Assignment</span>
          <h3><?php echo safe($course['course_name']); ?></h3>
          <div class="muted" style="margin-bottom:8px;">
            <?php echo renderRichText($course['assignment']); ?>
          </div>
          <a class="btn" href="tutorial.php">ኮርስ ክፈት</a>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
</body>
</html>
