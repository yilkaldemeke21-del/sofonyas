<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->exec('CREATE TABLE IF NOT EXISTS questions (id INT AUTO_INCREMENT PRIMARY KEY, question_type VARCHAR(30) NOT NULL DEFAULT "multiple_choice", question_text TEXT NOT NULL, option_a VARCHAR(255) NOT NULL, option_b VARCHAR(255) NOT NULL, option_c VARCHAR(255) NOT NULL, option_d VARCHAR(255) NOT NULL, correct_answer VARCHAR(255) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
    try {
        $pdo->exec('ALTER TABLE questions ADD COLUMN question_type VARCHAR(30) NOT NULL DEFAULT "multiple_choice"');
    } catch (PDOException $e) {
        // Ignore if the column already exists.
    }

    $question_type = ($_POST['question_type'] ?? 'multiple_choice') === 'short_answer' ? 'short_answer' : 'multiple_choice';
    $q = trim($_POST['question'] ?? '');

    if ($question_type === 'multiple_choice') {
        $a = trim($_POST['a'] ?? '');
        $b = trim($_POST['b'] ?? '');
        $c = trim($_POST['c'] ?? '');
        $d = trim($_POST['d'] ?? '');
        $correct = strtoupper(trim($_POST['correct'] ?? 'A'));

        if ($q === '' || $a === '' || $b === '' || $c === '' || $d === '') {
            $error = 'እባክዎ ሁሉንም መስኮች ይሙሉ።';
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
                $success = 'ምርጫ ጥያቄው በስኬት ታክሏል።';
            } catch (PDOException $e) {
                $error = 'ስህተት: ' . $e->getMessage();
            }
        }
    } else {
        $answer = trim($_POST['answer'] ?? '');
        if ($q === '' || $answer === '') {
            $error = 'እባክዎ ጥያቄና ትክክለኛ መልስ ይሙሉ።';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO questions (question_type, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES (:question_type, :q, :answer, :blank1, :blank2, :blank3, :answer)');
                $stmt->execute([
                    ':question_type' => $question_type,
                    ':q' => $q,
                    ':answer' => $answer,
                    ':blank1' => '',
                    ':blank2' => '',
                    ':blank3' => '',
                ]);
                $success = 'አጭር መልስ ጥያቄው በስኬት ታክሏል።';
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
                    <option value="short_answer">Short Answer</option>
                </select>
            </div>
            <div class="form-group">
                <label for="question">ጥያቄ</label>
                <textarea id="question" name="question" placeholder="ጥያቄዎን ይተይቡ" required></textarea>
            </div>
            <div id="mcqFields">
                <div class="form-group"><label for="a">A</label><input id="a" type="text" name="a" placeholder="አማራጭ 1" required></div>
                <div class="form-group"><label for="b">B</label><input id="b" type="text" name="b" placeholder="አማራጭ 2" required></div>
                <div class="form-group"><label for="c">C</label><input id="c" type="text" name="c" placeholder="አማራጭ 3" required></div>
                <div class="form-group"><label for="d">D</label><input id="d" type="text" name="d" placeholder="አማራጭ 4" required></div>
                <div class="form-group">
                    <label for="correct">ትክክለኛ መልስ</label>
                    <select id="correct" name="correct">
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                    </select>
                </div>
            </div>
            <div id="shortAnswerFields" style="display:none;">
                <div class="form-group">
                    <label for="answer">ትክክለኛ መልስ</label>
                    <input id="answer" type="text" name="answer" placeholder="አጭር መልስ ይተይቡ">
                </div>
            </div>
            <button type="submit" name="save">ጥያቄ ጨምር</button>
        </form>
        <script>
            function toggleQuestionType(type) {
                const mcq = document.getElementById('mcqFields');
                const shortAnswer = document.getElementById('shortAnswerFields');
                if (type === 'short_answer') {
                    mcq.style.display = 'none';
                    shortAnswer.style.display = 'block';
                } else {
                    mcq.style.display = 'block';
                    shortAnswer.style.display = 'none';
                }
            }
            toggleQuestionType(document.getElementById('question_type').value);
        </script>
    </div>
</div>
</body>
</html>