<?php
session_start();
require_once __DIR__ . '/db.php';

$leaderboard = [];
$errorMessage = '';

try {
    $stmt = $pdo->prepare(
        'SELECT
            s.student_id,
            s.name,
            COALESCE(sg.points, 0) AS points,
            COALESCE(sg.level, "Novice") AS level,
            COALESCE(ROUND(100 * (
                COALESCE((SELECT AVG(score / NULLIF(total_questions, 0)) FROM quiz_results qr WHERE qr.student_id = s.student_id), 0) * 0.6 +
                COALESCE((SELECT AVG(score / NULLIF(total_questions, 0)) FROM exam_submissions es WHERE es.student_id = s.student_id), 0) * 0.4
            )), 0) AS overall_percentage,
            COALESCE((SELECT COUNT(*) FROM quiz_results qr WHERE qr.student_id = s.student_id), 0) AS quiz_attempts,
            COALESCE(ROUND(100 * COALESCE((SELECT AVG(score / NULLIF(total_questions, 0)) FROM quiz_results qr WHERE qr.student_id = s.student_id), 0)), 0) AS quiz_percentage,
            COALESCE((SELECT COUNT(*) FROM exam_submissions es WHERE es.student_id = s.student_id), 0) AS exam_attempts,
            COALESCE(ROUND(100 * COALESCE((SELECT AVG(score / NULLIF(total_questions, 0)) FROM exam_submissions es WHERE es.student_id = s.student_id), 0)), 0) AS exam_percentage
        FROM students s
        LEFT JOIN student_gamification sg ON sg.student_id = s.student_id
        ORDER BY points DESC, overall_percentage DESC, (quiz_attempts + exam_attempts) DESC, s.name ASC
        LIMIT 25'
    );
    $stmt->execute();
    $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $errorMessage = 'Unable to load leaderboard data. Please ensure quiz or exam records exist.';
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #eef2ff; color: #0f172a; }
        .page { max-width: 1100px; margin: 32px auto; padding: 0 18px; }
        .header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 24px; }
        .header h1 { margin: 0; font-size: clamp(2rem, 3vw, 2.6rem); }
        .subtitle { margin: 0; color: #475569; max-width: 760px; line-height: 1.6; }
        .card { background: rgba(255,255,255,0.9); border: 1px solid rgba(148,163,184,0.18); border-radius: 24px; box-shadow: 0 24px 60px rgba(15,23,42,0.08); padding: 24px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #eff6ff; color: #1e3a8a; font-weight: 700; position: sticky; top: 0; }
        tbody tr:hover { background: rgba(59,130,246,0.08); }
        .rank { font-weight: 700; color: #2563eb; }
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; font-size: 0.9rem; background: #e0e7ff; color: #3730a3; }
        .empty { text-align: center; color: #475569; margin: 32px 0; }
        .alert { padding: 18px 20px; border-radius: 18px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; margin-bottom: 18px; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; margin-top: 16px; color: #2563eb; text-decoration: none; font-weight: 700; }
        @media (max-width: 760px) { th, td { padding: 12px 10px; } }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div>
                <h1>Leaderboard</h1>
                <p class="subtitle">የምርጫ ተማሪዎች እና የእርምጃ ማስተካከያ መድረክ። ይህ ሚዛን ከQuiz እና Exam ውጤቶች የተገነባ ነው።</p>
            </div>
            <a href="student_dashboard.php" class="back-link">← ዳሽቦርድ ወደ ጀርባ</a>
        </div>

        <div class="card">
            <?php if ($errorMessage !== ''): ?>
                <div class="alert"><?php echo safe($errorMessage); ?></div>
            <?php endif; ?>

            <?php if (empty($leaderboard)): ?>
                <p class="empty">ዳሳሽ ውስጥ ምንም ውጤት አልተመዘገበም። እባክዎ ማስረጃ በQuiz ወይም Exam ውስጥ እንዲጨምሩ ይሞክሩ።</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Overall %</th>
                                <th>Quiz %</th>
                                <th>Exam %</th>
                                <th>Attempts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaderboard as $index => $row): ?>
                                <tr>
                                    <td><span class="rank"><?php echo $index + 1; ?></span></td>
                                    <td><?php echo safe($row['name'] ?: $row['student_id']); ?></td>
                                    <td><?php echo safe($row['student_id']); ?></td>
                                    <td><span class="badge"><?php echo safe($row['overall_percentage']); ?>%</span></td>
                                    <td><?php echo safe($row['quiz_percentage']); ?>%</td>
                                    <td><?php echo safe($row['exam_percentage']); ?>%</td>
                                    <td><?php echo safe($row['quiz_attempts'] + $row['exam_attempts']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
