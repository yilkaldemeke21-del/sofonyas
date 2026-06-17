<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit;
}

ensureExamAccessTables($pdo);

$studentId = (string)($_SESSION['student_id'] ?? '');
$studentName = $_SESSION['student_name'] ?? 'Student';
$examType = 'exam20';
$EXAM_LIMIT_SECONDS = 150 * 60;

$approvalStmt = $pdo->prepare('SELECT status, approved_by, approved_at FROM student_exam_approvals WHERE student_id = :student_id AND exam_type = :exam_type LIMIT 1');
$approvalStmt->execute([
    ':student_id' => $studentId,
    ':exam_type' => $examType,
]);
$approvalRecord = $approvalStmt->fetch(PDO::FETCH_ASSOC);
$hasApproval = (($approvalRecord['status'] ?? '') === 'approved');

$accessCodeStmt = $pdo->prepare('SELECT access_code, is_active FROM exam_access_codes WHERE exam_type = :exam_type LIMIT 1');
$accessCodeStmt->execute([':exam_type' => $examType]);
$accessCodeRecord = $accessCodeStmt->fetch(PDO::FETCH_ASSOC);
$accessCodeIsActive = !empty($accessCodeRecord['is_active']);
$accessCode = $accessCodeRecord['access_code'] ?? '';
$accessCodeGranted = (!empty($_SESSION['exam20_access_granted']) && $_SESSION['exam20_access_granted'] === $examType);
$accessError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exam_access_submit'])) {
    $submittedCode = strtoupper(trim((string)($_POST['exam_access_code'] ?? '')));
    if (!$accessCodeIsActive || $accessCode === '' || !hash_equals(strtoupper($accessCode), $submittedCode)) {
        $accessError = 'የተሳሳተ የፈተና ኮድ ነው። እባክዎ እንደገና ይሞክሩ።';
    } else {
        $_SESSION['exam20_access_granted'] = $examType;
        $_SESSION['exam20_access_granted_at'] = time();
        $accessCodeGranted = true;
    }
}

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

$stmt = $pdo->query('SELECT * FROM questions ORDER BY created_at DESC LIMIT 30');
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!isset($_SESSION['exam20_started_at'])) {
    $_SESSION['exam20_started_at'] = time();
    $_SESSION['exam20_deadline'] = $_SESSION['exam20_started_at'] + $EXAM_LIMIT_SECONDS;
}

$startedAt = (int)($_SESSION['exam20_started_at'] ?? time());
$deadline = (int)($_SESSION['exam20_deadline'] ?? ($startedAt + $EXAM_LIMIT_SECONDS));
$currentTime = time();
$timeExpired = ($currentTime >= $deadline);

$existingSubmissionStmt = $pdo->prepare('SELECT id, score, total_questions, submitted_at FROM exam_submissions WHERE student_id = :student_id AND exam_type = :exam_type ORDER BY submitted_at DESC LIMIT 1');
$existingSubmissionStmt->execute([':student_id' => $studentId, ':exam_type' => $examType]);
$existingSubmission = $existingSubmissionStmt->fetch(PDO::FETCH_ASSOC);
$hasSubmittedExam = !empty($existingSubmission);

$score = 0;
$answers = [];
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['exam_access_submit'])) {
    if ($hasSubmittedExam) {
        $_SESSION['exam20_message'] = 'ይህን ፈተና አስቀድሞ መልስዎ ተመዝግቧል። እንደገና መስጠት አይቻልም።';
        header('Location: exam20.php');
        exit;
    }

    if (!$hasApproval) {
        $_SESSION['exam20_message'] = 'ይህን ፈተና ለመጀመር ከአስተዳዳሪ ማረጋገጫ ያስፈልጋል።';
        header('Location: exam20.php');
        exit;
    }

    if (!$accessCodeGranted) {
        $_SESSION['exam20_message'] = 'ይህን ፈተና ለመጀመር የፈተና ኮድ መግባት ያስፈልጋል።';
        header('Location: exam20.php');
        exit;
    }

    if ($timeExpired) {
        $_SESSION['exam20_message'] = 'የፈተና ጊዜ አልቋል፤ በ3:00 ሰዓት በኋላ መስጠት አይቻልም።';
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
        ':exam_type' => $examType,
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
        <h1>እዉነተኛ የፈተና ሥርዓት</h1>
        <p class="small">ይህ ጥያቄ በቤተ ገብርኤል በኦንላይን ሲማሩ ለቆዩ የቤተ ክርስቲያን ልጆች የወጣ የማጠቃለያ ጥያቄ ሲሆን ጥያቄው የሚሰራዉ በተሰጠው ሰአት መሰረት ላይ የሚሰራ የፈተና ስርዓት ነው።</p>
        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:8px;">
            <span class="badge">ሰአት መቆጣጠሪያ</span>
            <span class="badge">ዉጤት ማስተካከያ</span>
            <span class="badge">በቅጽበት ዉጤት አሳይ</span>
        </div>
        <p class="small" style="color:#b91c1c; font-weight:700;">የጊዜ ገደብ: 2:30 ሰዓት (150 ደቂቃ)</p>
        <div id="timerBox" class="result" style="margin-bottom:16px;">ቀሪ ጊዜ: <strong id="timerText">--:--:--</strong></div>
        <?php if ($hasSubmittedExam): ?>
            <div class="result" style="background:#fef3c7;border-color:#f59e0b;color:#92400e;">ይህን ፈተና አስቀድሞ መልስዎ ተመዝግቧል። እንደገና መስጠት አይቻልም።</div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['exam20_message'])): ?>
            <div class="result" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;"><?php echo safe($_SESSION['exam20_message']); ?></div>
            <?php unset($_SESSION['exam20_message']); ?>
        <?php endif; ?>
        <?php if (!$hasApproval): ?>
            <div class="result" style="background:#fef3c7;border-color:#f59e0b;color:#92400e;">ይህን ፈተና ለመጀመር ከአስተዳዳሪ ማረጋገጫ ያስፈልጋል። እባክዎ አስተዳዳሪውን ያስተማሙ።</div>
        <?php elseif (!$accessCodeGranted): ?>
            <div class="result" style="background:#eef2ff;border-color:#c7d2fe;color:#3730a3;">
                ፈተናውን ለመክፈት የፈተና ኮድ ያስገቡ።
            </div>
            <?php if ($accessError): ?>
                <div class="result" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;"><?php echo safe($accessError); ?></div>
            <?php endif; ?>
            <form method="post" style="margin-top: 12px; max-width: 420px;">
                <input type="hidden" name="exam_access_submit" value="1">
                <input type="text" name="exam_access_code" placeholder="ኮድ ያስገቡ" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px; margin-bottom: 10px;" />
                <button type="submit">ኮድ አረጋግጥ</button>
            </form>
        <?php elseif (empty($questions)): ?>
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
                                    <input type="radio" name="q<?php echo (int)($item['id'] ?? $index); ?>" value="<?php echo safe((string)($option['label'] ?? '')); ?>" required />
                                    <?php echo safe($option['label'] . '. ' . $option['text']); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <input type="text" name="q<?php echo (int)($item['id'] ?? $index); ?>" value="<?php echo safe($_POST['q' . ($item['id'] ?? $index)] ?? ''); ?>" placeholder="መልስዎን ይተይቡ" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;" />
                        <?php endif; ?>
                        <?php if ($submitted): ?>
                            <?php $selected = trim((string)($_POST['q' . ($item['id'] ?? $index)] ?? '')); ?>
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
                <button type="submit" id="submitBtn" <?php echo ($timeExpired || $hasSubmittedExam) ? 'disabled' : ''; ?>>ውጤት አሳይ</button>
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
                    examForm.querySelectorAll('input').forEach((el) => el.disabled = true);
                    examForm.submit();
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
