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
        .toast { position: fixed; right: 20px; top: 20px; max-width: 360px; padding: 14px 16px; border-radius: 12px; color: #fff; box-shadow: 0 16px 35px rgba(15,23,42,0.2); z-index: 9999; opacity: 0; transform: translateY(-8px); pointer-events: none; transition: all 0.3s ease; }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast-success { background: linear-gradient(135deg, #16a34a, #15803d); }
        .toast-error { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .toast-info { background: linear-gradient(135deg, #2563eb, #4f46e5); }

        .reveal { opacity: 0; transform: translateY(24px); transition: all 0.7s ease; }
        .reveal.visible { opacity: 1; transform: translateY(0); }

        .hero-showcase { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 24px; align-items: center; margin: 24px 0; padding: 24px; background: linear-gradient(135deg, rgba(37,99,235,0.12), rgba(124,58,237,0.12)); border: 1px solid rgba(148,163,184,0.2); border-radius: 24px; overflow: hidden; }
        .hero-copy h2 { font-size: clamp(1.8rem, 3vw, 2.7rem); margin: 10px 0 12px; color: #0f172a; }
        .hero-copy p { color: #475569; line-height: 1.65; font-size: 1rem; }
        .pill { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; background: rgba(37,99,235,0.16); color: #1d4ed8; font-weight: 700; font-size: 0.84rem; }
        .hero-stats { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 18px; }
        .hero-stats > div { background: #fff; padding: 12px 14px; border-radius: 14px; min-width: 120px; box-shadow: 0 12px 24px rgba(15,23,42,0.08); }
        .hero-stats strong { display: block; font-size: 1.2rem; color: #111827; }
        .hero-stats span { color: #64748b; font-size: 0.86rem; }
        .hero-visual { position: relative; display: grid; gap: 16px; }
        .floating-illustration { position: absolute; top: -12px; right: -10px; width: 150px; height: 150px; background: linear-gradient(135deg, #2563eb, #7c3aed); border-radius: 24px; box-shadow: 0 20px 40px rgba(37,99,235,0.24); z-index: 2; animation: float 3.4s ease-in-out infinite; padding: 12px; }
        .floating-illustration .mini-card { background: rgba(255,255,255,0.96); border-radius: 18px; height: 100%; padding: 10px; display: flex; flex-direction: column; justify-content: center; gap: 8px; color: #1e293b; }
        .floating-illustration .mini-card span { display: inline-block; width: 36px; height: 36px; text-align: center; line-height: 36px; border-radius: 50%; background: #dbeafe; color: #1d4ed8; font-weight: 700; }
        .showcase-slider { background: #fff; border-radius: 24px; padding: 12px; box-shadow: 0 20px 35px rgba(15,23,42,0.1); border: 1px solid rgba(226,232,240,0.9); overflow: hidden; }
        .showcase-track { position: relative; height: 280px; overflow: hidden; border-radius: 18px; }
        .showcase-slide { position: absolute; inset: 0; opacity: 0; transform: scale(1.05); transition: all 0.6s ease; }
        .showcase-slide.active { opacity: 1; transform: scale(1); }
        .showcase-slide img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .showcase-controls { display: flex; align-items: center; justify-content: space-between; margin-top: 12px; gap: 10px; }
        .showcase-nav { border: 0; width: 38px; height: 38px; border-radius: 50%; background: #eff6ff; color: #2563eb; font-size: 1.2rem; cursor: pointer; }
        .showcase-dots { display: flex; gap: 6px; }
        .showcase-dot { width: 10px; height: 10px; border-radius: 999px; border: 0; background: #cbd5e1; cursor: pointer; }
        .showcase-dot.active { background: #2563eb; transform: scale(1.18); }

        .section-heading { display: flex; justify-content: space-between; align-items: end; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .section-heading h2 { margin: 0; color: #0f172a; }
        .section-heading p { margin: 0; color: #64748b; }
        .course-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .course-card { border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden; background: #fff; box-shadow: 0 14px 30px rgba(15,23,42,0.06); transition: transform 0.24s ease, box-shadow 0.24s ease; }
        .course-card:hover { transform: translateY(-6px) scale(1.01); box-shadow: 0 18px 34px rgba(15,23,42,0.12); }
        .course-card img { width: 100%; height: 180px; object-fit: cover; transition: transform 0.35s ease; }
        .course-card:hover img { transform: scale(1.08); }
        .course-body { padding: 14px; }
        .course-body h3 { margin: 0 0 8px; color: #111827; }
        .course-body p { color: #64748b; line-height: 1.5; font-size: 0.95rem; }
        .course-link { display: inline-block; margin-top: 8px; color: #2563eb; font-weight: 700; }

        .testimonial-slider { display: grid; grid-template-columns: auto 1fr auto; gap: 12px; align-items: center; }
        .testimonial-nav { border: 0; width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff; cursor: pointer; font-size: 1.2rem; }
        .testimonial-card { display: none; background: linear-gradient(135deg, #f8fbff, #eef2ff); border-radius: 18px; padding: 18px; border: 1px solid #dbeafe; box-shadow: inset 0 1px 0 rgba(255,255,255,0.7); }
        .testimonial-card.active { display: block; }
        .testimonial-card p { margin: 0 0 10px; color: #334155; line-height: 1.7; }
        .testimonial-card strong { color: #111827; }
        .testimonial-dots { display: flex; justify-content: center; gap: 6px; margin-top: 12px; }
        .testimonial-dot { width: 10px; height: 10px; border-radius: 999px; border: 0; background: #cbd5e1; cursor: pointer; }
        .testimonial-dot.active { background: #2563eb; }

        @keyframes float { 0%,100% { transform: translateY(0px); } 50% { transform: translateY(-10px); } }

        @media (max-width: 860px) { .hero-showcase { grid-template-columns: 1fr; } .floating-illustration { display: none; } }
    </style>
</head>
<body>
    <div class="page-loader" id="pageLoader" aria-hidden="true">
        <div class="loader-ring"></div>
    </div>

    <nav>
        <ul>
            <li><a href="https://t.me/+Bqeu85XkOu4yMzFk" data-am="መነሻ" data-en="Home">Home</a></li>
            <li><a href="#about" data-am="ስለ እኛ" data-en="About">About</a></li>
            <li><a href="student_login.php" data-am="የተማሪ ማዕከል" data-en="Student Center">exam center</a></li>
            <li><a href="#view" data-am="እይታ" data-en="View">View</a></li>
            <li><a href="contact.html" data-am="እናግራ" data-en="Contact">Contact</a></li>
             <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="exam20.php">exam portal</a></li>
            <li><a href="tutorial.php">Courses</a></li>
            <li><a href="discussion_forum.php">Forum</a></li>
            <li><a href="library.php">Library</a></li>
            <li><a href="student_login.php">Student Login</a></li>
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
            <source src="sofi website hero section video.mp4">
        </video>
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <div id="logo">
                <img src="<?php echo safe($siteLogoUrl); ?>" alt="<?php echo safe($siteName); ?>" class="logo-img zoomable-img">
            </div>
            <h1 class="animate-title reveal" data-am="እንኳን ወደ ዲ/ን ሶፎንያስ ደመቀ ቤተ ገብርኤል ዌብሳይት በደህና መጡ!" data-en="Welcome to Dr. Sofoniyas Demeke's Church Community Website!">እንኳን ወደ ዲ/ን ሶፎንያስ ደመቀ ቤተ ገብርኤል ዌብሳይት በደህና መጡ!</h1>
            <p class="animate-subtitle reveal" data-am="ይህ የመማሪያ እና የእውቀት መንገድ በጥሩ እና በቀላሉ የሚያገኙ መረጃዎች ተዘጋጅቷል።" data-en="This learning and knowledge platform is prepared with clear, easy-to-access information for everyone.">ይህ የመማሪያ እና የእውቀት መንገድ በጥሩ እና በቀላሉ የሚያገኙ መረጃዎች ተዘጋጅቷል።</p>
            <div class="hero-actions reveal">
                <a class="button" href="student_login.php" data-am="የተማሪ ግባ" data-en="Student Access">Student Access</a>
                <a class="button secondary" href="#about" data-am="ተጨማሪ ይወቁ" data-en="Learn More">Learn More</a>
            </div>
        </div>
    </header>

    <section class="hero-showcase reveal">
        <div class="hero-copy">
            <span class="pill" data-am="🌟 የመስመር ላይ ትምህርት ልምድ" data-en="🌟 Online Learning Experience">🌟 Online Learning Experience</span>
            <h2 data-am="በተመጣጣኝ ድምፅ እና በሚያስደስት አካባቢ ተማሩ" data-en="Learn with confidence through beautiful lessons and guided support.">Learn with confidence through beautiful lessons and guided support.</h2>
            <p data-am="ይህ ዌብሳይት ተማሪዎችን ለትምህርት፣ ፈተና፣ ማስተማር እና ማህበራዊ ትብብር ይደግፋል" data-en="This website brings together inspiring lessons, assessments, guidance, and community interaction in one place.">This website brings together inspiring lessons, assessments, guidance, and community interaction in one place.</p>
            <div class="hero-stats">
                <div>
                    <strong>10k+</strong>
                    <span data-am="ንቁ ተማሪዎች" data-en="Active learners">Active learners</span>
                </div>
                <div>
                    <strong>24/7</strong>
                    <span data-am="ድጋፍ" data-en="Support">Support</span>
                </div>
                <div>
                    <strong>4.9★</strong>
                    <span data-am="የተማሪ ደረጃ" data-en="Student rating">Student rating</span>
                </div>
            </div>
        </div>
        <div class="hero-visual">
            <div class="floating-illustration">
                <div class="mini-card">
                    <span>✓</span>
                    <strong data-am="የተማሪ መምህር መርሃ ግብር" data-en="Student-first learning">Student-first learning</strong>
                    <small data-am="እርስዎን የሚያደርገው የድርጊት ትምህርት" data-en="A smooth path from first lesson to mastery.">A smooth path from first lesson to mastery.</small>
                </div>
            </div>
            <div class="showcase-slider">
                <div class="showcase-track">
                    <div class="showcase-slide active"><img src="10 .jpg" alt="Community preview"></div>
                    <div class="showcase-slide"><img src="sofi photo.jpg" alt="Sofoniyas learning community"></div>
                    <div class="showcase-slide"><img src="motta sofi.jpg" alt="Students learning together"></div>
                    <div class="showcase-slide"><img src="sofi2.jpg" alt="Community event"></div>
                </div>
                <div class="showcase-controls">
                    <button type="button" class="showcase-nav prev" aria-label="Previous slide">‹</button>
                    <div class="showcase-dots" role="tablist" aria-label="Hero slider controls"></div>
                    <button type="button" class="showcase-nav next" aria-label="Next slide">›</button>
                </div>
            </div>
        </div>
    </section>

    <section class="card reveal">
        <div class="section-heading">
            <div>
                <h2 data-am="ታዋቂ ኮርሶች" data-en="Popular Courses">Popular Courses</h2>
                <p data-am="እንደ አዲስ እና እንደ ተሻሻለ መማር ልምድ" data-en="Beautiful visuals and a smooth learning journey.">Beautiful visuals and a smooth learning journey.</p>
            </div>
        </div>
        <div class="course-grid">
            <article class="course-card">
                <img src="sofi photo.jpg" alt="Spiritual Teaching">
                <div class="course-body">
                    <h3 data-am="መንፈሳዊ ትምህርት" data-en="Spiritual Teaching">Spiritual Teaching</h3>
                    <p data-am="የእምነት እና የህይወት ማስተማር በቀላሉ የሚከተሉ" data-en="Faith-filled lessons that are easy to follow and inspiring to revisit.">Faith-filled lessons that are easy to follow and inspiring to revisit.</p>
                    <a href="tutorial.php" class="course-link" data-am="ኮርሱን ይመልከቱ" data-en="Explore course">Explore course</a>
                </div>
            </article>
            <article class="course-card">
                <img src="motta sofi.jpg" alt="Church Order">
                <div class="course-body">
                    <h3 data-am="የቤተ ክርስቲያን ሥርዓት" data-en="Church Order">Church Order</h3>
                    <p data-am="ተማሪዎች በትክክል ያውቃሉ እና በሂደት በጥሩ አስተማር" data-en="Structured lessons that make church principles clear and practical.">Structured lessons that make church principles clear and practical.</p>
                    <a href="tutorial.php" class="course-link" data-am="ኮርሱን ይመልከቱ" data-en="Explore course">Explore course</a>
                </div>
            </article>
            <article class="course-card">
                <img src="sofi2.jpg" alt="Anointing Message">
                <div class="course-body">
                    <h3 data-am="የቅባት መልእክት" data-en="Anointing Message">Anointing Message</h3>
                    <p data-am="መልእክቱን በማንኛውም ሰአት በቀላሉ እንዲከተል የተዘጋጀ" data-en="Designed for reflection, growth, and convenient access whenever you need it.">Designed for reflection, growth, and convenient access whenever you need it.</p>
                    <a href="tutorial.php" class="course-link" data-am="ኮርሱን ይመልከቱ" data-en="Explore course">Explore course</a>
                </div>
            </article>
        </div>
    </section>

    <section class="card reveal">
        <div class="section-heading">
            <div>
                <h2 data-am="ተማሪዎች የሚሉት" data-en="What learners say">What learners say</h2>
                <p data-am="ከተማሪዎቻችን የተሰበሰቡ እውነተኛ አስተያየቶች" data-en="Real feedback from our growing learner community.">Real feedback from our growing learner community.</p>
            </div>
        </div>
        <div class="testimonial-slider">
            <button type="button" class="testimonial-nav prev" aria-label="Previous testimonial">‹</button>
            <div style="width:100%;">
                <div class="testimonial-card active">
                    <p data-am="“በዚህ ዌብሳይት ውስጥ መማር በጣም ቀላል ነው፤ የእኔ ትምህርት በጣም ጠንካራ ሆኗል”" data-en="“Learning here feels simple and inspiring. My study journey has become much more focused.”">“Learning here feels simple and inspiring. My study journey has become much more focused.”</p>
                    <strong data-am="— አለም ሰላም" data-en="— Selam H.">— Selam H.</strong>
                </div>
                <div class="testimonial-card">
                    <p data-am="“እርስዎ የሚሰጡት ድጋፍ እንደ አስተማሪ ልምድ ነው፤ ሁልጊዜ እንደ ተመራማሪ እርዳታ ይሰጣል”" data-en="“The guidance feels personal and motivating, and I always know where to continue next.”">“The guidance feels personal and motivating, and I always know where to continue next.”</p>
                    <strong data-am="— መርበብ ነጋ" data-en="— Meron N.">— Meron N.</strong>
                </div>
                <div class="testimonial-card">
                    <p data-am="“የፈተናዎች እና የፕሮግራሙ ቅርጽ ጥሩ ነው፤ በቀላሉ እድገት አለመታየት ይቻላል”" data-en="“The exam flow and course structure are clear, and progress feels easy to track.”">“The exam flow and course structure are clear, and progress feels easy to track.”</p>
                    <strong data-am="— ለማ ቤተሰብ" data-en="— Lemu B.">— Lemu B.</strong>
                </div>
            </div>
            <button type="button" class="testimonial-nav next" aria-label="Next testimonial">›</button>
        </div>
        <div class="testimonial-dots"></div>
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
        <ul>
            <li data-am="ነገረ ሃይማኖት" data-en="Religious teachings">ነገረ ሃይማኖት</li>
            <li data-am="ስርዓተ ቤተ ክርስቲያን" data-en="Church order">ስርዓተ ቤተ ክርስቲያን</li>
            <li data-am="ነገረ ቅባት" data-en="The message of anointing">ነገረ ቅባት</li>
        </ul>
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
        const showcaseSlides = Array.from(document.querySelectorAll('.showcase-slide'));
        const showcaseDotsContainer = document.querySelector('.showcase-dots');
        const testimonialCards = Array.from(document.querySelectorAll('.testimonial-card'));
        const testimonialDotsContainer = document.querySelector('.testimonial-dots');
        let currentSlide = 0;
        let showcaseIndex = 0;
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

        function showShowcaseSlide(index) {
            if (!showcaseSlides.length) return;
            showcaseSlides.forEach((slide, slideIndex) => {
                slide.classList.toggle('active', slideIndex === index);
            });
            if (showcaseDotsContainer) {
                Array.from(showcaseDotsContainer.children).forEach((dot, dotIndex) => {
                    dot.classList.toggle('active', dotIndex === index);
                });
            }
        }

        function startShowcaseSlider() {
            if (!showcaseSlides.length || !showcaseDotsContainer) return;
            showcaseSlides.forEach((_, index) => {
                const dot = document.createElement('button');
                dot.className = 'showcase-dot' + (index === 0 ? ' active' : '');
                dot.type = 'button';
                dot.setAttribute('aria-label', `Show showcase slide ${index + 1}`);
                dot.addEventListener('click', () => {
                    showcaseIndex = index;
                    showShowcaseSlide(showcaseIndex);
                });
                showcaseDotsContainer.appendChild(dot);
            });
            setInterval(() => {
                showcaseIndex = (showcaseIndex + 1) % showcaseSlides.length;
                showShowcaseSlide(showcaseIndex);
            }, 4500);
        }

        function showTestimonial(index) {
            if (!testimonialCards.length) return;
            testimonialCards.forEach((card, cardIndex) => {
                card.classList.toggle('active', cardIndex === index);
            });
            if (testimonialDotsContainer) {
                Array.from(testimonialDotsContainer.children).forEach((dot, dotIndex) => {
                    dot.classList.toggle('active', dotIndex === index);
                });
            }
        }

        function startTestimonialSlider() {
            if (!testimonialCards.length || !testimonialDotsContainer) return;
            testimonialCards.forEach((_, index) => {
                const dot = document.createElement('button');
                dot.className = 'testimonial-dot' + (index === 0 ? ' active' : '');
                dot.type = 'button';
                dot.setAttribute('aria-label', `Show testimonial ${index + 1}`);
                dot.addEventListener('click', () => {
                    testimonialIndex = index;
                    showTestimonial(testimonialIndex);
                });
                testimonialDotsContainer.appendChild(dot);
            });
            const navButtons = document.querySelectorAll('.testimonial-nav');
            navButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const direction = button.classList.contains('next') ? 1 : -1;
                    testimonialIndex = (testimonialIndex + direction + testimonialCards.length) % testimonialCards.length;
                    showTestimonial(testimonialIndex);
                });
            });
            setInterval(() => {
                testimonialIndex = (testimonialIndex + 1) % testimonialCards.length;
                showTestimonial(testimonialIndex);
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
            startShowcaseSlider();
            startTestimonialSlider();
            revealOnScroll();
        });

        window.addEventListener('scroll', revealOnScroll);
        window.addEventListener('resize', revealOnScroll);
    </script>
</body>
</html>
