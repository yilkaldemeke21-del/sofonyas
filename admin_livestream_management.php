<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/management_schema.php';
require_once __DIR__ . '/admin_management_i18n.php';
requireRole(['admin'], $pdo);

if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit(adminManagementText('db_unavailable'));
}

ensureManagementTables($pdo);
$csrf = csrfToken();
$platforms = ['youtube', 'facebook', 'website', 'other'];
$statuses = ['scheduled', 'live', 'ended', 'cancelled'];
$error = '';
$message = '';
$editingId = filter_var($_GET['edit'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?: 0;
$editingStream = null;

if ($editingId > 0) {
    $editStmt = $pdo->prepare('SELECT * FROM church_livestreams WHERE id = :id LIMIT 1');
    $editStmt->execute([':id' => $editingId]);
    $editingStream = $editStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function isValidLivestreamUrl(string $url, string $platform): bool
{
    if ($url === '') {
        return true;
    }
    if (strlen($url) > 1000 || !filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $parts = parse_url($url);
    if (!$parts || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        return false;
    }

    $host = strtolower((string)$parts['host']);
    if ($platform === 'youtube') {
        return in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'www.youtu.be'], true);
    }
    if ($platform === 'facebook') {
        return in_array($host, ['facebook.com', 'www.facebook.com', 'fb.watch', 'm.facebook.com'], true);
    }
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $error = adminManagementText('csrf_error');
    } else {
        $streamId = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $platform = (string)($_POST['platform'] ?? 'youtube');
        $streamUrl = trim((string)($_POST['stream_url'] ?? ''));
        $replayUrl = trim((string)($_POST['replay_url'] ?? ''));
        $eventAtInput = (string)($_POST['event_at'] ?? '');
        $eventAt = DateTime::createFromFormat('!Y-m-d\TH:i', $eventAtInput);
        $status = (string)($_POST['status'] ?? 'scheduled');
        $isPublic = isset($_POST['is_public']) ? 1 : 0;

        if ($streamId === false || $title === '' || strlen($title) > 180 || strlen($description) > 10000 || !in_array($platform, $platforms, true) || !in_array($status, $statuses, true) || !$eventAt || $eventAt->format('Y-m-d\TH:i') !== $eventAtInput || !isValidLivestreamUrl($streamUrl, $platform) || !isValidLivestreamUrl($replayUrl, $platform) || ($status === 'live' && $streamUrl === '')) {
            $error = adminManagementText('livestream_validation_error');
        } else {
            try {
                if ((int)$streamId > 0) {
                    $stmt = $pdo->prepare('UPDATE church_livestreams SET title = :title, description = :description, platform = :platform, stream_url = :stream_url, replay_url = :replay_url, event_at = :event_at, status = :status, is_public = :is_public WHERE id = :id');
                    $stmt->execute([
                        ':title' => $title,
                        ':description' => $description !== '' ? $description : null,
                        ':platform' => $platform,
                        ':stream_url' => $streamUrl !== '' ? $streamUrl : null,
                        ':replay_url' => $replayUrl !== '' ? $replayUrl : null,
                        ':event_at' => $eventAt->format('Y-m-d H:i:s'),
                        ':status' => $status,
                        ':is_public' => $isPublic,
                        ':id' => (int)$streamId,
                    ]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO church_livestreams (title, description, platform, stream_url, replay_url, event_at, status, is_public, created_by) VALUES (:title, :description, :platform, :stream_url, :replay_url, :event_at, :status, :is_public, :created_by)');
                    $stmt->execute([
                        ':title' => $title,
                        ':description' => $description !== '' ? $description : null,
                        ':platform' => $platform,
                        ':stream_url' => $streamUrl !== '' ? $streamUrl : null,
                        ':replay_url' => $replayUrl !== '' ? $replayUrl : null,
                        ':event_at' => $eventAt->format('Y-m-d H:i:s'),
                        ':status' => $status,
                        ':is_public' => $isPublic,
                        ':created_by' => (int)($_SESSION['admin_id'] ?? 0),
                    ]);
                }
                header('Location: admin_livestream_management.php?lang=' . urlencode($adminManagementLanguage) . '&saved=1');
                exit;
            } catch (Throwable $exception) {
                error_log('Livestream save failed: ' . $exception->getMessage());
                $error = adminManagementText('livestream_save_error');
            }
        }
    }
}

if (isset($_GET['saved'])) {
    $message = adminManagementText('livestream_saved');
}
$streams = $pdo->query('SELECT * FROM church_livestreams ORDER BY event_at DESC, id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
$publicUrl = buildAppUrl('church_livestream.php?lang=' . urlencode($adminManagementLanguage));
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe(adminManagementText('livestream_title')); ?></title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f3f6f4;color:#202b27;font-family:Arial,sans-serif}.topbar{background:#143b36;color:#fff;padding:16px max(20px,calc((100% - 1180px)/2));display:flex;justify-content:space-between;align-items:center;gap:12px}.topbar h1{font-size:20px;margin:0}.topbar nav{display:flex;gap:8px;flex-wrap:wrap}.topbar a,.button{color:#fff;text-decoration:none;background:#236c5e;border:0;padding:9px 12px;border-radius:4px;font:inherit;cursor:pointer}.wrap{max-width:1180px;margin:22px auto;padding:0 18px}.panel{background:#fff;border-top:2px solid #d7e2dc;padding:17px;margin-bottom:16px}.layout{display:grid;grid-template-columns:minmax(280px,.8fr) minmax(0,1.5fr);gap:16px}.panel h2{font-size:17px;margin:0 0 14px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.field{display:flex;flex-direction:column;gap:5px}.field.full{grid-column:1/-1}label{font-size:13px;font-weight:600}input,select,textarea{width:100%;padding:9px;border:1px solid #cbd5d1;border-radius:4px;background:#fff;color:inherit;font:inherit}textarea{min-height:90px;resize:vertical}.button{display:inline-block}.button.secondary{color:#236c5e;background:#fff;border:1px solid #236c5e}.notice{padding:10px 12px;margin-bottom:12px;background:#e8f4ef;color:#176244}.notice.error{background:#fff0eb;color:#9b351f}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;padding:9px 8px;border-bottom:1px solid #e4ebe7;vertical-align:top}th{background:#f3f7f4;white-space:nowrap}.muted{color:#64736e;font-size:12px}.actions{display:flex;gap:8px;flex-wrap:wrap}.actions a{color:#236c5e}.checkbox{display:flex;align-items:center;gap:8px;font-size:13px}.checkbox input{width:auto}@media(max-width:850px){.layout{grid-template-columns:1fr}.topbar{align-items:flex-start;flex-direction:column}}@media(max-width:560px){.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.wrap{padding:0 12px}}
    </style>
</head>
<body>
<header class="topbar"><h1><?php echo safe(adminManagementText('livestream_title')); ?></h1><nav><a href="<?php echo safe($adminManagementSwitchUrl); ?>"><?php echo safe(adminManagementText('language_switch')); ?></a><a href="<?php echo safe($publicUrl); ?>" target="_blank" rel="noopener"><?php echo safe(adminManagementText('livestream_public_page')); ?></a><a href="admin_management_dashboard.php?lang=<?php echo safe($adminManagementLanguage); ?>"><?php echo safe(adminManagementText('back_dashboard')); ?></a></nav></header>
<main class="wrap">
    <?php if ($message !== ''): ?><div class="notice"><?php echo safe($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error"><?php echo safe($error); ?></div><?php endif; ?>
    <div class="layout">
        <section class="panel">
            <h2><?php echo safe(adminManagementText($editingStream ? 'livestream_edit' : 'livestream_new')); ?></h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>">
                <input type="hidden" name="id" value="<?php echo (int)($editingStream['id'] ?? 0); ?>">
                <div class="form-grid">
                    <div class="field full"><label for="title"><?php echo safe(adminManagementText('livestream_event_title')); ?></label><input id="title" name="title" maxlength="180" value="<?php echo safe($editingStream['title'] ?? ''); ?>" required></div>
                    <div class="field"><label for="event_at"><?php echo safe(adminManagementText('livestream_event_at')); ?></label><input id="event_at" name="event_at" type="datetime-local" value="<?php echo safe(isset($editingStream['event_at']) ? date('Y-m-d\TH:i', strtotime($editingStream['event_at'])) : ''); ?>" required></div>
                    <div class="field"><label for="platform"><?php echo safe(adminManagementText('livestream_platform')); ?></label><select id="platform" name="platform"><?php foreach ($platforms as $platform): ?><option value="<?php echo safe($platform); ?>" <?php echo ($editingStream['platform'] ?? 'youtube') === $platform ? 'selected' : ''; ?>><?php echo safe(adminManagementText('livestream_platform_' . $platform)); ?></option><?php endforeach; ?></select></div>
                    <div class="field full"><label for="stream_url"><?php echo safe(adminManagementText('livestream_stream_url')); ?></label><input id="stream_url" name="stream_url" type="url" maxlength="1000" placeholder="https://" value="<?php echo safe($editingStream['stream_url'] ?? ''); ?>"></div>
                    <div class="field full"><label for="replay_url"><?php echo safe(adminManagementText('livestream_replay_url')); ?></label><input id="replay_url" name="replay_url" type="url" maxlength="1000" placeholder="https://" value="<?php echo safe($editingStream['replay_url'] ?? ''); ?>"></div>
                    <div class="field"><label for="status"><?php echo safe(adminManagementText('status')); ?></label><select id="status" name="status"><?php foreach ($statuses as $status): ?><option value="<?php echo safe($status); ?>" <?php echo ($editingStream['status'] ?? 'scheduled') === $status ? 'selected' : ''; ?>><?php echo safe(adminManagementText('livestream_status_' . $status)); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>&nbsp;</label><label class="checkbox"><input type="checkbox" name="is_public" value="1" <?php echo !isset($editingStream['is_public']) || (int)$editingStream['is_public'] === 1 ? 'checked' : ''; ?>><?php echo safe(adminManagementText('livestream_public')); ?></label></div>
                    <div class="field full"><label for="description"><?php echo safe(adminManagementText('description')); ?></label><textarea id="description" name="description" maxlength="10000"><?php echo safe($editingStream['description'] ?? ''); ?></textarea></div>
                </div>
                <button class="button" type="submit"><?php echo safe(adminManagementText('livestream_save')); ?></button><?php if ($editingStream): ?> <a class="button secondary" href="admin_livestream_management.php?lang=<?php echo safe($adminManagementLanguage); ?>"><?php echo safe(adminManagementText('cancel_edit')); ?></a><?php endif; ?>
            </form>
        </section>
        <section class="panel">
            <h2><?php echo safe(adminManagementText('livestream_records')); ?></h2>
            <div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('livestream_event_at')); ?></th><th><?php echo safe(adminManagementText('livestream_event_title')); ?></th><th><?php echo safe(adminManagementText('livestream_platform')); ?></th><th><?php echo safe(adminManagementText('status')); ?></th><th><?php echo safe(adminManagementText('livestream_links')); ?></th><th><?php echo safe(adminManagementText('action')); ?></th></tr></thead><tbody>
            <?php if (!$streams): ?><tr><td colspan="6"><?php echo safe(adminManagementText('livestream_empty')); ?></td></tr><?php else: foreach ($streams as $stream): ?>
                <tr><td><?php echo safe($stream['event_at']); ?></td><td><?php echo safe($stream['title']); ?><br><span class="muted"><?php echo safe((int)$stream['is_public'] === 1 ? adminManagementText('livestream_public') : adminManagementText('livestream_private')); ?></span></td><td><?php echo safe(adminManagementText('livestream_platform_' . $stream['platform'])); ?></td><td><?php echo safe(adminManagementText('livestream_status_' . $stream['status'])); ?></td><td><?php if (!empty($stream['stream_url'])): ?><a href="<?php echo safe($stream['stream_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo safe(adminManagementText('livestream_watch')); ?></a><?php endif; ?><?php if (!empty($stream['replay_url'])): ?><br><a href="<?php echo safe($stream['replay_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo safe(adminManagementText('livestream_replay')); ?></a><?php endif; ?></td><td><a href="admin_livestream_management.php?lang=<?php echo safe($adminManagementLanguage); ?>&edit=<?php echo (int)$stream['id']; ?>"><?php echo safe(adminManagementText('resource_edit_action')); ?></a></td></tr>
            <?php endforeach; endif; ?>
            </tbody></table></div>
        </section>
    </div>
</main>
</body>
</html>
