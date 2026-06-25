<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once 'db.php';

$message = '';
$messageType = 'success';

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS site_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT DEFAULT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {}

$stmt = $pdo->query('SELECT setting_key, setting_value FROM site_settings');
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $websiteName = trim((string)($_POST['website_name'] ?? ''));
    $contactEmail = trim((string)($_POST['contact_email'] ?? ''));
    $phoneNumber = trim((string)($_POST['phone_number'] ?? ''));
    $footerText = trim((string)($_POST['footer_text'] ?? ''));

    $values = [
        'website_name' => $websiteName,
        'contact_email' => $contactEmail,
        'phone_number' => $phoneNumber,
        'footer_text' => $footerText,
    ];

    $logoPath = $settings['logo'] ?? '';
    if (isset($_FILES['logo']) && is_array($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/site_logo';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $fileName = 'logo-' . time() . '-' . bin2hex(random_bytes(4)) . ($ext !== '' ? '.' . $ext : '');
        $target = $uploadDir . '/' . $fileName;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
            $logoPath = 'uploads/site_logo/' . $fileName;
        }
    }

    $values['logo'] = $logoPath;

    foreach ($values as $key => $val) {
        $stmt = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $stmt->execute([':key' => $key, ':value' => $val]);
    }

    $message = 'Website settings were saved successfully.';
    $messageType = 'success';

    $stmt = $pdo->query('SELECT setting_key, setting_value FROM site_settings');
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Settings</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f7fb; margin: 0; padding: 0; color: #111827; }
        .wrap { max-width: 860px; margin: 30px auto; padding: 24px; background: #fff; border-radius: 16px; box-shadow: 0 10px 24px rgba(15,23,42,.08); }
        h1 { margin-top: 0; color: #2563eb; }
        label { display: block; font-weight: 700; margin-top: 12px; }
        input, textarea, button { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 10px; margin-top: 6px; }
        textarea { min-height: 110px; }
        button { background: #2563eb; color: #fff; border: 0; cursor: pointer; font-weight: 700; }
        .msg { margin-top: 12px; padding: 10px 12px; border-radius: 10px; background: #ecfdf3; color: #047857; }
        .muted { color: #64748b; font-size: 13px; }
        .logo-preview { margin-top: 10px; max-width: 140px; border-radius: 12px; border: 1px solid #e2e8f0; padding: 8px; background: #f8fafc; }
        a { color: #2563eb; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>⚙️ ዌቭሣይት ማስተካከያ</h1>
    <p class="muted">የዌቭሣይቱን ሥም፣ሎጎ፣ኢሜይል/ስልክ ቁጥድ፣እና የግርጌ ጽሁፍ ከዚህ ማስተካከል ይቺላሉ።.</p>
    <?php if ($message): ?><div class="msg"><?php echo safe($message); ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <label for="website_name">የዌቭሣይት ሥም</label>
        <input id="website_name" name="website_name" value="<?php echo safe($settings['website_name'] ?? ''); ?>" placeholder="ዲ/ን ሶፎንያስ ደመቀ ቤተ ገብርኤል ዌቭሣይት">

        <label for="logo">ሎጎ</label>
        <input id="logo" name="logo" type="file" accept="image/*">
        <?php if (!empty($settings['logo'])): ?>
            <img class="logo-preview" src="<?php echo safe($settings['logo']); ?>" alt="Current Logo">
        <?php endif; ?>

        <label for="contact_email">አድራሻ ኢሜይል</label>
        <input id="contact_email" name="contact_email" type="email" value="<?php echo safe($settings['contact_email'] ?? ''); ?>" placeholder="yilkaldemeke21@gmail.com">

        <label for="phone_number">ስልክ ቁጥር</label>
        <input id="phone_number" name="phone_number" value="<?php echo safe($settings['phone_number'] ?? ''); ?>" placeholder="+251 927603731">

        <label for="footer_text">የግርጌ ጽሁፍ</label>
        <textarea id="footer_text" name="footer_text" placeholder="Write footer text here"><?php echo safe($settings['footer_text'] ?? ''); ?></textarea>

        <button type="submit" style="margin-top:16px;">Save Settings</button>
    </form>
    <p style="margin-top:16px;"><a href="admin_dashboard.php">← ወደ ዳሽቦርድ</a></p>
</div>
</body>
</html>
