<?php
$questions = [
    [
        'question' => '1.እግዚአብሔር ወልድ ከቅድስት ድንግል ማርያም ከሥጋዋ ሥጋን
ከነፍሷ ነፍስን ነስቶ ሲዋሐድ ለወልድ ብቻ የሚሰጥ ግብር የትኛው
ነው?',
        'options' => ['ሀ.ሥጋን መክፈል', 'ሥጋን ማክበር', 'ሐ.ሥጋንና መለኮትን ማዋሐድ', 'መ.ለሥጋ ክብር መሆን'],
        'correct' => 'መ.ለሥጋ ክብር መሆን',
    ],
    [
        'question' => '2.ስለምስጠመረ ሥላሴ ትክክል ያልሆነው የቱ ነው?',
        'options' => ['ሀ.ሥላሴ ማለት ሦስት ማለት ነው', 'ለ.አብ አምላክ፤ወልድ አምላክ፤መንፈስ ቅዱስ አምላክ፤አንድ
አምላክ', 'ሐ.ሥላሴ በስም በአካል በግብር በኩነት ሦስት ሲሆኑ በመለኮት
አንድ ናቸው።', 'መ.መልስ የለም።'],
        'correct' => 'ሀ.ሥላሴ ማለትሦስት ማለት ነው።',
    ],
    [
        'question' => '3.ስለ እግዚአብሔር ወልድ ዳግም ልደትና የማዳን ስራ የሚያስረዳን
ምስጢር ምን ይባላል?',
        'options' => ['ሀ.ምስጢረ ሥላሴ', 'ለ.ትንሣኤ ዘክርስቶስ', 'ሐ.ምስጢረ ሥጋዌ', 'መ.ምስጢረ ቁርባን'],
        'correct' => 'ሐ.ምስጢረ ሥጋዌ',
    ],
];

$score = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($questions as $index => $item) {
        $answer = $_POST['q' . $index] ?? '';
        if ($answer === $item['correct']) {
            $score++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Online Multiple Choice Exam</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7fb; color: #233; margin: 0; }
        .wrap { max-width: 900px; margin: 30px auto; padding: 20px; }
        .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        h1 { color: #4f46e5; margin-top: 0; }
        .question { margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid #e5e7eb; }
        .question p { font-weight: 700; margin-bottom: 10px; }
        label { display: block; margin: 6px 0; cursor: pointer; }
        button { background: #4f46e5; color: white; border: none; padding: 12px 18px; border-radius: 8px; font-size: 15px; cursor: pointer; }
        .result { background: #ecfdf5; border: 1px solid #a7f3d0; padding: 14px; border-radius: 8px; margin-top: 16px; }
        .small { color: #555; font-size: 13px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Online Multiple Choice Exam</h1>
        <p class="small">ቀላል እና ቀጥታ የሚሰራ ፒ.ኤች.ፒ መልመጃ ገጽ።</p>

        <form method="post">
            <?php foreach ($questions as $index => $item): ?>
                <div class="question">
                    <p><?php echo $index + 1; ?>. <?php echo htmlspecialchars($item['question']); ?></p>
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
                <strong>ውጤት:</strong> እርስዎ <?php echo $score; ?> / <?php echo count($questions); ?> መልሳት በትክክል መለሱ።
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
