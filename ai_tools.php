<?php
session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to use AI learning tools.']);
    exit;
}

$studentId = (string)$_SESSION['student_id'];

function getRequestValue(string $key, $default = null)
{
    if (isset($_REQUEST[$key])) {
        return $_REQUEST[$key];
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (is_array($data) && array_key_exists($key, $data)) {
            return $data[$key];
        }
    }

    return $default;
}

$action = trim((string)(getRequestValue('action') ?? ''));

function normalizeCourseText(?string $value): string
{
    if ($value === null) {
        return '';
    }

    $text = html_entity_decode((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $text = preg_replace('/<[^>]+>/', ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

function tokenizeText(string $value): array
{
    $normalized = mb_strtolower($value, 'UTF-8');
    $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized);
    $tokens = preg_split('/\s+/u', trim((string)$normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $stopWords = ['the','and','for','with','from','that','this','your','about','into','what','when','where','why','how','have','has','can','will','should','their','lesson','course','chapter','topic','study','learn','student','students','using','help','please','know','give','answer','details','detail'];
    return array_values(array_filter($tokens, static function ($token) use ($stopWords): bool {
        return strlen($token) > 2 && !in_array($token, $stopWords, true);
    }));
}

function extractPdfText(string $pdfPath): string
{
    $normalizedPath = str_replace('\\', '/', trim($pdfPath));
    if ($normalizedPath === '' || !is_file($normalizedPath)) {
        return '';
    }

    $command = 'pdftotext -layout ' . escapeshellarg($normalizedPath) . ' - 2>/dev/null';
    $output = [];
    $exitCode = 0;
    @exec($command, $output, $exitCode);
    if ($exitCode === 0 && !empty($output)) {
        return normalizeCourseText(implode("\n", $output));
    }

    return '';
}

function getAiProviderConfig(): array
{
    $apiKey = trim((string)(getenv('OPENAI_API_KEY') ?: getenv('AZURE_OPENAI_API_KEY') ?: ''));
    $baseUrl = trim((string)(getenv('OPENAI_BASE_URL') ?: getenv('AZURE_OPENAI_ENDPOINT') ?: ''));
    if ($baseUrl === '') {
        $baseUrl = 'https://api.openai.com/v1';
    }
    $model = trim((string)(getenv('OPENAI_MODEL') ?: 'gpt-4o-mini'));

    return [
        'api_key' => $apiKey,
        'base_url' => $baseUrl,
        'model' => $model,
    ];
}

function buildRetrievedContextText(array $context, array $history = []): string
{
    $courseName = trim((string)($context['course']['course_name'] ?? ''));
    $lessonTitle = trim((string)($context['lesson_title'] ?? ''));
    $moduleName = trim((string)($context['module_name'] ?? ''));
    $courseDescription = trim((string)($context['course_description'] ?? ''));
    $lessonContent = trim((string)($context['lesson_content'] ?? ''));
    $notes = array_values(array_filter(array_map('trim', (array)($context['notes'] ?? [])))) ;
    $pdfTexts = array_values(array_filter(array_map('trim', (array)($context['pdf_texts'] ?? [])))) ;

    $sections = [];
    if ($courseName !== '') {
        $sections[] = "Course: {$courseName}";
    }
    if ($courseDescription !== '') {
        $sections[] = "Course description: {$courseDescription}";
    }
    if ($moduleName !== '') {
        $sections[] = "Chapter: {$moduleName}";
    }
    if ($lessonTitle !== '') {
        $sections[] = "Lesson: {$lessonTitle}";
    }
    if ($lessonContent !== '') {
        $sections[] = "Lesson content: {$lessonContent}";
    }
    foreach ($notes as $note) {
        $sections[] = "Note: {$note}";
    }
    foreach ($pdfTexts as $pdfText) {
        $sections[] = "PDF content: {$pdfText}";
    }

    if (!empty($history)) {
        $historyChunks = [];
        foreach ($history as $entry) {
            $role = trim((string)($entry['role'] ?? ''));
            $content = trim((string)($entry['content'] ?? ''));
            if ($role !== '' && $content !== '') {
                $historyChunks[] = ucfirst($role) . ': ' . $content;
            }
        }
        if (!empty($historyChunks)) {
            $sections[] = 'Conversation history: ' . implode("\n", $historyChunks);
        }
    }

    return implode("\n\n", $sections);
}

function callAiRagProvider(string $message, array $context, array $history = []): ?string
{
    if (empty($context['course'])) {
        return null;
    }

    $config = getAiProviderConfig();
    if ($config['api_key'] === '') {
        return null;
    }

    $contextText = buildRetrievedContextText($context, $history);
    if ($contextText === '') {
        return null;
    }

    $payload = [
        'model' => $config['model'],
        'temperature' => 0.1,
        'max_tokens' => 400,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a helpful LMS tutor. Answer only from the course materials provided to you. If the answer is not present in those materials, respond exactly: This information is not available in your current course materials.'
            ],
            [
                'role' => 'user',
                'content' => "Question: {$message}\n\nRetrieved course materials:\n{$contextText}"
            ],
        ],
    ];

    $url = rtrim($config['base_url'], '/') . '/chat/completions';
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['api_key'],
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody !== false && $responseBody !== '') {
            $decoded = json_decode($responseBody, true);
            if (is_array($decoded)) {
                $content = $decoded['choices'][0]['message']['content'] ?? '';
                if (is_string($content) && trim($content) !== '') {
                    return trim($content);
                }
                if (isset($decoded['error']['message']) && is_string($decoded['error']['message'])) {
                    error_log('AI provider error: ' . $decoded['error']['message']);
                }
            }
            if ($curlError !== '') {
                error_log('AI provider curl error: ' . $curlError);
            }
        }
    }

    return null;
}

function getStudentCourseContext(PDO $pdo, string $studentId, int $courseId, ?int $lessonId = null): ?array
{
    $studentId = trim($studentId);
    if ($studentId === '' || $courseId <= 0) {
        return null;
    }

    $courseStmt = $pdo->prepare('SELECT c.* FROM courses c JOIN registrations r ON (r.course_id = c.id OR r.course = c.course_name) WHERE r.student_id = :student_id AND c.id = :course_id LIMIT 1');
    $courseStmt->execute([':student_id' => $studentId, ':course_id' => $courseId]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
    if (!$course) {
        return null;
    }

    $lessonStmt = $pdo->prepare('SELECT cl.*, cm.name AS module_name FROM course_lessons cl LEFT JOIN course_modules cm ON cm.id = cl.module_id WHERE cl.course_id = :course_id ORDER BY COALESCE(cl.module_id, 999999), cl.sort_order ASC, cl.id ASC');
    $lessonStmt->execute([':course_id' => $courseId]);
    $lessons = $lessonStmt->fetchAll(PDO::FETCH_ASSOC);

    $selectedLesson = null;
    if ($lessonId !== null && $lessonId > 0) {
        foreach ($lessons as $lesson) {
            if ((int)$lesson['id'] === $lessonId) {
                $selectedLesson = $lesson;
                break;
            }
        }
    }

    if ($selectedLesson === null && !empty($lessons)) {
        $selectedLesson = $lessons[0];
    }

    $lessonContent = '';
    $moduleName = '';
    $lessonTitle = '';
    if ($selectedLesson) {
        $lessonContent = normalizeCourseText((string)($selectedLesson['content'] ?? ''));
        $moduleName = trim((string)($selectedLesson['module_name'] ?? ''));
        $lessonTitle = trim((string)($selectedLesson['title'] ?? ''));
    }

    $notesStmt = $pdo->prepare('SELECT title, description, file_path FROM admin_notes ORDER BY created_at DESC LIMIT 12');
    $notesStmt->execute();
    $notes = $notesStmt->fetchAll(PDO::FETCH_ASSOC);

    $noteTexts = [];
    foreach ($notes as $note) {
        $noteTitle = trim((string)($note['title'] ?? ''));
        $noteDescription = normalizeCourseText((string)($note['description'] ?? ''));
        $noteText = trim($noteTitle . ' ' . $noteDescription);
        if ($noteText !== '') {
            $noteTexts[] = $noteText;
        }
    }

    $pdfFiles = [];
    $pdfValue = trim((string)($course['pdf_file'] ?? ''));
    if ($pdfValue !== '') {
        $pdfFiles[] = $pdfValue;
    }

    $pdfTexts = [];
    foreach ($pdfFiles as $pdfFile) {
        $absolutePath = $pdfFile;
        if (strpos($pdfFile, '/') !== 0 && strpos($pdfFile, 'http') !== 0) {
            $absolutePath = __DIR__ . '/' . ltrim($pdfFile, '/');
        }
        $extracted = extractPdfText($absolutePath);
        if ($extracted !== '') {
            $pdfTexts[] = $extracted;
        }
    }

    return [
        'course' => $course,
        'lesson' => $selectedLesson,
        'module_name' => $moduleName,
        'lesson_title' => $lessonTitle,
        'lesson_content' => $lessonContent,
        'notes' => $noteTexts,
        'pdf_files' => $pdfFiles,
        'pdf_texts' => $pdfTexts,
        'course_description' => normalizeCourseText((string)($course['short_description'] ?: $course['description'] ?: '')),
    ];
}

function getConversationId(PDO $pdo, string $studentId, int $courseId, ?int $lessonId): int
{
    $studentId = trim($studentId);
    if ($studentId === '') {
        return 0;
    }

    $existingId = (int)($_SESSION['ai_tutor_conversation_id'] ?? 0);
    if ($existingId > 0) {
        return $existingId;
    }

    $stmt = $pdo->prepare('INSERT INTO ai_tutor_conversations (student_id, course_id, lesson_id) VALUES (:student_id, :course_id, :lesson_id)');
    $stmt->execute([
        ':student_id' => $studentId,
        ':course_id' => $courseId > 0 ? $courseId : null,
        ':lesson_id' => $lessonId !== null && $lessonId > 0 ? $lessonId : null,
    ]);
    $conversationId = (int)$pdo->lastInsertId();
    if ($conversationId > 0) {
        $_SESSION['ai_tutor_conversation_id'] = $conversationId;
    }
    return $conversationId;
}

function getConversationHistory(PDO $pdo, int $conversationId): array
{
    if ($conversationId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare('SELECT role, content FROM ai_tutor_messages WHERE conversation_id = :conversation_id ORDER BY id ASC LIMIT 20');
    $stmt->execute([':conversation_id' => $conversationId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function saveConversationMessage(PDO $pdo, int $conversationId, string $role, string $content): void
{
    if ($conversationId <= 0 || trim($content) === '') {
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO ai_tutor_messages (conversation_id, role, content) VALUES (:conversation_id, :role, :content)');
    $stmt->execute([
        ':conversation_id' => $conversationId,
        ':role' => $role,
        ':content' => $content,
    ]);
}

function buildAnswerFromContext(string $question, array $context, array $history = []): ?string
{
    $question = trim($question);
    if ($question === '') {
        return null;
    }

    $courseName = trim((string)($context['course']['course_name'] ?? ''));
    $lessonTitle = trim((string)($context['lesson_title'] ?? ''));
    $moduleName = trim((string)($context['module_name'] ?? ''));
    $courseDescription = trim((string)($context['course_description'] ?? ''));
    $lessonContent = trim((string)($context['lesson_content'] ?? ''));
    $notes = array_values(array_filter(array_map('trim', (array)($context['notes'] ?? []))));
    $pdfTexts = array_values(array_filter(array_map('trim', (array)($context['pdf_texts'] ?? []))));

    $segments = [];
    if ($courseName !== '') {
        $segments[] = ['title' => 'Course overview', 'content' => $courseName . ' ' . $courseDescription];
    }
    if ($lessonTitle !== '') {
        $segments[] = ['title' => 'Lesson title', 'content' => $lessonTitle . ' ' . $lessonContent];
    }
    if ($lessonContent !== '') {
        $segments[] = ['title' => 'Lesson content', 'content' => $lessonContent];
    }
    foreach ($notes as $note) {
        $segments[] = ['title' => 'Notes', 'content' => $note];
    }
    foreach ($pdfTexts as $pdfText) {
        $segments[] = ['title' => 'PDF content', 'content' => $pdfText];
    }

    $keywords = tokenizeText($question);
    if (empty($keywords)) {
        $keywords = tokenizeText($courseName . ' ' . $lessonTitle);
    }

    $bestSegment = null;
    $bestScore = 0;
    foreach ($segments as $segment) {
        $segmentText = $segment['title'] . ' ' . $segment['content'];
        $segmentTokens = tokenizeText($segmentText);
        $score = 0;
        foreach ($keywords as $keyword) {
            if (strpos(mb_strtolower($segmentText, 'UTF-8'), $keyword) !== false) {
                $score += 2;
            }
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestSegment = $segment;
        }
    }

    if ($bestSegment === null || $bestScore < 2) {
        return null;
    }

    $answerText = trim($bestSegment['content']);
    if ($answerText === '') {
        return null;
    }

    $excerpt = mb_substr($answerText, 0, 260, 'UTF-8');
    $contextPrefix = $courseName !== '' ? "Based on your enrolled course '{$courseName}'" : 'Based on your enrolled course';
    if ($moduleName !== '') {
        $contextPrefix .= " in chapter '{$moduleName}'";
    }
    if ($lessonTitle !== '') {
        $contextPrefix .= " and lesson '{$lessonTitle}'";
    }

    if (mb_strlen($answerText, 'UTF-8') > 260) {
        $answerText = $excerpt . '...';
    }

    return $contextPrefix . ', I can answer from the course materials: ' . $answerText;
}

if ($action === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing action parameter. Use one of: recommend_courses, generate_study_guide, generate_quiz. Example: ai_tools.php?action=recommend_courses',
        'available_actions' => ['recommend_courses', 'generate_study_guide', 'generate_quiz'],
    ]);
    exit;
}

switch ($action) {
    case 'recommend_courses':
        $recommendations = getRecommendedCourses($pdo, $studentId, 5);
        logAiActivity($pdo, $studentId, 'recommend_courses', null, json_encode(['count' => count($recommendations)]));
        echo json_encode(['success' => true, 'recommendations' => $recommendations]);
        break;

    case 'chat':
        $message = trim((string)(getRequestValue('message') ?? ''));
        if ($message === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please enter a question for the tutor.']);
            exit;
        }

        $courseId = (int)(getRequestValue('course_id') ?? $_GET['course_id'] ?? 0);
        $lessonId = (int)(getRequestValue('lesson_id') ?? $_GET['lesson_id'] ?? 0);
        $context = getStudentCourseContext($pdo, $studentId, $courseId, $lessonId > 0 ? $lessonId : null);
        if ($context === null) {
            $reply = 'I can only answer questions from the course materials of a course you are enrolled in. Please choose an enrolled course first.';
        } else {
            $conversationId = getConversationId($pdo, $studentId, $courseId, $lessonId > 0 ? $lessonId : null);
            $history = getConversationHistory($pdo, $conversationId);
            $reply = generateTutorReply($pdo, $studentId, $message, $context, $history);
            saveConversationMessage($pdo, $conversationId, 'user', $message);
            saveConversationMessage($pdo, $conversationId, 'assistant', $reply);
        }

        logAiActivity($pdo, $studentId, 'chat', $courseId > 0 ? $courseId : null, json_encode(['message' => $message]));
        echo json_encode(['success' => true, 'reply' => $reply]);
        break;

    case 'generate_study_guide':
        $courseId = (int)($_GET['course_id'] ?? 0);
        if ($courseId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid course identifier.']);
            exit;
        }

        $studyGuide = generateStudyGuide($pdo, $courseId, $_SESSION['student_name'] ?? 'Student');
        logAiActivity($pdo, $studentId, 'generate_study_guide', $courseId, json_encode(['title' => $studyGuide['title']]));
        echo json_encode(['success' => true, 'study_guide' => $studyGuide]);
        break;

    case 'generate_quiz':
        $courseId = (int)(getRequestValue('course_id') ?? $_GET['course_id'] ?? 0);
        $lessonId = (int)(getRequestValue('lesson_id') ?? $_GET['lesson_id'] ?? 0);
        $questionCount = max(3, min(10, (int)(getRequestValue('questions') ?? $_GET['questions'] ?? 5)));
        if ($courseId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid course identifier.']);
            exit;
        }

        $context = getStudentCourseContext($pdo, $studentId, $courseId, $lessonId > 0 ? $lessonId : null);
        if ($context === null) {
            echo json_encode(['success' => false, 'message' => 'Please select an enrolled course before generating a quiz.']);
            exit;
        }

        $quiz = generateCourseQuiz($pdo, $courseId, $questionCount, $lessonId > 0 ? $lessonId : null);
        logAiActivity($pdo, $studentId, 'generate_quiz', $courseId, json_encode(['question_count' => $questionCount, 'questions' => count($quiz['questions'] ?? [])]));
        echo json_encode(['success' => true, 'quiz' => $quiz]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . safe($action)]);
        break;
}

function logAiActivity(PDO $pdo, string $studentId, string $action, ?int $courseId = null, ?string $detail = null): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO student_ai_activity (student_id, action, course_id, detail) VALUES (:student_id, :action, :course_id, :detail)');
        $stmt->execute([
            ':student_id' => $studentId,
            ':action' => $action,
            ':course_id' => $courseId,
            ':detail' => $detail,
        ]);
    } catch (Throwable $e) {
        error_log('AI activity log failed: ' . $e->getMessage());
    }
}

function generateTutorReply(PDO $pdo, string $studentId, string $message, array $context, array $history = []): string
{
    $studentId = trim($studentId);
    $message = trim($message);

    if ($context === null || empty($context['course'])) {
        return 'I can only answer questions from the course materials of a course you are enrolled in. Please choose an enrolled course first.';
    }

    $historyText = '';
    foreach ($history as $entry) {
        $historyText .= trim((string)($entry['role'] ?? '')) . ': ' . trim((string)($entry['content'] ?? '')) . "\n";
    }

    if (preg_match('/^(hi|hello|hey|salut|salam)/i', $message)) {
        $courseName = trim((string)($context['course']['course_name'] ?? 'your current course'));
        return 'Hello! I can help you review ' . $courseName . ' using the current lesson, chapter, notes, and available course materials only.';
    }

    $providerReply = callAiRagProvider($message, $context, $history);
    if ($providerReply !== null && trim($providerReply) !== '') {
        return trim($providerReply);
    }

    $directAnswer = buildAnswerFromContext($message, $context, $history);
    if ($directAnswer !== null) {
        return $directAnswer;
    }

    if (preg_match('/quiz|test|practice/i', $message)) {
        return 'I can create a practice quiz from the current lesson only. Please use the quiz generator for that lesson so the questions stay grounded in your course materials.';
    }

    if (preg_match('/summary|outline|study guide|plan|schedule|timeline|day/i', $message)) {
        return 'I can support your study plan using the current lesson and attached notes, but I will only answer from the course materials for your enrolled course.';
    }

    return 'This information is not available in your current course materials.';
}

function getRecommendedCourses(PDO $pdo, string $studentId, int $limit = 5): array
{
    $studentId = trim($studentId);
    if ($studentId === '') {
        return [];
    }

    $enrolledCourseIds = [];
    $preferredCategories = [];
    $preferredLevels = [];

    $stmt = $pdo->prepare('SELECT DISTINCT c.id, c.course_name, c.category, c.level FROM courses c JOIN registrations r ON (r.course_id = c.id OR r.course = c.course_name) WHERE r.student_id = :student_id');
    $stmt->execute([':student_id' => $studentId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $enrolledCourseIds[] = (int)$row['id'];
        $category = trim((string)$row['category']);
        $level = trim((string)$row['level']);
        if ($category !== '') {
            $preferredCategories[$category] = ($preferredCategories[$category] ?? 0) + 1;
        }
        if ($level !== '') {
            $preferredLevels[$level] = ($preferredLevels[$level] ?? 0) + 1;
        }
    }

    $candidateSql = 'SELECT id, course_name, short_description, category, level, created_at FROM courses';
    if (!empty($enrolledCourseIds)) {
        $placeholders = implode(',', array_fill(0, count($enrolledCourseIds), '?'));
        $candidateSql .= ' WHERE id NOT IN (' . $placeholders . ')';
    }
    $candidateSql .= ' ORDER BY created_at DESC';

    $stmt = $pdo->prepare($candidateSql);
    if (!empty($enrolledCourseIds)) {
        $stmt->execute($enrolledCourseIds);
    } else {
        $stmt->execute();
    }

    $candidates = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $score = 0;
        $category = trim((string)$row['category']);
        $level = trim((string)$row['level']);

        if ($category !== '' && isset($preferredCategories[$category])) {
            $score += 20 + $preferredCategories[$category] * 2;
        }
        if ($level !== '' && isset($preferredLevels[$level])) {
            $score += 10 + $preferredLevels[$level];
        }
        if ($category === '') {
            $score -= 1;
        }
        if ($level === '') {
            $score -= 1;
        }

        $row['score'] = $score;
        $candidates[] = $row;
    }

    usort($candidates, static function ($a, $b) {
        if ($a['score'] === $b['score']) {
            return strcmp($b['created_at'], $a['created_at']);
        }
        return $b['score'] <=> $a['score'];
    });

    $output = [];
    foreach (array_slice($candidates, 0, $limit) as $course) {
        $output[] = [
            'id' => (int)$course['id'],
            'course_name' => $course['course_name'] ?? '',
            'short_description' => $course['short_description'] ?? '',
            'category' => $course['category'] ?? '',
            'level' => $course['level'] ?? '',
        ];
    }

    return $output;
}

function generateStudyGuide(PDO $pdo, int $courseId, string $studentName): array
{
    $courseStmt = $pdo->prepare('SELECT course_name, short_description, description, category, level FROM courses WHERE id = :id LIMIT 1');
    $courseStmt->execute([':id' => $courseId]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);

    $lessonStmt = $pdo->prepare('SELECT cl.title, cl.content, cm.name AS module_name FROM course_lessons cl LEFT JOIN course_modules cm ON cm.id = cl.module_id WHERE cl.course_id = :course_id ORDER BY COALESCE(cl.module_id, 999999), cl.sort_order ASC, cl.id ASC');
    $lessonStmt->execute([':course_id' => $courseId]);
    $lessons = $lessonStmt->fetchAll(PDO::FETCH_ASSOC);

    $courseName = trim((string)($course['course_name'] ?? 'This course'));
    $topics = [];
    foreach ($lessons as $lesson) {
        $title = trim((string)$lesson['title']);
        if ($title !== '') {
            $topics[] = $title;
        }
    }
    $topics = array_values(array_unique($topics));

    if (empty($topics)) {
        $description = trim((string)($course['short_description'] ?: $course['description']));
        $topics = $description !== '' ? array_slice(preg_split('/[\.|\?|!]/', $description), 0, 5) : ['Main course overview', 'Key learning objectives'];
    }

    $planDays = min(5, max(3, (int)ceil(count($topics) / 2)));
    $studyPlan = [];
    $topicChunks = array_chunk($topics, max(1, (int)ceil(count($topics) / $planDays)));
    foreach ($topicChunks as $index => $chunk) {
        $day = $index + 1;
        $studyPlan[] = "Day {$day}: Focus on " . implode(', ', $chunk) . ". Review these lessons and write one summary note for each topic.";
    }

    $learningObjectives = [
        "Understand the key ideas in {$courseName}",
        "Review the top topics using lesson summaries and examples",
        "Build confidence with targeted practice and self-quizzing",
    ];

    $revisionChecklist = [
        'Summarize each lesson in your own words.',
        'Highlight the most important terms and concepts.',
        'Try the course quiz after studying the lesson topics.',
        'Review any notes and repeat the plan until the ideas feel clear.',
    ];

    return [
        'title' => "Study Guide for {$courseName}",
        'introduction' => "Hello {$studentName}, this study guide is designed to help you learn {$courseName} step by step.",
        'course_summary' => trim((string)($course['short_description'] ?: $course['description'] ?: 'This guide helps you focus on the most important course topics.')),
        'learning_objectives' => $learningObjectives,
        'recommended_plan' => $studyPlan,
        'key_topics' => $topics,
        'revision_checklist' => $revisionChecklist,
        'tips' => [
            'Study consistently and take short breaks between lessons.',
            'Write notes in your own language to remember concepts better.',
            'Use the practice quiz to test what you retain.',
        ],
    ];
}

function generateCourseQuiz(PDO $pdo, int $courseId, int $questionCount, ?int $lessonId = null): array
{
    $courseStmt = $pdo->prepare('SELECT course_name, category, level FROM courses WHERE id = :id LIMIT 1');
    $courseStmt->execute([':id' => $courseId]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);

    $lessonStmt = $pdo->prepare('SELECT cl.id, cl.title, cl.content, cm.name AS module_name FROM course_lessons cl LEFT JOIN course_modules cm ON cm.id = cl.module_id WHERE cl.course_id = :course_id ORDER BY COALESCE(cl.module_id, 999999), cl.sort_order ASC, cl.id ASC');
    $lessonStmt->execute([':course_id' => $courseId]);
    $lessons = $lessonStmt->fetchAll(PDO::FETCH_ASSOC);

    $selectedLesson = null;
    if ($lessonId !== null && $lessonId > 0) {
        foreach ($lessons as $lesson) {
            if ((int)$lesson['id'] === $lessonId) {
                $selectedLesson = $lesson;
                break;
            }
        }
    }
    if ($selectedLesson === null && !empty($lessons)) {
        $selectedLesson = $lessons[0];
    }

    $lessonTitle = trim((string)($selectedLesson['title'] ?? 'Current lesson'));
    $lessonText = normalizeCourseText((string)($selectedLesson['content'] ?? ''));
    $segments = preg_split('/(?<=[.!?።])\s+/u', $lessonText, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $segments = array_values(array_filter(array_map('trim', $segments), static function ($segment): bool {
        return mb_strlen($segment, 'UTF-8') > 16;
    }));

    $questions = [];
    if (empty($segments)) {
        $segments = [$lessonTitle !== '' ? $lessonTitle : 'The key lesson topic'];
    }

    for ($i = 0; $i < $questionCount; $i++) {
        $answer = trim((string)($segments[$i % count($segments)] ?? $segments[0]));
        $answerExcerpt = mb_substr($answer, 0, 120, 'UTF-8');
        $wrongOptions = [];
        foreach ($segments as $segment) {
            if (count($wrongOptions) >= 3) {
                break;
            }
            if (trim((string)$segment) !== $answer && trim((string)$segment) !== '') {
                $wrongOptions[] = mb_substr(trim((string)$segment), 0, 120, 'UTF-8');
            }
        }
        if (count($wrongOptions) < 3) {
            $wrongOptions[] = 'Review the lesson notes and course summary';
            $wrongOptions[] = 'Ask the instructor for the answer';
            $wrongOptions[] = 'Look for the answer in the course PDF';
        }

        $options = array_slice(array_values(array_unique(array_merge([$answerExcerpt], $wrongOptions))), 0, 4);
        shuffle($options);

        $questions[] = [
            'question' => "Which option best matches the lesson focus for '{$lessonTitle}'?",
            'type' => 'multiple_choice',
            'options' => $options,
            'answer' => $answerExcerpt,
        ];
    }

    return [
        'course_name' => trim((string)($course['course_name'] ?? 'Selected course')),
        'category' => trim((string)($course['category'] ?? '')),
        'level' => trim((string)($course['level'] ?? '')),
        'questions' => $questions,
    ];
}
