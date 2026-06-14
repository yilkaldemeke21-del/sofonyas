<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit;
}

$studentId = (int)$_SESSION['student_id'];
$studentName = $_SESSION['student_name'] ?? 'Student';
$EXAM_LIMIT_SECONDS = 150 * 60;

$pdo->exec('CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_type VARCHAR(30) NOT NULL DEFAULT "multiple_choice",
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL DEFAULT "",
    option_b VARCHAR(255) NOT NULL DEFAULT "",
    option_c VARCHAR(255) NOT NULL DEFAULT "",
    option_d VARCHAR(255) NOT NULL DEFAULT "",
    correct_answer VARCHAR(255) NOT NULL DEFAULT "",
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

try {
    $pdo->exec('ALTER TABLE questions ADD COLUMN question_type VARCHAR(30) NOT NULL DEFAULT "multiple_choice"');
} catch (PDOException $e) {
}

function normalizeExamAnswer(string $value): string
{
    $value = mb_strtolower($value, 'UTF-8');
    $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';
    $value = preg_replace('/\s+/u', ' ', $value);
    return trim($value);
}

function isCorrectExamAnswer(array $question, string $selected): bool
{
    $type = $question['question_type'] ?? 'multiple_choice';

    if (in_array($type, ['multiple_choice', 'true_false'], true)) {
        $correct = strtoupper(trim((string)($question['correct_answer'] ?? '')));
        $selected = strtoupper(trim($selected));

        if ($type === 'true_false') {
            return $selected === 'TRUE' || $selected === 'FALSE' ? $selected === $correct : false;
        }

        return $selected === $correct;
    }

    $expected = normalizeExamAnswer((string)($question['correct_answer'] ?? ''));
    $actual = normalizeExamAnswer($selected);

    return $expected !== '' && $expected === $actual;
}

function buildExamOptions(array $question): array
{
    $type = $question['question_type'] ?? 'multiple_choice';

    if (in_array($type, ['short_answer', 'fill_in_blank'], true)) {
        return [];
    }

    if ($type === 'true_false') {
        return [
            ['label' => 'TRUE', 'text' => $question['option_a'] ?: 'True'],
            ['label' => 'FALSE', 'text' => $question['option_b'] ?: 'False'],
        ];
    }

    return [
        ['label' => 'A', 'text' => $question['option_a'] ?? ''],
        ['label' => 'B', 'text' => $question['option_b'] ?? ''],
        ['label' => 'C', 'text' => $question['option_c'] ?? ''],
        ['label' => 'D', 'text' => $question['option_d'] ?? ''],
    ];
}

$questionCount = (int)$pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn();
if ($questionCount === 0) {
    $seed = $pdo->prepare('INSERT INTO questions (question_type, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES (:question_type, :question_text, :option_a, :option_b, :option_c, :option_d, :correct_answer)');
    $seed->execute([':question_type' => 'multiple_choice', ':question_text' => 'ከሚከተሉት መካከል ትክክለኛ መልስ የቱ ነው?', ':option_a' => 'ሀ. እግዚአብሔር ነው', ':option_b' => 'ለ. ወልድ ነው', ':option_c' => 'ሐ. ሃይማኖት ነው', ':option_d' => 'መ. እንደዚህ አይደለም', ':correct_answer' => 'A']);
    $seed->execute([':question_type' => 'true_false', ':question_text' => 'ጥሩ ትምህርት ግልጽ ነው።', ':option_a' => 'True', ':option_b' => 'False', ':option_c' => '', ':option_d' => '', ':correct_answer' => 'TRUE']);
    $seed->execute([':question_type' => 'short_answer', ':question_text' => 'ቋንቋ እንዴት ይባላል?', ':option_a' => '', ':option_b' => '', ':option_c' => '', ':option_d' => '', ':correct_answer' => 'ቋንቋ']);
    $questionCount = 3;
}

$stmt = $pdo->query('SELECT * FROM questions ORDER BY created_at DESC LIMIT 30');
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!isset($_SESSION['exam20_started_at'])) {
    $_SESSION['exam20_started_at'] = time();
    $_SESSION['exam20_deadline'] = $_SESSION['exam20_started_at'] + $EXAM_LIMIT_SECONDS;
}

$startedAt = (int)($_SESSION['exam20_started_at'] ?? time());
$deadline = (int)($_SESSION['exam20_deadline'] ?? ($startedAt + $EXAM_LIMIT_SECONDS));
$timeExpired = (time() >= $deadline);

$questions = [
    ['question' => '1. እግዚአብሔር ወልድ ከቅድስት ድንግል ማርያም ከሥጋዋ ሥጋን ከነፍሷ ነፍስን ነስቶ ሲዋሐድ ለወልድ ብቻ የሚሰጥ ግብር የትኛው ነው?', 'options' => ['ሀ. ሥጋን መክፈል', 'ለ. ሥጋን ማክበር', 'ሐ. ሥጋንና መለኮትን ማዋሐድ', 'መ. ለሥጋ ክብር መሆን'], 'correct' => 'መ. ለሥጋ ክብር መሆን'],
    ['question' => '2. ስለምስጠመረ ሥላሴ ትክክል ያልሆነው የቱ ነው?', 'options' => ['ሀ. ሥላሴ ማለት ሦስት ማለት ነው', 'ለ. አብ አምላክ፤ ወልድ አምላክ፤ መንፈስ ቅዱስ አምላክ፤ አንድ አምላክ', 'ሐ. ሥላሴ በስም በአካል በግብር በኩነት ሦስት ሲሆኑ በመለኮት አንድ ናቸው', 'መ. መልስ የለም'], 'correct' => 'ሀ. ሥላሴ ማለት ሦስት ማለት ነው'],
    ['question' => '3. ስለ እግዚአብሔር ወልድ ዳግም ልደትና የማዳን ስራ የሚያስረዳን ምስጢር ምን ይባላል?', 'options' => ['ሀ. ምስጢረ ሥላሴ', 'ለ. ትንሣኤ ዘክርስቶስ', 'ሐ. ምስጢረ ሥጋዌ', 'መ. ምስጢረ ቁርባን'], 'correct' => 'ሐ. ምስጢረ ሥጋዌ'],
    ['question' => '4.ሃይማኖት ማለት በአንድ እግዚአብሔር በህላዌ መለኮቱ፤
በፈጣሪነቱ፤ በመጋቢነቱ ፤በባህርይ ግብራቱ ማመን ለእርሱም
ለእርሱም መታመን ማለት ነው።የሚለው የሃይማኖት ትርጉም የምን
ትርጉም ይባላል?', 'options' => ['ሀ.ሕይወታዊ ትርጉም', 'ለ.ምስጢራዊ ትርጉም', 'ሐ.ፊደላዊ ትርጉም', 'መ.መዝገበ ቃላዊ ትርጉም'], 'correct' => 'ለ.ምስጢራዊ ትርጉም'],
    ['question' => '5.እምነት ማለት በዓይን ሳያዩ፤በጆሮ ሳይሰሙ ሳይመረምሩ መቀበል
ነው።', 'options' => ['ሀ.ሀሰት', 'ለ.እውነት', 'ሐ.መልስ የለም', 'መ. አይባልም'], 'correct' => 'ሀ.ሀሰት'],
    ['question' => '6.በደቡብ ወሎ ዞሮ በጣም በርካታ ሰው አለ። ከእነዚህም መካከል
ሶፎንያስ በመቅደላ አምባ ዩኒቨርሲቲ የ4ኛ ዓመት ተማሪ ነው፤ሶፎንያስ
ለ12ኛ ክፍል፤ለ1ኛ ዓመትና ለሁለተኛ ዓመት ተማሪዎች የነገረ
ሃይማኖት በመስጠት ለተወሰኑ ወራት መምህራቸው በመሆን ሲሰጥ
ከቆዬ በኋላ አሁን ላይ ኮርሱን በሰላም አጠናቆ ፈተና በመስጠት ላይ
ይገኛል። የተሰመረባቸው ቃላት በቅደም ተከተል ምንን ያመለክታሉ?', 'options' => ['ሀ.የአካል ስም፤የባህርይ ስም፤የግብር ስም', 'ለ.የግብር ስም፤የባህርይ ስም፤የአካል ስም', 'ሐ.የባህርይ ስም፤የአካል ስም፤የግብር ስም', 'መ.የአካል ስም፤የባህርይ ስም፤የተዋህዶ ስም'], 'correct' => 'ሐ.የባህርይ ስም፤የአካል ስም፤የግብር ስም'],
    ['question' => '7.አፍአዊ ግብር ማለት ምን ማለት ነው?', 'options' => ['ሀ.በዓለመ ሥላሴ ብቻ የሚነገረ ወላዲ፤ተዋላዲ፤ሠራፂ የሚባሉት ናቸው።', 'ለ.ዓለምን የመፍጠር፣የመመገብ ፣የማሳለፍ ስራ', 'ሐ.ሥላሴ በየግላቸው የሚጠሩበት ስራ ነው።', 'መ. ሀ እና ሐ መልስ ናቸው።'], 'correct' => 'ለ.ዓለምን የመፍጠር፣የመመገብ ፣የማሳለፍ ስራ'],
    ['question' => '8.በምስጢረ ሥላሴ ትምህርት ስለ ኩነት ትክክል ያልሆነው የቱ ነው?', 'options' => ['ሀ.አብ ቃል፤ወልድ ልብ፤መንፈስ ቅዱስ እስትንፋስ ነው።', 'ለ.አብ ልብ፤ወልድ ቃል፤መንፈስ ቅዱስ ሕይወት ነው።', 'ሐ.መንፈስ ቅዱስ እስትንፋስ፤ወልድ ቃል፤አብ ልብ ነው።', 'መ.ሁሉም መልስ ናቸው።'], 'correct' => 'ሀ.አብ ቃል፤ወልድ ልብ፤መንፈስ ቅዱስ እስትንፋስ ነው።'],
    ['question' => '9.ሥላሴን በአካል ፫ ስንል ልብ ልንላቸው የሚገቡ ነገሮች የትኞቹ ናቸው?', 'options' => ['ሀ.ሦስት አካል ስንል ሦስት እግዚአብሔር የምንል መሆኑ', 'ለ.እያንዳንዳቸው ልብ፣ቃል፣እስትንፋስ አላቸው የምንል መሆኑ', 'ሐ.ሦስት አካል ስንል አንዱ አካል ከሌላው አካል ተለይቶ የተገኘበት
ዘመን የሚታወቅና አባት ከልጁ ቀድሞ የሚገኝ መሆኑ፤', 'መ. መልስ የለም'], 'correct' => 'መ. መልስ የለም'],
    ['question' => '10.አብ በሁሉ የመላ ከሆነ ወልድና መንፈስ ቅዱስ በምን ይመላሉ?', 'options' => ['ሀ.አባታችን ሆይ በሰማይ የምትኖር የምንለውም ከምድር ከፍ ብሎ
በሰማይ ስለሚኖሩ ነው።', 'ለ.እነርሱ የሚኖሩት በምድር ነው።', 'ሐ.እነርሱ የሚኖሩ በራሳቸው ዓለምነት ነው', 'መ.መልስ የለም'], 'correct' => 'ሐ.እነርሱ የሚኖሩ በራሳቸው ዓለምነት ነው'],
    ['question' => '12.በኦሪት ዘፍጥረት "ሰውን እንደምሳሌያችንና እንደ መልካችን
እንፍጠር ሲሉ ሥላሴ ይህ የምን ግብር ነው??', 'options' => ['ሀ.አፍአዊ ግብር', 'ለ.ውሳጣዊ ግብር', 'ሐ.ግብረ ዋህድና', 'መ.ሀ እና ለ'], 'correct' => 'መ.ሀ እና ለ'],
    
];

$score = 0;
$answers = [];
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($timeExpired || time() >= $deadline) {
        $_SESSION['exam20_message'] = 'የፈተና ጊዜ አልቋል፤ በ2:30 ሰዓት በኋላ መስጠት አይቻልም።';
        header('Location: exam20.php');
        exit;
    }

    $submitted = true;

    foreach ($questions as $item) {
        $answer = trim((string)($_POST['q' . (int)$item['id']] ?? ''));
        $isCorrect = isCorrectExamAnswer($item, $answer);

        if ($isCorrect) {
            $score++;
        }

        $answers[] = [
            'question' => $item['question_text'] ?? '',
            'selected' => $answer,
            'correct' => $item['correct_answer'] ?? '',
            'is_correct' => $isCorrect,
            'type' => $item['question_type'] ?? 'multiple_choice',
        ];
    }

    $pdo->exec('CREATE TABLE IF NOT EXISTS exam_submissions (id INT AUTO_INCREMENT PRIMARY KEY, student_id VARCHAR(100) NOT NULL, student_name VARCHAR(255) NOT NULL, exam_type VARCHAR(50) NOT NULL, score INT NOT NULL DEFAULT 0, total_questions INT NOT NULL DEFAULT 0, answers JSON DEFAULT NULL, submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
    $stmt = $pdo->prepare('INSERT INTO exam_submissions (student_id, student_name, exam_type, score, total_questions, answers) VALUES (:student_id, :student_name, :exam_type, :score, :total_questions, :answers)');
    $stmt->execute([
        ':student_id' => $studentId,
        ':student_name' => $studentName,
        ':exam_type' => 'exam20',
        ':score' => $score,
        ':total_questions' => count($questions),
        ':answers' => json_encode($answers, JSON_UNESCAPED_UNICODE),
    ]);
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Real Exam System</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7fb; color: #233; margin: 0; }
        .wrap { max-width: 1100px; margin: 30px auto; padding: 20px; }
        .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        h1 { color: #4f46e5; margin-top: 0; }
        .badge { display:inline-block; background:#eef2ff; color:#3730a3; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; margin-right:8px; }
        .question { margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #e5e7eb; }
        .question p { font-weight: 700; margin-bottom: 8px; }
        label { display: block; margin: 4px 0; cursor: pointer; font-size: 14px; }
        button { background: #4f46e5; color: white; border: none; padding: 12px 18px; border-radius: 8px; font-size: 15px; cursor: pointer; }
        .result { background: #ecfdf5; border: 1px solid #a7f3d0; padding: 12px; border-radius: 8px; margin-top: 14px; }
        .small { color: #555; font-size: 13px; }
        .good { color:#166534; }
        .bad { color:#991b1b; }
        .answer-note { font-size: 13px; color:#475569; margin-top:6px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Real Exam System</h1>
        <p class="small">ይህ አሁን ከተጠቃሚ የተገኘ ጥያቄ ባንክ ላይ የሚሰራ ፈተና ስርዓት ነው።</p>
        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:8px;">
            <span class="badge">Timer</span>
            <span class="badge">Auto-Grading</span>
            <span class="badge">Instant Result</span>
        </div>
        <p class="small" style="color:#b91c1c; font-weight:700;">የጊዜ ገደብ: 2:30 ሰዓት (150 ደቂቃ)</p>
        <div id="timerBox" class="result" style="margin-bottom:16px;">ቀሪ ጊዜ: <strong id="timerText">--:--:--</strong></div>
        <?php if (!empty($_SESSION['exam20_message'])): ?>
            <div class="result" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;"><?php echo safe($_SESSION['exam20_message']); ?></div>
            <?php unset($_SESSION['exam20_message']); ?>
        <?php endif; ?>
        <?php if (empty($questions)): ?>
            <div class="result" style="background:#fef3c7;border-color:#f59e0b;color:#92400e;">ምንም ጥያቄዎች አልተጫኑም። እባክዎ በ Admin ጥያቄዎች ውስጥ ጥያቄ ያክሉ።</div>
        <?php else: ?>
            <form method="post" id="examForm">
                <?php foreach ($questions as $index => $item): ?>
                    <div class="question">
                        <p><?php echo safe($item['question_text'] ?? ''); ?></p>
                        <?php $options = buildExamOptions($item); ?>
                        <?php if (!empty($options)): ?>
                            <?php foreach ($options as $option): ?>
                                <label>
                                    <input type="radio" name="q<?php echo (int)$item['id']; ?>" value="<?php echo safe($option['label']); ?>" required />
                                    <?php echo safe($option['label'] . '. ' . $option['text']); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <input type="text" name="q<?php echo (int)$item['id']; ?>" value="<?php echo safe($_POST['q' . $item['id']] ?? ''); ?>" placeholder="መልስዎን ይተይቡ" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;" />
                        <?php endif; ?>
                        <?php if ($submitted): ?>
                            <?php $selected = trim((string)($_POST['q' . $item['id']] ?? '')); ?>
                            <?php $correct = (string)($item['correct_answer'] ?? ''); ?>
                            <div class="answer-note <?php echo isCorrectExamAnswer($item, $selected) ? 'good' : 'bad'; ?>">
                                <?php if (isCorrectExamAnswer($item, $selected)): ?>
                                    ✓ ትክክለኛ መልስ ነው።
                                <?php else: ?>
                                    ✗ ትክክለኛ መልስ: <?php echo safe($correct); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <button type="submit" id="submitBtn" <?php echo $timeExpired ? 'disabled' : ''; ?>>ውጤት አሳይ</button>
            </form>
        <?php endif; ?>
        <?php if ($submitted && !empty($questions)): ?>
            <div class="result">
                <strong>Instant Result:</strong> እርስዎ <?php echo (int)$score; ?> / <?php echo count($questions); ?> በትክክል መልሱ።
                <br />ውጤታችሁ በስርዓቱ ተቀምጧል እና የጥያቄዎች ውጤት በቅጽበት ተመልሷል።
            </div>
        <?php endif; ?>
    </div>
</div>
    <script>
        const deadline = <?php echo $deadline; ?> * 1000;
        const timerText = document.getElementById('timerText');
        const submitBtn = document.getElementById('submitBtn');
        const examForm = document.getElementById('examForm');

        function updateTimer() {
            const now = Date.now();
            const diff = deadline - now;

            if (diff <= 0) {
                timerText.textContent = '00:00:00';
                if (submitBtn) submitBtn.disabled = true;
                if (examForm) {
                    examForm.querySelectorAll('input[type="radio"]').forEach((el) => el.disabled = true);
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
