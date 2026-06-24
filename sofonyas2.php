<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>የሶፎንያስ ድር ገፅ</title>
    <link rel="stylesheet" href="sofonyas (1).css">
</head>
<body>
    <nav>
        <ul>
            <li><a href="https://t.me/+Bqeu85XkOu4yMzFk" data-am="መነሻ" data-en="Home">Home</a></li>
            <li><a href="#about" data-am="ስለ እኛ" data-en="About">About</a></li>
            <li><a href="student_login.php" data-am="የተማሪ ማዕከል" data-en="Student Center">exam center</a></li>
            <li><a href="#view" data-am="እይታ" data-en="View">View</a></li>
            <li><a href="contact.html" data-am="እናግራ" data-en="Contact">Contact</a></li>
        </ul>
    </nav>

    <div class="lang-switch" role="group" aria-label="Language switcher">
        <button class="lang-btn active" data-lang="am">አማርኛ</button>
        <button class="lang-btn" data-lang="en">English</button>
    </div>

    <header class="hero-section">
        <div id="logo">
            <img src="10 .jpg" alt="sofi" class="logo-img">
        </div>
        <h1 class="animate-title" data-am="እንኳን ወደ ዲ/ን ሶፎንያስ ደመቀ ቤተ ገብርኤል ዌብሳይት በደህና መጡ!" data-en="Welcome to Dr. Sofoniyas Demeke's Church Community Website!">እንኳን ወደ ዲ/ን ሶፎንያስ ደመቀ ቤተ ገብርኤል ዌብሳይት በደህና መጡ!</h1>
        <p class="animate-subtitle" data-am="ይህ የመማሪያ እና የእውቀት መንገድ በጥሩ እና በቀላሉ የሚያገኙ መረጃዎች ተዘጋጅቷል።" data-en="This learning and knowledge platform is prepared with clear, easy-to-access information for everyone.">ይህ የመማሪያ እና የእውቀት መንገድ በጥሩ እና በቀላሉ የሚያገኙ መረጃዎች ተዘጋጅቷል።</p>
    </header>

    <div class="card">
        <h2 data-am="ስለ ቤተ ገብርኤል" data-en="About the Community">ስለ ቤተ ገብርኤል</h2>
        <p data-am="ቤተ ገብርኤል በመቅደላ አምባ ዩኒቨርሲቲ በፈለገ ሰላም አዲስ አምባ ግቢ ጉባኤ የቤተሰብ እናት አባት አደረጃጀት ውስጥ አንዱና ተናፋቂው ቡድን ነው" data-en="The community is a vibrant and active group within the family structure of the church, dedicated to spiritual growth and shared service.">ቤተ ገብርኤል በመቅደላ አምባ ዩኒቨርሲቲ በፈለገ ሰላም አዲስ አምባ ግቢ ጉባኤ የቤተሰብ እናት አባት አደረጃጀት ውስጥ አንዱና ተናፋቂው ቡድን ነው</p>
    </div>

    <div class="card">
        <h2 data-am="በዚህ ዌቭሳይት የሚካተቱ ትምህርቶች" data-en="Courses Included on This Website">በዚህ ዌቭሳይት የሚካተቱ ትምህርቶች</h2>
        <ul>
            <li data-am="ነገረ ሃይማኖት" data-en="Religious teachings">ነገረ ሃይማኖት</li>
            <li data-am="ስርዓተ ቤተ ክርስቲያን" data-en="Church order">ስርዓተ ቤተ ክርስቲያን</li>
            <li data-am="ነገረ ቅባት" data-en="The message of anointing">ነገረ ቅባት</li>
        </ul>
    </div>

    <section class="card contact-form">
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

    <div class="card">
        <h2 data-am="እባክዎ ያነጋግሩኝ" data-en="Please contact me">እባክዎ ያነጋግሩኝ</h2>
        <p data-am="ኢሜይል: yilkaldemeke21@gmail.com" data-en="Email: yilkaldemeke21@gmail.com">Email: yilkaldemeke21@gmail.com</p>
    </div>

    <footer>
        <p><b data-am="ይህ ዌቭሳይት የተሰራው በዲ/ን ሶፎንያስ ደመቀ (ወ/ጊዮርጊስ) ነው።" data-en="This website was created by Dr. Sofoniyas Demeke (W/Georgis).">ይህ ዌቭሳይት የተሰራው በዲ/ን ሶፎንያስ ደመቀ (ወ/ጊዮርጊስ) ነው።</b></p>
    </footer>

    <script>
        const langButtons = document.querySelectorAll('.lang-btn');
        const translatable = document.querySelectorAll('[data-am][data-en]');

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

        langButtons.forEach((button) => {
            button.addEventListener('click', () => applyLanguage(button.getAttribute('data-lang')));
        });

        applyLanguage('am');
    </script>
</body>
</html>
