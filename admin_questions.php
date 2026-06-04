<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$message = '';
$error = '';

if (isset($_GET['delete'])) {
    $question_id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare('DELETE FROM questions WHERE id = :id');
        $stmt->execute([':id' => $question_id]);
        $message = 'ጥያቄው ተሰርዟል።';
    } catch (PDOException $e) {
        $error = 'ስህተት: ' . $e->getMessage();
    }
}

try {
    $stmt = $pdo->query('SELECT * FROM questions ORDER BY created_at DESC');
    $questions = $stmt->fetchAll();
} catch (PDOException $e) {
    $questions = [];
    $error = 'DB ችግኝ አለ። ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>የጥያቄ አስተዳደር</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; color: #333; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: white; text-decoration: none; margin-left: 20px; padding: 8px 15px; background: rgba(255,255,255,0.1); border-radius: 5px; }
        .navbar a:hover { background: rgba(255,255,255,0.2); }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .message { padding: 12px; border-radius: 5px; margin-bottom: 20px; background: #d4f1d8; color: #1d6a2b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; vertical-align: top; }
        th { background: #f1f5ff; font-weight: bold; }
        .action-btn { display: inline-block; padding: 6px 12px; margin: 2px; border-radius: 5px; text-decoration: none; font-size: 12px; }
        .edit-btn { background: #667eea; color: white; }
        .delete-btn { background: #e74c3c; color: white; }
        .edit-btn:hover { background: #764ba2; }
        .delete-btn:hover { background: #c0392b; }
        .add-btn { background: #667eea; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; }
        .add-btn:hover { background: #764ba2; }
    </style>
</head>
<body>
<div class="navbar">
    <h2>የጥያቄ አስተዳደር</h2>
    <div>
        <a href="admin_add_question.php" class="add-btn">➕ ጥያቄ ጨምር</a>
        <a href="admin_dashboard.php">ዳሽቦርድ</a>
    </div>
</div>
<div class="container">
    <div class="card">
        <?php if ($message): ?>
            <div class="message"><?php echo safe($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message" style="background:#ffe7e7;color:#a41616;"><?php echo safe($error); ?></div>
        <?php endif; ?>

        <?php if (empty($questions)): ?>
            <p style="text-align:center; padding:30px;">ምንም ጥያቄ የለም። እባክዎ አዲስ ጥያቄ ያክሉ።</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ጥያቄ</th>
                        <th>መልሶች</th>
                        <th>ትክክለኛ መልስ</th>
                        <th>ድርጊቶች</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $q): ?>
                        <tr>
                            <td><?php echo safe($q['question_text']); ?></td>
                            <td>
                                <strong><?php echo ($q['question_type'] ?? 'multiple_choice') === 'short_answer' ? 'Short Answer' : 'Multiple Choice'; ?></strong><br>
                                <?php if (($q['question_type'] ?? 'multiple_choice') === 'short_answer'): ?>
                                    መልስ: <?php echo safe($q['correct_answer'] ?: $q['option_a']); ?>
                                <?php else: ?>
                                    A. <?php echo safe($q['option_a']); ?><br>
                                    B. <?php echo safe($q['option_b']); ?><br>
                                    C. <?php echo safe($q['option_c']); ?><br>
                                    D. <?php echo safe($q['option_d']); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo safe($q['correct_answer'] ?: $q['option_a']); ?></td>
                            <td>
                                <a class="action-btn edit-btn" href="admin_edit_question.php?id=<?php echo (int)$q['id']; ?>">ማስተካከል</a>
                                <a class="action-btn delete-btn" href="admin_questions.php?delete=<?php echo (int)$q['id']; ?>" onclick="return confirm('ይህን ጥያቄ ሰርዝ?');">ሰርዝ</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
