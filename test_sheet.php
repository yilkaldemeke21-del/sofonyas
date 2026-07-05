<?php
require_once __DIR__ . '/google_sheets_service.php';

$payload = [
    'submitted_at' => date('c'),
    'name' => 'Local Test Student',
    'email' => 'test@example.com',
    'student_id' => 'TS-001',
    'exam_title' => 'Unit Test',
    'access_code' => 'TEST',
    'score' => 100,
    'total_questions' => 100,
    'answers' => json_encode([]),
    'remarks' => 'automated test',
    'source' => 'test-script',
];

$res = appendExamSubmissionToSheet($payload);
echo "Result:\n";
print_r($res);

?>
