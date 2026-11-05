<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/management_schema.php';
require_once __DIR__ . '/admin_management_i18n.php';
if (empty($_SESSION['admin_id'])) {
    requireRole(['student'], $pdo);
}
ensureManagementTables($pdo);
$isAdmin = !empty($_SESSION['admin_id']);
$studentEmail = '';
if (!$isAdmin) {
    $accountStmt = $pdo->prepare('SELECT email, email_verified FROM students WHERE student_id = :student_id LIMIT 1');
    $accountStmt->execute([':student_id' => (string)($_SESSION['student_id'] ?? '')]);
    $account = $accountStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$account || (int)$account['email_verified'] !== 1) {
        http_response_code(403);
        exit(adminManagementText('member_portal_unverified'));
    }
    $studentEmail = strtolower(trim((string)$account['email']));
    $memberMatchCount = $pdo->prepare('SELECT COUNT(*) FROM church_members WHERE email = :email');
    $memberMatchCount->execute([':email' => $studentEmail]);
    $memberMatchTotal = (int)$memberMatchCount->fetchColumn();
    if ($memberMatchTotal !== 1) {
        http_response_code(403);
        exit(adminManagementText($memberMatchTotal > 1 ? 'member_portal_ambiguous' : 'member_portal_no_match'));
    }
}

$memberId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT m.id, m.member_number, m.full_name, m.email, m.status, m.photo_path, m.verification_token, b.branch_name FROM church_members m LEFT JOIN church_branches b ON b.id = m.branch_id WHERE m.id = :id LIMIT 1');
$stmt->execute([':id' => $memberId]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$member || (!$isAdmin && strtolower(trim((string)$member['email'])) !== $studentEmail)) {
    http_response_code(404);
    exit('Member not found.');
}

$verificationToken = (string)($member['verification_token'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/i', $verificationToken)) {
    $verificationToken = bin2hex(random_bytes(32));
    $update = $pdo->prepare('UPDATE church_members SET verification_token = :token WHERE id = :id');
    $update->execute([':token' => $verificationToken, ':id' => $memberId]);
}

$verificationUrl = buildAppUrl('verify_church_member.php?token=' . urlencode($verificationToken));
$attendanceCheckinUrl = buildAppUrl('church_attendance_qr_checkin.php?token=' . urlencode($verificationToken));
$qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . urlencode($attendanceCheckinUrl);
$photoUrl = !empty($member['photo_path']) ? 'church_member_photo.php?id=' . $memberId : '';
$isEnglish = $adminManagementLanguage === 'en';
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEnglish ? 'Church Member ID' : 'የቤተክርስቲያን አባል መታወቂያ'; ?></title>
    <style>
        *{box-sizing:border-box}body{margin:0;padding:24px;background:#edf2ef;color:#17221e;font-family:Arial,sans-serif}.page{max-width:760px;margin:0 auto}.toolbar{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:16px}.toolbar a,.toolbar button{border:0;background:#215e52;color:#fff;padding:10px 14px;border-radius:4px;text-decoration:none;font:inherit;cursor:pointer}.card{display:grid;grid-template-columns:1fr 240px;gap:24px;background:#fff;border:1px solid #d5dfd9;padding:24px}.identity{display:flex;gap:16px;align-items:center;border-bottom:1px solid #e4ebe7;padding-bottom:18px;margin-bottom:18px}.photo{width:88px;height:104px;object-fit:cover;border:1px solid #d5dfd9}.name{font-size:22px;font-weight:700;margin-bottom:7px}.number{font-size:14px;color:#51645b}.field{margin:14px 0}.label{display:block;color:#617168;font-size:12px;margin-bottom:4px}.value{font-size:15px;font-weight:600}.qr{display:grid;align-content:center;justify-items:center;border-left:1px solid #e4ebe7;padding-left:22px}.qr img{width:200px;height:200px}.qr p{font-size:12px;text-align:center;overflow-wrap:anywhere}.status{display:inline-block;padding:5px 9px;background:<?php echo $member['status'] === 'active' ? '#e5f4ec' : '#fce8e7'; ?>;color:<?php echo $member['status'] === 'active' ? '#176244' : '#9b351f'; ?>}
        @media(max-width:600px){body{padding:12px}.card{grid-template-columns:1fr}.qr{border-left:0;border-top:1px solid #e4ebe7;padding:18px 0 0}.toolbar{align-items:flex-start;flex-direction:column}}
        @media print{body{padding:0;background:#fff}.toolbar{display:none}.page{max-width:none}.card{border:1px solid #9aa8a0;min-height:320px;break-inside:avoid}}
    </style>
</head>
<body>
<main class="page">
    <nav class="toolbar"><a href="admin_church_members.php"><?php echo $isEnglish ? 'Back to members' : 'ወደ አባላት ተመለስ'; ?></a><button type="button" onclick="window.print()"><?php echo $isEnglish ? 'Print ID' : 'መታወቂያ አትም'; ?></button></nav>
    <section class="card">
        <div>
            <div class="identity">
                <?php if ($photoUrl !== ''): ?><img class="photo" src="<?php echo safe($photoUrl); ?>" alt="<?php echo $isEnglish ? 'Member photo' : 'የአባል ፎቶ'; ?>"><?php endif; ?>
                <div><div class="name"><?php echo safe($member['full_name']); ?></div><div class="number"><?php echo safe($member['member_number']); ?></div></div>
            </div>
            <div class="field"><span class="label"><?php echo $isEnglish ? 'Membership status' : 'የአባልነት ሁኔታ'; ?></span><span class="status"><?php echo $isEnglish ? ucfirst($member['status']) : ($member['status'] === 'active' ? 'ንቁ' : 'ንቁ ያልሆነ'); ?></span></div>
            <?php if (!empty($member['branch_name'])): ?><div class="field"><span class="label"><?php echo $isEnglish ? 'Branch' : 'ቅርንጫፍ'; ?></span><span class="value"><?php echo safe($member['branch_name']); ?></span></div><?php endif; ?>
            <div class="field"><span class="label"><?php echo $isEnglish ? 'Verification link' : 'የማረጋገጫ ሊንክ'; ?></span><span class="value"><?php echo safe($verificationUrl); ?></span></div>
        </div>
        <div class="qr"><img src="<?php echo safe($qrCodeUrl); ?>" alt="<?php echo $isEnglish ? 'Member attendance QR code' : 'የአባል ተሳትፎ QR ኮድ'; ?>"><p><?php echo $isEnglish ? 'Scan to open attendance check-in for this member.' : 'ይቃኙ አባሉን ለተሳትፎ ማስገባት ይጀምራል።'; ?></p></div>
    </section>
</main>
</body>
</html>