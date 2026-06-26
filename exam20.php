<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_config.php';

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

$defaultAccessCode = 'SOFI2721';
$accessCodeStmt = $pdo->prepare('SELECT access_code, is_active FROM exam_access_codes WHERE exam_type = :exam_type LIMIT 1');
$accessCodeStmt->execute([':exam_type' => $examType]);
$accessCodeRecord = $accessCodeStmt->fetch(PDO::FETCH_ASSOC);

if (empty($accessCodeRecord['access_code'])) {
    $insertCodeStmt = $pdo->prepare('INSERT INTO exam_access_codes (exam_type, access_code, is_active) VALUES (:exam_type, :access_code, 1) ON DUPLICATE KEY UPDATE access_code = VALUES(access_code), is_active = VALUES(is_active)');
    $insertCodeStmt->execute([
        ':exam_type' => $examType,
        ':access_code' => $defaultAccessCode,
    ]);

    $accessCodeStmt->execute([':exam_type' => $examType]);
    $accessCodeRecord = $accessCodeStmt->fetch(PDO::FETCH_ASSOC);
}

if (!empty($accessCodeRecord['access_code']) && isset($accessCodeRecord['is_active']) && (int)$accessCodeRecord['is_active'] === 0) {
    $activateStmt = $pdo->prepare('UPDATE exam_access_codes SET is_active = 1 WHERE exam_type = :exam_type AND access_code = :access_code');
    $activateStmt->execute([
        ':exam_type' => $examType,
        ':access_code' => $accessCodeRecord['access_code'],
    ]);
    $accessCodeRecord['is_active'] = 1;
}

$accessCodeIsActive = !empty($accessCodeRecord['is_active']);
$accessCode = $accessCodeRecord['access_code'] ?? $defaultAccessCode;
$accessCodeGranted = (!empty($_SESSION['exam20_access_granted']) && $_SESSION['exam20_access_granted'] === $examType);
$accessError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exam_access_submit'])) {
    $submittedCode = strtoupper(trim((string)($_POST['exam_access_code'] ?? '')));
    $expectedCode = strtoupper((string)($accessCode ?: $defaultAccessCode));

    if ($submittedCode === $expectedCode) {
        $activateStmt = $pdo->prepare('UPDATE exam_access_codes SET is_active = 1 WHERE exam_type = :exam_type');
        $activateStmt->execute([':exam_type' => $examType]);
        $_SESSION['exam20_access_granted'] = $examType;
        $_SESSION['exam20_access_granted_at'] = time();
        $accessCodeGranted = true;
    } else {
        $accessError = 'የተሳሳተ የፈተና ኮድ ነው። እባክዎ እንደገና ይሞክሩ።';
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

function sendCertificateEmail(PDO $pdo, string $studentId, string $studentName, string $examType, int $score, int $totalQuestions): void
{
    if ($totalQuestions <= 0 || $score < $totalQuestions) {
        return;
    }

    $stmt = $pdo->prepare('SELECT email FROM students WHERE student_id = :student_id LIMIT 1');
    $stmt->execute([':student_id' => $studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student || empty($student['email'])) {
        return;
    }

    $stmt = $pdo->prepare('INSERT IGNORE INTO certificates (student_id, student_name, exam_type, score, total_questions) VALUES (:student_id, :student_name, :exam_type, :score, :total_questions)');
    $stmt->execute([
        ':student_id' => $studentId,
        ':student_name' => $studentName,
        ':exam_type' => $examType,
        ':score' => $score,
        ':total_questions' => $totalQuestions,
    ]);

    $stmt = $pdo->prepare('SELECT id FROM certificates WHERE student_id = :student_id AND exam_type = :exam_type ORDER BY id DESC LIMIT 1');
    $stmt->execute([
        ':student_id' => $studentId,
        ':exam_type' => $examType,
    ]);
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$certificate) {
        return;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $downloadUrl = $scheme . '://' . $host . '/admin_certificate.php?download=' . (int)$certificate['id'];

    $message = '<p>Hello ' . safe($studentName) . ',</p>'
        . '<p>Congratulations! You completed the exam with a perfect score.</p>'
        . '<p>Your certificate has been generated automatically.</p>'
        . '<p><a href="' . safe($downloadUrl) . '">Download your certificate PDF</a></p>';

    sendAppEmail($student['email'], 'Your certificate is ready', $message);
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
$questionChunks = array_chunk($questions, 10);
$totalSections = count($questionChunks);

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

    if (count($questions) > 0 && $score === count($questions)) {
        sendCertificateEmail($pdo, $studentId, $studentName, $examType, $score, count($questions));
        $_SESSION['certificate_message'] = 'Congratulations! Your certificate has been generated and sent to your email.';
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Real Exam System</title>
    <style>
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #f8fbff 0%, #eef2ff 100%); color: #0f172a; margin: 0; }
        .wrap { max-width: 1120px; margin: 30px auto; padding: 20px; }
        .card { background: rgba(255,255,255,0.95); border-radius: 20px; padding: 24px; box-shadow: 0 18px 40px rgba(15,23,42,0.08); border: 1px solid #e2e8f0; backdrop-filter: blur(8px); }
        h1 { color: #4f46e5; margin-top: 0; }
        .badge { display:inline-block; background:#eef2ff; color:#3730a3; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; margin-right:8px; }
        .question { margin-bottom: 16px; padding: 14px; border-radius: 14px; border: 1px solid #e2e8f0; background: #fcfdff; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .question:hover { transform: translateY(-1px); box-shadow: 0 10px 20px rgba(15,23,42,0.05); }
        .question p { font-weight: 700; margin-bottom: 8px; }
        label { display: block; margin: 6px 0; cursor: pointer; font-size: 14px; padding: 8px 10px; border-radius: 10px; background: #f8fafc; border: 1px solid transparent; }
        label:hover { border-color: #c7d2fe; background: #f5f7ff; }
        input[type="radio"], input[type="text"] { accent-color: #4f46e5; }
        button { background: linear-gradient(135deg, #4f46e5, #2563eb); color: white; border: none; padding: 12px 18px; border-radius: 999px; font-size: 15px; cursor: pointer; box-shadow: 0 10px 20px rgba(79,70,229,0.18); }
        button:hover { transform: translateY(-1px); }
        .result { background: #ecfdf5; border: 1px solid #a7f3d0; padding: 12px; border-radius: 12px; margin-top: 14px; box-shadow: inset 0 1px 0 rgba(255,255,255,0.7); }
        .small { color: #555; font-size: 13px; }
        .exam-hero { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
        .progress-pill { display:inline-block; background:#eff6ff; color:#1d4ed8; padding:8px 10px; border-radius:999px; font-size:12px; font-weight:700; }
        .question-number { display:inline-flex; align-items:center; gap:8px; font-size:13px; font-weight:800; color:#4f46e5; margin-bottom:8px; }
        .question-number .chip { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:999px; background:linear-gradient(135deg, #4f46e5, #2563eb); color:white; font-size:12px; }
        .section-block { display: none; }
        .section-block.active { display: block; }
        .section-heading { display:flex; justify-content:space-between; align-items:center; gap:10px; margin: 0 0 12px; color:#1d4ed8; font-weight:700; }
        .progress { height:8px; background:#e2e8f0; border-radius:999px; overflow:hidden; margin-bottom:14px; }
        .progress > span { display:block; height:100%; width:0%; background:linear-gradient(90deg, #4f46e5, #2563eb); transition:width 0.25s ease; }
        .nav-row { display:flex; justify-content:space-between; align-items:center; gap:10px; margin-top:16px; }
        .nav-btn { background:linear-gradient(135deg, #4f46e5, #2563eb); color:white; border:none; padding:12px 18px; border-radius:999px; font-size:15px; cursor:pointer; box-shadow:0 10px 20px rgba(79,70,229,0.18); }
        .nav-btn.ghost { background:#e2e8f0; color:#334155; box-shadow:none; }
        .good { color:#166534; }
        .bad { color:#991b1b; }
        .answer-note { font-size: 13px; color:#475569; margin-top:6px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>እውነተኛ የፈተና ስርዓት</h1>
        <p class="small">ይህ እውነተኛ የሙከራ ጥያቄ ነው። በተሰጠው ጊዜ ውስጥ መልስ ይስጡ።</p>
        <div class="exam-hero">
            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                <span class="badge">⏱️ ሰአት መቆጣጠሪያ</span>
                <span class="badge">✅ ዉጤት ማስተካከያ</span>
                <span class="badge">📈 በቅጽበት ዉጤት አሳይ</span>
            </div>
            <span class="progress-pill">Questions: <?php echo count($questions); ?></span>
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
        <?php if (!empty($_SESSION['certificate_message'])): ?>
            <div class="result" style="background:#ecfdf5;border-color:#a7f3d0;color:#166534;"><?php echo safe($_SESSION['certificate_message']); ?></div>
            <?php unset($_SESSION['certificate_message']); ?>
        <?php endif; ?>
        <div class="result" style="background:#f8fafc;border-color:#cbd5e1;color:#334155; margin-bottom: 12px;">
            <strong>Exam flow:</strong> complete the access check, answer carefully, and submit before the timer ends.
        </div>
        <div class="result" style="background:#eff6ff;border-color:#bfdbfe;color:#1e3a8a; margin-bottom: 12px;">
            <strong>Premium mode:</strong> your answers are saved in a structured exam experience with instant feedback and a clear countdown.
        </div>
        <?php if (!$hasApproval): ?>
            <div class="result" style="background:#fef3c7;border-color:#f59e0b;color:#92400e;">ይህን ፈተና ለመጀመር ከአስተዳዳሪ ማረጋገጫ ያስፈልጋል። እባክዎ አስተዳዳሪውን ያስተማሙ።</div>
        <?php elseif (!$accessCodeGranted): ?>
            <div class="result" style="background:#eef2ff;border-color:#c7d2fe;color:#3730a3;">
                የፈተና ኮድ ያስገቡ።
            </div>
            <?php if ($accessError): ?>
                <div class="result" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;"><?php echo safe($accessError); ?></div>
            <?php endif; ?>
            <form method="post" style="margin-top: 12px; max-width: 420px;">
                <input type="hidden" name="exam_access_submit" value="1">
                <input type="text" name="exam_access_code" placeholder="SOFI2721" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px; margin-bottom: 10px;" />
                <button type="submit">አረጋግጥ</button>
            </form>
        <?php elseif (empty($questions)): ?>
            <div class="result" style="background:#fef3c7;border-color:#f59e0b;color:#92400e;">ምንም ጥያቄዎች አልተጫኑም። እባክዎ በ Admin ጥያቄዎች ውስጥ ጥያቄ ያክሉ።</div>
        <?php else: ?>
            <form method="post" id="examForm">
                <div class="result" style="background:#eff6ff;border-color:#bfdbfe;color:#1e3a8a;margin-bottom:12px;">
                    Please answer each question carefully. Your score will be shown immediately after submission.
                </div>
                <div class="progress" aria-hidden="true"><span id="progressFill"></span></div>
                <div class="result" style="background:#f8fafc;border-color:#cbd5e1;color:#334155; margin-bottom: 12px;">
                    <strong>Section:</strong> <span id="sectionInfo">Section 1 of <?php echo max(1, $totalSections); ?></span>
                </div>
                <?php $globalIndex = 0; ?>
                <?php foreach ($questionChunks as $chunkIndex => $chunk): ?>
                    <div class="section-block <?php echo $chunkIndex === 0 ? 'active' : ''; ?>" data-section="<?php echo $chunkIndex + 1; ?>">
                        <div class="section-heading">
                            <span>Section <?php echo (int)($chunkIndex + 1); ?></span>
                            <span><?php echo count($chunk); ?> Questions</span>
                        </div>
                        <?php foreach ($chunk as $item): ?>
                            <div class="question">
                                <div class="question-number"><span class="chip"><?php echo (int)($globalIndex + 1); ?></span> Question <?php echo (int)($globalIndex + 1); ?> of <?php echo count($questions); ?></div>
                                <p><?php echo safe($item['question_text'] ?? ''); ?></p>
                                <?php $options = buildExamOptions($item); ?>
                                <?php if (!empty($options)): ?>
                                    <?php foreach ($options as $option): ?>
                                        <label>
                                            <input type="radio" name="q<?php echo (int)($item['id'] ?? $globalIndex); ?>" value="<?php echo safe((string)($option['label'] ?? '')); ?>" />
                                            <?php echo safe($option['label'] . '. ' . $option['text']); ?>
                                        </label>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <input type="text" name="q<?php echo (int)($item['id'] ?? $globalIndex); ?>" value="<?php echo safe($_POST['q' . ($item['id'] ?? $globalIndex)] ?? ''); ?>" placeholder="መልስዎን ይተይቡ" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;" />
                                <?php endif; ?>
                                <?php if ($submitted): ?>
                                    <?php $selected = trim((string)($_POST['q' . ($item['id'] ?? $globalIndex)] ?? '')); ?>
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
                            <?php $globalIndex++; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <div class="nav-row">
                    <button type="button" class="nav-btn ghost" id="prevBtn" style="display:none;">Previous</button>
                    <button type="button" class="nav-btn" id="nextBtn">Next</button>
                    <button type="submit" class="nav-btn" id="submitBtn" style="display:none;" <?php echo ($timeExpired || $hasSubmittedExam) ? 'disabled' : ''; ?>>Submit</button>
                </div>
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
        const totalSections = <?php echo max(1, $totalSections); ?>;
        let currentSection = 0;
        const sections = Array.from(document.querySelectorAll('.section-block'));
        const progressFill = document.getElementById('progressFill');
        const sectionInfo = document.getElementById('sectionInfo');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');

        function updateNav() {
            sections.forEach((section, index) => {
                section.classList.toggle('active', index === currentSection);
            });
            const progress = ((currentSection + 1) / totalSections) * 100;
            progressFill.style.width = progress + '%';
            sectionInfo.textContent = 'Section ' + (currentSection + 1) + ' of ' + totalSections;
            prevBtn.style.display = currentSection === 0 ? 'none' : 'inline-block';
            nextBtn.style.display = currentSection === totalSections - 1 ? 'none' : 'inline-block';
            submitBtn.style.display = currentSection === totalSections - 1 ? 'inline-block' : 'none';
        }

        prevBtn.addEventListener('click', () => {
            if (currentSection > 0) {
                currentSection--;
                updateNav();
            }
        });

        nextBtn.addEventListener('click', () => {
            if (currentSection < totalSections - 1) {
                currentSection++;
                updateNav();
            }
        });

        updateNav();

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
