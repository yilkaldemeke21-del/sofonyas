<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

if (isset($_POST['save'])) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS questions (id INT AUTO_INCREMENT PRIMARY KEY, question_text TEXT NOT NULL, option_a VARCHAR(255) NOT NULL, option_b VARCHAR(255) NOT NULL, option_c VARCHAR(255) NOT NULL, option_d VARCHAR(255) NOT NULL, correct_answer VARCHAR(255) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');

    $q = trim($_POST['question'] ?? '');
    $a = trim($_POST['a'] ?? '');
    $b = trim($_POST['b'] ?? '');
    $c = trim($_POST['c'] ?? '');
    $d = trim($_POST['d'] ?? '');
    $correct = trim($_POST['correct'] ?? 'A');

    try {
        $stmt = $pdo->prepare('INSERT INTO questions (question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES (:q, :a, :b, :c, :d, :correct)');
        $stmt->execute([
            ':q' => $q,
            ':a' => $a,
            ':b' => $b,
            ':c' => $c,
            ':d' => $d,
            ':correct' => $correct,
        ]);

        echo 'Question Added';
    } catch (PDOException $e) {
        echo 'Error: ' . $e->getMessage();
    }
}
?>

<form method="POST">

Question:<br>
<textarea name="question"></textarea><br><br>

A:<input type="text" name="a"><br>
B:<input type="text" name="b"><br>
C:<input type="text" name="c"><br>
D:<input type="text" name="d"><br>

Correct:
<select name="correct">
<option>A</option>
<option>B</option>
<option>C</option>
<option>D</option>
</select>

<br><br>

<button name="save">Save</button>

</form>