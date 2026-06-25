<?php
session_start();
require_once __DIR__ . '/db.php';

$lang = getCurrentLanguage();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_lang'])) {
    $lang = setCurrentLanguage($_POST['set_lang']);
}

$headline = $lang === 'en' ? 'Cookie Policy' : 'የኩኪ ፖሊሲ';
$intro = $lang === 'en'
    ? 'This website uses cookies to improve performance, remember your language preference, and support a better browsing experience.'
    : 'ይህ ድር ጣቢያ አፈጻጸምን ለማሻሻል፣ የእርስዎን የቋንቋ ምርጫ ለማስታወስ እና የተሻለ የመቃኘት ልምድ ለመደገፍ ኩኪዎችን ይጠቀማል።';
$lastUpdated = $lang === 'en' ? 'Last updated: June 2026' : 'መጨረሻ የተሻሻለበት ቀን: ሰኔ 2026';
$sections = [
    [
        $lang === 'en' ? 'What cookies are' : 'ኩኪ ምንድን ነው',
        $lang === 'en'
            ? 'Cookies are small text files stored on your device that help websites recognize your browser and remember useful settings.'
            : 'ኩኪዎች በመሣሪያዎ ላይ የሚከማቹ ትናንሽ የጽሑፍ ፋይሎች ሲሆኑ ድር ጣቢያዎች አሳሽዎን እንዲያውቁ እና ጠቃሚ ቅንብሮችን እንዲያስታውሱ ይረዳሉ።'
    ],
    [
        $lang === 'en' ? 'Why we use them' : 'ለምን እንጠቀማለን',
        $lang === 'en'
            ? 'We use cookies for language selection, site performance, and essential functionality that makes your experience smoother.'
            : 'የቋንቋ ምርጫ፣ የድር ጣቢያ አፈጻጸም እና ልምድዎን ይበልጥ ለማመቻቸት አስፈላጊ ተግባራትን ለመደገፍ ኩኪዎችን እንጠቀማለን።'
    ],
    [
        $lang === 'en' ? 'Your control' : 'የእርስዎ ቁጥጥር',
        $lang === 'en'
            ? 'You can adjust your browser settings to refuse cookies, although some website features may become less functional.'
            : 'ኩኪዎችን ለመከልከል የአሳሽዎን ቅንብሮች ማስተካከል ይችላሉ፣ ምንም እንኳን አንዳንድ የድር ጣቢያ ባህሪዎች ያነሰ ተግባራዊ ሊሆኑ ይችላሉ።'
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
