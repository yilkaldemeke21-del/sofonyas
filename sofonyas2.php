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
    <link rel="manifest" href="manifest.json">
    <link rel="icon" href="icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="icon.svg">
    <link rel="stylesheet" href="sofonyas (1).css">
    <link rel="sitemap" type="application/xml" href="sitemap.xml">
    <style>
        :root { color-scheme: light; }
        body { background: linear-gradient(135deg, #f8fbff 0%, #eef2ff 100%); color: #0f172a; }
        .toast { position: fixed; right: 20px; top: 20px; max-width: 360px; padding: 14px 16px; border-radius: 12px; color: #fff; box-shadow: 0 16px 35px rgba(15,23,42,0.2); z-index: 9999; opacity: 0; transform: translateY(-8px); pointer-events: none; transition: all 0.3s ease; }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast-success { background: linear-gradient(135deg, #16a34a, #15803d); }
        .toast-error { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .toast-info { background: linear-gradient(135deg, #2563eb, #4f46e5); }
        .hero-section { position: relative; overflow: hidden; border-radius: 28px; min-height: 760px; margin: 24px 0 28px; background: linear-gradient(135deg, rgba(15,23,42,0.86), rgba(30,41,59,0.76)); box-shadow: 0 25px 45px rgba(15,23,42,0.18); }
        .hero-video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: object-fit: contain; opacity: 0.4; filter: brightness(0.72) saturate(1.08); }
        .hero-overlay { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(2,6,23,0.24), rgba(15,23,42,0.24)); }
        .hero-content { position: relative; z-index: 2; display: grid; grid-template-columns: minmax(320px, 1.1fr) minmax(320px, 0.9fr); gap: 32px; padding: 60px 42px; align-items: center; }
        .hero-copy { color: #fff; max-width: 660px; }
        .hero-copy h1 { font-size: clamp(2.2rem, 4vw, 3.6rem); line-height: 1.05; margin-bottom: 16px; }
        .hero-copy p { font-size: 1.05rem; color: #e2e8f0; line-height: 1.75; }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 22px; }
        .button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: linear-gradient(135deg, #2563eb, #4f46e5); color: white; text-decoration: none; padding: 12px 18px; border-radius: 999px; font-weight: 700; box-shadow: 0 12px 24px rgba(37,99,235,0.25); }
        .button.secondary { background: rgba(255,255,255,0.16); box-shadow: none; border: 1px solid rgba(255,255,255,0.2); }
        .hero-stats { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 20px; }
        .hero-stat { background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.18); border-radius: 14px; padding: 10px 12px; font-size: 0.95rem; backdrop-filter: blur(10px); }
        .hero-visual { position: relative; display: flex; align-items: center; justify-content: center; margin: 0 auto 32px; max-width: 660px; }
        /* Slider separation */
        .slider-section { margin-top: 24px; }
        /* Card becomes container for stacked slides */
        .hero-card { position: relative; background: rgba(255,255,255,0.98); border-radius: 32px; padding: 18px; box-shadow: 0 28px 60px rgba(15,23,42,0.18); min-height: 520px; max-width: 660px; width: 100%; display: block; backdrop-filter: blur(16px); overflow: hidden; }
        /* Stack slides absolutely and crossfade via opacity */
        .hero-slide { position: absolute; inset: 0; opacity: 0; transition: opacity 0.8s ease; display: flex; align-items: center; justify-content: center; z-index: 1; }
        .hero-slide.active { opacity: 1; z-index: 2; }
        .hero-slide img { width: 100%; height: 100%; object-fit: contain; border-radius: 18px; display: block; }
        .hero-slider-dots { display: flex; gap: 8px; justify-content: center; margin-top: 16px; }
        .hero-slider-dots button { width: 10px; height: 10px; border-radius: 999px; border: none; background: #cbd5e1; cursor: pointer; }
        .hero-slider-dots button.active { background: #2563eb; transform: scale(1.2); }
        .floating-card { position: absolute; right: -18px; bottom: -18px; background: linear-gradient(135deg, #0f172a, #334155); color: white; border-radius: 18px; padding: 14px 16px; max-width: 240px; box-shadow: 0 18px 30px rgba(15,23,42,0.22); animation: floatUp 3.2s ease-in-out infinite; }
        .floating-card strong { display:block; margin-bottom:6px; }
        .card { background: rgba(255,255,255,0.9); border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px; box-shadow: 0 12px 30px rgba(15,23,42,0.06); margin-bottom: 22px; }
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
        .testimonial-nav button { border:none; width: 36px; height: 36px; border-radius: 999px; background:#e2e8f0; color:#0f172a; cursor:pointer; }
        .testimonial-nav button.active { background:#2563eb; color:white; }
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
            <li><a href="https://t.me/+Bqeu85XkOu4yMzFk" data-am="መነሻ" data-en="Home">Home</a></li>
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
                </div>
                <div class="hero-stats reveal">
                    <div class="hero-stat">📚 100+ ሌሰኖች</div>
                    <div class="hero-stat">🎯 ኦንላይን ፈተናዎች</div>
                    <div class="hero-stat">🏅 ሰርተፊኬት</div>
                </div>
            </div>
        </div>
    </header>

    <section class="card reveal hero-visual slider-section" aria-label="Hero image slider">
        <div class="hero-card">
            <div class="hero-slide active">
                <img src="10 .jpg" alt="Community preview">
            </div>
            <div class="hero-slide active">
                <img src="IMG_20241202_031425_251.jpg" alt="Community preview">
            </div>
            <div class="hero-slide">
                <img src="sofi photo.jpg" alt="Sofoniyas community photo">
            </div>
            <div class="hero-slide active">
                <img src="sofi logo.jpg" alt="Community preview">
            </div>
            <div class="hero-slide">
                <img src="motta sofi.jpg" alt="Students learning">
            </div>
            <div class="hero-slide active">
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
                <div class="slide"><img src="sofi photo.jpg" alt="sofonyas bete gebriel"></div>
                <div class="slide"><img src="motta sofi.jpg" alt="Students learning"></div>
                <div class="slide"><img src="sofi2.jpg" alt="Community preview"></div>
            </div>
            <div class="slider-dots" role="tablist" aria-label="Image slider controls"></div>
        </div>
    </section>

    <div class="card reveal" id="about">
        <h2 data-am="ስለ ቤተ ገብርኤል" data-en="About the Community">ስለ ቤተ ገብርኤል</h2>
        <p data-am="ቤተ ገብርኤል በመቅደላ አምባ ዩኒቨርሲቲ በፈለገ ሰላም አዲስ አምባ ግቢ ጉባኤ የቤተሰብ እናት አባት አደረጃጀት ውስጥ አንዱና ተናፋቂው ቡድን ነው" data-en="The community is a vibrant and active group within the family structure of the church, dedicated to spiritual growth and shared service.">ቤተ ገብርኤል በመቅደላ አምባ ዩኒቨርሲቲ በፈለገ ሰላም አዲስ አምባ ግቢ ጉባኤ የቤተሰብ እናት አባት አደረጃጀት ውስጥ አንዱና ተናፋቂው ቡድን ነው</p>
    </div>

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
                <strong data-am="የሰርተፊኬት ማረጋገጫ" data-en="Certificate Verification">Certificate Verification</strong>
                <p style="margin:6px 0 0; color:#475569;" data-am="የተሰጡ ሰርተፊኬቶች በውስጥ በቀላሉ ሊፈተሹ ይችላሉ" data-en="Allow completed certificates to be validated quickly and reliably.">Allow completed certificates to be validated quickly and reliably.</p>
            </div>
            <div style="background:#fff; border-radius:12px; padding:14px; box-shadow:0 8px 20px rgba(15,23,42,0.05);">
                <strong data-am="አለም አቀፍ ፍለጋ" data-en="Global Search">Global Search</strong>
                <p style="margin:6px 0 0; color:#475569;" data-am="በአጭሩ የእርስዎን መረጃ የሚያገኙበት የፍለጋ ስርዓት" data-en="Provide a fast search experience so learners can instantly find the information they need.">Provide a fast search experience so learners can instantly find the information they need.</p>
            </div>
            <div style="background:#fff; border-radius:12px; padding:14px; box-shadow:0 8px 20px rgba(15,23,42,0.05);">
                <strong data-am="PWA ድጋፍ" data-en="PWA Support">PWA Support</strong>
                <p style="margin:6px 0 0; color:#475569;" data-am="እንደ ሞባይል አፕ የሚጫኑ እና ከመስመር ውጭ የሚሰሩ ባህሪያት" data-en="Support installable mobile app experiences with offline-friendly access.">Support installable mobile app experiences with offline-friendly access.</p>
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
                    <p>“The platform is easy to use, inspiring, and full of practical learning content.”</p>
                    <strong>— Selam, Student</strong>
                </div>
                <div class="testimonial-card">
                    <p>“The course experience feels modern and organized. I can learn at my own pace.”</p>
                    <strong>— Henok, Learner</strong>
                </div>
                <div class="testimonial-card">
                    <p>“I love the live guidance, the beautiful design, and the simple navigation.”</p>
                    <strong>— Meron, Community Member</strong>
                </div>
            </div>
            <div class="testimonial-nav" id="testimonialNav"></div>
        </div>
    </section>

    <section class="card contact-form reveal">
        <h3 data-am="እንኳን ወደ ቤተ ገብርኤል በደህና መጡ!" data-en="Welcome to the Community!">እንኳን ወደ ቤተ ገብርኤል በደህና መጡ!</h3>
        <form id="contactForm" action="register.php" method="post" novalidate>
            <label for="name" data-am="ስም" data-en="Name">ስም</label>
            <input id="name" name="name" required placeholder="ስምዎን እዚህ ያስገቡ"><br><br>

            <label for="email" data-am="ኢሜይል" data-en="Email">ኢሜይል</label>
            <input id="email" name="email" type="email" required placeholder="እባክዎ ኢሜይሎን ያስገቡ"><br><br>

            <label for="student_id" data-am="የተማሪ መለያ ቁጥር" data-en="Student ID">የተማሪ መለያ ቁጥር</label>
            <input type="text" id="student_id" name="student_id" placeholder="እባክዎ መለያ ቁጥር ያስገቡ" required><br><br>

            <label for="password" data-am="ፓስወርድ" data-en="Password">ፓስወርድ</label>
            <input id="password" name="password" type="password" placeholder="ፓስወርድ ያስገቡ" required><br><br>

            <div class="controls"><br>
                <button type="submit" class="button" data-am="ግባ" data-en="Register"><span class="loading" aria-hidden="true"></span>ግባ</button>
                <button type="reset" class="button" data-am="አጥፋ" data-en="Clear">አጥፋ</button>
            </div>
        </form>
        <div id="formMsg" class="small" aria-live="polite" style="margin-top:10px"></div>
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

    <footer style="background:#0f172a; color:#e2e8f0; padding:24px 20px 32px; margin-top:24px;">
        <div style="max-width:1100px; margin:0 auto; display:grid; gap:16px; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); align-items:start;">
            <div>
                <h3 style="margin:0 0 8px; color:#fff;" data-am="የእኛ አድራሻ" data-en="Contact Us">Contact Us</h3>
                <p style="margin:4px 0;">Phone: <?php echo safe($phoneNumber); ?></p>
                <p style="margin:4px 0;"><a href="mailto:<?php echo safe($contactEmail); ?>" style="color:#bfdbfe; text-decoration:none;">Email: <?php echo safe($contactEmail); ?></a></p>
                <p style="margin:4px 0;"><?php echo safe($footerText); ?></p>
            </div>
            <div>
                <h3 style="margin:0 0 8px; color:#fff;" data-am="ማህበራዊ መረብ" data-en="Follow Us">Follow Us</h3>
                <div style="display:flex; flex-wrap:wrap; gap:10px;">
                    <a href="https://t.me/sophonyasbetmichael" style="color:#bfdbfe; text-decoration:none;" data-am="ቴሌግራም" data-en="Telegram">Telegram</a>
                    <a href="https://www.facebook.com/" style="color:#bfdbfe; text-decoration:none;" data-am="ፌስቡክ" data-en="Facebook">Facebook</a>
                    <a href="https://www.instagram.com/" style="color:#bfdbfe; text-decoration:none;" data-am="Instagram" data-en="Instagram">Instagram</a>
                </div>
            </div>
            <div>
                <h3 style="margin:0 0 8px; color:#fff;" data-am="ህጋዊ መረጃ" data-en="Legal">Legal</h3>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <a href="terms.php" style="color:#bfdbfe; text-decoration:none;" data-am="የአገልግሎት ውል" data-en="Terms and Conditions">Terms and Conditions</a>
                    <a href="privacy.php" style="color:#bfdbfe; text-decoration:none;" data-am="የግላዊነት ፖሊሲ" data-en="Privacy Policy">Privacy Policy</a>
                    <a href="cookie_policy.php" style="color:#bfdbfe; text-decoration:none;" data-am="የኩኪ ፖሊሲ" data-en="Cookie Policy">Cookie Policy</a>
                </div>
                <p style="margin-top:12px;"><b data-am="ይህ ዌቭሳይት የተሰራው በዲ/ን ሶፎንያስ ደመቀ (ወ/ጊዮርጊስ) ነው።" data-en="This website was created by Dr. Sofoniyas Demeke (W/Georgis).">ይህ ዌቭሳይት የተሰራው በዲ/ን ሶፎንያስ ደመቀ (ወ/ጊዮርጊስ) ነው።</b></p>
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
        let currentSlide = 0;
        let heroSlideIndex = 0;
        let testimonialIndex = 0;

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

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('service-worker.js').catch(() => {});
            });
        }

        window.addEventListener('load', () => {
            loader.classList.add('hidden');
            setTimeout(() => loader.remove(), 400);
            applyLanguage('am');
            startSlider();
            startHeroSlider();
            startTestimonialSlider();
            revealOnScroll();
        });

        window.addEventListener('scroll', revealOnScroll);
        window.addEventListener('resize', revealOnScroll);
    </script>
</body>
</html>
