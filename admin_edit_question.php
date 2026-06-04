<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$question_id = (int)($_GET['id'] ?? 0);
$question = null;
$error = '';
$success = '';
$question_type = 'multiple_choice';

if ($question_id) {
    $stmt = $pdo->prepare('SELECT * FROM questions WHERE id = :id');
    $stmt->execute([':id' => $question_id]);
    $question = $stmt->fetch();
    $question_type = $question['question_type'] ?? 'multiple_choice';
}

if (!$question) {
    header('Location: admin_questions.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question_type = ($_POST['question_type'] ?? 'multiple_choice') === 'short_answer' ? 'short_answer' : 'multiple_choice';
    $question_text = trim($_POST['question_text'] ?? '');

    if ($question_type === 'multiple_choice') {
        $a = trim($_POST['a'] ?? '');
        $b = trim($_POST['b'] ?? '');
        $c = trim($_POST['c'] ?? '');
        $d = trim($_POST['d'] ?? '');
        $correct = strtoupper(trim($_POST['correct'] ?? 'A'));

        if ($question_text === '' || $a === '' || $b === '' || $c === '' || $d === '') {
            $error = 'እባክዎ ሁሉንም መስኮች ይሙሉ።';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE questions SET question_type = :question_type, question_text = :question_text, option_a = :a, option_b = :b, option_c = :c, option_d = :d, correct_answer = :correct WHERE id = :id');
                $stmt->execute([
                    ':question_type' => $question_type,
                    ':question_text' => $question_text,
                    ':a' => $a,
                    ':b' => $b,
                    ':c' => $c,
                    ':d' => $d,
                    ':correct' => $correct,
                    ':id' => $question_id,
                ]);
                $success = 'ጥያቄው በስኬት ተሻሽሏል።';
                $stmt = $pdo->prepare('SELECT * FROM questions WHERE id = :id');
                $stmt->execute([':id' => $question_id]);
                $question = $stmt->fetch();
                $question_type = $question['question_type'] ?? 'multiple_choice';
            } catch (PDOException $e) {
                $error = 'ስህተት: ' . $e->getMessage();
            }
        }
    } else {
        $answer = trim($_POST['answer'] ?? '');
        if ($question_text === '' || $answer === '') {
            $error = 'እባክዎ ጥያቄና ትክክለኛ መልስ ይሙሉ።';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE questions SET question_type = :question_type, question_text = :question_text, option_a = :answer, option_b = "", option_c = "", option_d = "", correct_answer = :answer WHERE id = :id');
                $stmt->execute([
                    ':question_type' => $question_type,
                    ':question_text' => $question_text,
                    ':answer' => $answer,
                    ':id' => $question_id,
                ]);
                $success = 'ጥያቄው በስኬት ተሻሽሏል።';
                $stmt = $pdo->prepare('SELECT * FROM questions WHERE id = :id');
                $stmt->execute([':id' => $question_id]);
                $question = $stmt->fetch();
                $question_type = $question['question_type'] ?? 'short_answer';
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
    <title>ጥያቄ ማስተካከል</title>
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
    <h2>ጥያቄ ማስተካከል</h2>
    <a href="admin_questions.php">← ወደ ጥያቄዎች</a>
</div>
<div class="container">
    <div class="card">
        <h1>ጥያቄ #<?php echo (int)$question['id']; ?> ማስተካከል</h1>
        <?php if ($error): ?><div class="error"><?php echo safe($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?php echo safe($success); ?></div><?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="question_type">የጥያቄ አይነት</label>
                <select id="question_type" name="question_type" onchange="toggleQuestionType(this.value)">
                    <option value="multiple_choice" <?php echo $question_type === 'multiple_choice' ? 'selected' : ''; ?>>Multiple Choice</option>
                    <option value="short_answer" <?php echo $question_type === 'short_answer' ? 'selected' : ''; ?>>Short Answer</option>
                </select>
            </div>
            <div class="form-group">
                <label for="question_text">ጥያቄ</label>
                <textarea id="question_text" name="question_text" required><?php echo safe($question['question_text']); ?></textarea>
            </div>
            <div id="mcqFields" style="display:<?php echo $question_type === 'short_answer' ? 'none' : 'block'; ?>;">
                <div class="form-group"><label for="a">A</label><input type="text" id="a" name="a" value="<?php echo safe($question['option_a'] ?? ''); ?>" required></div>
                <div class="form-group"><label for="b">B</label><input type="text" id="b" name="b" value="<?php echo safe($question['option_b'] ?? ''); ?>" required></div>
                <div class="form-group"><label for="c">C</label><input type="text" id="c" name="c" value="<?php echo safe($question['option_c'] ?? ''); ?>" required></div>
                <div class="form-group"><label for="d">D</label><input type="text" id="d" name="d" value="<?php echo safe($question['option_d'] ?? ''); ?>" required></div>
                <div class="form-group">
                    <label for="correct">ትክክለኛ መልስ</label>
                    <select id="correct" name="correct">
                        <option value="A" <?php echo ($question['correct_answer'] ?? 'A') === 'A' ? 'selected' : ''; ?>>A</option>
                        <option value="B" <?php echo ($question['correct_answer'] ?? 'A') === 'B' ? 'selected' : ''; ?>>B</option>
                        <option value="C" <?php echo ($question['correct_answer'] ?? 'A') === 'C' ? 'selected' : ''; ?>>C</option>
                        <option value="D" <?php echo ($question['correct_answer'] ?? 'A') === 'D' ? 'selected' : ''; ?>>D</option>
                    </select>
                </div>
            </div>
            <div id="shortAnswerFields" style="display:<?php echo $question_type === 'short_answer' ? 'block' : 'none'; ?>;">
                <div class="form-group">
                    <label for="answer">ትክክለኛ መልስ</label>
                    <input type="text" id="answer" name="answer" value="<?php echo safe($question['correct_answer'] ?? $question['option_a'] ?? ''); ?>" required>
                </div>
            </div>
            <button type="submit">ማስተካከል</button>
        </form>
        <script>
            function toggleQuestionType(type) {
                const mcq = document.getElementById('mcqFields');
                const shortAnswer = document.getElementById('shortAnswerFields');
                if (type === 'short_answer') { mcq.style.display='none'; shortAnswer.style.display='block'; }
                else { mcq.style.display='block'; shortAnswer.style.display='none'; }
            }
            toggleQuestionType(document.getElementById('question_type').value);
        </script>
    </div>
</div>
</body>
</html>
