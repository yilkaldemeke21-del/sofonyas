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

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$query = trim($_GET['q'] ?? $_POST['q'] ?? '');
$certificate = null;
$verificationToken = '';
$errors = [];

if ($token !== '') {
    $query = $token;
    $verificationToken = $token;
}

if ($query !== '') {
    $normalized = trim($query);
    $tokenCandidate = preg_match('/^[a-f0-9]{32}$/i', $normalized) === 1;
    $certificateCodeCandidate = preg_match('/^VC[- ]?\d{1,10}$/i', $normalized) === 1;
    $numericCandidate = preg_match('/^\d{1,10}$/', $normalized) === 1;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        $dbErr = defined('DB_CONNECTION_ERROR') && DB_CONNECTION_ERROR !== '' ? DB_CONNECTION_ERROR : 'የዳታቤዝ ግንኙነት አልተፈጸመም። እባክዎ ከኮምፒውተሩ የMySQL/ MariaDB አገልግሎት እንደተነሳ ያረጋግጡ።';
        $errors[] = 'አስቸኳይ: ' . $dbErr;
    } else {
        if ($tokenCandidate) {
            $stmt = $pdo->prepare('SELECT * FROM certificates WHERE verification_token = :query LIMIT 1');
            $stmt->execute([':query' => $normalized]);
            $certificate = $stmt->fetch();
        }

        if (!$certificate && $certificateCodeCandidate) {
            $stmt = $pdo->prepare('SELECT * FROM certificates WHERE certificate_code = :query LIMIT 1');
            $stmt->execute([':query' => strtoupper($normalized)]);
            $certificate = $stmt->fetch();
        }

        if (!$certificate && $numericCandidate) {
            $stmt = $pdo->prepare('SELECT * FROM certificates WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => (int)$normalized]);
            $certificate = $stmt->fetch();
        }

        if ($certificate) {
            try {
                $updateStmt = $pdo->prepare('UPDATE certificates SET last_verified_at = NOW(), last_verified_ip = :ip, last_verified_user_agent = :user_agent WHERE id = :id');
                $updateStmt->execute([
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                    ':user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
                    ':id' => $certificate['id'],
                ]);
            } catch (PDOException $e) {
                error_log('Certificate verification audit failed: ' . $e->getMessage());
            }
        } else {
            $errors[] = 'ይህ ሰርቲፊኬት አልተገኘም። እባክዎ ሊንኩን ወይም QR ኮድን ትክክለኛ እንደሆነ ያረጋግጡ።';
        }
    }
}

$statusLabel = trim((string)($certificate['certificate_status'] ?? 'Valid'));
$isCertificateValid = strtolower($statusLabel) === 'valid';
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
      <input type="text" name="q" value="<?php echo safe($query ?? $verificationToken); ?>" placeholder="Verification token, certificate code, or certificate ID" required>
      <button type="submit">አረጋግጥ</button>
    </form>

    <?php if (!empty($errors)): ?>
      <div class="error"><?php echo safe(implode("<br>", $errors)); ?></div>
    <?php endif; ?>

    <?php if ($certificate): ?>
      <?php $statusLabel = trim((string)($certificate['certificate_status'] ?? 'Valid')); ?>
      <div class="result">
        <strong>Certificate Verified</strong>
        <p class="muted">This certificate record was found and validated successfully. Scan the QR code on the certificate or use the verification link below for trusted validation.</p>
        <div class="grid">
          <div class="mini"><h3>Certificate ID</h3><div class="value"><?php echo htmlspecialchars((string)($certificate['certificate_code'] ?? 'VC-' . str_pad((string)$certificate['id'], 6, '0', STR_PAD_LEFT)), ENT_QUOTES, 'UTF-8'); ?></div></div>
          <div class="mini"><h3>Student</h3><div class="value"><?php echo safe($certificate['student_name'] ?? 'Student'); ?></div></div>
          <div class="mini"><h3>Course / Exam</h3><div class="value"><?php echo safe($certificate['course_name'] ?? $certificate['exam_type'] ?? 'Certificate'); ?></div></div>
          <div class="mini"><h3>Status</h3><div class="value"><?php echo safe($statusLabel); ?></div></div>
          <div class="mini"><h3>Score</h3><div class="value"><?php echo (int)($certificate['score'] ?? 0); ?> / <?php echo (int)($certificate['total_questions'] ?? 0); ?></div></div>
          <div class="mini"><h3>Completion</h3><div class="value"><?php echo safe($certificate['completion_date'] ?? $certificate['issued_at'] ?? ''); ?></div></div>
          <div class="mini"><h3>Last Verified</h3><div class="value"><?php echo safe($certificate['last_verified_at'] ?? 'Not yet'); ?></div></div>
          <div class="mini"><h3>Verification IP</h3><div class="value"><?php echo safe($certificate['last_verified_ip'] ?? '-'); ?></div></div>
          <div class="mini"><h3>User Agent</h3><div class="value"><?php echo safe($certificate['last_verified_user_agent'] ?? '-'); ?></div></div>
        </div>
        <?php if (!empty($certificate['qr_code_url'])): ?>
          <div style="margin-top:16px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            <div style="min-width:150px; text-align:center;">
              <img src="<?php echo htmlspecialchars($certificate['qr_code_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="QR code" style="max-width:160px; border-radius:14px; border:1px solid #e5eefb; background:#fff; padding:10px;" />
              <div style="margin-top:8px; color:#475569; font-size:13px;">Scan QR code</div>
            </div>
            <?php if (!empty($certificate['verification_url'])): ?>
              <div style="flex:1; min-width:220px;">
                <div style="font-size:14px; font-weight:700; color:#1e293b; margin-bottom:6px;">Verification link</div>
                <a class="link" href="<?php echo htmlspecialchars($certificate['verification_url'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($certificate['verification_url'], ENT_QUOTES, 'UTF-8'); ?></a>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
