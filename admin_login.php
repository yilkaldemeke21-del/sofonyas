<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/db.php';

// If admin already logged in, redirect to dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: admin_dashboard.php');
    exit;
}

if (!isset($error)) {
    $error = '';
}
$csrfToken = csrfToken();
$recaptchaSiteKey = getenv('RECAPTCHA_SITE_KEY') ?: '';
$recaptchaSecret = getenv('RECAPTCHA_SECRET_KEY') ?: '';
$maxAttempts = 5;
$dbConnectionError = defined('DB_CONNECTION_ERROR') && DB_CONNECTION_ERROR !== '' ? DB_CONNECTION_ERROR : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($dbConnectionError !== '') {
        $error = $dbConnectionError;
    } else {
        $submittedToken = trim((string)($_POST['csrf_token'] ?? ''));
        $csrfValid = $submittedToken === '' ? true : validateCsrfToken($submittedToken);
        if (!$csrfValid) {
            $error = 'ደህንነት ተሰርዟል። እባክዎ ገጹን እንደገና ይጫኑ።';
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $captchaResponse = $_POST['g-recaptcha-response'] ?? '';

            if ($username !== '' && $password !== '') {
                $attemptKey = 'admin_login:' . strtolower($username) . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
                if ($pdo instanceof PDO && loginAttemptWindowCount($pdo, $attemptKey) >= $maxAttempts) {
                    $error = 'ብዙ የመግቢያ ሙከራዎች ተደርገዋል። እባክዎ ከጥቂት ደቂቃዎች በኋላ እንደገና ይሞክሩ።';
                } elseif ($pdo instanceof PDO && $recaptchaSiteKey !== '' && $recaptchaSecret !== '' && !verifyCaptchaResponse($captchaResponse, $recaptchaSecret)) {
                    $error = 'የማንኛውም ደህንነት ምልክት አልተሳካም። እባክዎ ዳግም ይሞክሩ።';
                } elseif ($pdo instanceof PDO) {
                    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = :username');
                    $stmt->execute([':username' => $username]);
                    $admin = $stmt->fetch();

                    if ($admin && password_verify($password, $admin['password_hash'])) {
                        clearLoginAttempts($pdo, $attemptKey);
                        session_regenerate_id(true);
                        $role = isset($admin['role']) && $admin['role'] !== '' ? $admin['role'] : 'Admin';
                        $_SESSION['admin_id'] = $admin['id'];
                        $_SESSION['admin_username'] = $admin['username'];
                        $_SESSION['user_role'] = $role;
                        $_SESSION['is_admin'] = ($role === 'Admin');
                        $_SESSION['is_instructor'] = ($role === 'Instructor');

                        if ($role === 'Instructor') {
                            $instructorRoute = file_exists(__DIR__ . '/instructor_dashboard.php') ? 'instructor_dashboard.php' : 'instractor_dashboard.php';
                            header('Location: ' . $instructorRoute);
                        } else {
                            header('Location: admin_dashboard.php');
                        }
                        exit;
                    }

                    recordLoginAttempt($pdo, $attemptKey);
                    $error = 'አገልግሎት ስም ወይም ይለፍ ቃል ትክክል አይደለም።';
                } else {
                    $error = $dbConnectionError !== '' ? $dbConnectionError : 'Database connection is not available.';
                }
            } else {
                $error = 'እባክዎ ሁለቱንም መስኮች ይሙሉ።';
            }
        }
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
    
    <?php if (!empty($error)): ?>
        <div class="error"><?php echo safe($error); ?></div>
    <?php endif; ?>
    
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo safe($csrfToken); ?>">
        <div class="form-group">
            <label for="username">አገልግሎት ስም</label>
            <input type="text" id="username" name="username" required>
        </div>
        
        <div class="form-group">
            <label for="password">ይለፍ ቃል</label>
            <input type="password" id="password" name="password" required>
        </div>
        <?php if ($recaptchaSiteKey !== ''): ?>
            <div class="form-group">
                <script src="https://www.google.com/recaptcha/api.js" async defer></script>
                <div class="g-recaptcha" data-sitekey="<?php echo safe($recaptchaSiteKey); ?>"></div>
            </div>
        <?php endif; ?>
        
        <button type="submit">ግባ</button>
    </form>
    
    <p style="text-align: center; margin-top: 20px; color: #666;">
        <strong>ዲ/ን ሶፎንያስ ደመቀ:</strong>ቤተ ገብርኤል
    </p>
</div>
</body>
</html>
