-- Database backup generated on 2026-06-28 02:54:08 UTC
-- Database: sofonyas_db
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `admin_notes`;
CREATE TABLE `admin_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admin_notes` (`id`, `admin_id`, `title`, `description`, `file_path`, `created_at`) VALUES ('1', '1', 'ሐዋርያዊ ተልዕኮ', '❖.ስብከት የሚለው ቃል \"ሰበከ\" አስተማረ፣ አዋጀ ነገረ ከሚለው የግዕዝ ቃል የተገኘ ነው፡፡በግሪክ፣ኬሪግማ(kerygma) በእንግሊዝኛ sermonl ይባላል፡፡ ተዛማጅ /ተመሳሳይ/ የሆነ ትርጉም አለው፡፡ ማስተማር አዋጅ መንገር የሚለውን ይተረጉማሉ፡፡\r\n↪ የቤተክርስቲያን ዋናው ተልዕኮዋ ሰዎችን.. \r\n ✦ . ከአለማመን ወደ ማመን ማምጣት፣\r\n ✦ . ያመኑትን ደግሞ በእምነታቸው ማጽናት ነው፡፡\r\n \"በስብከት ሞኝነት የሚያምኑትን ሊያድን የእግዚአብሔር በጎ ፈቃድ ሆኗልና\"፩ኛ ቆሮ ፩÷ŧƅ \r\n↪ ስብከት የቤተክርስቲያን ትልቁ የአገልግሎት ክፍል ነው፡፡\r\n✦ .ስብከት መንፈሳዊ ምግብን ለሰዎች መመገብ ማለት ሲሆን ለአንድ ሰው ሥጋዊ ምግብ ለመመገብ ከሚያስፈልገው ዝግጅት በላይ ዝግጅት ያስፈልገዋል።\r\n❖.የቤተ ክርስቲያን ተልዕኮዋ የስብከተ ወንጌል ዓላማ\r\n ✔ ማንጻት(ያላመኑትን ማሳመን)\r\n “ብዙኃን እለ ይቤሉ መኑ ያርእየነ ሠናይቶ”\r\n ✔ .ማጽናት እና\r\n ✔ .መቀደስ ናቸው።\r\n❖.ስብከት\r\n↪ ስለ እግዚአብሔር የምንመሰክርበት ነው።ዮሐ ፩÷፮;፩ኛ ዮሐ ፩÷፩;ማቴ ፲÷ŨƆ\r\n↪ የአምልኮ ክፍል ነው።\r\n -ማምለክ፤ማመንና መዳን መሠረቱ መሰማት ነው።ሮሜ ፲÷፲-ŦƋ\r\n↪ ትእዛዘ እግዚአብሔር ነው።\r\n \"ለሕዝብም እንድንሰብክና በሕያዋንና በሙታን ሊፈርድ በእግዚአብሔር የተወሰነ እርሱ እንደሆነ እንመሰክር ዘንድ አዘዘን\"ሐዋ ፲÷ũƆ\r\n✔ .አስተውሉ:-ጌታችን ኢየሱስ ክርስቶስ ደቀ መዛሙርቱን የጠራበትና የመረጠበት ዓላማና የመጨረሻው ትእዛዝ ስብከት ነው።።ማር ፲፮÷ŦƉ ;', NULL, '2026-06-25 09:48:56');

DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` varchar(30) NOT NULL DEFAULT 'Admin',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admin_users` (`id`, `username`, `password_hash`, `email`, `role`, `created_at`) VALUES ('1', 'sofonyas', '$2y$10$j9GIkMzGzQGi4l8rKSB7n.0GxLLAZI6XN/ZujrQqCO.qf.46lXwqC', 'yilkaldemeke21@gmail.com', 'Admin', '2026-06-23 21:35:00');

DROP TABLE IF EXISTS `assignments`;
CREATE TABLE `assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `due_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `certificates`;
CREATE TABLE `certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(100) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `exam_type` varchar(50) NOT NULL,
  `score` int(11) NOT NULL DEFAULT 0,
  `total_questions` int(11) NOT NULL DEFAULT 0,
  `issued_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_certificate` (`student_id`,`exam_type`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `certificates` (`id`, `student_id`, `student_name`, `exam_type`, `score`, `total_questions`, `issued_at`) VALUES ('1', '027', 'Yilkal Demeke', 'exam20', '7', '30', '2026-06-26 04:51:00');

DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'new',
  `created_at` datetime DEFAULT current_timestamp(),
  `admin_reply` text DEFAULT NULL,
  `replied_at` datetime DEFAULT NULL,
  `replied_by` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_contact_messages_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `course_lessons`;
CREATE TABLE `course_lessons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `module_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `content` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_course_lessons_course` (`course_id`),
  KEY `idx_course_lessons_module` (`module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `course_modules`;
CREATE TABLE `course_modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_course_modules_course` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `course_updates`;
CREATE TABLE `course_updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `update_message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `courses`;
CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_name` varchar(255) NOT NULL,
  `course_code` varchar(50) NOT NULL,
  `short_description` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `level` varchar(50) DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `instructor` varchar(255) DEFAULT NULL,
  `pdf_file` varchar(255) DEFAULT NULL,
  `tutorial_topic` varchar(255) DEFAULT NULL,
  `tutorial_text` text DEFAULT NULL,
  `tutorial_image` varchar(255) DEFAULT NULL,
  `tutorial_audio` varchar(255) DEFAULT NULL,
  `tutorial_video` varchar(255) DEFAULT NULL,
  `modules` text DEFAULT NULL,
  `quiz` text DEFAULT NULL,
  `assignment` text DEFAULT NULL,
  `certificate_requirements` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `instructor_bio` text DEFAULT NULL,
  `instructor_image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_code` (`course_code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `courses` (`id`, `course_name`, `course_code`, `short_description`, `description`, `category`, `level`, `thumbnail`, `price`, `instructor`, `pdf_file`, `tutorial_topic`, `tutorial_text`, `tutorial_image`, `tutorial_audio`, `tutorial_video`, `modules`, `quiz`, `assignment`, `certificate_requirements`, `created_at`, `instructor_bio`, `instructor_image`) VALUES ('2', 'ሐዋርያዊ ተልዕኮ', '028', '<p>የዚህ ትምህርት መሰረታዊ ዓላማ የቤተ ክርስቲያን አገልጋዮች በተለይ ሰባኪያነ ወንጌል መምህራን፣አባቶች ካህናት እና ዲያቆናት የስብከት ዘዴ እንዲሁም ከአንድ ሰባኪ የሚጠበቁ ነገሮች ምንድን ናቸው አንድ ሰባኪስ ሰባኪ ለመባል ምን ማሟላት አለበት የሚለዉን ነገር መሰረት ለማስጨበጥ የሚረዳ ትምህርት ነው።</p>', '', '', 'ከፍተኛ', '', '250.00', 'ዲ/ን ሶፎንያስ ደመቀ', 'uploads/course_pdfs/1782256244_c5f19218bf.pdf', '', '', '', '', '', '', '', '', '', '2026-06-24 02:10:44', NULL, NULL);
INSERT INTO `courses` (`id`, `course_name`, `course_code`, `short_description`, `description`, `category`, `level`, `thumbnail`, `price`, `instructor`, `pdf_file`, `tutorial_topic`, `tutorial_text`, `tutorial_image`, `tutorial_audio`, `tutorial_video`, `modules`, `quiz`, `assignment`, `certificate_requirements`, `created_at`, `instructor_bio`, `instructor_image`) VALUES ('3', 'አገልግሎት እና መንፈሳዊ ሕይወት', '029', '<p>በዚህ ኮርስ የቤተ ክርስቲያን ልጆች ስለ መንፈሳዊ ሕይወት ምንነት፣ስለ አገልግሎት እና የበረከት ዉጤቱ ተረድተው ክርስቲያኖች ከቃል ይልቅ የተግባር ሰዎች የሚሆኑበት የትምህርት አይነት ነው።</p>', '', '', 'ከፍተኛ', '', '300.00', 'ዲ/ን ሶፎንያስ ደመቀ', 'uploads/course_pdfs/1782258075_a551589c5e.pdf', '', '', '', '', '', '', '', '', '', '2026-06-24 02:41:15', NULL, NULL);
INSERT INTO `courses` (`id`, `course_name`, `course_code`, `short_description`, `description`, `category`, `level`, `thumbnail`, `price`, `instructor`, `pdf_file`, `tutorial_topic`, `tutorial_text`, `tutorial_image`, `tutorial_audio`, `tutorial_video`, `modules`, `quiz`, `assignment`, `certificate_requirements`, `created_at`, `instructor_bio`, `instructor_image`) VALUES ('4', 'ነገረ ቅባት', '030', '<p>ይህ የትምህርት አይነት እንደዋናነት በነገረ መለኮት ነገረ ሥጋዌ ላይ ያጠነጠነ ሲሆን ነገረ ቅባት ከተዋሕዶ ጋር ያለው የአስተምህሮ ልዩነት ምንድን ነው የሚለዉን እያነጻጸርን የምንመለከትበት ነው።</p>', '', '', 'ከፍተኛ', '', '350.00', 'ዲ/ን ሶፎንያስ ደመቀ', NULL, '', '', '', '', '', '', '', '', '', '2026-06-24 02:46:48', NULL, NULL);
INSERT INTO `courses` (`id`, `course_name`, `course_code`, `short_description`, `description`, `category`, `level`, `thumbnail`, `price`, `instructor`, `pdf_file`, `tutorial_topic`, `tutorial_text`, `tutorial_image`, `tutorial_audio`, `tutorial_video`, `modules`, `quiz`, `assignment`, `certificate_requirements`, `created_at`, `instructor_bio`, `instructor_image`) VALUES ('5', 'ነገረ ሃይማኖት', '027', '<p>ይህ ትምህርት የሚያተኩረዉ የቤተ ክርስቲያን ልጆች ምሥጢረ ሥላሴን፣ከ3ቱ አካል አንዱ አካል እግዚአብሔር ወልድ ማለትም ምሥጢረ ሥጋዌን  ምንነት እና ግንዛቤ በሰፊዉ እንዲረዱ የሚያደርግ ሲሆን በአጠቃላይ 5ቱን አዕማደ ምሥጢር የሚያውቁበት የትምህርት ክፍል ነው።</p>', '', '', 'ከፍተኛ', '', '500.00', 'ዲ/ን ሶፎንያስ ደመቀ', 'uploads/course_pdfs/1782259088_b781b796a9.pdf', '', '', '', '', '', '', '', '', '', '2026-06-24 02:58:08', NULL, NULL);
INSERT INTO `courses` (`id`, `course_name`, `course_code`, `short_description`, `description`, `category`, `level`, `thumbnail`, `price`, `instructor`, `pdf_file`, `tutorial_topic`, `tutorial_text`, `tutorial_image`, `tutorial_audio`, `tutorial_video`, `modules`, `quiz`, `assignment`, `certificate_requirements`, `created_at`, `instructor_bio`, `instructor_image`) VALUES ('6', 'ሐዋርያዊ ተልዕኮ', '012', '<div>\n<p><strong>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; <em><span style=\"text-decoration: underline;\">&nbsp;ሐዋርያዊ ተልዕኮ እና ኦርቶዶክሳዊ የስብከት ዘዴ</span></em></strong></p>\n</div>\n<div>❖.ስብከት የሚለው ቃል \"ሰበከ\" አስተማረ፣ አዋጀ ነገረ ከሚለው የግዕዝ&nbsp;ቃል የተገኘ ነው፡፡በግሪክ፣ኬሪግማ(kerygma) በእንግሊዝኛ sermonl&nbsp;ይባላል፡፡ ተዛማጅ /ተመሳሳይ/ የሆነ ትርጉም አለው፡፡ ማስተማር አዋጅ&nbsp;መንገር የሚለውን ይተረጉማሉ፡፡</div>\n<div>↪ የቤተክርስቲያን ዋናው ተልዕኮዋ ሰዎችን..</div>\n<div>✦ . ከአለማመን ወደ ማመን ማምጣት፣</div>\n<div>✦ . ያመኑትን ደግሞ በእምነታቸው ማጽናት ነው፡፡</div>\n<div>&nbsp;</div>\n<div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;<span style=\"text-decoration: underline;\"><em><strong> የስብከት አይነቶች</strong></em></span></div>\n<div>✳.ይዘትን መሠረት በማድረግ የስብከት ዓይነት በሦስት ይከፈላሉ፡፡</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; እነርሱም:-</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 👉፩ኛ.ትምህርት</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 👉፪ኛ.ትምህርታዊ ስብከት እና</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 👉፫ኛ.ስብከት ናቸው።</div>\n<div>\n<div>✔ በአጭሩ ስለ ስብከት አይነቶች ጠቅለል አድርገን ስንመለከት&nbsp;እነዚህን ነጥቦች ማየት እንቺላለን።</div>\n<div><strong>✳.ኬሪግማቲክ:</strong>-የማያምኑን አረማውያን ሰዎችን እግዚአብሔርን&nbsp;ካለማመን ወደ ማመን የምንመልስበት የስብከት ዓይነት ነው።</div>\n<div><strong>✳.ዲዳክቲክ(ዲያሌክቲክ):-</strong>ያመኑትን፣ነገር ግን ያልጠነከሩትን አዲስ&nbsp;አማንያንን፤ንዑስ ክርስቲያንን የምንጸናበት የስብከት አይነት ነው።</div>\n<div>↪ አምነው የነበሩ፣ነገር ግን በልዩ ልዩ ምክንያት የዓለማውያን ጠባይ&nbsp;የወረሳቸውን የማነጽ የስብከት አይነት ነው።</div>\n<div><strong>✳.ፕራግማቲክ(ፕራክሎቲክ):-</strong>አምነው&nbsp;በእምነት፤በእውቀት፤በምግባር የጸኑትን መጠበቅና ወደ ተግባራዊ&nbsp;አኗኗር የምንሸጋገርበት ለቅድስና ሕይወት የምናበቃበት የስብከት&nbsp;ዓይነት ነው፡፡</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&nbsp;\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; <span style=\"text-decoration: underline;\"><em><strong>የመልካም መምህር(ሰባኪ) ባህርያት</strong></em></span></div>\n<div>\n<div>❶.በእምነት እና በእውቀት በመስተማር (ትምህርት)፣</div>\n<div>\n<div>❷.መጻሕፍትን ማንበብ፣</div>\n<div>\n<div>❸.ቀናዒነት፣</div>\n<div>\n<div>❹.ክርስቲያናዊ ሕይወት፣</div>\n<div>\n<div>❺.የማይፈራና የማያፍር መሆን፣</div>\n<div>\n<div>&nbsp;➏.አላስፈላጊ እንቅስቃሴ አለማድረግ፣</div>\n<div>\n<div>❼.መጥኖ ማስተማር የሚቺል፤</div>\n<div>\n<div>❽.አርዓያ ክህነት(አለባበስ)፣</div>\n<div>\n<div>❾.ከመስበኩ በፊት ቅድመ ዝግጅት ማድረግ፣</div>\n<div>\n<div>❿.ራስን አለመስበክ፤</div>\n<div>\n<div>⓫.ርእሱን መጠበቅ፤</div>\n<div>\n<div>⓬.ድምፁን መመጠን፣</div>\n<div>\n<div>⓭.የሰባኪውና አድማጩ ግንኙነት፣</div>\n<div>\n<div>⓮.ራስን መሆን፣</div>\n<div>\n<div>⓯.የተሰባክያንን ሥነ ልቡና መረዳት፣</div>\n<div>\n<div>⓰.ንግግሩ ኦርቶዶክሳዊ ላህይ ያለው መሆን አለበት፣</div>\n<div>\n<div>⓱.ቁረጠኝነት ሊኖረው ይገባል።</div>\n<div>&nbsp;</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&nbsp;\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; <span style=\"text-decoration: underline;\"><em><strong>&nbsp;የኦርቶዶክሳዊ ስብከት መለያ ጠባያት</strong></em></span></div>\n<div> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&nbsp;\n<div>\n<ul style=\"list-style-type: square;\">\n<li>ነገረ መለኮታዊ፣</li>\n<li>መጽሐፋዊ፣</li>\n<li>\n<div>ጥምረት ያለው፣</div>\n</li>\n<li>\n<div>ማኅበራዊ ኑሮን በተመለከተ፣</div>\n</li>\n<li>\n<div>ሥርአተ ቤተ ክርስቲያን፣</div>\n</li>\n</ul>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;<strong><em><span style=\"text-decoration: underline;\">የስብከት ዝግጅት</span></em></strong></div>\n<div>💠.አንድ ስብከት ፮ ንዑሳን ዘርፎችና&nbsp;ክፍሎች አሉት።እነርሱም:-</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;✳.ርእስ</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;✳.ዓላማ</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;✳.መግቢያ</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;✳.ዋና ክፍል</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;✳.ምክር</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;✳.መደምደሚያ</div>\n<div>&nbsp;</div>\n<div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;<span style=\"text-decoration: underline;\"><strong>የስብከተ ወንጌል መንገዶች</strong></span></div>\n<div>❖.የስብከተ ወንጌል መንገዶችን በሚከተሉት መንገዶች መክፈል&nbsp;ይቻላል።እነርሱም:-</div>\n<div>✳.ብቻ ለብቻ (ለአንድ ሰው)</div>\n<div>✳.ለጥቂት ሰዎች (ለደቀ መዛሙርት፣ ለቤተሰቦች)</div>\n<div>✳.ለሕዝብ</div>\n<div>✳.ለማኅበር፣ . .</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&nbsp;\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; <em><span style=\"text-decoration: underline;\"><strong>ሐዋርያዊነት</strong></span></em></div>\n<div>\n<div>✳.<strong>ሐዋርያ</strong> የሚለው ቃል \"<span style=\"text-decoration: underline;\"><strong>ሖረ</strong></span>\" ሄደ ከሚለው የግዕዝ ግስ የተገኘ&nbsp;ሲሆን፤ሐዋርያ ማለት የተላከ፣የተመረጠና የተጠራ ማለት ነው።</div>\n<div>\n<div><strong>✳.ሐዋርያዊነት&nbsp;</strong></div>\n<div>\n<ul style=\"list-style-type: circle;\">\n<li>ለአገልግሎት የሚፋጠን፣</li>\n<li>የቅዱሳንን አሠረ ፍኖት (ፈለግ) የተከተለ፣</li>\n<li>ብቁና ንቁ የሆነ፣</li>\n<li>የሐዋርያዊነትን ተልዕኮ በተግባር የሚያሳይ ማለት ነው፡፡</li>\n</ul>\n<p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;<em><span style=\"text-decoration: underline;\"><strong>ሐዋርያዊ ሊጠነቀቅባቸው የሚገቡ ጉዳዮች</strong></span></em></p>\n<div>❶.ወገንተኝነት፤</div>\n<div>\n<div>❷.ከንቱ ውዳሴ፣</div>\n<div>\n<div>❸.ለምድራዊ ጥቅም ማገልገል፣</div>\n<div>\n<div>❹.ከፈተናዎች መጠበቅ ነው።</div>\n<div>\n<ul>\n<li><strong>ማጠቃለያ</strong>፦ይህ ትምህርት በጣም ሰፊና ወሳኝ ሲሆን ከብዙ በጥቂቱ በውስጡ እነዚህን ነጥቦች ይዟል።</li>\n</ul>\n</div>\n<div>\n<p>&nbsp;</p>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>\n</div>', '<h1>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; <span style=\"text-decoration: underline;\"><strong>ሐዋርያዊ ተልዕኮ እና ኦርቶዶክሳዊ የስብከት ዘዴ</strong></span></h1>\n<div>❖.ስብከት የሚለው ቃል \"ሰበከ\" አስተማረ፣ አዋጀ ነገረ ከሚለው የግዕዝ&nbsp;ቃል የተገኘ ነው፡፡በግሪክ፣ኬሪግማ(kerygma) በእንግሊዝኛ sermonl&nbsp;ይባላል፡፡ ተዛማጅ /ተመሳሳይ/ የሆነ ትርጉም አለው፡፡ ማስተማር አዋጅመንገር የሚለውን ይተረጉማሉ፡፡</div>\n<div>↪ የቤተክርስቲያን ዋናው ተልዕኮዋ ሰዎችን..</div>\n<div>✦ . ከአለማመን ወደ ማመን ማምጣት፣</div>\n<div>✦ . ያመኑትን ደግሞ በእምነታቸው ማጽናት ነው፡፡</div>\n<div>\"<strong><em>በስብከት ሞኝነት የሚያምኑትን ሊያድን የእግዚአብሔር በጎ ፈቃድ&nbsp;ሆኗልና</em></strong>\"1ኛ ቆሮ 1&divide;21</div>\n<div>↪ ስብከት የቤተክርስቲያን ትልቁ የአገልግሎት ክፍል ነው፡፡</div>\n<div>✦ .ስብከት መንፈሳዊ ምግብን ለሰዎች መመገብ ማለት ሲሆን&nbsp;ለአንድ ሰው ሥጋዊ ምግብ ለመመገብ ከሚያስፈልገው ዝግጅት በላይ&nbsp;ዝግጅት ያስፈልገዋል።</div>\n<div>❖.የቤተ ክርስቲያን ተልዕኮዋ የስብከተ ወንጌል ዓላማ</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;✔ ማንጻት(ያላመኑትን ማሳመን)&ldquo;<strong>ብዙኃን እለ ይቤሉ መኑ ያርእየነ ሠናይቶ&rdquo;</strong></div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;✔ .ማጽናት እና</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;✔ .መቀደስ ናቸው።</div>\n<h3><strong>❖.<span style=\"text-decoration: underline;\">ስብከት</span></strong></h3>\n<div>\n<ul>\n<li>ስለ እግዚአብሔር የምንመሰክርበት ነው።ዮሐ 1&divide;6፣1ኛ ዮሐ 1&divide;1፣ማቴ 10&divide;32</li>\n<li>የአምልኮ ክፍል ነው።</li>\n<li>ማምለክ፤ማመንና መዳን መሠረቱ መሰማት ነው።ሮሜ 10&divide;10-17</li>\n<li>ትእዛዘ እግዚአብሔር ነው።\"<em><strong>ለሕዝብም እንድንሰብክና በሕያዋንና በሙታን ሊፈርድ በእግዚአብሔር የተወሰነ እርሱ እንደሆነ እንመሰክር ዘንድ አዘዘን\"</strong></em>ሐዋ 10&divide;42</li>\n</ul>\n</div>\n<div><strong>✔ .አስተውሉ</strong>:-ጌታችን ኢየሱስ ክርስቶስ ደቀ መዛሙርቱን የጠራበትና የመረጠበት&nbsp;ዓላማና የመጨረሻው ትእዛዝ ስብከት ነው።።ማር 16&divide;15</div>\n<div>✦ .በአንደበታችን እውነትን ልንመሰክር ትእዛዘ እግዚአብሔርን ልንፈጽም&nbsp;ይገባል።</div>\n<div>\n<ul>\n<li>አንደበት \"<strong>ይእቲ ተዓቢ እም ሲኦል</strong>\"አንደበት ከሲኦል የከፋች ናት።</li>\n<li>\"<strong>ሞተ ዮሴፍ ነገርዎ ለዮሴፍ</strong>\"</li>\n</ul>\n</div>\n<div>✔ .ጨው ሳይሟሟ እንደማያጣፍጥ ሰውም ሳይደክም አገልግሎት&nbsp;(ስብከት) የለም።</div>\n<div>👉&ldquo;እንግዲህ ያላመኑበትን እንዴት አድርገው ይጠሩታል?ባልሰሙትስ&nbsp;እንዴት ያምናሉ? ያለ ሰባኪስ እንዴት ይሰማሉ?&rdquo;ሮሜ 10&divide;14 -15</div>\n<div>&ldquo;አንዳንዶች ተከራካሪዎችንም ውቀሱ፥ አንዳንዶችንም ከእሳት ነጥቃችሁ&nbsp;አድኑ&rdquo; ይሁዳ 1&divide;22</div>\n<div><em><strong>&ldquo;የወንጌል ሰባኪነትን ሥራ አድርግ፥ አገልግሎትህን ፈጽም</strong></em>።&rdquo; 2ኛ ጢሞ 4&divide;5</div>\n<div>👉&ldquo;በመልካም የሚያስተዳድሩ ሽማግሌዎች ይልቁንም በመስበክና&nbsp;በማስተማር የሚደክሙት እጥፍ ክብር ይገባቸዋል&rdquo;፩ኛ ጢሞ 5&divide;17</div>\n<div>👉&ldquo;ወንጌልንም ባልሰብክ ወዮልኝ&rdquo;1ኛ ቆሮ 9&divide;16</div>\n<div>👉&ldquo;ኃጢአተኛውን ኃጢአተኛ ሆይ በእርግጥ ትሞታለህ ባልሁ ጊዜ&nbsp;ኃጢአተኛውን ከመንገዱ ታስጠነቅቅ ዘንድ ባትናገር ያ ኃጢአተኛ&nbsp;በኃጢአቱ ይሞታል ደሙን ግን ከእጅህ እፈልጋለሁ&rdquo;ሕዝ 33&divide;8</div>\n<div>\n<ul>\n<li><strong>ስብከት የእግዚአብሔርን መንግስት የምንገልጥበት ነው።</strong></li>\n<li style=\"font-weight: bold;\"><strong>ስብከት መንግስተ ሰማያት እግዚአብሔርን ለሚወድና እንደፈቃዱ ለሚኖሩ የተዘጋጀቺ ናት\"🔆💡🔐</strong></li>\n</ul>\n</div>\n<div>❖&ldquo;ወንድሞቼ ሆይ ከእናንተ ማንም ከእውነት ቢስት አንዱም&nbsp;ቢመልሰው፣ኃጢአተኛን ከተሳሳተበት መንገድ የሚመልሰው ነፍሱን ከሞትእንዲያድን የኃጢአትንም ብዛት እንዲሸፍን ይወቅ\"ያዕ 5&divide;19-20</div>\n<div>✳.&ldquo;ስለ ሌላው ወንድሙ መዳን ግድ የሌለው ሰው እርሱ ይድናል ብዬ&nbsp;አላምንም፡፡&rdquo; ቅዱስ ዮሐንስ አፈወርቅ</div>\n<div>✳.\"ማንም ሳይከለክለው የእግዚአብሔርን መንግስት እየሰበከ ስለ ጌታ&nbsp;ኢየሱስ ክርስቶስ እጅግ ገልጦ ያስተምር ነበር\"ሐዋ 28&divide;31</div>\n<h2>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;የስብከት ታሪክ</h2>\n<div><strong>፩ኛ.በዓለመ መላእክት:-</strong>መላእክት በተፈጠሩ በመጀመሪያ ቀን የመላእክት አለቃ የነበረ ሳጥናኤል የተፈጠረበትን ዓላማ በመሳት ክህደትንና ሐሰተኛነትን ከራሱ አፍልቆ እኔ ፈጥርኳችሁ ባለ ጊዜ ቅዱስ ገብርኤል \"ንቁም በበህላዌነ እስከ ንረክቦ ለአምላክነ\'\' በማለት መላእክትን አረጋግቷል ሰብኳል።የመጀመሪያው ስብከትም ይህ ነበር።ለበለጠ ግን ታሪኩን በሰፊው ተመልከቱ መጽሐፈ አክሲማሮስን \"<strong>ወበእንተዝ ደለወ ከመ ይጽር&nbsp;ዜናሃ ለማርያም</strong>\"ኢሳ 40&divide;1፣ምሳ 1&divide;35</div>\n<div><strong>፪ኛ.በህገ ልቡና:-</strong></div>\n<div>✦ .ከአዳም-ሙሴ&nbsp;ይሁ 14፣2ኛ ጴጥ 2&divide;5;ኩፋ 11&divide;14</div>\n<div><strong>፫ኛ.ህገ ኦሪት:-</strong></div>\n<div>✦ .ከሙሴ-መጥምቀ&nbsp;መለኮት&nbsp;ቅ/ዮሐንስ ፣ነኅ ፰&divide;፩-፰</div>\n<div><strong>፬ኛ.ዘመነ ወንጌል:-</strong></div>\n<div>✳.የዘመነ&nbsp;ወንጌል&nbsp;በር&nbsp;ከፋቺና&nbsp;መንገድ&nbsp;ጠራጊ&nbsp;መጥምቀ&nbsp;መለኮት&nbsp;ቅ/ዮሐንስ&nbsp;ነው።ማቴ 3&divide;2፣23&divide;35</div>\n<h2>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; የስብከት አይነቶች</h2>\n<div>✳.ይዘትን መሠረት በማድረግ የስብከት ዓይነት በሦስት ይከፈላሉ፡፡እነርሱም:-</div>\n<div>\n<p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 1ኛ.ትምህርት</p>\n<p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 2ኛ.ትምህርታዊ ስብከት እና</p>\n<p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 3ኛ.ስብከት ናቸው።</p>\n</div>\n<h3>1ኛ.ትምህርት</h3>\n<div>✔ .በእውቀት ላይ የተመሠረተ</div>\n<div>✔ .ተከታታይነት ያለው</div>\n<div>✔ .በማስረጃ የተደገፈ</div>\n<div>✔ .ማብራራትን መተርጉምን፣ ማመሥጢርን የሚጠይቅ ነው።</div>\n<h3>2ኛ.ትምህርታዊ ስብከት፡-</h3>\n<div>✔ .በጥምረት የሚስጥ</div>\n<div>✔ .መነሻው ትምህርት መድርሻው ስብከት የሆነ</div>\n<div>✔ .ማስረጃ በመስጠት ሰውን ወደ ፈጣሪው የሚያቀርብ</div>\n<div>✔ .እውቀትና ሕይወት በአንድ ጊዜ የሚያስጨብጥ</div>\n<h3>3ኛ.ስብከት፡-</h3>\n<div>✔ .ቀለል ባለ አገላለጽ የሚቀርብ</div>\n<div>✔ .ማስረዳትና ማሳየት ላይ የሚያተኮር</div>\n<div>✔ .የሰውን ልቡናና አእምሮ መማረክ እና መግዛት የሚችል</div>\n<div>✳.ትምህርት አቀራረብን መሠረት በማድረግ ደግሞ በሚከተሉት&nbsp;ዘርፎች ይከፈላል።</div>\n<div><strong>❶.ነገረ ሃይማኖት:-</strong>ከስብከቶች ሁሉ ጠጣርና ከባድ የሚባለው&nbsp;የስብከት ዓይነት ነው፡፡ይህን ትምህርት ለማስተማር ሰባኪውየሚከተሉትን ነጠቦች ማስታዋል ይጠበቅበታል፡፡</div>\n<div><strong><em>ሀ.አንድ ነጥብ ላይ ብቻ ማተኮር</em></strong></div>\n<div>ለምሳሌ፡-ሀልዎተ እግዚአብሔር ፣ምሥጢረ ሥላሴ&hellip;</div>\n<div><strong>ለ.በሚገባ ማብራራት፡-</strong>ሊያስተምር የፈለገውን ነጥብ በሚገባ&nbsp;ማብራራት ይኖርበታል፡፡የሚያበራራባቸው ዘዴዎችም፡-</div>\n<div>✔ .ምሳሌዎችን በማንሳት፤</div>\n<div>✔ .ታሪክ በማስታወስ በመተረክ</div>\n<div>✔ .ለትምህርት አስረጂ ጥቅሶችን በማንሳት</div>\n<div>✔ .የሊቃውንትን ብሒል በመጠቀም</div>\n<div><strong>❷.ታሪካዊ:-</strong></div>\n<div>❖.ይህ ዘዴ አብዛኛውን ጊዜ በበዓላት ቀን የምናስተምርበት መንገድ&nbsp;ነው፡፡ በየበዓላቱ የዕለቱ ስንክሳር፣ገድል፣ድርሳን ይነበባል፣ ይተረካል፡፡ሰባኪውና የስብከቱ ቦታ የኦርቶዶክስ ቤተ ክርስቲያን እንደመሆኑ እነዚህን&nbsp;አስቀድሞ አንብቦና አጥንቶ ለምእመናን ማስተማር ይጠቅበታል፡፡</div>\n<div>❖.ሰባኪው ሕዝቡን በሚመጥንና በማያስለች መልኩ ቀላልና ግልጽ&nbsp;በሆነ አቀራረብ ሰዓት መጥኖ ርዕስ መርጦ ምእመናን ከታሪኩ ተነስተው&nbsp;በሕይወታቸው ላይ ትርጉም እንዲኖራቸው ወደ ሕይወት በመቀየር&nbsp;ማጠቃለያውን ስብከት አድርጎ መጨረስ ማለት ነው፡፡</div>\n<div><strong>❸.ባለ አንድ ሐረግ ስብከት</strong></div>\n<div><strong>❹.ቅዳሴያዊ ስብከት:-</strong>ይህ ዓይነት ስብከት በጾም ወቅት በኪዳን የጸሎት&nbsp;ሰዓት፣በንግስ ጊዜ፣በሥርዒተ ቅዲሴ መካከል የሚሰጥ ትምህርት ነው፡፡በቤተክርስቲያናችን ዋነኛው የስብከት ዘዴ ነው፡፡</div>\n<div>✔ በአጭሩ ስለ ስብከት አይነቶች ጠቅለል አድርገን ስንመለከት&nbsp;እነዚህን ነጥቦች ማየት እንቺላለን።</div>\n<div><strong>✳.ኬሪግማቲክ</strong>:-የማያምኑን አረማውያን ሰዎችን እግዚአብሔርን&nbsp;ካለማመን ወደ ማመን የምንመልስበት የስብከት ዓይነት ነው።</div>\n<div><strong>✳.ዲዳክቲክ(ዲያሌክቲክ)</strong>:-ያመኑትን፣ነገር ግን ያልጠነከሩትን አዲስ&nbsp;አማንያንን፤ንዑስ ክርስቲያንን የምንጸናበት የስብከት አይነት ነው።</div>\n<div>↪ አምነው የነበሩ፣ነገር ግን በልዩ ልዩ ምክንያት የዓለማውያን ጠባይ&nbsp;የወረሳቸውን የማነጽ የስብከት አይነት ነው።</div>\n<div><strong>✳.ፕራግማቲክ(ፕራክሎቲክ)</strong>:-አምነው&nbsp;በእምነት፤በእውቀት፤በምግባር የጸኑትን መጠበቅና ወደ ተግባራዊ&nbsp;አኗኗር የምንሸጋገርበት ለቅድስና ሕይወት የምናበቃበት የስብከት</div>\n<div>ዓይነት ነው፡፡</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&nbsp;\n<h2>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; የመልካም መምህር(ሰባኪ) ባህርያት</h2>\n</div>\n<h4>❶.በእምነት እና በእውቀት በመስተማር (ትምህርት)</h4>\n<div>🔆.መንፈሳዊ መምህር ማስተማር ያለበት በእምነት ነው።እምነት&nbsp;የሌለው ሰው ሌላውን ሊያሳምን አይችልም።ክህደትን ቆርጦ ለመጣል&nbsp;አስተማሪው በመጀመሪያ በእምነት መሳል አለበት።</div>\n<div>\"<strong>አመንኩ በዘነበብኩ</strong>\"መዝ 115&divide;1አመንሁ ስለዚህ ተናገር</div>\n<div>🔆.መምህር ሠወች የእግዚአብሔር ቸርነት እንዲቀምሱ የመጋበዝ&nbsp;ሀላፊነት አለበት።</div>\n<div>\"<strong>ጠአሙ ወተአይምሩ ከመ ሔር እግዚአብሔር</strong>\"መዝ 33&divide;15 ነገር ግን ያልቀመሱትን ነገር ቅመሱ ማለት አይቻልም።አንድ መምህር በእምነት ያላጣጣመውን ትምህርት ቢያስተምር ትርፉ ድካም ብቻ ነው።</div>\n<div>❖.አንድ ሰባኪ ሳይማር ሌሎችን ለማስተማር አይችልምና በቅድሚያ በሚገባ መጽሐፍ ቅዱስን የተማረ መሆን አለበት፡፡✔ .ስለዚህ አንድ ሰባኪ ከአባቶች እግር ሥር የተማረ መሆን አለበት። ከአባቶች ከሚቀዳው ምንጭ የጠጣና በትሕትና ራሱን ዝቅ በማድረግ ምስጠሩን እግዚአብሔር እንዲገልጥለት በጸሎት በመጠየቅ የተማረ መሆን አለበት፡፡</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;<em><strong>\"ሳይማር የሚያስተምር ከተሳዳቢ ይቆጠራል\"</strong></em></div>\n<h4><strong>❷.መጻሕፍትን ማንበብ:-</strong></h4>\n<div>❖.አንድ ሰባኪ ወይም መምህር እንደመሆኑ መጠን ከመማሩ በተጨማሪ&nbsp;ሌሎች ተጨማሪ ይጠቅማሉ የሚላቸውን መጻሕፍቶች ዘመኑን በመዋጀት&nbsp;ማንበብ አለበት።</div>\n<div>✔ . ከሚያስተምራቸው ሰዎች በእውቀት ልቆ መገኘት አለበት፤ ካልሆነ ግን&nbsp;ቤተ ክርስቲያኗን ያስገምታታል፡፡</div>\n<div>✳.\"<strong>ለማንበብና ለመምከር ለማስተማርም ተጠንቀቅ</strong>&rdquo; 1ኛ ጢሞ 4&divide;13</div>\n<div>✳&ldquo;ሰባኪውም ጠቢብ ስለ ሆነ ለሕዝቡ እውቀትን አስተማረ፣እርሱም ብዙ&nbsp;ምሳሌዎችን መረመረና ፈላለገ፣አስማማም።&rdquo; መክ 12&divide;9-10</div>\n<div>✳.\"<strong>ለእመ ሐሰሰከ ትኩን መምህረ ኩን ገንዳሌ መጻሕፍት</strong>\"ቅ.ዮሐንስ&nbsp;አፈወርቅ</div>\n<div>➡.ሰባኪ መጻሕፍትን ካላነበበና በእውቀት ራሱን ካላካበተ የሚናገረው&nbsp;ይደናገረዋል፤ይጠፋዋል፡፡ስለዚህ ከሌሎች መምህራን፣ ከቅዱሳት መጻሕፍት፣ከምቹ አጋጣሚዎችወዘተ መማር አለበት፡፡</div>\n<div><strong>❖.አስተውሉ:</strong>-ብርጭቆ ካልሞላ አይፈስም ፤ሰባኪም ሙሉ ካልሆነ&nbsp;የምዕመናንን ልብ ማርካት አይቺልም።</div>\n<h3><strong>❸.ቀናዒነት:-</strong></h3>\n<div>💠.የክርስቶስ ወንጌል ለሁሉም ይዳረስ ዘንድ ሁሉም ይድኑ ዘንድ&nbsp;በትጋናት በቅናት ሌትና ቀን ደከመኝ ሰለቸኝ ሳይል መጋደል አለበት፡፡</div>\n<div>🔆.&ldquo;የቀረውንም ነገር ሳልቆጥር ዕለት ዕለት የሚከብድብኝ&nbsp;የአብያተ ክርስቲያናት ሁሉ አሳብ ነው&rdquo; 2ኛ ወደ ቆሮ 11&divide;28</div>\n<div>🔆. \"ለቅዱሳን አንድ ጊዜ ፈጽሞ ስለተሰጠቺ ሃይማኖት&nbsp;እንድትጋደሉ እየመከርኳችሁ እጽፍላችሁ ዘንድ ግድ ሆነብኝ\"ይሁ 1&divide;3</div>\n<h3><strong>❹.ክርስቲያናዊ ሕይወት:-</strong></h3>\n<h4><em>✔ .በፍቅር እና በትህትና የሚታወቅ</em></h4>\n<div>✳.\"ፍቅር ለዘወትር አይወድቅም\"1ኛ ቆሮ 13&divide;8</div>\n<div>✳<em><strong>.ሠናየ ሀልዩ በልብክሙ/ ለቢጽክሙ/ ተፋቀሩ በኩሉ ጊዜ ዝ ውእቱ&nbsp;ፈቃዱ ለእግዚአብሔር መንፈሰ ኢታጥፍኡ ተነብዮ ኢትመንኑ ወኩሎ&nbsp;አመክሩ ወዘሠናየ አጽንኡ</strong></em>\"በልባችሁ መልካም ነገርን አስቡ&nbsp;የእግዚአብሔር ፈቃድ እርስ በርሳችሁ እንድንፋቀሩ ነውና እርስበርሳችሁ&nbsp;ሁልጊዜ ተፋቀሩ መንፈሳዊ ነገርን አታጥፉ ትንቢትንም አትናቁ ሁሉንም&nbsp;መርምሩ የተሻለውን አጽንታችሁ ያዙ\"</div>\n<div>🔆. \"<strong>ተፋቅሮ ኢያስተፄንስ ወኢያሄሊ ተድላ ለባህቲቱ</strong> \"ፍቅር ማንንም ችግር&nbsp;ውስጥ አያስገባም የብቻ ድሎትንም አያሳስብም ወይም ብቻየን ልደሰት&nbsp;አያስብልም\"ፍቅር ከስግብግብነት ከቀናተኛነት ወጥቶ ለሌሎች መትረፍ ስለሆነ&nbsp;ብቻየን ይድላኝ አያሰኝም፡፡1ኛ ቆሮ 13&divide;5</div>\n<div><strong>✳.አስተውሉ</strong>:-ጸጋ እግዚአብሔር የሚፈሰው ዝቅ ወደአሉ ሰዎች ነው።</div>\n<h3><strong>❖.ሰባኪ መምህር ትሁት መሆን አለበት።</strong></h3>\n<div>\"ማንም ለሰርግ ቢጠራህ አስቀድመህ በከበሬታ ወንበር አትቀመጥ&nbsp;ምናልባት ከአንተ ይልቅ የከበረ ተጠርቶ ይሆናልና አንተንም እሱንም&nbsp;የጠራ ጋባዥ መጥቶ ለዚህ ስፍራ ታውለት ይልሃል ያንጊዜ እያፈርክ&nbsp;በዝቅተኛ ቦታ ትሆናለህ..\"ሉቃ 14 &divide;7-11 ብሎ በምሳሌ ጌታችን&nbsp;ያስተማረው ደቀ መዛሙረቱ ለማስተማር ሲሽቀዳደሙ በማየቱ&nbsp;እንደሆነ ነው የሚነገረው።</div>\n<div>\"ማንም ከእናንተ ታላቅ ሊሆን የሚወድ የሁሉ አገልጋይ ይሁን\"ማቴ 20&divide;26 ይህንን ትህትና ማሳየት መቻል አለበት።</div>\n<div>✔ .ማስተማርን የሥጋዊ ክብር መፈለጊያ ማድረግ አያስፈልግም ክብር&nbsp;እንደ ጥላ ሲሸሿት የምትርቅ ሲርቋት ደግሞ የምትቀርብ ናት።</div>\n<div>💠.\'\'<strong>ዘአትሐተ ርእሶ ይትሌአል ወዘ አልአለ ርእሶ ይቴሀት፤ እስመ&nbsp;ዘአዕበየ ርእሶ የሐስር ወዘአትሐተ ርእሶ ይከብር</strong>\"ማቴ 23&divide;12 -ራሱን&nbsp;ዝቅ ዝቅ የሚያደርግ ከፍ ክፍ ይላል ራሱን ከፍ ክፍ የሚያድርግ ደግሞ&nbsp;ዝቅ ዝቅ ይላል፡፡አባቶቻችን ምድር ከዚህ በታች ዝቅ የማትለው&nbsp;እስከመጨረሻው ዝቅ ስላለች ነው ይላሉ፡፡ሌላው ፀሐይ በደንብ&nbsp;የምታሞቀው ዝቅ ያለውን ነው፡፡ከፍ ሥትል ትቀዘቅዛለህ ስለዚህ&nbsp;ላለመቀዝቀዝ ራስህን ከፍ አታድርግ፡፡ከፍ አትበል በረዶ ትሆናል፡፡</div>\n<h3><strong>✔ .በክርስትና ሕይወቱ የተመሰከረለት</strong></h3>\n<div>✳.\"ወዘሰ ኢይክል ሠሪዐ ቤቱ እፎኑ ያስተሐምም ቤተ እግዚአብሔር \"ጢሞ 3&divide;5</div>\n<div>✳.በእናንተ ስበብ የእግዚአብሔር ስም ይሰደባል\"ሮሜ 2&divide;20-24</div>\n<div>➡ሃይማኖቱ የቀና፤ስነ ምግባሩ የተስተካከለ፤ከእውቀቱ ይልቅ በተግባሩ&nbsp;የሚሰብክ፤የሚጣፍጥ ጨው መልካም መዓዛ ያለው መሆን አለበት።1ኛ ቆሮ 11&divide;1</div>\n<div><em><strong>\"አንተ መዓዛህ ምንድን ነው?ክርስቶስ ክርስቶስን ነው ወይስ ዲያቢሎስ&nbsp;ዲያቢሎስን?መዓዛህ የሚታወቀው በታቀፈው ነው፤አንድ እናት የምትሸተው&nbsp;በታቀፈቺው ልጅ ነው\"<span style=\"text-decoration: underline;\">አረጋዊ መንፈሳዊ</span></strong></em></div>\n<h3><strong>❺.የማይፈራና የማያፍር መሆን</strong></h3>\n<div>✦ .ሰው ምን ይለኝ ይሆን በማለት ሳይጨናነቅ ጥበብ በተሞላበት&nbsp;በሁኔታ የእግዚአብሔርን ቃል በሚገባ ማስተላለፍ አለበት፡፡</div>\n<div>✳.&ldquo;<strong>በወንጌል አላፍርም</strong>&rdquo; ሮሜ 1&divide;16</div>\n<div>✳. &ldquo;ነገር ግን የሠራዊት ጌታ እግዚአብሔርን ቀድሱት፣ የሚያስፈራችሁና&nbsp;የሚያስደነግጣችሁም እርሱ ይሁን\"ኢሳ 8&divide;13</div>\n<h3><strong>➏ .አላስፈላጊ እንቅስቃሴ አለማድረግ</strong></h3>\n<div>✔ .እጅን ማወራጨት፤</div>\n<div>✔ .ራስን፣ . . . ማከክ፤</div>\n<div>✔ .ወዲያ ወዲህ መንጎራደድ፤</div>\n<div>✔ . አላስፈላጊ ድግግሞሽ ማድረግ የለበትም፡፡</div>\n<h3>❼.መጥኖ ማስተማር የሚቺል፤</h3>\n<div>✦ ያልተመጠነ ነገር ይጎመዝዛል ያልተመጠነ ነገር ጣእም&nbsp;አይኖረውም።ቅዱስ ጴጥሮስ በ፩ኛ መልእክቱ 4&divide;8 እና 5&divide;8 በመጠን ኑሩ ብሎ እንዳስተማረን ለሁሉም ነገር መጠንና ልክ አለው።</div>\n<div>\"<strong>ዘበልአ በአቅሙ የኃድር በሰላም</strong>\" እንዳለው ጠቢቡ ሰሎሞን ስጋዊ&nbsp;ምግብን በመጠን እንደምንመገብ ሁሉ ቃለ እግዚአብሔርንም በመጠን&nbsp;መማር ያስፈልገናል።ምእመናን መመህሩ የያዘውን ሁሉ እንዲይዙ&nbsp;የሚሞክርባቸው ቤተ ሙከራወች አይደሉም በመጠን ሰምተው&nbsp;ከህይወታቸው ጋር እንዲያዋህዱ የሚጠበቁ ናቸው እንጅ</div>\n<div>\n<p>\"<em><strong>ወበዘከመዝ አምሳል ተናገሮሙ ቃሎ በአምጣነ ይክሉ ሰሚዓ</strong></em>\" መስማት በሚችሉበት መጠን እነዚህን በሚመስል በብዙ ምሳሌ ቃሉን ይነግራችው ነበር።ማር 4&divide;33</p>\n<h3>❽.አርዓያ ክህነት(አለባበስ)</h3>\n</div>\n<div>✳በኢትዮጵያ ቤተክርስቲያን የስብከተ ወንጌል ታሪክ በጃንደረባው&nbsp;ቢጀመርም የተሟላ ሆኖ የተፈጸመውና በተግባራዊነት ዕድገት ያሳየው&nbsp;ከፍሬምናጦስ የክህነት አገልግሎት በኋላ ነው፡፡</div>\n<div>&nbsp;</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;<em><strong> &nbsp;ከ34ዓ.ም-330ዓ.ም</strong></em></div>\n<div>✳ አለባበሱ አርአያ ክህነትን የጠበቀ መሆን አለበት፡፡ንጽሕናውን&nbsp;የመጠበቅና አግባብ ያለው አለባበስ መሆን አለበት።በጣም ከደረጃዉ&nbsp;የወረደ ወይም በጣም ዘመናዊ የሆነ ልብስ መልበስ የለበትም፡፡</div>\n<div><em><strong>\"ኢኃደጋ ለምድር እምቅድመ ዓለም ወእስከ ለዓለም እንበለ ካህናት&nbsp;ወዲያቆናት</strong></em>\"<strong><span style=\"text-decoration: underline;\">ቅ/ያሬድ</span></strong></div>\n<div>&nbsp;</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 1ኛ ቆሮ 15&divide;45 ተመልከቱ</div>\n<h3><strong>መምህራን፦</strong></h3>\n<ul style=\"list-style-type: square;\">\n<li>\"<em><strong>እለ ይሜህሩ ወንጌለ ለኩሉ ፍጥረት</strong></em>\"</li>\n<li>\"<strong>እስመ አርአያየ ወሀብኩክሙ</strong>\"ዮሐ 13&divide;15</li>\n</ul>\n<h3>❾.ከመስበኩ በፊት ቅድመ ዝግጅት ማድረግ</h3>\n<div>✳. ሰባኪ መጋቢ ነው፡፡ለዝግጅቱ በቂ ጊዜ በመስጠት መጻሕፍትን&nbsp;በማንበብ ማስታወሻ በመያዝ መዘጋጀት አለበት።በሚገባ ተዘጋጀቶ በመሄድ በሚገባ ቦታ የሚገባውን መልእክት&nbsp;አስተላልፎ መምጣት አለበት እንጂ በችኮላ ሳይዘጋጅ በመሄድ&nbsp;ዘበራርቆ መምጣት የለበትም፡፡</div>\n<div>✳.ለማን እንደሚሰብክ ማወቅ አለበት።የስብከት በተለያዩ ቦታዎችና&nbsp;ለተለያዩ ሰዎች ለምሳሌ፡-</div>\n<div>\n<ul style=\"list-style-type: circle;\">\n<li>በሐዘን ቤት፣</li>\n<li>በሠርግ ቤት፣</li>\n<li>በእስር ቤት&hellip;የሚሰጥ ሲሆን</li>\n</ul>\n</div>\n<div>✦ .ሰዎቹ ያሉበትን ሁኔታ</div>\n<div>✦ .ቦታውን</div>\n<div>✦ .የሰዎቹን የእውቀት ደረጃ</div>\n<div>✦ .ባህልና እድሜ&nbsp;ጠንቅቆ ማወቅ ያስፈልጋል።</div>\n<h3>❿.ራስን አለመስበክ፤</h3>\n<div>🔆.ራሱ ባለቤቱ ክርስቶስ ምንም እንኳን በሥልጣን፣ በፈቃድ፣በሕልውና፣ በባሕርይ ከአብና ከመንፈስ ቅዱስ ጋር አንድ (ትክክል)&nbsp;ቢሆንም በተዋሐደው ሥጋ ምክንያት ወልድ ከአብ ዘንድ የተላከና ለአብ&nbsp;የታዘዘ ነው።</div>\n<div><strong>\" የላከኝን ፈቃድ እንጂ ፈቃዴን ላደርግ አልሻምና\"</strong>ዮሐ 5&divide;30&nbsp;በማለት እንደተናገርው።</div>\n<div>🔆.መጥምቀ መለኮት ዮሐንስም \"ስለ ራስህ ምን ትላለህ?\"ብለው ለጠየቁት&nbsp;የሰጠው መልስ \"የጌታን መንገድ አቅኑ ብሎ በምድረ በዳ የሚጮህ ሰው&nbsp;ድምጽ እኔ ነኝ\" ብቻ ነበር ያለው፡፡ዮሐ 1&divide;23</div>\n<div><strong>❖.አስተውሉ</strong>:-ዮሐንስ የክርስቶስን ፈቃድ እንጂ የራሱን የሚያውቀውን&nbsp;እውነተኛ ታሪኩን እንኳን መናገር አልፈለገም፡፡ አንድ ሰባኪም እንዲሁ&nbsp;ሊያደርግ ይገባል።</div>\n<div>✳.የሚያደንቁት ሰባኪና ምን ልሥራ ብለው የሚጠይቁት ሰባኪ በእጅጉ&nbsp;ይለያልና ሰባኪ ራስን ከመስበክ ነጻ መሆን ይጠበቅበታል፡</div>\n<div>✳.\"ብዙ ሰዎች ቸርነታቸውን ያወራሉ የታመነውን ሰው ግን ማን&nbsp;ያገኘዋል\"ምሳ 20&divide;6</div>\n<div>✳.\"እግዚአብሔር የላክው የእግዚአብሔር ቃለ ይናገራል\"ዮሐ 3&divide;34</div>\n<div>✳.\"ልቡናዬ መልካም ነገርን አፈለቀ (አወጣ)\"ቅ/ዳዊት</div>\n<div>✳.\"እኛ ግን የተሰቀለውን ክርስቶስን እንሰብካለን\"1ኛ ቆሮ 1&divide;23</div>\n<h3>⓫.ርእሱን መጠበቅ፤</h3>\n<div>✳.አንድ ሰባኪ በግድ የለሽነት ተነስቶ ርእሱን ያልጠበቀ ስብከት&nbsp;ለአድማጮች ጥቅም የማይሰጥ ከመሆኑም ባሻገር ሰባኪውን&nbsp;ያስገምተዋል፡፡</div>\n<div>✳.ምስጢር ሳበኝ እያለ ትዝ ያለውን ሁሉ ማውራት የለበትም፡፡</div>\n<div>✳.የማያውቀውን ወይም እርግጠኛ ያልሆነበትን ነገር ለመናገር መድፈር&nbsp;የለበትም።</div>\n<div>✳.ስብከት ርዕሱ ካልተጠበቀ ውጤታማ ሊሆን አይችልም፤ ምእመናኑ&nbsp;የሚጨብጡት ነገር አይኖራቸውም ሰባኪውም ትዝብት ውስጥ&nbsp;ይወድቃል፡፡ ርዕስ መጠበቅ የሚሰጠው ትምህርት ወጥና ያልተደባለቀ&nbsp;ያደርገዋል።</div>\n<div><strong>ለምሳሌ፡</strong>- ስለ ምሥጢረ ሥላሴ ጀምሮ ስለ ጾም፤ስለ ቅዱስ ቁርባን ጀምሮ ስለ&nbsp;ሥዕል፤ስለ ክርስቶስ የባሕርይ አምላክነት ጀምሮ ስለ ታቦትየሚል ከሆነ\"አርባ ስድስት ጥዳ አንዱንም&hellip; አረረባት\" እንደተባለው ይሆናል፡፡</div>\n<div><strong>✳.አስተውሉ:-</strong> ሰዓትና ርዕስ መጠበቅ የአንድ ሰባኪ መታወቂያዎች ናቸው፡፡</div>\n<div>🔆.ሁሉ ነገር በልኩና በአግባቡ ሊሆን ይገባዋል።ነገር ሲበዛ ውጤቱና&nbsp;መጨረሻው አያምርም።</div>\n<div>✳.\"የምነግራችሁ ገና ብዙ አለኝ ነገር ግን አሁን ልትሸከሙት&nbsp;አትችሉም\"ዮሐ 16&divide;12 በማለት ተነግጿል</div>\n<div>✳.\"<em><strong>ግብር ዘአልቦ ምጣኔ ገአር ውእቱ ወለገአርኒ ይተልኦ ዝንጋኤ</strong></em>\"ልክ&nbsp;የሌለው ስራ ድካም ነው ድካምንም መዘንጋት ይከተለዋል ያልተመጠነ&nbsp;ትምህርትም ተናጋሪውንም ሰሚውንም ወደ መሰልቸት ያደርሳል።</div>\n<div>✳.መመጠን(አለማርዘም):-በመጠን ኑሩ&rdquo; 1ኛ ጴጥ 5&divide;8</div>\n<h2><strong>⓬.ድምፁን መመጠን</strong></h2>\n<div>✔ .ሰባኪው ድምጹ ለአድማጭ የሚረብሽና የሚሰቀጥጥ በጣም የሚጮኽ&nbsp;ወይም በጣም ፈዘዝ ያለ ለአድማጭ የሚሰለች መሆን የለበትም።</div>\n<div>✔ .በጣም መፍጠንም በጣም መጐተትም የለበትም፣ ትክክለኛና&nbsp;አግባብ ባለው ሁኔታ መሆን አለበት።</div>\n<h3><strong>⓭.የሰባኪውና አድማጩ ግንኙነት</strong></h3>\n<div>✳.ተረጋግተው ቦታቸውን እስኪይዙ ድረስ መጠበቅ አለበት፡፡</div>\n<div>✳.የአድማጮቹን ሁኔታ ይገምታል፡፡</div>\n<div>✳.ከዚያ በኋላ ለዕለቱ ተስማሚ በሆነ ርዕስ ትምህርቱን ይጀምራል፡፡</div>\n<div>✳.ዓይንን በአንድ ቦታ ላይ ወይም በአንድ ሰው ላይ ሳይተክሉ&nbsp;በአራቱም አቅጣጫ ቃኘት በማድረግ ሰልችቷቸው ወይም ደክመው&nbsp;እንዳይሆን ወይም አልገባቸው እያለ እንዳይሆን በዓይን ሁሉንም&nbsp;አድማጭ ለመቆጣጠር መሞከር እና በሚሰብክበት ጊዜ ጣራ ጣራ&nbsp;ወይም ወደ መሬት ማየት የለበትም፡፡</div>\n<h3><strong>⓮.ራስን መሆን</strong></h3>\n<div>✳.ሌሎችን ለመሆን መጣር የለበትም።</div>\n<h3>⓯.የተሰባክያንን ሥነ ልቡና መረዳት</h3>\n<div>✳.በመማር ማስተማር ሂደት ውስጥ የተሰባክያንን ስነ ልቡና ማወቅና መረዳት&nbsp;ለሰባኪው ከፍተኛ ጥቅምና እንደ ዋና ግብዓት ሆኖ የሚያገለግል ጉዳይ ነው።</div>\n<div>✳.ይህም ማለት የተሰባኪያንን ሥነ ልቡና መረዳት ሲባል</div>\n<div>\n<ul style=\"list-style-type: square;\">\n<li>መዳን እንደሚወዱ ማወቅና ጠይቆ መረዳት፤</li>\n<li>እንደራባቸው ምግብ እንደሚያስፈልጋቸው መረዳት፤</li>\n<li>ጥያቄ ሊጠይቁ እንደፈለጉ መረዳት፤</li>\n</ul>\n</div>\n<div><strong>አስተውሉ:-</strong>እናንተን የሚጠይቋችሁ ሰዎች አራት አይነት አመለካከት&nbsp;ያላቸው መሆኑን አትዘንጉ።<strong>እነርሱም</strong>:-</div>\n<div>&nbsp;</div>\n<div>\n<ul style=\"list-style-type: square;\">\n<li><em><strong>፩ኛ.መምህራንን ለመፈተሽ የሚጠይቁ፤</strong></em></li>\n<li><em><strong>፪ኛ.ያውቀዋል ነገር ግን ሌሎች እንዲያውቁት ፈልጎ የሚጠይቅ፤</strong></em></li>\n<li><em><strong>፫ኛ.ያውቀዋል፤ነገር ግን ተጨማሪ ፈልጎ የሚጠይቅ፤</strong></em></li>\n</ul>\n<p><em><strong>፬ኛ.ምንም አያውቅም፤ስለዚህ ለማወቅ የሚጠይቅ እንዳለ መዘንጋት የለባችሁም።</strong></em></p>\n<h3><span style=\"text-decoration: underline;\"><em><strong>🔆.የተሰባኪያንን ሥነ ልቡና የመረዳት ጠቀሜታው</strong></em></span></h3>\n</div>\n<div>❶.ከአድማጩ ለመተቼት ለመዳን፤</div>\n<div>❷.ከመፍረድ ለመዳን፤</div>\n<div>❸.ከማጽደቅና ከመኮነን ለመዳን...ወዘተ.....</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;\"<em><strong>እንደካርቦን አትሁን\" ለምን ካልከኝ ካርቦን የጻፉለትን ኮፒ ያደርጋልና።</strong></em></div>\n<h3><strong>⓰.ንግግሩ ኦርቶዶክሳዊ ላህይ ያለው መሆን አለበት፡፡</strong></h3>\n<div>💠. ሰባኪው የቤተ ክርስቲያን መቃን የመታው የቤተ ክርስቲያንን&nbsp;ቋንቋ በሚገባ የሚያውቅ መሆን አለበት፡፡</div>\n<div>💠.ጸያፍ የሆኑና ኦርቶዶክሳዊ ያልሆኑ ቃላትን መጠቀም የለበትም፡፡</div>\n<div>💠.የማይመጥኑ ወይም ለአድማጩ ከባድ የሆኑ ቃላትን መጠቀም&nbsp;የለበትም።</div>\n<div>💠.ጠንከራ ስሜት ማለትም በቀላሉ የማይበርድ ስሜት ሊኖረው&nbsp;ይገባል።</div>\n<div>💠.አለምልሞ መገሰፅ የሚችል መሆን አለበት።ይህ ማለት አስተምሮ&nbsp;መምክርና መገሰፅ አለበት ማለት ነው።ጌታችን ኒቆዲሞስን&nbsp;አስተምሮ እንደገሰፀው።</div>\n<h3><strong>⓱.ቁረጠኝነት ሊኖረው ይገባል</strong></h3>\n<div>✳. &ldquo;የሎጥን ሚስት አስቧት&rdquo;ሉቃ 17&divide;32</div>\n<div>✳.ያለቆረጠ ሰው የትም መድረስ አይችልም፡፡</div>\n<div>&ldquo;በዚያን ጊዜ ኢየሱስ ለደቀ መዛሙርቱ እንዲህ አለ፦ እኔን መከተል&nbsp;የሚወድ ቢኖር ራሱን ይካድ መስቀሉንም ተሸክሞ ይከተለኝ።ነፍሱን&nbsp;ሊያድን የሚወድ ሁሉ ያጠፋታል፤ስለ እኔ ግን ነፍሱን የሚያጠፋ ሁሉ&nbsp;ያገኛታል።&rdquo; ማቴ 16&divide;24</div>\n<div>✳. \"<em><strong>መኑ የሀድገነ ፍቅሮ ለክርስቶስ</strong></em>\"ሮሜ 8 እንዳለ ቅዱስ ጳውሎስ&nbsp;ሰባኪ መምህርም ለዓላማው እስከሞት ድረስ የታመነ መሆን አለበት።</div>\n<div>\n<h2>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; የኦርቶዶክሳዊ ስብከት መለያ ጠባያት</h2>\n</div>\n<div><strong>መጽሐፋዊ፦</strong></div>\n<div>\n<ul>\n<li>✳.መጽሐፍ ቅዱስ</li>\n<li>✳.አዋልድ መጻሕፍት</li>\n<li>✳.ታሪካዊ መጻሕፍት</li>\n</ul>\n<p><strong>ነገረ መለኮታዊ፦</strong></p>\n<ul>\n<li>✳.ምሥጢራተ ቤተ ክርስቲያን</li>\n<li>✳.ሥርዓተ ቤተ ክርስቲያን</li>\n<li>✳.ሐዋርያዊ ትውፊት</li>\n<li>✳.ዶግማ</li>\n</ul>\n</div>\n<div><strong>✳.ማኅበራዊ ኑሮን በተመለከተ</strong></div>\n<div><strong>✳.ሥርአተ ቤተ ክርስቲያን</strong></div>\n<div><strong>✳.ጥምረት ያለው የስብከት አይነት አለ።</strong></div>\n<div>&nbsp;</div>\n<h2>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;የስብከት ዝግጅት</h2>\n<div>💠.አንድ ስብከት ፮ ንዑሳን ዘርፎችና&nbsp;ክፍሎች አሉት።እነርሱም:-</div>\n<div>\n<ul style=\"list-style-type: circle;\">\n<li>✳.ርእስ</li>\n<li>✳.ዓላማ</li>\n<li>✳.መግቢያ</li>\n<li>✳.ዋና ክፍል</li>\n<li>✳.ምክር</li>\n<li>✳.መደምደሚያ</li>\n</ul>\n</div>\n<h2>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; የስብከተ ወንጌል መንገዶች</h2>\n<div>❖.የስብከተ ወንጌል መንገዶችን በሚከተሉት መንገዶች መክፈል&nbsp;ይቻላል።እነርሱም:-</div>\n<div>\n<ul>\n<li>.ብቻ ለብቻ (ለአንድ ሰው)፣</li>\n<li>.ለጥቂት ሰዎች (ለደቀ መዛሙርት፣ ለቤተሰቦች)፣</li>\n<li>.ለሕዝብ፣</li>\n<li>.ለማኅበር፣ . . .</li>\n</ul>\n</div>\n<h3>❶.ለህዝብ:-</h3>\n<div>✳.&ldquo;ሕዝቡንም አይቶ ወደ ተራራ ወጣ፤ በተቀመጠም ጊዜ ደቀ መዛሙርቱ&nbsp;ወደ እርሱ ቀረቡ፤ አፉንም ከፍቶ አስተማራቸው እንዲህም አለ፦ . . .&rdquo;ማቴ. 5፡1-2</div>\n<h3>❷.ብቻ ለብቻ(አንድ ለአንድ):-</h3>\n<div>\n<ul style=\"list-style-type: square;\">\n<li style=\"font-weight: bold;\"><strong>✳.ኒቆዲሞስ</strong></li>\n</ul>\n</div>\n<div>&ldquo;ከእነርሱ አንዱ በሌሊት ቀድሞ ወደ እርሱ መጥቶ የነበረ ኒቆዲሞስ፡-ሕጋችን አስቀድሞ ከእርሱ ሳይሰማ ምንስ እንዳደረገ ሳያውቅ በሰው&nbsp;ይፈርዳልን? አላቸው።&rdquo; ዮሐ. 7&divide;50-51</div>\n<div>\n<ul style=\"list-style-type: square;\">\n<li style=\"font-weight: bold;\"><strong>✳.ለሳምራዊቷ ሴት</strong></li>\n</ul>\n</div>\n<div>&ldquo;ሴቲቱም፡- ያደረግሁትን ሁሉ ነገረኝ ብላ ስለ መሰከረችው ቃል ከዚያች&nbsp;ከተማ የሰማርያ ሰዎች ብዙ አመኑበት። የሰማርያ ሰዎችም ወደ እርሱ&nbsp;በመጡ ጊዜ በእነርሱ ዘንድ እንዲኖር ለመኑት፤ በዚያም ሁለት ቀን ያህል&nbsp;ኖረ።\"ስለ ቃሉ ከፊተኞች ይልቅ ብዙ ሰዎች አመኑ፤ ሴቲቱንም። አሁን&nbsp;የምናምን ስለ ቃልሽ አይደለምእኛ ራሳችን ሰምተነዋልና፤ እርሱም</div>\n<div>በእውነት ክርስቶስ የዓለም መድኃኒት እንደ ሆነ እናውቃለን ይሏት&nbsp;ነበር።&rdquo;</div>\n<h3>❸.ለጥቂት ሰዎች (ለደቀ መዛሙርት፣ለቤተሰቦች)</h3>\n<div><strong>✳.ለጵርስቅላ እና አቂላ</strong></div>\n<div>&ldquo;በክርስቶስ ኢየሱስ አብረውኝ ለሚሠሩ ለጵርስቅላና ለአቂላ ሰላምታ አቅርቡልኝ፤እነርሱም ስለ ነፍሴ ነፍሳቸውን ለሞት አቀረቡ፣የአሕዛብም አብያተ ክርስቲያናት ሁሉ የሚያመሰግኑአቸው ናቸው እንጂ እኔ ብቻ አይደለሁም፤በቤታቸውም ላለች ቤተ ክርስቲያን ሰላምታ አቅርቡልኝ። ከእስያ ለክርስቶስ በኵራት ለሆነው ለምወደው&nbsp;ለአጤኔጦን ሰላምታ አቅርቡልኝ።&rdquo;</div>\n<div>ሮሜ 16&divide;3-5</div>\n<div><strong>✳.ልድያና ቤተሰቦቿ</strong></div>\n<div>&ldquo;ከትያጥሮን ከተማም የመጣች ቀይ ሐር ሻጭ እግዚአብሔርን የምታመልክ ልድያ&nbsp;የሚሉአት አንዲት ሴት ትሰማ ነበረች፤ ጳውሎስም የሚናገረውን ታዳምጥ ዘንድ ጌታ&nbsp;ልብዋን ከፈተላት።</div>\n<div>&ldquo;እርስዋም ከቤተ ሰዎችዋ ጋር ከተጠመቀች በኋላ። በጌታ የማምን እንድሆን&nbsp;ከፈረዳችሁልኝ፥ ወደ ቤቴ ገብታችሁ ኑሩ ብላ ለመነችን፤ በግድም አለችን።&rdquo;</div>\n<div>የሐዋ.ሥራ 16&divide;14-15</div>\n<h2>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;ሐዋርያዊነት</h2>\n<div><strong>✳.ሐዋርያ</strong> የሚለው ቃል \"<strong>ሖረ</strong>\" ሄደ ከሚለው የግዕዝ ግስ የተገኘ&nbsp;ሲሆን፤ሐዋርያ ማለት የተላከ፣የተመረጠና የተጠራ ማለት ነው።</div>\n<div><strong>✳.ሐዋርያዊነት፦</strong></div>\n<div>\n<ul style=\"list-style-type: square;\">\n<li>ለአገልግሎት የሚፋጠን፣</li>\n<li>የቅዱሳንን አሠረ ፍኖት (ፈለግ) የተከተለ፣</li>\n<li>ብቁና ንቁ የሆነ፣</li>\n<li>የሐዋርያዊነትን ተልዕኮ በተግባር የሚያሳይ ማለት ነው፡፡</li>\n</ul>\n</div>\n<div>💠.ሐዋርያዊነት:-ጌታችን ኢየሱስ ክርሰቶስ በኢየሩሳሌም፤በይሁዳ፤በሰማርያም ሁሉ እስከ ምድርም ዳርቻ ድረስ ምስክሮች ትሆናላችሁ\" ብሎ&nbsp;ባዘዘው አምላካዊ ቃል መሠረት \"ሑሩ ወመሐሩ\"ያለውን በተግባር&nbsp;መፈጸም ነው፡፡ሐዋ 1&divide;8</div>\n<h2><strong>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;ሐዋርያዊ ሊጠነቀቅባቸው የሚገቡ ጉዳዮች</strong></h2>\n<div>🔆..በሐዋርያነት መስክ የተሰማራ ማንኛውም አገልጋይ ጌታ&nbsp;እንደተናገረው \"በተራራ ላይ ያለ መብራት\" ማለት ነው፡፡በሌላ አነጋገር \"የክርስትና ባንዲራ ነው\" ምእመናን የሚያጠፋት ጥፋትና&nbsp;እርሱ የሚያጠፋው በሰውም ዘንድ በእግዚብሔርም ዘንድ በእኩል&nbsp;አይታይም አይመዘንም፡፡</div>\n<div>🔆..የጌታውን ፈቃድ አውቆ እንደ ፈቃዱ የማይሠራና የማይዘጋጅ የዚያ&nbsp;አገልጋይ ቅጣቱ ብዙ ነው\"ሉቃ 12&divide;17 ተብሎ እንደተነገረው ኃለፊነት&nbsp;ተቀብሎ አውቆ የሚያጠፋ የቅጣቱ መጠን ከባድ መሆኑን መረዳት ተገቢ&nbsp;ነው።</div>\n<h3>💠.አንድ ሐዋርያዊ ሊጠነቀቅባቸው ከሚገቡ ዓበይት ጉዳዮች መካከል:-</h3>\n<h3><strong>❶.ወገንተኝነት፡-</strong></h3>\n<ul>\n<li>የቆሮንቶስንና የፊልጵስዮስን አብያተ ክርስቲያናት ያውኳቸው ከነበሩ ጉዳዮች መካከል እንዱ ወገንተኝነት ነው፡፡</li>\n</ul>\n<div>\n<p><em><strong>&nbsp;በቆሮንቶስ ቤተ ክርስቲያን፡</strong></em>-</p>\n<ul>\n<li>እኔ የጳውሎስ ነኝ፣</li>\n<li>እኔ የኬፋ ነኝ፣</li>\n<li>እኔ የአጵሎስ ነኝ የሚል ክፍፍል ነበር ።፩ኛ ቆሮ 3&divide;1-12</li>\n</ul>\n</div>\n<div><strong>✳.ወገንተኝነት በብዙ መንገድ ይገለጣል፡-</strong></div>\n<div>✔ .የፓለቲካ ወገንተኝነት፣</div>\n<div>✔ .የቋንቋና የብሔር፣</div>\n<div>✔ .የአካባቢ ተወላጀነት፣</div>\n<div>✔ .ቡድን በመሰብሰብ፣</div>\n<h3>❷.ከንቱ ውዳሴ</h3>\n<div>🔆..አውቀውትም ሳያውቁትም ብዙዎችን ከፀጋው ዙፋን ያራቆተ፣ዓላማቸውን ያሳተ፣አቅጣጫቸውን ያሰናከለ ከባድ የሆነ የበጐ ሕሊና&nbsp;ወጥመድና እንቅፋት ነው፡፡</div>\n<div><strong>\"የታዘ</strong><em><strong>ዛችሁትን ሁሉ ባደረጋችሁ ጊዜ የማንጠቅም ባሪያዎች&nbsp;ነን፣ልናደርገው የሚገባንን አድርገናል በሉ</strong></em>\"ሉቃ 17&divide;10&nbsp;በማለት ጌታችን ደቀ መዛሙርቱን የመከረው፡፡</div>\n<div>&nbsp;</div>\n<div><strong>✳.ከንቱ ውዳሴ:-</strong></div>\n<div>✔ .ያለ እኔ ሰው የለም የሚያሰኝ፤</div>\n<div>✔ .ትዕቢትንና ትምክህትን የሚያስከትል፣</div>\n<div>✔ .ከያዘ የማይነቀል መርዛማ ሥር ነው፡፡ሉቃ 18&divide;9፣ማቴ 6&divide;2-18</div>\n<h3>❸.ለምድራዊ ጥቅም ማገልገል፡-</h3>\n<div>✳.በዚህ ዘመን ቤተክርስቲያንን በእጅጉ የተፈታተናት፣ አንዳንዶችም&nbsp;ያመኑትን ፈጣሪና የታመኑለትንና ያመነቻቸውን ቤተክርስቲያን ከመክዳት&nbsp;አልፈው የጫማ ውስጥ ጠጠርና የጭቃ ውስጥ እሾህ እንዲሆኑባት&nbsp;ያደረገው የጥቅም ፍላጐት ነው፡፡</div>\n<div>↪ በቤተክርስቲያን ታሪክ ለንዘብ ማገልገል \"<strong>ሲሞኒዝም</strong>\" ይባላል።ሐዋ 8&divide;9-25</div>\n<div><em><strong>➡.ሲሞኒዝም በዚህ ዘመን፡-</strong></em></div>\n<div><em>❖.በኑሮ ደካማ መሆንና የማኅበራዊ ኑሮ ጫና፤</em></div>\n<div><em>❖.ለገንዘብ ብቻ ሲሉ ማገልገል፤</em></div>\n<div><em>❖.ጥሩ ክፍያ ይከፍሉኛል የሚሉትን ብቻ መርጦ ማገልገል(የነፍስና የሥጋ ብለው ጉባኤያትን እስከ መክፈል ደርሰዋል)፤</em></div>\n<div><em>❖.ሀብታሞችን ብቻ ማሰባሰብ፤</em></div>\n<h3><strong>❹.ከፈተናዎች መጠበቅ</strong></h3>\n<div>✳.ከፈተናዎች መጠበቅ ሲባል</div>\n<div>\n<ul>\n<li>.ከመናፍቃንና ከአህዛብ፤</li>\n<li>.ከራስ ጋር፤ .ከወግ አጥባቂዎች፤</li>\n<li>&nbsp;.ጥቅማችን ሊነካ ይችላል ብለው ከሚያስቡ ግለሰቦች፤</li>\n<li>.ከባለስልጣናት ወዘተ ብዙ መከራና ግፍ ስለ ክርስቶስ ስም ይደርሳል።</li>\n</ul>\n<h2>ማጠቃለያ፦</h2>\n</div>\n<div>\n<ul style=\"list-style-type: square;\">\n<li>&nbsp; &nbsp; &nbsp;ከላይ ከብዙዉ በጥቂቱ ለመግለጽ እንደሞከርነው በአጠቃላይ በዚህ ኮርስ የስብከት አመጣጥ ከሐዋርያት፣ሐዋርያነ አበዉ፣ዛሬም በዘመነ ሊቃዉንት እየተሰበከ ያለ ሲሆን ስለ ትክክለኛ ሐዋርያዊነት እና አገልግሎቱ&nbsp; ምን ይመስላል ትክክለኛ ሐዋርያዊነትስ ምን ይመስላል የሚለዉን የሚዳስስ ትምህርት ነው።&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&nbsp;</li>\n</ul>\n</div>\n<div>\n<h2>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;</h2>\n<h2>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;ዋቢ መጻሕፍት</h2>\n<div>\n<div>\n<ul style=\"list-style-type: square;\">\n<li>የስብከት ዘዴ&hellip;(በብጹዕ አቡነ ጎርጎርዮስ የሽዋ ሀገረ ስብከት ሊቀ ጳጳስ የነበሩ)&nbsp;</li>\n<li>ሐዋርያዊ ተልዕኮ(ሐዋተ) ...ለግቢ ጉባኤያት የተዘጋጀ(በዲ.ዳንኤል ክብረት</li>\n</ul>\n<p>&nbsp;</p>\n</div>\n</div>\n<h2>&nbsp; ስብሐት ለእግዚአብሔር ወለወላዲቱ ድንግል ወለመስቀሉ ክቡር ይቆየን!!!</h2>\n</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;</div>\n<div>&nbsp;</div>\n<div>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; <strong>&nbsp; <em>አዘጋጅ፦ዲ/ን ሶፎንያስ ደመቀ(ወ/ጊዮርጊስ)&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&nbsp;</em></strong></div>\n<div><strong><em>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; +251927603731/+251935535937</em></strong></div>\n<div><strong><em>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 27/10/2018ዓ.ም</em></strong></div>\n<h2>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&nbsp;</h2>', 'ሐዋርያዊ ተልዕኮ', 'Advanced', 'uploads/course_media/images/1782568307_caa0ff9a.jpg', '250.00', 'ዲ/ን ሶፎንያስ ደመቀ', 'uploads/course_media/pdfs/1782568307_60c6388f.pdf', '', '', '', '', '', '<section class=\"builder-output-card\"><h4>ሐዋርያዊ ተልዕኮ እና ኦርቶዶክሳዊ የስብከት ዘዴ</h4><ul><li>የስብከት ምንነት እና ትርጉም</li><li>የስብከት ታሪክ</li><li>የስብከት አይነቶች</li><li>የመልካም መምህር(ሰባኪ) ባህርያት</li><li>የስብከት ዝግጅት</li><li>የስብከተ ወንጌል መንገዶች</li><li>ሐዋርያዊነት</li><li>ሐዋርያዊ ሊጠነቀቅባቸው የሚገቡ ጉዳዮች</li></ul></section><section class=\"builder-output-card\"><h4>Untitled Module</h4><p class=\"small\">No lessons added yet.</p></section>', '<article class=\"builder-output-card\"><h4>1.ከሚከተሉት ዉስጥ ለሰባኪ የማያስፈልገዉ የቱ ነው?</h4><ol><li>የስብከት ርእስ መጠበቅ</li><li>አርአያ ክህነት</li><li>ራስን መስበክ</li><li>ትምህርት</li></ol><p><strong>Answer:</strong> ራስን መስበክ</p><p class=\"small\">ሌሎች ከላይ የተዘረዘሩት ለአንድ ሰባኪ የሚያስፈልጉ ነገሮች ሲሆኑ ራስን መስበክ ግን የትክክለኛ ሰባኪ መገለጫ አይደለም።</p></article><article class=\"builder-output-card\"><h4>2.ትምህርት፣ትምህርታዊ ስብከት እና ስብከት ከሚለያዩባቸው ነገሮች መካከል ትክክል የሆነው የቱ ነው?</h4><ol><li>ትምህርታዊ ስብከት እዉቀት እና ሕይወት በአንድ ጊዜ የሚያስጨብጥ ነው።</li><li>ትምህርት ቀለል ባለ አገላለጽ የሚቀርብ ነው።</li><li>ስብከት ተከታታይነት ያለው ነው።</li><li>ሁሉም</li></ol><p><strong>Answer:</strong> ትምህርታዊ ስብከት እዉቀት እና ሕይወት በአንድ ጊዜ የሚያስጨብጥ ነው።</p><p class=\"small\">መልሱ ትምህርታዊ ስብከት እዉቀት እና ሕይወት በአንድ ጊዜ የሚያስጨብጥ ነው የሚለው ሲሆን ሌሎቹ በትክክል አልተዛመዱም።</p></article><article class=\"builder-output-card\"><h4>3.ከሁሉም የስብከት አይነቶች በደንብ ማብራራት ፣መመሰል እና ማመሳጠር የሚፈልገው የትኛው ነው?</h4><ol><li>ተዉኔታዊ ስብከት</li><li>ታሪካዊ ስብከት</li><li>ተግባራዊ ስብከት</li><li>ነገረ ሃይማኖታዊ ስብከት</li></ol><p><strong>Answer:</strong> ነገረ ሃይማኖታዊ ስብከት</p><p class=\"small\">ነገረ ሃይማኖታዊ ስብከት በተለዪ ነገረ መለኮት፣ምሥጢረ ሥላሴ እንዲሁም ምሥጠረ ሥጋዌ ከሌሎች የስብከት አይነቶች መካከል ለየት የሚያደርገው ግልጽ የሆነ ምሳሌ ማብራሪያ ምሥጢር ማመስጠርን ይጠይቃል።</p></article><article class=\"builder-output-card\"><h4>4.አንድ ሰባኪ ያስተማረዉን ትምህርት ተግባራዊ እንዲሆን መንገዱን የሚያሳዪበት፣ዕለት ከዕለት የሰማዕያኑ ሕይወት ጋር የሚያዛምድበት የስብከት ክፍል ምን ይባላል?</h4><ol><li>ሀተታ</li><li>ምክር</li><li>ዓላማ</li><li>መግቢያ</li></ol><p><strong>Answer:</strong> ምክር</p><p class=\"small\">አንድ ስብከት 6 ንዑሳን ዘርፎችና ክፍሎች ሲኖሩት ምክር የሚባለው ሰባኪ ያስተማረዉን ትምህርት ተግባራዊ እንዲሆን መንገዱን የሚያሳዪበት፣ዕለት ከዕለት የሰማዕያኑ ሕይወት ጋር የሚያዛምድበት የስብከት ክፍል ነው።</p></article>', '<article class=\"builder-output-card\"><h4>ሐዋርያዊ ተልዕኮ</h4><p>የዚህ አሳይመንት ዋና ዓላማ ተማሪዎች ስለ ሐዋርያዊ አገልግሎት እና ሕይወቱ ከዚህ ኮርስ በተጨማሪ  ሰፊ ግንዛቤ እንዲያገኙ ስለሆነ እርስዎም  እባክዎ ይህንን አሳይመንት ሲሰሩ ቅዱሳት መጻሕፍትን አንብበው ከአባቶች አሰረ ፍኖት ተምረዉ እንዲሰሩ እመክርዎታለሁ።</p><p class=\"small\">Due: የመግቢያ ቀን ማጠቃለያ ፈተና ከመውሰድዎ በፊት እንዲያስገቡ • Points: 15%</p></article>', '<p>እባክዎ ሕጋዊ ሰርተፊኬት ለመዉሰድ ኮርሶችን፣ፈተናዎችን እና አሳይመንቶችን በአግባቡ መዉሰድ እና ቢያንስ 80% ፕሮግረስ መሆን አለበት።እነዚህን ነገሮች ያላሟላ ተማሪ አቤቱታ እና ቅሬታ ቢያቀርብ ተጠያቂ አይደለሁም።</p>', '2026-06-27 16:51:47', NULL, NULL);

DROP TABLE IF EXISTS `discussion_posts`;
CREATE TABLE `discussion_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `topic` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `author_name` varchar(255) NOT NULL,
  `author_role` varchar(50) NOT NULL DEFAULT 'Guest',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `discussion_posts` (`id`, `topic`, `message`, `author_name`, `author_role`, `created_at`) VALUES ('1', 'ምሥጢረ ሥላሴ', 'ኩነት ምንድን ነው?', 'sofonyas', 'Admin', '2026-06-23 22:56:20');

DROP TABLE IF EXISTS `discussion_replies`;
CREATE TABLE `discussion_replies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `author_name` varchar(255) NOT NULL,
  `author_role` varchar(50) NOT NULL DEFAULT 'Guest',
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  CONSTRAINT `discussion_replies_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `discussion_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `email_notifications`;
CREATE TABLE `email_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `sent_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `email_notifications` (`id`, `admin_id`, `recipient_email`, `subject`, `message`, `sent_at`) VALUES ('1', '1', 'yilkaldemeke21@gmail.com', 'የውይይት ፎርም አዲስ ልጥፍ', 'አዲስ የውይይት ልጥፍ ተሰብስቧል። ርዕስ: ምሥጢረ ሥላሴ በ sofonyas አማካኝነት', '2026-06-23 22:56:20');

DROP TABLE IF EXISTS `email_verification_tokens`;
CREATE TABLE `email_verification_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email_verification_token` (`token`),
  KEY `idx_email_verification_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `email_verification_tokens` (`id`, `email`, `token`, `expires_at`, `used`, `created_at`) VALUES ('1', 'yilkaldemeke212@gmail.com', 'f4ee1d13d4e47613d214714785566cbb390e85d4758c5d1d05cfa613dbb8b13b', '2026-06-24 21:16:43', '0', '2026-06-23 22:16:43');
INSERT INTO `email_verification_tokens` (`id`, `email`, `token`, `expires_at`, `used`, `created_at`) VALUES ('2', 'yilkaldemeke21@gmail.com', 'b7eed2d05325b4fac61217d6c2c7858faaeae36aef6d410d340f312173b1fbbf', '2026-06-24 21:18:39', '0', '2026-06-23 22:18:39');
INSERT INTO `email_verification_tokens` (`id`, `email`, `token`, `expires_at`, `used`, `created_at`) VALUES ('3', 'yilkaldemeke21@gmail.com', 'cdf5a278fb9cdfcfec019d6af2f48aea8def119baa0080bac0e5de1182779812', '2026-06-27 00:29:02', '0', '2026-06-26 01:29:02');

DROP TABLE IF EXISTS `event_announcements`;
CREATE TABLE `event_announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_title` varchar(255) NOT NULL,
  `event_description` text NOT NULL,
  `event_date` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `content_type` varchar(30) NOT NULL DEFAULT 'announcement',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `exam_access_codes`;
CREATE TABLE `exam_access_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_type` varchar(50) NOT NULL,
  `access_code` varchar(50) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exam_access_code` (`exam_type`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `exam_access_codes` (`id`, `exam_type`, `access_code`, `is_active`, `created_by`, `created_at`) VALUES ('1', 'exam20', 'SOFI2721', '1', 'sofonyas', '2026-06-25 12:37:36');

DROP TABLE IF EXISTS `exam_reminders`;
CREATE TABLE `exam_reminders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(100) NOT NULL,
  `exam_type` varchar(100) NOT NULL,
  `exam_date` datetime NOT NULL,
  `reminder_message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `exam_submissions`;
CREATE TABLE `exam_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(100) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `exam_type` varchar(50) NOT NULL,
  `score` int(11) NOT NULL DEFAULT 0,
  `total_questions` int(11) NOT NULL DEFAULT 0,
  `answers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`answers`)),
  `submitted_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `exam_submissions` (`id`, `student_id`, `student_name`, `exam_type`, `score`, `total_questions`, `answers`, `submitted_at`) VALUES ('1', '027', 'Yilkal Demeke', 'exam20', '7', '30', '[{\"question\":\"13.አብ ቀባዒ፣ወልድ ተቀባዒ እና መንፈስ ቅዱስ ቅብዕ የሚለዉ አስተምህሮ እንዴት ይታያል?\",\"selected\":\"x\",\"correct\":\"በመጀመሪያ ይህ አስተምህሮ የቅባት እምነት ተከታዮች አስተምህሮ ሲሆን -ይህ ማለት-አብ-አክባሪ  -ወልድ ከባሪ -መንፈስ ቅዱስ ክብር ማለት ነው።አሁን አሁን ከሆነ በምሥጢረ ሥላሴ ትምህርትና ምስጢረ ሥላሴ መለየት ተስኖናል ይመስለኛል::በምስጢረ ሥላሴ ትምህርት ሥላሴ በክብር አንድ አንድ ናቸው ብለናል።ታዲያ ↪ ሥላሴ አብ ወልድ መንፈስ ቅዱስ በክብር አንዲት ናቸው ካልን አብ አክባሪ፣ወልድ ከባሪ መንፈስ \",\"is_correct\":false,\"type\":\"short_answer\"},{\"question\":\"12.\\\"አፍቀርከ ጽድቀ ወዓመጻ ጸላእከ በእንተዝ ቀብዐከእግዚአብሄር አምላክከ ቅብዐ ትፍሥህት እምእለ ከማከ-ጽድቅን ወደድህ አመጻን ጠላህ ስለዚህ ከባልንጀሮችህ ይልቅ እግዚአብሔር አምላክ የደስታ ዘይትን ቀባህ\\\"መዝ 44÷7  ሲል ምን ማለቱ ነው?\",\"selected\":\"d\",\"correct\":\"በመጀመሪያ\\\"ጽድቅን ወደድህ አመጻን ጠላህ ስለዚህ ከባልንጀሮችህ ይልቅ እግዚአብሔር አምላክ የደስታ ዘይትን ቀባህ\\\"በሚለውኃይለ ቃል ውስጥ \\\"አንተ\\\"ተብሎ በውስጠ ታዋቂ የተጠራ እንዳለ እናስተውል።ይኸውም \\\"ትስብእት\\\"ነው።➡ጽድቅን ወደድክ አመጻን ጠላህ ማለትስ ታልከኝ ሰው መሆንን ወደድክ አንድም ትንቢተ ነቢያትን መፈጸምን ወደድክ ወድድክ ሰው አለመሆንን ጠላህ ማለቱ ነው!!ሰው መሆንን ወደድክ የተባለው ማነው\",\"is_correct\":false,\"type\":\"short_answer\"},{\"question\":\"11.ወልድ ለአብ ልጁ ከሆነ ለመንፈስ ቅዱስ ምኑ ነው?\",\"selected\":\"z\",\"correct\":\"ወልድ ለአብ ልጁ ከሆነ ለመንፈስ ቅዱስ ቃሉ ነው።\",\"is_correct\":false,\"type\":\"short_answer\"},{\"question\":\"10.የምሥጢረ ፈጣሪ እና ምሥጢረ ፍጡራን ልዩነታቸው ምንድን ነው?\",\"selected\":\"x\",\"correct\":\"ምሥጢረ ፍጡራን:-ምሥጢረ ፍጡራን ማለት በፍጡራን ውስጥ ያለ የማይታወቅ ነገር ነው።እርሱ ሦስት ወገን ነው።እነርሱም:-ምሥጢረ ሰማይ ወምድር፤ምሥጢረ ሰብእ፤ምሥጢረ መላእክት ሲሆኑ ምሥጢረ ፈጣሪ(እግዚአብሔር) የምንለው ደግሞ ዓለምን እምኀበ አልቦ መፍጠሩ፤አንድ ሲሆን ሦስት፤ሦስት ሲሆኑ አንድ ቀዳማዊነቱ፤ርቀቱ፤ምልዓቱ ይህንን ዓለም እምኀበ አልቦ ኀበ ቦ ከኢምንት ኀበ ምንት የፈጠረበት ረቂቅ ምስጢር ነው።\",\"is_correct\":false,\"type\":\"short_answer\"},{\"question\":\"9.እግዚአብሔር ወልድ በ30 ዘመኑ ከተጠመቀ ዛሬ ህፃናት ለምን በ40 እና በ80 ቀን ለምን ይጠመቃሉ?\",\"selected\":\"x\",\"correct\":\"አዳም ወደ ገነት የገባዉ በ40 ቀኑ ሄዋን ደግሞ በ80 ቀኗ ስለሆነ ነው\",\"is_correct\":false,\"type\":\"short_answer\"},{\"question\":\"8.ተዓቅቦ ማለት ምን ማለት ነው?\",\"selected\":\"x\",\"correct\":\"ተዓቅቦ:-ማለት መለኮት ርቀቱን፤ስፋቱን፤ምልዓቱን፤አይመረመሬነቱን፤ አምላክነቱን ሳይለቅ ባለመጣፋፋት በመጠባበቅ በስጋ ርስት መመርመር ፤መግዘፍ፤መወሰን;ስጋም የሚመረመር፤ረቂቅ፤ግዙፍ፤ውሱንነቱን ሳይለቅ ባለመጠፋፋት በመጠባበቅ በመለኮት ገንዘብ የማይመረመር የማይታመም ረቂቅ፤ስፉህ፤ምልዑ መሆን ተዓቅቦ ይባላል።\",\"is_correct\":false,\"type\":\"short_answer\"},{\"question\":\"7.በነገረ ሥጋዌ ትምህርት አክባሪ፣ከባሪ እና ክብር የሚባሉ ነገሮች ለማን ተሰጥተው ይነገራሉ?\",\"selected\":\"x\",\"correct\":\"አክባሪነት➠አብ ወልድ መንፈስ ቅዱስ(አፋዊ ግብር) ፣ትስብእት(ስጋ)➠ከባሪ(የከበረ)፣ወልድ(ቃል) ክብር(ቅብዕ) ነው።\",\"is_correct\":false,\"type\":\"short_answer\"},{\"question\":\"6.የትንሣኤ ዘለሕይወት እና ትንሣኤ ዘለደይን ልዩነት ምንድን ነው?\",\"selected\":\"x\",\"correct\":\"ትንሣኤ ዘለሕይወት እና ትንሣኤ ዘለደይን ሁለቱ የፍርድ አይነቶች ሲሆኑ በትንሣኤ ዘጉባኤ ጊዜ ኃጥኣንን ለኃሳር ጻድቃንን ለክብር የሚጠራበት ነው።\",\"is_correct\":false,\"type\":\"short_answer\"},{\"question\":\"5.አምላክ እንዴት ሰው ሆነ?\",\"selected\":\"x\",\"correct\":\"አምላክ ሰው የሆነው እንበለ ቱሳሔ፣እንበለ ውላጤ፣እንበለ ቡአዴ፣እንበለ ትድምርት፣እንበለ ሚጠት፣እንበለ ተጋውሮ፣እንበለ ፍልጠት ነው።ታዲያ በአጭሩ ታዲያ ክርስቶስ ሰው የሆነው እንዴት ነው ትለኝ እንደሆነ በፍጹም ተዋህዶ ነው\",\"is_correct\":false,\"type\":\"short_answer\"},{\"question\":\"4.በዓለ ጥምቀት ወይም ልደት እሑድ ቢውል መቼ ይጾማል?\",\"selected\":\"x\",\"correct\":\"በዓለ ጥምቀት ወይም ልደት እሑድ ቢውል ቅዳሜ ጥሉላት አይበላም።“ገሃድ” አንድ ስለሆነ (ስንክሳር ጥር ፲ “ሰላም ለዕለት ዋሕድ ዘስሙ ገሃድ” እንዲል) የጥምቀትም ይሁን የልደት ገሃድ(ጋድ) አንድ ቀን ስለሆነ ዋዜማዉን ብቻ ይጾማል እንጂ ሁለት ቀን አይጾምም።\",\"is_correct\":false,\"type\":\"short_answer\"},{\"question\":\"3.በተዋሕዶ የከበረ ማን ነው?አክባሪዉስ?\",\"selected\":\"c\",\"correct\":\"በተዋህዶ የከበረ ስጋ ወይም ትስብእት ሲሆን አክባሪው ደግሞ ቃል(መለኮት) ነው።\",\"is_correct\":false,\"type\":\"short_answer\"},{\"question\":\"2.ንዴት እንዴት ጠፋ?\",\"selected\":\"a\",\"correct\":\" ንዴት ማለት በግድ(መራብ:መጠማት) ለፈቃዳት መገዛት ማለት ነው።ይህ እንዴት ጠፋ ሲባል ቃል ከስጋ ጋር ፈጽሞ በመዋሐዱ ነው። ☝ቅድመ ተዋህዶ ነዳይ የነበረ ስጋ በግድ የሚራብ የሚጠማ ሲሆን ከባለጸጋው መለኮት ጋር ሲዋሐድ ግን ሁሉ የእርሱ ሆነ።ይሁን እንጂ ተዋሕዶ ሁለትነትን እንጂ ንዴትን አላጠፋም የሚሉ አሉ;መጽሐፍ ግን አይልም።  ለዚህም ማስረጃ  ➡\\\"እርሱ መለኮት የተዋሐደውን ከእርሱ ጋር አንድ አካ\",\"is_correct\":false,\"type\":\"short_answer\"},{\"question\":\"1.የአንዲት ሃይማኖት መጠሪያ ስያሜ ማነው?መቼ የት አገኘ?\",\"selected\":\"h\",\"correct\":\"✔ .የአንዲት ሃይማኖት መጠሪያ ስያሜ በብሉይ ኪዳን \\\"ህዝበ እግዚአብሔር\\\"ይባሉ ነበር።የማያምኑትም አህዛብ ይባላሉ። ✔ .የአንዲት ሃይማኖት መጠሪያ ስያሜ በዘመነ አበው\\\"ክርስቲያን\\\"ይባሉ ነበር።ለዚህም ማስረጃ:- \\\"ወነበሩ አሐተ ዓመተ ኅቡረ በቤተ ክርስቲያን ወመሀሩ ለብዙኃን አህዛብ ወተሰምዩ አርድእት ክርስቲያን በአንጾኪያ ቀዲሙ\\\"ሐዋ 11 ÷26 ብሎ በመጀመሪያ በአንጾኪያ ክርስቲያን ተባሉ ብሎ ገልጾታል!\",\"is_correct\":false,\"type\":\"short_answer\"},{\"question\":\"10.በምሥጢረ ሥላሴ ትምህርት አካል ያለ ባህርይ፣ባህርይ ደግሞ ያለ አካል ተለይቶ አይገኝም እና ባህርይ በየአካላቸው እንዳላቸው መጽሐፍት ይናገራሉ።\",\"selected\":\"FALSE\",\"correct\":\"FALSE\",\"is_correct\":true,\"type\":\"true_false\"},{\"question\":\"9.የኢትዮጵያ ኦርቶዶክስ ተዋሕዶ ቤተ ክርስቲያን መሰረታዊ የምሥጢረ ሥላሴ ትምህርት አብ ቀባዒ፣ወልድ ተቀባዒ መንፈስ ቅዱስ ቅብዕ የሚል ነው።\",\"selected\":\"FALSE\",\"correct\":\"FALSE\",\"is_correct\":true,\"type\":\"true_false\"},{\"question\":\"8.በአፍአ ከሚደረጉ ነገሮች ጋር የማይገናኝ ጊዜያት እና ዘመናት፣ዓለማት እና ፍጥረታት፣ቀናት እና ሰዓታት፣ዓመታት እና ወራት በማይነገሩበት አብ ከወልድ ከመንፈስ ቅዱስ ሳይቀድም እና በባህርይዉ ሳይለይ በባህርይው ወልድን የወለደበት፣መንፈስ ቅዱስን ያሰረጸበት ግብር ዉሳጣዊ አካላዊ ግላዊ ግብር ይባላል።\",\"selected\":\"TRUE\",\"correct\":\"TRUE\",\"is_correct\":true,\"type\":\"true_false\"},{\"question\":\"7.ለምሥጢረ ሥላሴ ትምህርት ማስረጃ ሆነው ከሚያገለግሉ ፍጥረታት መካከል ሊቁ ቅዱስ ቄርሎስ የመሰለው የእሳት እና የብረት ተዋሕዶ ዋነኛ ማስረጃ ነው።\",\"selected\":\"TRUE\",\"correct\":\"FALSE\",\"is_correct\":false,\"type\":\"true_false\"},{\"question\":\"6.በጊዜ ተዋህዶ ተዋህዶ ሳይከብር ቢቀር ዜገ ያሰኛል፣አካለ ቃል ክብር አልባ ያሰኛል።\",\"selected\":\"FALSE\",\"correct\":\"TRUE\",\"is_correct\":false,\"type\":\"true_false\"},{\"question\":\"5.ክርስቶስ ቅድመ ተዋህዶ አንድ አካል አንድ ባህርይ ሲሆን በጊዜ ተዋህዶ መክፈል፣መዋሐድ እና መክበር ተፈጽመዋል።\",\"selected\":\"FALSE\",\"correct\":\"FALSE\",\"is_correct\":true,\"type\":\"true_false\"},{\"question\":\"21..በኦሪት ዘፍጥረት \\\"ሰውን እንደምሳሌያችንና እንደ መልካችን እንፍጠር ሲሉ ሥላሴ ይህ የምን ግብር ነው?\",\"selected\":\"A\",\"correct\":\"ሐ\",\"is_correct\":false,\"type\":\"multiple_choice\"},{\"question\":\"4.መንፈስ ቅዱስ ለአብ እና ለወልድ ሕይወታቸዉ ብቻ ሳይሆን ክብራቸዉ መንግስታቸዉ እስትንፋሳቸዉ ነው።\",\"selected\":\"FALSE\",\"correct\":\"FALSE\",\"is_correct\":true,\"type\":\"true_false\"},{\"question\":\"3.እምነት ማለት በዓይን ሳያዩ፤በጆሮ ሳይሰሙ ሳይመረምሩ መቀበል ነው።\",\"selected\":\"FALSE\",\"correct\":\"FALSE\",\"is_correct\":true,\"type\":\"true_false\"},{\"question\":\"2.አካላዊ ቃል ለተዋሐደው ስጋ ክብር ሆነው።\",\"selected\":\"FALSE\",\"correct\":\"TRUE\",\"is_correct\":false,\"type\":\"true_false\"},{\"question\":\"1.አካላዊ ቃል ከስጋ ጋር ሲዋሐድ አሐቲ ባህርየ ሥላሴ ተዋሐደች ማለት ይቻላል።\",\"selected\":\"FALSE\",\"correct\":\"FALSE\",\"is_correct\":true,\"type\":\"true_false\"},{\"question\":\"20.ከሚከተሉት ዉስጥ በተዋህዶ ጊዜ በትክክል የተዛመደው የቱ ነው?\",\"selected\":\"A\",\"correct\":\"ሀ\",\"is_correct\":false,\"type\":\"multiple_choice\"},{\"question\":\"19.አብ፣ወልድ፤መንፈስ ቅዱስ የሚለው የምን ስም ነው?\",\"selected\":\"C\",\"correct\":\"ሐ\",\"is_correct\":false,\"type\":\"multiple_choice\"},{\"question\":\"18.ከሚከተቱት አንዱ ልዩ የሆነው የቱ ነው?\",\"selected\":\"C\",\"correct\":\"ለ\",\"is_correct\":false,\"type\":\"multiple_choice\"},{\"question\":\"17.አብ በሁሉ የመላ ከሆነ ወልድና መንፈስ ቅዱስ በምን ይመላሉ?\",\"selected\":\"C\",\"correct\":\"ሐ\",\"is_correct\":false,\"type\":\"multiple_choice\"},{\"question\":\"16.በጊዜ ተዋሕዶ የከበረ ማን ነው?\",\"selected\":\"B\",\"correct\":\"ለ\",\"is_correct\":false,\"type\":\"multiple_choice\"},{\"question\":\"15.ሥላሴን በአካል ፫ ስንል ልብ ልንላቸው የሚገቡ ነገሮች የትኞቹ ናቸው?\",\"selected\":\"B\",\"correct\":\"መ\",\"is_correct\":false,\"type\":\"multiple_choice\"}]', '2026-06-26 04:30:49');

DROP TABLE IF EXISTS `lesson_bookmarks`;
CREATE TABLE `lesson_bookmarks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(100) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `lesson_title` varchar(255) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `instructor` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_student_lesson` (`student_id`,`lesson_id`),
  KEY `idx_lesson_bookmarks_student` (`student_id`),
  KEY `idx_lesson_bookmarks_lesson` (`lesson_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `lesson_bookmarks` (`id`, `student_id`, `lesson_id`, `course_id`, `lesson_title`, `course_name`, `instructor`, `created_at`, `updated_at`) VALUES ('1', '099', '1', '1', 'ነገረ ሃይማኖት', 'ነገረ ሃይማኖት', 'ዲ/ን ሶፎንያስ ደመቀ', '2026-06-24 00:15:32', '2026-06-24 00:15:32');
INSERT INTO `lesson_bookmarks` (`id`, `student_id`, `lesson_id`, `course_id`, `lesson_title`, `course_name`, `instructor`, `created_at`, `updated_at`) VALUES ('2', '027', '2', '2', 'ሐዋርያዊ ተልዕኮ', 'ሐዋርያዊ ተልዕኮ', 'ዲ/ን ሶፎንያስ ደመቀ', '2026-06-25 10:20:45', '2026-06-25 10:20:45');

DROP TABLE IF EXISTS `live_class_questions`;
CREATE TABLE `live_class_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `student_id` varchar(100) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `question` text NOT NULL,
  `answer` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_live_class_questions_session` (`session_id`),
  KEY `idx_live_class_questions_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `live_class_sessions`;
CREATE TABLE `live_class_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `session_date` datetime DEFAULT NULL,
  `stream_url` varchar(500) DEFAULT NULL,
  `room_url` varchar(500) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'scheduled',
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_live_class_sessions_date` (`session_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `live_class_sessions` (`id`, `title`, `description`, `session_date`, `stream_url`, `room_url`, `status`, `created_by`, `created_at`) VALUES ('1', 'negere melekot', '', '2026-06-25 23:48:00', NULL, NULL, 'scheduled', 'sofonyas', '2026-06-25 23:48:02');

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key_identifier` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_key_time` (`key_identifier`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `login_attempts` (`id`, `key_identifier`, `created_at`) VALUES ('12', 'admin_login:admin123:::1', '2026-06-23 22:29:40');
INSERT INTO `login_attempts` (`id`, `key_identifier`, `created_at`) VALUES ('3', 'student_login:027:127.0.0.1', '2026-06-23 21:41:56');
INSERT INTO `login_attempts` (`id`, `key_identifier`, `created_at`) VALUES ('4', 'student_login:027:127.0.0.1', '2026-06-23 21:47:40');
INSERT INTO `login_attempts` (`id`, `key_identifier`, `created_at`) VALUES ('2', 'student_login:036:127.0.0.1', '2026-06-23 21:41:40');

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `notifications` (`id`, `student_id`, `message`, `created_at`, `is_read`) VALUES ('1', '099', 'እርስዎ የጻፉት ውይይት ተመዝግቧል። አስተዳዳሪዎች መልስ ይሰጣሉ።', '2026-06-23 22:56:20', '0');

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_reset_token` (`token`),
  KEY `idx_password_reset_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `question_sections`;
CREATE TABLE `question_sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `instruction` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `question_sections` (`id`, `title`, `instruction`, `created_at`) VALUES ('1', 'ይህ የዲ/ን ሶፎንያስ ደመቀ ቤተ ገብርኤል ዌብሣይት የማጠቃለያ ፈተና ነው።', 'ይህ የምርጫ ጥያቄ ኮርሱን መሰረት አድርጎ የወጣ ሲሆን እባክዎ ቲክክለኛ መልስ የያዘዉን ፊደል ምረጡ።', '2026-06-26 00:02:08');
INSERT INTO `question_sections` (`id`, `title`, `instruction`, `created_at`) VALUES ('2', 'የምርጫ ጥያቄዎች', '', '2026-06-26 00:05:04');
INSERT INTO `question_sections` (`id`, `title`, `instruction`, `created_at`) VALUES ('3', 'ይህ የዲ/ን ሶፎንያስ ደመቀ ቤተ ገብርኤል ዌብሣይት የማጠቃለያ ፈተና ነው እንኳን ደህና መጡ።', 'ይህ የአጭር መልስ ኢንስትራክሽን ጥያቄ ሲሆን ኮርሱን መሰረት አድርጎ የወጣ ሲሆን እባክዎ ትክክለኛ መልስ እና ትምህርቱን መሰረት ያደረገ ተቀራራቢቢ መልስ ይስጡ።', '2026-06-26 02:31:10');
INSERT INTO `question_sections` (`id`, `title`, `instruction`, `created_at`) VALUES ('4', 'እንኳን ወደ ገብርኤል ደህና መጡ።የማስተዋል እና ትኩረትን የሚሹ ጥያቄዎች', 'እባክዎ ያስተዉሉ እነዚህ የማስተዋል አጭር መልስ ጥያቄዎች ሲሆኑ ማስተዋልን ይሻሉና እባክዎ ምላሽዎን ከነማስረጃዎ በሰፊዉ ይተንትኑና ማብራሪያ ይስጡበት።', '2026-06-26 03:19:14');

DROP TABLE IF EXISTS `questions`;
CREATE TABLE `questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_text` text NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) NOT NULL,
  `option_d` varchar(255) NOT NULL,
  `correct_answer` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `question_type` varchar(30) NOT NULL DEFAULT 'multiple_choice',
  `section_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('1', '1.እግዚአብሔር ወልድ ከቅድስት ድንግል ማርያም ከሥጋዋ ሥጋን ከነፍሷ ነፍስን ነስቶ ሲዋሐድ ለወልድ ብቻ የሚሰጥ ግብር የትኛው ነው?', 'ሥጋን መክፈል', 'ለሥጋ ክብር መሆን', 'ሥጋንና መለኮትን ማዋሐድ', 'ሥጋን ማክበር', 'ለ', '2026-06-24 04:01:14', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('2', '2.ሥጋ ከበረ ስንል ሁልጊዜ የምናስበው የትኛዉን ጊዜ ነው?', 'ድኅረ ተዋህዶ', 'ጊዜ ተዋህዶ', 'ቅድመ ተዋህዶ', 'ሁሉም', 'ሀ', '2026-06-24 04:04:18', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('3', '3.ስለምሥጢረ ሥላሴ ትክክል ያልሆነው የቱ ነው?', 'ሥላሴ ማለት ሦስት ማለት ነው።', 'አብ አምላክ፤ ወልድ አምላክ፤ መንፈስ ቅዱስ አምላክ፤ አንድ አምላክ', 'ሥላሴ በስም በአካል በግብር በኩነት ሦስት ሲሆኑ በመለኮት አንድ ናቸው።', 'መልስ የለም', 'ሀ', '2026-06-24 04:07:53', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('4', '4.ከሚከተሉት አንዱ ልዩ ነው።', 'መክፈል', 'መዋሐድ', 'መራብ', 'መክበር', 'ሐ', '2026-06-24 04:10:00', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('5', '5.ስለ እግዚአብሔር ወልድ ዳግም ልደትና የማዳን ስራ የሚያስረዳን ምሥጢር ምን ይባላል?', 'ትንሣኤ ዘክርስቶስ', 'ምስጢረ ሥጋዌ', 'ምሥጢረ ሥላሴ', 'ሀ እና ሐ', 'ለ', '2026-06-24 04:13:07', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('6', '6.ከሚከተሉት መካከል የቃል እና የስጋን ተዋህዶ የማይገልጸው የቱ ነው?', 'እንበለ ሚጠት', 'እንበለ ቡዐዴ', 'እንበለ ኅድረት', 'እንበለ ተዓቅቦ', 'መ', '2026-06-24 04:18:05', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('7', '7.ሃይማኖት ማለት በአንድ እግዚአብሔር በህላዌ መለኮቱ፤በፈጣሪነቱ፤ በመጋቢነቱ ፤በባህርይ ግብራቱ ማመን ለእርሱም\r\nለእርሱም መታመን ማለት ነው።የሚለው የሃይማኖት ትርጉም የምን ትርጉም ይባላል?', 'ሕይወታዊ ትርጉም', 'ምሥጢራዊ ትርጉም', 'ፊደላዊ ትርጉም', 'መዝገበ ቃላዊ ትርጉም', 'ለ', '2026-06-24 04:20:57', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('8', '8.ከሚከተሉት መካከል ስለ ምሥጢረ ሥላሴ ትክክል የሆነው የቱ ነው?', 'ወልድ ለባዊ በራሱ ነው።', 'መንፈስ ቅዱስ እና ወልድ አለባዉያን በአብ ናቸው።', 'አብ እና ወልድ ማኅየዊ በመንፈስ ቅዱስ ናቸው።', 'ወልድ እና መንፈስ ቅዱስ ለባዉያን በአብ ናቸው።', 'መ', '2026-06-24 04:29:44', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('9', '9..በደቡብ ወሎ ዞሮ በጣም በርካታ (ሰው) አለ። ከእነዚህም መካከል (ሶፎንያስ) በመቅደላ አምባ ዩኒቨርሲቲ የ4ኛ ዓመት የኮምፒዪተር ሳይንስ ተማሪ ነው፤ሶፎንያስ ለ12ኛ ክፍል፤ለ1ኛ ዓመትና ለሁለተኛ ዓመት ተማሪዎች የነገረ ሃይማኖት በመስጠት ለተወሰኑ ወራት (መምህራቸው) በመሆን ሲሰጥ ከቆዬ በኋላ አሁን ላይ ኮርሱን በሰላም አጠናቆ ፈተና በመስጠት ላይ ይገኛል።በቅንፍ የተቀመጡት ቃላት በቅደም ተከተል ምንን ያመለክታሉ?', 'የአካል ስም፤የባህርይ ስም፤የግብር ስም', 'የግብር ስም፤የባህርይ ስም፤የአካል ስም', 'የባህርይ ስም፤የአካል ስም፤የግብር ስም', 'የአካል ስም፤የባህርይ ስም፤የተዋህዶ ስም', 'ሐ', '2026-06-24 04:35:12', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('10', '10.\"በ\" እና \"እንደ\" የሚል ርስት ሰጥተን የምንናገረው መቸ ነው?', 'ድኅረ ተዋህዶ', 'ቅድመ ተዋህዶ', 'ጊዜ ተዋህዶ', 'ሁሉም', 'ሀ', '2026-06-24 04:38:50', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('11', '11.አፍአዊ ግብር ማለት ምን ማለት ነው?', 'በዓለመ ሥላሴ ብቻ የሚነገረ ወላዲ፤ተዋላዲ፤ሠራፂ የሚባሉት ናቸው።', 'ዓለምን የመፍጠር፣የመመገብ ፣የማሳለፍ ስራ ነው።', 'ሥላሴ በየግላቸው የሚጠሩበት ስራ ነው።', 'ሀ እና ለ', 'ለ', '2026-06-24 04:43:43', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('12', '12.ከሚከተሉት አንዱ እዉነት የሆነው የቱ ነው?', 'አብ ቀባኢ፣ወልደ ተቀባኢ፣መንፈስ ቅዱስ ቅብዕ -->በነገረ ሥጋዌ', 'አብ ቀባኢ፣ወልድ ቅብዕ፣መንፈስ ቅዱስ ቀባኢ-->በነገረ ሥጋዌ', 'አብ ቀባኢ፣ወልደ ተቀባኢ፣መንፈስ ቅዱስ ቅብዕ -->በምሥጢረ ሥላሴ', 'አብ ቅብዕ ፣ወልደ ቅብዕ፣መንፈስ ቅዱስ ቅብዕ -->በምሥጢረ ሥጋዌ', 'ለ', '2026-06-24 04:50:04', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('13', '13.በምሥጢረ ሥላሴ ትምህርት ስለ ኩነት ትክክል ያልሆነው የቱ ነው?', 'አብ ቃል፤ወልድ ልብ፤መንፈስ ቅዱስ እስትንፋስ ነው።', 'አብ ልብ፤ወልድ ቃል፤መንፈስ ቅዱስ ሕይወት ነው።', 'መንፈስ ቅዱስ እስትንፋስ፤ወልድ ቃል፤አብ ልብ ነው።', 'ሁሉም', 'ሀ', '2026-06-24 04:53:09', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('14', '14.ከሚከተሉት አንዱ ልዩ የሆነው የቱ ነው?', 'የመንፈስ ቅዱስ መስረጽ እና የስጋ መንፈስ ቅዱስን ገንዘብ ማድረግ በአንድ ጊዜ በቅጽበት የተፈጸመ ነው።', 'የወልድ መወልድ እና የመንፈስ ቅዱስ መስረጽ የተፈጸመው በጊዜ ተዋሕዶ ነው።', 'ስጋ በጊዜ ተዋሕዶ የመንፈስ ቅዱስን ሕይወትነት ገንዘብ ያደረገው በእንበለ ተዋሕዶ ነው።', 'መልስ የለም', 'መ', '2026-06-24 05:05:36', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('15', '15.ሥላሴን በአካል ፫ ስንል ልብ ልንላቸው የሚገቡ ነገሮች የትኞቹ ናቸው?', 'ሦስት አካል ስንል ሦስት እግዚአብሔር የምንል መሆኑ', 'እያንዳንዳቸው ልብ፣ቃል፣እስትንፋስ አላቸው የምንል መሆኑ', 'ሦስት አካል ስንል አንዱ አካል ከሌላው አካል ተለይቶ የተገኘበት ዘመን የሚታወቅና አባት ከልጁ ቀድሞ የሚገኝ መሆኑ፤', 'መልስ የለም', 'መ', '2026-06-24 05:10:20', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('16', '16.በጊዜ ተዋሕዶ የከበረ ማን ነው?', 'ሥግዉ ቃል(ክርስቶስ)', 'ስጋ(ትስብእት)', 'ቃል(ወልድ)', 'አብ እና መንፈስ ቅዱስ', 'ለ', '2026-06-24 05:13:06', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('17', '17.አብ በሁሉ የመላ ከሆነ ወልድና መንፈስ ቅዱስ በምን ይመላሉ?', 'አባታችን ሆይ በሰማይ የምትኖር የምንለውም ከምድር ከፍ ብሎ በሰማይ ስለሚኖሩ ነው።', 'እነርሱ የሚኖሩት በምድር ነው።', 'እነርሱ የሚኖሩ በራሳቸው ዓለምነት ነው', 'እነርሱ የሚኖሩት በገነትና እና በመንግስተ ሰማይ ነው', 'ሐ', '2026-06-24 05:18:17', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('18', '18.ከሚከተቱት አንዱ ልዩ የሆነው የቱ ነው?', 'ልብነት', 'አክባሪነት', 'ተወላዲነት', 'ሠራፂነት', 'ለ', '2026-06-24 05:26:19', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('19', '19.አብ፣ወልድ፤መንፈስ ቅዱስ የሚለው የምን ስም ነው?', 'የባህርይ ስም', 'የአንድነት ስም', 'የአካል ስም', 'መልስ የለም', 'ሐ', '2026-06-24 05:30:39', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('20', '20.ከሚከተሉት ዉስጥ በተዋህዶ ጊዜ በትክክል የተዛመደው የቱ ነው?', 'ተዋህዶ ሳይከብር ቢቀር ዜገ ያሰኛል።አካለ ቃል ክብር አልባ ያሰኛል።', 'ከፍሎ ሳይዋሐድ ቢቀር እመቤታችን አምላክ ሆነች ያሰኛል።', 'ተዋህዶ ሳይከፈል ቢቀር በሰው ወይም በእመቤታችን አደረች ያሰኛል።', 'ከብሮ ሳይዋሐድ ቢቆይ አካለ ቃል ክብር አልባ ያሰኛል።', 'ሀ', '2026-06-24 05:41:34', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('21', '1.አካላዊ ቃል ከስጋ ጋር ሲዋሐድ አሐቲ ባህርየ ሥላሴ ተዋሐደች ማለት ይቻላል።', 'እውነት', 'ሀሰት', '', '', 'FALSE', '2026-06-24 05:45:39', 'true_false', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('22', '2.አካላዊ ቃል ለተዋሐደው ስጋ ክብር ሆነው።', 'እውነት', 'ሀሰት', '', '', 'TRUE', '2026-06-24 05:46:56', 'true_false', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('23', '3.እምነት ማለት በዓይን ሳያዩ፤በጆሮ ሳይሰሙ ሳይመረምሩ መቀበል ነው።', 'እውነት', 'ሀሰት', '', '', 'FALSE', '2026-06-24 05:48:12', 'true_false', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('24', '4.መንፈስ ቅዱስ ለአብ እና ለወልድ ሕይወታቸዉ ብቻ ሳይሆን ክብራቸዉ መንግስታቸዉ እስትንፋሳቸዉ ነው።', 'እውነት', 'ሀሰት', '', '', 'FALSE', '2026-06-24 05:53:05', 'true_false', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('25', '21..በኦሪት ዘፍጥረት \"ሰውን እንደምሳሌያችንና እንደ መልካችን እንፍጠር ሲሉ ሥላሴ ይህ የምን ግብር ነው?', 'አካላዊ ግላዊ ግብር', 'ውሳጣዊ ግብር', 'ግብረ ዋህድና', 'ሀ እና ሐ', 'ሐ', '2026-06-24 06:01:59', 'multiple_choice', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('26', '5.ክርስቶስ ቅድመ ተዋህዶ አንድ አካል አንድ ባህርይ ሲሆን በጊዜ ተዋህዶ መክፈል፣መዋሐድ እና መክበር ተፈጽመዋል።', 'እውነት', 'ሀሰት', '', '', 'FALSE', '2026-06-24 06:06:50', 'true_false', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('27', '6.በጊዜ ተዋህዶ ተዋህዶ ሳይከብር ቢቀር ዜገ ያሰኛል፣አካለ ቃል ክብር አልባ ያሰኛል።', 'እውነት', 'ሀሰት', '', '', 'TRUE', '2026-06-24 06:12:46', 'true_false', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('28', '7.ለምሥጢረ ሥላሴ ትምህርት ማስረጃ ሆነው ከሚያገለግሉ ፍጥረታት መካከል ሊቁ ቅዱስ ቄርሎስ የመሰለው የእሳት እና የብረት ተዋሕዶ ዋነኛ ማስረጃ ነው።', 'እውነት', 'ሀሰት', '', '', 'FALSE', '2026-06-24 06:18:27', 'true_false', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('29', '8.በአፍአ ከሚደረጉ ነገሮች ጋር የማይገናኝ ጊዜያት እና ዘመናት፣ዓለማት እና ፍጥረታት፣ቀናት እና ሰዓታት፣ዓመታት እና ወራት በማይነገሩበት አብ ከወልድ ከመንፈስ ቅዱስ ሳይቀድም እና በባህርይዉ ሳይለይ በባህርይው ወልድን የወለደበት፣መንፈስ ቅዱስን ያሰረጸበት ግብር ዉሳጣዊ አካላዊ ግላዊ ግብር ይባላል።', 'እውነት', 'ሀሰት', '', '', 'TRUE', '2026-06-24 06:28:41', 'true_false', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('30', '9.የኢትዮጵያ ኦርቶዶክስ ተዋሕዶ ቤተ ክርስቲያን መሰረታዊ የምሥጢረ ሥላሴ ትምህርት አብ ቀባዒ፣ወልድ ተቀባዒ መንፈስ ቅዱስ ቅብዕ የሚል ነው።', 'እውነት', 'ሀሰት', '', '', 'FALSE', '2026-06-24 06:34:25', 'true_false', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('31', '10.በምሥጢረ ሥላሴ ትምህርት አካል ያለ ባህርይ፣ባህርይ ደግሞ ያለ አካል ተለይቶ አይገኝም እና ባህርይ በየአካላቸው እንዳላቸው መጽሐፍት ይናገራሉ።', 'እውነት', 'ሀሰት', '', '', 'FALSE', '2026-06-24 06:41:50', 'true_false', NULL);
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('32', '1.የአንዲት ሃይማኖት መጠሪያ ስያሜ ማነው?መቼ የት አገኘ?', '✔ .የአንዲት ሃይማኖት መጠሪያ ስያሜ በብሉይ ኪዳን \"ህዝበ እግዚአብሔር\"ይባሉ ነበር።የማያምኑትም አህዛብ ይባላሉ። ✔ .የአንዲት ሃይማኖት መጠሪያ ስያሜ በዘመነ አበው\"ክርስቲያን\"ይባሉ ነበር።ለዚህም ማስረጃ:- \"ወነበሩ አሐተ ዓመተ ኅቡረ በቤተ ክርስቲያን ወመሀሩ ለብዙኃን አህዛብ ወተሰምዩ አርድእት ክርስቲያን በአንጾኪያ ቀዲሙ\"ሐዋ 11 ÷26 ብሎ በመጀመሪያ በአንጾኪያ ክርስቲያን ተባሉ ብሎ ገልጾታል!', '', '', '', '✔ .የአንዲት ሃይማኖት መጠሪያ ስያሜ በብሉይ ኪዳን \"ህዝበ እግዚአብሔር\"ይባሉ ነበር።የማያምኑትም አህዛብ ይባላሉ። ✔ .የአንዲት ሃይማኖት መጠሪያ ስያሜ በዘመነ አበው\"ክርስቲያን\"ይባሉ ነበር።ለዚህም ማስረጃ:- \"ወነበሩ አሐተ ዓመተ ኅቡረ በቤተ ክርስቲያን ወመሀሩ ለብዙኃን አህዛብ ወተሰምዩ አርድእት ክርስቲያን በአንጾኪያ ቀዲሙ\"ሐዋ 11 ÷26 ብሎ በመጀመሪያ በአንጾኪያ ክርስቲያን ተባሉ ብሎ ገልጾታል!', '2026-06-26 02:34:56', 'short_answer', '3');
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('33', '2.ንዴት እንዴት ጠፋ?', ' ንዴት ማለት በግድ(መራብ:መጠማት) ለፈቃዳት መገዛት ማለት ነው።ይህ እንዴት ጠፋ ሲባል ቃል ከስጋ ጋር ፈጽሞ በመዋሐዱ ነው። ☝ቅድመ ተዋህዶ ነዳይ የነበረ ስጋ በግድ የሚራብ የሚጠማ ሲሆን ከባለጸጋው መለኮት ጋር ሲዋሐድ ግን ሁሉ የእርሱ ሆነ።ይሁን እንጂ ተዋሕዶ ሁለትነትን እንጂ ንዴትን አላጠፋም የሚሉ አሉ;መጽሐፍ ግን አይልም።  ለዚህም ማስረጃ  ➡\"እርሱ መለኮት የተዋሐደውን ከእርሱ ጋር አንድ አካ', '', '', '', ' ንዴት ማለት በግድ(መራብ:መጠማት) ለፈቃዳት መገዛት ማለት ነው።ይህ እንዴት ጠፋ ሲባል ቃል ከስጋ ጋር ፈጽሞ በመዋሐዱ ነው። ☝ቅድመ ተዋህዶ ነዳይ የነበረ ስጋ በግድ የሚራብ የሚጠማ ሲሆን ከባለጸጋው መለኮት ጋር ሲዋሐድ ግን ሁሉ የእርሱ ሆነ።ይሁን እንጂ ተዋሕዶ ሁለትነትን እንጂ ንዴትን አላጠፋም የሚሉ አሉ;መጽሐፍ ግን አይልም።  ለዚህም ማስረጃ  ➡\"እርሱ መለኮት የተዋሐደውን ከእርሱ ጋር አንድ አካ', '2026-06-26 02:37:24', 'short_answer', '3');
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('34', '3.በተዋሕዶ የከበረ ማን ነው?አክባሪዉስ?', 'በተዋህዶ የከበረ ስጋ ወይም ትስብእት ሲሆን አክባሪው ደግሞ ቃል(መለኮት) ነው።', '', '', '', 'በተዋህዶ የከበረ ስጋ ወይም ትስብእት ሲሆን አክባሪው ደግሞ ቃል(መለኮት) ነው።', '2026-06-26 02:42:24', 'short_answer', '3');
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('35', '4.በዓለ ጥምቀት ወይም ልደት እሑድ ቢውል መቼ ይጾማል?', 'በዓለ ጥምቀት ወይም ልደት እሑድ ቢውል ቅዳሜ ጥሉላት አይበላም።“ገሃድ” አንድ ስለሆነ (ስንክሳር ጥር ፲ “ሰላም ለዕለት ዋሕድ ዘስሙ ገሃድ” እንዲል) የጥምቀትም ይሁን የልደት ገሃድ(ጋድ) አንድ ቀን ስለሆነ ዋዜማዉን ብቻ ይጾማል እንጂ ሁለት ቀን አይጾምም።', '', '', '', 'በዓለ ጥምቀት ወይም ልደት እሑድ ቢውል ቅዳሜ ጥሉላት አይበላም።“ገሃድ” አንድ ስለሆነ (ስንክሳር ጥር ፲ “ሰላም ለዕለት ዋሕድ ዘስሙ ገሃድ” እንዲል) የጥምቀትም ይሁን የልደት ገሃድ(ጋድ) አንድ ቀን ስለሆነ ዋዜማዉን ብቻ ይጾማል እንጂ ሁለት ቀን አይጾምም።', '2026-06-26 02:47:26', 'short_answer', '3');
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('36', '5.አምላክ እንዴት ሰው ሆነ?', 'አምላክ ሰው የሆነው እንበለ ቱሳሔ፣እንበለ ውላጤ፣እንበለ ቡአዴ፣እንበለ ትድምርት፣እንበለ ሚጠት፣እንበለ ተጋውሮ፣እንበለ ፍልጠት ነው።ታዲያ በአጭሩ ታዲያ ክርስቶስ ሰው የሆነው እንዴት ነው ትለኝ እንደሆነ በፍጹም ተዋህዶ ነው', '', '', '', 'አምላክ ሰው የሆነው እንበለ ቱሳሔ፣እንበለ ውላጤ፣እንበለ ቡአዴ፣እንበለ ትድምርት፣እንበለ ሚጠት፣እንበለ ተጋውሮ፣እንበለ ፍልጠት ነው።ታዲያ በአጭሩ ታዲያ ክርስቶስ ሰው የሆነው እንዴት ነው ትለኝ እንደሆነ በፍጹም ተዋህዶ ነው', '2026-06-26 02:49:05', 'short_answer', '3');
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('37', '6.የትንሣኤ ዘለሕይወት እና ትንሣኤ ዘለደይን ልዩነት ምንድን ነው?', 'ትንሣኤ ዘለሕይወት እና ትንሣኤ ዘለደይን ሁለቱ የፍርድ አይነቶች ሲሆኑ በትንሣኤ ዘጉባኤ ጊዜ ኃጥኣንን ለኃሳር ጻድቃንን ለክብር የሚጠራበት ነው።', '', '', '', 'ትንሣኤ ዘለሕይወት እና ትንሣኤ ዘለደይን ሁለቱ የፍርድ አይነቶች ሲሆኑ በትንሣኤ ዘጉባኤ ጊዜ ኃጥኣንን ለኃሳር ጻድቃንን ለክብር የሚጠራበት ነው።', '2026-06-26 02:52:09', 'short_answer', '3');
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('38', '7.በነገረ ሥጋዌ ትምህርት አክባሪ፣ከባሪ እና ክብር የሚባሉ ነገሮች ለማን ተሰጥተው ይነገራሉ?', 'አክባሪነት➠አብ ወልድ መንፈስ ቅዱስ(አፋዊ ግብር) ፣ትስብእት(ስጋ)➠ከባሪ(የከበረ)፣ወልድ(ቃል) ክብር(ቅብዕ) ነው።', '', '', '', 'አክባሪነት➠አብ ወልድ መንፈስ ቅዱስ(አፋዊ ግብር) ፣ትስብእት(ስጋ)➠ከባሪ(የከበረ)፣ወልድ(ቃል) ክብር(ቅብዕ) ነው።', '2026-06-26 02:57:14', 'short_answer', '3');
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('39', '8.ተዓቅቦ ማለት ምን ማለት ነው?', 'ተዓቅቦ:-ማለት መለኮት ርቀቱን፤ስፋቱን፤ምልዓቱን፤አይመረመሬነቱን፤ አምላክነቱን ሳይለቅ ባለመጣፋፋት በመጠባበቅ በስጋ ርስት መመርመር ፤መግዘፍ፤መወሰን;ስጋም የሚመረመር፤ረቂቅ፤ግዙፍ፤ውሱንነቱን ሳይለቅ ባለመጠፋፋት በመጠባበቅ በመለኮት ገንዘብ የማይመረመር የማይታመም ረቂቅ፤ስፉህ፤ምልዑ መሆን ተዓቅቦ ይባላል።', '', '', '', 'ተዓቅቦ:-ማለት መለኮት ርቀቱን፤ስፋቱን፤ምልዓቱን፤አይመረመሬነቱን፤ አምላክነቱን ሳይለቅ ባለመጣፋፋት በመጠባበቅ በስጋ ርስት መመርመር ፤መግዘፍ፤መወሰን;ስጋም የሚመረመር፤ረቂቅ፤ግዙፍ፤ውሱንነቱን ሳይለቅ ባለመጠፋፋት በመጠባበቅ በመለኮት ገንዘብ የማይመረመር የማይታመም ረቂቅ፤ስፉህ፤ምልዑ መሆን ተዓቅቦ ይባላል።', '2026-06-26 03:00:35', 'short_answer', '3');
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('40', '9.እግዚአብሔር ወልድ በ30 ዘመኑ ከተጠመቀ ዛሬ ህፃናት ለምን በ40 እና በ80 ቀን ለምን ይጠመቃሉ?', 'አዳም ወደ ገነት የገባዉ በ40 ቀኑ ሄዋን ደግሞ በ80 ቀኗ ስለሆነ ነው', '', '', '', 'አዳም ወደ ገነት የገባዉ በ40 ቀኑ ሄዋን ደግሞ በ80 ቀኗ ስለሆነ ነው', '2026-06-26 03:05:27', 'short_answer', '3');
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('41', '10.የምሥጢረ ፈጣሪ እና ምሥጢረ ፍጡራን ልዩነታቸው ምንድን ነው?', 'ምሥጢረ ፍጡራን:-ምሥጢረ ፍጡራን ማለት በፍጡራን ውስጥ ያለ የማይታወቅ ነገር ነው።እርሱ ሦስት ወገን ነው።እነርሱም:-ምሥጢረ ሰማይ ወምድር፤ምሥጢረ ሰብእ፤ምሥጢረ መላእክት ሲሆኑ ምሥጢረ ፈጣሪ(እግዚአብሔር) የምንለው ደግሞ ዓለምን እምኀበ አልቦ መፍጠሩ፤አንድ ሲሆን ሦስት፤ሦስት ሲሆኑ አንድ ቀዳማዊነቱ፤ርቀቱ፤ምልዓቱ ይህንን ዓለም እምኀበ አልቦ ኀበ ቦ ከኢምንት ኀበ ምንት የፈጠረበት ረቂቅ ምስጢር ነው።', '', '', '', 'ምሥጢረ ፍጡራን:-ምሥጢረ ፍጡራን ማለት በፍጡራን ውስጥ ያለ የማይታወቅ ነገር ነው።እርሱ ሦስት ወገን ነው።እነርሱም:-ምሥጢረ ሰማይ ወምድር፤ምሥጢረ ሰብእ፤ምሥጢረ መላእክት ሲሆኑ ምሥጢረ ፈጣሪ(እግዚአብሔር) የምንለው ደግሞ ዓለምን እምኀበ አልቦ መፍጠሩ፤አንድ ሲሆን ሦስት፤ሦስት ሲሆኑ አንድ ቀዳማዊነቱ፤ርቀቱ፤ምልዓቱ ይህንን ዓለም እምኀበ አልቦ ኀበ ቦ ከኢምንት ኀበ ምንት የፈጠረበት ረቂቅ ምስጢር ነው።', '2026-06-26 03:09:24', 'short_answer', '3');
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('42', '11.ወልድ ለአብ ልጁ ከሆነ ለመንፈስ ቅዱስ ምኑ ነው?', 'ወልድ ለአብ ልጁ ከሆነ ለመንፈስ ቅዱስ ቃሉ ነው።', '', '', '', 'ወልድ ለአብ ልጁ ከሆነ ለመንፈስ ቅዱስ ቃሉ ነው።', '2026-06-26 03:20:53', 'short_answer', '4');
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('43', '12.\"አፍቀርከ ጽድቀ ወዓመጻ ጸላእከ በእንተዝ ቀብዐከእግዚአብሄር አምላክከ ቅብዐ ትፍሥህት እምእለ ከማከ-ጽድቅን ወደድህ አመጻን ጠላህ ስለዚህ ከባልንጀሮችህ ይልቅ እግዚአብሔር አምላክ የደስታ ዘይትን ቀባህ\"መዝ 44÷7  ሲል ምን ማለቱ ነው?', 'በመጀመሪያ\"ጽድቅን ወደድህ አመጻን ጠላህ ስለዚህ ከባልንጀሮችህ ይልቅ እግዚአብሔር አምላክ የደስታ ዘይትን ቀባህ\"በሚለውኃይለ ቃል ውስጥ \"አንተ\"ተብሎ በውስጠ ታዋቂ የተጠራ እንዳለ እናስተውል።ይኸውም \"ትስብእት\"ነው።➡ጽድቅን ወደድክ አመጻን ጠላህ ማለትስ ታልከኝ ሰው መሆንን ወደድክ አንድም ትንቢተ ነቢያትን መፈጸምን ወደድክ ወድድክ ሰው አለመሆንን ጠላህ ማለቱ ነው!!ሰው መሆንን ወደድክ የተባለው ማነው', '', '', '', 'በመጀመሪያ\"ጽድቅን ወደድህ አመጻን ጠላህ ስለዚህ ከባልንጀሮችህ ይልቅ እግዚአብሔር አምላክ የደስታ ዘይትን ቀባህ\"በሚለውኃይለ ቃል ውስጥ \"አንተ\"ተብሎ በውስጠ ታዋቂ የተጠራ እንዳለ እናስተውል።ይኸውም \"ትስብእት\"ነው።➡ጽድቅን ወደድክ አመጻን ጠላህ ማለትስ ታልከኝ ሰው መሆንን ወደድክ አንድም ትንቢተ ነቢያትን መፈጸምን ወደድክ ወድድክ ሰው አለመሆንን ጠላህ ማለቱ ነው!!ሰው መሆንን ወደድክ የተባለው ማነው', '2026-06-26 03:27:59', 'short_answer', '4');
INSERT INTO `questions` (`id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `question_type`, `section_id`) VALUES ('44', '13.አብ ቀባዒ፣ወልድ ተቀባዒ እና መንፈስ ቅዱስ ቅብዕ የሚለዉ አስተምህሮ እንዴት ይታያል?', 'በመጀመሪያ ይህ አስተምህሮ የቅባት እምነት ተከታዮች አስተምህሮ ሲሆን -ይህ ማለት-አብ-አክባሪ  -ወልድ ከባሪ -መንፈስ ቅዱስ ክብር ማለት ነው።አሁን አሁን ከሆነ በምሥጢረ ሥላሴ ትምህርትና ምስጢረ ሥላሴ መለየት ተስኖናል ይመስለኛል::በምስጢረ ሥላሴ ትምህርት ሥላሴ በክብር አንድ አንድ ናቸው ብለናል።ታዲያ ↪ ሥላሴ አብ ወልድ መንፈስ ቅዱስ በክብር አንዲት ናቸው ካልን አብ አክባሪ፣ወልድ ከባሪ መንፈስ ', '', '', '', 'በመጀመሪያ ይህ አስተምህሮ የቅባት እምነት ተከታዮች አስተምህሮ ሲሆን -ይህ ማለት-አብ-አክባሪ  -ወልድ ከባሪ -መንፈስ ቅዱስ ክብር ማለት ነው።አሁን አሁን ከሆነ በምሥጢረ ሥላሴ ትምህርትና ምስጢረ ሥላሴ መለየት ተስኖናል ይመስለኛል::በምስጢረ ሥላሴ ትምህርት ሥላሴ በክብር አንድ አንድ ናቸው ብለናል።ታዲያ ↪ ሥላሴ አብ ወልድ መንፈስ ቅዱስ በክብር አንዲት ናቸው ካልን አብ አክባሪ፣ወልድ ከባሪ መንፈስ ', '2026-06-26 03:34:01', 'short_answer', '4');

DROP TABLE IF EXISTS `quiz_link_generators`;
CREATE TABLE `quiz_link_generators` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_title` varchar(255) NOT NULL,
  `exam_type` varchar(50) NOT NULL,
  `link_url` text NOT NULL,
  `access_code` varchar(50) NOT NULL,
  `expiry_minutes` int(11) NOT NULL DEFAULT 60,
  `timer_minutes` int(11) NOT NULL DEFAULT 30,
  `one_attempt` tinyint(1) NOT NULL DEFAULT 1,
  `qr_code_svg` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `quiz_results`;
CREATE TABLE `quiz_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(100) NOT NULL,
  `quiz_name` varchar(255) NOT NULL,
  `score` int(11) NOT NULL DEFAULT 0,
  `total_questions` int(11) NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `registrations`;
CREATE TABLE `registrations` (
  `id` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `student_id` varchar(100) NOT NULL,
  `course` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  `created_at` datetime NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `registrations` (`id`, `name`, `email`, `student_id`, `course`, `amount`, `payment_status`, `created_at`, `paid_at`) VALUES ('', '', '', '027', 'ሐዋርያዊ ተልዕኮ', '250.00', '', '0000-00-00 00:00:00', NULL);
INSERT INTO `registrations` (`id`, `name`, `email`, `student_id`, `course`, `amount`, `payment_status`, `created_at`, `paid_at`) VALUES ('reg_6a3ce0d6cfa5d5.61722870', 'Yilkal Demeke', 'yilkaldemeke21@gmail.com', '027', 'ነገረ ሃይማኖት', '500.00', 'unpaid', '2026-06-25 10:03:34', NULL);
INSERT INTO `registrations` (`id`, `name`, `email`, `student_id`, `course`, `amount`, `payment_status`, `created_at`, `paid_at`) VALUES ('reg_6a3e8cbd74ea65.90843457', 'Yilkal Demeke', 'yilkaldemeke21@gmail.com', '027', 'ነገረ ቅባት', '350.00', 'unpaid', '2026-06-26 16:29:17', NULL);
INSERT INTO `registrations` (`id`, `name`, `email`, `student_id`, `course`, `amount`, `payment_status`, `created_at`, `paid_at`) VALUES ('reg_6a3eab79b41156.01366629', 'Yilkal Demeke', 'yilkaldemeke21@gmail.com', '027', 'አገልግሎት እና መንፈሳዊ ሕይወት', '300.00', 'unpaid', '2026-06-26 18:40:25', NULL);

DROP TABLE IF EXISTS `saved_courses`;
CREATE TABLE `saved_courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(100) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `instructor` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `site_chat_messages`;
CREATE TABLE `site_chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_type` varchar(30) NOT NULL DEFAULT 'guest',
  `sender_name` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `reply_message` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'new',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_site_chat_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `site_settings`;
CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `student_attendance`;
CREATE TABLE `student_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(100) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `login_date` date NOT NULL,
  `login_time` datetime NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Present',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_attendance` (`student_id`,`login_date`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `student_attendance` (`id`, `student_id`, `student_name`, `login_date`, `login_time`, `ip_address`, `user_agent`, `status`) VALUES ('1', '099', 'gggg', '2026-06-23', '2026-06-23 22:49:41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'Present');
INSERT INTO `student_attendance` (`id`, `student_id`, `student_name`, `login_date`, `login_time`, `ip_address`, `user_agent`, `status`) VALUES ('3', '027', 'Yilkal Demeke', '2026-06-24', '2026-06-24 00:38:26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'Present');
INSERT INTO `student_attendance` (`id`, `student_id`, `student_name`, `login_date`, `login_time`, `ip_address`, `user_agent`, `status`) VALUES ('4', '027', 'Yilkal Demeke', '2026-06-25', '2026-06-25 23:19:17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'Present');
INSERT INTO `student_attendance` (`id`, `student_id`, `student_name`, `login_date`, `login_time`, `ip_address`, `user_agent`, `status`) VALUES ('10', '027', 'Yilkal Demeke', '2026-06-26', '2026-06-26 22:39:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'Present');
INSERT INTO `student_attendance` (`id`, `student_id`, `student_name`, `login_date`, `login_time`, `ip_address`, `user_agent`, `status`) VALUES ('17', '027', 'Yilkal Demeke', '2026-06-27', '2026-06-27 23:19:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'Present');
INSERT INTO `student_attendance` (`id`, `student_id`, `student_name`, `login_date`, `login_time`, `ip_address`, `user_agent`, `status`) VALUES ('19', '027', 'Yilkal Demeke', '2026-06-28', '2026-06-28 05:27:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'Present');

DROP TABLE IF EXISTS `student_exam_approvals`;
CREATE TABLE `student_exam_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(100) NOT NULL,
  `exam_type` varchar(50) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `approved_by` varchar(100) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_exam_approval` (`student_id`,`exam_type`),
  KEY `idx_student_exam_approval_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `student_exam_approvals` (`id`, `student_id`, `exam_type`, `status`, `approved_by`, `approved_at`, `notes`, `created_at`) VALUES ('1', '027', 'exam20', 'approved', 'sofonyas', '2026-06-25 11:39:33', '', '2026-06-25 12:38:11');

DROP TABLE IF EXISTS `student_notes`;
CREATE TABLE `student_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(100) NOT NULL,
  `note_text` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `student_id` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(30) NOT NULL DEFAULT 'Student',
  `created_at` datetime DEFAULT current_timestamp(),
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `email_verification_token` varchar(255) DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `student_id` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('1', 'Yilkal Demeke', 'yilkaldemeke21@gmail.com', '027', '$2y$10$uYAcZxUCu6XQWCK9CMkqSuHXQcwelvs6S/UTvxxmZe2Dbzss7nNUu', 'Student', '2026-06-23 21:35:00', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('3', 'fikre girma', 'fikregirma422@gmail.com', '001', '$2y$10$y982a9ACkU9HoRM8FE6MqODiha1Tqn84BdI2iErjjdALwn61bvcZe', 'Student', '2026-06-23 22:16:42', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('4', 'fetenech worku', 'fetenechworku498@gmail.com', '002', '$2y$10$.l4SBrDmm.UZccfBl.Cwx.2RURjV65LB9234p3OXN6tBZyzRkTG2O', 'Student', '2026-06-23 23:03:19', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('5', 'adugnaw negesse', 'adugnawnegesse@gmail.com', '003', '$2y$10$29Mi.nTgh4KpNYhUw6q81uqnml0dxnIbeYajMdekGpehcgMYq8Bnq', 'Student', '2026-06-23 23:04:52', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('6', 'hawariat ayenew', 'hawariatayenew@gmail.com', '004', '$2y$10$Ee4t8obZUCjip8Gs7TP7jeO/93Y/CdAXeo.2v1JjwvvES89EyVoIy', 'Student', '2026-06-23 23:06:09', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('7', 'simachewu guache', 'simachewuguache0@gmail.com', '005', '$2y$10$I1b6margg7CtJ9NcsntU3OuJ.987EALgnSWSWxwQNZR5zJjV5t2mW', 'Student', '2026-06-23 23:07:41', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('8', 'd/n mamaru shitahun', 'mamarushitahun@gmail.com', '006', '$2y$10$vdi3omuTThnzSQPCALZGC.kXwdB7D0jdLXKr3E2CTBLtwKKzUEFki', 'Student', '2026-06-23 23:09:15', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('9', 'temesgen kassie', 'tkassie874@gmail.com', '007', '$2y$10$JiqSy9UHUxBNfH9Crghxqucr0cfvJ6HUL4/RX6vgVdv6IxWTm.01u', 'Student', '2026-06-23 23:10:52', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('10', 'kidst minda', 'kidstminda586@gmail.com', '008', '$2y$10$3ZiV07NtXaxeJ9BspENR6ufNdcVDBfYLeDbmYu/4LNZrB.XMprm6m', 'Student', '2026-06-23 23:13:11', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('11', 'alelgn negash', 'alelgnnegash10@gmail.com', '009', '$2y$10$ot8owgI26W94AJiYyt0T8.5epTc8ZjtG47WfgiM14QPav8pEspXZa', 'Student', '2026-06-23 23:14:45', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('12', 'lijhawult bayuh', 'lijhawultbayuh2721@gmail.com', '010', '$2y$10$.35Gyl/zDGJtLCoHX9nuOu5gtS.ltaqCDwoyvAWsvy.Unq/vF.tBC', 'Student', '2026-06-23 23:16:52', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('13', 'naol abebe', 'naolabebe05@gmail.com', '011', '$2y$10$PS/VAOHqNJzVLStnDhhK6OP.r4jvtIbiuXnzk/H18Yx2gNkEpUoJq', 'Student', '2026-06-23 23:18:28', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('14', 'tinbit yemata', 'yematatinbit@gmail.com', '012', '$2y$10$wh9JFWf0KtsaeYPZopqHJOWsdPpRTWyfQW1OekwDctRwaiR0Is5Ja', 'Student', '2026-06-23 23:20:58', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('15', 'tigist yibeltal', 'yibeltaltigst824@gmail.com', '013', '$2y$10$Vs0ghAI.lHDZhPz8CvW/mOgKUIYExgrTUkP.Rky7M55tBPwi8Ee.C', 'Student', '2026-06-23 23:22:23', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('16', 'asemarewu melkie', 'melkie2763@gmail.com', '014', '$2y$10$cJHL7udg6TEiKjLuQTARle0L0DI8ynOGIgKiBpdnqMl/FN.d4t/hu', 'Student', '2026-06-23 23:23:37', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('17', 'getaneh markie', 'getanehmarkie837@gmail.com', '015', '$2y$10$Ooe7CsmptefRBqnBYe7VUe9YD0M4kUYKXgE/KJ8in0gpi616t2w6.', 'Student', '2026-06-23 23:25:05', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('18', 'digis birhan', 'digisbirhan21@gmail.com', '016', '$2y$10$DGo8AQ1U0wk4lHehqik8huClo2jI1suc.UdCeAKjev.MWRGzrcuOu', 'Student', '2026-06-23 23:27:25', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('19', 'fasika shegaw', 'fasikashegaw21@gmail.com', '017', '$2y$10$sQjdBo18NlfayBVuFkg9MeUtWwGewFv7xyaxSF4qVOUUNlFvzWbVK', 'Student', '2026-06-23 23:29:20', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('20', 'gizachew kassa', 'gizachewkassa22@gmail.com', '018', '$2y$10$hkXdO/Z6RfKVnRcPReeWu.7TFoLu/KmpDenkdVmEgW2zX6PGkcYCS', 'Student', '2026-06-23 23:30:38', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('21', 'amarech abebaw', 'amarechabebaw09@gmail.com', '019', '$2y$10$I/N./2p.5pZllz8S2yolJu9vyF3HhXcMDlhyeSL6AR0.46NBaSLHi', 'Student', '2026-06-23 23:31:49', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('22', 'tigist asefa', 'tigistasefa375@gmail.com', '020', '$2y$10$lWBs5ZKjQuUWQb.JDUZA6eFUM1PHQRJcLRFZtkbYbauRNLC16MMPa', 'Student', '2026-06-23 23:33:38', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('23', 'bosena shumet', 'shumetbosena2127@gmail.com', '021', '$2y$10$4lgGxDR.RaOJNOYNlJ/6hOESKnRLnBdhoZN2P.7.Whxfgqoxkpp2S', 'Student', '2026-06-23 23:35:55', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('24', 'belayinesh dires', 'baldiress@gmail.com', '022', '$2y$10$0yD9Gf5kb3KBlB5SPknD.OJ2IV/Yli8qfx3KS0lGgqfsFw8kCxrCi', 'Student', '2026-06-23 23:37:50', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('25', 'tekeba aweke', 'tadessaweke8@gmail.com', '023', '$2y$10$IS6ILZw1Z.N21.sEEsk3zOoOns1iKL07L7yg3r8fO35tngivZ8sdi', 'Student', '2026-06-23 23:39:34', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('26', 'manayeh bayilie', 'manayehbayilie1921@gmail.com', '024', '$2y$10$./a6pp9pnNeY4TAoC7hU7eYH/fda.NhG9J/.4cj8kgFNz.u.DAZmu', 'Student', '2026-06-23 23:41:03', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('27', 'enatihun yimer', 'yenatihun@gmail.com', '025', '$2y$10$HRXfRVI7b1fu7TbpWhmO9uE5oI.1q3DbAFLC9mOidVWGbAc9e0Jwe', 'Student', '2026-06-23 23:42:17', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('28', 'henok sfat', 'henoksfat3@gmail.com', '026', '$2y$10$Qh9f8Lwx/pwvbUzW2hplVO8qvKnbdOi5md7naGRnO/j7OFqVr.S4K', 'Student', '2026-06-23 23:43:51', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('29', 'betelhem nigatu', 'betelhemnigatu20@gmail.com', '028', '$2y$10$KQW56qN9oLfYqIXcdlut/OklourhriSYAhuwGEaG6.FLWPfApwiKC', 'Student', '2026-06-23 23:46:03', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('30', 'tsegaye aderajew', 'tsegayeaderajew021@gmail.com', '029', '$2y$10$cKQZmLKo6bDxCN9JVdSbEOqxwBIT.fjKt.tZVWC2WKgSnT8S5axHG', 'Student', '2026-06-23 23:48:43', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('31', 'kindu fikad', 'kindufikad085@gmail.com', '030', '$2y$10$xjs0mxmfNcXX8VgldBvwaOcAVyQJ6P8e11o7yqG.IbNT43xNvH0YS', 'Student', '2026-06-23 23:50:05', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('32', 'mastewal balew', 'mastewalbalew08@gmail.com', '031', '$2y$10$48HwNLY/TmqCjMU9u/bjXOuAKHFLT5HB4FJoVfL3vWujrJvzC6RJO', 'Student', '2026-06-23 23:53:01', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('33', 'tihun wendie', 'tihun1124@gmail.com', '032', '$2y$10$Nyalo7wNzjh.QQkl5wXisu7RcYUkXxOMwvIO.o300AjuKZvEedGku', 'Student', '2026-06-23 23:57:07', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('34', 'simegnaw munye', 'simegnawmunye4@gmail.com', '033', '$2y$10$DM059UMn93qcTpbpQdYluOsUkhL0V/KV8bP0.O04WbAeSYvxvF/wK', 'Student', '2026-06-23 23:59:54', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('35', 'bezawit awoke', 'bezawitawokeabebe@gmail.com', '034', '$2y$10$FwHjPTapinIVPGrgQOVSxu5LvVIAR2X9PB.IPBWErFhkd.ZMY8F9u', 'Student', '2026-06-26 13:28:07', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('36', 'haregeweyin yibeltal', 'haregeweyinyibeltaladane@gmail.com', '035', '$2y$10$tueLDrK4.zQKm987FIRrEexE4iFJMxYBPhp.Q5HsIDTyr4RUB0NOq', 'Student', '2026-06-28 01:26:33', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('37', 'sefiager awoke', 'sefiagerawokeabebe@gmail.com', '036', '$2y$10$/qIEN0hC4rpdxtUQOpavXOeLHmb2lVGzsIs9vcKXkfeY.ddEM.K9i', 'Student', '2026-06-28 01:27:50', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('38', 'diakon getachew melaku', 'gechmelaku21@gmail.com', '037', '$2y$10$UacKphZQFnQj/zf3dWHekebIkUEswQ68Qw6RMJBGfXdPiK3bF0H9O', 'Student', '2026-06-28 01:29:25', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('39', 'manaye hunegnaw', 'hunegnawmanaye936@gmail.com', '038', '$2y$10$kpV/lAreKs.K3npeUzXHHOH/IqswMQ3JjOjsUn.CBX1yxqDlBFi3u', 'Student', '2026-06-28 01:31:37', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('40', 'muluken abeje', 'mulukenabeje693@gmail.com', '039', '$2y$10$htbM51Uat8M/Ue2IUarfaesPWYAj6n83cPwDPEdKnZac8aA3PVj0i', 'Student', '2026-06-28 01:33:48', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('41', 'yalganesh muche', 'yalganeshmuche6@gmail.com', '040', '$2y$10$42aTYGUXcza3uk/yZX2U/uEsoAx6et65FicVfjN//Jx2ncZ8OrwXC', 'Student', '2026-06-28 01:36:22', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('42', 'melaku shiferaw', 'melakushiferaw61@gmail.com', '041', '$2y$10$jcZRnI2fSCZUDGU7wIqq9Ott11xipDuv/f0mjdTnoGEzEO1xOXDfq', 'Student', '2026-06-28 01:37:59', '0', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `email`, `student_id`, `password_hash`, `role`, `created_at`, `email_verified`, `email_verification_token`, `email_verified_at`) VALUES ('43', 'fasika kiburie', 'fasikakiburie@gmail.com', '042', '$2y$10$m0YRq4kVJMPhJNnbEy0wEed6TtXwFtA8dPLgBo/6f5mWedAYGlN8u', 'Student', '2026-06-28 01:44:44', '0', NULL, NULL);

SET FOREIGN_KEY_CHECKS = 1;
