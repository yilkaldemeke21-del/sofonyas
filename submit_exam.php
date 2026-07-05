<?php
session_start();
require_once __DIR__ . '/db.php';

$googleScriptUrl = getenv('GOOGLE_SHEETS_EXAM_WEBHOOK') ?: 'https://script.google.com/macros/s/YOUR_SCRIPT_ID/exec';
$success = false;
$error = '';
$submittedData = [
    'name' => $_SESSION['student_name'] ?? '',
    'email' => $_SESSION['student_email'] ?? '',
    'student_id' => $_SESSION['student_id'] ?? '',
    'exam_title' => '',
    'access_code' => '',
    'score' => '',
    'total_questions' => '',
    'answers' => '',
    'remarks' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedData = [
        'name' => trim($_POST['name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'student_id' => trim($_POST['student_id'] ?? ''),
        'exam_title' => trim($_POST['exam_title'] ?? ''),
        'access_code' => trim($_POST['access_code'] ?? ''),
        'score' => trim($_POST['score'] ?? ''),
        'total_questions' => trim($_POST['total_questions'] ?? ''),
        'answers' => trim($_POST['answers'] ?? ''),
        'remarks' => trim($_POST['remarks'] ?? ''),
    ];

    if ($submittedData['name'] === '' || $submittedData['email'] === '' || $submittedData['exam_title'] === '' || $submittedData['access_code'] === '') {
        $error = 'ፈተና ለመሙላት ስም፣ ኢሜይል፣ የፈተና ርእስና የይዘት ኮድ አስፈላጊ ናቸው።';
    } else {
        $payload = [
            'name' => $submittedData['name'],
            'email' => $submittedData['email'],
            'student_id' => $submittedData['student_id'],
            'exam_title' => $submittedData['exam_title'],
            'access_code' => $submittedData['access_code'],
            'score' => $submittedData['score'],
            'total_questions' => $submittedData['total_questions'],
            'answers' => $submittedData['answers'],
            'remarks' => $submittedData['remarks'],
            'submitted_at' => date('Y-m-d H:i:s'),
            'source' => 'website',
        ];

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $response = null;

        // Prefer server-side service account when configured
        $serviceJson = getenv('GOOGLE_SERVICE_ACCOUNT_JSON') ?: '';
        $sheetId = getenv('GOOGLE_SHEETS_ID') ?: '';
        if ($serviceJson && $sheetId && file_exists($serviceJson)) {
            require_once __DIR__ . '/google_sheets_service.php';
            $serviceResult = appendExamSubmissionToSheet($payload);
            if (is_array($serviceResult) && !empty($serviceResult['success'])) {
                $success = true;
                $submittedData = [];
            } else {
                $error = 'Service account sync failed: ' . ($serviceResult['message'] ?? 'unknown');
                error_log('Service account sync error: ' . print_r($serviceResult, true));
            }
        } else {
            // Fallback to Apps Script webhook
            if (function_exists('curl_init')) {
                $ch = curl_init($googleScriptUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $responseBody = curl_exec($ch);
                $curlErr = curl_error($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($responseBody === false || $httpCode < 200 || $httpCode >= 300) {
                    $error = 'የGoogle Sheets መረጃ ማስገባት አልተሳካም። እባክዎ ድጋሚ ይሞክሩ።';
                    if ($curlErr) {
                        error_log('Google Sheets submit curl error: ' . $curlErr);
                    }
                } else {
                    $response = json_decode($responseBody, true);
                }
            } else {
                $context = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/json\r\n",
                        'content' => $jsonPayload,
                        'timeout' => 10,
                    ],
                ]);
                $responseBody = @file_get_contents($googleScriptUrl, false, $context);
                if ($responseBody === false) {
                    $error = 'የGoogle Sheets መረጃ ማስገባት አልተሳካም በገና።';
                } else {
                    $response = json_decode($responseBody, true);
                }
            }

            if (!$error && is_array($response) && isset($response['success']) && $response['success'] === true) {
                $success = true;
                $submittedData = [];
            } elseif (!$error) {
                $error = $response['message'] ?? 'Google Sheets የተላከ መልስ የተሳናይ ነው።';
                error_log('Google Sheets submit response: ' . print_r($response, true));
            }
        }

        if (!$error && is_array($response) && isset($response['success']) && $response['success'] === true) {
            $success = true;
            $submittedData = [];
        } elseif (!$error) {
            $error = $response['message'] ?? 'Google Sheets የተላከ መልስ የተሳናይ ነው።';
            error_log('Google Sheets submit response: ' . print_r($response, true));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ፈተና ማስገባት - Google Sheet</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f3f4f6; color: #111827; }
        .container { max-width: 680px; margin: 36px auto; padding: 24px; background: #ffffff; border-radius: 18px; box-shadow: 0 16px 40px rgba(15,23,42,0.12); }
        h1 { margin-top: 0; color:#1d4ed8; }
        label { display:block; margin-top:16px; font-weight:700; color:#334155; }
        input, textarea, select { width:100%; padding:12px 14px; margin-top:8px; border:1px solid #cbd5e1; border-radius:12px; font-size:15px; }
        textarea { min-height:140px; resize:vertical; }
        .button { margin-top:22px; padding:14px 20px; border:none; border-radius:14px; background: linear-gradient(135deg,#2563eb,#4f46e5); color:#fff; font-weight:700; font-size:15px; cursor:pointer; }
        .button:hover { opacity:.95; }
        .message { border-radius: 14px; padding: 16px; margin-bottom: 18px; }
        .success { background:#e0f2fe; color:#0f172a; border:1px solid #93c5fd; }
        .error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
        .hint { margin-top:14px; font-size:14px; color:#475569; }
        a.link { color:#2563eb; text-decoration:none; }
    </style>
</head>
<body>
<div class="container">
    <h1>ፈተና ወደ Google Sheets መስጠት</h1>
    <p class="hint">ይህ ቅጽ የፈተና መረጃዎትን ወደ Google Sheet ይላካል።</p>

    <?php if ($success): ?>
        <div class="message success">እናት! መረጃዎ በጥሩ ሁኔታ ወደ Google Sheets ተላከ።</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="post" action="submit_exam.php">
        <label for="name">ስም</label>
        <input id="name" name="name" type="text" value="<?php echo htmlspecialchars($submittedData['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>

        <label for="email">ኢሜይል</label>
        <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($submittedData['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>

        <label for="student_id">የተማሪ መታወቂያ</label>
        <input id="student_id" name="student_id" type="text" value="<?php echo htmlspecialchars($submittedData['student_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <label for="exam_title">የፈተና ርእስ</label>
        <input id="exam_title" name="exam_title" type="text" value="<?php echo htmlspecialchars($submittedData['exam_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>

        <label for="access_code">የመግቢያ ኮድ</label>
        <input id="access_code" name="access_code" type="text" value="<?php echo htmlspecialchars($submittedData['access_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>

        <label for="score">ውጤት (ፍሰኛ)</label>
        <input id="score" name="score" type="number" min="0" value="<?php echo htmlspecialchars($submittedData['score'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <label for="total_questions">አጠቃላይ ጥያቄዎች</label>
        <input id="total_questions" name="total_questions" type="number" min="0" value="<?php echo htmlspecialchars($submittedData['total_questions'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <label for="answers">መልሶች / እባኮች</label>
        <textarea id="answers" name="answers"><?php echo htmlspecialchars($submittedData['answers'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>

        <label for="remarks">ማስታወሻ</label>
        <textarea id="remarks" name="remarks"><?php echo htmlspecialchars($submittedData['remarks'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>

        <button class="button" type="submit">ላክ</button>
    </form>

    <p class="hint">እባክዎ ለGoogle Sheets የወደዱ እና ፍጥነት እንዲሆን እንደገና ይሙሉ።</p>
    <p class="hint">የGoogle Apps Script የWeb App URL እንዲያደርጉ ከፍ አድርጉ።</p>
</div>
</body>
</html>
