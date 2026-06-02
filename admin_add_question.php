<?php
include("../config/db.php");

if($_SESSION['role']!='admin'){
die("Access Denied");
}

if(isset($_POST['save'])){

$q=$_POST['question'];

$a=$_POST['a'];
$b=$_POST['b'];
$c=$_POST['c'];
$d=$_POST['d'];

$correct=$_POST['correct'];

$conn->query("
INSERT INTO questions
(question_text,option_a,option_b,option_c,option_d,correct_answer)

VALUES
('$q','$a','$b','$c','$d','$correct')
");

echo "Question Added";
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