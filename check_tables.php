<?php
$host = '127.0.0.1';
$db = 'sofonyas_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Check Tables</title></head>
<body style="font-family:Arial,sans-serif;background:#f8fafc;color:#111;padding:24px;">
  <h1>Database Connection Check</h1>
  <pre style="background:#fff;border:1px solid #d1d5db;padding:16px;border-radius:12px;overflow:auto;">
<?php
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Connected to database.\n";
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if ($tables) {
        echo "Tables:\n";
        foreach ($tables as $table) {
            echo "- $table\n";
        }
    } else {
        echo "No tables found.\n";
    }
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage();
}
?>
  </pre>
  <p><a href="deployment_check.php">Go to deployment check</a></p>
</body>
</html>
