<?php
session_start();
require_once __DIR__ . '/db.php';

$lang = getCurrentLanguage();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_lang'])) {
    $lang = setCurrentLanguage($_POST['set_lang']);
}

$headline = $lang === 'en' ? 'Terms and Conditions' : 'የአገልግሎት ውል';
$intro = $lang === 'en'
    ? 'These terms govern your use of this website and its learning, registration, and communication services.'
    : 'እነዚህ ውሎች ይህንን ድር ጣቢያ እና የመማር ፣ የመመዝገብ እና የግንኙነት አገልግሎቶችን ለመጠቀም የሚመለከቱ ናቸው።';
$lastUpdated = $lang === 'en' ? 'Last updated: June 2026' : 'መጨረሻ የተሻሻለበት ቀን: ሰኔ 2026';
$sections = [
    [
        $lang === 'en' ? 'Use of the website' : 'የድር ጣቢያ አጠቃቀም',
        $lang === 'en'
            ? 'You may use the website for lawful purposes related to learning, enrollment, and communication. Any misuse, harmful behavior, or unauthorized access is prohibited.'
            : 'ድር ጣቢያውን ለህጋዊ የመማር፣ የመመዝገብ እና የግንኙነት አጠቃቀም መጠቀም ይችላሉ። ማንኛውም የተሳሳተ አጠቃቀም፣ ጎጂ ባህሪ ወይም ያልተፈቀደ መዳረሻ የተከለከለ ነው።'
    ],
    [
        $lang === 'en' ? 'User accounts' : 'የተጠቃሚ መለያዎች',
        $lang === 'en'
            ? 'You are responsible for keeping your account credentials secure. We reserve the right to suspend or remove accounts that violate these terms.'
            : 'የመለያ መረጃዎን ደህንነት ያለው መጠባበቅ እርስዎ ኃላፊነት ነው። እነዚህን ውሎች የሚጥሱ መለያዎችን ለማግደስ ወይም ለማስወገድ መብት እንወስዳለን።'
    ],
    [
        $lang === 'en' ? 'Intellectual property' : 'አመለካከት ማስረጃ',
        $lang === 'en'
            ? 'All text, images, and educational material on this website are protected by applicable copyright and intellectual property laws.'
            : 'በዚህ ድር ጣቢያ ላይ ያለው ሁሉም ጽሑፍ፣ ምስል እና የትምህርት ቁሳቁስ በሚመለከት የቅጂ መብት እና የአእምሮ ንብረት ህጎች የተጠበቀ ነው።'
    ],
    [
        $lang === 'en' ? 'Contact' : 'አድራሻ',
        $lang === 'en'
            ? 'If you have questions about these terms, please contact us through the website contact form or the listed email address.'
            : 'ስለ እነዚህ ውሎች ጥያቄ ካሎት እባክዎ በድር ጣቢያው የግንኙነት ቅጽ ወይም በተጠቀሰው ኢሜይል አድራሻ ያግኙን።'
    ],
];
?>
<!DOCTYPE html>
<html lang="<?php echo safe($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe($headline); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin:0; background:#f8fafc; color:#0f172a; line-height:1.7; }
        .wrap { max-width:900px; margin:0 auto; padding:32px 20px 48px; }
        .card { background:#fff; border-radius:16px; padding:24px; box-shadow:0 10px 30px rgba(15,23,42,0.08); }
        .topbar { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
        .btn { display:inline-block; padding:8px 12px; background:#2563eb; color:#fff; text-decoration:none; border-radius:8px; }
        .btn.secondary { background:#0f766e; }
        .lang-switch { display:flex; gap:8px; }
        .lang-switch button { border:1px solid #cbd5e1; background:#fff; padding:6px 10px; border-radius:999px; cursor:pointer; }
        .lang-switch button.active { background:#2563eb; color:#fff; border-color:#2563eb; }
        h1 { margin-top:0; }
        .muted { color:#64748b; }
        .section { margin-top:18px; padding-top:16px; border-top:1px solid #e2e8f0; }
        .section h3 { margin-bottom:6px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <a class="btn" href="sofonyas2.php">← <?php echo safe($lang === 'en' ? 'Back to Home' : 'ወደ መነሻ ተመለስ'); ?></a>
        <form class="lang-switch" method="post">
            <input type="hidden" name="set_lang" value="am">
            <button type="submit" class="<?php echo $lang === 'am' ? 'active' : ''; ?>">አማርኛ</button>
        </form>
        <form class="lang-switch" method="post">
            <input type="hidden" name="set_lang" value="en">
            <button type="submit" class="<?php echo $lang === 'en' ? 'active' : ''; ?>">English</button>
        </form>
    </div>

    <div class="card">
        <h1><?php echo safe($headline); ?></h1>
        <p class="muted"><?php echo safe($intro); ?></p>
        <p class="muted"><?php echo safe($lastUpdated); ?></p>

        <?php foreach ($sections as $section): ?>
            <div class="section">
                <h3><?php echo safe($section[0]); ?></h3>
                <p><?php echo safe($section[1]); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
