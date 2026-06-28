<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$backupDir = __DIR__ . '/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$message = '';
$error = '';

function safePath(string $path): string {
    return str_replace(['..', '\\', '/'], '', $path);
}

function createBackup(PDO $pdo, string $databaseName, string $backupDir): array
{
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if (!$tables) {
        return ['success' => false, 'message' => 'No tables found to back up.'];
    }

    $timestamp = gmdate('Ymd_His');
    $fileName = $databaseName . '_backup_' . $timestamp . '.sql';
    $filePath = $backupDir . '/' . $fileName;

    $dump = "-- Database backup generated on " . gmdate('Y-m-d H:i:s') . " UTC\n";
    $dump .= "-- Database: {$databaseName}\n";
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'backup') {
        if (!$pdo instanceof PDO) {
            $error = 'Unable to connect to database. Backup cannot be generated.';
        } else {
            $result = createBackup($pdo, $db, $backupDir);
            if ($result['success']) {
                $message = 'Backup created successfully: ' . safe($result['fileName']);
                $downloadLink = 'backups/' . rawurlencode($result['fileName']);
            } else {
                $error = $result['message'];
            }
        }
    } elseif ($action === 'restore') {
        if (empty($_FILES['backup_file']['tmp_name'])) {
            $error = 'Please select a SQL backup file to restore.';
        } else {
            $uploadedContent = file_get_contents($_FILES['backup_file']['tmp_name']);
            if ($uploadedContent === false) {
                $error = 'Unable to read the uploaded file.';
            } elseif (!$pdo instanceof PDO) {
                $error = 'Unable to connect to database. Restore cannot proceed.';
            } else {
                $result = restoreBackup($pdo, $uploadedContent);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
}

$backupFiles = [];
$files = scandir($backupDir);
if ($files !== false) {
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || !is_file($backupDir . '/' . $file)) {
            continue;
        }
        $backupFiles[] = [
            'name' => $file,
            'size' => filesize($backupDir . '/' . $file),
            'time' => filemtime($backupDir . '/' . $file),
        ];
    }
}

function formatBytes(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Database Backup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { margin: 0; font-family: Inter, Arial, sans-serif; background: #f3f6fb; color: #1f2937; }
        .navbar { background: linear-gradient(135deg, #334155, #2563eb); color: white; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .navbar a, .navbar span { color: white; text-decoration: none; }
        .root { max-width: 1120px; margin: 24px auto; padding: 0 20px; }
        .hero { background: white; border-radius: 20px; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.08); padding: 28px; margin-bottom: 24px; }
        .hero h1 { margin-bottom: 10px; font-size: 2rem; }
        .hero p { color: #475569; line-height: 1.75; }
        .card { border: 0; border-radius: 18px; box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06); }
        .status-box { border-radius: 14px; padding: 14px 18px; margin-bottom: 18px; }
        .status-success { background: #ecfdf5; color: #166534; border: 1px solid #bbf7d0; }
        .status-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .panel-title { font-weight: 700; margin-bottom: 12px; }
        .note { color: #475569; font-size: 0.95rem; margin-top: 6px; }
        .backup-tag { display: inline-flex; align-items: center; gap: 8px; background: #e0f2fe; color: #0369a1; border-radius: 999px; padding: 6px 12px; font-size: 0.95rem; }
        .table-wrap { overflow-x: auto; }
        .table thead th { border-bottom: 2px solid #e2e8f0; }
    </style>
</head>
<body>
<div class="navbar">
    <div>
        <a href="admin_dashboard.php" style="font-size: 1.05rem; font-weight: 700;">← ዳሽቦርድ</a>
    </div>
    <div>
        <span>ዳታቤዝ መመለሻ</span>
    </div>
</div>

<div class="root">
    <section class="hero">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start">
            <div>
                <h1>MySQL Backup & Restore</h1>
                <p>Export your current database state, download a secure SQL backup, or restore from an existing backup file. This page is for trusted administrators only.</p>
            </div>
            <div class="backup-tag">Safe admin tool</div>
        </div>
    </section>

    <?php if ($message): ?>
        <div class="status-box status-success"><?php echo safe($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="status-box status-error"><?php echo safe($error); ?></div>
    <?php endif; ?>

    <div class="row gy-4">
        <div class="col-lg-6">
            <div class="card p-4">
                <h2 class="panel-title">Create New Backup</h2>
                <p class="note">Click the button below to generate a fresh database backup file. The backup includes table schema and all current data.</p>
                <form method="post">
                    <input type="hidden" name="action" value="backup">
                    <button type="submit" class="btn btn-primary btn-lg">Generate Backup</button>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card p-4">
                <h2 class="panel-title">Restore From Backup</h2>
                <p class="note">Upload a valid SQL file to restore the database. This action may overwrite existing data.</p>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="restore">
                    <div class="mb-3">
                        <label class="form-label">Backup file</label>
                        <input type="file" class="form-control" name="backup_file" accept=".sql" required>
                    </div>
                    <button type="submit" class="btn btn-outline-primary">Restore Backup</button>
                </form>
            </div>
        </div>
    </div>

    <section class="card p-4 mt-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h2 class="panel-title">Available Backup Files</h2>
                <p class="note">Backups are stored in the <code>backups/</code> folder. Click a file to download it.</p>
            </div>
            <div>
                <span class="badge bg-info text-dark">Total: <?php echo count($backupFiles); ?></span>
            </div>
        </div>
        <div class="table-wrap mt-3">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>File name</th>
                        <th>Size</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($backupFiles)): ?>
                        <tr><td colspan="4" class="text-muted">No backup files found yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($backupFiles as $file): ?>
                            <tr>
                                <td><?php echo safe($file['name']); ?></td>
                                <td><?php echo safe(formatBytes((int)$file['size'])); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', (int)$file['time']); ?></td>
                                <td><a href="backups/<?php echo rawurlencode($file['name']); ?>" class="btn btn-sm btn-outline-secondary" target="_blank">Download</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>
