<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/management_schema.php';
requireRole(['admin'], $pdo);
ensureManagementTables($pdo);

$today = date('Y-m-d');
$totalRegisteredMembers = (int)$pdo->query('SELECT COUNT(*) FROM church_members')->fetchColumn();
$activeMembers = (int)$pdo->query("SELECT COUNT(*) FROM church_members WHERE status = 'active'")->fetchColumn();
$presentMembers = (int)$pdo->query("SELECT COUNT(DISTINCT member_id) FROM church_attendance WHERE service_date = CURDATE() AND attendance_status IN ('present','late')")->fetchColumn();
$absentMembers = max(0, $activeMembers - $presentMembers);
$attendancePercentage = $activeMembers > 0 ? round(($presentMembers / $activeMembers) * 100, 1) : 0.0;
$todayAttendanceRecords = (int)$pdo->query('SELECT COUNT(*) FROM church_attendance WHERE service_date = CURDATE()')->fetchColumn();

$weeklyStmt = $pdo->query("SELECT DATE_FORMAT(service_date, '%Y-%u') AS week_key, COUNT(*) AS total_records, SUM(attendance_status IN ('present','late')) AS present_records FROM church_attendance WHERE service_date >= DATE_SUB(CURDATE(), INTERVAL 6 WEEK) GROUP BY DATE_FORMAT(service_date, '%Y-%u') ORDER BY week_key ASC");
$weeklyRows = $weeklyStmt->fetchAll(PDO::FETCH_ASSOC);

$monthlyStmt = $pdo->query("SELECT DATE_FORMAT(service_date, '%Y-%m') AS month_key, COUNT(*) AS total_records, SUM(attendance_status IN ('present','late')) AS present_records FROM church_attendance WHERE service_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY DATE_FORMAT(service_date, '%Y-%m') ORDER BY month_key ASC");
$monthlyRows = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

$ministryStmt = $pdo->query("SELECT service_name, COUNT(*) AS total_records, SUM(attendance_status IN ('present','late')) AS present_records FROM church_attendance WHERE service_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY service_name ORDER BY total_records DESC, service_name ASC LIMIT 8");
$ministryRows = $ministryStmt->fetchAll(PDO::FETCH_ASSOC);

$sundayStmt = $pdo->query("SELECT attendance_status, COUNT(*) AS total_records FROM sunday_school_attendance WHERE attendance_date = CURDATE() GROUP BY attendance_status ORDER BY attendance_status");
$sundayRows = $sundayStmt->fetchAll(PDO::FETCH_ASSOC);

$recentAttendance = $pdo->query('SELECT a.service_name, a.service_date, a.attendance_status, a.checkin_time, a.recorded_by, m.full_name FROM church_attendance a JOIN church_members m ON m.id = a.member_id ORDER BY a.service_date DESC, a.id DESC LIMIT 8')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Church Attendance Dashboard</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f4f7f5;color:#1f2d2a;font-family:Arial,sans-serif}.topbar{background:#143b36;color:#fff;padding:18px max(20px,calc((100% - 1200px)/2));display:flex;justify-content:space-between;align-items:center;gap:16px}.topbar a{color:#fff;text-decoration:none;border:1px solid #7aa19a;padding:8px 12px;border-radius:5px}.wrap{max-width:1200px;margin:24px auto;padding:0 18px}.cards{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.card{background:#fff;padding:16px;border-top:3px solid #236c5e;min-height:120px}.card:nth-child(2){border-top-color:#607b8a}.card:nth-child(3){border-top-color:#b6853b}.card:nth-child(4){border-top-color:#b85e4a}.card:nth-child(5){border-top-color:#327b66}.card span{display:block;color:#687874;font-size:12px}.card strong{display:block;font-size:28px;margin-top:8px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-top:16px}.panel{background:#fff;padding:16px;border-top:2px solid #d7e2dc}.panel h3{margin:0 0 12px;font-size:17px}.bar-wrap{display:grid;grid-template-columns:repeat(auto-fit,minmax(36px,1fr));gap:8px;align-items:end;height:180px}.bar-col{display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:6px}.bar{width:100%;background:#236c5e;border-radius:4px 4px 0 0;min-height:6px}.bar.secondary{background:#607b8a}.bar-label{font-size:10px;color:#687874}.table-wrap{overflow:auto}.table{width:100%;border-collapse:collapse;font-size:13px}.table th,.table td{padding:8px 10px;border-bottom:1px solid #e3ebe7;text-align:left}.table th{background:#f3f7f4}.chip{display:inline-block;padding:4px 8px;border-radius:999px;background:#e5f4ec;color:#176244;font-size:11px;font-weight:700}.chip.alt{background:#fce8e7;color:#9b351f}@media(max-width:900px){.cards,.grid{grid-template-columns:1fr 1fr}.topbar{flex-direction:column;align-items:flex-start}}@media(max-width:640px){.cards,.grid{grid-template-columns:1fr}.topbar{padding:14px 16px}} 
    </style>
</head>
<body>
<header class="topbar">
    <div><h1 style="margin:0">Attendance Dashboard</h1><div style="font-size:13px;color:#dfeae7;margin-top:4px">Live church attendance overview</div></div>
    <a href="admin_management_dashboard.php">← Back to dashboard</a>
</header>
<main class="wrap">
    <div class="cards">
        <div class="card"><span>Total registered members</span><strong><?php echo number_format($totalRegisteredMembers); ?></strong></div>
        <div class="card"><span>Present members</span><strong><?php echo number_format($presentMembers); ?></strong></div>
        <div class="card"><span>Absent members</span><strong><?php echo number_format($absentMembers); ?></strong></div>
        <div class="card"><span>Attendance %</span><strong><?php echo number_format($attendancePercentage, 1); ?>%</strong></div>
        <div class="card"><span>Today records</span><strong><?php echo number_format($todayAttendanceRecords); ?></strong></div>
    </div>

    <div class="grid">
        <section class="panel">
            <h3>Weekly attendance trend</h3>
            <div class="bar-wrap">
                <?php foreach ($weeklyRows as $row):
                    $height = $row['total_records'] > 0 ? max(12, (int)round(($row['present_records'] / max(1, (int)$row['total_records'])) * 100)) : 6;
                ?>
                    <div class="bar-col">
                        <div class="bar" style="height:<?php echo $height; ?>px"></div>
                        <div class="bar-label"><?php echo safe(substr($row['week_key'], 5)); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="panel">
            <h3>Monthly attendance trend</h3>
            <div class="bar-wrap">
                <?php foreach ($monthlyRows as $row):
                    $height = $row['total_records'] > 0 ? max(12, (int)round(($row['present_records'] / max(1, (int)$row['total_records'])) * 100)) : 6;
                ?>
                    <div class="bar-col">
                        <div class="bar secondary" style="height:<?php echo $height; ?>px"></div>
                        <div class="bar-label"><?php echo safe(substr($row['month_key'], 5)); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <div class="grid">
        <section class="panel">
            <h3>Attendance by ministry / service</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Service</th><th>Total</th><th>Present</th></tr></thead>
                    <tbody>
                        <?php foreach ($ministryRows as $row): ?>
                            <tr>
                                <td><?php echo safe($row['service_name']); ?></td>
                                <td><?php echo (int)$row['total_records']; ?></td>
                                <td><?php echo (int)$row['present_records']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <h3>Sunday School attendance</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Status</th><th>Count</th></tr></thead>
                    <tbody>
                        <?php foreach (['present','late','absent','excused'] as $status):
                            $count = 0;
                            foreach ($sundayRows as $row) {
                                if ($row['attendance_status'] === $status) {
                                    $count = (int)$row['total_records'];
                                    break;
                                }
                            }
                        ?>
                            <tr><td><?php echo ucfirst($status); ?></td><td><?php echo number_format($count); ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="panel" style="margin-top:16px;">
        <h3>Recent attendance records</h3>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Member</th><th>Service</th><th>Date</th><th>Time</th><th>Status</th><th>Recorder</th></tr></thead>
                <tbody>
                    <?php foreach ($recentAttendance as $row): ?>
                        <tr>
                            <td><?php echo safe($row['full_name']); ?></td>
                            <td><?php echo safe($row['service_name']); ?></td>
                            <td><?php echo safe($row['service_date']); ?></td>
                            <td><?php echo safe($row['checkin_time'] ?? '—'); ?></td>
                            <td><span class="chip <?php echo $row['attendance_status'] === 'absent' ? 'alt' : ''; ?>"><?php echo ucfirst(safe($row['attendance_status'])); ?></span></td>
                            <td><?php echo safe($row['recorded_by'] ?? '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
