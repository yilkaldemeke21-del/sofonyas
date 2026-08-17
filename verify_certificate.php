<?php
session_start();
require_once __DIR__ . '/db.php';

$certificateIdInput = trim((string)($_GET['id'] ?? $_POST['certificate_id'] ?? ''));
$certificate = null;
$errors = [];
$verificationMessage = 'CERTIFICATE NOT VALID';
$statusLabel = 'Not Valid';
$statusClass = 'invalid';
$verificationTimestamp = '';
$verificationQrSrc = '';
$certificateData = [
    'certificate_id' => '',
    'student_name' => '-',
    'course_name' => '-',
    'score' => '-',
    'issued_at' => '-',
    'instructor_name' => '-',
    'status' => 'Not Valid',
];

if ($certificateIdInput !== '') {
    $qrTarget = buildAppUrl('verify_certificate.php?id=' . urlencode($certificateIdInput));
    $verificationQrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' . urlencode($qrTarget);
}

if ($certificateIdInput !== '') {
    $normalized = trim($certificateIdInput);
    $searchById = null;
    $searchByCode = null;

    if (preg_match('/^VC[- ]?(\d{1,10})$/i', $normalized, $matches)) {
        $searchById = (int)$matches[1];
        $searchByCode = strtoupper($normalized);
    } elseif (preg_match('/^\d{1,10}$/', $normalized)) {
        $searchById = (int)$normalized;
        $searchByCode = $normalized;
    } else {
        $searchByCode = $normalized;
    }

    $querySql = 'SELECT * FROM certificates WHERE id = :id';
    $queryParams = [':id' => $searchById ?? 0];

    if (function_exists('columnExists') && columnExists($pdo, 'certificates', 'certificate_code')) {
        $querySql = 'SELECT * FROM certificates WHERE id = :id OR certificate_code = :certificate_code LIMIT 1';
        $queryParams[':certificate_code'] = $searchByCode;
    } else {
        // Fallback when the certificate_code column is not present.
        $querySql .= ' LIMIT 1';
    }

    $stmt = $pdo->prepare($querySql);
    $stmt->execute($queryParams);
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$certificate) {
        $errors[] = 'Certificate Not Found';
    } else {
        $certificateStatus = trim((string)($certificate['certificate_status'] ?? 'Valid'));
        if ($certificateStatus === '') {
            $certificateStatus = 'Valid';
        }

        $lowerStatus = strtolower($certificateStatus);
        if (str_contains($lowerStatus, 'revoked') || str_contains($lowerStatus, 'expired')) {
            $verificationMessage = 'CERTIFICATE REVOKED/EXPIRED';
            $statusLabel = 'Revoked / Expired';
            $statusClass = 'revoked';
        } elseif ($lowerStatus === 'valid') {
            $verificationMessage = 'CERTIFICATE VALID';
            $statusLabel = 'Valid';
            $statusClass = 'valid';
        } else {
            $verificationMessage = 'CERTIFICATE NOT VALID';
            $statusLabel = $certificateStatus;
            $statusClass = 'invalid';
        }

        $certificateCode = trim((string)($certificate['certificate_code'] ?? ''));
        if ($certificateCode === '') {
            $certificateCode = 'VC-' . str_pad((string)$certificate['id'], 6, '0', STR_PAD_LEFT);
        }

        $score = '-';
        if (isset($certificate['score']) || isset($certificate['total_questions'])) {
            $score = (int)($certificate['score'] ?? 0) . ' / ' . max(0, (int)($certificate['total_questions'] ?? 0));
            if ($score === '0 / 0') {
                $score = '-';
            }
        }

        $certificateData = [
            'certificate_id' => $certificateCode,
            'student_name' => trim((string)($certificate['student_name'] ?? '-')),
            'course_name' => trim((string)($certificate['course_name'] ?? $certificate['exam_type'] ?? $certificate['certificate_title'] ?? '-')),
            'score' => $score,
            'issued_at' => trim((string)($certificate['completion_date'] ?? $certificate['issued_at'] ?? '-')),
            'instructor_name' => trim((string)($certificate['signer_name'] ?? $certificate['instructor_name'] ?? '-')),
            'status' => $statusLabel,
        ];

        $verificationTimestamp = date('Y-m-d H:i:s');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification</title>
    <style>
        :root { font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f1f5f9; color: #0f172a; }
        .page { width: min(100%, 1120px); margin: 0 auto; padding: 24px; }
        .panel { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 24px; padding: 28px; box-shadow: 0 24px 60px rgba(15,23,42,0.08); }
        .brand { display: inline-flex; align-items: center; gap: 10px; font-weight: 800; font-size: 14px; letter-spacing: .12em; text-transform: uppercase; color: #1d4ed8; }
        .brand-dot { width: 10px; height: 10px; border-radius: 999px; background: #2563eb; }
        h1 { margin: 16px 0 6px; font-size: clamp(2rem, 2.5vw, 3rem); line-height: 1.05; }
        p.lead { margin: 0; color: #475569; font-size: 1rem; max-width: 760px; }
        form { display: grid; gap: 14px; margin-top: 22px; }
        .input-row { display: grid; grid-template-columns: 1fr auto; gap: 12px; }
        input[type="text"] { width: 100%; min-height: 54px; padding: 0 18px; border-radius: 16px; border: 1px solid #cbd5e1; font-size: 16px; color: #0f172a; }
        button, .secondary-btn { min-height: 54px; border-radius: 16px; padding: 0 24px; font-size: 16px; font-weight: 700; cursor: pointer; }
        button { border: none; background: linear-gradient(135deg,#2563eb,#7c3aed); color: #fff; }
        .secondary-btn { background: #fff; border: 1px solid #cbd5e1; color: #0f172a; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .status-card { margin-top: 26px; padding: 28px; border-radius: 22px; border: 1px solid transparent; background: #ffffff; }
        .status-card.valid { border-color: #16a34a; background: linear-gradient(180deg, rgba(236,253,245,0.88), #ffffff 90%); }
        .status-card.invalid, .status-card.revoked { border-color: #dc2626; background: linear-gradient(180deg, rgba(254,226,226,0.9), #ffffff 90%); }
        .status-badge { display: inline-flex; align-items: center; gap: 12px; padding: 16px 20px; border-radius: 18px; font-weight: 700; }
        .status-badge.valid { color: #166534; background: rgba(220,253,228,0.95); }
        .status-badge.invalid, .status-badge.revoked { color: #991b1b; background: rgba(254,226,226,0.95); }
        .status-icon { width: 28px; height: 28px; display: inline-grid; place-items: center; border-radius: 999px; color: #fff; }
        .status-icon.valid { background: #16a34a; }
        .status-icon.invalid, .status-icon.revoked { background: #b91c1c; }
        .details-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-top: 18px; }
        .detail-card { padding: 18px 20px; border-radius: 18px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .detail-card h3 { margin: 0 0 8px; font-size: 12px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #334155; }
        .detail-card p { margin: 0; font-size: 16px; font-weight: 700; color: #0f172a; word-break: break-word; }
        .helper { margin-top: 24px; padding: 22px; border-radius: 22px; background: #eff6ff; border: 1px solid #dbeafe; }
        .helper strong { display: block; margin-bottom: 8px; font-size: 16px; }
        .qr-box { margin-top: 24px; padding: 20px; border: 1px solid #dbeafe; border-radius: 20px; background: linear-gradient(180deg, #f8fbff, #ffffff); display: inline-flex; align-items: center; gap: 18px; flex-wrap: wrap; }
        .qr-box img { width: 150px; height: 150px; object-fit: contain; background: #fff; border-radius: 18px; border: 1px solid #dbeafe; padding: 10px; box-shadow: 0 8px 18px rgba(30,41,59,0.08); }
        .qr-box .qr-meta { max-width: 260px; }
        .qr-box .qr-meta strong { display: block; margin-bottom: 6px; font-size: 15px; }
        .qr-box .qr-meta p { margin: 0; color: #475569; font-size: 14px; line-height: 1.5; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 18px; }
        @media (max-width: 720px) { .input-row { grid-template-columns: 1fr; } .qr-box { display: grid; justify-items: center; text-align: center; } .actions { flex-direction: column; } }
    </style>
</head>
<body>
<div class="page">
    <div class="panel">
        <div class="brand"><span class="brand-dot"></span>የዲ/ን ሶፎንያስ ደመቀ ዌብሳይት ሰርቲፊኬት ማረጋገጫ</div>
        <h1>የዲ/ን ሶፎንያስ ደመቀ ዌብሳይት ሰርቲፊኬት ማረጋገጫ</h1>
        <p class="lead">Scan the certificate QR code or enter the certificate ID manually. The verification result is displayed automatically without needing any extra data entry.</p>

        <form method="get" action="verify_certificate.php">
            <div class="input-row">
                <input type="text" name="id" value="<?php echo htmlspecialchars($certificateIdInput, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter certificate ID or scan QR code" aria-label="Certificate ID">
                <button type="submit">Verify Certificate</button>
            </div>
        </form>

        <?php if ($verificationQrSrc !== ''): ?>
            <div class="qr-box" aria-label="Verification QR code">
                <img src="<?php echo htmlspecialchars($verificationQrSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="Certificate verification QR code">
                <div class="qr-meta">
                    <strong>Scan with your phone camera</strong>
                    <p>Use this QR code to open the certificate verification page on mobile immediately.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($certificate): ?>
            <div class="status-card <?php echo $statusClass; ?>">
                <div class="status-badge <?php echo $statusClass; ?>">
                    <span class="status-icon <?php echo $statusClass; ?>"><?php echo $statusClass === 'valid' ? '✓' : '✕'; ?></span>
                    <span><?php echo htmlspecialchars($verificationMessage, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>

                <div class="details-grid">
                    <div class="detail-card"><h3>Certificate ID</h3><p><?php echo htmlspecialchars($certificateData['certificate_id'], ENT_QUOTES, 'UTF-8'); ?></p></div>
                    <div class="detail-card"><h3>Student Name</h3><p><?php echo htmlspecialchars($certificateData['student_name'], ENT_QUOTES, 'UTF-8'); ?></p></div>
                    <div class="detail-card"><h3>Course / Exam</h3><p><?php echo htmlspecialchars($certificateData['course_name'], ENT_QUOTES, 'UTF-8'); ?></p></div>
                    <div class="detail-card"><h3>Score</h3><p><?php echo htmlspecialchars($certificateData['score'], ENT_QUOTES, 'UTF-8'); ?></p></div>
                    <div class="detail-card"><h3>Issue Date</h3><p><?php echo htmlspecialchars($certificateData['issued_at'], ENT_QUOTES, 'UTF-8'); ?></p></div>
                    <div class="detail-card"><h3>Instructor Name</h3><p><?php echo htmlspecialchars($certificateData['instructor_name'], ENT_QUOTES, 'UTF-8'); ?></p></div>
                    <div class="detail-card"><h3>Certificate Status</h3><p><?php echo htmlspecialchars($certificateData['status'], ENT_QUOTES, 'UTF-8'); ?></p></div>
                    <div class="detail-card"><h3>Verification Date</h3><p><?php echo htmlspecialchars($verificationTimestamp, ENT_QUOTES, 'UTF-8'); ?></p></div>
                </div>
            </div>
        <?php elseif ($certificateIdInput !== ''): ?>
            <div class="status-card invalid">
                <div class="status-badge invalid"><span class="status-icon invalid">✕</span><span>CERTIFICATE NOT VALID</span></div>
                <div class="details-grid">
                    <div class="detail-card"><h3>Certificate ID</h3><p><?php echo htmlspecialchars($certificateIdInput, ENT_QUOTES, 'UTF-8'); ?></p></div>
                    <div class="detail-card"><h3>Status</h3><p>Not Valid</p></div>
                    <div class="detail-card"><h3>Verification Date</h3><p><?php echo htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8'); ?></p></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="helper">
            <strong>QR code verification</strong>
            <p>Scan the QR code on your certificate with your phone camera to verify this certificate immediately. The QR code opens the verification page automatically and checks the certificate by ID.</p>
        </div>

        <div class="actions">
            <a href="verify_certificate.php" class="secondary-btn">Verify Another Certificate</a>
            <button type="button" class="secondary-btn" onclick="window.print();">Print Verification Result</button>
        </div>
    </div>
</div>
</body>
</html>
