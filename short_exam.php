<?php
$questions = [
    ['q' => '?', 'a' => 'ሰነድ ቋንቋ'],
    ['q' => 'HTML ለምን ያገለግላል?', 'a' => 'ድር ገጽ መዋቢያ'],
    ['q' => 'MySQL ምን ያደርጋል?', 'a' => 'ዳታቤዝ ማስቀመጫ'],
];

$score = 0;
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Short Answer Exam</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f7ff; color: #1f2937; margin: 0; }
        .wrap { max-width: 980px; margin: 30px auto; padding: 20px; }
        .card { background: white; border-radius: 14px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        h1 { color: #4f46e5; margin-top: 0; }
        .q { margin-bottom: 18px; }
        .q p { font-weight: 700; margin-bottom: 8px; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; }
        button { background: #4f46e5; color: white; border: none; padding: 12px 16px; border-radius: 8px; cursor: pointer; font-size: 15px; }
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

        <form method="post">
            <?php foreach ($questions as $index => $item): ?>
                <div class="q">
                    <p><?php echo $index + 1; ?>. <?php echo htmlspecialchars($item['q']); ?></p>
                    <input type="text" name="answer_<?php echo $index; ?>" placeholder="መልስዎን ይተይቡ" required />
                </div>
            <?php endforeach; ?>
            <button type="submit">ውጤት አሳይ</button>
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
</body>
</html>
