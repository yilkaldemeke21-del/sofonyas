<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit;
}

$studentId = (int)$_SESSION['student_id'];
$studentName = $_SESSION['student_name'] ?? 'Student';

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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($questions as $index => $item) {
        $answer = $_POST['q' . $index] ?? '';
        $isCorrect = ($answer === $item['correct']);
        if ($isCorrect) {
            $score++;
        }
        $answers[] = [
            'question' => $item['question'],
            'selected' => $answer,
            'correct' => $item['correct'],
            'is_correct' => $isCorrect,
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
    <title>12 Question Exam</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7fb; color: #233; margin: 0; }
        .wrap { max-width: 1100px; margin: 30px auto; padding: 20px; }
        .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        h1 { color: #4f46e5; margin-top: 0; }
        .question { margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #e5e7eb; }
        .question p { font-weight: 700; margin-bottom: 8px; }
        label { display: block; margin: 4px 0; cursor: pointer; font-size: 14px; }
        button { background: #4f46e5; color: white; border: none; padding: 12px 18px; border-radius: 8px; font-size: 15px; cursor: pointer; }
        .result { background: #ecfdf5; border: 1px solid #a7f3d0; padding: 12px; border-radius: 8px; margin-top: 14px; }
        .small { color: #555; font-size: 13px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>12 Question Online Exam</h1>
        <p class="small">ይህ በ PHP የተሰራ 12 ጥያቄ ስርዓት ነው።</p>
        <form method="post">
            <?php foreach ($questions as $index => $item): ?>
                <div class="question">
                    <p><?php echo $item['question']; ?></p>
                    <?php foreach ($item['options'] as $option): ?>
                        <label>
                            <input type="radio" name="q<?php echo $index; ?>" value="<?php echo htmlspecialchars($option); ?>" required />
                            <?php echo htmlspecialchars($option); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit">ውጤት አሳይ</button>
        </form>
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="result">
                <strong>ውጤት:</strong> እርስዎ <?php echo $score; ?> / <?php echo count($questions); ?> በትክክል መለሱ።
                <br />ውጤታችሁ በስርዓቱ ተቀምጧል።
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
