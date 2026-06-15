<?php
session_start();
require_once __DIR__ . '/db.php';

$certificateIdInput = trim($_GET['id'] ?? $_POST['certificate_id'] ?? '');
$certificate = null;
$message = '';

if ($certificateIdInput !== '') {
    $normalized = strtoupper(trim($certificateIdInput));
    $id = $normalized;

    if (preg_match('/^VC[- ]?(\d+)$/', $normalized, $m)) {
        $id = (int)$m[1];
    } else {
        $id = filter_var($normalized, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false ? (int)$normalized : 0;
    }

    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM certificates WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $certificate = $stmt->fetch();

        if (!$certificate) {
            $message = 'ይህ ሰርቲፊኬት ተለይቶ አልተገኘም።';
        }
    } else {
        $message = 'እባክዎ ትክክለኛ የሰርቲፊኬት መለያ ቁጥር ያስገቡ።';
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

    <?php if ($message !== ''): ?>
      <div class="error"><?php echo safe($message); ?></div>
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
