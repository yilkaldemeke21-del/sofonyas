<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit;
}

$studentId = (string)($_SESSION['student_id'] ?? '');
$studentName = $_SESSION['student_name'] ?? 'Student';
$scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');

function normalizeExamType(string $examType): string
{
    $value = strtolower(trim($examType));
    $value = preg_replace('/\s+/', '_', $value);
    if (in_array($value, ['mid', 'midexam', 'mid_exam'], true)) {
        return 'mid_exam';
    }
    if (in_array($value, ['final', 'finalexam', 'final_exam'], true)) {
        return 'final_exam';
    }
    return $value;
}

$examType = normalizeExamType($scriptName === 'final_exam.php' ? 'final_exam' : 'mid_exam');
if ($scriptName === 'exam.php') {
    $requestedExamType = normalizeExamType((string)($_GET['exam_type'] ?? ''));
    if (in_array($requestedExamType, ['mid_exam', 'final_exam'], true)) {
        $examType = $requestedExamType;
    }
}

$accessCode = strtoupper(trim((string)($_GET['access_code'] ?? '')));
$quizLink = null;
$accessError = '';
$timeExpired = false;
$examDeadline = time();

// Handle access code submission (form POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exam_access_submit'])) {
    $submitted = strtoupper(trim((string)($_POST['exam_access_code'] ?? '')));
    if ($submitted !== '') {
        // Check quiz_link_generators first
        $checkStmt = $pdo->prepare('SELECT * FROM quiz_link_generators WHERE REPLACE(LOWER(exam_type), \' \', \'_\') = :exam_type AND access_code = :access_code LIMIT 1');
        $checkStmt->execute([':exam_type' => $examType, ':access_code' => $submitted]);
        $found = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if ($found) {
            $redirect = basename($_SERVER['SCRIPT_NAME']) . '?access_code=' . rawurlencode($submitted);
            if (!empty($_GET['exam_type'])) {
                $redirect = basename($_SERVER['SCRIPT_NAME']) . '?exam_type=' . rawurlencode($_GET['exam_type']) . '&access_code=' . rawurlencode($submitted);
            }
            header('Location: ' . $redirect);
            exit;
        } else {
            // Fallback to exam_access_codes
            $codeStmt = $pdo->prepare('SELECT * FROM exam_access_codes WHERE exam_type = :exam_type AND access_code = :access_code LIMIT 1');
            $codeStmt->execute([':exam_type' => $examType, ':access_code' => $submitted]);
            $codeFound = $codeStmt->fetch(PDO::FETCH_ASSOC);
            if ($codeFound) {
                $redirect = basename($_SERVER['SCRIPT_NAME']) . '?access_code=' . rawurlencode($submitted);
                if (!empty($_GET['exam_type'])) {
                    $redirect = basename($_SERVER['SCRIPT_NAME']) . '?exam_type=' . rawurlencode($_GET['exam_type']) . '&access_code=' . rawurlencode($submitted);
                }
                header('Location: ' . $redirect);
                exit;
            }
            $accessError = 'የተሳሳተ የፈተና ኮድ ነው። እባክዎ ደግመው ይሞክሩ።';
        }
    }
}

$pdo->exec('CREATE TABLE IF NOT EXISTS quiz_link_generators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_title VARCHAR(255) NOT NULL,
    exam_type VARCHAR(50) NOT NULL,
    link_url TEXT NOT NULL,
    access_code VARCHAR(50) NOT NULL,
    expiry_minutes INT NOT NULL DEFAULT 60,
    timer_minutes INT NOT NULL DEFAULT 210,
    one_attempt TINYINT(1) NOT NULL DEFAULT 1,
    qr_code_svg TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

ensureExamAccessTables($pdo);
ensureExamSubmissionsTable($pdo);

function ensureExamSubmissionsTable(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS exam_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        exam_type VARCHAR(50) NOT NULL,
        access_code VARCHAR(50) NOT NULL,
        score INT NOT NULL DEFAULT 0,
        total_questions INT NOT NULL DEFAULT 0,
        answers JSON DEFAULT NULL,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_exam_submission_unique (student_id, exam_type, access_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $columnCheck = $pdo->query("SHOW COLUMNS FROM exam_submissions LIKE 'access_code'")->fetch(PDO::FETCH_ASSOC);
    if (!$columnCheck) {
        $pdo->exec('ALTER TABLE exam_submissions ADD COLUMN access_code VARCHAR(50) NOT NULL AFTER exam_type');
    }
}

function buildExamRedirectUrl(array $quizLink): string
{
    $url = trim((string)($quizLink['link_url'] ?? ''));
    if ($url === '') {
        return '';
    }
    if (stripos($url, 'access_code=') === false) {
        $url .= strpos($url, '?') === false ? '?' : '&';
        $url .= 'access_code=' . rawurlencode((string)($quizLink['access_code'] ?? ''));
    }
    return $url;
}

function formatExamDuration(int $minutes): string
{
    $hours = floor($minutes / 60);
    $remainingMinutes = $minutes % 60;
    return sprintf('%02d:%02d:00', $hours, $remainingMinutes);
}

if ($accessCode !== '') {
    $stmt = $pdo->prepare('SELECT * FROM quiz_link_generators WHERE REPLACE(LOWER(exam_type), \' \' , \'_\') = :exam_type AND access_code = :access_code LIMIT 1');
    $stmt->execute([
        ':exam_type' => $examType,
        ':access_code' => $accessCode,
    ]);
    $quizLink = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare('SELECT * FROM quiz_link_generators WHERE REPLACE(LOWER(exam_type), \' \' , \'_\') = :exam_type ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([':exam_type' => $examType]);
    $latestLink = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($latestLink) {
        $redirectUrl = buildExamRedirectUrl($latestLink);
        if ($redirectUrl !== '') {
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}

if (!$quizLink) {
    $stmt = $pdo->prepare('SELECT * FROM exam_access_codes WHERE exam_type = :exam_type AND is_active = 1 LIMIT 1');
    $stmt->execute([':exam_type' => $examType]);
    $activeCode = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($activeCode && ($accessCode === '' || strtoupper(trim((string)$activeCode['access_code'])) === $accessCode)) {
        $quizLink = [
            'access_code' => $activeCode['access_code'],
            'created_at' => $activeCode['created_at'] ?? date('Y-m-d H:i:s'),
            'expiry_minutes' => 99999,
            'timer_minutes' => 210,
            'one_attempt' => 1,
        ];
        $_GET['access_code'] = $activeCode['access_code'];
    }
}

if (!$quizLink) {
    $accessError = 'ይቅርታ፣ የፈተና መግቢያ ኮድ ወይም መንገድ ከሆነ ከተሳሳተ ወይም አልተገኘም።';
}

if (!$accessError && $quizLink) {
    $enrollmentStmt = $pdo->prepare('SELECT COUNT(*) as total FROM registrations WHERE student_id = :student_id');
    $enrollmentStmt->execute([':student_id' => $studentId]);
    $enrollment = (int)($enrollmentStmt->fetchColumn() ?? 0);
    if ($enrollment === 0) {
        $accessError = 'ይቅርታ፣ ይህን ቅጽ ለመጠቀም በውስጥ ምዝገባ ያለዎት ተማሪ መሆን ይኖርቦታል።';
    }

    $pdo->exec('CREATE TABLE IF NOT EXISTS exam_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        exam_type VARCHAR(50) NOT NULL,
        access_code VARCHAR(50) NOT NULL,
        score INT NOT NULL DEFAULT 0,
        total_questions INT NOT NULL DEFAULT 0,
        answers JSON DEFAULT NULL,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_exam_submission_unique (student_id, exam_type, access_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    if ((int)$quizLink['one_attempt'] === 1) {
        $usedStmt = $pdo->prepare('SELECT COUNT(*) as total FROM exam_submissions WHERE exam_type = :exam_type AND access_code = :access_code');
        $usedStmt->execute([
            ':exam_type' => $examType,
            ':access_code' => $quizLink['access_code'],
        ]);
        $usedCount = (int)($usedStmt->fetchColumn() ?? 0);
        if ($usedCount > 0) {
            $accessError = 'ይቅርታ፣ ይህ የፈተና ኮድ አንድ ጊዜ ብቻ ሊጠቀሙ ይችላሉ።';
        }
    }
}

function getExamSessionKey(string $examType, string $key): string
{
    return sprintf('%s_%s', $examType, $key);
}

$timerMinutes = 210;
$examDeadline = time();
$timeExpired = false;
if (!$accessError && $quizLink) {
    $timerMinutes = max(5, (int)$quizLink['timer_minutes']);
    $startedKey = getExamSessionKey($examType, 'started_at');
    $deadlineKey = getExamSessionKey($examType, 'deadline');
    $accessKey = getExamSessionKey($examType, 'access_code');

    if (!isset($_SESSION[$startedKey]) || !isset($_SESSION[$deadlineKey]) || ($_SESSION[$accessKey] ?? '') !== $quizLink['access_code']) {
        $_SESSION[$startedKey] = time();
        $_SESSION[$deadlineKey] = $_SESSION[$startedKey] + ($timerMinutes * 60);
        $_SESSION[$accessKey] = $quizLink['access_code'];
    }

    $examDeadline = (int)($_SESSION[$deadlineKey] ?? time());
    $timeExpired = time() >= $examDeadline;
    if ($timeExpired) {
        $accessError = 'ይቅርታ፣ የፈተና ጊዜ አልቋል።';
    }
}

$pdo->exec('CREATE TABLE IF NOT EXISTS question_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    instruction TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$pdo->exec('CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_type VARCHAR(30) NOT NULL DEFAULT "multiple_choice",
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL DEFAULT "",
    option_b VARCHAR(255) NOT NULL DEFAULT "",
    option_c VARCHAR(255) NOT NULL DEFAULT "",
    option_d VARCHAR(255) NOT NULL DEFAULT "",
    correct_answer VARCHAR(255) NOT NULL DEFAULT "",
    section_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_questions_section (section_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

try {
    $pdo->exec('ALTER TABLE questions ADD COLUMN section_id INT DEFAULT NULL');
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

$stmt = $pdo->query('SELECT q.*, s.title AS section_title, s.instruction AS section_instruction, COALESCE(q.section_id, 0) AS section_id FROM questions q LEFT JOIN question_sections s ON s.id = q.section_id ORDER BY COALESCE(q.section_id, 0) ASC, q.created_at ASC');
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pages = [];
$currentSectionId = null;
$currentPageQuestions = [];
$questionsPerPage = 10;

foreach ($questions as $question) {
    $sectionId = (int)($question['section_id'] ?? 0);

    if ($currentSectionId !== $sectionId || count($currentPageQuestions) >= $questionsPerPage) {
        if (!empty($currentPageQuestions)) {
            $pages[] = [
                'section_id' => $currentSectionId,
                'section_title' => $currentPageQuestions[0]['section_title'] ?: 'General Section',
                'section_instruction' => $currentPageQuestions[0]['section_instruction'] ?? '',
                'questions' => $currentPageQuestions,
            ];
        }
        $currentSectionId = $sectionId;
        $currentPageQuestions = [];
    }

    $currentPageQuestions[] = $question;
}

if (!empty($currentPageQuestions)) {
    $pages[] = [
        'section_id' => $currentSectionId,
        'section_title' => $currentPageQuestions[0]['section_title'] ?: 'General Section',
        'section_instruction' => $currentPageQuestions[0]['section_instruction'] ?? '',
        'questions' => $currentPageQuestions,
    ];
}

$totalSections = count($pages);
$score = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$accessError && !$timeExpired) {
    foreach ($questions as $index => $item) {
        $answer = trim((string)($_POST['q' . $index] ?? ''));
        if (isCorrectExamAnswer($item, $answer)) {
            $score++;
        }
    }

    if ($quizLink) {
        $answers = [];
        foreach ($questions as $index => $item) {
            $answers[] = [
                'question' => $item['question_text'] ?? '',
                'selected' => trim((string)($_POST['q' . $index] ?? '')),
                'correct' => $item['correct_answer'] ?? '',
                'is_correct' => isCorrectExamAnswer($item, trim((string)($_POST['q' . $index] ?? ''))),
            ];
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS exam_submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(100) NOT NULL,
            student_name VARCHAR(255) NOT NULL,
            exam_type VARCHAR(50) NOT NULL,
            access_code VARCHAR(50) NOT NULL,
            score INT NOT NULL DEFAULT 0,
            total_questions INT NOT NULL DEFAULT 0,
            answers JSON DEFAULT NULL,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_exam_submission_unique (student_id, exam_type, access_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $stmt = $pdo->prepare('INSERT IGNORE INTO exam_submissions (student_id, student_name, exam_type, access_code, score, total_questions, answers) VALUES (:student_id, :student_name, :exam_type, :access_code, :score, :total_questions, :answers)');
        $stmt->execute([
            ':student_id' => $studentId,
            ':student_name' => $studentName,
            ':exam_type' => $examType,
            ':access_code' => $quizLink['access_code'] ?? '',
            ':score' => $score,
            ':total_questions' => count($questions),
            ':answers' => json_encode($answers, JSON_UNESCAPED_UNICODE),
        ]);
        // If a submission was recorded, deactivate any matching exam_access_codes so the code cannot be reused.
        try {
            if (!empty($quizLink['access_code'])) {
                $deact = $pdo->prepare('UPDATE exam_access_codes SET is_active = 10 WHERE access_code = :access_code AND exam_type = :exam_type');
                $deact->execute([
                    ':access_code' => $quizLink['access_code'],
                    ':exam_type' => $examType,
                ]);
            }
        } catch (Exception $e) {
            // non-fatal: continue even if marking inactive fails
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
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #f8fbff 0%, #eef2ff 100%); color: #0f172a; margin: 0; }
        .wrap { max-width: 960px; margin: 30px auto; padding: 20px; }
        .card { background: rgba(255,255,255,0.96); border-radius: 20px; padding: 24px; box-shadow: 0 18px 40px rgba(15,23,42,0.08); border: 1px solid #e2e8f0; }
        h1 { color: #4f46e5; margin-top: 0; }
        .question { margin-bottom: 18px; padding: 14px; border-radius: 14px; border: 1px solid #e2e8f0; background: #fcfdff; }
        .question p { font-weight: 700; margin-bottom: 10px; }
        label { display: block; margin: 6px 0; cursor: pointer; padding: 8px 10px; border-radius: 10px; background: #f8fafc; border: 1px solid transparent; transition: all 0.2s ease; }
        label:hover { border-color: #c7d2fe; background: #f5f7ff; transform: translateY(-1px); }
        .answer-option.selected { background: #eff6ff; border-color: #93c5fd; box-shadow: 0 8px 16px rgba(59,130,246,0.12); }
        .answer-option input { accent-color: #4f46e5; }
        .answer-text { width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; transition: border-color 0.2s ease, box-shadow 0.2s ease; }
        .answer-text:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,0.12); outline: none; }
        .submit-btn { background: linear-gradient(135deg, #4f46e5, #2563eb); color: white; border: none; padding: 12px 18px; border-radius: 999px; font-size: 15px; cursor: pointer; box-shadow: 0 10px 20px rgba(79,70,229,0.18); transition: transform 0.2s ease, opacity 0.2s ease; }
        .submit-btn:hover { transform: translateY(-1px); }
        .submit-btn.submitting { opacity: 0.8; transform: scale(0.98); }
        .section-block { display: none; }
        .section-block.active { display: block; }
        .section-heading { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 12px; color: #1d4ed8; font-weight: 700; }
        .nav-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-top: 16px; }
        .nav-btn { background: linear-gradient(135deg, #4f46e5, #2563eb); color: white; border: none; padding: 12px 18px; border-radius: 999px; font-size: 15px; cursor: pointer; box-shadow: 0 10px 20px rgba(79,70,229,0.18); }
        .nav-btn.ghost { background: #e2e8f0; color: #334155; box-shadow: none; }
        .progress { height: 8px; background: #e2e8f0; border-radius: 999px; overflow: hidden; margin-bottom: 16px; }
        .progress > span { display: block; height: 100%; width: 0%; background: linear-gradient(90deg, #4f46e5, #2563eb); transition: width 0.25s ease; }
        .result { background: #ecfdf5; border: 1px solid #a7f3d0; padding: 14px; border-radius: 12px; margin-top: 16px; }
        .small { color: #555; font-size: 13px; }
        .chip { display: inline-block; padding: 6px 10px; border-radius: 999px; background: #eff6ff; color: #1d4ed8; font-size: 12px; font-weight: 700; }
</style>
    <style>
        body.locked { overflow: hidden; }
    </style>
</head>
<body<?php echo empty($quizLink) ? ' class="locked"' : ''; ?>>
<div class="wrap">
    <div class="card">
        <h1>Online Multiple Choice Exam</h1>
        <p class="small">ቀላል እና ቀጥታ የሚሰራ ፒ.ኤች.ፒ መልመጃ ገጽ።</p>
        <p class="small" style="margin-top:-4px; color:#1d4ed8;">ይህ ፈተና ከአድሚኑ የተጨመሩት ጥያቄዎች እና ክፍሎች ላይ ይመሠረታል።</p>

        <?php if ($accessError): ?>
            <div class="result" style="background:#fee2e2;border:1px solid #fecaca;color:#991b1b;"><?php echo safe($accessError); ?></div>
            <form method="post" style="margin-top:12px; max-width:420px;">
                <input type="hidden" name="exam_access_submit" value="1">
                <input type="text" name="exam_access_code" placeholder="Enter access code" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px; margin-bottom: 10px;" />
                <button type="submit" class="submit-btn">አረጋግጥ</button>
            </form>
        <?php elseif (empty($questions)): ?>
            <div class="result" style="background:#fef3c7;border:1px solid #f59e0b;color:#92400e;">እስካሁን ምንም ጥያቄ አልተጨመረም። አድሚኑ ጥያቄዎችን እንዲጨምር ይጠይቁ።</div>
        <?php else: ?>
        <div class="result" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;margin-bottom:16px;">
            <strong>Timer:</strong> <?php echo formatExamDuration($timerMinutes); ?>. Please finish before the timer expires.
        </div>
        <form method="post" id="examForm">
            <div class="progress" aria-hidden="true"><span id="progressFill"></span></div>
            <div class="small chip" id="sectionInfo">Section 1 of <?php echo max(1, $totalSections); ?></div>

            <?php $globalIndex = 0; ?>
            <?php foreach ($pages as $pageIndex => $page): ?>
                <div class="section-block <?php echo $pageIndex === 0 ? 'active' : ''; ?>" data-section="<?php echo $pageIndex + 1; ?>">
                    <div class="section-heading">
                        <span><?php echo htmlspecialchars((string)($page['section_title'] ?? 'General Section')); ?></span>
                        <span><?php echo count($page['questions']); ?> Questions</span>
                    </div>
                    <?php if (!empty($page['section_instruction'])): ?>
                        <p class="small" style="margin-top:-10px;margin-bottom:12px;"><?php echo htmlspecialchars((string)($page['section_instruction'])); ?></p>
                    <?php endif; ?>
                    <?php foreach ($page['questions'] as $item): ?>
                        <div class="question">
                            <p><?php echo $globalIndex + 1; ?>. <?php echo htmlspecialchars((string)($item['question_text'] ?? '')); ?></p>
                            <?php $options = buildExamOptions($item); ?>
                            <?php if (!empty($options)): ?>
                                <?php foreach ($options as $option): ?>
                                    <label class="answer-option">
                                        <input type="radio" name="q<?php echo $globalIndex; ?>" value="<?php echo htmlspecialchars((string)($option['label'] ?? '')); ?>" />
                                        <?php echo htmlspecialchars((string)($option['label'] ?? '') . '. ' . (string)($option['text'] ?? '')); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <input type="text" class="answer-text" name="q<?php echo $globalIndex; ?>" value="<?php echo htmlspecialchars((string)($_POST['q' . $globalIndex] ?? '')); ?>" placeholder="መልስዎን ይተይቡ" />
                            <?php endif; ?>
                        </div>
                        <?php $globalIndex++; ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="nav-row">
                <button type="button" class="nav-btn ghost" id="prevBtn" style="display:none;">Previous</button>
                <button type="button" class="nav-btn" id="nextBtn">Next</button>
                <button type="submit" class="nav-btn submit-btn" id="submitBtn" style="display:none;">Submit</button>
            </div>
        </form>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$accessError && !$timeExpired): ?>
            <div class="result">
                <strong>ውጤት:</strong> እርስዎ <?php echo $score; ?> / <?php echo count($questions); ?> መልሳት በትክክል መለሱ።
            </div>
            <div class="result" style="background:#f8fafc;border:1px solid #e2e8f0;color:#334155;">
                <a href="results.php" style="color:#2563eb;text-decoration:none;">View your exam results</a>
            </div>
        <?php elseif ($timeExpired): ?>
            <div class="result" style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;">
                ይቅርታ፣ የፈተና ጊዜ አልቋል። እባክዎ ይሄንን ቅጽ ይዘው ይግቡ።
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
    <script>
        const totalSections = <?php echo max(1, $totalSections); ?>;
        let currentSection = 0;
        const sections = Array.from(document.querySelectorAll('.section-block'));
        const progressFill = document.getElementById('progressFill');
        const sectionInfo = document.getElementById('sectionInfo');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const submitBtn = document.getElementById('submitBtn');

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

        document.querySelectorAll('.answer-option input').forEach((input) => {
            const option = input.closest('.answer-option');
            const setSelected = () => {
                document.querySelectorAll('.answer-option').forEach((item) => item.classList.toggle('selected', item === option && input.checked));
            };
            input.addEventListener('change', setSelected);
            input.addEventListener('focus', () => option.classList.add('selected'));
            input.addEventListener('blur', () => {
                if (!input.checked) {
                    option.classList.remove('selected');
                }
            });
            setSelected();
        });

        document.querySelectorAll('.answer-text').forEach((input) => {
            input.addEventListener('focus', () => input.classList.add('focused'));
            input.addEventListener('blur', () => input.classList.remove('focused'));
        });

        const form = document.getElementById('examForm');
        const submitButton = document.getElementById('submitBtn');
        if (form && submitButton) {
            form.addEventListener('submit', () => {
                submitButton.classList.add('submitting');
                submitButton.textContent = 'Submitting...';
            });
        }

        <?php if (!$accessError && $quizLink): ?>
        const deadline = <?php echo (int)$examDeadline; ?> * 1000;
        const timerText = document.createElement('div');
        timerText.style.marginBottom = '16px';
        timerText.style.fontWeight = '700';
        timerText.textContent = 'Remaining time: --:--:--';
        document.querySelector('.wrap .card').insertBefore(timerText, document.querySelector('.wrap .card > .result'));

        function updateTimer() {
            const now = Date.now();
            const diff = deadline - now;
            if (diff <= 0) {
                timerText.textContent = '00:00:00';
                if (submitButton) submitButton.disabled = true;
                if (form) {
                    form.querySelectorAll('input, button').forEach((el) => el.disabled = true);
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
        <?php endif; ?>
    </script>
</body>
</html>
