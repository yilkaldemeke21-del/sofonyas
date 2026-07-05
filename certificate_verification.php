<?php
session_start();
require_once __DIR__ . '/db.php';

// Immediate guard: if DB connection failed, show clear Amharic error and stop.
if (!isset($pdo) || !$pdo instanceof PDO) {
  $dbErr = defined('DB_CONNECTION_ERROR') && DB_CONNECTION_ERROR !== '' ? DB_CONNECTION_ERROR : 'የዳታቤዝ ግንኙነት አልተፈጸመም። እባክዎ ከኮምፒውተሩ የMySQL/ MariaDB አገልግሎት እንደተነሳ ያረጋግጡ።';
  http_response_code(500);
  echo '<!doctype html><html><head><meta charset="utf-8"><title>DB Error</title></head><body style="font-family:Arial,sans-serif;padding:24px">';
  echo '<h2 style="color:#991b1b">አስቸኳይ: የዳታቤዝ ግንኙነት ተደርጎ አልተፈጸምም</h2>';
  echo '<p>' . htmlspecialchars($dbErr) . '</p>';
  echo '</body></html>';
  exit;
}

$certificateIdInput = trim($_GET['id'] ?? $_POST['certificate_id'] ?? '');
$certificate = null;
$errors = [];

if ($certificateIdInput !== '') {
  $normalized = strtoupper(trim($certificateIdInput));

  // Accept: VC-000123, VC 123, VC123, or plain numeric id e.g. 123
  if (preg_match('/^VC[- ]?(\d{1,10})$/i', $normalized, $m)) {
    $id = (int)$m[1];
  } elseif (preg_match('/^\d{1,10}$/', $normalized)) {
    $id = (int)$normalized;
  } else {
    $id = 0;
  }

  if ($id > 0) {
              if (!isset($pdo) || !$pdo instanceof PDO) {
                  $dbErr = defined('DB_CONNECTION_ERROR') && DB_CONNECTION_ERROR !== '' ? DB_CONNECTION_ERROR : 'የዳታቤዝ ግንኙነት አልተፈጸመም። እባክዎ ከኮምፒውተሩ የMySQL/ MariaDB አገልግሎት እንደተነሳ ያረጋግጡ።';
                  $errors[] = 'አስቸኳይ: ' . $dbErr;
              } else {
    $stmt = $pdo->prepare('SELECT * FROM certificates WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $certificate = $stmt->fetch();

    if (!$certificate) {
      $errors[] = 'ይህ ሰርቲፊኬት አልተገኘም። እባክዎ መለያውን ደግመው ያስገቡ።';
    }
              }
  } else {
    $errors[] = 'እባክዎ ትክክለኛ የሰርቲፊኬት መለያ ቁጥር (ለምሳሌ VC-000123 ወይም 123) ያስገቡ።';
  }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>Certificate Verification</title>
    <style>
        * { box-sizing: border-box; font-family: Arial, sans-serif; }
        body { margin: 0; background: linear-gradient(135deg, #eff6ff, #f8fafc); color: #0f172a; }
        .page { max-width: 980px; margin: 0 auto; padding: 24px; }
        .card { background: #fff; border-radius: 18px; padding: 22px; box-shadow: 0 14px 34px rgba(15,23,42,0.10); border: 1px solid #e5eefb; }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 14px; margin-bottom: 18px; }
        .pill { display: inline-block; padding: 8px 12px; border-radius: 999px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 12px; font-weight: 700; }
        h1 { margin: 0 0 8px; font-size: 28px; color: #111827; }
        p { color: #475569; line-height: 1.45; }
        form { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
        input { flex: 1 1 320px; padding: 12px 14px; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 15px; }
        button { background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff; border: none; border-radius: 10px; padding: 12px 16px; font-weight: 700; cursor: pointer; }
        .result { margin-top: 18px; border-radius: 14px; border: 1px solid #dbeafe; background: #f8fbff; padding: 14px; }
        .result strong { color: #111827; }
        .muted { color: #475569; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-top: 12px; }
        .mini { background: #fff; border: 1px solid #e5eefb; border-radius: 12px; padding: 12px; }
        .mini h3 { margin: 0 0 6px; font-size: 13px; text-transform: uppercase; letter-spacing: .08em; color: #475569; }
        .mini .value { font-size: 18px; font-weight: 800; color: #1e293b; }
        .error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 12px; padding: 12px; margin-top: 14px; }
        .link { color: #2563eb; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
<div class="page">
  <div class="card">
    <div class="topbar">
      <div>
        <span class="pill">ሶፊ ዌቭሣይት</span>
        <h1>ሰርቲፊኬት ማረጋገጫ</h1>
        <p>የሰርቲፊኬት መለያ ቁጥር በመጻፍ የሰርቲፊኬት መረጃን ያረጋግጡ።</p>
      </div>
      <a class="link" href="library.php">← ወደ ላይበራሪ</a>
    </div>

    <form method="get" action="certificate_verification.php">
      <input type="text" name="id" value="<?php echo safe($certificateIdInput); ?>" placeholder="Certificate ID (example: VC-000001 or 1)" required>
      <button type="submit">አረጋግጥ</button>
    </form>

    <?php if (!empty($errors)): ?>
      <div class="error"><?php echo safe(implode("<br>", $errors)); ?></div>
    <?php endif; ?>

    <?php if ($certificate): ?>
      <div class="result">
        <strong>Certificate Found</strong>
        <p class="muted">This record matches the certificate ID provided.</p>
        <div class="grid">
          <div class="mini"><h3>የሰርተፊኬት መለያ ቁጥር</h3><div class="value">VC-<?php echo str_pad((string)$certificate['id'], 6, '0', STR_PAD_LEFT); ?></div></div>
          <div class="mini"><h3>ተማሪ</h3><div class="value"><?php echo safe($certificate['student_name'] ?? 'Student'); ?></div></div>
          <div class="mini"><h3>የፈተናዉ አይነት</h3><div class="value"><?php echo safe($certificate['exam_type'] ?? 'Certificate'); ?></div></div>
          <div class="mini"><h3>Score</h3><div class="value"><?php echo (int)($certificate['score'] ?? 0); ?> / <?php echo (int)($certificate['total_questions'] ?? 0); ?></div></div>
          <div class="mini"><h3>የተሰጠ</h3><div class="value"><?php echo safe($certificate['issued_at'] ?? ''); ?></div></div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
