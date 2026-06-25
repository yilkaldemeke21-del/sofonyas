<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit;
}

$studentId = $_SESSION['student_id'];
$studentName = 'Student';
$stmt = $pdo->prepare('SELECT name, student_id FROM students WHERE student_id = :student_id LIMIT 1');
$stmt->execute([':student_id' => $studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if ($student) {
    $studentName = $student['name'] ?: $student['student_id'];
}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS quiz_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        quiz_name VARCHAR(255) NOT NULL,
        score INT NOT NULL DEFAULT 0,
        total_questions INT NOT NULL DEFAULT 0,
        status VARCHAR(30) NOT NULL DEFAULT "pending",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {}

$stmt = $pdo->prepare('SELECT * FROM quiz_results WHERE student_id = :student_id ORDER BY created_at DESC');
$stmt->execute([':student_id' => $studentId]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$viewId = (int)($_GET['view'] ?? 0);
$detailsId = (int)($_GET['details'] ?? 0);
$printId = (int)($_GET['print'] ?? 0);
$selected = null;
if ($viewId > 0 || $detailsId > 0 || $printId > 0) {
    $id = $viewId > 0 ? $viewId : ($detailsId > 0 ? $detailsId : $printId);
    $stmt = $pdo->prepare('SELECT * FROM quiz_results WHERE id = :id AND student_id = :student_id LIMIT 1');
    $stmt->execute([':id' => $id, ':student_id' => $studentId]);
    $selected = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($printId > 0 && $selected) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Student Result\n";
    echo "Name: {$studentName}\n";
    echo "Quiz: {$selected['quiz_name']}\n";
    echo "Score: {$selected['score']} / {$selected['total_questions']}\n";
    echo "Status: {$selected['status']}\n";
    echo "Date: {$selected['created_at']}\n";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Results</title>
<style>
body{font-family:Arial,sans-serif;background:#f8fafc;margin:0;padding:0;color:#0f172a;}
.wrap{max-width:1020px;margin:24px auto;padding:24px;background:#fff;border-radius:16px;box-shadow:0 10px 26px rgba(15,23,42,.08);}h1{margin-top:0;color:#2563eb;}table{width:100%;border-collapse:collapse;margin-top:16px;}th,td{padding:10px;border-bottom:1px solid #e2e8f0;text-align:left;}a{color:#2563eb;text-decoration:none;}.button{display:inline-block;padding:8px 12px;border-radius:8px;background:#2563eb;color:#fff;margin-top:8px;}.muted{color:#64748b;}.pill{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;font-weight:700;}.pill.success{background:#ecfdf3;color:#047857;}.pill.warning{background:#fff7ed;color:#c2410c;}.card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px;margin-top:12px;}
</style>
</head>
<body>
<div class="wrap">
    <h1>📊 My Results</h1>
    <p class="muted">Quiz Name · Date · Taken Score · Percentage · Status · View · Details · Print Result</p>
    <a class="button" href="student_dashboard.php">← Back to Dashboard</a>
    <?php if ($selected): ?>
        <div class="card">
            <h2><?php echo safe($selected['quiz_name']); ?></h2>
            <p><strong>Student:</strong> <?php echo safe($studentName); ?></p>
            <p><strong>Date:</strong> <?php echo safe($selected['created_at']); ?></p>
            <p><strong>Score:</strong> <?php echo (int)$selected['score']; ?> / <?php echo (int)$selected['total_questions']; ?></p>
            <p><strong>Status:</strong> <span class="pill <?php echo ((string)$selected['status'] === 'Passed' || (int)$selected['score'] >= 50) ? 'success' : 'warning'; ?>"><?php echo safe($selected['status']); ?></span></p>
            <p><strong>Percentage:</strong> <?php echo (int)round(((int)$selected['score'] / max(1, (int)$selected['total_questions'])) * 100); ?>%</p>
        </div>
    <?php endif; ?>
    <table>
        <thead>
            <tr>
                <th>Quiz Name</th>
                <th>Date</th>
                <th>Taken Score</th>
                <th>Percentage</th>
                <th>Status</th>
                <th>View</th>
                <th>Details</th>
                <th>Print Result</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($results)): ?>
                <tr><td colspan="8" class="muted">No results yet.</td></tr>
            <?php else: foreach ($results as $row): ?>
                <?php $score = (int)($row['score'] ?? 0); $total = max(1, (int)($row['total_questions'] ?? 100)); $percentage = (int)round(($score / $total) * 100); $status = (string)($row['status'] ?? 'Passed'); ?>
                <tr>
                    <td><?php echo safe($row['quiz_name']); ?></td>
                    <td><?php echo safe($row['created_at']); ?></td>
                    <td><?php echo $score; ?> / <?php echo $total; ?></td>
                    <td><?php echo $percentage; ?>%</td>
                    <td><span class="pill <?php echo ($status === 'Passed' || $score >= 50) ? 'success' : 'warning'; ?>"><?php echo safe($status); ?></span></td>
                    <td><a href="results.php?view=<?php echo (int)$row['id']; ?>">View</a></td>
                    <td><a href="results.php?details=<?php echo (int)$row['id']; ?>">Details</a></td>
                    <td><a href="results.php?print=<?php echo (int)$row['id']; ?>">Print</a></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
