<?php
session_start();
require_once __DIR__ . '/db.php';

$lang = getCurrentLanguage();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_lang'])) {
    $lang = setCurrentLanguage($_POST['set_lang']);
}

$headline = $lang === 'en' ? 'Privacy Policy' : 'የግላዊነት ፖሊሲ';
$intro = $lang === 'en'
    ? 'We respect your privacy and are committed to protecting the personal information you share with us.'
    : 'እኛ የእርስዎን ግላዊነት እንከብራለን እና ከእኛ ጋር የምታካፍሉትን የግል መረጃ ለመጠበቅ ቁርጠኝነት እናደርጋለን።';
$lastUpdated = $lang === 'en' ? 'Last updated: June 2026' : 'መጨረሻ የተሻሻለበት ቀን: ሰኔ 2026';
$sections = [
    [
        $lang === 'en' ? 'Information we collect' : 'የምንሰበስበው መረጃ',
        $lang === 'en'
            ? 'We collect basic contact information such as your name, email address, and student registration details when you use our forms or create an account.'
            : 'በቅጾቻችን ወይም መለያ በሚፈጥሩበት ጊዜ ስም፣ ኢሜይል አድራሻ እና የተማሪ መመዝገብ ዝርዝሮችን እንሰበስባለን።'
    ],
    [
        $lang === 'en' ? 'How we use it' : 'እንዴት እንጠቀማለን',
        $lang === 'en'
            ? 'Your information is used to respond to requests, manage registrations, improve services, and maintain the security of the website.'
            : 'የእርስዎ መረጃ ጥያቄዎችን ለመመለስ፣ መመዝገብ ለማስተዳደር፣ አገልግሎቶችን ለማሻሻል እና የድር ጣቢያውን ደህንነት ለመጠበቅ ያገለግላል።'
    ],
    [
        $lang === 'en' ? 'Your choices' : 'የእርስዎ ምርጫዎች',
        $lang === 'en'
            ? 'You may request access to, correction of, or deletion of your personal data, subject to applicable legal requirements.'
            : 'በሚመለከተው ህጋዊ መስፈርት ላይ ተመስርተው የግልዎን ውሂብ ለመመልከት፣ ለማስተካከል ወይም ለመሰረዝ መጠየቅ ይችላሉ።'
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
