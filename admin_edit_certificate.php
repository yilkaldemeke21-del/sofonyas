<?php
session_start();
require_once __DIR__ . '/db.php';

if (!function_exists('ensureCertificatePhotoColumns')) {
    function ensureCertificatePhotoColumns(PDO $pdo): void
    {
        $existingColumns = [];
        $columnStmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'certificates'");

        foreach ($columnStmt->fetchAll(PDO::FETCH_COLUMN) as $columnName) {
            $existingColumns[$columnName] = true;
        }

        foreach ([
            'instructor_photo' => 'ALTER TABLE certificates ADD COLUMN instructor_photo VARCHAR(255) NULL AFTER seal_image',
            'student_photo' => 'ALTER TABLE certificates ADD COLUMN student_photo VARCHAR(255) NULL AFTER instructor_photo',
            'signer_position' => 'ALTER TABLE certificates ADD COLUMN signer_position VARCHAR(255) NULL AFTER signer_name',
        ] as $columnName => $sql) {
            if (!isset($existingColumns[$columnName])) {
                try {
                    $pdo->exec($sql);
                } catch (PDOException $e) {
                    error_log('Certificate schema migration warning for ' . $columnName . ': ' . $e->getMessage());
                }
            }
        }
    }
}

ensureCertificatePhotoColumns($pdo);

if (!function_exists('safeCertificatePhotoPath')) {
    function safeCertificatePhotoPath(string $path): string
    {
        $normalized = trim(str_replace('\\', '/', $path));
        if ($normalized === '') {
            return '';
        }

        if (preg_match('~^(https?:)?//~i', $normalized) === 1) {
            return $normalized;
        }

        return ltrim($normalized, '/');
    }
}

if (!function_exists('saveCertificatePhotoFile')) {
    function saveCertificatePhotoFile(array $file, string $prefix): string
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name']) || !is_readable($file['tmp_name'])) {
            return '';
        }

        $uploadDir = __DIR__ . '/uploads/certificates';
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            return '';
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowed, true)) {
            return '';
        }

        $extension = 'jpg';
        if ($mime === 'image/png') {
            $extension = 'png';
        } elseif ($mime === 'image/webp') {
            $extension = 'webp';
        } elseif ($mime === 'image/gif') {
            $extension = 'gif';
        }

        $safeName = $prefix . '_' . uniqid('', true) . '.' . $extension;
        $targetPath = $uploadDir . '/' . $safeName;

        if (function_exists('imagecreatetruecolor') && function_exists('imagecopyresampled')) {
            $size = getimagesize($file['tmp_name']);
            if ($size !== false) {
                $source = null;
                if ($mime === 'image/png') {
                    $source = imagecreatefrompng($file['tmp_name']);
                } elseif ($mime === 'image/webp') {
                    $source = imagecreatefromwebp($file['tmp_name']);
                } elseif ($mime === 'image/gif') {
                    $source = imagecreatefromgif($file['tmp_name']);
                } else {
                    $source = imagecreatefromjpeg($file['tmp_name']);
                }

                if ($source !== false) {
                    $width = imagesx($source);
                    $height = imagesy($source);
                    $squareSize = 600;
                    $cropSize = min($width, $height);
                    $cropX = (int)floor(($width - $cropSize) / 2);
                    $cropY = (int)floor(($height - $cropSize) / 2);
                    $cropped = imagecreatetruecolor($squareSize, $squareSize);
                    imagealphablending($cropped, false);
                    imagesavealpha($cropped, true);
                    $transparent = imagecolorallocatealpha($cropped, 255, 255, 255, 127);
                    imagefilledrectangle($cropped, 0, 0, $squareSize, $squareSize, $transparent);
                    imagecopyresampled($cropped, $source, 0, 0, $cropX, $cropY, $squareSize, $squareSize, $cropSize, $cropSize);
                    if ($mime === 'image/webp') {
                        imagewebp($cropped, $targetPath, 90);
                    } elseif ($mime === 'image/png') {
                        imagepng($cropped, $targetPath, 9);
                    } elseif ($mime === 'image/gif') {
                        imagegif($cropped, $targetPath);
                    } else {
                        imagejpeg($cropped, $targetPath, 92);
                    }
                    imagedestroy($cropped);
                    imagedestroy($source);
                    return 'uploads/certificates/' . $safeName;
                }
            }
        }

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return '';
        }

        return 'uploads/certificates/' . $safeName;
    }
}

if (!function_exists('deleteCertificatePhotoFile')) {
    function deleteCertificatePhotoFile(string $photoPath): void
    {
        $normalized = safeCertificatePhotoPath($photoPath);
        if ($normalized === '') {
            return;
        }

        $absolutePath = __DIR__ . '/' . $normalized;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$cert_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

if ($cert_id) {
    $stmt = $pdo->prepare('SELECT * FROM certificates WHERE id = :id');
    $stmt->execute([':id' => $cert_id]);
    $certificate = $stmt->fetch();
    if (!$certificate) {
        header('Location: admin_certificate.php');
        exit;
    }
} else {
    header('Location: admin_certificate.php');
    exit;
}

$student_name = $certificate['student_name'] ?? '';
$exam_type = $certificate['exam_type'] ?? '';
$score = (int)($certificate['score'] ?? 0);
$total_questions = (int)($certificate['total_questions'] ?? 0);
$issued_at = $certificate['issued_at'] ?? date('Y-m-d H:i:s');
$certificate_title = trim((string)($certificate['certificate_title'] ?? 'የማጠናከር ሰርቲፊኬት'));
$seal_text_top = trim((string)($certificate['seal_text_top'] ?? 'ቤተ ገብርኤል'));
$seal_text_bottom = trim((string)($certificate['seal_text_bottom'] ?? 'ዲ/ን ሶፎንያስ ደመቀ'));
$signer_name = trim((string)($certificate['signer_name'] ?? 'ዲ/ን ሶፎንያስ ደመቀ'));
$signer_position = trim((string)($certificate['signer_position'] ?? 'የሰርቲፊኬት ሰጪ ተቋም'));
$signature_data = trim((string)($certificate['signature_data'] ?? ''));
$instructor_photo = safeCertificatePhotoPath((string)($certificate['instructor_photo'] ?? ''));
$student_photo = safeCertificatePhotoPath((string)($certificate['student_photo'] ?? ''));
$seal_image_url = '';
if (!empty($certificate['seal_image'])) {
    $seal_image_url = htmlspecialchars(publicMediaUrl($certificate['seal_image']), ENT_QUOTES, 'UTF-8');
}
$photoVersion = time();
$instructor_photo_url = $instructor_photo !== '' ? htmlspecialchars(publicMediaUrl($instructor_photo), ENT_QUOTES, 'UTF-8') : htmlspecialchars(publicMediaUrl('sofi fikr.jpg'), ENT_QUOTES, 'UTF-8');
$student_photo_url = $student_photo !== '' ? htmlspecialchars(publicMediaUrl($student_photo), ENT_QUOTES, 'UTF-8') : htmlspecialchars(publicMediaUrl('yesofi 1 photo.jpg'), ENT_QUOTES, 'UTF-8');
$instructor_photo_url .= '?v=' . $photoVersion;
$student_photo_url .= '?v=' . $photoVersion;
$show_watermark = !empty($certificate['show_watermark']) || !empty($certificate['watermark_mode']);

foreach ([
    'ALTER TABLE certificates ADD COLUMN IF NOT EXISTS certificate_title VARCHAR(255) NOT NULL DEFAULT "የማጠናከር ሰርቲፊኬት"',
    'ALTER TABLE certificates ADD COLUMN IF NOT EXISTS seal_text_top VARCHAR(255) NOT NULL DEFAULT "ቤተ ገብርኤል"',
    'ALTER TABLE certificates ADD COLUMN IF NOT EXISTS seal_text_bottom VARCHAR(255) NOT NULL DEFAULT "ዲ/ን ሶፎንያስ ደመቀ"',
    'ALTER TABLE certificates ADD COLUMN IF NOT EXISTS signer_name VARCHAR(255) NOT NULL DEFAULT "ዲ/ን ሶፎንያስ ደመቀ"',
    'ALTER TABLE certificates ADD COLUMN IF NOT EXISTS signer_position VARCHAR(255) NOT NULL DEFAULT "የሰርቲፊኬት ሰጪ ተቋም"',
    'ALTER TABLE certificates ADD COLUMN IF NOT EXISTS signature_data LONGTEXT NULL',
    'ALTER TABLE certificates ADD COLUMN IF NOT EXISTS seal_image VARCHAR(255) NULL',
    'ALTER TABLE certificates ADD COLUMN IF NOT EXISTS instructor_photo VARCHAR(255) NULL',
    'ALTER TABLE certificates ADD COLUMN IF NOT EXISTS student_photo VARCHAR(255) NULL',
    'ALTER TABLE certificates ADD COLUMN IF NOT EXISTS show_watermark TINYINT(1) NOT NULL DEFAULT 1',
] as $alterSql) {
    try {
        $pdo->exec($alterSql);
    } catch (PDOException $e) {
        // Ignore schema mismatch warnings and keep the page usable.
    }
}

$seal_top = htmlspecialchars($seal_text_top, ENT_QUOTES, 'UTF-8');
$seal_bottom = htmlspecialchars($seal_text_bottom, ENT_QUOTES, 'UTF-8');
$instructor_photo_url = htmlspecialchars(publicMediaUrl('sofi fikr.jpg'), ENT_QUOTES, 'UTF-8') . '?v=' . time();
$student_photo_url = htmlspecialchars(publicMediaUrl('emaye photo.jpg'), ENT_QUOTES, 'UTF-8') . '?v=' . time();
$watermark_text = htmlspecialchars("ዲ/ን ሶፎንያስ ደመቀ\nቤተ ገብርኤል ዌብሳይት", ENT_QUOTES, 'UTF-8');
$watermark_text = str_replace("\n", '<br>', $watermark_text);
$seal_html = '
<div class="seal">
    <svg class="seal-svg" viewBox="0 0 240 240" aria-hidden="true">
        <defs>
            <filter id="sealGlow" x="-30%" y="-30%" width="160%" height="160%">
                <feDropShadow dx="0" dy="0" stdDeviation="4" flood-color="#8b1f1f" flood-opacity="0.35" />
            </filter>
            <path id="sealTopPath" d="M 30 120 A 90 90 0 0 0 210 120" />
            <path id="sealBottomPath" d="M 30 120 A 90 90 0 0 1 210 120" />
        </defs>
        <g filter="url(#sealGlow)">
            <circle cx="120" cy="120" r="93" fill="#fff7e0" stroke="#c5962e" stroke-width="10" />
            <circle cx="120" cy="120" r="77" fill="none" stroke="#7a1f1f" stroke-width="5" />
            <circle cx="120" cy="120" r="66" fill="none" stroke="#c5962e" stroke-width="1.8" stroke-dasharray="3 5" opacity="0.75" />
        </g>
        <text fill="#7a1f1f" font-size="17" font-weight="700" font-family="Georgia, serif" letter-spacing="0.6">
            <textPath href="#sealTopPath" startOffset="50%" text-anchor="middle">' . $seal_top . '</textPath>
        </text>
        <text fill="#7a1f1f" font-size="17" font-weight="700" font-family="Georgia, serif" letter-spacing="0.6">
            <textPath href="#sealBottomPath" startOffset="50%" text-anchor="middle">' . $seal_bottom . '</textPath>
        </text>
        <g transform="translate(120 120)">
            <circle cx="0" cy="0" r="42" fill="rgba(122,31,31,0.08)" stroke="#8b1f1f" stroke-width="1.7" opacity="0.65" />
            <path d="M -10 -60 L 10 -60 L 10 -16 L 32 -16 L 32 16 L 10 16 L 10 60 L -10 60 L -10 16 L -32 16 L -32 -16 L -10 -16 Z" fill="#c5962e" stroke="#8a2e2e" stroke-width="2" />
            <path d="M -46 -8 L 46 -8 M -20 -24 L 20 -24 M -12 -50 L 12 -50" stroke="#8a2e2e" stroke-width="2.2" stroke-linecap="round" opacity="0.55" />
            <path d="M -24 -6 Q 0 -26 24 -6 L 24 0 Q 0 14 -24 0 Z" fill="#8b1f1f" opacity="0.18" />
        </g>
    </svg>
</div>';
if ($seal_image_url !== '') {
    $seal_html = '
<div class="seal">
    <svg class="seal-svg" viewBox="0 0 240 240" aria-hidden="true">
        <defs>
            <filter id="sealGlow" x="-30%" y="-30%" width="160%" height="160%">
                <feDropShadow dx="0" dy="0" stdDeviation="4" flood-color="#8b1f1f" flood-opacity="0.35" />
            </filter>
            <path id="sealTopPath" d="M 30 120 A 90 90 0 0 0 210 120" />
            <path id="sealBottomPath" d="M 30 120 A 90 90 0 0 1 210 120" />
        </defs>
        <g filter="url(#sealGlow)">
            <circle cx="120" cy="120" r="93" fill="#fff7e0" stroke="#c5962e" stroke-width="10" />
            <circle cx="120" cy="120" r="77" fill="none" stroke="#7a1f1f" stroke-width="5" />
            <circle cx="120" cy="120" r="66" fill="none" stroke="#c5962e" stroke-width="1.8" stroke-dasharray="3 5" opacity="0.75" />
        </g>
        <text fill="#7a1f1f" font-size="17" font-weight="700" font-family="Georgia, serif" letter-spacing="0.6">
            <textPath href="#sealTopPath" startOffset="50%" text-anchor="middle">' . $seal_top . '</textPath>
        </text>
        <text fill="#7a1f1f" font-size="17" font-weight="700" font-family="Georgia, serif" letter-spacing="0.6">
            <textPath href="#sealBottomPath" startOffset="50%" text-anchor="middle">' . $seal_bottom . '</textPath>
        </text>
        <image href="' . $seal_image_url . '" x="64" y="64" width="112" height="112" preserveAspectRatio="xMidYMid meet" opacity="0.30" />
        <g transform="translate(120 120)">
            <circle cx="0" cy="0" r="42" fill="rgba(122,31,31,0.08)" stroke="#8b1f1f" stroke-width="1.7" opacity="0.65" />
            <path d="M -10 -60 L 10 -60 L 10 -16 L 32 -16 L 32 16 L 10 16 L 10 60 L -10 60 L -10 16 L -32 16 L -32 -16 L -10 -16 Z" fill="#c5962e" stroke="#8a2e2e" stroke-width="2" />
            <path d="M -46 -8 L 46 -8 M -20 -24 L 20 -24 M -12 -50 L 12 -50" stroke="#8a2e2e" stroke-width="2.2" stroke-linecap="round" opacity="0.55" />
            <path d="M -24 -6 Q 0 -26 24 -6 L 24 0 Q 0 14 -24 0 Z" fill="#8b1f1f" opacity="0.18" />
        </g>
    </svg>
</div>';
}
$watermark_html = $show_watermark ? '<div class="watermark">' . $watermark_text . '</div>' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_name = trim($_POST['student_name'] ?? '');
    $exam_type = trim($_POST['exam_type'] ?? '');
    $certificate_title = trim($_POST['certificate_title'] ?? '');
    $seal_text_top = trim($_POST['seal_text_top'] ?? '');
    $seal_text_bottom = trim($_POST['seal_text_bottom'] ?? '');
    $signer_name = trim($_POST['signer_name'] ?? '');
    $score = max(0, (int)($_POST['score'] ?? 0));
    $total_questions = max(1, (int)($_POST['total_questions'] ?? 1));
    $issued_at = trim($_POST['issued_at'] ?? '');
    $issued_at = $issued_at !== '' ? date('Y-m-d H:i:s', strtotime($issued_at)) : date('Y-m-d H:i:s');
    $signature_data = trim($_POST['signature_data'] ?? '');
    $delete_instructor_photo = !empty($_POST['delete_instructor_photo']);
    $delete_student_photo = !empty($_POST['delete_student_photo']);

    $instructor_photo = safeCertificatePhotoPath((string)($_POST['instructor_photo'] ?? $instructor_photo));
    $student_photo = safeCertificatePhotoPath((string)($_POST['student_photo'] ?? $student_photo));

    if (isset($_FILES['instructor_photo_file']) && is_array($_FILES['instructor_photo_file']) && $_FILES['instructor_photo_file']['error'] === UPLOAD_ERR_OK) {
        $uploadedInstructorPhoto = saveCertificatePhotoFile($_FILES['instructor_photo_file'], 'instructor');
        if ($uploadedInstructorPhoto !== '') {
            deleteCertificatePhotoFile($instructor_photo);
            $instructor_photo = $uploadedInstructorPhoto;
        }
    }

    if (isset($_FILES['student_photo_file']) && is_array($_FILES['student_photo_file']) && $_FILES['student_photo_file']['error'] === UPLOAD_ERR_OK) {
        $uploadedStudentPhoto = saveCertificatePhotoFile($_FILES['student_photo_file'], 'student');
        if ($uploadedStudentPhoto !== '') {
            deleteCertificatePhotoFile($student_photo);
            $student_photo = $uploadedStudentPhoto;
        }
    }

    if ($delete_instructor_photo) {
        deleteCertificatePhotoFile($instructor_photo);
        $instructor_photo = '';
    }

    if ($delete_student_photo) {
        deleteCertificatePhotoFile($student_photo);
        $student_photo = '';
    }

    if ($student_name === '' || $exam_type === '') {
        $error = 'ስም እና ፈተና አይነት ያስገቡ።';
    } else {
        try {
            $stmt = $pdo->prepare('UPDATE certificates SET student_name = :student_name, exam_type = :exam_type, certificate_title = :certificate_title, seal_text_top = :seal_text_top, seal_text_bottom = :seal_text_bottom, signer_name = :signer_name, signer_position = :signer_position, signature_data = :signature_data, score = :score, total_questions = :total_questions, issued_at = :issued_at, instructor_photo = :instructor_photo, student_photo = :student_photo WHERE id = :id');
            $stmt->execute([
                ':student_name' => $student_name,
                ':exam_type' => $exam_type,
                ':certificate_title' => $certificate_title !== '' ? $certificate_title : 'የቤተ ገብርኤል የምስክር ወረቀት',
                ':seal_text_top' => $seal_text_top !== '' ? $seal_text_top : 'ቤተ ገብርኤል',
                ':seal_text_bottom' => $seal_text_bottom !== '' ? $seal_text_bottom : 'ዲ/ን ሶፎንያስ ደመቀ',
                ':signer_name' => $signer_name !== '' ? $signer_name : 'ዲ/ን ሶፎንያስ ደመቀ',
                ':signer_position' => $signer_position !== '' ? $signer_position : 'የሰርቲፊኬት ሰጪ ተቋም',
                ':signature_data' => $signature_data,
                ':score' => $score,
                ':total_questions' => $total_questions,
                ':issued_at' => $issued_at,
                ':instructor_photo' => $instructor_photo,
                ':student_photo' => $student_photo,
                ':id' => $cert_id,
            ]);
            $success = 'ሰርቲፊኬት በትክክል ተስተካክሏል።';
            $certificate['student_name'] = $student_name;
            $certificate['exam_type'] = $exam_type;
            $certificate['certificate_title'] = $certificate_title !== '' ? $certificate_title : 'የቤተ ገብርኤል የምስክር ወረቀት';
            $certificate['seal_text_top'] = $seal_text_top !== '' ? $seal_text_top : 'ቤተ ገብርኤል';
            $certificate['seal_text_bottom'] = $seal_text_bottom !== '' ? $seal_text_bottom : 'ዲ/ን ሶፎንያስ ደመቀ';
            $certificate['signer_name'] = $signer_name !== '' ? $signer_name : 'ዲ/ን ሶፎንያስ ደመቀ';
            $certificate['signer_position'] = $signer_position !== '' ? $signer_position : 'የሰርቲፊኬት ሰጪ ተቋም';
            $certificate['signature_data'] = $signature_data;
            $certificate['score'] = $score;
            $certificate['total_questions'] = $total_questions;
            $certificate['issued_at'] = $issued_at;
            $certificate['instructor_photo'] = $instructor_photo;
            $certificate['student_photo'] = $student_photo;
        } catch (PDOException $e) {
            $error = 'ስህተት: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>ሰርቲፊኬት ማስተካከል</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Georgia, "Times New Roman", serif; background: #f7f3ea; color: #2f1b16; }
        .topbar { background: linear-gradient(135deg, #7a1f1f 0%, #a02a28 48%, #b58d32 100%); color: white; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; }
        .topbar a { color: white; text-decoration: none; background: rgba(255,255,255,0.12); padding: 8px 12px; border-radius: 6px; }
        .wrap { max-width: 900px; margin: 24px auto; padding: 0 18px; }
        .card { background: #fffdf8; border-radius: 10px; box-shadow: 0 4px 16px rgba(74, 34, 8, 0.12); padding: 18px; }
        .msg { padding: 10px 12px; border-radius: 6px; margin-bottom: 12px; }
        .msg.ok { background: #d8ebdb; color: #1f5d29; }
        .msg.err { background: #f8dede; color: #922727; }
        label { display: block; margin-bottom: 6px; font-weight: bold; }
        input, button { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #d2b48c; font-size: 14px; }
        button { background: #8a2e2e; color: white; border: none; cursor: pointer; font-weight: bold; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .field { margin-bottom: 12px; }
        .preview { border: 1px solid #e8d8b1; border-radius: 18px; padding: 18px; background: linear-gradient(135deg, #fffdf7 0%, #f7f0df 100%); margin-bottom: 16px; box-shadow: 0 16px 40px rgba(122,31,31,0.12); border: 2px solid #d8ba76; }
        .certificate-container { position: relative; }
        .preview-frame { position: relative; overflow: hidden; border: 8px solid #d2a54f; border-radius: 28px; padding: 18px 20px; background: radial-gradient(circle at top, #fffaf0 0%, #fffdf8 52%, #f2ead5 100%); }
        .preview-frame::before { content: ""; position: absolute; inset: 10px; border: 1px solid rgba(180, 139, 50, 0.65); border-radius: 20px; pointer-events: none; }
        .photo-header { position: relative; z-index: 1; display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 14px; margin: 0 0 16px; }
        .photo-column { display: flex; flex-direction: column; align-items: center; gap: 8px; }
        .photo-frame { width: 116px; height: 116px; padding: 4px; border-radius: 50%; background: linear-gradient(135deg, #f2d894, #7a1f1f); box-shadow: 0 12px 24px rgba(122, 31, 31, 0.22); }
        .photo-frame img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; border: 3px solid #f7e6ba; background: #fffdf7; }
        .photo-label { font-size: 11px; line-height: 1.4; text-align: center; color: #7a1f1f; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; }
        .certificate-verse { position: relative; z-index: 1; width: min(100%, 520px); margin: 0 auto 14px; text-align: center; padding: 2px 0 6px; }
        .certificate-verse .verse-text {
            display: block;
            font-size: 18px;
            font-weight: 900;
            line-height: 1.45;
            letter-spacing: 0.06em;
            text-transform: none;
            color: #10285e;
            background: linear-gradient(90deg, #10285e 0%, #c59a2f 50%, #10285e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 1px 1px rgba(243, 7, 86, 0.5), 0 0 10px rgba(16, 40, 94, 0.18), 0 0 12px rgba(197, 154, 47, 0.18);
        }
        .certificate-verse .verse-reference {
            margin-top: 4px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.12em;
            color: #8b6323;
            text-shadow: 0 1px 1px rgba(255,255,255,0.48);
        }
        .certificate-verse .verse-divider {
            margin-top: 10px;
            height: 2px;
            background: linear-gradient(90deg, transparent, #c5962e, transparent);
            box-shadow: 0 0 10px rgba(194, 150, 45, 0.5);
        }
        .cross-badge { width: 92px; height: 92px; border-radius: 50%; display: grid; place-items: center; font-size: 34px; color: #7a1f1f; background: radial-gradient(circle at center, #fff7dd 0%, #f0d38b 54%, #a02a28 100%); border: 3px solid #8f5b18; box-shadow: 0 10px 22px rgba(122, 31, 31, 0.2); }
        .preview h3 { margin: 10px 0 8px; font-size: 24px; letter-spacing: 0.04em; color: #7a1f1f; text-transform: uppercase; }
        .preview-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-top: 16px; }
        .preview-value { margin: 6px 0 0; font-size: 16px; color: #3f2319; font-weight: 700; }
        .mini { color: #7e5330; font-size: 13px; line-height: 1.6; }
        .mini strong { color: #7a1f1f; }
        .church-title { margin: 10px 0 8px; font-size: 22px; font-weight: 800; letter-spacing: 0.04em; text-align: center; color: #7a1f1f; text-transform: uppercase; }
        .church-title span { color: #a02a28; }
        .church-title-main { margin: 18px 0 4px; font-size: 42px; line-height: 1.1; font-weight: 900; letter-spacing: 0.06em; text-align: center; color: #7a1f1f; text-transform: uppercase; background: linear-gradient(135deg, #f6df9f 0%, #c49c32 42%, #7a1f1f 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 0 10px rgba(194, 150, 45, 0.24); }
        .authority-header { position: relative; z-index: 1; margin: 12px 0 12px; text-align: center; }
        .authority-label { font-size: 12px; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; color: #8b6323; margin-bottom: 6px; }
        .authority-name { font-size: 28px; line-height: 1.15; font-weight: 900; color: #7a1f1f; text-transform: uppercase; letter-spacing: 0.05em; text-shadow: 0 1px 0 #f6ddb0; }
        .authority-position { margin-top: 4px; font-size: 16px; font-weight: 700; color: #8d5f22; text-transform: uppercase; letter-spacing: 0.08em; }
        .meta-row { position: relative; z-index: 1; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 14px; }
        .meta-card { border: 1px solid #dcbf83; border-radius: 12px; padding: 10px 12px; background: rgba(255,250,240,0.84); }
        .meta-label { display: block; font-size: 11px; color: #885e2f; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 700; }
        .meta-value { display: block; margin-top: 4px; font-size: 13px; font-weight: 800; color: #4b2a1d; word-break: break-word; }
        .signature-row { position: relative; z-index: 1; display: grid; grid-template-columns: 1fr 180px 1fr; align-items: center; gap: 16px; margin-top: 18px; }
        .signature-column { display: flex; flex-direction: column; align-items: center; gap: 8px; }
        .signature-authority { font-size: 18px; font-weight: 900; color: #7a1f1f; text-align: center; text-transform: uppercase; letter-spacing: 0.04em; }
        .signature-line { border-top: 1px solid #b88b41; padding-top: 8px; text-align: center; font-size: 12px; color: #7a1f1f; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; }
        .signature-image { min-height: 90px; display: flex; align-items: center; justify-content: center; padding: 8px; border-radius: 12px; background: rgba(255, 252, 241, 0.92); box-shadow: 0 0 16px rgba(122, 31, 31, 0.16); }
        .signature-image img { width: 230px; max-width: 230px; height: 110px; object-fit: contain; filter: drop-shadow(0 2px 0 rgba(19,19,19,0.48)) drop-shadow(0 8px 12px rgba(0,0,0,0.22)); }
        .sign-placeholder { border: 1px dashed #c99e42; border-radius: 12px; padding: 10px; color: #7a1f1f; font-weight: 700; font-size: 12px; background: rgba(247,236,207,0.8); }
        .seal { display: flex; justify-content: center; align-items: center; }
        .seal-svg { width: 170px; height: 170px; overflow: visible; filter: drop-shadow(0 8px 14px rgba(122,31,31,0.18)); }
        .watermark { position: absolute; inset: 0; display: grid; place-items: center; font-size: 60px; font-weight: 900; line-height: 1.3; letter-spacing: 3px; color: rgba(122,31,31,0.06); text-align: center; transform: rotate(-18deg); pointer-events: none; z-index: 0; }
        .photo-manager { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin: 16px 0; }
        .photo-card { padding: 12px; border: 1px solid #ddc999; border-radius: 14px; background: rgba(255,248,230,0.88); }
        .photo-card h4 { margin: 0 0 10px; color: #7a1f1f; font-size: 14px; text-transform: uppercase; letter-spacing: 0.08em; }
        .photo-input-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .photo-input-row input[type="file"] { padding: 0; border: none; background: transparent; width: auto; }
        .photo-preview { width: 108px; height: 108px; border-radius: 50%; overflow: hidden; border: 3px solid #d4b066; background: #fefaf0; box-shadow: 0 12px 24px rgba(122,31,31,0.16); margin: 10px 0; }
        .photo-preview img { width: 100%; height: 100%; object-fit: cover; }
        .photo-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .photo-actions button, .photo-actions .ghost-btn { flex: 1 1 auto; }
        .ghost-btn { background: #f3efe6; color: #7a1f1f; border: 1px solid #d4b066; }
        .signature-pad-wrap { margin-top: 16px; padding: 12px; border: 1px solid #ddc999; border-radius: 14px; background: rgba(255,248,230,0.88); }
        .signature-pad-wrap canvas { width: 100%; height: 180px; border: 1px dashed #b7862d; border-radius: 12px; background: white; touch-action: none; }
        .signature-actions { display: flex; gap: 10px; margin-top: 10px; }
        .signature-actions button { flex: 1; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; }
        .print-btn { display: inline-block; padding: 10px 14px; border-radius: 6px; background: #7a1f1f; color: white; text-decoration: none; border: 0; cursor: pointer; }
        .print-btn:hover { background: #5e1717; }
        @page {
            size: A4 landscape;
            margin: 0;
        }
        @media print {
            html, body {
                width: 297mm;
                height: 210mm;
                margin: 0;
                padding: 0;
                overflow: hidden;
                background: #fff;
            }
            .topbar, .no-print, .msg, .actions { display: none !important; }
            .wrap {
                width: 297mm;
                height: 210mm;
                max-width: none;
                margin: 0;
                padding: 0;
            }
            .card {
                width: 297mm;
                height: 210mm;
                box-shadow: none;
                border: none;
                background: transparent;
                padding: 0;
                margin: 0;
                border-radius: 0;
            }
            .preview {
                border: none;
                box-shadow: none;
                background: transparent;
                margin: 0;
                padding: 0;
                border-radius: 0;
            }
            .certificate-container {
                width: 297mm;
                height: 210mm;
                margin: 0;
                padding: 0;
                box-shadow: none;
                background:
                    linear-gradient(#fffdf8, #fffdf8) padding-box,
                    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1000 700'%3E%3Crect x='8' y='8' width='984' height='684' fill='none' stroke='%23c5962e' stroke-width='4'/%3E%3Crect x='24' y='24' width='952' height='652' fill='none' stroke='%23f0dc8f' stroke-width='1.5'/%3E%3Ctext x='20' y='40' font-family='Georgia, serif' font-size='24' fill='%23c5962e'%3E✠%3C/text%3E%3Ctext x='960' y='40' font-family='Georgia, serif' font-size='24' fill='%23c5962e' text-anchor='end'%3E✠%3C/text%3E%3Ctext x='20' y='680' font-family='Georgia, serif' font-size='24' fill='%23c5962e'%3E✠%3C/text%3E%3Ctext x='960' y='680' font-family='Georgia, serif' font-size='24' fill='%23c5962e' text-anchor='end'%3E✠%3C/text%3E%3C/svg%3E") center/100% 100% no-repeat;
            }
            .certificate-container::before {
                content: "";
                position: absolute;
                inset: 7mm;
                border: 1px solid rgba(197, 150, 47, 0.75);
                box-shadow: 0 0 12px rgba(197, 150, 47, 0.18);
                pointer-events: none;
            }
            .preview-frame {
                width: 100%;
                height: 100%;
                border: none !important;
                border-radius: 0 !important;
                padding: 10mm 12mm !important;
                background: transparent !important;
                box-shadow: none !important;
                overflow: hidden;
            }
            .preview-frame::before {
                inset: 6mm;
                border: 1px solid rgba(197, 150, 47, 0.55);
                border-radius: 0;
            }
            .certificate-verse {
                width: 100%;
                max-width: 520px;
                margin: 0 auto 10px;
                text-align: center;
                position: relative;
                left: 0;
                transform: none;
            }
            .certificate-verse .verse-text {
                display: block !important;
                width: 100%;
                text-align: center !important;
                font-size: 18px !important;
                font-weight: 900 !important;
                line-height: 1.45 !important;
                color: #10285e !important;
                background: linear-gradient(90deg, #10285e 0%, #c59a2f 50%, #10285e 100%);
                -webkit-background-clip: text !important;
                -webkit-text-fill-color: transparent !important;
                text-shadow: 0 1px 1px rgba(255,255,255,0.55), 0 0 10px rgba(16, 40, 94, 0.18) !important;
            }
            .certificate-verse .verse-reference {
                display: block !important;
                margin-top: 4px;
                font-size: 12px !important;
                font-weight: 800 !important;
                letter-spacing: 0.12em !important;
                color: #8b6323 !important;
                text-shadow: 0 1px 1px rgba(255,255,255,0.48);
            }
            .certificate-verse .verse-divider {
                margin-top: 8px;
            }
            .watermark { opacity: 0.65; }
        }
        @media (max-width: 768px) { .preview-grid { grid-template-columns: 1fr; } .meta-row { grid-template-columns: 1fr; } .photo-header { grid-template-columns: 1fr; } .cross-badge { margin: 0 auto; } .signature-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="topbar">
    <h2 style="margin: 0;">✏️ ሰርቲፊኬት ማስተካከል</h2>
    <a href="admin_certificate.php">ወደ ሰርቲፊኬት ማስተዳደር</a>
</div>
<div class="wrap">
    <div class="card">
        <?php if ($error): ?><div class="msg err"><?php echo safe($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="msg ok"><?php echo safe($success); ?></div><?php endif; ?>

        <div class="preview">
            <div class="preview-frame certificate-container">
                <?php echo $watermark_html; ?>
                <div class="certificate-verse">
                    <div class="verse-text">"በጉብዝናህ ወራት ፈጣሪህን አስብ"መ.መክብብ 12፥1</div>
                    <div class="verse-divider"></div>
                </div>
                <div class="photo-header">
                    <div class="photo-column">
                        <div class="photo-frame">
                            <img src="<?php echo $instructor_photo_url; ?>" alt="Instructor photo">
                        </div>
                        <div class="photo-label">ዲ/ን ሶፎንያስ ደመቀ</div>
                    </div>
                    <div class="cross-badge" aria-hidden="true">✚</div>
                    <div class="photo-column">
                        <div class="photo-frame">
                            <img src="<?php echo $student_photo_url; ?>" alt="Student photo">
                        </div>
                        <div class="photo-label"><?php echo safe($student_name ?: 'Student'); ?></div>
                    </div>
                </div>
                <div class="church-title"><?php echo safe($certificate_title ?: 'የምስክር ወረቀት'); ?></div>
                <p class="mini" style="text-align:center;">ይህ ሰርቲፊኬት በቤተ ገብርኤል እና በእምነት ማኅደረ ትምህርት የተዘጋጀ ሲሆን የምስክር ወረቀቱ የሚሰጠው በቤት ገብርኤል የተለያዩ የቤተ ክርስቲያን ትምህርት አጠናቆ የማጠቃለያ ጥያቄ ለወሰደ ነውና እርስዎም ትምህርቱን በሚገባ አጠናቀዉ የማጠካለያ ፈተና ስለወሰዱ ታላቅ የምስራችን እያበሰርንዎ ይህንን የምስክር ወረቀት ከታላቅ አክብሮት ጋር ሰጥተነዎታል። </p>
                <div class="preview-grid">
                    <div>
                        <p class="mini"><strong>የተማሪ ስም</strong></p>
                        <p class="preview-value"><?php echo safe($student_name ?: 'እባክዎ ስም ያስገቡ'); ?></p>
                    </div>
                    <div>
                        <p class="mini"><strong>የፈተና አይነት</strong></p>
                        <p class="preview-value"><?php echo safe($exam_type ?: 'እባክዎ ያስገቡ'); ?></p>
                    </div>
                    <div>
                        <p class="mini"><strong>ውጤት</strong></p>
                        <p class="preview-value"><?php echo (int)$score; ?> / <?php echo (int)$total_questions; ?></p>
                    </div>
                    <div>
                        <p class="mini"><strong>የተሰጠበት ቀን</strong></p>
                        <p class="preview-value"><?php echo safe(date('Y-m-d H:i', strtotime($issued_at))); ?></p>
                    </div>
                </div>
                
                <div class="signature-row">
                    <div class="signature-column">
                        <div class="signature-authority"><?php echo safe($signer_name ?: ''); ?></div>
                        <div class="signature-line">ተቋም</div>
                    </div>
                    <?php echo $seal_html; ?>
                    <div class="signature-line"><?php echo safe($signer_position ?: 'ወልደ ጊዮርጊስ'); ?></div>
                </div>
                <div class="signature-row" style="margin-top: 10px;">
                    <div class="signature-column">
                        <div class="signature-authority"><?php echo safe($signer_name ?: 'ዲ/ን ሶፎንያስ ደመቀ'); ?></div>
                        <div class="signature-line">ልዩ ፊርማ</div>
                    </div>
                    <?php if ($signature_data !== ''): ?>
                        <div class="signature-image"><img src="<?php echo safe($signature_data); ?>" alt="Saved signature" /></div>
                    <?php else: ?>
                        <div class="signature-image sign-placeholder">Add Signature</div>
                    <?php endif; ?>
                    <div class="signature-line"><?php echo safe($signer_position ?: 'የሰርቲፊኬት ሰጪ ተቋም'); ?></div>
                </div>
            </div>
            <div class="actions no-print">
                <button type="submit" form="certificate-form">💾 ሰርቲፊኬት አስተካክል</button>
                <button type="button" class="print-btn" onclick="printCertificate()">🖨️ ሰርቲፊኬት አትም</button>
                <a class="print-btn" href="admin_certificate.php?download=<?php echo (int)$cert_id; ?>">⬇️ PDF አውርድ</a>
            </div>
        </div>

        <form id="certificate-form" method="post" enctype="multipart/form-data">
            <div class="photo-manager">
                <div class="photo-card">
                    <h4>Instructor Photo</h4>
                    <div class="photo-preview"><img id="instructorPhotoPreview" src="<?php echo $instructor_photo_url; ?>" alt="Instructor photo preview"></div>
                    <div class="photo-input-row">
                        <input type="file" id="instructor_photo_file" name="instructor_photo_file" accept="image/*">
                    </div>
                    <div class="photo-actions">
                        <button type="submit" class="ghost-btn">Save Instructor Photo</button>
                        <button type="submit" name="delete_instructor_photo" value="1" class="ghost-btn">Delete</button>
                    </div>
                    <input type="hidden" name="instructor_photo" value="<?php echo safe($instructor_photo); ?>">
                </div>
                <div class="photo-card">
                    <h4>Student Photo</h4>
                    <div class="photo-preview"><img id="studentPhotoPreview" src="<?php echo $student_photo_url; ?>" alt="Student photo preview"></div>
                    <div class="photo-input-row">
                        <input type="file" id="student_photo_file" name="student_photo_file" accept="image/*">
                    </div>
                    <div class="photo-actions">
                        <button type="submit" class="ghost-btn">Save Student Photo</button>
                        <button type="submit" name="delete_student_photo" value="1" class="ghost-btn">Delete</button>
                    </div>
                    <input type="hidden" name="student_photo" value="<?php echo safe($student_photo); ?>">
                </div>
            </div>
            <div class="field">
                <label for="student_name">የተማሪ ስም</label>
                <input type="text" id="student_name" name="student_name" value="<?php echo safe($student_name); ?>" required>
            </div>
            <div class="field">
                <label for="exam_type">ፈተና አይነት</label>
                <input type="text" id="exam_type" name="exam_type" value="<?php echo safe($exam_type); ?>" required>
            </div>
            <div class="field">
                <label for="certificate_title">Certificate Title</label>
                <input type="text" id="certificate_title" name="certificate_title" value="<?php echo safe($certificate_title); ?>">
            </div>
            <div class="row">
                <div class="field">
                    <label for="seal_text_top">Seal Top Text</label>
                    <input type="text" id="seal_text_top" name="seal_text_top" value="<?php echo safe($seal_text_top); ?>">
                </div>
                <div class="field">
                    <label for="seal_text_bottom">Seal Bottom Text</label>
                    <input type="text" id="seal_text_bottom" name="seal_text_bottom" value="<?php echo safe($seal_text_bottom); ?>">
                </div>
            </div>
            <div class="field">
                <label for="signer_name">Certificate Authority Name</label>
                <input type="text" id="signer_name" name="signer_name" value="<?php echo safe($signer_name); ?>">
            </div>
            <div class="field">
                <label for="signer_position">Certificate Position</label>
                <input type="text" id="signer_position" name="signer_position" value="<?php echo safe($signer_position); ?>">
            </div>
            <div class="row">
                <div class="field">
                    <label for="score">ውጤት</label>
                    <input type="number" id="score" name="score" min="0" value="<?php echo (int)$score; ?>" required>
                </div>
                <div class="field">
                    <label for="total_questions">ጠቅላላ ጥያቄዎች</label>
                    <input type="number" id="total_questions" name="total_questions" min="1" value="<?php echo (int)$total_questions; ?>" required>
                </div>
            </div>
            <div class="field">
                <label for="issued_at">የተሰጠበት ቀን</label>
                <input type="datetime-local" id="issued_at" name="issued_at" value="<?php echo date('Y-m-d\TH:i', strtotime($issued_at)); ?>" required>
            </div>            <div class="signature-pad-wrap">
                <canvas id="signatureCanvas" width="480" height="180" aria-label="Signature pad"></canvas>
                <div class="signature-actions no-print">
                    <button type="button" id="clearSignature">Clear Signature</button>
                    <button type="button" id="saveSignature">Save Signature</button>
                </div>
                <input type="hidden" id="signature_data" name="signature_data" value="<?php echo safe($signature_data); ?>">
            </div>            <button type="submit">ሰርቲፊኬት አስተካክል</button>
        </form>
    </div>
</div>
        <script>
        function bindPhotoPreview(inputId, previewId) {
            var input = document.getElementById(inputId);
            var preview = document.getElementById(previewId);
            if (!input || !preview) {
                return;
            }

            input.addEventListener('change', function () {
                var file = this.files && this.files[0];
                if (!file) {
                    return;
                }
                var objectUrl = URL.createObjectURL(file);
                preview.src = objectUrl;
            });
        }

        bindPhotoPreview('instructor_photo_file', 'instructorPhotoPreview');
        bindPhotoPreview('student_photo_file', 'studentPhotoPreview');

        function printCertificate() {
            var preview = document.querySelector('.preview-frame');
            if (!preview) {
                window.print();
                return;
            }

            var css = 'body{font-family: Georgia, "Times New Roman", serif; margin:0; padding:24px; background:#fff} .preview-frame{position:relative; overflow:hidden; border:8px solid #d2a54f; border-radius:28px; padding:18px 20px; background:radial-gradient(circle at top, #fffaf0 0%, #fffdf8 52%, #f2ead5 100%);} .preview-frame::before{content:""; position:absolute; inset:10px; border:1px solid rgba(180,139,50,0.65); border-radius:20px; pointer-events:none;} .photo-header{position:relative; z-index:1; display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:14px; margin-bottom:12px;} .photo-column{display:flex; flex-direction:column; align-items:center; gap:8px;} .photo-frame{width:116px; height:116px; padding:4px; border-radius:50%; background:linear-gradient(135deg, #f2d894, #7a1f1f); box-shadow:0 12px 24px rgba(122, 31, 31, 0.22);} .photo-frame img{width:100%; height:100%; object-fit:cover; border-radius:50%; border:3px solid #f7e6ba; background:#fffdf7;} .photo-label{font-size:11px; line-height:1.4; text-align:center; color:#7a1f1f; font-weight:700; text-transform:uppercase; letter-spacing:0.08em;} .cross-badge{width:92px; height:92px; border-radius:50%; display:grid; place-items:center; font-size:34px; color:#7a1f1f; background:radial-gradient(circle at center, #fff7dd 0%, #f0d38b 54%, #a02a28 100%); border:3px solid #8f5b18; box-shadow:0 10px 22px rgba(122, 31, 31, 0.2);} .church-title{margin:10px 0 8px; font-size:22px; font-weight:800; letter-spacing:0.04em; text-align:center; color:#7a1f1f; text-transform:uppercase;} .church-title span{color:#a02a28;} .preview-grid{display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:14px; margin-top:16px;} .preview-value{margin:6px 0 0; font-size:16px; color:#3f2319; font-weight:700;} .mini{color:#7e5330; font-size:13px; line-height:1.6;} .mini strong{color:#7a1f1f;} .meta-row{position:relative; z-index:1; display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:12px; margin-top:14px;} .meta-card{border:1px solid #dcbf83; border-radius:12px; padding:10px 12px; background:rgba(255,250,240,0.84);} .meta-label{display:block; font-size:11px; color:#885e2f; letter-spacing:0.08em; text-transform:uppercase; font-weight:700;} .meta-value{display:block; margin-top:4px; font-size:13px; font-weight:800; color:#4b2a1d; word-break:break-word;} .watermark{position:absolute; inset:0; display:grid; place-items:center; font-size:64px; font-weight:900; letter-spacing:5px; color:rgba(122,31,31,0.10); text-transform:uppercase; text-align:center; transform:rotate(-18deg); pointer-events:none; z-index:0;} .qr-box{position:relative; z-index:1; margin-top:14px; display:flex; align-items:center; gap:12px; border:1px solid #dcbf83; border-radius:14px; padding:10px; background:rgba(255,248,230,0.88);} .qr-box svg{flex:0 0 auto;} .qr-caption{font-size:12px; color:#6b4d2a; line-height:1.5;} .signature-row{position:relative; z-index:1; display:grid; grid-template-columns:1fr 180px 1fr; align-items:center; gap:16px; margin-top:18px;} .signature-line{border-top:1px solid #b88b41; padding-top:8px; text-align:center; font-size:12px; color:#7a1f1f; font-weight:700; text-transform:uppercase; letter-spacing:0.08em;} .signature-image{min-height:70px; display:flex; align-items:center; justify-content:center;} .signature-image img{max-width:150px; max-height:68px; object-fit:contain;} .sign-placeholder{border:1px dashed #c99e42; border-radius:12px; padding:10px; color:#7a1f1f; font-weight:700; font-size:12px; background:rgba(247,236,207,0.8);} .seal{display:flex; justify-content:center; align-items:center;} .seal-svg{width:170px; height:170px; overflow:visible; filter:drop-shadow(0 8px 14px rgba(122,31,31,0.18));}';
            var newWin = window.open('', '_blank');
            newWin.document.open();
            newWin.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Certificate Print</title><style>' + css + '</style></head><body>');
            newWin.document.write(preview.outerHTML);
            newWin.document.write('</body></html>');
            newWin.document.close();
            newWin.focus();
            setTimeout(function(){ newWin.print(); }, 250);
        }

        (function () {
            var canvas = document.getElementById('signatureCanvas');
            if (!canvas) {
                return;
            }

            var ctx = canvas.getContext('2d');
            var drawing = false;
            var signatureInput = document.getElementById('signature_data');
            var lastX = 0;
            var lastY = 0;

            function resizeCanvas() {
                var ratio = window.devicePixelRatio || 1;
                var rect = canvas.getBoundingClientRect();
                var width = Math.max(320, Math.round(rect.width));
                var height = 180;
                var image = ctx.getImageData(0, 0, canvas.width, canvas.height);
                canvas.width = width * ratio;
                canvas.height = height * ratio;
                ctx.setTransform(1, 0, 0, 1, 0, 0);
                ctx.scale(ratio, ratio);
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                ctx.lineWidth = 2.6;
                ctx.strokeStyle = '#7a1f1f';
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, width, height);
                if (image && image.data && image.data.length) {
                    ctx.putImageData(image, 0, 0);
                }
            }

            function getPos(evt) {
                var rect = canvas.getBoundingClientRect();
                return {
                    x: evt.clientX - rect.left,
                    y: evt.clientY - rect.top,
                };
            }

            function beginDraw(evt) {
                drawing = true;
                var pos = getPos(evt);
                lastX = pos.x;
                lastY = pos.y;
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
            }

            function moveDraw(evt) {
                if (!drawing) {
                    return;
                }
                var pos = getPos(evt);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
                lastX = pos.x;
                lastY = pos.y;
            }

            function endDraw() {
                drawing = false;
            }

            resizeCanvas();
            canvas.addEventListener('pointerdown', beginDraw);
            canvas.addEventListener('pointermove', moveDraw);
            canvas.addEventListener('pointerup', endDraw);
            canvas.addEventListener('pointerleave', endDraw);
            canvas.addEventListener('pointercancel', endDraw);

            document.getElementById('clearSignature').addEventListener('click', function () {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                signatureInput.value = '';
            });

            document.getElementById('saveSignature').addEventListener('click', function () {
                signatureInput.value = canvas.toDataURL('image/png');
                var previewImg = document.querySelector('.signature-image img');
                if (previewImg) {
                    previewImg.src = signatureInput.value;
                }
            });
        })();
        </script>
</body>
</html>
