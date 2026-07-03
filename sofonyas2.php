<?php
session_start();
require_once __DIR__ . '/db.php';

try {
    ensureSiteSettingsTable($pdo);
} catch (Throwable $e) {
    // ignore schema bootstrap issues
}

$siteSettings = getSiteSettings($pdo);
$siteName = trim((string)($siteSettings['website_name'] ?? ''));
if ($siteName === '') {
    $siteName = 'Sofoniyas Website';
}
$siteLogo = trim((string)($siteSettings['logo'] ?? ''));
$siteLogoUrl = publicMediaUrl($siteLogo);
if ($siteLogoUrl === '') {
    $siteLogoUrl = '10 .jpg';
}
$contactEmail = trim((string)($siteSettings['contact_email'] ?? ''));
if ($contactEmail === '') {
    $contactEmail = 'yilkaldemeke21@gmail.com';
}
$phoneNumber = trim((string)($siteSettings['phone_number'] ?? ''));
if ($phoneNumber === '') {
    $phoneNumber = '+251 927603731';
}
$footerText = trim((string)($siteSettings['footer_text'] ?? ''));
if ($footerText === '') {
    $footerText = 'Address: motta, Ethiopia';
}

$lang = getCurrentLanguage();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_lang'])) {
    $lang = setCurrentLanguage($_POST['set_lang']);
}

$chatMessages = [];
try {
    $stmt = $pdo->prepare('SELECT id, sender_type, sender_name, message, reply_message, status, created_at, updated_at FROM site_chat_messages ORDER BY id DESC LIMIT 18');
    $stmt->execute();
    $chatMessages = array_reverse($stmt->fetchAll());
} catch (Throwable $e) {
    $chatMessages = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_message']) && trim($_POST['chat_message']) !== '') {
    $senderName = trim((string)($_POST['chat_name'] ?? 'Guest'));
    $senderName = $senderName === '' ? 'Guest' : $senderName;
    $message = trim((string)$_POST['chat_message']);
    $pdo->prepare('INSERT INTO site_chat_messages (sender_type, sender_name, message, status) VALUES (:sender_type, :sender_name, :message, :status)')->execute([
        ':sender_type' => 'guest',
        ':sender_name' => $senderName,
        ':message' => $message,
        ':status' => 'new',
    ]);
    header('Location: sofonyas2.php?lang=' . $lang);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message']) && isset($_POST['chat_id'])) {
    $chatId = (int)$_POST['chat_id'];
    $reply = trim((string)$_POST['reply_message']);
    if ($chatId > 0 && $reply !== '') {
        $pdo->prepare('UPDATE site_chat_messages SET reply_message = :reply_message, status = :status, updated_at = NOW() WHERE id = :id')->execute([
            ':reply_message' => $reply,
            ':status' => 'replied',
            ':id' => $chatId,
        ]);
    }
    header('Location: sofonyas2.php?lang=' . $lang);
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?php echo safe($lang); ?>">
<head>
    <?php echo renderSeoMeta(['title' => $siteName . ' - ' . translateText('ድር ገፅ', 'Home'), 'description' => translateText('የሶፎንያስ የመማሪያ እና ኮሚዩኒቲ ዌብሳይት', 'Sofoniyas learning and community website')]); ?>
    <title><?php echo safe($siteName); ?></title>
    <meta name="description" content="<?php echo safe(translateText('የሶፎንያስ የመማሪያ እና ኮሚዩኒቲ ዌብሳይት', 'Sofoniyas learning and community website')); ?>">
    <meta name="keywords" content="Sofoniyas, learning, church, community, education, Ethiopia">
    <meta name="theme-color" content="#0f172a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="manifest.json">
    <link rel="icon" href="icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="icon.svg">
    <link rel="mask-icon" href="icon.svg" color="#0f172a">
    <link rel="stylesheet" href="sofonyas (1).css">
    <link rel="sitemap" type="application/xml" href="sitemap.xml">
    <style>
        :root { color-scheme: light; scroll-behavior: smooth; }
        html { min-height: 100%; scroll-behavior: smooth; }
        body { min-height: 100%; background: radial-gradient(circle at top left, rgba(124,58,237,0.18), transparent 22%), radial-gradient(circle at bottom right, rgba(59,130,246,0.12), transparent 18%), linear-gradient(135deg, #f8fbff 0%, #eef2ff 100%); color: #0f172a; }
        body::before { content: ''; position: fixed; inset: 0; background: radial-gradient(circle at 25% 20%, rgba(99,102,241,0.12), transparent 16%), radial-gradient(circle at 80% 10%, rgba(236,72,153,0.1), transparent 14%), radial-gradient(circle at 50% 90%, rgba(14,165,233,0.08), transparent 16%); pointer-events: none; z-index: 0; }
        nav { position: sticky; top: 0; z-index: 20; background: rgba(248,251,255,0.78); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(148,163,184,0.18); }
        nav ul { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: center; margin: 0; padding: 14px 18px; list-style: none; }
        nav a { color: #0f172a; text-decoration: none; font-weight: 600; transition: color 0.2s ease, transform 0.2s ease; }
        nav a:hover { color: #4338ca; transform: translateY(-1px); }
        .card { background: rgba(216, 11, 124, 0.88); border: 1px solid rgba(236, 13, 191, 0.42); border-radius: 28px; box-shadow: 0 34px 80px rgba(15,23,42,0.1); padding: 26px; backdrop-filter: blur(18px); }
        .card h2 { margin-top: 0; }
        .quick-card { background: rgba(229, 11, 175, 0.9); border: 1px solid rgba(148,163,184,0.18); border-radius: 24px; padding: 24px; box-shadow: 0 24px 50px rgba(15,23,42,0.08); transition: transform 0.25s ease, box-shadow 0.25s ease; }
        .quick-card:hover { transform: translateY(-4px); box-shadow: 0 28px 60px rgba(15,23,42,0.12); }
        .button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: linear-gradient(135deg, #f10eb1, #4f46e5); color: white; text-decoration: none; padding: 14px 22px; border-radius: 999px; font-weight: 800; letter-spacing: 0.01em; box-shadow: 0 16px 40px rgba(37,99,235,0.22); transition: transform 0.24s ease, box-shadow 0.24s ease, filter 0.24s ease; }
        .button:hover { transform: translateY(-2px); filter: brightness(1.05); }
        .toast { position: fixed; right: 20px; top: 20px; max-width: 360px; padding: 14px 16px; border-radius: 12px; color: #fff; box-shadow: 0 16px 35px rgba(15,23,42,0.2); z-index: 9999; opacity: 0; transform: translateY(-8px); pointer-events: none; transition: all 0.3s ease; }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast-success { background: linear-gradient(135deg, #16a34a, #15803d); }
        .toast-error { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .toast-info { background: linear-gradient(135deg, #2563eb, #4f46e5); }
        .hero-section { position: relative; overflow: hidden; border-radius: 28px; min-height: 760px; margin: 24px 0 28px; background: linear-gradient(135deg, rgba(15,23,42,0.86), rgba(30,41,59,0.76)); box-shadow: 0 25px 45px rgba(15,23,42,0.18); }
        .hero-video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; object-position: center; opacity: 1; filter: none; }
        .hero-overlay { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(2,6,23,0.24), rgba(15,23,42,0.24)); }
        .hero-content { position: relative; z-index: 2; display: grid; grid-template-columns: minmax(320px, 1.1fr) minmax(320px, 0.9fr); gap: 32px; padding: 60px 42px; align-items: center; }
        .hero-copy { color: #fff; max-width: 660px; }
        .hero-copy h1 { font-size: clamp(2.4rem, 4vw, 3.8rem); line-height: 1.02; margin-bottom: 18px; text-shadow: 0 16px 45px rgba(15,23,42,0.3); letter-spacing: -0.03em; }
        .hero-copy p { font-size: 1.05rem; color: rgba(226,232,240,0.96); line-height: 1.75; max-width: 620px; }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 22px; }
        .button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: linear-gradient(135deg, #f10eb1, #4f46e5); color: white; text-decoration: none; padding: 14px 22px; border-radius: 999px; font-weight: 800; letter-spacing: 0.01em; box-shadow: 0 16px 40px rgba(37,99,235,0.22); transition: transform 0.24s ease, box-shadow 0.24s ease, filter 0.24s ease; }
        .button:hover { transform: translateY(-2px); filter: brightness(1.05); }
        .button.secondary { background: linear-gradient(135deg, #38bdf8, #e10bb3); color: white; box-shadow: 0 16px 40px rgba(59,130,246,0.2); border: none; }
        .button.secondary:hover { background: linear-gradient(135deg, #0ea5e9, #0284c7); }
        .hero-stats { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 20px; }
        .hero-stat { background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.22); border-radius: 18px; padding: 12px 14px; font-size: 0.95rem; backdrop-filter: blur(12px); }
        .hero-visual { position: relative; display: flex; align-items: center; justify-content: center; margin: 0 auto 32px; max-width: 660px; }
        /* Slider separation */
        .slider-section { margin-top: 24px; }
        .hero-stats-section { background: linear-gradient(180deg, #09c522 0%, #eef2ff 100%); padding: 18px; border-radius: 16px; margin: 18px 0; }
        .hero-stats-section h2 { margin-bottom: 12px; font-size: clamp(1.2rem, 2.2vw, 1.8rem); text-align: center; color: #0b1220; direction: ltr; }
        .stats-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-top: 8px; }
        .stat-card { background: linear-gradient(135deg, #93c208, #2c049b); border-radius: 12px; padding: 18px; box-shadow: 0 12px 30px rgba(11,18,32,0.06); text-align: center; transition: transform 250ms ease, box-shadow 250ms ease, background 250ms ease; cursor: default; }
        .stat-card:hover { transform: translateY(-8px); box-shadow: 0 26px 60px rgba(11,18,32,0.12); background: linear-gradient(135deg, #a50d96, #eef2ff); }
        .stat-card strong { display:block; font-size: 1.6rem; color: #0b1220; }
        .stat-card span { display:block; margin-top:8px; color:#334155; font-weight:600; }
        /* make stats friendly for Amharic RTL when page lang is am */
        html[lang="am"] .hero-stats-section, html[lang="am"] .stat-card { direction: rtl; text-align: center; }
        /* Card becomes container for stacked slides */
        .hero-card { position: relative; background: rgba(255,255,255,0.78); border: 1px solid rgba(255,255,255,0.24); border-radius: 32px; padding: 24px; box-shadow: 0 40px 90px rgba(15,23,42,0.16); min-height: 540px; max-width: 660px; width: 100%; display: block; backdrop-filter: blur(20px); overflow: hidden; }
        .hero-card::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255,255,255,0.42), rgba(196,181,253,0.08)); pointer-events: none; }
        .hero-card .hero-slide { position: absolute; inset: 0; opacity: 0; transition: opacity 0.9s ease; display: flex; align-items: center; justify-content: center; z-index: 1; }
        .hero-slide.active { opacity: 1; z-index: 2; }
        .hero-slide img { width: 100%; height: 100%; object-fit: contain; border-radius: 18px; display: block; }
        .hero-slider-dots { display: flex; gap: 8px; justify-content: center; margin-top: 16px; }
        .hero-slider-dots button { width: 10px; height: 10px; border-radius: 999px; border: none; background: #cbd5e1; cursor: pointer; }
        .hero-slider-dots button.active { background: #2563eb; transform: scale(1.2); }
        .floating-card { position: absolute; right: -18px; bottom: -18px; background: linear-gradient(135deg, #0f172a, #334155); color: white; border-radius: 18px; padding: 14px 16px; max-width: 240px; box-shadow: 0 18px 30px rgba(15,23,42,0.22); animation: floatUp 3.2s ease-in-out infinite; }
        .floating-card strong { display:block; margin-bottom:6px; }
        .card { background: rgba(255,255,255,0.9); border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px; box-shadow: 0 12px 30px rgba(15,23,42,0.06); margin-bottom: 22px; }
        .contact-form { background: linear-gradient(135deg, rgba(247,250,255,0.92), rgba(226,232,255,0.82)); border: 1px solid rgba(148,163,184,0.26); padding: 36px; border-radius: 32px; box-shadow: 0 42px 90px rgba(15,23,42,0.14); max-width: 760px; margin: 0 auto; }
        .contact-form h3 { margin-top: 0; margin-bottom: 24px; color: #0f172a; font-size: clamp(2rem, 2.6vw, 2.7rem); text-align: center; letter-spacing: 0.02em; background: rgba(59,130,246,0.12); display: inline-block; padding: 14px 24px; border-radius: 20px; box-shadow: 0 14px 30px rgba(59,130,246,0.12); }
        .contact-form form { display: grid; gap: 20px; }
        .contact-form .form-field { display: grid; gap: 8px; }
        .contact-form label { font-weight: 700; color: #0f172a; }
        .contact-form input,
        .contact-form textarea { width: 100%; min-height: 54px; padding: 18px 20px; border: 1px solid #cbd5e1; border-radius: 18px; background: #f7fbff; color: #0f172a; font-size: 1rem; transition: all 0.2s ease; }
        .contact-form input::placeholder,
        .contact-form textarea::placeholder { color: #475569; opacity: 1; font-size: 1.1rem; font-weight: 500; }
        .contact-form textarea { min-height: 140px; resize: vertical; }
        .contact-form input:focus,
        .contact-form textarea:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37,99,235,0.16); background: #ffffff; }
        .contact-form .controls { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; align-items: center; margin-top: 6px; }
        .contact-form .button { min-width: 140px; }
        .contact-form .small { font-size: 0.95rem; color: #475569; text-align: center; }
        .contact-form .button:hover { transform: translateY(-1px); }
        @media (max-width: 980px) { .hero-content { grid-template-columns: 1fr; padding: 48px 24px; } .hero-visual { max-width: 100%; margin-bottom: 24px; } .hero-stats { flex-direction: column; } }
        @media (max-width: 760px) { .contact-form { padding: 22px; } }

        .reveal { opacity: 0; transform: translateY(24px); transition: opacity 0.8s ease, transform 0.8s ease; }
        .reveal.visible { opacity: 1; transform: translateY(0); }
        .course-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-top: 16px; }
        .course-card { background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 10px 20px rgba(15,23,42,0.05); transition: transform 0.25s ease, box-shadow 0.25s ease; }
        .course-card:hover { transform: translateY(-4px) scale(1.02); box-shadow: 0 16px 32px rgba(15,23,42,0.12); }
        .course-card img { width: 100%; height: 75%; object-fit: cover; transition: transform 0.35s ease; }
        .course-card:hover img { transform: scale(1.08); }
        .course-card .content { padding: 12px 14px; }
        .testimonial-slider { position: relative; overflow: hidden; margin-top: 16px; }
        .testimonial-track { display: flex; transition: transform 0.45s ease; }
        .testimonial-card { min-width: 100%; background: linear-gradient(135deg, #ffffff, #f8fafc); border-radius: 18px; border: 1px solid #e2e8f0; padding: 18px; box-shadow: 0 8px 18px rgba(15,23,42,0.05); }
        .testimonial-nav { display:flex; justify-content:center; gap:10px; margin-top:12px; }
        .testimonial-nav button { border:none; width: 36px; height: 36px; border-radius: 999px; background:#e2e8f0; color:#0f172a; cursor:pointer; transition: transform 0.24s ease, background 0.24s ease; }
        .testimonial-nav button.active { background:#2563eb; color:white; transform: scale(1.05); }
        .slider-frame { position: relative; overflow: hidden; border-radius: 26px; margin-top: 18px; }
        .slider-track { display: flex; transition: transform 0.5s ease; }
        .slide { min-width: 100%; opacity: 0; transition: opacity 0.45s ease; position: absolute; inset: 0; }
        .slide.active { opacity: 1; position: relative; }
        .slider-frame img { width: 100%; height: auto; display: block; border-radius: 22px; }
        .button .loading { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.6); border-top-color: rgba(255,255,255,1); border-radius: 50%; animation: spin 0.9s linear infinite; margin-right: 8px; vertical-align: middle; }
        .button:disabled { opacity: 0.88; cursor: not-allowed; }
        .zoomable-img { transition: transform 0.3s ease; }
        .zoomable-img:hover { transform: scale(1.04); }
        @keyframes fadeIn { from { opacity:0; transform: translateY(8px); } to { opacity:1; transform: translateY(0); } }
        @keyframes floatUp { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        @media (max-width: 900px) { .hero-content { grid-template-columns: 1fr; padding: 28px 20px; } .hero-section { min-height: 860px; } .hero-card { min-height: auto; width: 100%; transform: translateY(0); } .hero-slide { min-height: 360px; } .floating-card { position: relative; right:auto; bottom:auto; margin-top:12px; } }
    </style>
</head>
<body>
    <div class="page-loader" id="pageLoader" aria-hidden="true">
        <div class="loader-ring"></div>
    </div>

    <nav>
        <ul>
            <li><a href="https://t.me/+Bqeu85XkOu4yMzFk" data-am="tele_ join" data-en="Home">Home</a></li>
            <li><a href="#about" data-am="about" data-en="About">About</a></li>
            <li><a href="student_login.php" data-am="Student Login" data-en="Student Center">exam center</a></li>
            <li><a href="#view" data-am="view" data-en="View">View</a></li>
            <li><a href="contact.html" data-am="contact" data-en="Contact">Contact</a></li>
             <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="exam20.php">exam portal</a></li>
            <li><a href="tutorial.php">Courses</a></li>
            <li><a href="discussion_forum.php">Forum</a></li>
            <li><a href="library.php">Library</a></li>
            <li><a href="admin_login.php">Admin Login</a></li>
            <li><a href="student_register.php">Register</a></li>
        </ul>
    </nav>

    <form class="lang-switch" role="group" aria-label="Language switcher" method="post" style="display:flex; gap:8px; align-items:center;">
        <input type="hidden" name="set_lang" value="am">
        <button class="lang-btn <?php echo $lang === 'am' ? 'active' : ''; ?>" type="submit" data-lang="am">አማርኛ</button>
    </form>
    <form class="lang-switch" role="group" aria-label="Language switcher" method="post" style="display:flex; gap:8px; align-items:center;">
        <input type="hidden" name="set_lang" value="en">
        <button class="lang-btn <?php echo $lang === 'en' ? 'active' : ''; ?>" type="submit" data-lang="en">English</button>
    </form>

    <header class="hero-section">
        <video class="hero-video" autoplay muted loop playsinline poster="10 .jpg">
            <source src="sofi website hero section video.mp4" type="video/mp4">
        </video>
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <div class="hero-copy">
                <div id="logo">
                    <img src="<?php echo safe($siteLogoUrl); ?>" alt="<?php echo safe($siteName); ?>" class="logo-img zoomable-img">
                </div>
                <h1 class="animate-title reveal" data-am="እንኳን ወደ ዲ/ን ሶፎንያስ ደመቀ ቤተ ገብርኤል ዌብሳይት በደህና መጡ!" data-en="Welcome to Dr. Sofoniyas Demeke's Church Community Website!">እንኳን ወደ ዲ/ን ሶፎንያስ ደመቀ ቤተ ገብርኤል ዌብሳይት በደህና መጡ!</h1>
                <p class="animate-subtitle reveal" data-am="ይህ የመማሪያ እና የእውቀት መንገድ በጥሩ እና በቀላሉ የሚያገኙ መረጃዎች ተዘጋጅቷል።" data-en="This learning and knowledge platform is prepared with clear, easy-to-access information for everyone.">ይህ የመማሪያ እና የእውቀት መንገድ በጥሩ እና በቀላሉ የሚያገኙ መረጃዎች ተዘጋጅቷል።</p>
                <div class="hero-actions reveal">
                    <a class="button" href="student_login.php" data-am="የተማሪ ግባ" data-en="Student Access">Student Access</a>
                    <a class="button secondary" href="#about" data-am="ተጨማሪ ይወቁ" data-en="Learn More">Learn More</a>
                    <button id="installAppBtn" class="button secondary" style="display:none;" type="button">Install App</button>
                    <button id="enableNotificationsBtn" class="button secondary" style="display:none;" type="button">Enable Notifications</button>
                </div>
            </div>
        </div>
    </header>

    <section class="card reveal hero-stats-section" aria-label="LMS stats">
        <h2 data-am="የቤተ ገብርኤል አጠቃላይ ዕይታ" data-en="Professional LMS Features">Professional LMS Features</h2>
        <section class="stats-grid" aria-label="Detailed stats">
            <article class="stat-card"><strong>500+</strong><span>የተመዘገቡ ተማሪዎች</span></article>
            <article class="stat-card"><strong>25</strong><span>የተለያዩ ኮርሶች</span></article>
            <article class="stat-card"><strong>12</strong><span>የተማሪ ዳሽቦርዶች</span></article>
            <article class="stat-card"><strong>100%</strong><span>የእምነት እና የትምህርት ማዕከል</span></article>
        </section>
    </section>

    <?php
    // List uploaded contact files for admins/viewing on the site
    $contactUploadsDir = __DIR__ . '/uploads/contacts/';
    $contactFiles = [];
    if (is_dir($contactUploadsDir)) {
        foreach (new DirectoryIterator($contactUploadsDir) as $fileInfo) {
            if ($fileInfo->isFile()) {
                $contactFiles[] = [
                    'name' => $fileInfo->getFilename(),
                    'mtime' => $fileInfo->getMTime(),
                    'size' => $fileInfo->getSize(),
                ];
            }
        }
        usort($contactFiles, function($a, $b) { return $b['mtime'] - $a['mtime']; });
    }
    ?>

    <section class="card reveal" aria-label="Contact submissions" style="margin-top:22px;">
        <h2><?php echo safe(translateText('የእርዳታ ፋይሎች', 'Contact Submissions')); ?></h2>
        <?php if (empty($contactFiles)): ?>
            <p style="color:#475569;"><?php echo safe(translateText('አሁን ምንም ፋይል አልተሰበሰበም።', 'No files uploaded yet.')); ?></p>
        <?php else: ?>
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:12px; margin-top:12px;">
                <?php foreach ($contactFiles as $f): ?>
                    <div style="background:#fff; border-radius:12px; padding:12px; border:1px solid #e2e8f0; box-shadow:0 8px 18px rgba(15,23,42,0.04);">
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                            <strong style="font-size:0.98rem; color:#0f172a;"><?php echo safe($f['name']); ?></strong>
                            <a href="uploads/contacts/<?php echo rawurlencode($f['name']); ?>" download style="color:#4338ca; text-decoration:none; font-weight:700;"><?php echo safe(translateText('Download', 'Download')); ?></a>
                        </div>
                        <div style="margin-top:6px; color:#64748b; font-size:0.9rem;">
                            <?php echo date('Y-m-d H:i', $f['mtime']); ?> • <?php echo number_format($f['size'] / 1024, 1); ?> KB
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card reveal hero-visual slider-section" aria-label="Hero image slider">
        <div class="hero-card">
            <div class="hero-slide active">
                <img src="10 .jpg" alt="Community preview">
            </div>
            <div class="hero-slide">
                <img src="yesofi 3 photo.jpg" alt="Community preview">
            </div>
            <div class="hero-slide">
                <img src="sofi 3 photo.jpg" alt="Community preview">
            </div>
            <div class="hero-slide">
                <img src="yesofi 1 photo.jpg" alt="Community preview">
            </div>
            <div class="hero-slide">
                <img src="sofi photo.jpg" alt="Sofoniyas community photo">
            </div>
            <div class="hero-slide">
                <img src="yesofi 2 photo.jpg" alt="Community preview">
            </div>
            <div class="hero-slide">
                <img src="sofi logo.jpg" alt="Community preview">
            </div>
            <div class="hero-slide">
                <img src="motta sofi.jpg" alt="Students learning">
            </div>
            <div class="hero-slide">
                <img src="sofi2.jpg" alt="Community preview">
            </div>
            <div class="hero-slider-dots" id="heroSliderDots"></div>
        </div>
    </section>

    <section class="card reveal slider-section" aria-label="Featured gallery">
        <h2 data-am="የተመረጡ ምስሎች" data-en="Featured Images">Featured Images</h2>
        <div class="slider-frame">
            <div class="slider-track">
                <div class="slide active"><img src="10 .jpg" alt="Community preview"></div>
                <div class="slide"><img src="sofi 3 photo.jpg" alt="sofonyas bete gebriel"></div>
                <div class="slide"><img src="motta sofi.jpg" alt="Students learning"></div>
                <div class="slide"><img src="yesofi 1 photo.jpg" alt="Community preview"></div>
            </div>
            <div class="slider-dots" role="tablist" aria-label="Image slider controls"></div>
        </div>
    </section>

    <div class="card reveal" id="about">
        <h2 data-am="ስለ ቤተ ገብርኤል" data-en="About the Community">ስለ ቤተ ገብርኤል</h2>
        <p data-am="ቤተ ገብርኤል በመቅደላ አምባ ዩኒቨርሲቲ በፈለገ ሰላም አዲስ አምባ ግቢ ጉባኤ የቤተሰብ እናት አባት አደረጃጀት ውስጥ አንዱና ተናፋቂው ቡድን ነው፣ይህ ድር ገፅ በእውነተኛ ባክኤንድ ገፆች ላይ የተመሰረተ ነው። ኮርስ መመዝገብ፣ ተማሪ እና አስተዳዳሪ ዳሽቦርድ፣ ፎርም ቀጥታ መግባት እና ማስታወቂያ በአንድ አጠቃቀም ይሰራሉ።" data-en="The community is a vibrant and active group within the family structure of the church, dedicated to spiritual growth and shared service.">ቤተ ገብርኤል በመቅደላ አምባ ዩኒቨርሲቲ በፈለገ ሰላም አዲስ አምባ ግቢ ጉባኤ የቤተሰብ እናት አባት አደረጃጀት ውስጥ አንዱና ተናፋቂው ቡድን ነው</p>
    </div>
<section class="card reveal" aria-label="Quick Start">
        <h2>አጭር ማሰስ / Quick Start</h2>
        <div class="quick-grid">
            <article class="quick-card"><h3>ተማሪ</h3><p>ኮርስ መመዝገብ እና ዳሽቦርድ ለመከታተል።</p><a class="button" href="student_login.php">ወደ ተማሪ መግቢያ</a> </article>
            <article class="quick-card"><h3>አስተዳዳሪ</h3><p>ኮርስ፣ ጥያቄዎች እና አስተዳደር ፍርግርግ ለመቆጣጠር።</p><a class="button secondary" href="admin_login.php">ወደ አስተዳዳሪ</a></article>
            <article class="quick-card"><h3>ኮርሶች</h3><p>የትምህርት እና የPDF መረጃዎችን ለመመልከት።</p><a class="button" href="tutorial.php">ወደ ኮርሶች</a></article>
            <article class="quick-card"><h3>ፎርም</h3><p>ለተማሪዎች እና ለአስተዳዳሪዎች ማስታወቂያ እና ክፍል ለመጠየቅ።</p><a class="button" href="discussion_forum.php">ወደ ፎርም</a></article>
        </div>
    </section>
    <div class="card reveal">
        <h2 data-am="በዚህ ዌቭሳይት የሚካተቱ ትምህርቶች" data-en="Courses Included on This Website">በዚህ ዌቭሳይት የሚካተቱ ትምህርቶች</h2>
        <div class="course-grid">
            <div class="course-card">
                <img src="10 .jpg" alt="Religious teachings">
                <div class="content">
                    <h3 data-am="ነገረ ሃይማኖት" data-en="Religious teachings">ነገረ ሃይማኖት</h3>
                    <p data-am="የሃይማኖት ትምህርት እና ማብራሪያ" data-en="Faith-based teachings and guidance">Faith-based teachings and guidance</p>
                </div>
            </div>
            <div class="course-card">
                <img src="sofi photo.jpg" alt="Church order">
                <div class="content">
                    <h3 data-am="ስርዓተ ቤተ ክርስቲያን" data-en="Church order">ስርዓተ ቤተ ክርስቲያን</h3>
                    <p data-am="በአስተዳደር እና ሥነ ምግባር ላይ ትምህርት" data-en="Structured guidance on church order and ethics">Structured guidance on church order and ethics</p>
                </div>
            </div>
            <div class="course-card">
                <img src="motta sofi.jpg" alt="The message of anointing">
                <div class="content">
                    <h3 data-am="ነገረ ቅባት" data-en="The message of anointing">ነገረ ቅባት</h3>
                    <p data-am="ጥልቅ የትምህርት እና ልምድ እድል" data-en="Deep learning experiences and practical insight">Deep learning experiences and practical insight</p>
                </div>
            </div>
        </div>
    </div>

    <section class="card reveal" style="background:linear-gradient(135deg,#f8fbff 0%,#eef2ff 100%); border:1px solid #dbeafe;">
        <h2 data-am="የLMS ደረጃ ባህሪያት" data-en="Professional LMS Features">Professional LMS Features</h2>
        <div style="display:grid; gap:12px; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); margin-top:12px;">
            <div style="background:#fff; border-radius:12px; padding:14px; box-shadow:0 8px 20px rgba(15,23,42,0.05);">
                <strong data-am="ጨዋታ ማበረታቻ" data-en="Gamification">Gamification</strong>
                <p style="margin:6px 0 0; color:#475569;" data-am="እንቅስቃሴዎችን ለመከታተል እና ለማበረታታት ተግባራዊ ስርዓት" data-en="Track progress and motivate learner engagement with a structured reward system.">Track progress and motivate learner engagement with a structured reward system.</p>
            </div>
            <div style="background:#fff; border-radius:12px; padding:14px; box-shadow:0 8px 20px rgba(15,23,42,0.05);">
                <strong data-am="የመማሪያ መንገድ" data-en="Learning Path">Learning Path</strong>
                <p style="margin:6px 0 0; color:#475569;" data-am="ተማሪዎች በአቅጣጫ የተዘጋጀ የመማር ፍሰት ውስጥ እንዲሄዱ ያስችላል" data-en="Guide learners through a clear and structured study journey.">Guide learners through a clear and structured study journey.</p>
            </div>
            <div style="background:#fff; border-radius:12px; padding:14px; box-shadow:0 8px 20px rgba(15,23,42,0.05);">
                <strong data-am="የሰርቲፊኬት ስርዓት" data-en="Certificate System">Certificate System</strong>
                <ul style="margin:8px 0 0 16px; padding:0; color:#475569; line-height:1.6;">
                    <li data-am="አውቶ ሰርቲፊኬት ማመንጨት" data-en="Auto Certificate Generation">Auto Certificate Generation</li>
                    <li data-am="PDF ሰርቲፊኬት" data-en="PDF Certificate">PDF Certificate</li>
                    <li data-am="QR ማረጋገጫ" data-en="QR Verification">QR Verification</li>
                    <li data-am="የሰርቲፊኬት ቁጥር" data-en="Certificate Number">Certificate Number</li>
                    <li data-am="የሰርቲፊኬት ውርድ" data-en="Certificate Download">Certificate Download</li>
                    <li data-am="የሰርቲፊኬት ማረጋገጫ ገፅ" data-en="Certificate Validation Page">Certificate Validation Page</li>
                </ul>
            </div>
            <div style="background:#fff; border-radius:12px; padding:14px; box-shadow:0 8px 20px rgba(15,23,42,0.05);">
                <strong data-am="አለም አቀፍ ፍለጋ" data-en="Global Search">Global Search</strong>
                <p style="margin:6px 0 0; color:#475569;" data-am="በአጭሩ የእርስዎን መረጃ የሚያገኙበት የፍለጋ ስርዓት" data-en="Provide a fast search experience so learners can instantly find the information they need.">Provide a fast search experience so learners can instantly find the information they need.</p>
            </div>
            <div style="background:#fff; border-radius:12px; padding:14px; box-shadow:0 8px 20px rgba(15,23,42,0.05);">
                <strong data-am="የሞባይል አፕ ድጋፍ" data-en="Mobile App Support">Mobile App Support</strong>
                <ul style="margin:8px 0 0 16px; padding:0; color:#475569; line-height:1.6;">
                    <li data-am="PWA – እንደ አፕ ማጫን ይቻላል" data-en="PWA – install as an app">PWA – install as an app</li>
                    <li data-am="Android App – ለAndroid ተመራጭ ተግባር" data-en="Android App – optimized for Android">Android App – optimized for Android</li>
                    <li data-am="iOS App – ለiPhone እና iPad ተመራጭ" data-en="iOS App – designed for iPhone and iPad">iOS App – designed for iPhone and iPad</li>
                    <li data-am="Offline Learning – ያለ በይነመረብ በቀላሉ ማጥናት" data-en="Offline Learning – continue studying without internet">Offline Learning – continue studying without internet</li>
                </ul>
            </div>
            <div style="background:#fff; border-radius:12px; padding:14px; box-shadow:0 8px 20px rgba(15,23,42,0.05);">
                <strong data-am="የማሳወቂያ ስርዓት" data-en="Notification System">Notification System</strong>
                <ul style="margin:8px 0 0 16px; padding:0; color:#475569; line-height:1.6;">
                    <li data-am="ኢሜይል ማሳወቂያዎች" data-en="Email Notifications">Email Notifications</li>
                    <li data-am="SMS ማሳወቂያዎች" data-en="SMS Notifications">SMS Notifications</li>
                    <li data-am="Push ማሳወቂያዎች" data-en="Push Notifications">Push Notifications</li>
                    <li data-am="አዲስ ኮርስ ሲጨመር የሚላከው ማሳወቂያ" data-en="New Course Alert">New Course Alert</li>
                    <li data-am="አዲስ ፈተና ማስጠንቀቂያ" data-en="New Exam Alert">New Exam Alert</li>
                    <li data-am="ሰርቲፊኬት እድር ማሳወቂያ" data-en="Certificate Ready">Certificate Ready</li>
                    <li data-am="የሥራ ማስጠንቀቂያ" data-en="Assignment Reminder">Assignment Reminder</li>
                </ul>
            </div>
            <div style="background:#fff; border-radius:12px; padding:14px; box-shadow:0 8px 20px rgba(15,23,42,0.05);">
                <strong data-am="ኢሜይል ማርኬቲንግ" data-en="Email Marketing">Email Marketing</strong>
                <ul style="margin:8px 0 0 16px; padding:0; color:#475569; line-height:1.6;">
                    <li data-am="የዜና ጋዜጣ ማስታወቂያ" data-en="Newsletter">Newsletter</li>
                    <li data-am="የኮርስ ማስታወቂያዎች" data-en="Course Promotions">Course Promotions</li>
                    <li data-am="የተማሪ ዝግጅቶች" data-en="Student Updates">Student Updates</li>
                </ul>
            </div>
            <div style="background:#fff; border-radius:12px; padding:14px; box-shadow:0 8px 20px rgba(15,23,42,0.05);">
                <strong data-am="ተዋናይ ቪዲዮ" data-en="Interactive Video">Interactive Video</strong>
                <p style="margin:6px 0 0; color:#475569;" data-am="ቪዲዮ መካከል Quiz ይወጣል" data-en="Video-based quizzes appear during playback.">Video-based quizzes appear during playback.</p>
            </div>
            <div style="background:#fff; border-radius:12px; padding:14px; box-shadow:0 8px 20px rgba(15,23,42,0.05);">
                <strong data-am="የማድረጊያ ቪዲዮ ትምህርቶች" data-en="Screen Recording Lessons">Screen Recording Lessons</strong>
                <p style="margin:6px 0 0; color:#475569;" data-am="መምህራን በዌብሳይቱ ውስጥ ቀጥታ ቪዲዮ እንዲቀዱ" data-en="Instructors can record lessons directly on the website.">Instructors can record lessons directly on the website.</p>
            </div>
            <div style="background:#fff; border-radius:12px; padding:14px; box-shadow:0 8px 20px rgba(15,23,42,0.05);">
                <strong data-am="ምዘና ማስገባት" data-en="Assignment Submission">Assignment Submission</strong>
                <ul style="margin:8px 0 0 16px; padding:0; color:#475569; line-height:1.6;">
                    <li data-am="ፋይል ማስገባት" data-en="File Upload">File Upload</li>
                    <li data-am="PDF ማስገባት" data-en="PDF Submission">PDF Submission</li>
                    <li data-am="የክፍያ ስርዓት" data-en="Grading System">Grading System</li>
                </ul>
            </div>
            <div style="background:#fff; border-radius:12px; padding:14px; box-shadow:0 8px 20px rgba(15,23,42,0.05);">
                <strong data-am="የክፍያ ስርዓት" data-en="Payment System">Payment System</strong>
                <ul style="margin:8px 0 0 16px; padding:0; color:#475569; line-height:1.6;">
                    <li data-am="Telebirr" data-en="Telebirr">Telebirr</li>
                    <li data-am="CBE Birr" data-en="CBE Birr">CBE Birr</li>
                    <li data-am="Chapa" data-en="Chapa">Chapa</li>
                    <li data-am="ArifPay" data-en="ArifPay">ArifPay</li>
                    <li data-am="የክፍያ ማረጋገጫ" data-en="Payment Verification">Payment Verification</li>
                    <li data-am="የክፍያ መጠየቂያ / ኢንቮይስ" data-en="Invoice Generation">Invoice Generation</li>
                    <li data-am="የግብይት ታሪክ" data-en="Transaction History">Transaction History</li>
                </ul>
            </div>
            <div style="background:#fff; border-radius:12px; padding:14px; box-shadow:0 8px 20px rgba(15,23,42,0.05);">
                <strong data-am="ሳምንታዊ እንቅስቃሴ ሪፖርት" data-en="Weekly Activity Reports">Weekly Activity Reports</strong>
                <p style="margin:6px 0 0; color:#475569;" data-am="ተማሪዎች እና አስተዳዳሪዎች ሳምንታዊ ተመሳሳይ መረጃ እንዲያገኙ ያስችላል" data-en="Help students and administrators stay informed with weekly engagement reports.">Help students and administrators stay informed with weekly engagement reports.</p>
            </div>
        </div>
    </section>

    <section class="card reveal">
        <h2 data-am="ተማሪዎች ምን ይላሉ" data-en="What students say">ተማሪዎች ምን ይላሉ</h2>
        <div class="testimonial-slider">
            <div class="testimonial-track" id="testimonialTrack">
                <div class="testimonial-card">
                    <p>“"ይህ የመማሪያ መድረክ ለመጠቀም ቀላል፣ ተማሪዎችን የሚያነሳሳ እና በተግባር የሚጠቅሙ የትምህርት ይዘቶችን የያዘ ነው።”</p>
                    <strong> ማናየ(ዘደ/ማርቆስ), ተማሪ</strong>
                </div>
                  <div class="testimonial-card">
                    <p>“"በዌብሳይት መንፈሳዊ ትምህርት መማር ሕይወትን እና ጊዜን በአግባቡ መጠቀም እና ቴክኖሎጅን አሳላጭ ማድረግ የቤተ ክርስቲያን ሁለንተናዊ እድገት ነው።”</p>
                    <strong> M.r ወ/መርቆርዮስ(ዘሞጣ), መምህር</strong>
                </div>
                  <div class="testimonial-card">
                    <p>“ይህ ድህረ ገጽ በጣም በቀላሉ ለመረዳት የሚችል እና የእምነት ትምህርትን በጥልቅ የሚያስተምር ነው። ዲያቆን ሶፎንያስ ደመቀ የሚያቀርበው ትምህርት በግልጽ እና በፍቅር የተሞላ ነው። በቤተ ክርስቲያን እውቀት ለመጨመር በጣም ጥሩ መንገድ ነው።”</p>
                    <strong>ማስተዋል(ዘጎንደር), ተማሪ</strong>
                    </div>
                <div class="testimonial-card">
                    <p>“በዚህ በቤተ ገብርኤል የሚያቀርበው ትምህርት ዘመናዊ እና በጥሩ ስርዓት የተደረገ ነው። በቤት ቁጭ ብዬ እንኳ እምነትን እና ቤተ ክርስቲያንን በጥልቅ መማር ቻልኩ። እጅግ አመሰግናለሁ።”</p>
                    <strong>ክብር ተመስገን(ዘሞጣ), የማኅበር አባል</strong>
                    </div>
                     <div class="testimonial-card">
                    <p>“በዚህ ኦንላይን ትምህርት መድረክ መማር ህይወቴን ቀይሮታል። ትምህርቶቹ ቀላል ናቸው፣ እና የኢትዮጵያ ኦርቶዶክስ እምነትን በትክክል እንድማር ረድቶኛል። በተለይ የዲያቆን ሶፎንያስ መግለጫ በጣም ግልጽ ነው።”</p>
                    <strong>ትእግስት(ዘቢቸና), ተማሪ</strong>
                </div>
                <div class="testimonial-card">
                    <p>“የኮርሱ አቀራረብ ዘመናዊ፣ በጥሩ ሁኔታ የተደራጀ እና ለመረዳት ቀላል ነው። እንዲሁም በራሴ ጊዜና ፍጥነት መማር እችላለሁ።.”</p>
                    <strong>ፋሲካ(መክሊት), ተማሪ</strong>
                </div>
                <div class="testimonial-card">
                    <p>“እመብርሃንን ይዞ ተስፋ የሰነቀ፣ዘላለም ይኖራል ፍቅሯን እያወቀ፣እኛም እንድንድን ሶፎንያስ ደመቀ፣ሕይወትን እንድናይ ይህን አሳወቀ”</p>
                    <strong>MR አዱኛው, ተማሪ ፕሬዘዳንት</strong>
                </div>
                <div class="testimonial-card">
                    <p>“በጉጉት የምንጠብቀው ዌብሳይት አልቆ በማየቴ ደስታየን እየገለጽኩ እግዚአብሔር አምላክ በዲ/ን ሶፎንያስ ላይ አድሮ እያስተማረን ነውና እኛም ተምረን ተለውጠን እግዚአብሔርን የምናመሰግንበት ስለሃይማኖታችን በጥልቁ የምናዉቅበት እንዲሆን ከልብ አመኛለው!!”</p>
                    <strong>እታገኘሁ, ተማሪ</strong>
                </div>
                <div class="testimonial-card">
                    <p>“አህዛብ መናፍቅ ሲከበን በተራ፣እውነት ለመሸርሸር ሲወጡ ሲወርዱ የእምነት ባላጋራ፣ስህተት ሲገነቡ የውሸት ተራራ፣ደረሰልን ሶፊ ዌብሳይቱን ሰርቶ መንጥሮ እያጣራ፣ሁሉም  በመሰለው ሲያስተምር እንዳሻው፣መጣባቸው ሶፊ የቅባት መዶሻው፣የለም ብለው ነበር ከኛ በላይ ሰው፣ለስለስ ብሎ ገብቶ እውነቱን አወጣው”</p>
                    <strong>ፍቅረ ግርማ, ተማሪ</strong>
                </div>
            </div>
            <div class="testimonial-nav" id="testimonialNav"></div>
        </div>
    </section>

    <section class="card contact-form reveal">
        <h3 data-am="እንኳን ወደ ቤተ ገብርኤል በደህና መጡ!" data-en="Welcome to the Community!">እንኳን ወደ ቤተ ገብርኤል በደህና መጡ!</h3>
        <form id="contactForm" action="register.php" method="post" novalidate>
            <div class="form-field">
                <label for="name" data-am="ስም" data-en="Name">ስም</label>
                <input id="name" name="name" required placeholder="ስምዎን እዚህ ያስገቡ">
            </div>
            <div class="form-field">
                <label for="email" data-am="ኢሜይል" data-en="Email">ኢሜይል</label>
                <input id="email" name="email" type="email" required placeholder="እባክዎ ኢሜይሎን ያስገቡ">
            </div>
            <div class="form-field">
                <label for="student_id" data-am="የተማሪ መለያ ቁጥር" data-en="Student ID">የተማሪ መለያ ቁጥር</label>
                <input type="text" id="student_id" name="student_id" placeholder="እባክዎ መለያ ቁጥር ያስገቡ" required>
            </div>
            <div class="form-field">
                <label for="password" data-am="ፓስወርድ" data-en="Password">ፓስወርድ</label>
                <input id="password" name="password" type="password" placeholder="ፓስወርድ ያስገቡ" required>
            </div>
            <div class="controls">
                <button type="submit" class="button" data-am="ግባ" data-en="Register"><span class="loading" aria-hidden="true"></span>ግባ</button>
                <button type="reset" class="button secondary" data-am="አጥፋ" data-en="Clear">አጥፋ</button>
            </div>
        </form>
        <div id="formMsg" class="small" aria-live="polite"></div>
    </section>

    <div class="card reveal">
        <h2 data-am="እባክዎ ያነጋግሩኝ" data-en="Please contact me">እባክዎ ያነጋግሩኝ</h2>
        <p><?php echo safe($contactEmail); ?></p>
    </div>

    <section class="card reveal" aria-label="Live chat" style="background: linear-gradient(135deg, #f8fbff 0%, #f5f3ff 100%); border:1px solid #dbeafe;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
            <div>
                <h3 style="margin:0; color:#1d4ed8;" data-am="የጣቢያ ቻት" data-en="Professional Support Chat">Professional Support Chat</h3>
                <p style="margin:4px 0 0; color:#475569;" data-am="እርስዎ ለኮርሶች፣ ፈተናዎች እና የተማሪ ጥያቄዎች በአጭር ጊዜ ይጠይቃሉ።" data-en="Ask questions about courses, exams, or student support and receive guided help quickly.">Ask questions about courses, exams, or student support and receive guided help quickly.</p>
            </div>
            <span style="display:inline-block; padding:6px 10px; border-radius:999px; background:#dbeafe; color:#1d4ed8; font-weight:700; font-size:12px;">● Live</span>
        </div>

        <form method="post" style="display:grid; gap:10px; margin-top:10px;">
            <input type="text" name="chat_name" placeholder="<?php echo safe(translateText('ስምዎ', 'Your name')); ?>" style="padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px;">
            <textarea name="chat_message" rows="3" placeholder="<?php echo safe(translateText('በአጭሩ ጥያቄዎን ይጻፉ...', 'Write your question briefly...')); ?>" required style="padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; min-height:90px;"></textarea>
            <button type="submit" class="button" style="width:fit-content; border:none;"><?php echo safe(translateText('ላክ', 'Send')); ?></button>
        </form>

        <div style="margin-top:16px; display:flex; flex-direction:column; gap:10px;">
            <?php foreach ($chatMessages as $message): ?>
                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:12px 14px; box-shadow:0 8px 18px rgba(15,23,42,0.04);">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;">
                        <strong style="color:#1e3a8a;"><?php echo safe($message['sender_name']); ?></strong>
                        <span style="font-size:12px; color:#64748b;"><?php echo safe($message['created_at']); ?></span>
                    </div>
                    <div style="color:#334155; margin-top:6px; line-height:1.55;"><?php echo safe($message['message']); ?></div>
                    <?php if (!empty($message['reply_message'])): ?>
                        <div style="margin-top:10px; padding:10px 12px; border-left:3px solid #8b5cf6; background:#f5f3ff; border-radius:8px; color:#5b21b6;">
                            <strong><?php echo safe(translateText('የአስተዳዳሪ መልስ', 'Admin reply')); ?>:</strong> <?php echo safe($message['reply_message']); ?>
                        </div>
                    <?php else: ?>
                        <div style="margin-top:8px; font-size:12px; color:#7c3aed; font-weight:700;"><?php echo safe(translateText('በመጠበቅ ላይ', 'Pending response')); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div id="welcomeToast" class="toast toast-success" role="status" aria-live="polite">
        <strong><?php echo safe(translateText('እንኳን ደህና መጡ!', 'Welcome!')); ?></strong>
        <div style="margin-top:4px; font-size:14px;"><?php echo safe(translateText('ይህ ዌብሳይት ለእርስዎ በቀላሉ እና በጥሩ ቅርጸት መረጃ ይሰጣል።', 'This website provides clear and easy-to-follow information for you.')); ?></div>
    </div>

    <footer style="background:#0f172a; color:#e2e8f0; padding:28px 20px 36px; margin-top:28px;">
        <div style="max-width:1100px; margin:0 auto; display:grid; gap:24px; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); align-items:start;">
            <div>
                <h3 style="margin:0 0 10px; color:#fff;" data-am="ስለ እኛ" data-en="About Us">About Us</h3>
                <p style="margin:4px 0 14px; max-width:320px; color:#cbd5e1;" data-am="በቤተ ክርስቲያን እና በመመሪያ ከፍተኛ ጥናት የተመሰረተ ዌብሳይት ነው።" data-en="A church-centered learning platform built for trusted knowledge and community growth.">A church-centered learning platform built for trusted knowledge and community growth.</p>
                <a href="sofonyas2.php#about" style="color:#93c5fd; text-decoration:none; font-weight:600;" data-am="ይህን ይጎብኙ" data-en="Visit About">Visit About</a>
            </div>
            <div>
                <h3 style="margin:0 0 10px; color:#fff;" data-am="ኮርሶች" data-en="Courses">Courses</h3>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <a href="tutorial.php" style="color:#bfdbfe; text-decoration:none;" data-am="የቅድመ ትምህርቶች" data-en="Featured Courses">Featured Courses</a>
                    <a href="course_details.php" style="color:#bfdbfe; text-decoration:none;" data-am="ኮርስ ዝርዝር" data-en="Course Catalog">Course Catalog</a>
                    <a href="student_register.php" style="color:#bfdbfe; text-decoration:none;" data-am="ከእኛ ጋር ይቀላቀሉ" data-en="Join Now">Join Now</a>
                </div>
            </div>
            <div>
                <h3 style="margin:0 0 10px; color:#fff;" data-am="ሰርቲፊኬቶች" data-en="Certificates">Certificates</h3>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <a href="certificate_verification.php" style="color:#bfdbfe; text-decoration:none;" data-am="የሰርቲፊኬት የማረጋገጫ ገፅ" data-en="Certificate Validation">Certificate Validation</a>
                    <a href="admin_certificate.php" style="color:#bfdbfe; text-decoration:none;" data-am="የሰርቲፊኬት አስተዳደር" data-en="Certificate Management">Certificate Management</a>
                    <a href="download_center.php" style="color:#bfdbfe; text-decoration:none;" data-am="የሰርቲፊኬት ውርድ" data-en="Download Certificates">Download Certificates</a>
                </div>
            </div>
            <div>
                <h3 style="margin:0 0 10px; color:#fff;" data-am="ህጋዊ ፈቃዶች" data-en="Legal">Legal</h3>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <a href="terms.php" style="color:#bfdbfe; text-decoration:none;" data-am="ውሎችና ውሎች" data-en="Terms & Conditions">Terms & Conditions</a>
                    <a href="privacy.php" style="color:#bfdbfe; text-decoration:none;" data-am="የግላዊነት ፖሊሲ" data-en="Privacy Policy">Privacy Policy</a>
                </div>
                <div style="margin-top:16px;">
                    <h4 style="margin:0 0 10px; color:#fff;" data-am="ማህበራዊ መረብ" data-en="Social Links">Social Links</h4>
                    <div style="display:flex; flex-wrap:wrap; gap:10px;">
                        <a href="https://t.me/sophonyasbetmichael" style="color:#bfdbfe; text-decoration:none;" data-am="ቴሌግራም" data-en="Telegram">Telegram</a>
                        <a href="https://t.me/sophonyasbetmichael" style="color:#bfdbfe; text-decoration:none;" data-am="ፌስቡክ" data-en="Facebook">Facebook</a>
                        <a href="https://www.instagram.com/" style="color:#bfdbfe; text-decoration:none;" data-am="Instagram" data-en="Instagram">Instagram</a>
                    </div>
                </div>
            </div>
        </div>
        <div style="margin-top:26px; border-top:1px solid rgba(255,255,255,0.12); padding-top:18px; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:14px; color:#94a3b8; font-size:0.95rem;">
            <span data-am="© 2026 ሶፊ ፕሮጀክት. መብቶች የተጠበቁ" data-en="© 2026 Sofi Project. All rights reserved.">© 2026 Sofi Project. All rights reserved.</span>
            <div style="display:flex; flex-wrap:wrap; gap:12px;">
                <a href="about.php" style="color:#94a3b8; text-decoration:none;" data-am="ስለ እኛ" data-en="About Us">About Us</a>
                <a href="tutorial.php" style="color:#94a3b8; text-decoration:none;" data-am="ኮርሶች" data-en="Courses">Courses</a>
                <a href="certificate_verification.php" style="color:#94a3b8; text-decoration:none;" data-am="ሰርቲፊኬቶች" data-en="Certificates">Certificates</a>
                <a href="privacy.php" style="color:#94a3b8; text-decoration:none;" data-am="የግላዊነት ፖሊሲ" data-en="Privacy Policy">Privacy Policy</a>
                <a href="terms.php" style="color:#94a3b8; text-decoration:none;" data-am="ውሎችና ውሎች" data-en="Terms & Conditions">Terms & Conditions</a>
            </div>
        </div>
    </footer>

    <script>
        const langButtons = document.querySelectorAll('.lang-btn');
        const welcomeToast = document.getElementById('welcomeToast');

        function showToast(message, type = 'info', timeout = 4200) {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `<div>${message}</div>`;
            document.body.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 50);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 250);
            }, timeout);
        }
        const translatable = document.querySelectorAll('[data-am][data-en]');
        const loader = document.getElementById('pageLoader');
        const form = document.getElementById('contactForm');
        const sliderImages = Array.from(document.querySelectorAll('.slider-frame .slide'));
        const dotsContainer = document.querySelector('.slider-dots');
        const heroSlides = Array.from(document.querySelectorAll('.hero-slide'));
        const heroDotsContainer = document.getElementById('heroSliderDots');
        const testimonialTrack = document.getElementById('testimonialTrack');
        const testimonialNav = document.getElementById('testimonialNav');
        const installBtn = document.getElementById('installAppBtn');
        const enableNotificationsBtn = document.getElementById('enableNotificationsBtn');
        const vapidPublicKey = 'BDm7zLpbTZE-Ldhfe687Iqs6Ne9jEJcvGS76zSqbYbsbrUA73orumEo-DxBFhYGyskD7MueWvPr6KZU3RwxPiA4';
        let deferredPrompt = null;
        let currentSlide = 0;
        let heroSlideIndex = 0;
        let testimonialIndex = 0;

        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }

        function bufferToBase64Url(buffer) {
            const bytes = new Uint8Array(buffer);
            let str = '';
            bytes.forEach((byte) => {
                str += String.fromCharCode(byte);
            });
            return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
        }

        function makeSubscriptionPayload(subscription) {
            const raw = subscription && typeof subscription.toJSON === 'function' ? subscription.toJSON() : subscription;
            const keys = raw.keys || {};
            const p256dh = typeof keys.p256dh === 'string' && keys.p256dh !== ''
                ? keys.p256dh
                : bufferToBase64Url(subscription.getKey('p256dh'));
            const auth = typeof keys.auth === 'string' && keys.auth !== ''
                ? keys.auth
                : bufferToBase64Url(subscription.getKey('auth'));

            return {
                endpoint: raw.endpoint,
                expirationTime: raw.expirationTime || null,
                keys: {
                    p256dh,
                    auth
                }
            };
        }

        async function subscribeToPush() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                showToast('Push notifications are not supported by this browser.', 'error');
                return;
            }

            const registration = await navigator.serviceWorker.ready;
            let subscription = await registration.pushManager.getSubscription();
            if (!subscription) {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    showToast('Notification permission denied. Please allow notifications to enable updates.', 'error');
                    return;
                }
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
                });
            }

            const payload = makeSubscriptionPayload(subscription);
            const response = await fetch('push_subscribe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            if (result.success) {
                showToast('Push notifications are enabled for this app.', 'success');
                if (enableNotificationsBtn) {
                    enableNotificationsBtn.textContent = 'Notifications Enabled';
                    enableNotificationsBtn.disabled = true;
                }
            } else {
                showToast(result.message || 'Unable to save push subscription.', 'error');
            }
        }

        function updateNotificationButton() {
            if (!enableNotificationsBtn) return;
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                enableNotificationsBtn.style.display = 'none';
                return;
            }
            enableNotificationsBtn.style.display = 'inline-flex';
            enableNotificationsBtn.addEventListener('click', subscribeToPush);
        }

        function applyLanguage(lang) {
            document.documentElement.lang = lang;
            document.body.setAttribute('data-lang', lang);
            translatable.forEach((element) => {
                const text = element.getAttribute(lang === 'en' ? 'data-en' : 'data-am');
                if (text) {
                    element.textContent = text;
                }
            });
            langButtons.forEach((button) => {
                button.classList.toggle('active', button.getAttribute('data-lang') === lang);
            });
        }

        function showSlide(index) {
            sliderImages.forEach((slide, slideIndex) => {
                slide.classList.toggle('active', slideIndex === index);
            });
            Array.from(dotsContainer.children).forEach((dot, dotIndex) => {
                dot.classList.toggle('active', dotIndex === index);
            });
        }

        function startSlider() {
            if (!sliderImages.length || !dotsContainer) return;
            sliderImages.forEach((_, index) => {
                const dot = document.createElement('button');
                dot.className = 'slider-dot' + (index === 0 ? ' active' : '');
                dot.type = 'button';
                dot.setAttribute('aria-label', `Show slide ${index + 1}`);
                dot.addEventListener('click', () => {
                    currentSlide = index;
                    showSlide(currentSlide);
                });
                dotsContainer.appendChild(dot);
            });
            setInterval(() => {
                currentSlide = (currentSlide + 1) % sliderImages.length;
                showSlide(currentSlide);
            }, 5000);
        }

        function startHeroSlider() {
            if (!heroSlides.length || !heroDotsContainer) return;
            heroSlides.forEach((_, index) => {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = index === 0 ? 'active' : '';
                dot.addEventListener('click', () => {
                    heroSlideIndex = index;
                    heroSlides.forEach((slide, slideIndex) => slide.classList.toggle('active', slideIndex === heroSlideIndex));
                    Array.from(heroDotsContainer.children).forEach((button, buttonIndex) => button.classList.toggle('active', buttonIndex === heroSlideIndex));
                });
                heroDotsContainer.appendChild(dot);
            });
            setInterval(() => {
                heroSlideIndex = (heroSlideIndex - 1 + heroSlides.length) % heroSlides.length;
                heroSlides.forEach((slide, slideIndex) => slide.classList.toggle('active', slideIndex === heroSlideIndex));
                Array.from(heroDotsContainer.children).forEach((button, buttonIndex) => button.classList.toggle('active', buttonIndex === heroSlideIndex));
            }, 3000);
        }

        function startTestimonialSlider() {
            if (!testimonialTrack || !testimonialNav) return;
            const testimonials = Array.from(testimonialTrack.children);
            testimonials.forEach((_, index) => {
                const navButton = document.createElement('button');
                navButton.type = 'button';
                navButton.className = index === 0 ? 'active' : '';
                navButton.addEventListener('click', () => {
                    testimonialIndex = index;
                    testimonialTrack.style.transform = `translateX(-${testimonialIndex * 100}%)`;
                    Array.from(testimonialNav.children).forEach((button, buttonIndex) => button.classList.toggle('active', buttonIndex === testimonialIndex));
                });
                testimonialNav.appendChild(navButton);
            });
            setInterval(() => {
                testimonialIndex = (testimonialIndex + 1) % testimonials.length;
                testimonialTrack.style.transform = `translateX(-${testimonialIndex * 100}%)`;
                Array.from(testimonialNav.children).forEach((button, buttonIndex) => button.classList.toggle('active', buttonIndex === testimonialIndex));
            }, 6000);
        }

        function revealOnScroll() {
            document.querySelectorAll('.reveal').forEach((element) => {
                const rect = element.getBoundingClientRect();
                if (rect.top < window.innerHeight - 80) {
                    element.classList.add('visible');
                }
            });
        }

        langButtons.forEach((button) => {
            button.addEventListener('click', () => applyLanguage(button.getAttribute('data-lang')));
        });

        if (welcomeToast) {
            setTimeout(() => {
                welcomeToast.classList.add('show');
                setTimeout(() => {
                    welcomeToast.classList.remove('show');
                }, 3800);
            }, 250);
        }

        if (form) {
            form.addEventListener('submit', () => {
                const submitBtn = form.querySelector('button[type="submit"]');
                const spinner = form.querySelector('.loading');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="loading" aria-hidden="true"></span>Loading...';
                }
                if (spinner) {
                    spinner.style.display = 'inline-block';
                }
            });
        }

        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            deferredPrompt = event;
            if (installBtn) {
                installBtn.style.display = 'inline-flex';
            }
        });

        if (installBtn) {
            installBtn.addEventListener('click', async () => {
                if (!deferredPrompt) {
                    return;
                }
                deferredPrompt.prompt();
                const choice = await deferredPrompt.userChoice;
                deferredPrompt = null;
                installBtn.style.display = 'none';
                if (choice.outcome === 'accepted') {
                    showToast('App installed successfully!', 'success');
                }
            });
        }

        window.addEventListener('appinstalled', () => {
            showToast('App installed. Thank you!', 'success');
            if (installBtn) {
                installBtn.style.display = 'none';
            }
        });

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('service-worker.js').catch(() => {});
            });
        }

        window.addEventListener('load', () => {
            loader.classList.add('hidden');
            setTimeout(() => loader.remove(), 400);
            applyLanguage('am');
            updateNotificationButton();
            startSlider();
            startHeroSlider();
            startTestimonialSlider();
            revealOnScroll();
        });

        window.addEventListener('scroll', revealOnScroll);
        window.addEventListener('resize', revealOnScroll);
    </script>
    <script>
    (function(){
        const form = document.getElementById('siteContactForm');
        const feedback = document.getElementById('siteContactFeedback');
        if (!form) return;
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (feedback) { feedback.style.display = 'block'; feedback.textContent = ''; }
            const fd = new FormData(form);
            const lines = [];
            for (const pair of fd.entries()) {
                const k = pair[0];
                const v = pair[1];
                if (k === 'attachment') {
                    lines.push(`${k} : ${v && v.name ? v.name : '(no file)'}`);
                } else {
                    lines.push(`${k} : ${v}`);
                }
            }
            if (feedback) feedback.textContent = lines.join('\n');
            const btn = form.querySelector('button[type="submit"]');
            if (btn) { btn.disabled = true; btn.textContent = 'Sending...'; }
            try {
                const resp = await fetch(form.action, { method: 'POST', body: fd });
                const json = await resp.json();
                if (feedback) feedback.textContent += '\n\nServer: ' + (json.message || JSON.stringify(json));
                if (json.success) form.reset();
            } catch (err) {
                if (feedback) feedback.textContent += '\n\nRequest failed: ' + err.message;
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = 'መልእክት ላክ'; }
            }
        });
    })();
    </script>
</body>
</html>
