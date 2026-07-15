<?php
session_start();
require_once __DIR__ . '/db.php';

function generate_certificate_pdf_file($certificate)
{
    $tmp_html = tempnam(sys_get_temp_dir(), 'cert_html_');
    $tmp_pdf  = tempnam(sys_get_temp_dir(), 'cert_pdf_');

    if ($tmp_html === false || $tmp_pdf === false) {
        return false;
    }

    $name = htmlspecialchars((string)($certificate['student_name'] ?? 'Student'), ENT_QUOTES, 'UTF-8');
    $exam = htmlspecialchars((string)($certificate['exam_type'] ?? 'Certificate'), ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars((string)($certificate['certificate_title'] ?? 'የማጠናከር ሰርቲፊኬት'), ENT_QUOTES, 'UTF-8');
    $seal_top = htmlspecialchars((string)($certificate['seal_text_top'] ?? 'ቤተ ገብርኤል'), ENT_QUOTES, 'UTF-8');
    $seal_bottom = htmlspecialchars((string)($certificate['seal_text_bottom'] ?? 'ዲ/ን ሶፎንያስ ደመቀ'), ENT_QUOTES, 'UTF-8');
    $signer = htmlspecialchars((string)($certificate['signer_name'] ?? 'Authorized Signatory'), ENT_QUOTES, 'UTF-8');
    $score = (int)($certificate['score'] ?? 0);
    $total = (int)($certificate['total_questions'] ?? 0);
    $issued = date('Y-m-d', strtotime((string)($certificate['issued_at'] ?? 'now')));
    $verify = 'VC-' . str_pad((string)($certificate['id'] ?? 0), 6, '0', STR_PAD_LEFT);
    $instructor_photo = htmlspecialchars((string)(publicMediaUrl($certificate['instructor_photo'] ?? 'sofi photo.jpg')), ENT_QUOTES, 'UTF-8');
    $student_photo = htmlspecialchars((string)(publicMediaUrl($certificate['student_photo'] ?? 'yesofi 1 photo.jpg')), ENT_QUOTES, 'UTF-8');
    $watermark_lines = htmlspecialchars("ዲ/ን ሶፎንያስ ደመቀ\nቤተ ገብርኤል ዌብሳይት", ENT_QUOTES, 'UTF-8');
    $watermark_lines = str_replace("\n", '<br>', $watermark_lines);
    $signature_markup = '';
    if (!empty($certificate['signature_data'])) {
        $signature_markup = '<div class="sig-img"><img src="' . htmlspecialchars((string)$certificate['signature_data'], ENT_QUOTES, 'UTF-8') . '" alt="Signature" /></div>';
    } else {
        $signature_markup = '<div class="sig-placeholder">Digital Signature</div>';
    }

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Church Certificate</title>
  <style>
    body { margin: 0; font-family: Georgia, "Times New Roman", serif; background: #fff; color: #3a2318; }
    .page { width: 920px; min-height: 1180px; margin: 0 auto; padding: 36px 46px; box-sizing: border-box; border: 8px solid #d2a54f; border-radius: 28px; background: radial-gradient(circle at top, #fffaf0 0%, #fffdf8 52%, #f2ead5 100%); position: relative; }
    .page::before { content: ""; position: absolute; inset: 10px; border: 1px solid rgba(180,139,50,0.65); border-radius: 20px; pointer-events: none; }
    .photo-header { position: relative; z-index: 1; display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 16px; margin-bottom: 14px; }
    .photo-column { display: flex; flex-direction: column; align-items: center; gap: 8px; }
    .photo-frame { width: 116px; height: 116px; padding: 4px; border-radius: 50%; background: linear-gradient(135deg, #f2d894, #7a1f1f); box-shadow: 0 12px 24px rgba(122, 31, 31, 0.22); }
    .photo-frame img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; border: 3px solid #f7e6ba; background: #fffdf7; }
    .photo-label { font-size: 11px; line-height: 1.4; text-align: center; color: #7a1f1f; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; }
    .cross-badge { width: 92px; height: 92px; border-radius: 50%; display: grid; place-items: center; font-size: 34px; color: #7a1f1f; background: radial-gradient(circle at center, #fff7dd 0%, #f0d38b 54%, #a02a28 100%); border: 3px solid #8f5b18; box-shadow: 0 10px 22px rgba(122, 31, 31, 0.2); }
    .title { text-align: center; font-size: 28px; color: #7a1f1f; text-transform: uppercase; margin: 10px 0 8px; font-weight: 800; }
    .sub { text-align: center; font-size: 16px; color: #7e5330; margin: 0 0 18px; }
    .name { text-align: center; font-size: 36px; margin: 20px 0 10px; color: #3d2418; font-weight: 800; }
    .body-copy { text-align: center; font-size: 18px; line-height: 1.7; margin: 6px 0; }
    .meta { text-align: center; font-size: 15px; color: #6d4b2d; margin-top: 10px; }
    .sign-row { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 42px; position: relative; z-index: 1; }
    .sign-box { width: 220px; text-align: center; font-size: 13px; color: #7a1f1f; border-top: 1px solid #b88b41; padding-top: 8px; font-weight: 700; text-transform: uppercase; }
    .sig-img, .sig-placeholder { height: 86px; width: 180px; display: grid; place-items: center; }
    .sig-img img { max-width: 160px; max-height: 70px; object-fit: contain; }
    .sig-placeholder { border: 1px dashed #c99e42; border-radius: 12px; color: #7a1f1f; font-size: 12px; font-weight: 700; }
    .watermark { position: absolute; inset: 0; display: grid; place-items: center; font-size: 58px; font-weight: 900; line-height: 1.3; letter-spacing: 3px; color: rgba(122,31,31,0.06); text-align: center; transform: rotate(-18deg); pointer-events: none; }
  </style>
</head>
<body>
  <div class="page">
    <div class="watermark">$watermark_lines</div>
    <div class="photo-header">
      <div class="photo-column">
        <div class="photo-frame"><img src="$instructor_photo" alt="Instructor photo" /></div>
        <div class="photo-label">ዲ/ን ሶፎንያስ ደመቀ</div>
      </div>
      <div class="cross-badge">✚</div>
      <div class="photo-column">
        <div class="photo-frame"><img src="$student_photo" alt="Student photo" /></div>
        <div class="photo-label">$name</div>
      </div>
    </div>
    <div class="title">$title</div>
    <div class="sub">This certificate is officially granted in recognition of the learner’s successful completion.</div>
    <div class="name">$name</div>
    <p class="body-copy">has successfully completed the <strong>$exam</strong> assessment with a score of <strong>$score / $total</strong>.</p>
    <div class="meta">Issued On: $issued &nbsp; | &nbsp; Verification: $verify</div>
    <div class="sign-row">
      <div class="sign-box">$signer</div>
      $signature_markup
      <div class="sign-box">Church Registrar</div>
    </div>
  </div>
</body>
</html>
HTML;

    file_put_contents($tmp_html, $html);

    $browser = 'C:\Program Files\Google\Chrome\Application\chrome.exe';
    if (!is_file($browser)) {
        $browser = 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe';
    }

    if (!is_file($browser)) {
        @unlink($tmp_html);
        @unlink($tmp_pdf);
        return false;
    }

    $command = '"' . str_replace('"', '\"', $browser) . '" --headless --disable-gpu --no-sandbox --print-to-pdf="' . str_replace('"', '\"', $tmp_pdf) . '" "' . str_replace('"', '\"', $tmp_html) . '"';
    exec($command, $output, $code);

    @unlink($tmp_html);

    if ($code !== 0 || !is_file($tmp_pdf) || filesize($tmp_pdf) === 0) {
        @unlink($tmp_pdf);
        return false;
    }

    return $tmp_pdf;
}

$is_admin = isset($_SESSION['admin_id']);
$is_student = isset($_SESSION['student_id']);

if (!$is_admin && !$is_student) {
    header('Location: student_login.php');
    exit;
}

$message = '';
$error = '';

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS certificates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        exam_type VARCHAR(50) NOT NULL,
        score INT NOT NULL DEFAULT 0,
        total_questions INT NOT NULL DEFAULT 0,
        issued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_certificate (student_id, exam_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {
    $error = 'Certificate table setup failed: ' . $e->getMessage();
}

if (isset($_GET['issue'])) {
    $submission_id = (int)$_GET['issue'];
    try {
        $stmt = $pdo->prepare('SELECT student_id, student_name, exam_type, score, total_questions FROM exam_submissions WHERE id = :id');
        $stmt->execute([':id' => $submission_id]);
        $row = $stmt->fetch();

        if (!$row) {
            $error = 'ይህ ፈተና ውጤት አልተገኘም።';
        } else {
            $stmt = $pdo->prepare('INSERT IGNORE INTO certificates (student_id, student_name, exam_type, score, total_questions) VALUES (:student_id, :student_name, :exam_type, :score, :total_questions)');
            $stmt->execute([
                ':student_id' => $row['student_id'],
                ':student_name' => $row['student_name'],
                ':exam_type' => $row['exam_type'],
                ':score' => (int)$row['score'],
                ':total_questions' => (int)$row['total_questions'],
            ]);
            $message = 'ሰርቲፊኬት ተለቋል።';
        }
    } catch (PDOException $e) {
        $error = 'ስህተት: ' . $e->getMessage();
    }
}

if (isset($_GET['delete'])) {
    $cert_id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare('DELETE FROM certificates WHERE id = :id');
        $stmt->execute([':id' => $cert_id]);
        $message = 'ሰርቲፊኬት ተሰርዟል።';
    } catch (PDOException $e) {
        $error = 'ስህተት: ' . $e->getMessage();
    }
}

if (!$is_admin && !isset($_GET['download'])) {
    header('Location: student_dashboard.php');
    exit;
}

try {
    $stmt = $pdo->query('SELECT es.*, s.email FROM exam_submissions es LEFT JOIN students s ON s.student_id = es.student_id ORDER BY es.submitted_at DESC');
    $submissions = $stmt->fetchAll();
} catch (PDOException $e) {
    $submissions = [];
    $error = 'DB ችግኝ አለ። ' . $e->getMessage();
}

try {
    $stmt = $pdo->query('SELECT COUNT(*) as total_certificates FROM certificates');
    $dashboard_certificate_count = (int)($stmt->fetch()['total_certificates'] ?? 0);
} catch (PDOException $e) {
    $dashboard_certificate_count = 0;
}

try {
    $stmt = $pdo->query('SELECT * FROM certificates ORDER BY issued_at DESC');
    $certificates = $stmt->fetchAll();
} catch (PDOException $e) {
    $certificates = [];
}

if (isset($_GET['download'])) {
    $cert_id = (int)$_GET['download'];
    try {
        if ($is_student) {
            $stmt = $pdo->prepare('SELECT * FROM certificates WHERE id = :id AND student_id = :student_id');
            $stmt->execute([':id' => $cert_id, ':student_id' => $_SESSION['student_id']]);
        } else {
            $stmt = $pdo->prepare('SELECT * FROM certificates WHERE id = :id');
            $stmt->execute([':id' => $cert_id]);
        }
        $certificate = $stmt->fetch();

        if ($certificate) {
            $pdf_path = generate_certificate_pdf_file($certificate);
            if ($pdf_path !== false) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="certificate-' . (int)$cert_id . '.pdf"');
                header('Content-Length: ' . filesize($pdf_path));
                readfile($pdf_path);
                unlink($pdf_path);
                exit;
            }
            $error = 'PDF ማውረድ አልተቻለም። የብራውዘር እገዳ እና ቪዛውን ይመልከቱ።';
        } else {
            $error = 'ይህ ሰርቲፊኬት አልተገኘም።';
        }
    } catch (PDOException $e) {
        $error = 'ስህተት: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>የሰርቲፊኬት አስተዳደር</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f5f7fa; color: #1f2937; }
        .topbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; }
        .topbar a { color: white; text-decoration: none; background: rgba(255,255,255,0.12); padding: 8px 12px; border-radius: 6px; }
        .wrap { max-width: 1200px; margin: 24px auto; padding: 0 18px; }
        .card { background: white; border-radius: 10px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); padding: 18px; margin-bottom: 18px; }
        .msg { padding: 10px 12px; border-radius: 6px; margin-bottom: 12px; }
        .msg.ok { background: #d4f1d8; color: #1d6a2b; }
        .msg.err { background: #ffe7e7; color: #a41616; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 10px; text-align: left; vertical-align: top; }
        th { background: #eef2ff; }
        .btn { display: inline-block; padding: 7px 10px; border-radius: 6px; text-decoration: none; font-size: 12px; }
        .btn.issue { background: #667eea; color: white; }
        .btn.del { background: #e74c3c; color: white; }
        .print-btn { display: inline-block; padding: 8px 12px; border-radius: 6px; background: #16a34a; color: white; text-decoration: none; border: 0; cursor: pointer; }
        .print-btn:hover { background: #15803d; }
        .header-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
        @media print {
            .topbar, .no-print, .btn, .msg { display: none !important; }
            body { background: #fff; }
            .card { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
<div class="topbar">
    <h2 style="margin: 0;">📜 የሰርቲፊኬት አስተዳደር</h2>
    <a href="admin_dashboard.php">ወደ ዳሽቦርድ</a>
</div>
<div class="wrap">
    <div class="card">
        <div class="header-row">
            <div>
                <h3>እንዴት ይሰራል</h3>
                <p>ከፈተና ውጤቶች ውስጥ የሚገኙትን ተማሪዎች በአንድ ጠቅላላ ቁጥር ሰርቲፊኬት ለመስጠት እዚህ ይቆጣጠሩ። እንዲሁም የተሰጡትን ሰርቲፊኬቶች እዚህ ማስተካከል ወይም መሰረዝ ይችላሉ።</p>
                <p><strong>የአሁኑ ሰርቲፊኬት ቆጣሪ:</strong> <?php echo $dashboard_certificate_count; ?></p>
            </div>
            <div class="no-print">
                <button type="button" class="print-btn" onclick="window.print()">🖨️ ሰርቲፊኬቶች አትም</button>
            </div>
        </div>
        <?php if ($message): ?><div class="msg ok"><?php echo safe($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="msg err"><?php echo safe($error); ?></div><?php endif; ?>
    </div>

    <div class="card">
        <h3>ለማስተላለፍ ዝግጁ የሆኑ ውጤቶች</h3>
        <table>
            <thead>
                <tr>
                    <th>ተማሪ</th>
                    <th>ኢሜይል</th>
                    <th>ፈተና</th>
                    <th>ውጤት</th>
                    <th>ድርጊት</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($submissions)): ?>
                    <tr><td colspan="5">ምንም የፈተና ውጤት የለም።</td></tr>
                <?php else: foreach ($submissions as $row): ?>
                    <tr>
                        <td><?php echo safe($row['student_name']); ?></td>
                        <td><?php echo safe($row['email'] ?? '-'); ?></td>
                        <td><?php echo safe($row['exam_type']); ?></td>
                        <td><?php echo (int)$row['score']; ?> / <?php echo (int)$row['total_questions']; ?></td>
                        <td><a class="btn issue" href="admin_certificate.php?issue=<?php echo (int)$row['id']; ?>">ሰርቲፊኬት ለመስጠት</a></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>የተሰጡ ሰርቲፊኬቶች</h3>
        <table>
            <thead>
                <tr>
                    <th>ተማሪ</th>
                    <th>ፈተና</th>
                    <th>ውጤት</th>
                    <th>ቀን</th>
                    <th>ድርጊት</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($certificates)): ?>
                    <tr><td colspan="5">ምንም ሰርቲፊኬት አልተሰጠም።</td></tr>
                <?php else: foreach ($certificates as $row): ?>
                    <tr>
                        <td><?php echo safe($row['student_name']); ?></td>
                        <td><?php echo safe($row['exam_type']); ?></td>
                        <td><?php echo (int)$row['score']; ?> / <?php echo (int)$row['total_questions']; ?></td>
                        <td><?php echo safe($row['issued_at']); ?></td>
                        <td>
                            <a class="btn issue" href="admin_edit_certificate.php?id=<?php echo (int)$row['id']; ?>">አስተካከል</a>
                            <a class="btn del" href="admin_certificate.php?delete=<?php echo (int)$row['id']; ?>" onclick="return confirm('ይህን ሰርቲፊኬት ሰርዝ?');">ሰርዝ</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
