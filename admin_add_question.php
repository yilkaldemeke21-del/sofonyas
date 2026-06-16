<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$adminRole = $_SESSION['admin_role'] ?? 'admin';
if (!in_array($adminRole, ['admin', 'super_admin'], true)) {
    http_response_code(403);
    exit('ይህን ገጽ ለመጠቀም የአስተዳዳሪ ፈቃድ ያስፈልጋል።');
}

$error = '';
$success = '';

function normalizeQuestionType($value) {
    $type = strtolower(trim((string)($value ?? 'multiple_choice')));
    $type = str_replace([' ', '_'], '', $type);

    if (in_array($type, ['multiplechoice', 'multiple_choice'], true)) {
        return 'multiple_choice';
    }
    if (in_array($type, ['truefalse', 'true_false', 'boolean'], true)) {
        return 'true_false';
    }
    if (in_array($type, ['fillinblank', 'fill_in_blank', 'blankspace', 'blank_space'], true)) {
        return 'fill_in_blank';
    }
    if (in_array($type, ['shortanswer', 'short_answer'], true)) {
        return 'short_answer';
    }
    return 'multiple_choice';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->exec('CREATE TABLE IF NOT EXISTS questions (id INT AUTO_INCREMENT PRIMARY KEY, question_type VARCHAR(30) NOT NULL DEFAULT "multiple_choice", question_text TEXT NOT NULL, option_a VARCHAR(255) NOT NULL, option_b VARCHAR(255) NOT NULL, option_c VARCHAR(255) NOT NULL, option_d VARCHAR(255) NOT NULL, correct_answer VARCHAR(255) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
    try {
        $pdo->exec('ALTER TABLE questions ADD COLUMN question_type VARCHAR(30) NOT NULL DEFAULT "multiple_choice"');
    } catch (PDOException $e) {
        // Ignore if the column already exists.
    }

    $question_type = normalizeQuestionType($_POST['question_type'] ?? 'multiple_choice');
    $q = trim($_POST['question'] ?? '');

    if ($question_type === 'multiple_choice' || $question_type === 'true_false') {
        $a = trim($_POST['a'] ?? '');
        $b = trim($_POST['b'] ?? '');
        $c = trim($_POST['c'] ?? '');
        $d = trim($_POST['d'] ?? '');
        $correct = strtoupper(trim($_POST['correct'] ?? 'A'));

        if ($question_type === 'true_false') {
            $a = 'True';
            $b = 'False';
            $c = '';
            $d = '';
            $correct = strtoupper(trim($_POST['correct'] ?? 'TRUE'));
        }

        if ($q === '' || $a === '' || $b === '') {
            $error = 'እባክዎ ጥያቄን እና ምርጫዎቹን ይሙሉ።';
        } else {
            if ($question_type === 'multiple_choice' && ($c === '' || $d === '')) {
                $error = 'እባክዎ ሁሉንም አራት አማራጮች ይሙሉ።';
            } else {
                try {
                    $stmt = $pdo->prepare('INSERT INTO questions (question_type, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES (:question_type, :q, :a, :b, :c, :d, :correct)');
                    $stmt->execute([
                        ':question_type' => $question_type,
                        ':q' => $q,
                        ':a' => $a,
                        ':b' => $b,
                        ':c' => $c,
                        ':d' => $d,
                        ':correct' => $correct,
                    ]);
                    $success = $question_type === 'true_false'
                        ? 'True/False ጥያቄው በስኬት ታክሏል። አሁን እንደገና ማየት ይቻላል።'
                        : 'ምርጫ ጥያቄው በስኬት ታክሏል። ይህ ጥያቄ አሁን በስርዓቱ ውስጥ ይገኛል።';
                } catch (PDOException $e) {
                    $error = 'ስህተት: ' . $e->getMessage();
                }
            }
        }
    } else {
        $answer = trim($_POST['answer'] ?? '');
        if ($q === '' || $answer === '') {
            $error = 'እባክዎ ጥያቄና ትክክለኛ መልስ ይሙሉ።';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO questions (question_type, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES (:question_type, :q, :answer, :blank1, :blank2, :blank3, :correct_answer)');
                $stmt->execute([
                    ':question_type' => $question_type,
                    ':q' => $q,
                    ':answer' => $answer,
                    ':blank1' => '',
                    ':blank2' => '',
                    ':blank3' => '',
                    ':correct_answer' => $answer,
                ]);
                $success = $question_type === 'fill_in_blank'
                    ? 'Fill in the Blank ጥያቄው በስኬት ታክሏል። አሁን በቅድሚያ የተከማቹት ጥያቄዎች ውስጥ ይታያል።'
                    : 'አጭር መልስ ጥያቄው በስኬት ታክሏል። አሁን በጥያቄ ዝርዝር ላይ ይታያል።';
            } catch (PDOException $e) {
                $error = 'ስህተት: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>ጥያቄ ጨምር</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; color: #333; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; }
        .navbar a { color: white; text-decoration: none; margin-right: 15px; }
        .container { max-width: 700px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h1 { margin-bottom: 20px; color: #667eea; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input, textarea, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        textarea { resize: vertical; min-height: 90px; }
        button { width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; font-size: 16px; }
        button:hover { background: #764ba2; }
        .error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 5px; margin-bottom: 15px; }
        .success { background: #d4f1d8; color: #1d6a2b; padding: 12px; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="navbar">
    <h2>ጥያቄ ጨምር</h2>
    <a href="admin_questions.php">← ወደ ጥያቄዎች</a>
</div>
<div class="container">
    <div class="card">
        <h1>አዲስ ጥያቄ ጨምር</h1>
        <?php if ($error): ?><div class="error"><?php echo safe($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?php echo safe($success); ?></div><?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="question_type">የጥያቄ አይነት</label>
                <select id="question_type" name="question_type" onchange="toggleQuestionType(this.value)">
                    <option value="multiple_choice">Multiple Choice</option>
                    <option value="true_false">True / False</option>
                    <option value="fill_in_blank">Fill in the Blank / Blank Space</option>
                    <option value="short_answer">Short Answer</option>
                </select>
                <p style="color:#475569; font-size:13px; margin-top:6px;">ይህ ክፍል ለ ተማሪዎች ግልጽ የሆኑ ጥያቄዎችን ይደግፋል። አዲስ ጥያቄ ሲጨምሩ በዚህ መስመር ውስጥ ትክክለኛውን መልስ እንደ ሀ/ለ/ሐ/መ በትክክለኛ ቅደም ተከተል ያስገቡ።</p>
            </div>
            <div class="form-group">
                <label for="question">ጥያቄ</label>
                <textarea id="question" name="question" placeholder="ለምሳሌ፦ ይህ ትምህርት የትኛው ነው?" required></textarea>
            </div>
            <div id="mcqFields">
                <div class="form-group"><label for="a" id="labelA">ሀ</label><input id="a" type="text" name="a" placeholder="አማራጭ 1" required></div>
                <div class="form-group"><label for="b" id="labelB">ለ</label><input id="b" type="text" name="b" placeholder="አማራጭ 2" required></div>
                <div class="form-group"><label for="c">ሐ</label><input id="c" type="text" name="c" placeholder="አማራጭ 3" required></div>
                <div class="form-group"><label for="d">መ</label><input id="d" type="text" name="d" placeholder="አማራጭ 4" required></div>
                <div class="form-group">
                    <label for="correct">ትክክለኛ መልስ</label>
                    <select id="correct" name="correct">
                        <option value="ሀ">ሀ</option>
                        <option value="ለ">ለ</option>
                        <option value="ሐ">ሐ</option>
                        <option value="መ">መ</option>
                    </select>
                </div>
            </div>
            <div id="shortAnswerFields" style="display:none;">
                <div class="form-group">
                    <label for="answer">ትክክለኛ መልስ / Correct Answer</label>
                    <input id="answer" type="text" name="answer" placeholder="ለምሳሌ፦ አስተማሪ የሚሠራበት ቦታ">
                    <p style="color:#475569; font-size:13px; margin-top:6px;">ይህ ቦታ በ Short Answer እና Fill in the Blank ጥያቄዎች ውስጥ ትክክለኛውን መልስ ይወስዳል። እባክዎ በድምር ለጥያቄው አንድ ትክክለኛ መልስ ብቻ ያስገቡ።</p>
                </div>
            </div>
            <button type="submit" name="save" onclick="this.disabled=true; this.form.submit();" style="background:#2563eb; border-radius:8px; font-size:15px;">ጥያቄ አስገባ / Save Question</button>
        </form>
        <script>
            function toggleQuestionType(type) {
                const mcq = document.getElementById('mcqFields');
                const shortAnswer = document.getElementById('shortAnswerFields');
                const labelA = document.getElementById('labelA');
                const labelB = document.getElementById('labelB');
                const correct = document.getElementById('correct');
                const a = document.getElementById('a');
                const b = document.getElementById('b');
                const c = document.getElementById('c');
                const d = document.getElementById('d');
                const answer = document.getElementById('answer');

                const isTextType = type === 'short_answer' || type === 'fill_in_blank';

                if (isTextType) {
                    mcq.style.display = 'none';
                    shortAnswer.style.display = 'block';
                    a.removeAttribute('required');
                    b.removeAttribute('required');
                    c.removeAttribute('required');
                    d.removeAttribute('required');
                    answer.setAttribute('required', 'required');
                    return;
                }

                mcq.style.display = 'block';
                shortAnswer.style.display = 'none';
                a.setAttribute('required', 'required');
                b.setAttribute('required', 'required');
                c.setAttribute('required', 'required');
                d.setAttribute('required', 'required');
                answer.removeAttribute('required');

                if (type === 'true_false') {
                    labelA.textContent = 'እውነት';
                    labelB.textContent = 'ሐሰት';
                    a.value = 'እውነት';
                    b.value = 'ሀሰት';
                   
                    correct.innerHTML = '<option value="TRUE">እውነት</option><option value="FALSE">ሀሰት</option>';
                } else {
                    labelA.textContent = 'ሀ';
                    labelB.textContent = 'ለ';
                    a.value = '';
                    b.value = '';
                    c.value = '';
                    d.value = '';
                    correct.innerHTML = '<option value="ሀ">ሀ</option><option value="ለ">ለ</option><option value="ሐ">ሐ</option><option value="መ">መ</option>';
                }
            }

            toggleQuestionType(document.getElementById('question_type').value);
        </script>
    </div>
</div>
</body>
</html>