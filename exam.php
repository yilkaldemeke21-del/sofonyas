<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit;
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

$stmt = $pdo->query('SELECT q.*, s.title AS section_title FROM questions q LEFT JOIN question_sections s ON s.id = q.section_id ORDER BY q.created_at DESC LIMIT 80');
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$questionChunks = array_chunk($questions, 10);
$totalSections = count($questionChunks);
$score = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($questions as $index => $item) {
        $answer = trim((string)($_POST['q' . $index] ?? ''));
        if (isCorrectExamAnswer($item, $answer)) {
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
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Online Multiple Choice Exam</h1>
        <p class="small">ቀላል እና ቀጥታ የሚሰራ ፒ.ኤች.ፒ መልመጃ ገጽ።</p>
        <p class="small" style="margin-top:-4px; color:#1d4ed8;">ይህ ፈተና ከአድሚኑ የተጨመሩት ጥያቄዎች እና ክፍሎች ላይ ይመሠረታል።</p>

        <?php if (empty($questions)): ?>
            <div class="result" style="background:#fef3c7;border:1px solid #f59e0b;color:#92400e;">እስካሁን ምንም ጥያቄ አልተጨመረም። አድሚኑ ጥያቄዎችን እንዲጨምር ይጠይቁ።</div>
        <?php else: ?>
        <form method="post" id="examForm">
            <div class="progress" aria-hidden="true"><span id="progressFill"></span></div>
            <div class="small chip" id="sectionInfo">Section 1 of <?php echo max(1, $totalSections); ?></div>

            <?php $globalIndex = 0; ?>
            <?php foreach ($questionChunks as $chunkIndex => $chunk): ?>
                <div class="section-block <?php echo $chunkIndex === 0 ? 'active' : ''; ?>" data-section="<?php echo $chunkIndex + 1; ?>">
                    <div class="section-heading">
                        <span>Section <?php echo $chunkIndex + 1; ?></span>
                        <span><?php echo count($chunk); ?> Questions</span>
                    </div>
                    <?php foreach ($chunk as $item): ?>
                        <div class="question">
                            <p><?php echo $globalIndex + 1; ?>. <?php echo htmlspecialchars($item['question']); ?></p>
                            <?php foreach ($item['options'] as $option): ?>
                                <label class="answer-option">
                                    <input type="radio" name="q<?php echo $globalIndex; ?>" value="<?php echo htmlspecialchars($option); ?>" />
                                    <?php echo htmlspecialchars($option); ?>
                                </label>
                            <?php endforeach; ?>
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

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="result">
                <strong>ውጤት:</strong> እርስዎ <?php echo $score; ?> / <?php echo count($questions); ?> መልሳት በትክክል መለሱ።
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
    </script>
</body>
</html>
