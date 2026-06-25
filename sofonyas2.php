<?php
session_start();
require_once __DIR__ . '/db.php';

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
    <?php echo renderSeoMeta(['title' => translateText('የሶፎንያስ ድር ገፅ', 'Sofoniyas Website Home'), 'description' => translateText('የሶፎንያስ የመማሪያ እና ኮሚዩኒቲ ዌብሳይት', 'Sofoniyas learning and community website')]); ?>
    <title><?php echo safe(translateText('የሶፎንያስ ድር ገፅ', 'Sofoniyas Website Home')); ?></title>
    <link rel="stylesheet" href="sofonyas (1).css">
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
            <source src="https://www.w3schools.com/howto/rain.mp4" type="video/mp4">
        </video>
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <div id="logo">
                <img src="10 .jpg" alt="sofi" class="logo-img zoomable-img">
            </div>
            <h1 class="animate-title reveal" data-am="እንኳን ወደ ዲ/ን ሶፎንያስ ደመቀ ቤተ ገብርኤል ዌብሳይት በደህና መጡ!" data-en="Welcome to Dr. Sofoniyas Demeke's Church Community Website!">እንኳን ወደ ዲ/ን ሶፎንያስ ደመቀ ቤተ ገብርኤል ዌብሳይት በደህና መጡ!</h1>
            <p class="animate-subtitle reveal" data-am="ይህ የመማሪያ እና የእውቀት መንገድ በጥሩ እና በቀላሉ የሚያገኙ መረጃዎች ተዘጋጅቷል።" data-en="This learning and knowledge platform is prepared with clear, easy-to-access information for everyone.">ይህ የመማሪያ እና የእውቀት መንገድ በጥሩ እና በቀላሉ የሚያገኙ መረጃዎች ተዘጋጅቷል።</p>
            <div class="hero-actions reveal">
                <a class="button" href="student_login.php" data-am="የተማሪ ግባ" data-en="Student Access">Student Access</a>
                <a class="button secondary" href="#about" data-am="ተጨማሪ ይወቁ" data-en="Learn More">Learn More</a>
            </div>
        </div>
    </header>

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
        <p data-am="ኢሜይል: yilkaldemeke21@gmail.com" data-en="Email: yilkaldemeke21@gmail.com">Email: yilkaldemeke21@gmail.com</p>
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

    <footer>
        <p><b data-am="ይህ ዌቭሳይት የተሰራው በዲ/ን ሶፎንያስ ደመቀ (ወ/ጊዮርጊስ) ነው።" data-en="This website was created by Dr. Sofoniyas Demeke (W/Georgis).">ይህ ዌቭሳይት የተሰራው በዲ/ን ሶፎንያስ ደመቀ (ወ/ጊዮርጊስ) ነው።</b></p>
    </footer>

    <script>
        const langButtons = document.querySelectorAll('.lang-btn');
        const translatable = document.querySelectorAll('[data-am][data-en]');
        const loader = document.getElementById('pageLoader');
        const form = document.getElementById('contactForm');
        const sliderImages = Array.from(document.querySelectorAll('.slider-frame .slide'));
        const dotsContainer = document.querySelector('.slider-dots');
        let currentSlide = 0;

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

        window.addEventListener('load', () => {
            loader.classList.add('hidden');
            setTimeout(() => loader.remove(), 400);
            applyLanguage('am');
            startSlider();
            revealOnScroll();
        });

        window.addEventListener('scroll', revealOnScroll);
        window.addEventListener('resize', revealOnScroll);
    </script>
</body>
</html>
