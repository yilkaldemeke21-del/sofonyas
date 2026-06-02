<?php
$adminAccessKey = 'sofonyas_secret_admin_2026';

if (!isset($_GET['key']) || $_GET['key'] !== $adminAccessKey) {
    http_response_code(404);
    exit('Page not found');
}

session_start();
require_once __DIR__ . '/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = :username');
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            header('Location: admin_dashboard.php');
            exit;
        } else {
            $error = 'አገልግሎት ስም ወይም ይለፍ ቃል ትክክል አይደለም።';
        }
    } else {
        $error = 'እባክዎ ሁለቱንም መስኮች ይሙሉ።';
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>አስተዳዳሪ ግባ</title>
    <style>
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; justify-content: center; align-items: center; }
        .login-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); width: 100%; max-width: 400px; }
        h1 { text-align: center; color: #333; margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; }
        input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; font-size: 14px; }
        input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 5px rgba(102, 126, 234, 0.3); }
        button { width: 100%; padding: 10px; background: #667eea; color: white; border: none; border-radius: 5px; font-size: 16px; font-weight: bold; cursor: pointer; }
        button:hover { background: #764ba2; }
        .error { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="login-card">
    <h1>አስተዳዳሪ ግባ</h1>
    
    <?php if ($error): ?>
        <div class="error"><?php echo safe($error); ?></div>
    <?php endif; ?>
    
    <form method="post">
        <div class="form-group">
            <label for="username">አገልግሎት ስም</label>
            <input type="text" id="username" name="username" required>
        </div>
        
        <div class="form-group">
            <label for="password">ይለፍ ቃል</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <button type="submit">ግባ</button>
    </form>
    
    <p style="text-align: center; margin-top: 20px; color: #666;">
        <strong>ሙከራ ምዝገባ:</strong> admin / password
    </p>
</div>
</body>
</html>
