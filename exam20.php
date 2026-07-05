<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_config.php';

$previewMode = !empty($_GET['preview']) && isset($_SESSION['admin_id']);
if (!$previewMode && !isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit;
}

ensureExamAccessTables($pdo);

$studentId = $previewMode ? 'admin-preview' : (string)($_SESSION['student_id'] ?? '');
$studentName = $previewMode ? 'Admin Preview' : ($_SESSION['student_name'] ?? 'Student');
$examType = 'exam20';
$EXAM_LIMIT_SECONDS = 210 * 60; // 210 minutes = 3 hours 30 minutes

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
$accessCodeGranted = $previewMode || (!empty($_SESSION['exam20_access_granted']) && $_SESSION['exam20_access_granted'] === $examType);
$accessError = '';

if ($previewMode) {
    $hasApproval = true;
    $accessCodeGranted = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exam_access_submit']) && !$previewMode) {
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
    $pdo->exec('ALTER TABLE questions ADD COLUMN question_type VARCHAR(30) NOT NULL DEFAULT "multiple_choice"');
} catch (PDOException $e) {
}

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

$stmt = $pdo->query('SELECT q.*, s.title AS section_title, s.instruction AS section_instruction, COALESCE(q.section_id, 0) AS section_id FROM questions q LEFT JOIN question_sections s ON s.id = q.section_id ORDER BY COALESCE(q.section_id, 0) ASC, q.created_at ASC LIMIT 45');
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

$totalPages = count($pages);

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
        $_SESSION['exam20_message'] = 'የፈተና ጊዜ አልቋል፤ በ03:30:00 በኋላ መስጠት አይቻልም።';
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

    $sheetPayload = [
        'name' => $studentName,
        'email' => $_SESSION['student_email'] ?? '',
        'student_id' => $studentId,
        'exam_title' => $examType,
        'access_code' => $accessCode ?? '',
        'score' => $score,
        'total_questions' => count($questions),
        'answers' => json_encode($answers, JSON_UNESCAPED_UNICODE),
        'remarks' => 'Auto graded exam20 submission',
        'submitted_at' => date('Y-m-d H:i:s'),
        'source' => 'exam20',
    ];
    $sheetResult = sendGoogleSheetsExamSync($sheetPayload);
    if (!$sheetResult['success']) {
        error_log('Google Sheets exam20 sync failed: ' . ($sheetResult['error'] ?? 'unknown') . ' response=' . print_r($sheetResult['response'], true));
    }

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
        body { font-family: Arial, sans-serif; background: #f4f7fb; color: #233; margin: 0; }
        body.admin-preview { background: linear-gradient(135deg, #e0f2fe 0%, #dbeafe 40%, #bfdbfe 100%); }
        body.admin-preview .card { border: 1px solid #93c5fd; box-shadow: 0 24px 50px rgba(37, 99, 235, 0.15); }
        body.admin-preview .badge { background: #bfdbfe; color: #1e40af; }
        body.admin-preview .section-card { box-shadow: 0 14px 32px rgba(59,130,246,0.18); }
        body.admin-preview .nav-btn { box-shadow: 0 12px 24px rgba(37,99,235,0.16); }
        .wrap { max-width: 1100px; margin: 30px auto; padding: 20px; }
        .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        h1 { color: #4f46e5; margin-top: 0; }
        .badge { display:inline-block; background:#eef2ff; color:#3730a3; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; margin-right:8px; }
        .question { margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #e5e7eb; }
        .question p { font-weight: 700; margin-bottom: 8px; }
        .section-block { display: none; }
        .section-block.active { display: block; }
        .section-heading { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 14px; font-weight: 700; color: #1d4ed8; }
        .progress { height: 8px; background: #e5e7eb; border-radius: 999px; overflow: hidden; margin-bottom: 16px; }
        .progress > span { display: block; height: 100%; width: 0%; background: linear-gradient(90deg,#2563eb,#38bdf8); transition: width 0.25s ease; }
        .nav-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-top: 16px; }
        .nav-btn { background: #4f46e5; color: white; border: none; padding: 12px 18px; border-radius: 999px; font-size: 15px; cursor: pointer; }
        .nav-btn.ghost { background: #e5e7f5; color: #1f2937; }
        .submit-btn { background: #16a34a; }
        .chip { display: inline-block; padding: 6px 10px; border-radius: 999px; background: #dbeafe; color: #1d4ed8; font-size: 12px; font-weight: 700; }
        label { display: block; margin: 4px 0; cursor: pointer; font-size: 14px; }
        button { background: #4f46e5; color: white; border: none; padding: 12px 18px; border-radius: 8px; font-size: 15px; cursor: pointer; }
        .result { background: #ecfdf5; border: 1px solid #a7f3d0; padding: 12px; border-radius: 8px; margin-top: 14px; }
        .small { color: #555; font-size: 13px; }
        .good { color:#166534; }
        .bad { color:#991b1b; }
        .answer-note { font-size: 13px; color:#475569; margin-top:6px; }
        body.locked { overflow: hidden; }
        /* Section grid */
        .section-grid { display:flex; gap:10px; flex-wrap:wrap; margin: 14px 0 18px; }
        .section-card { flex: 1 0 90px; min-width:80px; padding:12px; border-radius:10px; color:#fff; font-weight:700; text-align:center; cursor:pointer; box-shadow: 0 8px 20px rgba(2,6,23,0.06); transition: transform .12s ease, opacity .12s ease; }
        .section-card.disabled { opacity:.35; cursor:not-allowed; transform:none; box-shadow:none; }
        .section-card.active { outline: 3px solid rgba(255,255,255,0.15); transform: translateY(-4px); }
        .section-card[data-index='1']{ background: linear-gradient(90deg,#ef4444,#f97316); }
        .section-card[data-index='2']{ background: linear-gradient(90deg,#f97316,#f59e0b); }
        .section-card[data-index='3']{ background: linear-gradient(90deg,#f59e0b,#eab308); }
        .section-card[data-index='4']{ background: linear-gradient(90deg,#84cc16,#16a34a); }
        .section-card[data-index='5']{ background: linear-gradient(90deg,#10b981,#06b6d4); }
        .section-card[data-index='6']{ background: linear-gradient(90deg,#06b6d4,#3b82f6); }
        .section-card[data-index='7']{ background: linear-gradient(90deg,#3b82f6,#7c3aed); }
        .section-card[data-index='8']{ background: linear-gradient(90deg,#7c3aed,#ec4899); }
        .section-card[data-index='9']{ background: linear-gradient(90deg,#ec4899,#ef4444); }
        .section-card[data-index='10']{ background: linear-gradient(90deg,#0ea5e9,#06b6d4); }
    </style>
</head>
<body class="<?php echo $previewMode ? 'admin-preview' : ''; ?><?php echo empty($accessCodeGranted) ? ' locked' : ''; ?>">
<div class="wrap">
    <div class="card">
        <h1><?php echo $previewMode ? 'Admin Preview: Exam Page' : 'እውነተኛ የፈተና ስርዓት'; ?></h1>
        <p class="small">ይህ እውነተኛ የሙከራ ጥያቄ ነው። በተሰጠው ጊዜ ውስጥ መልስ ይስጡ።</p>
        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:8px;">
            <span class="badge">ሰአት መቆጣጠሪያ</span>
            <span class="badge">ዉጤት ማስተካከያ</span>
            <span class="badge">በቅጽበት ዉጤት አሳይ</span>
        </div>
        <?php if ($previewMode): ?>
            <div class="result" style="background:#e0f2fe;border-color:#93c5fd;color:#1d4ed8;">Preview mode enabled: this is a designer preview of the exam experience with up to 45 questions.</div>
        <?php endif; ?>
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
            <!-- Section selector: 10 colored cards -->
            <div class="section-grid" id="sectionGrid">
                <?php for ($si = 1; $si <= 10; $si++): ?>
                    <?php $enabled = $si <= max(1, $totalPages); ?>
                    <div class="section-card <?php echo $enabled ? '' : 'disabled'; ?>" data-index="<?php echo $si; ?>">
                        ክፍል <?php echo $si; ?>
                        <div style="font-size:12px;font-weight:600;margin-top:6px;"><?php echo $enabled ? (isset($pages[$si-1]) ? count($pages[$si-1]['questions']).' Q' : 'N/A') : '—'; ?></div>
                    </div>
                <?php endfor; ?>
            </div>
            <form method="post" id="examForm">
                <div class="progress" aria-hidden="true"><span id="progressFill"></span></div>
                <div class="small chip" id="sectionInfo">Section 1 of <?php echo max(1, $totalPages); ?></div>
                <?php foreach ($pages as $pageIndex => $page): ?>
                    <div class="section-block <?php echo $pageIndex === 0 ? 'active' : ''; ?>" data-section="<?php echo $pageIndex + 1; ?>">
                        <div class="section-heading">
                            <span><?php echo safe($page['section_title'] ?? 'General Section'); ?></span>
                            <span><?php echo count($page['questions']); ?> Questions</span>
                        </div>
                        <?php if (!empty($page['section_instruction'])): ?>
                            <p class="small" style="margin-top:-10px;margin-bottom:12px;"><?php echo safe($page['section_instruction']); ?></p>
                        <?php endif; ?>
                        <?php foreach ($page['questions'] as $item): ?>
                            <div class="question">
                                <p><?php echo safe($item['question_text'] ?? ''); ?></p>
                                <?php $options = buildExamOptions($item); ?>
                                <?php if (!empty($options)): ?>
                                    <?php foreach ($options as $option): ?>
                                        <label class="answer-option">
                                            <input type="radio" name="q<?php echo (int)($item['id'] ?? 0); ?>" value="<?php echo safe((string)($option['label'] ?? '')); ?>" required />
                                            <?php echo safe($option['label'] . '. ' . $option['text']); ?>
                                        </label>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <input type="text" class="answer-text" name="q<?php echo (int)($item['id'] ?? 0); ?>" value="<?php echo safe($_POST['q' . ($item['id'] ?? 0)] ?? ''); ?>" placeholder="መልስዎን ይተይቡ" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;" />
                                <?php endif; ?>
                                <?php if ($submitted): ?>
                                    <?php $selected = trim((string)($_POST['q' . ($item['id'] ?? 0)] ?? '')); ?>
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
                    </div>
                <?php endforeach; ?>
                <div class="nav-row">
                    <button type="button" class="nav-btn ghost" id="prevBtn" style="display:none;">Previous</button>
                    <button type="button" class="nav-btn" id="nextBtn">Next</button>
                    <button type="submit" class="nav-btn submit-btn" id="submitBtn" style="display:none;">ላክ</button>
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
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const submitBtn = document.getElementById('submitBtn');
        const progressFill = document.getElementById('progressFill');
        const sectionInfo = document.getElementById('sectionInfo');
        const sections = Array.from(document.querySelectorAll('.section-block'));
        const examForm = document.getElementById('examForm');

        function updateNav() {
            sections.forEach((section, index) => {
                section.classList.toggle('active', index === currentPage);
            });
            const progress = ((currentPage + 1) / <?php echo max(1, $totalPages); ?>) * 100;
            if (progressFill) progressFill.style.width = progress + '%';
            if (sectionInfo) sectionInfo.textContent = 'Section ' + (currentPage + 1) + ' of ' + <?php echo max(1, $totalPages); ?>;
            if (prevBtn) prevBtn.style.display = currentPage === 0 ? 'none' : 'inline-block';
            if (nextBtn) nextBtn.style.display = currentPage === <?php echo max(0, $totalPages - 1); ?> ? 'none' : 'inline-block';
            if (submitBtn) submitBtn.style.display = currentPage === <?php echo max(0, $totalPages - 1); ?> ? 'inline-block' : 'none';
        }

        let currentPage = 0;
        function syncSectionCards() {
            const sectionGrid = document.getElementById('sectionGrid');
            const sectionBlocks = document.querySelectorAll('.section-block');
            if (!sectionGrid || sectionBlocks.length === 0) {
                return;
            }
            const cards = Array.from(sectionGrid.querySelectorAll('.section-card'));
            cards.forEach((card) => {
                const idx = parseInt(card.getAttribute('data-index'), 10) - 1;
                if (card.classList.contains('disabled')) {
                    return;
                }
                card.addEventListener('click', () => {
                    if (idx >= 0 && idx < sectionBlocks.length) {
                        currentPage = idx;
                        updateNav();
                        setTimeout(() => { document.getElementById('examForm').scrollIntoView({behavior:'smooth', block:'start'}); }, 100);
                    }
                });
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (currentPage > 0) {
                    currentPage--;
                    updateNav();
                }
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                if (currentPage < <?php echo max(0, $totalPages - 1); ?>) {
                    currentPage++;
                    updateNav();
                }
            });
        }

        syncSectionCards();
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

        if (examForm) {
            examForm.addEventListener('submit', () => {
                if (submitBtn) {
                    submitBtn.classList.add('submitting');
                    submitBtn.textContent = 'Submitting...';
                }
            });
        }

        function updateTimer() {
            const now = Date.now();
            const diff = deadline - now;

            if (diff <= 0) {
                timerText.textContent = '00:00:00';
                if (submitBtn) submitBtn.disabled = true;
                if (examForm) {
                    examForm.querySelectorAll('input, button').forEach((el) => el.disabled = true);
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
