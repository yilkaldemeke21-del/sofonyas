<?php
session_start();
require_once __DIR__ . '/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

// Get statistics
$stmt = $pdo->query('SELECT COUNT(*) as total FROM registrations');
$total_registrations = $stmt->fetch()['total'];

$stmt = $pdo->query('SELECT COUNT(*) as paid FROM registrations WHERE payment_status = "paid"');
$paid_count = $stmt->fetch()['paid'];

$stmt = $pdo->query('SELECT COUNT(*) as unpaid FROM registrations WHERE payment_status = "unpaid"');
$unpaid_count = $stmt->fetch()['unpaid'];

$stmt = $pdo->query('SELECT COUNT(*) as courses FROM courses');
$total_courses = $stmt->fetch()['courses'];

$stmt = $pdo->query('SELECT COUNT(*) as students FROM students');
$total_students = $stmt->fetch()['students'];

try {
    $stmt = $pdo->query('SELECT COUNT(*) as certificates FROM certificates');
    $total_certificates = (int)($stmt->fetch()['certificates'] ?? 0);
} catch (PDOException $e) {
    $total_certificates = 0;
}

$stmt = $pdo->query('SELECT SUM(amount) as total_revenue FROM registrations WHERE payment_status = "paid"');
$result = $stmt->fetch();
$total_revenue = $result['total_revenue'] ?? 0;

$most_popular = $pdo->query('SELECT course, COUNT(*) as total FROM registrations WHERE course IS NOT NULL AND TRIM(course) <> "" GROUP BY course ORDER BY total DESC, course ASC LIMIT 1')->fetch();
$most_popular_course = $most_popular['course'] ?? 'ምንም ኮርስ የለም';
$most_popular_count = (int)($most_popular['total'] ?? 0);

$active_students = (int)$pdo->query('SELECT COUNT(*) as total FROM students WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)')->fetch()['total'];

$weekly_registrations = $pdo->query('SELECT DATE(created_at) AS day, COUNT(*) AS total FROM registrations WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY day ASC')->fetchAll();

$course_breakdown = $pdo->query('SELECT course, COUNT(*) as total FROM registrations WHERE course IS NOT NULL AND TRIM(course) <> "" GROUP BY course ORDER BY total DESC LIMIT 5')->fetchAll();

$recent_students = $pdo->query('SELECT * FROM students ORDER BY created_at DESC LIMIT 5')->fetchAll();
$recent_courses = $pdo->query('SELECT * FROM courses ORDER BY created_at DESC LIMIT 5')->fetchAll();

// Create notifications tables if they don't exist
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS email_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        recipient_email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS course_updates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        update_message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS exam_reminders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        exam_type VARCHAR(100) NOT NULL,
        exam_date DATETIME NOT NULL,
        reminder_message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS event_announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_title VARCHAR(255) NOT NULL,
        event_description TEXT NOT NULL,
        event_date DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {}

// Get recent notifications
$recent_email_notifications = $pdo->query('SELECT * FROM email_notifications ORDER BY sent_at DESC LIMIT 5')->fetchAll();
$recent_course_updates = $pdo->query('SELECT cu.*, c.course_name FROM course_updates cu JOIN courses c ON cu.course_id = c.id ORDER BY cu.created_at DESC LIMIT 5')->fetchAll();
$recent_exam_reminders = $pdo->query('SELECT * FROM exam_reminders ORDER BY created_at DESC LIMIT 5')->fetchAll();
$recent_events = $pdo->query('SELECT * FROM event_announcements ORDER BY event_date DESC LIMIT 5')->fetchAll();

$success_message = '';
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $section = $_GET['section'] ?? '';
    $section_labels = [
        'news' => 'ዜና',
        'blog' => 'ብሎግ',
        'announcement' => 'ማስታወቂያ',
    ];
    $label = $section_labels[$section] ?? 'ይዘት';
    $success_message = $label . ' በተሳካ ሁኔታ ታክሏል።';
}

$backupDir = __DIR__ . '/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$backupMessage = '';
$backupError = '';

function parseBackupFileName(string $fileName): array
{
    $parts = ['type' => 'manual', 'timestamp' => null];
    if (preg_match('/^(.+)_backup_(daily|weekly|manual)_(\d{8}_\d{6})\.sql$/', $fileName, $matches)) {
        $parts['type'] = $matches[2];
        $parts['timestamp'] = $matches[3];
    }
    return $parts;
}

function createBackup(PDO $pdo, string $databaseName, string $backupDir, string $backupType = 'manual'): array
{
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if (!$tables) {
        return ['success' => false, 'message' => 'No tables found to back up.'];
    }

    $backupType = in_array($backupType, ['daily', 'weekly', 'manual'], true) ? $backupType : 'manual';
    $timestamp = gmdate('Ymd_His');
    $fileName = $databaseName . '_backup_' . $backupType . '_' . $timestamp . '.sql';
    $filePath = $backupDir . '/' . $fileName;

    $dump = "-- Database backup generated on " . gmdate('Y-m-d H:i:s') . " UTC\n";
    $dump .= "-- Database: {$databaseName}\n";
    $dump .= "-- Backup type: {$backupType}\n";
    $dump .= "SET NAMES utf8mb4;\n";
    $dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $table) {
        $createResult = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
        if (!$createResult) {
            continue;
        }

        $createStatement = $createResult['Create Table'] ?? '';
        $dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $dump .= $createStatement . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            continue;
        }

        $columns = array_keys($rows[0]);
        $columnList = implode(', ', array_map(static function ($column): string {
            return '`' . str_replace('`', '\\`', $column) . '`';
        }, $columns));

        foreach ($rows as $row) {
            $values = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = 'NULL';
                    continue;
                }
                $values[] = $pdo->quote((string) $value);
            }
            $dump .= "INSERT INTO `{$table}` ({$columnList}) VALUES (" . implode(', ', $values) . ");\n";
        }
        $dump .= "\n";
    }

    $dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    if (file_put_contents($filePath, $dump) === false) {
        return ['success' => false, 'message' => 'Failed to write backup file. Ensure the backups directory is writable.'];
    }

    return ['success' => true, 'fileName' => $fileName, 'filePath' => $filePath];
}

function restoreBackup(PDO $pdo, string $sqlContent): array
{
    $sqlContent = str_replace(["\r\n", "\r"], "\n", $sqlContent);
    $statements = [];
    $buffer = '';
    $inSingle = false;
    $inDouble = false;
    $escaped = false;

    foreach (str_split($sqlContent) as $char) {
        $buffer .= $char;

        if ($char === '\\' && !$escaped) {
            $escaped = true;
            continue;
        }

        if ($char === "'" && !$escaped && !$inDouble) {
            $inSingle = !$inSingle;
        }
        if ($char === '"' && !$escaped && !$inSingle) {
            $inDouble = !$inDouble;
        }

        if ($char === ';' && !$inSingle && !$inDouble) {
            $statements[] = trim($buffer);
            $buffer = '';
        }

        $escaped = false;
    }

    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }

    $executed = 0;
    foreach ($statements as $statement) {
        if ($statement === '' || preg_match('/^(--|\/\*)/', trim($statement))) {
            continue;
        }

        try {
            $pdo->exec($statement);
            $executed++;
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Restore failed: ' . $e->getMessage()];
        }
    }

    return ['success' => true, 'message' => 'Database restored successfully. ' . $executed . ' statements executed.'];
}

function loadBackupFiles(string $backupDir): array
{
    $files = scandir($backupDir);
    $backupFiles = [];
    if ($files !== false) {
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || !is_file($backupDir . '/' . $file)) {
                continue;
            }
            $meta = parseBackupFileName($file);
            $backupFiles[] = [
                'name' => $file,
                'size' => filesize($backupDir . '/' . $file),
                'time' => filemtime($backupDir . '/' . $file),
                'type' => $meta['type'],
                'timestamp' => $meta['timestamp'],
            ];
        }
    }

    usort($backupFiles, static function ($a, $b) {
        return $b['time'] <=> $a['time'];
    });

    return $backupFiles;
}

$backupFiles = loadBackupFiles($backupDir);
$lastDaily = null;
$lastWeekly = null;
foreach ($backupFiles as $file) {
    if ($file['type'] === 'daily' && $lastDaily === null) {
        $lastDaily = $file;
    }
    if ($file['type'] === 'weekly' && $lastWeekly === null) {
        $lastWeekly = $file;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['backup_daily', 'backup_weekly', 'backup_manual'], true)) {
        if (!$pdo instanceof PDO) {
            $backupError = 'Unable to connect to database. Backup cannot be generated.';
        } else {
            $backupType = $action === 'backup_daily' ? 'daily' : ($action === 'backup_weekly' ? 'weekly' : 'manual');
            $result = createBackup($pdo, $db, $backupDir, $backupType);
            if ($result['success']) {
                $backupMessage = 'Backup created successfully: ' . htmlspecialchars($result['fileName'], ENT_QUOTES, 'UTF-8');
            } else {
                $backupError = $result['message'];
            }
            $backupFiles = loadBackupFiles($backupDir);
        }
    } elseif ($action === 'restore') {
        if (empty($_FILES['backup_file']['tmp_name'])) {
            $backupError = 'Please select a SQL backup file to restore.';
        } else {
            $uploadedContent = file_get_contents($_FILES['backup_file']['tmp_name']);
            if ($uploadedContent === false) {
                $backupError = 'Unable to read the uploaded file.';
            } elseif (!$pdo instanceof PDO) {
                $backupError = 'Unable to connect to database. Restore cannot proceed.';
            } else {
                $result = restoreBackup($pdo, $uploadedContent);
                if ($result['success']) {
                    $backupMessage = $result['message'];
                } else {
                    $backupError = $result['message'];
                }
            }
        }
    }
} else {
    $now = time();
    $dailyDue = $lastDaily === null || ($now - $lastDaily['time']) >= 86400;
    $weeklyDue = $lastWeekly === null || ($now - $lastWeekly['time']) >= 604800;

    if ($dailyDue) {
        $result = createBackup($pdo, $db, $backupDir, 'daily');
        if ($result['success']) {
            $backupMessage = 'Automatic daily backup created: ' . htmlspecialchars($result['fileName'], ENT_QUOTES, 'UTF-8');
        }
    }
    if ($weeklyDue) {
        $result = createBackup($pdo, $db, $backupDir, 'weekly');
        if ($result['success']) {
            $backupMessage = ($backupMessage ? $backupMessage . ' ' : '') . 'Automatic weekly backup created: ' . htmlspecialchars($result['fileName'], ENT_QUOTES, 'UTF-8');
        }
    }
    $backupFiles = loadBackupFiles($backupDir);
}

// Get recent notifications
$recent_email_notifications = $pdo->query('SELECT * FROM email_notifications ORDER BY sent_at DESC LIMIT 5')->fetchAll();
$recent_course_updates = $pdo->query('SELECT cu.*, c.course_name FROM course_updates cu JOIN courses c ON cu.course_id = c.id ORDER BY cu.created_at DESC LIMIT 5')->fetchAll();
$recent_exam_reminders = $pdo->query('SELECT * FROM exam_reminders ORDER BY created_at DESC LIMIT 5')->fetchAll();
$recent_events = $pdo->query('SELECT * FROM event_announcements ORDER BY event_date DESC LIMIT 5')->fetchAll();
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>አስተዳዳሪ ዳሽቦርድ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f7fa;
            --surface: #ffffff;
            --surface-alt: #f1f5ff;
            --surface-muted: #f8fafc;
            --text: #333333;
            --muted: #6b7280;
            --border: #e5e7eb;
            --primary: #667eea;
            --primary-2: #764ba2;
            --primary-text: #ffffff;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            transition: background 0.2s ease, color 0.2s ease;
        }
        body[data-theme="dark"] {
            --bg: #0f172a;
            --surface: #111827;
            --surface-alt: #0b1220;
            --surface-muted: #0f172a;
            --text: #e5eefb;
            --muted: #94a3b8;
            --border: #1f2937;
            --primary: #8b5cf6;
            --primary-2: #6d28d9;
            --primary-text: #eef2ff;
        }
        .navbar { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-2) 100%); color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .navbar h1 { font-size: 24px; }
        .navbar a { color: white; text-decoration: none; margin-left: 20px; padding: 8px 15px; background: rgba(255,255,255,0.1); border-radius: 5px; }
        .navbar a:hover { background: rgba(255,255,255,0.2); }
        .theme-toggle {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 999px;
            padding: 8px 12px;
            cursor: pointer;
            font-weight: 700;
            margin-left: 12px;
        }
        .actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        .action-btn {
            display: block;
            padding: 12px 14px;
            border-radius: 12px;
            background: var(--surface);
            color: var(--text);
            text-decoration: none;
            font-weight: 700;
            border: 1px solid var(--border);
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
        }
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(0,0,0,0.12);
        }
        .action-btn.featured {
            background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
            color: white;
            border: none;
            box-shadow: 0 10px 22px rgba(37, 99, 235, 0.24);
        }
        .action-btn.featured:hover {
            box-shadow: 0 14px 28px rgba(37, 99, 235, 0.28);
        }
        .action-btn.featured.news {
            background: linear-gradient(135deg, #0f766e 0%, #2563eb 100%);
        }
        .action-btn.featured.blog {
            background: linear-gradient(135deg, #9333ea 0%, #7c3aed 100%);
        }
        .action-btn.featured.announcement {
            background: linear-gradient(135deg, #dc2626 0%, #f97316 100%);
        }
        body[data-theme="dark"] .theme-toggle {
            background: rgba(15, 23, 42, 0.7);
            border-color: var(--border);
            color: var(--text);
        }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: var(--surface); padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-left: 4px solid var(--primary); color: var(--text); }
        .stat-card h3 { color: var(--muted); font-size: 14px; margin-bottom: 10px; }
        .stat-card .value { font-size: 32px; font-weight: bold; color: var(--primary); }
        .stat-card .meta { color: var(--muted); font-size: 12px; margin-top: 8px; }
        .analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .chart-card { background: var(--surface); padding: 18px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); color: var(--text); }
        .chart-card h3 { margin-bottom: 12px; color: var(--text); font-size: 17px; }
        .chart-bars { display: flex; align-items: flex-end; gap: 10px; min-height: 160px; }
        .bar-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; gap: 6px; height: 160px; }
        .bar-track { height: 110px; width: 100%; display: flex; align-items: flex-end; justify-content: center; }
        .bar { width: 100%; border-radius: 8px 8px 0 0; background: linear-gradient(180deg, #8b7cf6 0%, #667eea 100%); min-height: 6px; }
        .bar-label { font-size: 11px; color: #667; text-transform: uppercase; }
        .bar-value { font-size: 12px; font-weight: 700; color: #1f2937; }
        .progress-list { display: flex; flex-direction: column; gap: 10px; }
        .progress-row { display: flex; flex-direction: column; gap: 6px; }
        .progress-top { display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #334155; }
        .progress-track { width: 100%; background: #edf2ff; border-radius: 999px; height: 8px; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #667eea 0%, #8b7cf6 100%); }
        .mini-badge { display: inline-block; padding: 4px 8px; border-radius: 999px; background: #eef2ff; color: #4f46e5; font-size: 11px; font-weight: 700; }
        .actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .action-btn { background: var(--surface); padding: 15px; border-radius: 8px; text-align: center; cursor: pointer; border: 2px solid var(--primary); transition: all 0.3s; text-decoration: none; color: var(--primary); font-weight: bold; }
        .action-btn:hover { background: var(--primary); color: white; }
        .report-section { background: var(--surface); padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); color: var(--text); margin-bottom: 30px; }
        .report-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
        .report-card { border: 1px solid var(--border); border-radius: 10px; padding: 12px; background: var(--surface-muted); }
        .report-title { font-weight: 700; margin-bottom: 8px; }
        .report-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .report-action { display: inline-block; padding: 6px 10px; border: 1px solid var(--primary); border-radius: 999px; background: var(--surface); color: var(--primary); font-size: 12px; font-weight: 700; text-decoration: none; }
        .report-action:hover { background: var(--primary); color: white; }
        .report-action.secondary { border-color: #64748b; color: #475569; }
        .report-action.secondary:hover { background: #64748b; color: white; }
        .table-section { background: var(--surface); padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); color: var(--text); }
        .backup-panel .card { min-height: 240px; }
        .status-box { border-radius: 14px; padding: 14px 18px; margin-bottom: 18px; }
        .status-success { background: #ecfdf5; color: #166534; border: 1px solid #bbf7d0; }
        .status-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: var(--surface-alt); font-weight: bold; color: var(--text); }
        .badge { padding: 5px 10px; border-radius: 5px; font-size: 12px; }
        .paid { background: #d4f1d8; color: #1d6a2b; }
        .unpaid { background: #ffe6e6; color: #a41616; }
        @media (max-width: 992px) { .navbar { flex-direction: column; align-items: flex-start; gap: 10px; } .navbar div { width: 100%; } .navbar a { margin-left: 0; margin-right: 10px; } }
        @media (max-width: 768px) { .container { padding: 0 12px; } .stats-grid, .analytics-grid, .actions { grid-template-columns: 1fr; } .table-section { overflow-x: auto; } }
        @media (max-width: 576px) { .navbar h1 { font-size: 20px; } .navbar a { display: inline-block; margin-top: 6px; } }
    </style>
</head>
<body>
<div class="navbar">
    <h1>🎓 ቤተ ገብርኤል - ዳሽቦርድ</h1>
    <div>
        <span>እንኳን ደህና መጡ, <?php echo safe($_SESSION['admin_username'] ?? 'Admin'); ?>!</span>
        <button class="theme-toggle" id="themeToggle" type="button" aria-label="Toggle theme">🌙 Dark</button>
    </div>
</div>

<div class="container">
    <h2 style="margin-bottom: 20px;">የዲያቆን ሶፎንያስ ዌቭሳይት ደመቀ(የቤተ ገብርኤል አጠቃላይ መረጃ) </h2>
    
    <div class="stats-grid">
        <div class="stat-card">
            <h3>ጠቅላላ ምዝገቦች</h3>
            <div class="value"><?php echo $total_registrations; ?></div>
        </div>
        <div class="stat-card">
            <h3>ክፍያ የከፈለ</h3>
            <div class="value"><?php echo $paid_count; ?></div>
        </div>
        <div class="stat-card">
            <h3>ክፍያ ያልከፈለ</h3>
            <div class="value"><?php echo $unpaid_count; ?></div>
        </div>
        <div class="stat-card">
            <h3>ጠቅላላ ኮርሶች</h3>
            <div class="value"><?php echo $total_courses; ?></div>
        </div>
        <div class="stat-card">
            <h3>ጠቅላላ ተማሪዎች</h3>
            <div class="value"><?php echo $total_students; ?></div>
        </div>
        <div class="stat-card">
            <h3>ጠቅላላ ሰርቲፊኬቶች</h3>
            <div class="value"><?php echo $total_certificates; ?></div>
        </div>
        <div class="stat-card">
            <h3>ጠቅላላ ገቢ (ብር)</h3>
            <div class="value"><?php echo number_format($total_revenue, 2); ?></div>
            <div class="meta">ከፍለው የተመዘገቡ ብቻ</div>
        </div>
        <div class="stat-card">
            <h3>ታዋቂው ኮርስ</h3>
            <div class="value" style="font-size: 18px; line-height: 1.4;"><?php echo safe($most_popular_course); ?></div>
            <div class="meta"><?php echo $most_popular_count; ?> የተመዘገቡ</div>
        </div>
        <div class="stat-card">
            <h3>ንቁ ተማሪዎች</h3>
            <div class="value"><?php echo $active_students; ?></div>
            <div class="meta">ላለፉት 30 ቀናት</div>
        </div>
    </div>

    <div class="analytics-grid">
        <div class="chart-card">
            <h3>📈 የ7 ቀን ምዝገባ አዝማሚያ</h3>
            <?php if (empty($weekly_registrations)): ?>
                <p style="color:#667;">ምንም ዳታ የለም።</p>
            <?php else: ?>
                <div class="chart-bars">
                    <?php foreach ($weekly_registrations as $item):
                        $height = $item['total'] > 0 ? min(100, 8 + (int)$item['total'] * 18) : 8;
                    ?>
                        <div class="bar-wrap">
                            <div class="bar-track">
                                <div class="bar" style="height: <?php echo $height; ?>%;"></div>
                            </div>
                            <div class="bar-label"><?php echo date('D', strtotime($item['day'])); ?></div>
                            <div class="bar-value"><?php echo (int)$item['total']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="chart-card">
            <h3>🏆 ታዋቂ ኮርሶች ድርሻ</h3>
            <div class="progress-list">
                <?php if (empty($course_breakdown)): ?>
                    <p style="color:#667;">ኮርስ አሁን የለም።</p>
                <?php else:
                    $maxCourseCount = max(array_column($course_breakdown, 'total'));
                    foreach ($course_breakdown as $item):
                        $pct = $maxCourseCount > 0 ? (int)(($item['total'] / $maxCourseCount) * 100) : 0;
                ?>
                    <div class="progress-row">
                        <div class="progress-top">
                            <span><?php echo safe($item['course']); ?></span>
                            <span class="mini-badge"><?php echo (int)$item['total']; ?></span>
                        </div>
                        <div class="progress-track"><div class="progress-fill" style="width: <?php echo $pct; ?>%;"></div></div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <?php if ($success_message): ?>
    <div style="margin-bottom: 20px; padding: 12px 14px; border-radius: 8px; background: #ecfdf3; color: #047857; border: 1px solid #a7f3d0;">
        <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <?php endif; ?>

    <h2 style="margin-bottom: 20px;">ድርጊቶች</h2>
    <div class="actions">
        <a href="admin_add_news.php" class="action-btn featured news">📰 አዲስ ዜና ጨምር</a>
        <a href="admin_add_blog.php" class="action-btn featured blog">📝 ብሎግ ጨምር</a>
        <a href="admin_add_announcement.php" class="action-btn featured announcement">📢 ማስታወቂያ ጨምር</a>
        <a href="admin_website_settings.php" class="action-btn">⚙️ Website Settings</a>
        <a href="admin_course_builder.php" class="action-btn">🛠️ ኮርስ ብሉደር ክፈት</a>
        <a href="admin_courses.php" class="action-btn">📹 ቪዲዮዎችን ጨምር</a>
        <a href="gallery_admin.php" class="action-btn featured gallery">🖼️ Gallery Manager</a>
        <a href="admin_add_question.php?view=sections" class="action-btn">🧩 Manage Sections</a>
        <a href="admin_add_question.php?view=questions" class="action-btn">🧠 Manage Questions</a>
        <a href="admin_exam_results.php" class="action-btn">📊 ሪፖርቶችን ተመልከት</a>
        <a href="admin_certificate.php" class="action-btn">📜 ሰርቲፊኬት አስተዳድር</a>
        <a href="admin_exam_access.php" class="action-btn">🔐 ፈተና ኮድ እና ማረጋገጫ</a>
        <a href="admin_db_backup.php" class="action-btn">💾 MySQL Backup</a>
        <a href="live_class.php" class="action-btn">📡 ላይቭ ክላስ</a>
        <a href="discussion_forum.php" class="action-btn">💬 ዲስኬሽን ፎርም</a>
        <a href="library.php" class="action-btn">📚 ላይበራሪ ዳሽቦርድ</a>
        <a href="admin_whiteboard.php" class="action-btn featured">🖋️ ኢንተራክቲቭ ዋይትቦርድ</a>
        <a href="admin_course_builder.php" class="action-btn">➕ ኮርስ ብሉደር / ፋብሪካ ስራ</a>
        <a href="admin_view_courses.php" class="action-btn">📚 ኮርሶች / ትምህርት ዝርዝር</a>
        <a href="admin_view_courses.php" class="action-btn">📚 ኮርሶችን አስተካክል / ሰርዝ</a>
        <a href="admin_students.php" class="action-btn">👥 ተማሪዎችን አስተዳድር</a>
        <a href="password_store.php" class="action-btn">🔐 የይለፍ ቃል ማስቀመጫ</a>
        <a href="admin_questions.php" class="action-btn">🧠 ጥያቄዎችን አስተዳድር</a>
        <a href="admin_registrations.php" class="action-btn">📝 ምዝገቦችን አስተዳድር</a>
        <a href="admin_exam_results.php" class="action-btn">📊 የፈተና ውጤቶች</a>
        <a href="results.php" class="action-btn">📈 የተማሪ ውጤቶች</a>
        <a href="admin_exam_reminders.php" class="action-btn">🔔 የፈተና ማስታወሻ አስተዳደር</a>
        <a href="admin_chat_management.php" class="action-btn">💬 ቻት አስተዳደር</a>
    </div>

    <div class="report-section">
        <div class="report-header">
            <h3>💾 Daily + Weekly Backup Management</h3>
            <p style="margin: 0; color: var(--muted);">Generate daily and weekly snapshots, restore from a backup, or download saved SQL files.</p>
        </div>
        <?php if ($backupMessage): ?>
            <div class="status-box status-success"><?php echo htmlspecialchars($backupMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($backupError): ?>
            <div class="status-box status-error"><?php echo htmlspecialchars($backupError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <div class="row gy-4 backup-panel">
            <div class="col-lg-4">
                <div class="card p-4">
                    <h2 class="panel-title">Daily Backup</h2>
                    <p class="note">Create a daily recovery point. The dashboard also auto-generates a daily backup when due.</p>
                    <form method="post">
                        <input type="hidden" name="action" value="backup_daily">
                        <button type="submit" class="btn btn-primary btn-lg">Create Daily Backup</button>
                    </form>
                    <?php if ($lastDaily): ?>
                        <p class="note" style="margin-top: 12px;">Last daily backup: <?php echo date('Y-m-d H:i', (int)$lastDaily['time']); ?></p>
                    <?php else: ?>
                        <p class="note" style="margin-top: 12px;">No daily backup found yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card p-4">
                    <h2 class="panel-title">Weekly Backup</h2>
                    <p class="note">Create a weekly database snapshot and keep a weekly recovery point.</p>
                    <form method="post">
                        <input type="hidden" name="action" value="backup_weekly">
                        <button type="submit" class="btn btn-primary btn-lg">Create Weekly Backup</button>
                    </form>
                    <?php if ($lastWeekly): ?>
                        <p class="note" style="margin-top: 12px;">Last weekly backup: <?php echo date('Y-m-d H:i', (int)$lastWeekly['time']); ?></p>
                    <?php else: ?>
                        <p class="note" style="margin-top: 12px;">No weekly backup found yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card p-4">
                    <h2 class="panel-title">Restore Backup</h2>
                    <p class="note">Upload any valid SQL backup to restore the database.</p>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="restore">
                        <div class="mb-3">
                            <label class="form-label">Backup file</label>
                            <input type="file" class="form-control" name="backup_file" accept=".sql" required>
                        </div>
                        <button type="submit" class="btn btn-outline-primary">Restore Now</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="table-wrap mt-3">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>File name</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($backupFiles)): ?>
                        <tr><td colspan="5" class="text-muted">No backup files available yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($backupFiles as $file): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($file['type']), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo number_format((int)$file['size'] / 1024, 2); ?> KB</td>
                                <td><?php echo date('Y-m-d H:i', (int)$file['time']); ?></td>
                                <td><a href="backups/<?php echo rawurlencode($file['name']); ?>" class="btn btn-sm btn-outline-secondary" target="_blank">Download</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-section">
        <div class="report-header">
            <h3>🧾 ፕሮፌሽናል ሪፖርት ማዕከል</h3>
            <p style="margin: 0; color: var(--muted);">በአስተዳዳሪ ዳሽቦርድ ውስጥ የሚታይ ሪፖርት እርምጃዎች</p>
        </div>
        <div class="report-grid">
            <div class="report-card">
                <div class="report-title">👨‍🎓 የተማሪ ዝርዝር</div>
                <div class="report-actions">
                    <a href="admin_students.php" class="report-action">View</a>
                    <a href="admin_edit_student.php" class="report-action">Edit</a>
                    <a href="admin_students.php" class="report-action secondary">Delete</a>
                    <a href="admin_reports.php?type=students&print=1" class="report-action">Print</a>
                    <a href="admin_reports.php?type=students" class="report-action">Export PDF</a>
                    <a href="admin_reports.php?type=students" class="report-action">Export Excel</a>
                </div>
            </div>
            <div class="report-card">
                <div class="report-title">📚 የኮርስ ዝርዝር</div>
                <div class="report-actions">
                    <a href="admin_view_courses.php" class="report-action">View</a>
                    <a href="admin_edit_course.php" class="report-action">Edit</a>
                    <a href="admin_view_courses.php" class="report-action secondary">Delete</a>
                    <a href="admin_reports.php?type=courses&print=1" class="report-action">Print</a>
                    <a href="admin_reports.php?type=courses" class="report-action">Export PDF</a>
                    <a href="admin_reports.php?type=courses" class="report-action">Export Excel</a>
                </div>
            </div>
            <div class="report-card">
                <div class="report-title">🧪 የኩዊዝ ውጤቶች</div>
                <div class="report-actions">
                    <a href="admin_exam_results.php" class="report-action">View</a>
                    <a href="admin_exam_results.php" class="report-action">Edit</a>
                    <a href="admin_exam_results.php" class="report-action secondary">Delete</a>
                    <a href="admin_reports.php?type=quiz_results&print=1" class="report-action">Print</a>
                    <a href="admin_reports.php?type=quiz_results" class="report-action">Export PDF</a>
                    <a href="admin_reports.php?type=quiz_results" class="report-action">Export Excel</a>
                </div>
            </div>
            <div class="report-card">
                <div class="report-title">📜 ሰርቲፊኬት</div>
                <div class="report-actions">
                    <a href="admin_certificate.php" class="report-action">View</a>
                    <a href="admin_certificate.php" class="report-action">Edit</a>
                    <a href="admin_certificate.php" class="report-action secondary">Delete</a>
                    <a href="admin_reports.php?type=certificates&print=1" class="report-action">Print</a>
                    <a href="admin_reports.php?type=certificates" class="report-action">Export PDF</a>
                    <a href="admin_reports.php?type=certificates" class="report-action">Export Excel</a>
                </div>
            </div>
            <div class="report-card">
                <div class="report-title">📩 የእውቂያ መልእክቶች</div>
                <div class="report-actions">
                    <a href="admin_reports.php?type=contact_messages" class="report-action">View</a>
                    <a href="admin_reports.php?type=contact_messages" class="report-action">Edit</a>
                    <a href="admin_reports.php?type=contact_messages" class="report-action secondary">Delete</a>
                    <a href="admin_reports.php?type=contact_messages&print=1" class="report-action">Print</a>
                    <a href="admin_reports.php?type=contact_messages" class="report-action">Export PDF</a>
                    <a href="admin_reports.php?type=contact_messages" class="report-action">Export Excel</a>
                </div>
            </div>
            <div class="report-card">
                <div class="report-title">📢 ማስታወቂያዎች</div>
                <div class="report-actions">
                    <a href="admin_reports.php?type=announcements" class="report-action">View</a>
                    <a href="admin_reports.php?type=announcements" class="report-action">Edit</a>
                    <a href="admin_reports.php?type=announcements" class="report-action secondary">Delete</a>
                    <a href="admin_reports.php?type=announcements&print=1" class="report-action">Print</a>
                    <a href="admin_reports.php?type=announcements" class="report-action">Export PDF</a>
                    <a href="admin_reports.php?type=announcements" class="report-action">Export Excel</a>
                </div>
            </div>
            <div class="report-card">
                <div class="report-title">📝 ብሎግ ፖስቶች</div>
                <div class="report-actions">
                    <a href="admin_reports.php?type=blog_posts" class="report-action">View</a>
                    <a href="admin_reports.php?type=blog_posts" class="report-action">Edit</a>
                    <a href="admin_reports.php?type=blog_posts" class="report-action secondary">Delete</a>
                    <a href="admin_reports.php?type=blog_posts&print=1" class="report-action">Print</a>
                    <a href="admin_reports.php?type=blog_posts" class="report-action">Export PDF</a>
                    <a href="admin_reports.php?type=blog_posts" class="report-action">Export Excel</a>
                </div>
            </div>
            <div class="report-card">
                <div class="report-title">🧾 የምዝገባ ሪፖርት</div>
                <div class="report-actions">
                    <a href="admin_registrations.php" class="report-action">View</a>
                    <a href="admin_registrations.php" class="report-action">Edit</a>
                    <a href="admin_registrations.php" class="report-action secondary">Delete</a>
                    <a href="admin_reports.php?type=enrollment_reports&print=1" class="report-action">Print</a>
                    <a href="admin_reports.php?type=enrollment_reports" class="report-action">Export PDF</a>
                    <a href="admin_reports.php?type=enrollment_reports" class="report-action">Export Excel</a>
                </div>
            </div>
        </div>
    </div>

    <div class="table-section">
        <h3>የቅርብ መዝገቦች</h3>
        <table>
            <thead>
                <tr>
                    <th>ስም</th>
                    <th>ኢሜይል</th>
                    <th>መለያ</th>
                    <th>ኮርስ</th>
                    <th>ክፍያ ሁኔታ</th>
                    <th>ታሪክ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->query('SELECT * FROM registrations ORDER BY created_at DESC LIMIT 10');
                $recent = $stmt->fetchAll();
                
                if (empty($recent)): ?>
                    <tr><td colspan="6" style="text-align: center;">ምንም ምዝገባ የለም</td></tr>
                <?php else:
                    foreach ($recent as $row): ?>
                        <tr>
                            <td><?php echo safe($row['name']); ?></td>
                            <td><?php echo safe($row['email']); ?></td>
                            <td><?php echo safe($row['student_id']); ?></td>
                            <td><?php echo safe($row['course']); ?></td>
                            <td><span class="badge <?php echo ($row['payment_status'] === 'paid' ? 'paid' : 'unpaid'); ?>"><?php echo safe($row['payment_status']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                        </tr>
                    <?php endforeach;
                endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-section" style="margin-top: 30px;">
        <h3>የቅርብ ተማሪዎች</h3>
        <table>
            <thead>
                <tr>
                    <th>ስም</th>
                    <th>ኢሜይል</th>
                    <th>መለያ</th>
                    <th>ታሪክ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_students)): ?>
                    <tr><td colspan="4" style="text-align: center;">ምንም ተማሪ የለም</td></tr>
                <?php else: ?>
                    <?php foreach ($recent_students as $student): ?>
                        <tr>
                            <td><?php echo safe($student['name']); ?></td>
                            <td><?php echo safe($student['email']); ?></td>
                            <td><?php echo safe($student['student_id']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-section" style="margin-top: 30px;">
        <h3>የቅርብ ኮርሶች</h3>
        <table>
            <thead>
                <tr>
                    <th>ኮርስ ስም</th>
                    <th>ኮድ</th>
                    <th>ዋጋ</th>
                    <th>ታሪክ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_courses)): ?>
                    <tr><td colspan="4" style="text-align: center;">ምንም ኮርስ የለም</td></tr>
                <?php else: ?>
                    <?php foreach ($recent_courses as $course): ?>
                        <tr>
                            <td><?php echo safe($course['course_name']); ?></td>
                            <td><?php echo safe($course['course_code']); ?></td>
                            <td><?php echo number_format($course['price'], 2); ?> ብር</td>
                            <td><?php echo date('M d, Y', strtotime($course['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <h2 style="margin-bottom: 20px; margin-top: 30px;">📢 በዳሽቦርድ ማስታወቂያ</h2>

    <div class="table-section">
        <h3>📧 የኢሜይል ማስታወቂያ</h3>
        <table>
            <thead>
                <tr>
                    <th>ተቀባይ</th>
                    <th>ርእስ</th>
                    <th>መልዕክት</th>
                    <th>ታሪክ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_email_notifications)): ?>
                    <tr><td colspan="4" style="text-align: center;">ምንም ኢሜይል ማስታወቂያ የለም</td></tr>
                <?php else: ?>
                    <?php foreach ($recent_email_notifications as $email): ?>
                        <tr>
                            <td><?php echo safe($email['recipient_email']); ?></td>
                            <td><?php echo safe($email['subject']); ?></td>
                            <td><?php echo substr(safe($email['message']), 0, 50) . '...'; ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($email['sent_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-section" style="margin-top: 30px;">
        <h3>📚 የተሻሻሉ ኮርሶች</h3>
        <table>
            <thead>
                <tr>
                    <th>ኮርስ ስም</th>
                    <th>ዝርዝር</th>
                    <th>ታሪክ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_course_updates)): ?>
                    <tr><td colspan="3" style="text-align: center;">ምንም የኮርስ ዝርዝር የተሻሻለ የለም</td></tr>
                <?php else: ?>
                    <?php foreach ($recent_course_updates as $update): ?>
                        <tr>
                            <td><?php echo safe($update['course_name']); ?></td>
                            <td><?php echo substr(safe($update['update_message']), 0, 50) . '...'; ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($update['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-section" style="margin-top: 30px;">
        <h3>🔔 ፈተናዎችን ማስታወሻ</h3>
        <table>
            <thead>
                <tr>
                    <th>ተማሪ መለያ</th>
                    <th>ፈተና ዓይነት</th>
                    <th>ፈተና ቀን</th>
                    <th>ታሪክ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_exam_reminders)): ?>
                    <tr><td colspan="4" style="text-align: center;">ምንም ፈተና ማስታወቂያ የለም</td></tr>
                <?php else: ?>
                    <?php foreach ($recent_exam_reminders as $reminder): ?>
                        <tr>
                            <td><?php echo safe($reminder['student_id']); ?></td>
                            <td><?php echo safe($reminder['exam_type']); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($reminder['exam_date'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($reminder['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-section" style="margin-top: 30px;">
        <h3>🎉 ሁኔታዎችን ማስታወቂያ እና መግለጫ</h3>
        <table>
            <thead>
                <tr>
                    <th>ፍጥረታዊ ርዕስ</th>
                    <th>መግለጫ</th>
                    <th>ፍጥረታዊ ቀን</th>
                    <th>እርምጃ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_events)): ?>
                    <tr><td colspan="3" style="text-align: center;">ምንም ፍጥረታዊ ዝበሌ የለም</td></tr>
                <?php else: ?>
                    <?php foreach ($recent_events as $event): ?>
                        <tr>
                            <td><?php echo safe($event['event_title']); ?></td>
                            <td><?php echo substr(safe($event['event_description']), 0, 50) . '...'; ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($event['event_date'])); ?></td>
                            <td><a href="admin_edit_content.php?id=<?php echo (int)$event['id']; ?>&type=announcement" style="color:#2563eb;text-decoration:none;">✏️ አስተካክል</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

    <script>
        (function () {
            const storageKey = 'sofnyas-theme';
            const toggle = document.getElementById('themeToggle');
            const applyTheme = (theme) => {
                document.body.setAttribute('data-theme', theme);
                if (toggle) {
                    toggle.textContent = theme === 'dark' ? '☀️ Light' : '🌙 Dark';
                }
            };
            const savedTheme = localStorage.getItem(storageKey);
            if (savedTheme === 'dark' || savedTheme === 'light') {
                applyTheme(savedTheme);
            } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                applyTheme('dark');
            } else {
                applyTheme('light');
            }
            if (toggle) {
                toggle.addEventListener('click', () => {
                    const currentTheme = document.body.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                    const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
                    applyTheme(nextTheme);
                    localStorage.setItem(storageKey, nextTheme);
                });
            }
        })();
    </script>
</body>
</html>
