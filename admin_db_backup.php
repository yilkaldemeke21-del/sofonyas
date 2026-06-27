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

function splitSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $inSingleQuote = false;
    $inDoubleQuote = false;
    $escaped = false;

    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($char === '\\' && !$escaped) {
            $escaped = true;
            $buffer .= $char;
            continue;
        }

        if ($char === "'" && !$inDoubleQuote && !$escaped) {
            $inSingleQuote = !$inSingleQuote;
            $buffer .= $char;
            continue;
        }

        if ($char === '"' && !$inSingleQuote && !$escaped) {
            $inDoubleQuote = !$inDoubleQuote;
            $buffer .= $char;
            continue;
        }

        if ($char === ';' && !$inSingleQuote && !$inDoubleQuote) {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            $escaped = false;
            continue;
        }

        $buffer .= $char;
        $escaped = false;
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

function buildDatabaseBackup(PDO $pdo, string $databaseName): array
{
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if (!$tables) {
        return ['success' => false, 'message' => 'No tables found to back up.'];
    }

    $timestamp = gmdate('Ymd_His');
    $fileName = $databaseName . '_backup_' . $timestamp . '.sql';
    $backupDir = __DIR__ . '/backups';
    $filePath = $backupDir . '/' . $fileName;

    $dump = "-- Database backup generated on " . gmdate('Y-m-d H:i:s') . " UTC\n";
    $dump .= "-- Database: $databaseName\n";
    $dump .= "SET NAMES utf8mb4;\n";
    $dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $table) {
        $createResult = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
        if (!$createResult) {
            continue;
        }

        $createStatement = $createResult['Create Table'] ?? $createResult['Create Table'] ?? '';
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
        return ['success' => false, 'message' => 'Unable to write backup file. Check writable permissions for the backups folder.'];
    }

    return ['success' => true, 'fileName' => $fileName, 'filePath' => $filePath];
}

function restoreDatabaseBackup(PDO $pdo, string $sqlContent): array
{
    $statements = splitSqlStatements($sqlContent);
    $executed = 0;

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '' || preg_match('/^(--|\/\*)/i', $statement)) {
            continue;
        }

        try {
            $pdo->exec($statement);
            $executed++;
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Restore failed: ' . $e->getMessage()];
        }
    }

    return ['success' => true, 'message' => 'Database restored successfully. ' . $executed . ' statement(s) executed.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'backup') {
        $result = buildDatabaseBackup($pdo, $db);
        if ($result['success']) {
            $downloadPath = 'backups/' . $result['fileName'];
            header('Location: ' . $downloadPath);
            exit;
        }

        $error = $result['message'];
    } elseif ($action === 'restore') {
        if (empty($_FILES['backup_file']['tmp_name'])) {
            $error = 'Please select a backup file to restore.';
        } else {
            $uploadedContent = file_get_contents($_FILES['backup_file']['tmp_name']);
            if ($uploadedContent === false) {
                $error = 'The uploaded backup file could not be read.';
            } else {
                $result = restoreDatabaseBackup($pdo, $uploadedContent);
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
if (is_dir($backupDir)) {
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

        usort($backupFiles, static function ($a, $b): int {
            return $b['time'] <=> $a['time'];
        });
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup & Restore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%); font-family: 'Segoe UI', Arial, sans-serif; color: #0f172a; }
        .page { max-width: 1100px; margin: 32px auto; padding: 24px; }
        .card { background: #fff; border-radius: 18px; box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08); border: 1px solid #e2e8f0; }
        .card-header { background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%); color: #fff; padding: 20px 24px; border-top-left-radius: 18px; border-top-right-radius: 18px; }
        .card-body { padding: 24px; }
        .btn-primary { background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%); border: 0; }
        .btn-outline { border: 1px solid #cbd5e1; color: #334155; }
        .status { padding: 14px 16px; border-radius: 12px; margin-bottom: 18px; }
        .status.success { background: #ecfdf3; color: #047857; border: 1px solid #a7f3d0; }
        .status.error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .list-group-item { border-left: 0; border-right: 0; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h2 class="mb-1">🗄️ Database Backup & Restore</h2>
                    <p class="mb-0 muted text-white-50">Keep your system safe with professional backup and restore controls.</p>
                </div>
                <a href="admin_dashboard.php" class="btn btn-light">← Back to Dashboard</a>
            </div>
        </div>
        <div class="card-body">
            <?php if ($message !== ''): ?>
                <div class="status success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="status error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="border rounded-4 p-4 h-100">
                        <h4 class="mb-3">Create Backup</h4>
                        <p class="muted">Download a clean SQL backup of the current database. This is ideal before major updates or migrations.</p>
                        <form method="post">
                            <input type="hidden" name="action" value="backup">
                            <button type="submit" class="btn btn-primary">Download SQL Backup</button>
                        </form>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="border rounded-4 p-4 h-100">
                        <h4 class="mb-3">Restore Backup</h4>
                        <p class="muted">Upload an earlier SQL backup file to restore your database. Use this with caution.</p>
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="restore">
                            <div class="mb-3">
                                <input class="form-control" type="file" name="backup_file" accept=".sql" required>
                            </div>
                            <button type="submit" class="btn btn-outline">Restore Database</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="mt-4 border rounded-4 p-4">
                <h4 class="mb-3">Recent Backup Files</h4>
                <?php if (empty($backupFiles)): ?>
                    <p class="muted mb-0">No backup files have been created yet.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($backupFiles as $backupFile): ?>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($backupFile['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <div class="muted small"><?php echo number_format($backupFile['size'] / 1024, 1); ?> KB</div>
                                </div>
                                <a class="btn btn-sm btn-outline" href="backups/<?php echo urlencode($backupFile['name']); ?>" target="_blank">Download</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
