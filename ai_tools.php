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
        $courseId = (int)($_GET['course_id'] ?? 0);
        $questionCount = max(3, min(10, (int)($_GET['questions'] ?? 5)));
        if ($courseId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid course identifier.']);
            exit;
        }

        $quiz = generateCourseQuiz($pdo, $courseId, $questionCount);
        logAiActivity($pdo, $studentId, 'generate_quiz', $courseId, json_encode(['question_count' => $questionCount, 'questions' => count($quiz)]));
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

function getRecommendedCourses(PDO $pdo, string $studentId, int $limit = 5): array
{
    $studentId = trim($studentId);
    if ($studentId === '') {
        return [];
    }

    $enrolledCourseIds = [];
    $preferredCategories = [];
    $preferredLevels = [];

    $stmt = $pdo->prepare('SELECT c.id, c.course_name, c.category, c.level FROM courses c JOIN registrations r ON r.course_id = c.id WHERE r.student_id = :student_id');
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

function generateCourseQuiz(PDO $pdo, int $courseId, int $questionCount): array
{
    $courseStmt = $pdo->prepare('SELECT course_name, category, level FROM courses WHERE id = :id LIMIT 1');
    $courseStmt->execute([':id' => $courseId]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);

    $lessonStmt = $pdo->prepare('SELECT title FROM course_lessons WHERE course_id = :course_id ORDER BY sort_order ASC, id ASC');
    $lessonStmt->execute([':course_id' => $courseId]);
    $lessonTitles = array_column($lessonStmt->fetchAll(PDO::FETCH_ASSOC), 'title');
    $lessonTitles = array_filter(array_map('trim', $lessonTitles));
    $lessonTitles = array_values(array_unique($lessonTitles));

    $questions = [];
    $topicPool = $lessonTitles;
    if (empty($topicPool)) {
        $topicPool = [trim((string)$course['category']) ?: 'core course topics'];
    }

    for ($i = 0; $i < $questionCount; $i++) {
        $baseTopic = $topicPool[$i % count($topicPool)];
        $wrongOptions = [];
        $answer = $baseTopic;
        $declaredOptions = [$answer];

        $availableWrong = array_values(array_filter($topicPool, static function ($topic) use ($answer) {
            return $topic !== $answer;
        }));
        shuffle($availableWrong);
        while (count($wrongOptions) < 3 && !empty($availableWrong)) {
            $wrongOptions[] = array_shift($availableWrong);
        }

        $genericDistractors = [
            'Course setup and enrollment steps',
            'Exam preparation strategies',
            'General student support information',
            'Classroom participation rules',
        ];
        foreach ($genericDistractors as $distractor) {
            if (count($wrongOptions) >= 3) {
                break;
            }
            if (!in_array($distractor, $wrongOptions, true) && $distractor !== $answer) {
                $wrongOptions[] = $distractor;
            }
        }

        $options = array_slice(array_merge([$answer], $wrongOptions), 0, 4);
        shuffle($options);

        $questions[] = [
            'question' => "Which of the following is most likely a key topic in the lesson titled '{$baseTopic}'?",
            'type' => 'multiple_choice',
            'options' => $options,
            'answer' => $answer,
        ];
    }

    return [
        'course_name' => trim((string)($course['course_name'] ?? 'Selected course')),
        'category' => trim((string)($course['category'] ?? '')), 
        'level' => trim((string)($course['level'] ?? '')), 
        'questions' => $questions,
    ];
}
