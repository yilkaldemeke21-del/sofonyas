<?php
/**
 * Simple Google Sheets helper using a service account.
 * Requirements:
 * - `composer require google/apiclient:^2.0`
 * - set env `GOOGLE_SERVICE_ACCOUNT_JSON` to path of service account JSON
 * - set env `GOOGLE_SHEETS_ID` to your spreadsheet ID
 * - (optional) set env `GOOGLE_SHEETS_NAME` to sheet name (default: ExamSubmissions)
 */

$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    throw new \RuntimeException("Dependencies not found. Run `composer require google/apiclient:^2.0` in the project root to install dependencies. Missing: $autoload");
}
require_once $autoload;

function appendExamSubmissionToSheet(array $payload)
{
    $jsonPath = getenv('GOOGLE_SERVICE_ACCOUNT_JSON') ?: __DIR__ . '/service-account.json';
    if (!file_exists($jsonPath)) {
        return ['success' => false, 'message' => 'Service account JSON not found at ' . $jsonPath];
    }

    $spreadsheetId = getenv('GOOGLE_SHEETS_ID') ?: '';
    if (!$spreadsheetId) {
        return ['success' => false, 'message' => 'GOOGLE_SHEETS_ID not configured'];
    }

    $sheetName = getenv('GOOGLE_SHEETS_NAME') ?: 'ExamSubmissions';

    $client = new Google_Client();
    $client->setAuthConfig($jsonPath);
    $client->setApplicationName('Sofny Exam Submissions');
    $client->setScopes([Google_Service_Sheets::SPREADSHEETS]);

    $service = new Google_Service_Sheets($client);

    $values = [[
        $payload['submitted_at'] ?? date('c'),
        $payload['name'] ?? '',
        $payload['email'] ?? '',
        $payload['student_id'] ?? '',
        $payload['exam_title'] ?? '',
        $payload['access_code'] ?? '',
        $payload['score'] ?? '',
        $payload['total_questions'] ?? '',
        $payload['answers'] ?? '',
        $payload['remarks'] ?? '',
        $payload['source'] ?? 'website',
    ]];

    $body = new Google_Service_Sheets_ValueRange(['values' => $values]);
    $params = ['valueInputOption' => 'USER_ENTERED'];

    try {
        $result = $service->spreadsheets_values->append($spreadsheetId, $sheetName . '!A1:K', $body, $params);
        return ['success' => true, 'message' => 'Appended to sheet', 'result' => $result];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

?>
