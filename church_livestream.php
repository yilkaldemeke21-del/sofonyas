<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/management_schema.php';
require_once __DIR__ . '/admin_management_i18n.php';
if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit(adminManagementText('db_unavailable'));
}
ensureManagementTables($pdo);

function livestreamYouTubeId(string $url): ?string
{
    $parts = parse_url($url);
    if (!$parts || !in_array(strtolower((string)($parts['host'] ?? '')), ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'www.youtu.be'], true)) {
        return null;
    }

    $videoId = '';
    if (strtolower((string)$parts['host']) === 'youtu.be' || strtolower((string)$parts['host']) === 'www.youtu.be') {
        $videoId = trim((string)($parts['path'] ?? ''), '/');
    } else {
        parse_str((string)($parts['query'] ?? ''), $query);
        $videoId = (string)($query['v'] ?? '');
        if ($videoId === '' && preg_match('~/(?:embed|live|shorts)/([A-Za-z0-9_-]{11})(?:$|/)~', (string)($parts['path'] ?? ''), $matches)) {
            $videoId = $matches[1];
        }
    }

    return preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) ? $videoId : null;
}

$streams = $pdo->query("SELECT id, title, description, platform, stream_url, replay_url, event_at, status FROM church_livestreams WHERE is_public = 1 AND status <> 'cancelled' ORDER BY FIELD(status, 'live', 'scheduled', 'ended'), event_at DESC, id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
$liveStreams = array_values(array_filter($streams, static fn(array $stream): bool => $stream['status'] === 'live'));
$scheduledStreams = array_values(array_filter($streams, static fn(array $stream): bool => $stream['status'] === 'scheduled'));
$endedStreams = array_values(array_filter($streams, static fn(array $stream): bool => $stream['status'] === 'ended'));
$languageSwitchUrl = buildAppUrl('church_livestream.php?lang=' . urlencode($adminManagementLanguage === 'am' ? 'en' : 'am'));
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe(adminManagementText('livestream_public_title')); ?></title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f1f5f2;color:#1d2925;font-family:Arial,sans-serif}.masthead{background:#123a34;color:#fff;padding:22px max(20px,calc((100% - 1120px)/2));display:flex;justify-content:space-between;align-items:center;gap:14px}.masthead h1{margin:0;font-size:25px}.masthead p{margin:6px 0 0;color:#d0dfd9;font-size:14px}.masthead a{color:#fff;border:1px solid #91aaa2;padding:8px 11px;text-decoration:none;border-radius:4px;white-space:nowrap}.wrap{max-width:1120px;margin:26px auto;padding:0 18px}.section{margin-bottom:26px}.section-head{display:flex;align-items:baseline;justify-content:space-between;gap:12px;border-bottom:1px solid #cfdad4;padding-bottom:9px;margin-bottom:14px}.section h2{margin:0;font-size:19px}.count{font-size:13px;color:#62726b}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.stream{background:#fff;border:1px solid #d8e1dc;border-top:3px solid #28705f;padding:14px;min-width:0}.stream.scheduled{border-top-color:#b48536}.stream.ended{border-top-color:#647a85}.stream h3{margin:0 0 7px;font-size:17px}.meta{font-size:12px;color:#5e6d67;margin-bottom:10px}.description{font-size:14px;line-height:1.55;white-space:pre-wrap;color:#34433d}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.actions a{display:inline-block;background:#236c5e;color:#fff;padding:8px 11px;border-radius:4px;text-decoration:none;font-size:13px}.actions a.secondary{background:#fff;color:#236c5e;border:1px solid #236c5e}.video{width:100%;aspect-ratio:16/9;border:0;background:#172522;margin-top:10px}.empty{padding:18px;background:#fff;border:1px solid #d8e1dc;color:#62726b}@media(max-width:700px){.grid{grid-template-columns:1fr}.masthead{align-items:flex-start;flex-direction:column}.masthead h1{font-size:21px}.wrap{padding:0 12px}}
    </style>
</head>
<body>
<header class="masthead"><div><h1><?php echo safe(adminManagementText('livestream_public_title')); ?></h1><p><?php echo safe(adminManagementText('livestream_public_intro')); ?></p></div><a href="<?php echo safe($languageSwitchUrl); ?>"><?php echo safe(adminManagementText('language_switch')); ?></a></header>
<main class="wrap">
    <?php foreach ([['live', $liveStreams], ['scheduled', $scheduledStreams], ['ended', $endedStreams]] as [$groupStatus, $groupStreams]): ?>
        <section class="section">
            <div class="section-head"><h2><?php echo safe(adminManagementText('livestream_group_' . $groupStatus)); ?></h2><span class="count"><?php echo number_format(count($groupStreams)); ?></span></div>
            <?php if (!$groupStreams): ?><div class="empty"><?php echo safe(adminManagementText('livestream_empty_group')); ?></div><?php else: ?>
                <div class="grid">
                    <?php foreach ($groupStreams as $stream):
                        $videoUrl = $stream['status'] === 'ended' ? (string)($stream['replay_url'] ?? '') : (string)($stream['stream_url'] ?? '');
                        if ($videoUrl === '' && $stream['status'] === 'ended') {
                            $videoUrl = (string)($stream['stream_url'] ?? '');
                        }
                        $videoId = $stream['platform'] === 'youtube' && $videoUrl !== '' ? livestreamYouTubeId($videoUrl) : null;
                    ?>
                        <article class="stream <?php echo safe($groupStatus); ?>">
                            <h3><?php echo safe($stream['title']); ?></h3>
                            <div class="meta"><?php echo safe(date('M j, Y · g:i A', strtotime($stream['event_at']))); ?> · <?php echo safe(adminManagementText('livestream_platform_' . $stream['platform'])); ?></div>
                            <?php if (!empty($stream['description'])): ?><div class="description"><?php echo safe($stream['description']); ?></div><?php endif; ?>
                            <?php if ($videoId): ?><iframe class="video" src="https://www.youtube-nocookie.com/embed/<?php echo safe($videoId); ?>" title="<?php echo safe($stream['title']); ?>" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe><?php endif; ?>
                            <div class="actions">
                                <?php if ($stream['status'] === 'live' && !empty($stream['stream_url'])): ?><a href="<?php echo safe($stream['stream_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo safe(adminManagementText('livestream_watch_live')); ?></a><?php endif; ?>
                                <?php if ($stream['status'] === 'scheduled' && !empty($stream['stream_url'])): ?><a class="secondary" href="<?php echo safe($stream['stream_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo safe(adminManagementText('livestream_open_stream')); ?></a><?php endif; ?>
                                <?php if ($stream['status'] === 'ended' && !empty($stream['replay_url'])): ?><a href="<?php echo safe($stream['replay_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo safe(adminManagementText('livestream_watch_replay')); ?></a><?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</main>
</body>
</html>
