<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit;
}

$studentId = (int)$_SESSION['student_id'];
$studentName = $_SESSION['student_name'] ?? 'Student';
$EXAM_LIMIT_SECONDS = 210 * 60; // 210 minutes = 3 hours 30 minutes

if (!isset($_SESSION['short_exam_started_at'])) {
    $_SESSION['short_exam_started_at'] = time();
    $_SESSION['short_exam_deadline'] = $_SESSION['short_exam_started_at'] + $EXAM_LIMIT_SECONDS;
}

$startedAt = (int)($_SESSION['short_exam_started_at'] ?? time());
$deadline = (int)($_SESSION['short_exam_deadline'] ?? ($startedAt + $EXAM_LIMIT_SECONDS));
$timeExpired = (time() >= $deadline);

$questions = [
    ['q' => '፩ኛ. ሥላሴን በአካል ፫ ስንል ልብ ልንላቸው የሚገቡ ነገሮች እነማን ናቸው?(2%)?', 'a' => ''],
    ['q' => '፪ኛ.ተዋህዶን ስናነሳ "በ" እና "እንደ" የሚል ርስት እየሰጠን የምንናገረው መቼ ላይ ነው?(1%)?', 'a' => 'ድኅረ ተዋሕዶ'],
    ['q' => '፫ኛ.ቃል ከሥጋ ጋር በተዋሐደ ጊዜ የአብና የመንፈስ ቅዱስ ግብር ምን ነበር?(3%)?', 'a' => ''],
    ['q' => '፬ኛ.የሥላሴ ክብር ዝርዉ ነው ወይስ አካላዊ??(3%)?', 'a' => ''],
    ['q' => '፭ኛ.አብ ቀባዒ፤ወልድ ተቀባዒ፤መንፈስ ቅዱስ ቅብዕ የሚለው አስተምህሮ ምንን ያስከትላል?በሰፊው አብራሩ!(2%)?', 'a' => ''],
    ['q' => '፮ኛ.በምሥጢረ ሥላሴ ትምህርት ኩነት ላይ አንድነት ሳለበት ልዩነት፤ልዩነት ሳለበት አንድነት ስንል ምን ማለታችን ነው?(3%)', 'a' => ''],

    ['q' => '፯ኛ."እምቅድመ ቅብዓትሰ ነበሩ መላእክትከመ ህፃናት በዝንጋዔ ወሶበ ተቀብዑ መልዓ ላዕሌሆሙ አእምሮ;ወኂሩት;ፍቅር ወትዕግስት;ትህትና ወየዋሐት"-"ይህስ ቅብዓት ምንድንነው ቢሉ መንፈስ ቅዱስ ነው;የቅብዓት ጣዕሙ ምስጢሩ ምንድን ነው ቢሉ መላእክት ሳይቀቡ አእምሮ እውቀት እንደሌላቸውህፃናት ነበሩ;ከተቀቡ በኋላ ግን አእምሮ እውቀት;ፍቅር;ትህትና;የዋሐት;ትዕግስት አደረባቸው።ምስጋና እንደምንጭ እየፈሰሰ እየጎረፈ ከአፋቸው የማይለይ ሆነ"መ.አክሲማሮስ ገጽ ũƊ ሲል ምን ማለቱ ነው???አብራሩ!!!(3%)', 'a' => ''],
    ['q' => '፰ኛ.አካላትን የሚያገናዝበው ባህርይ ምን ይባላል?(3%)', 'a' => ''],
    ['q' => '፱ኛ.አብ መለኮት ወልድ መለኮት መንፈስ ቅዱስ መለኮት ማለት ይቻላል?ወላዲ መለኮት፣ተወላዲ መለኮት ፣ሠራፂ መለኮትስ ማለት ይቻላል?(2%)', 'a' => ''],
    ['q' => '፲.አካላትንም ባህርያትንም ከምንታዌ፤ከፍልጠት፤ከቡዓዴ የሚጠብቅ ምን ይባላል?(2%)', 'a' => ''],
    ['q' => '11.ልጅነት የሚገኘው በቅባተ መንፈስ ቅዱስ ነው ወይስ በተዋህዶ ነው?አብራሩ(3%)', 'a' => ''],
    ['q' => '12.የኩነት ሦስትነት ከአካላት ሦስትነት የሚለየው እንዴት ነው?(2%)', 'a' => ''],
    ['q' => '13.አካላዊ ቃል ከስጋ ጋር ሲዋሐድ በየትኛው ባህርይ ተዋሐደ?(3%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '14.ለምሥጢረ ሥላሴ ትምህርት ምሳሌ ሆነው ከሚያገለግሉ ፍጥረታት ውስጥ ፫ቱን ጥቀሱ?ምሳሌነታቸውም አስረዱ!(2%)', 'a' => ''],
    ['q' => '15.ተዓቅቦ ምንድን ነው?(2%)', 'a' => ''],
    ['q' => '16.ጠቅለል ባለ ሀሳብ እግዚአብሔር ወልድ እንዴት ሰው ሆነ?(2%)', 'a' => ''],
    ['q' => '17.ህጻናት ፵ ወይም ፹ ቀን ሳይሞላቸው ለሞት የሚያሰጋ ህመም ቢያጋጥማቸው አስቀድሞ ይጠመቁ ዘንድ ይገባልን?በመጽሐፍ ማስረጃ አስደግፋችሁ አብራሩ (1%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '18.ወልድ ለአብ ልጁ ከሆነ ለመንፈስ ቅዱስ ምኑ ነው? (3%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '20.ምሥጢረ ፈጣሪ ወይም ምሥጢረ እግዚአብሔር የምንለው እንዴት ነው?(2%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '21.ውህደት የሚነገረው እንዴት ነው?ለማን ነው?(3%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '22.በምሥጢረ ሥላሴ ኩነት ማለት ምን ማለት ነው?ኩነትን በቀጥታ ባህርይ ማለት ይቻላል?ካልተቻለስ ባህርይ የምንል ምኑን ነው ምን ሲሆን ነው?
በሰፊው አብራሩ(3%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '23.በፍጡራን ደረጃ የባህርይ ስምና የግብር ስም ማለት ምን ማለት ነው?
በምሳሌ አብራሩ?(3%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '24.አምስቱ አዕማደ ምስጢራት “ምስጢር” የተባሉበት ምክንያት ምንድን ነው??(1%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '25.ጌታችን በ30 ዘመኑ ከተጠመቀ ዛሬ ህጻናት ለምን በ40 እና በ80 ቀን ይጠመቃሉ?(1%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '26.የእግዚአብሔር ወልድ ልደታት ስንት ናቸው?ልደቶቹን በመዘርዘር ስለእነርሱ ገለጻ አድርጉ!(1%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => 'በምሥጢረ ሥጋዌ ወይም በነገረ ክርስቶስ ትምህርት በገዳመ ቆሮንቶስ 40 መዓልትና 40 ሌሊት የጾመው ማነው መለኮት ወይስ ትስብእት?ከ40 
መዓልትና ከ40 ሌሊት በኋላ የተራበውስ ማነው?መለኮት ወይስ ትስብእት?
በሰፊው አብራሩ!(3%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '28.የአብ ወላዲ አስራፂ፤የወልድ ተወላዲነት፤የመንፈስ ቅዱስ ሰራፂነት ተፈፅሟል አልተፈፀመም?አልተፈፀመም ካላችሁ ምክንያት ተፈጽሟል ካላችሁም በምክንያት አስረዱ(2%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '29.ንዴት እንዴት ጠፋ?(2%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '30.አብ ሕይወት ወልድ ሕይወት መንፈስ ቅዱስ ሕይወት እንላለን።መንፈስ ቅዱስ ደግሞ ለአብና ለወልድ ህይወታቸው ነው ብለናል ኩነት ላይ;ስለዚህ አብ ሕይወት ወልድ ሕይወት መንፈስ ቅዱስ ሕይወት ነው ካልን እንዴት መንፈስ ቅዱስ ለአብና ለወልድ ሕይወት ነው እንላለን?(2%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '31.አካላዊ ቃል ከስጋ ጋር ሲዋሐድ ባህርየ ሥላሴ ተዋሐደቺ ማለት ይቻላል?
ለምን?በምክንያት አስረዱ!(3%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '32.እግዚአብሔር የሁሉ አምላክ ወይስ የሁሉ አባት?በማስረጃ አብራሩ!', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '33.ጻድቃን "ንዑ ኀቤየ" ኑ የአባቴ ቡሩካን፤ ኃጥአን ደግሞ "ሑሩ እምኔየ"ከእኔ ሂዱ የሚለውን ቃል የሚሰሙት በየትኛው የትንሣኤ አይነት ነው?(2%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '34.አብ ወላዲ አስራፂ፤ወልድ ተወላዲ፤መንፈስ ቅዱስ ሠራፂ ስላልን አብ ከወልድና ከመንፈስ ቅዱስ ይበልጣልን? አብ ወላዲ አስራፂ ከሆነ ወላዲ አስራፂ የሚለው እንደሁለት ግብር ይታያልን? ወላዲ አስራፂ የሚለው እንደአንድ ግብር የሚታይ ከሆነስ ወልድን ሠራፂ መንፈስ ቅዱስ ተወላዲ ማለት ይቻላልን?በምሳሌ አብራሩ(2%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '35.ሀልዎተ እግዚአብሔር በምን ይታወቃል?(1%)?', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '36.በምሥጢረ ሥላሴ ትምህርት ግብረ ዋህድና ምንድን ነው? እንዴት ይታያል?(3%', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '37.ለሐዲስ ኪዳን ምሳሌነት ከሚያገለግሉ የብሉይ ኪዳን ቁርባን አይነቶች መካከል ፫ቱን በመጥቀስ በሠፊው ከእነ ምሳሌነታቸው አብራሩ?(2%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '38.አካላዊ ቃል ከስጋ ጋር ሲዋሐድ በየትኛው ባህርይ ተዋሐደ?(3%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '39.በምሥጢረ ሥጋዌ ትምህርት አክባሪ፤ከባሪ፤ክብር የሚሉ ነገሮች እያንዳንዳቸው ለማን ተሰጥተው ይነገራሉ?እንዴት ትረዷቸዋላችሁ?(3%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '40.አብና መንፈስ ቅዱስ ለምን ሰው አልሆኑም?(2%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '41.የሐዲስ ኪዳን ቁርባን ከብሉይ ኪዳን ቁርባን ልዩነቱን በሰፊው አብራሩ?(2%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '42.ትንሣኤ ዘክርስቶስ ከሌሎች የትንሣኤ አይነቶች እንዴት ይለያል?(2%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '43.ወንጌላዊ ቅዱስ ዮሐንስ ፩÷Ŧƈ ላይ "ወልድ ስጋ ሆነ"ማለት ሲቺል "ቃል ስጋ ሆነ"ያለው ለምንድን ነው?(3%)', 'a' => 'ዳታቤዝ ማስቀመጫ'], 
    ['q' => '44.እግዚአብሔር ወልድ ለምን ሰው ሆነ ሰው ሳይሆን ሰውን ማዳን አይቺልም?(2%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
    ['q' => '45.እግዚአብሔር ወልድ ለምን ሰው ሆነ ሰው ሳይሆን ሰውን ማዳን አይቺልም?(2%)በጊዜ ተዋህዶ የማይቀዳደሙ ፫ቱ ተግባራት እነማን ናቸው?ቢቀዳደሙስ የሚያመጣው ክህደት ምንድን ነው?(4%)', 'a' => 'ዳታቤዝ ማስቀመጫ'],
];


$score = 0;
$results = [];

// Build pages of questions (10 per page)
$questionsPerPage = 10;
$pages = [];
$totalQuestions = count($questions);
for ($i = 0; $i < $totalQuestions; $i += $questionsPerPage) {
    $slice = array_slice($questions, $i, $questionsPerPage);
    $pages[] = [
        'start_index' => $i,
        'questions' => $slice,
    ];
}
$totalPages = count($pages);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($timeExpired || time() >= $deadline) {
        $_SESSION['short_exam_message'] = 'የፈተና ጊዜ አልቋል፤ በ03:30:00 በኋላ መስጠት አይቻልም።';
        header('Location: short_exam.php');
        exit;
    }
    foreach ($questions as $index => $item) {
        $answer = trim($_POST['answer_' . $index] ?? '');
        $correct = strtolower($item['a']);
        $user = strtolower($answer);
        $isCorrect = $user === $correct;
        $results[$index] = [
            'question' => $item['q'],
            'your_answer' => $answer,
            'correct_answer' => $item['a'],
            'correct' => $isCorrect,
        ];
        if ($isCorrect) {
            $score++;
        }
    }

    $pdo->exec('CREATE TABLE IF NOT EXISTS exam_submissions (id INT AUTO_INCREMENT PRIMARY KEY, student_id VARCHAR(100) NOT NULL, student_name VARCHAR(255) NOT NULL, exam_type VARCHAR(50) NOT NULL, score INT NOT NULL DEFAULT 0, total_questions INT NOT NULL DEFAULT 0, answers JSON DEFAULT NULL, submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
    $stmt = $pdo->prepare('INSERT INTO exam_submissions (student_id, student_name, exam_type, score, total_questions, answers) VALUES (:student_id, :student_name, :exam_type, :score, :total_questions, :answers)');
    $stmt->execute([
        ':student_id' => $studentId,
        ':student_name' => $studentName,
        ':exam_type' => 'short_exam',
        ':score' => $score,
        ':total_questions' => count($questions),
        ':answers' => json_encode($results, JSON_UNESCAPED_UNICODE),
    ]);
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Short Answer Exam</title>
    <style>
        body { font-family: Arial, sans-serif; background: linear-gradient(180deg,#f8fafc,#eef2ff); color: #1f2937; margin: 0; }
        .wrap { max-width: 1100px; margin: 40px auto; padding: 28px; }
        .card { background: white; border-radius: 16px; padding: 32px; box-shadow: 0 18px 40px rgba(2,6,23,0.06); border: 1px solid rgba(14,165,233,0.04); }
        h1 { color: #4f46e5; margin-top: 0; }
        .q { margin-bottom: 18px; }
        .q p { font-weight: 700; margin-bottom: 8px; }
        .section-block { transition: transform .28s ease, opacity .22s ease; border-radius: 10px; padding: 18px; background: linear-gradient(180deg, rgba(14,165,233,0.03), rgba(14,165,233,0.02)); margin-bottom:14px; }
        .section-block.hidden { opacity: 0; transform: translateY(8px); pointer-events: none; }
        .section-block.active { opacity: 1; transform: translateY(0); pointer-events: auto; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; }
        button { background: #4f46e5; color: white; border: none; padding: 12px 16px; border-radius: 8px; cursor: pointer; font-size: 15px; }
        .nav-row { display:flex; align-items:center; gap:12px; }
        .nav-btn { width:44px; height:44px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; border:none; color:white; font-weight:700; box-shadow:0 6px 18px rgba(14,165,233,0.18); transition: transform .12s ease, box-shadow .12s ease; }
        .nav-btn:hover { transform: translateY(-3px); }
        .nav-btn:disabled { opacity: .45; cursor: not-allowed; transform: none; box-shadow:none; }
        .nav-btn.prev { background: #7dd3fc; color: #023047; }
        .nav-btn.next { background: #0ea5e9; }
        .nav-btn.submit-btn { width:auto; height:auto; padding:10px 16px; border-radius:8px; background:#10b981; box-shadow:0 8px 20px rgba(16,185,129,0.18); }
        .progress { height: 8px; background: #e6eefc; border-radius: 999px; overflow: hidden; margin-bottom: 6px; }
        .progress > span { display:block; height:100%; width:0%; background: linear-gradient(90deg,#06b6d4,#0ea5e9); transition: width .4s ease; }
        .result { background: #ecfdf5; border: 1px solid #a7f3d0; padding: 12px; border-radius: 10px; margin-top: 16px; }
        .wrong { background: #fff7ed; border-color: #fdba74; }
        .small { color: #555; font-size: 13px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Short Answer Exam</h1>
        <p class="small">እያንዳንዱን ጥያቄ በጽሁፍ መልስ ይመልሱ።</p>
        <p class="small" style="color:#b91c1c; font-weight:700;">የጊዜ ገደብ: 03:30:00 (210 ደቂቃ)</p>
        <div id="timerBox" class="result" style="margin-bottom:16px;">ቀሪ ጊዜ: <strong id="timerText">--:--:--</strong></div>
        <?php if (!empty($_SESSION['short_exam_message'])): ?>
            <div class="result" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;"><?php echo safe($_SESSION['short_exam_message']); ?></div>
            <?php unset($_SESSION['short_exam_message']); ?>
        <?php endif; ?>

        <form method="post" id="examForm">
            <?php foreach ($pages as $pageIndex => $page): ?>
                <div class="section-block <?php echo $pageIndex === 0 ? 'active' : ''; ?>" data-page="<?php echo $pageIndex; ?>" style="display: <?php echo $pageIndex === 0 ? 'block' : 'none'; ?>;">
                    <?php foreach ($page['questions'] as $qidx => $item): $globalIndex = $page['start_index'] + $qidx; ?>
                        <div class="q">
                            <p><?php echo $globalIndex + 1; ?>. <?php echo htmlspecialchars($item['q']); ?></p>
                            <input type="text" name="answer_<?php echo $globalIndex; ?>" placeholder="መልስዎን ይተይቡ" <?php echo $timeExpired ? 'disabled' : 'required'; ?> />
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

                <div class="nav-row" style="align-items:center;margin-top:18px;">
                <div style="flex:1; margin-right:12px;">
                    <div class="progress" aria-hidden="true"><span id="progressBar" style="width:0%"></span></div>
                    <div class="small" id="pageLabel">Page 1 of <?php echo $totalPages; ?></div>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <button type="button" id="prevBtn" class="nav-btn prev" aria-label="Previous" <?php echo $timeExpired ? 'disabled' : ''; ?>>◀</button>
                    <button type="button" id="nextBtn" class="nav-btn next" aria-label="Next" <?php echo $timeExpired ? 'disabled' : ''; ?>>▶</button>
                    <button type="submit" id="submitBtn" class="nav-btn submit-btn" style="display:none;" <?php echo $timeExpired ? 'disabled' : ''; ?>>ውጤት አሳይ</button>
                </div>
            </div>
        </form>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="result">
                <strong>ውጤት:</strong> <?php echo $score; ?> / <?php echo count($questions); ?>
            </div>
            <?php foreach ($results as $item): ?>
                <div class="result <?php echo $item['correct'] ? '' : 'wrong'; ?>">
                    <strong><?php echo htmlspecialchars($item['question']); ?></strong><br />
                    እርስዎ: <?php echo htmlspecialchars($item['your_answer']); ?><br />
                    ትክክለኛ መልስ: <?php echo htmlspecialchars($item['correct_answer']); ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
    <script>
        const deadline = <?php echo $deadline; ?> * 1000;
        const timerText = document.getElementById('timerText');
        const submitBtn = document.getElementById('submitBtn');
        const examForm = document.getElementById('examForm');

        // Paging controls
        const totalPages = <?php echo $totalPages; ?>;
        let currentPage = 0;
        const nextBtn = document.getElementById('nextBtn');
        const prevBtn = document.getElementById('prevBtn');
        const progressBar = document.getElementById('progressBar');
        const pageLabel = document.getElementById('pageLabel');

        function showPage(page) {
            const blocks = document.querySelectorAll('.section-block');
            blocks.forEach((b) => b.style.display = 'none');
            const active = document.querySelector('.section-block[data-page="' + page + '"]');
            if (active) active.style.display = 'block';
            currentPage = page;
            const pct = Math.round(((page + 1) / totalPages) * 100);
            if (progressBar) progressBar.style.width = pct + '%';
            if (pageLabel) pageLabel.textContent = 'Page ' + (page + 1) + ' of ' + totalPages;
            if (nextBtn) {
                if (page >= totalPages - 1) {
                    nextBtn.style.display = 'none';
                    if (submitBtn) submitBtn.style.display = 'inline-block';
                } else {
                    nextBtn.style.display = 'inline-block';
                    if (submitBtn) submitBtn.style.display = 'none';
                }
            }
            // focus first input on page
            const firstInput = document.querySelector('.section-block[data-page="' + page + '"] input[type="text"]');
            if (firstInput) firstInput.focus();
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                if (currentPage < totalPages - 1) {
                    showPage(currentPage + 1);
                }
            });
        }
        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                if (currentPage > 0) {
                    showPage(currentPage - 1);
                }
            });
        }

        // Initialize
        showPage(0);

        function updateTimer() {
            const now = Date.now();
            const diff = deadline - now;

            if (diff <= 0) {
                timerText.textContent = '00:00:00';
                if (submitBtn) submitBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = true;
                if (examForm) {
                    examForm.querySelectorAll('input[type="text"]').forEach((el) => el.disabled = true);
                }
                return;
            }

            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);
            timerText.textContent = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        }

        updateTimer();
        setInterval(updateTimer, 1000);
    </script>
</body>
</html>
