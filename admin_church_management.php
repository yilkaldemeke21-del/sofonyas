<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/management_schema.php';
require_once __DIR__ . '/admin_management_i18n.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit(adminManagementText('db_unavailable'));
}

ensureManagementTables($pdo);
$message = '';
$error = '';
$csrf = csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $error = adminManagementText('csrf_error');
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'add_member') {
                $fullName = trim((string)($_POST['full_name'] ?? ''));
                $phone = trim((string)($_POST['phone'] ?? ''));
                $email = trim((string)($_POST['email'] ?? ''));
                $gender = trim((string)($_POST['gender'] ?? ''));
                $address = trim((string)($_POST['address'] ?? ''));
                $joinedOn = (string)($_POST['joined_on'] ?? '');
                $joinedDate = DateTime::createFromFormat('!Y-m-d', $joinedOn);

                if ($fullName === '' || !$joinedDate || $joinedDate->format('Y-m-d') !== $joinedOn) {
                    $error = adminManagementText('member_validation_error');
                } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = adminManagementText('email_validation_error');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO church_members (full_name, phone, email, gender, address, joined_on) VALUES (:full_name, :phone, :email, :gender, :address, :joined_on)');
                    $stmt->execute([
                        ':full_name' => $fullName,
                        ':phone' => $phone !== '' ? $phone : null,
                        ':email' => $email !== '' ? $email : null,
                        ':gender' => $gender !== '' ? $gender : null,
                        ':address' => $address !== '' ? $address : null,
                        ':joined_on' => $joinedOn,
                    ]);
                    $message = adminManagementText('member_registered');
                }
            } elseif ($action === 'record_attendance') {
                $memberId = (int)($_POST['member_id'] ?? 0);
                $serviceName = trim((string)($_POST['service_name'] ?? ''));
                $serviceDate = (string)($_POST['service_date'] ?? '');
                $attendanceStatus = (string)($_POST['attendance_status'] ?? 'present');
                $notes = trim((string)($_POST['notes'] ?? ''));
                $validDate = DateTime::createFromFormat('!Y-m-d', $serviceDate);

                if ($memberId < 1 || $serviceName === '' || !$validDate || $validDate->format('Y-m-d') !== $serviceDate) {
                    $error = adminManagementText('attendance_validation_error');
                } elseif (!in_array($attendanceStatus, ['present', 'absent', 'late'], true)) {
                    $error = adminManagementText('invalid_attendance_status');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO church_attendance (member_id, service_name, service_date, attendance_status, notes, recorded_by) VALUES (:member_id, :service_name, :service_date, :attendance_status, :notes, :recorded_by)');
                    $stmt->execute([
                        ':member_id' => $memberId,
                        ':service_name' => $serviceName,
                        ':service_date' => $serviceDate,
                        ':attendance_status' => $attendanceStatus,
                        ':notes' => $notes !== '' ? $notes : null,
                        ':recorded_by' => (string)($_SESSION['admin_username'] ?? 'Admin'),
                    ]);
                    $message = adminManagementText('attendance_saved');
                }
            } elseif ($action === 'set_member_status') {
                $memberId = (int)($_POST['member_id'] ?? 0);
                $status = (string)($_POST['status'] ?? '');
                if ($memberId > 0 && in_array($status, ['active', 'inactive'], true)) {
                    $stmt = $pdo->prepare('UPDATE church_members SET status = :status WHERE id = :id');
                    $stmt->execute([':status' => $status, ':id' => $memberId]);
                    $message = adminManagementText('member_status_updated');
                }
            }
        } catch (PDOException $exception) {
            $error = $exception->getCode() === '23000'
                ? adminManagementText('duplicate_attendance')
                : adminManagementText('save_error');
            error_log('Church management save failed: ' . $exception->getMessage());
        }
    }
}

$attendanceDate = (string)($_GET['date'] ?? date('Y-m-d'));
$selectedDate = DateTime::createFromFormat('!Y-m-d', $attendanceDate);
if (!$selectedDate || $selectedDate->format('Y-m-d') !== $attendanceDate) {
    $attendanceDate = date('Y-m-d');
}

$members = $pdo->query('SELECT id, full_name, phone, email, joined_on, status FROM church_members ORDER BY full_name ASC')->fetchAll(PDO::FETCH_ASSOC);
$recentMembers = $pdo->query('SELECT id, full_name, phone, email, joined_on, status FROM church_members ORDER BY created_at DESC LIMIT 30')->fetchAll(PDO::FETCH_ASSOC);
$attendanceStmt = $pdo->prepare('SELECT a.*, m.full_name FROM church_attendance a JOIN church_members m ON m.id = a.member_id WHERE a.service_date = :service_date ORDER BY a.service_name, m.full_name');
$attendanceStmt->execute([':service_date' => $attendanceDate]);
$attendanceRows = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
$activeMembers = (int)$pdo->query("SELECT COUNT(*) FROM church_members WHERE status = 'active'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe(adminManagementText('church_title')); ?></title>
    <style>
        :root{font-family:Arial,sans-serif;color:#202b2b;background:#f4f7f5;font-synthesis:none}*{box-sizing:border-box}body{margin:0}.topbar{background:#143b36;color:#fff;padding:16px max(20px,calc((100% - 1180px)/2));display:flex;align-items:center;justify-content:space-between;gap:16px}.topbar h1{font-size:20px;margin:0}.topbar a,.button{color:#fff;text-decoration:none;background:#236c5e;border:0;padding:10px 14px;border-radius:5px;font:inherit;cursor:pointer}.wrap{max-width:1180px;margin:24px auto;padding:0 18px}.intro{display:flex;align-items:end;justify-content:space-between;gap:16px;margin-bottom:18px}.intro h2{margin:0 0 5px;font-size:23px}.muted{color:#64736e}.stat{padding:12px 16px;border-left:3px solid #b6853b;background:#fff;min-width:145px}.stat strong{display:block;font-size:24px}.columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin:18px 0}.section{background:#fff;padding:18px;border-top:2px solid #d7e2dc;min-width:0}.section h3{margin:0 0 15px;font-size:17px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}.field{display:flex;flex-direction:column;gap:5px}.field.full{grid-column:1/-1}label{font-size:13px;font-weight:600}input,select,textarea{width:100%;padding:10px;border:1px solid #cbd5d1;border-radius:4px;font:inherit;background:#fff;color:#202b2b}.submit{margin-top:12px;background:#236c5e;color:#fff;border:0;border-radius:4px;padding:10px 14px;font:inherit;cursor:pointer}.notice{padding:11px 13px;background:#e8f4ef;color:#176244;margin-bottom:14px}.error{background:#fff0eb;color:#9b351f}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:14px}th,td{text-align:left;padding:10px;border-bottom:1px solid #e5ebe7;white-space:nowrap}th{background:#f3f7f4;color:#46534e}.small-form{display:inline}.small-form button{border:0;color:#236c5e;background:none;text-decoration:underline;cursor:pointer;font:inherit}.toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:4px 0 12px}.toolbar form{display:flex;align-items:center;gap:8px}.print{background:#b6853b}.status{font-weight:600}.status-present{color:#19734e}.status-late{color:#936315}.status-absent{color:#a33b35}@media(max-width:760px){.columns{grid-template-columns:1fr}.intro{align-items:start;flex-direction:column}.topbar{padding:14px 18px}.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}}@media print{body{background:#fff}.topbar,.intro,.columns,.toolbar form,.no-print{display:none!important}.wrap{margin:0;max-width:none;padding:0}.section{border:0;padding:0}#attendance-report{display:block!important}}
    </style>
</head>
<body>
<header class="topbar">
    <h1><?php echo safe(adminManagementText('church_title')); ?></h1>
    <nav style="display:flex;gap:8px;flex-wrap:wrap"><a href="<?php echo safe($adminManagementSwitchUrl); ?>"><?php echo safe(adminManagementText('language_switch')); ?></a><a href="admin_church_members.php"><?php echo safe(adminManagementText('church_members_page')); ?></a><a href="admin_management_dashboard.php">← <?php echo safe(adminManagementText('back_dashboard')); ?></a></nav>
</header>
<main class="wrap">
    <?php if ($message !== ''): ?><div class="notice"><?php echo safe($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error"><?php echo safe($error); ?></div><?php endif; ?>
    <div class="intro">
        <div><h2><?php echo safe(adminManagementText('church_heading')); ?></h2><div class="muted"><?php echo safe(adminManagementText('church_intro')); ?></div></div>
        <div class="stat"><strong><?php echo $activeMembers; ?></strong><span class="muted"><?php echo safe(adminManagementText('active_members')); ?></span></div>
    </div>
    <div class="columns">
        <section class="section" id="register">
            <h3><?php echo safe(adminManagementText('register_member')); ?></h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>">
                <input type="hidden" name="action" value="add_member">
                <div class="form-grid">
                    <div class="field full"><label for="full_name"><?php echo safe(adminManagementText('full_name')); ?> *</label><input id="full_name" name="full_name" maxlength="180" required></div>
                    <div class="field"><label for="phone"><?php echo safe(adminManagementText('phone')); ?></label><input id="phone" name="phone" maxlength="40" type="tel"></div>
                    <div class="field"><label for="email"><?php echo safe(adminManagementText('email')); ?></label><input id="email" name="email" maxlength="190" type="email"></div>
                    <div class="field"><label for="gender"><?php echo safe(adminManagementText('gender')); ?></label><select id="gender" name="gender"><option value=""><?php echo safe(adminManagementText('not_specified')); ?></option><option value="Female"><?php echo safe(adminManagementText('female')); ?></option><option value="Male"><?php echo safe(adminManagementText('male')); ?></option><option value="Other"><?php echo safe(adminManagementText('other')); ?></option></select></div>
                    <div class="field"><label for="joined_on"><?php echo safe(adminManagementText('joined_on')); ?> *</label><input id="joined_on" name="joined_on" type="date" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div class="field full"><label for="address"><?php echo safe(adminManagementText('address')); ?></label><input id="address" name="address" maxlength="255"></div>
                </div>
                <button class="submit" type="submit"><?php echo safe(adminManagementText('register')); ?></button>
            </form>
        </section>
        <section class="section" id="attendance-entry">
            <h3><?php echo safe(adminManagementText('record_attendance')); ?></h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>">
                <input type="hidden" name="action" value="record_attendance">
                <div class="form-grid">
                    <div class="field full"><label for="member_id"><?php echo safe(adminManagementText('member')); ?> *</label><select id="member_id" name="member_id" required><option value=""><?php echo safe(adminManagementText('select_member')); ?></option><?php foreach ($members as $member): if ($member['status'] !== 'active') continue; ?><option value="<?php echo (int)$member['id']; ?>"><?php echo safe($member['full_name']); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="service_name"><?php echo safe(adminManagementText('service_name')); ?> *</label><input id="service_name" name="service_name" maxlength="120" required></div>
                    <div class="field"><label for="service_date"><?php echo safe(adminManagementText('date')); ?> *</label><input id="service_date" name="service_date" type="date" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div class="field"><label for="attendance_status"><?php echo safe(adminManagementText('status')); ?></label><select id="attendance_status" name="attendance_status"><option value="present"><?php echo safe(adminManagementText('present')); ?></option><option value="late"><?php echo safe(adminManagementText('late')); ?></option><option value="absent"><?php echo safe(adminManagementText('absent')); ?></option></select></div>
                    <div class="field"><label for="notes"><?php echo safe(adminManagementText('notes')); ?></label><input id="notes" name="notes" maxlength="500"></div>
                </div>
                <button class="submit" type="submit"><?php echo safe(adminManagementText('save_attendance')); ?></button>
            </form>
        </section>
    </div>
    <section class="section" id="attendance-report">
        <div class="toolbar">
            <div><h3 style="margin-bottom:4px"><?php echo safe(adminManagementText('attendance_report')); ?></h3><span class="muted"><?php echo count($attendanceRows); ?> <?php echo safe(adminManagementText('records_for')); ?> <?php echo safe($attendanceDate); ?></span></div>
            <form method="get" class="no-print"><label for="date"><?php echo safe(adminManagementText('service_date')); ?></label><input id="date" name="date" type="date" value="<?php echo safe($attendanceDate); ?>"><button class="submit" type="submit" style="margin:0"><?php echo safe(adminManagementText('view')); ?></button><button class="submit print" type="button" onclick="window.print()"><?php echo safe(adminManagementText('print')); ?></button></form>
        </div>
        <div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('member')); ?></th><th><?php echo safe(adminManagementText('service_name')); ?></th><th><?php echo safe(adminManagementText('status')); ?></th><th><?php echo safe(adminManagementText('notes')); ?></th><th><?php echo safe(adminManagementText('recorded_by')); ?></th></tr></thead><tbody>
        <?php if (!$attendanceRows): ?><tr><td colspan="5"><?php echo safe(adminManagementText('no_attendance')); ?></td></tr><?php else: foreach ($attendanceRows as $row): ?><tr><td><?php echo safe($row['full_name']); ?></td><td><?php echo safe($row['service_name']); ?></td><td class="status status-<?php echo safe($row['attendance_status']); ?>"><?php echo safe(adminManagementText($row['attendance_status'])); ?></td><td><?php echo safe($row['notes'] ?? ''); ?></td><td><?php echo safe($row['recorded_by'] ?? ''); ?></td></tr><?php endforeach; endif; ?>
        </tbody></table></div>
    </section>
    <section class="section" style="margin-top:18px">
        <div class="toolbar"><h3 style="margin:0"><?php echo safe(adminManagementText('members_list')); ?></h3><span class="muted"><?php echo safe(adminManagementText('latest_30')); ?></span></div>
        <div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('full_name')); ?></th><th><?php echo safe(adminManagementText('phone')); ?></th><th><?php echo safe(adminManagementText('email')); ?></th><th><?php echo safe(adminManagementText('joined')); ?></th><th><?php echo safe(adminManagementText('status')); ?></th><th><?php echo safe(adminManagementText('action')); ?></th></tr></thead><tbody>
        <?php if (!$recentMembers): ?><tr><td colspan="6"><?php echo safe(adminManagementText('no_members')); ?></td></tr><?php else: foreach ($recentMembers as $member): ?><tr><td><?php echo safe($member['full_name']); ?></td><td><?php echo safe($member['phone'] ?? ''); ?></td><td><?php echo safe($member['email'] ?? ''); ?></td><td><?php echo safe($member['joined_on']); ?></td><td><?php echo safe(adminManagementText($member['status'] === 'active' ? 'member_active' : 'member_inactive')); ?></td><td><form class="small-form" method="post"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="set_member_status"><input type="hidden" name="member_id" value="<?php echo (int)$member['id']; ?>"><input type="hidden" name="status" value="<?php echo $member['status'] === 'active' ? 'inactive' : 'active'; ?>"><button type="submit"><?php echo safe($member['status'] === 'active' ? adminManagementText('set_inactive') : adminManagementText('set_active')); ?></button></form></td></tr><?php endforeach; endif; ?>
        </tbody></table></div>
    </section>
</main>
</body>
</html>