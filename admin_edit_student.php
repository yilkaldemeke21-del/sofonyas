<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$student = null;
$error = '';
$success = '';

if ($student_id) {
    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = :id');
    $stmt->execute([':id' => $student_id]);
    $student = $stmt->fetch();
    if (!$student) {
        header('Location: admin_students.php');
        exit;
    }
}

$name = $student['name'] ?? '';
$email = $student['email'] ?? '';
$student_code = $student['student_id'] ?? '';
$country = $student['country'] ?? '';
$city = $student['city'] ?? '';
$password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $student_code = trim($_POST['student_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '') {
        $error = 'ስም ያስገቡ።';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'እባክዎ ትክክለኛ ኢሜይል ያስገቡ።';
    } elseif ($student_code === '') {
        $error = 'የተማሪ መለያ ቁጥር ያስገቡ።';
    }

    if (!$error) {
        $query = 'SELECT id FROM students WHERE (email = :email OR student_id = :student_id)';
        $params = [':email' => $email, ':student_id' => $student_code];
        if ($student_id) {
            $query .= ' AND id != :id';
            $params[':id'] = $student_id;
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $error = 'ይህ ኢሜይል ወይም የተማሪ መለያ ቁጥር አስቀድሞ ተመዝግቧል።';
        }
    }

    if (!$error) {
        try {
            if ($student_id) {
                $sql = 'UPDATE students SET name = :name, email = :email, student_id = :student_id, country = :country, city = :city';
                $params = [
                    ':name' => $name,
                    ':email' => $email,
                    ':student_id' => $student_code,
                    ':country' => $country,
                    ':city' => $city,
                    ':id' => $student_id,
                ];
                if ($password !== '') {
                    $sql .= ', password_hash = :password_hash';
                    $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql .= ' WHERE id = :id';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $success = 'ተማሪው በትክክል ተሻሽሏል።';
            } else {
                if ($password === '') {
                    $error = 'እባክዎ የይለፍ ቃል ያስገቡ።';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO students (name, email, student_id, password_hash, country, city) VALUES (:name, :email, :student_id, :password_hash, :country, :city)');
                    $stmt->execute([
                        ':name' => $name,
                        ':email' => $email,
                        ':student_id' => $student_code,
                        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        ':country' => $country,
                        ':city' => $city,
                    ]);
                    $student_id = $pdo->lastInsertId();
                    $success = 'ተማሪው ተመዝግቧል።';
                }
            }
            if (!$error) {
                $stmt = $pdo->prepare('SELECT * FROM students WHERE id = :id');
                $stmt->execute([':id' => $student_id]);
                $student = $stmt->fetch();
            }
        } catch (Exception $e) {
            $error = 'ስህተት: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title><?php echo $student_id ? 'ተማሪ ማስተካከል' : 'አዲስ ተማሪ ጨምር'; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; color: #333; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; }
        .navbar a { color: white; text-decoration: none; margin-right: 15px; }
        .container { max-width: 600px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h1 { margin-bottom: 20px; color: #667eea; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 5px rgba(102, 126, 234, 0.3); }
        button { width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; font-size: 16px; }
        button:hover { background: #764ba2; }
        .error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 5px; margin-bottom: 15px; }
        .success { background: #d4f1d8; color: #1d6a2b; padding: 12px; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="navbar">
    <h2><?php echo $student_id ? 'ተማሪ ማስተካከል' : 'አዲስ ተማሪ ጨምር'; ?></h2>
    <a href="admin_students.php">⇦ ወደ ተማሪዎች</a>
</div>

<div class="container">
    <div class="card">
        <?php if ($error): ?>
            <div class="error"><?php echo safe($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo safe($success); ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="name">ስም *</label>
                <input type="text" id="name" name="name" value="<?php echo safe($name); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">ኢሜይል *</label>
                <input type="email" id="email" name="email" value="<?php echo safe($email); ?>" required>
            </div>
            <div class="form-group">
                <label for="student_id">የተማሪ መለያ ቁጥር *</label>
                <input type="text" id="student_id" name="student_id" value="<?php echo safe($student_code); ?>" required>
            </div>
            <div class="form-group">
                <label for="country">አገር</label>
                <input type="text" id="country" name="country" value="<?php echo safe($country); ?>" placeholder="ኢትዮጵያ">
            </div>
            <div class="form-group">
                <label for="city">ከተማ</label>
                <input type="text" id="city" name="city" value="<?php echo safe($city); ?>" placeholder="አዲስ አበባ">
            </div>
            <div class="form-group">
                <label for="password"><?php echo $student_id ? 'የይለፍ ቃል ማስተካከል (ከፈለጉ)' : 'የይለፍ ቃል *'; ?></label>
                <input type="password" id="password" name="password" <?php echo $student_id ? '' : 'required'; ?>>
            </div>
            <button type="submit"><?php echo $student_id ? 'ማስተካከል' : 'ጨምር'; ?></button>
        </form>
    </div>
</div>
</body>
</html>
