<?php
require_once __DIR__ . '/db.php';
header('Cache-Control: no-store, max-age=0');
$token = trim((string)($_GET['token'] ?? ''));
$member = null;
$verificationAvailable = true;

if (preg_match('/^[a-f0-9]{64}$/i', $token)) {
    try {
        $stmt = $pdo->prepare('SELECT member_number, full_name, status FROM church_members WHERE verification_token = :token LIMIT 1');
        $stmt->execute([':token' => $token]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $exception) {
        error_log('Church member verification query failed: ' . $exception->getMessage());
        $verificationAvailable = false;
    }
}

$isEnglish = (($_GET['lang'] ?? 'am') === 'en');
?>
<!DOCTYPE html>
<html lang="<?php echo $isEnglish ? 'en' : 'am'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEnglish ? 'Church Member Verification' : 'የቤተክርስቲያን አባል ማረጋገጫ'; ?></title>
    <style>
        *{box-sizing:border-box}body{margin:0;padding:24px;background:#edf2ef;color:#17221e;font-family:Arial,sans-serif}.page{max-width:620px;margin:0 auto}.result{background:#fff;border:1px solid #d5dfd9;padding:24px}.result h1{font-size:22px;margin:0 0 16px}.status{display:inline-block;padding:7px 10px;background:#e5f4ec;color:#176244;font-weight:700}.inactive,.invalid{background:#fce8e7;color:#9b351f}.field{margin-top:16px}.label{display:block;color:#617168;font-size:12px;margin-bottom:4px}.value{font-weight:600;overflow-wrap:anywhere}.note{color:#617168;line-height:1.5}
    </style>
</head>
<body>
<main class="page"><section class="result">
    <h1><?php echo $isEnglish ? 'Church Member Verification' : 'የቤተክርስቲያን አባል ማረጋገጫ'; ?></h1>
    <?php if (!$verificationAvailable): ?>
        <p class="note"><?php echo $isEnglish ? 'Verification is temporarily unavailable.' : 'ማረጋገጫው ለጊዜው አይገኝም።'; ?></p>
    <?php elseif ($token === ''): ?>
        <p class="note"><?php echo $isEnglish ? 'To verify a member, scan the QR code on their church ID or open its verification link.' : 'አባልን ለማረጋገጥ በቤተክርስቲያን መታወቂያው ላይ ያለውን QR ኮድ ይቃኙ ወይም የማረጋገጫ ሊንኩን ይክፈቱ።'; ?></p>
    <?php elseif ($member): ?>
        <span class="status <?php echo $member['status'] === 'active' ? '' : 'inactive'; ?>"><?php echo $isEnglish ? ($member['status'] === 'active' ? 'ACTIVE MEMBER' : 'INACTIVE MEMBER') : ($member['status'] === 'active' ? 'ንቁ አባል' : 'ንቁ ያልሆነ አባል'); ?></span>
        <div class="field"><span class="label"><?php echo $isEnglish ? 'Member' : 'አባል'; ?></span><span class="value"><?php echo safe($member['full_name']); ?></span></div>
        <div class="field"><span class="label"><?php echo $isEnglish ? 'Member number' : 'የአባል ቁጥር'; ?></span><span class="value"><?php echo safe($member['member_number']); ?></span></div>
    <?php else: ?>
        <span class="status invalid"><?php echo $isEnglish ? 'NOT VERIFIED' : 'አልተረጋገጠም'; ?></span>
        <p class="note"><?php echo $isEnglish ? 'This verification link is invalid or unavailable.' : 'ይህ የማረጋገጫ ሊንክ ትክክል አይደለም ወይም አይገኝም።'; ?></p>
    <?php endif; ?>
</section></main>
</body>
</html>