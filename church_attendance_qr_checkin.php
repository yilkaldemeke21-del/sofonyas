<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/management_schema.php';
require_once __DIR__ . '/admin_management_i18n.php';
requireRole(['admin'], $pdo);
ensureManagementTables($pdo);

$member = null;
$notice = '';
$error = '';
$token = trim((string)($_GET['token'] ?? ''));
$csrf = csrfToken();

if ($token !== '') {
    $memberStmt = $pdo->prepare('SELECT id, member_number, full_name, status, email, verification_token FROM church_members WHERE verification_token = :token LIMIT 1');
    $memberStmt->execute([':token' => $token]);
    $member = $memberStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $error = adminManagementText('csrf_error');
    } else {
        $memberId = (int)($_POST['member_id'] ?? 0);
        $memberToken = trim((string)($_POST['verification_token'] ?? ''));
        $serviceName = trim((string)($_POST['service_name'] ?? ''));
        $attendanceDate = trim((string)($_POST['attendance_date'] ?? date('Y-m-d')));
        $attendanceStatus = in_array((string)($_POST['attendance_status'] ?? 'present'), ['present', 'late', 'absent'], true)
            ? (string)$_POST['attendance_status']
            : 'present';
        $eventType = trim((string)($_POST['event_type'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($memberId < 1 && $memberToken !== '') {
            $memberLookup = $pdo->prepare('SELECT id, member_number, full_name, status, email FROM church_members WHERE verification_token = :token LIMIT 1');
            $memberLookup->execute([':token' => $memberToken]);
            $member = $memberLookup->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($member) {
                $memberId = (int)$member['id'];
            }
        }

        if ($memberId < 1) {
            $error = adminManagementText('church_qr_member_not_found');
        } elseif ($memberToken === '' && $member !== null) {
            $memberToken = (string)($member['verification_token'] ?? '');
        }

        if ($error === '') {
            $memberRowStmt = $pdo->prepare('SELECT id, full_name, status, verification_token FROM church_members WHERE id = :id LIMIT 1');
            $memberRowStmt->execute([':id' => $memberId]);
            $memberRow = $memberRowStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$memberRow) {
                $error = adminManagementText('church_qr_member_not_found');
            } elseif ($memberRow['status'] !== 'active') {
                $error = adminManagementText('church_qr_inactive');
            }
        }

        if ($error === '') {
            $serviceName = trim($serviceName);
            $attendanceDateObject = DateTime::createFromFormat('!Y-m-d', $attendanceDate);
            if ($serviceName === '' || !$attendanceDateObject || $attendanceDateObject->format('Y-m-d') !== $attendanceDate) {
                $error = adminManagementText('church_qr_valid_service');
            }
        }

        if ($error === '') {
            $duplicateStmt = $pdo->prepare('SELECT id FROM church_attendance WHERE member_id = :member_id AND service_date = :service_date AND service_name = :service_name LIMIT 1');
            $duplicateStmt->execute([
                ':member_id' => $memberId,
                ':service_date' => $attendanceDate,
                ':service_name' => $serviceName,
            ]);
            if ($duplicateStmt->fetchColumn()) {
                $error = adminManagementText('church_qr_duplicate');
            }
        }

        if ($error === '') {
            try {
                $pdo->beginTransaction();
                $insertStmt = $pdo->prepare('INSERT INTO church_attendance (member_id, service_name, service_date, checkin_time, event_type, attendance_status, attendance_method, notes, recorded_by) VALUES (:member_id, :service_name, :service_date, :checkin_time, :event_type, :attendance_status, :attendance_method, :notes, :recorded_by)');
                $insertStmt->execute([
                    ':member_id' => $memberId,
                    ':service_name' => $serviceName,
                    ':service_date' => $attendanceDate,
                    ':checkin_time' => date('H:i:s'),
                    ':event_type' => $eventType !== '' ? $eventType : null,
                    ':attendance_status' => $attendanceStatus,
                    ':attendance_method' => 'qr',
                    ':notes' => $notes !== '' ? $notes : null,
                    ':recorded_by' => (string)($_SESSION['admin_username'] ?? $_SESSION['admin_name'] ?? 'admin'),
                ]);
                $pdo->commit();
                $notice = adminManagementText('church_qr_saved') . ' ' . safe($memberRow['full_name']) . ' - ' . safe($serviceName) . ' - ' . safe($attendanceDate);
                $member = $memberRow;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Church QR attendance failed: ' . $exception->getMessage());
                $error = adminManagementText('save_error');
            }
        }
    }
}

$members = $pdo->query('SELECT id, full_name, status, email FROM church_members WHERE status = "active" ORDER BY full_name ASC')->fetchAll(PDO::FETCH_ASSOC);
$serviceOptions = ['Sunday Service', 'Wednesday Prayer', 'Bible Study', 'Youth Service', 'Women Ministry', 'Men Ministry', 'Children Service', 'Special Event'];
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe(adminManagementText('church_qr_title')); ?></title>
    <style>
        *{box-sizing:border-box}body{margin:0;padding:24px;background:#edf2ef;color:#17221e;font-family:Arial,sans-serif}.page{max-width:900px;margin:0 auto}.panel{background:#fff;border:1px solid #d5dfd9;padding:24px;border-radius:8px}.topbar{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px}.topbar a,.button{display:inline-block;background:#215e52;color:#fff;padding:10px 14px;text-decoration:none;border-radius:4px}.grid{display:grid;grid-template-columns:1.2fr 0.8fr;gap:20px}.field{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}.label{font-weight:600;color:#4d5d59;font-size:13px}.input,select,textarea{width:100%;padding:10px;border:1px solid #cbd5d1;border-radius:5px;font:inherit}.notice{padding:11px 12px;border-radius:6px;margin-bottom:12px}.notice.success{background:#e5f4ec;color:#176244;border:1px solid #bfddca}.notice.error{background:#fff0eb;color:#9b351f;border:1px solid #f3c0b0}.summary{background:#f5f8f6;padding:16px;border-radius:6px;margin-top:16px}.meta{display:flex;gap:12px;flex-wrap:wrap}.meta span{display:inline-block;padding:6px 10px;background:#e2ece9;border-radius:999px;font-size:12px;color:#1d4d45}button{background:#236c5e;color:#fff;border:0;border-radius:5px;padding:10px 16px;cursor:pointer}@media (max-width:700px){.grid{grid-template-columns:1fr}.topbar{flex-direction:column;align-items:flex-start}} 
    </style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <h1 style="margin:0"><?php echo safe(adminManagementText('church_qr_title')); ?></h1>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <a href="<?php echo safe(buildAppUrl('church_attendance_qr_checkin.php?lang=' . urlencode($adminManagementLanguage === 'am' ? 'en' : 'am'))); ?>"><?php echo safe(adminManagementText('language_switch')); ?></a>
            <a href="admin_church_management.php"><?php echo safe(adminManagementText('church_qr_back')); ?></a>
        </div>
    </div>

    <?php if ($notice !== ''): ?><div class="notice success"><?php echo safe($notice); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error"><?php echo safe($error); ?></div><?php endif; ?>

    <div class="panel">
        <div class="grid">
            <div>
                <?php if ($member): ?>
                    <div class="summary">
                        <strong><?php echo safe($member['full_name']); ?></strong>
                        <div class="meta" style="margin-top:10px">
                            <span><?php echo safe(adminManagementText('member_number')); ?>: <?php echo safe($member['member_number'] ?? 'N/A'); ?></span>
                            <span><?php echo safe(adminManagementText('status')); ?>: <?php echo safe(ucfirst((string)($member['status'] ?? 'inactive'))); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="margin-bottom:16px;border:1px solid #d5dfd9;border-radius:8px;padding:12px;background:#f5f8f6;">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                        <strong><?php echo safe(adminManagementText('church_qr_scan')); ?></strong>
                        <button type="button" id="startQrCamera" style="background:#2f7d6b;padding:8px 12px;border-radius:5px;cursor:pointer;color:#fff;border:0;"><?php echo safe(adminManagementText('church_qr_start_camera')); ?></button>
                    </div>
                    <video id="qrVideo" playsinline autoplay muted style="width:100%;max-width:420px;height:260px;background:#0f1d1d;border-radius:6px;margin-top:12px;display:none;"></video>
                    <div id="qrStatus" style="margin-top:10px;color:#4d5d59;font-size:13px;"><?php echo safe(adminManagementText('church_qr_camera_notice')); ?></div>
                    <div style="margin-top:12px;">
                        <label class="label" for="manual_qr_token"><?php echo safe(adminManagementText('church_qr_manually')); ?></label>
                        <input id="manual_qr_token" class="input" value="<?php echo safe($token); ?>" placeholder="<?php echo safe(adminManagementText('church_qr_manually')); ?>" style="margin-top:6px;">
                        <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">
                            <button type="button" id="applyToken" style="background:#1d4d45;padding:8px 12px;border-radius:5px;cursor:pointer;color:#fff;border:0;"><?php echo safe(adminManagementText('church_qr_use_token')); ?></button>
                            <button type="button" id="stopQrCamera" style="background:#6b7a76;padding:8px 12px;border-radius:5px;cursor:pointer;color:#fff;border:0;"><?php echo safe(adminManagementText('church_qr_stop_camera')); ?></button>
                        </div>
                    </div>
                </div>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>">
                    <input type="hidden" name="verification_token" id="verification_token_hidden" value="<?php echo safe($token); ?>">

                    <div class="field">
                        <label class="label" for="member_id"><?php echo safe(adminManagementText('member')); ?></label>
                        <select id="member_id" name="member_id" required>
                            <option value=""><?php echo safe(adminManagementText('select_member')); ?></option>
                            <?php foreach ($members as $row): ?>
                                <option value="<?php echo (int)$row['id']; ?>" <?php echo ($member && (int)$member['id'] === (int)$row['id']) ? 'selected' : ''; ?>><?php echo safe($row['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label class="label" for="service_name"><?php echo safe(adminManagementText('service_name')); ?></label>
                        <input id="service_name" name="service_name" class="input" list="service_options" value="<?php echo safe((string)($_POST['service_name'] ?? '')); ?>" placeholder="<?php echo safe(adminManagementText('service_name')); ?>" required>
                        <datalist id="service_options">
                            <?php foreach ($serviceOptions as $option): ?><option value="<?php echo safe($option); ?>"><?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="field">
                        <label class="label" for="event_type"><?php echo safe(adminManagementText('church_qr_event_type')); ?></label>
                        <select id="event_type" name="event_type">
                            <option value=""><?php echo safe(adminManagementText('church_qr_event_type')); ?></option>
                            <option value="service"><?php echo safe(adminManagementText('church_qr_event_service')); ?></option>
                            <option value="special_event"><?php echo safe(adminManagementText('church_qr_event_special')); ?></option>
                            <option value="ministry"><?php echo safe(adminManagementText('church_qr_event_ministry')); ?></option>
                            <option value="sunday_school"><?php echo safe(adminManagementText('church_qr_event_sunday_school')); ?></option>
                            <option value="prayer"><?php echo safe(adminManagementText('church_qr_event_prayer')); ?></option>
                        </select>
                    </div>

                    <div class="field">
                        <label class="label" for="attendance_date"><?php echo safe(adminManagementText('date')); ?></label>
                        <input id="attendance_date" name="attendance_date" class="input" type="date" value="<?php echo safe((string)($_POST['attendance_date'] ?? date('Y-m-d'))); ?>" required>
                    </div>

                    <div class="field">
                        <label class="label" for="attendance_status"><?php echo safe(adminManagementText('status')); ?></label>
                        <select id="attendance_status" name="attendance_status">
                            <option value="present" <?php echo ((string)($_POST['attendance_status'] ?? 'present')) === 'present' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('present')); ?></option>
                            <option value="late" <?php echo ((string)($_POST['attendance_status'] ?? 'present')) === 'late' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('late')); ?></option>
                            <option value="absent" <?php echo ((string)($_POST['attendance_status'] ?? 'present')) === 'absent' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('absent')); ?></option>
                        </select>
                    </div>

                    <div class="field">
                        <label class="label" for="notes"><?php echo safe(adminManagementText('notes')); ?></label>
                        <textarea id="notes" name="notes" rows="3" placeholder="<?php echo safe(adminManagementText('notes')); ?>"><?php echo safe((string)($_POST['notes'] ?? '')); ?></textarea>
                    </div>

                    <button type="submit"><?php echo safe(adminManagementText('save_attendance')); ?></button>
                </form>
            </div>

            <div>
                <h3 style="margin-top:0"><?php echo safe(adminManagementText('church_qr_title')); ?></h3>
                <p><?php echo safe(adminManagementText('church_qr_desc')); ?></p>
                <ul>
                    <li><?php echo safe(adminManagementText('church_qr_help_1')); ?></li>
                    <li><?php echo safe(adminManagementText('church_qr_help_2')); ?></li>
                    <li><?php echo safe(adminManagementText('church_qr_help_3')); ?></li>
                    <li><?php echo safe(adminManagementText('church_qr_help_4')); ?></li>
                </ul>
                <div style="margin-top:16px;border-top:1px solid #d5dfd9;padding-top:16px">
                    <strong><?php echo safe(adminManagementText('church_qr_verification_link')); ?></strong>
                    <p><?php echo !empty($token) ? safe(buildAppUrl('verify_church_member.php?token=' . urlencode($token))) : safe(adminManagementText('church_qr_no_token')); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
(function () {
    const hiddenToken = document.getElementById('verification_token_hidden');
    const manualToken = document.getElementById('manual_qr_token');
    const startButton = document.getElementById('startQrCamera');
    const stopButton = document.getElementById('stopQrCamera');
    const video = document.getElementById('qrVideo');
    const statusNode = document.getElementById('qrStatus');
    let stream = null;
    let animationFrame = null;

    function setTokenValue(value) {
        const cleaned = (value || '').trim();
        if (!cleaned) {
            statusNode.textContent = <?php echo json_encode(adminManagementText('church_qr_token_required'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            return false;
        }
        const token = extractToken(cleaned);
        manualToken.value = token;
        hiddenToken.value = token;
        statusNode.textContent = <?php echo json_encode(adminManagementText('church_qr_token_loaded'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        return true;
    }

    function extractToken(value) {
        if (!value) return '';
        const urlMatch = value.match(/[?&]token=([^&]+)/i);
        if (urlMatch && urlMatch[1]) return decodeURIComponent(urlMatch[1]);
        const tokenMatch = value.match(/[a-f0-9]{32,}/i);
        return tokenMatch ? tokenMatch[0] : value.trim();
    }

    function stopCamera() {
        if (animationFrame) {
            cancelAnimationFrame(animationFrame);
            animationFrame = null;
        }
        if (stream) {
            stream.getTracks().forEach(function (track) {
                track.stop();
            });
            stream = null;
        }
        if (video) {
            video.srcObject = null;
            video.style.display = 'none';
        }
    }

    function scanFrame() {
        if (!video || !video.videoWidth || !video.videoHeight || !window.jsQR) {
            animationFrame = requestAnimationFrame(scanFrame);
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const code = jsQR(ctx.getImageData(0, 0, canvas.width, canvas.height).data, canvas.width, canvas.height);
        if (code) {
            const token = extractToken(code.data);
            if (token && setTokenValue(token)) {
                stopCamera();
                return;
            }
        }
        animationFrame = requestAnimationFrame(scanFrame);
    }

    async function startCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            statusNode.textContent = '<?php echo safe(adminManagementText('church_qr_camera_error')); ?>';
            return;
        }

        try {
            stopCamera();
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
                audio: false
            });
            video.srcObject = stream;
            video.style.display = 'block';
            statusNode.textContent = '<?php echo safe(adminManagementText('church_qr_camera_active')); ?>';
            video.onloadedmetadata = function () {
                animationFrame = requestAnimationFrame(scanFrame);
            };
        } catch (error) {
            statusNode.textContent = '<?php echo safe(adminManagementText('church_qr_camera_error')); ?>';
        }
    }

    if (startButton) {
        startButton.addEventListener('click', startCamera);
    }
    if (stopButton) {
        stopButton.addEventListener('click', stopCamera);
    }
    if (manualToken) {
        manualToken.addEventListener('change', function () {
            setTokenValue(this.value);
        });
    }
    if (document.getElementById('applyToken')) {
        document.getElementById('applyToken').addEventListener('click', function () {
            setTokenValue(manualToken.value);
        });
    }

    if (hiddenToken && hiddenToken.value) {
        statusNode.textContent = <?php echo json_encode(adminManagementText('church_qr_already_loaded'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    }
})();
</script>
</html>
