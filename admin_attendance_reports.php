<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/management_schema.php';
requireRole(['admin'], $pdo);
ensureManagementTables($pdo);

$fromDate = trim((string)($_GET['from_date'] ?? date('Y-m-d', strtotime('-7 days'))));
$toDate = trim((string)($_GET['to_date'] ?? date('Y-m-d')));
$serviceFilter = trim((string)($_GET['service_name'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$reportFormat = trim((string)($_GET['format'] ?? 'html'));

$fromDateObj = DateTime::createFromFormat('!Y-m-d', $fromDate);
$toDateObj = DateTime::createFromFormat('!Y-m-d', $toDate);
if (!$fromDateObj || $fromDateObj->format('Y-m-d') !== $fromDate) {
    $fromDate = date('Y-m-d', strtotime('-7 days'));
}
if (!$toDateObj || $toDateObj->format('Y-m-d') !== $toDate) {
    $toDate = date('Y-m-d');
}

$sql = 'SELECT a.id, a.service_name, a.service_date, a.checkin_time, a.attendance_status, a.recorded_by, a.notes, m.full_name, m.member_number FROM church_attendance a JOIN church_members m ON m.id = a.member_id WHERE a.service_date BETWEEN :from_date AND :to_date';
$params = [':from_date' => $fromDate, ':to_date' => $toDate];
if ($serviceFilter !== '') {
    $sql .= ' AND a.service_name = :service_name';
    $params[':service_name'] = $serviceFilter;
}
if ($statusFilter !== '') {
    $sql .= ' AND a.attendance_status = :status';
    $params[':status'] = $statusFilter;
}
$sql .= ' ORDER BY a.service_date DESC, a.service_name ASC, m.full_name ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sundaySql = 'SELECT ssa.attendance_date, ssa.attendance_status, ssa.recorded_by, m.full_name, e.student_id FROM sunday_school_attendance ssa JOIN sunday_school_enrollments e ON e.id = ssa.enrollment_id JOIN church_members m ON m.id = e.student_id WHERE ssa.attendance_date BETWEEN :from_date AND :to_date';
if ($statusFilter !== '') {
    $sundaySql .= ' AND ssa.attendance_status = :status';
}
$sundaySql .= ' ORDER BY ssa.attendance_date DESC, m.full_name ASC';
$sundayStmt = $pdo->prepare($sundaySql);
$sundayParams = [':from_date' => $fromDate, ':to_date' => $toDate];
if ($statusFilter !== '') {
    $sundayParams[':status'] = $statusFilter;
}
$sundayStmt->execute($sundayParams);
$sundayRows = $sundayStmt->fetchAll(PDO::FETCH_ASSOC);

if ($reportFormat === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="church_attendance_report_' . $fromDate . '_to_' . $toDate . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Member', 'Member number', 'Service', 'Date', 'Time', 'Status', 'Recorded by', 'Notes']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['full_name'],
            $row['member_number'],
            $row['service_name'],
            $row['service_date'],
            $row['checkin_time'] ?? '',
            $row['attendance_status'],
            $row['recorded_by'] ?? '',
            $row['notes'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f4f7f5;color:#1f2d2a;font-family:Arial,sans-serif}.wrap{max-width:1200px;margin:24px auto;padding:0 18px}.panel{background:#fff;padding:18px;border-top:2px solid #d7e2dc}.toolbar{display:flex;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:16px}.field{display:flex;flex-direction:column;gap:6px}.field label{font-size:12px;font-weight:600;color:#4b5c59}.field input,.field select{padding:8px 10px;border:1px solid #cbd5d1;border-radius:5px}.button{background:#236c5e;color:#fff;padding:9px 14px;border:none;border-radius:5px;cursor:pointer;text-decoration:none;display:inline-block}.button.secondary{background:#6a7a79}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.table-wrap{overflow:auto}.table{width:100%;border-collapse:collapse;font-size:13px}.table th,.table td{padding:8px 10px;border-bottom:1px solid #e4ebe8;text-align:left}.table th{background:#f3f7f4}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}.print-link{padding:9px 14px;background:#fff;border:1px solid #d0dbd7;border-radius:5px;text-decoration:none;color:#1f2d2a}@media(max-width:700px){.grid{grid-template-columns:1fr}} 
    </style>
</head>
<body>
<div class="wrap">
    <div class="panel">
        <h2 style="margin:0 0 12px">Attendance reports</h2>
        <form class="toolbar" method="get">
            <div class="field">
                <label for="from_date">From</label>
                <input id="from_date" type="date" name="from_date" value="<?php echo safe($fromDate); ?>">
            </div>
            <div class="field">
                <label for="to_date">To</label>
                <input id="to_date" type="date" name="to_date" value="<?php echo safe($toDate); ?>">
            </div>
            <div class="field">
                <label for="service_name">Service</label>
                <input id="service_name" type="text" name="service_name" value="<?php echo safe($serviceFilter); ?>" placeholder="Sunday Service">
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All</option>
                    <option value="present" <?php echo $statusFilter === 'present' ? 'selected' : ''; ?>>Present</option>
                    <option value="late" <?php echo $statusFilter === 'late' ? 'selected' : ''; ?>>Late</option>
                    <option value="absent" <?php echo $statusFilter === 'absent' ? 'selected' : ''; ?>>Absent</option>
                </select>
            </div>
            <button class="button" type="submit">Run report</button>
        </form>

        <div class="actions">
            <a class="button secondary" href="admin_attendance_reports.php?from_date=<?php echo urlencode($fromDate); ?>&to_date=<?php echo urlencode($toDate); ?>&service_name=<?php echo urlencode($serviceFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&format=csv">Export CSV</a>
            <a class="print-link" href="javascript:window.print()">Print / PDF</a>
            <a class="print-link" href="admin_management_dashboard.php">Back to dashboard</a>
        </div>
    </div>

    <div class="grid" style="margin-top:16px;">
        <section class="panel">
            <h3 style="margin-top:0">Church attendance</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Member</th><th>Service</th><th>Date</th><th>Status</th><th>Recorded by</th></tr></thead>
                    <tbody>
                        <?php if (!$rows): ?><tr><td colspan="5">No church attendance records found for the selected range.</td></tr><?php else: foreach ($rows as $row): ?>
                            <tr>
                                <td><?php echo safe($row['full_name']); ?> (<?php echo safe($row['member_number']); ?>)</td>
                                <td><?php echo safe($row['service_name']); ?></td>
                                <td><?php echo safe($row['service_date']); ?></td>
                                <td><?php echo ucfirst(safe($row['attendance_status'])); ?></td>
                                <td><?php echo safe($row['recorded_by'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <h3 style="margin-top:0">Sunday School attendance</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Student</th><th>Date</th><th>Status</th><th>Recorder</th></tr></thead>
                    <tbody>
                        <?php if (!$sundayRows): ?><tr><td colspan="4">No Sunday School attendance records found for the selected range.</td></tr><?php else: foreach ($sundayRows as $row): ?>
                            <tr>
                                <td><?php echo safe($row['full_name']); ?> (<?php echo safe($row['student_id']); ?>)</td>
                                <td><?php echo safe($row['attendance_date']); ?></td>
                                <td><?php echo ucfirst(safe($row['attendance_status'])); ?></td>
                                <td><?php echo safe($row['recorded_by'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
</body>
</html>
