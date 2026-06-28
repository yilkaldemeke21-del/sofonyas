<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$adminRole = $_SESSION['admin_role'] ?? 'admin';
if (!in_array($adminRole, ['admin', 'super_admin'], true)) {
    http_response_code(403);
    exit('ይህን ገጽ ለመጠቀም የአስተዳዳሪ ፈቃድ ያስፈልጋል።');
}

$error = '';
$success = '';
$lang = isset($_GET['lang']) ? $_GET['lang'] : ($_SESSION['lang'] ?? 'am');
if (!in_array($lang, ['am', 'en'], true)) {
    $lang = 'am';
}
$_SESSION['lang'] = $lang;
$view = 'questions';
if ((isset($_GET['view']) && $_GET['view'] === 'sections') || (isset($_POST['view']) && $_POST['view'] === 'sections')) {
    $view = 'sections';
}

$ui = [
    'am' => [
        'page_title' => 'የጥያቄ ማዕከል',
        'back' => '← ወደ ዳሽቦርድ',
        'hero_title' => 'Professional Question Management',
        'hero_text' => 'የጥያቄዎችን ክፍሎች እና ጥያቄዎች አንድ በአንድ ንፅፅር ይፍጠሩ እና ያስተዳድሩ።',
        'summary_sections' => 'ክፍሎች',
        'summary_questions' => 'ጥያቄዎች',
        'summary_latest' => 'የመጨረሻ ግብዓት',
        'tabs_sections' => 'ክፍሎችን አስተዳድር',
        'tabs_questions' => 'ጥያቄዎችን አስተዳድር',
        'section_form_title' => 'ክፍሎችን ጨምር',
        'section_form_desc' => 'ከተዛማጅ ጥያቄዎች ጋር አንድ ክፍል ይፍጠሩ እና ለተማሪዎች አጭር መመሪያ ይጻፉ።',
        'section_title' => 'የክፍል ርዕስ',
        'section_placeholder' => 'ለምሳሌ፦ ነገረ መለኮት',
        'instruction' => 'መመሪያ',
        'instruction_placeholder' => 'ለዚህ ክፍል አጭር መመሪያ ይጻፉ',
        'section_submit' => 'ክፍል ጨምር',
        'section_title_required' => 'እባክዎ የክፍል ርዕስ ያስገቡ።',
        'section_added' => 'ክፍል በተሳካ ሁኔታ ታክሏል።',
        'section_error' => 'ስህተት: ',
        'question_section_required' => 'እባክዎ የጥያቄ ክፍል ይምረጡ።',
        'question_required' => 'እባክዎ ጥያቄ ያስገቡ።',
        'options_required' => 'እባክዎ ሁለቱን መጀመሪያ አማራጮች ይሙሉ።',
        'all_options_required' => 'እባክዎ ሁሉንም አራት አማራጮች ይሙሉ።',
        'answer_required' => 'እባክዎ ትክክለኛ መልስ ያስገቡ።',
        'question_added' => 'ጥያቄው በተሳካ ሁኔታ ታክሏል።',
        'type_multiple_choice' => 'ብዙ ምርጫ',
        'type_true_false' => 'እውነት / ሀሰት',
        'type_fill_in_blank' => 'ባዶ ቦታ ሙሉ',
        'type_short_answer' => 'አጭር መልስ',
        'true_label' => 'እውነት',
        'false_label' => 'ሀሰት',
        'sections_list_title' => 'ነባር ክፍሎች',
        'sections_list_desc' => 'እነዚህን ክፍሎች በጥያቄ መጨመር ጊዜ ይጠቀሙ።',
        'empty_sections' => 'አሁን ምንም ክፍል የለም። መጀመሪያ ክፍል ይፍጠሩ እና ጥያቄዎችን ይደራጁ።',
        'question_form_title' => 'ጥያቄዎችን ጨምር',
        'question_form_desc' => 'አንድ ክፍል ይምረጡ እና ጥያቄዎችን በተፈለገው ቅርጸት ይጨምሩ።',
        'select_section' => 'ክፍል ይምረጡ',
        'select_section_placeholder' => 'ክፍል ይምረጡ',
        'no_instruction' => 'ምንም መመሪያ አልተሰጠም።',
        'select_section_button' => 'ክፍል ይምረጡ',
        'question_type' => 'የጥያቄ አይነት',
        'question_label' => 'ጥያቄ',
        'question_placeholder' => 'ጥያቄውን እዚህ ይጻፉ',
        'option_a' => 'ሀ',
        'option_b' => 'ለ',
        'option_c' => 'ሐ',
        'option_d' => 'መ',
        'correct_answer' => 'ትክክለኛ መልስ',
        'answer_label' => 'ትክክለኛ መልስ',
        'answer_placeholder' => 'የሚጠበቀውን መልስ ያስገቡ',
        'answer_help' => 'አጭር መልስ እና ባዶ ቦታ ሙሉ ጥያቄዎች ይህን መስክ እንደ የሚጠበቀው መልስ ይጠቀማሉ።',
        'question_submit' => 'ጥያቄ ጨምር',
        'recent_questions_title' => 'የቅርብ ጊዜ ጥያቄዎች',
        'recent_questions_desc' => 'በእያንዳንዱ ክፍል ላይ የተጨመሩትን የቅርብ ጊዜ ጥያቄዎች ይመልከቱ።',
        'empty_questions' => 'እስካሁን ምንም ጥያቄ አልተጨመረም። መጀመሪያ ጥያቄ ይፍጠሩ።',
        'show_more' => 'ተጨማሪ አሳይ',
        'hide' => 'ተሸጥ',
        'edit_question' => 'ጥያቄ አስተካከል',
        'question_type_label' => 'አይነት',
        'options_label' => 'አማራጮች',
        'correct_label' => 'ትክክለኛ መልስ',
        'unassigned' => 'ያልተመደበ',
        'sections' => 'ክፍሎች',
        'questions' => 'ጥያቄዎች',
        'ready' => 'ዝግጁ',
        'am_lang' => 'አማርኛ',
        'en_lang' => 'English',
        'section_label' => 'የክፍል ርዕስ',
    ],
    'en' => [
        'page_title' => 'Question Center',
        'back' => '← Back to Dashboard',
        'hero_title' => 'Professional Question Management',
        'hero_text' => 'Create and manage sections and questions in one clean workspace.',
        'summary_sections' => 'Sections',
        'summary_questions' => 'Questions',
        'summary_latest' => 'Latest Entry',
        'tabs_sections' => 'Manage Sections',
        'tabs_questions' => 'Manage Questions',
        'section_form_title' => 'Add Section',
        'section_form_desc' => 'Create a section for related questions and provide a short instruction for students.',
        'section_title' => 'Section Title',
        'section_placeholder' => 'e.g. Theology Foundations',
        'instruction' => 'Instruction',
        'instruction_placeholder' => 'Write a short instruction for this section',
        'section_submit' => 'Add Section',
        'section_title_required' => 'Please enter a section title.',
        'section_added' => 'Section added successfully.',
        'section_error' => 'Error: ',
        'question_section_required' => 'Please select a question section.',
        'question_required' => 'Please enter a question.',
        'options_required' => 'Please fill the first two options.',
        'all_options_required' => 'Please fill all four options.',
        'answer_required' => 'Please enter the correct answer.',
        'question_added' => 'Question added successfully.',
        'type_multiple_choice' => 'Multiple Choice',
        'type_true_false' => 'True / False',
        'type_fill_in_blank' => 'Fill in the Blank',
        'type_short_answer' => 'Short Answer',
        'true_label' => 'True',
        'false_label' => 'False',
        'sections_list_title' => 'Existing Sections',
        'sections_list_desc' => 'Use these sections when adding new questions.',
        'empty_sections' => 'No sections yet. Create your first section to start organizing questions.',
        'question_form_title' => 'Add Question',
        'question_form_desc' => 'Select a section and add a question in the format you need.',
        'select_section' => 'Select Section',
        'select_section_placeholder' => 'Choose a section',
        'no_instruction' => 'No instruction provided.',
        'select_section_button' => 'Select Section',
        'question_type' => 'Question Type',
        'question_label' => 'Question',
        'question_placeholder' => 'Write the question here',
        'option_a' => 'A',
        'option_b' => 'B',
        'option_c' => 'C',
        'option_d' => 'D',
        'correct_answer' => 'Correct Answer',
        'answer_label' => 'Correct Answer',
        'answer_placeholder' => 'Enter the expected answer',
        'answer_help' => 'Short Answer and Fill in the Blank questions use this field as the expected answer.',
        'question_submit' => 'Add Question',
        'recent_questions_title' => 'Recent Questions',
        'recent_questions_desc' => 'See the latest questions added to each section.',
        'empty_questions' => 'No questions yet. Create your first question to populate this area.',
        'show_more' => 'Show More',
        'hide' => 'Hide',
        'edit_question' => 'Edit Question',
        'question_type_label' => 'Type',
        'options_label' => 'Options',
        'correct_label' => 'Correct Answer',
        'unassigned' => 'Unassigned',
        'sections' => 'Sections',
        'questions' => 'Questions',
        'ready' => 'Ready',
        'am_lang' => 'አማርኛ',
        'en_lang' => 'English',
        'section_label' => 'Section Title',
    ],
];
$txt = $ui[$lang] ?? $ui['am'];
$questionTypeOptions = [
    'multiple_choice' => $txt['type_multiple_choice'],
    'true_false' => $txt['type_true_false'],
    'fill_in_blank' => $txt['type_fill_in_blank'],
    'short_answer' => $txt['type_short_answer'],
];
$answerChoices = [
    'A' => $txt['option_a'],
    'B' => $txt['option_b'],
    'C' => $txt['option_c'],
    'D' => $txt['option_d'],
];

function normalizeQuestionType($value) {
    $type = strtolower(trim((string)($value ?? 'multiple_choice')));
    $type = str_replace([' ', '_'], '', $type);

    if (in_array($type, ['multiplechoice', 'multiple_choice'], true)) {
        return 'multiple_choice';
    }
    if (in_array($type, ['truefalse', 'true_false', 'boolean'], true)) {
        return 'true_false';
    }
    if (in_array($type, ['fillinblank', 'fill_in_blank', 'blankspace', 'blank_space'], true)) {
        return 'fill_in_blank';
    }
    if (in_array($type, ['shortanswer', 'short_answer'], true)) {
        return 'short_answer';
    }
    return 'multiple_choice';
}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS question_sections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        instruction TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {
}

try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_type VARCHAR(30) NOT NULL DEFAULT "multiple_choice",
        question_text TEXT NOT NULL,
        option_a VARCHAR(255) NOT NULL DEFAULT "",
        option_b VARCHAR(255) NOT NULL DEFAULT "",
        option_c VARCHAR(255) NOT NULL DEFAULT "",
        option_d VARCHAR(255) NOT NULL DEFAULT "",
        correct_answer VARCHAR(255) NOT NULL DEFAULT "",
        section_id INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_questions_section (section_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (PDOException $e) {
}

try {
    $pdo->exec('ALTER TABLE questions ADD COLUMN section_id INT DEFAULT NULL');
} catch (PDOException $e) {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_section'])) {
        $sectionTitle = trim((string)($_POST['section_title'] ?? ''));
        $instruction = trim((string)($_POST['instruction'] ?? ''));

        if ($sectionTitle === '') {
            $error = $txt['section_title_required'];
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO question_sections (title, instruction) VALUES (:title, :instruction)');
                $stmt->execute([
                    ':title' => $sectionTitle,
                    ':instruction' => $instruction,
                ]);
                $success = $txt['section_added'];
            } catch (PDOException $e) {
                $error = $txt['section_error'] . $e->getMessage();
            }
        }
    } elseif (isset($_POST['add_question'])) {
        $questionType = normalizeQuestionType($_POST['question_type'] ?? 'multiple_choice');
        $questionText = trim((string)($_POST['question'] ?? ''));
        $sectionId = (int)($_POST['section_id'] ?? 0);

        // allow section_id = 0 to mean "auto-assign": fill the most recent section up to 10 questions,
        // otherwise create a new section automatically
        if ($sectionId < 0) {
            $error = $txt['question_section_required'];
        } elseif ($sectionId === 0) {
            try {
                $lastStmt = $pdo->query('SELECT id FROM question_sections ORDER BY id DESC LIMIT 1');
                $last = $lastStmt->fetch(PDO::FETCH_ASSOC);
                if ($last) {
                    $countStmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM questions WHERE section_id = :id');
                    $countStmt->execute([':id' => (int)$last['id']]);
                    $cnt = (int)($countStmt->fetchColumn() ?? 0);
                    if ($cnt >= 10) {
                        $title = 'Auto Section ' . date('YmdHis');
                        $ins = $pdo->prepare('INSERT INTO question_sections (title, instruction) VALUES (:title, :instruction)');
                        $ins->execute([':title' => $title, ':instruction' => 'Auto-generated section']);
                        $sectionId = (int)$pdo->lastInsertId();
                    } else {
                        $sectionId = (int)$last['id'];
                    }
                } else {
                    $title = 'Auto Section ' . date('YmdHis');
                    $ins = $pdo->prepare('INSERT INTO question_sections (title, instruction) VALUES (:title, :instruction)');
                    $ins->execute([':title' => $title, ':instruction' => 'Auto-generated section']);
                    $sectionId = (int)$pdo->lastInsertId();
                }
            } catch (PDOException $e) {
                $error = $txt['section_error'] . $e->getMessage();
            }
        }

        if (empty($error) && $sectionId <= 0) {
            $error = $txt['question_section_required'];
        }
        elseif ($questionText === '') {
            $error = $txt['question_required'];
        } elseif ($questionType === 'multiple_choice' || $questionType === 'true_false') {
            $a = trim((string)($_POST['a'] ?? ''));
            $b = trim((string)($_POST['b'] ?? ''));
            $c = trim((string)($_POST['c'] ?? ''));
            $d = trim((string)($_POST['d'] ?? ''));
            $correct = strtoupper(trim((string)($_POST['correct'] ?? 'A')));

            if ($questionType === 'true_false') {
                $a = 'እውነት';
                $b = 'ሀሰት';
                $c = '';
                $d = '';
                $correct = strtoupper(trim((string)($_POST['correct'] ?? 'TRUE')));
            }

            if ($a === '' || $b === '') {
                $error = $txt['options_required'];
            } elseif ($questionType === 'multiple_choice' && ($c === '' || $d === '')) {
                $error = $txt['all_options_required'];
            } else {
                try {
                    $stmt = $pdo->prepare('INSERT INTO questions (question_type, question_text, option_a, option_b, option_c, option_d, correct_answer, section_id) VALUES (:question_type, :question_text, :a, :b, :c, :d, :correct, :section_id)');
                    $stmt->execute([
                        ':question_type' => $questionType,
                        ':question_text' => $questionText,
                        ':a' => $a,
                        ':b' => $b,
                        ':c' => $c,
                        ':d' => $d,
                        ':correct' => $correct,
                        ':section_id' => $sectionId,
                    ]);
                    $success = $txt['question_added'];
                } catch (PDOException $e) {
                    $error = $txt['section_error'] . $e->getMessage();
                }
            }
        } else {
            $answer = trim((string)($_POST['answer'] ?? ''));
            if ($answer === '') {
                $error = $txt['answer_required'];
            } else {
                try {
                    $stmt = $pdo->prepare('INSERT INTO questions (question_type, question_text, option_a, option_b, option_c, option_d, correct_answer, section_id) VALUES (:question_type, :question_text, :answer, :blank1, :blank2, :blank3, :correct_answer, :section_id)');
                    $stmt->execute([
                        ':question_type' => $questionType,
                        ':question_text' => $questionText,
                        ':answer' => $answer,
                        ':blank1' => '',
                        ':blank2' => '',
                        ':blank3' => '',
                        ':correct_answer' => $answer,
                        ':section_id' => $sectionId,
                    ]);
                    $success = $txt['question_added'];
                } catch (PDOException $e) {
                    $error = $txt['section_error'] . $e->getMessage();
                }
            }
        }
    }
}

$stmt = $pdo->query('SELECT qs.*, COUNT(q.id) AS question_count FROM question_sections qs LEFT JOIN questions q ON q.section_id = qs.id GROUP BY qs.id ORDER BY qs.created_at DESC');
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query('SELECT COUNT(*) as total FROM question_sections');
$sectionCount = (int)($stmt->fetch()['total'] ?? 0);

$stmt = $pdo->query('SELECT COUNT(*) as total FROM questions');
$questionCount = (int)($stmt->fetch()['total'] ?? 0);

$stmt = $pdo->query('SELECT q.*, s.title as section_title FROM questions q LEFT JOIN question_sections s ON s.id = q.section_id ORDER BY q.created_at DESC LIMIT 8');
$recentQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo safe($lang === 'en' ? 'en' : 'am'); ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo safe($txt['page_title']); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #f5f7ff 0%, #eef4ff 100%); color: #0f172a; }
        .navbar { background: linear-gradient(135deg, #4f46e5 0%, #2563eb 100%); color: white; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .navbar a { color: white; text-decoration: none; margin-right: 14px; }
        .lang-switch { display: inline-flex; gap: 8px; align-items: center; }
        .lang-switch a { background: rgba(255,255,255,0.16); padding: 6px 10px; border-radius: 999px; font-size: 13px; }
        .lang-switch a.active { background: white; color: #2563eb; }
        .container { max-width: 1200px; margin: 24px auto; padding: 0 20px 40px; }
        .hero { background: white; border-radius: 18px; padding: 24px; box-shadow: 0 16px 35px rgba(15, 23, 42, 0.08); margin-bottom: 18px; }
        .hero h1 { color: #1d4ed8; margin-bottom: 6px; }
        .hero p { color: #475569; line-height: 1.7; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin: 18px 0 22px; }
        .summary-card { background: #f8fbff; border: 1px solid #dbeafe; border-radius: 14px; padding: 14px; }
        .summary-card h3 { font-size: 13px; color: #64748b; margin-bottom: 6px; }
        .summary-card .value { font-size: 26px; font-weight: 800; color: #2563eb; }
        .tabs { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }
        .tab-btn { display: inline-block; padding: 10px 14px; border-radius: 999px; background: #e2e8f0; color: #334155; text-decoration: none; font-weight: 700; }
        .tab-btn.active { background: #2563eb; color: white; }
        .grid { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 18px; }
        .panel-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06); }
        .panel-card h2 { margin-bottom: 12px; color: #1e3a8a; font-size: 20px; }
        .panel-card p { color: #64748b; margin-bottom: 14px; line-height: 1.6; }
        .form-group { margin-bottom: 14px; }
        label { display: block; margin-bottom: 6px; font-weight: 700; color: #334155; }
        input, textarea, select { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; }
        textarea { min-height: 90px; resize: vertical; }
        button { width: 100%; padding: 12px 14px; border: none; border-radius: 10px; background: linear-gradient(135deg, #2563eb, #4f46e5); color: white; font-weight: 800; cursor: pointer; }
        button:hover { background: linear-gradient(135deg, #1d4ed8, #4338ca); }
        .help-box { background: #eef4ff; border-left: 4px solid #2563eb; border-radius: 10px; padding: 10px 12px; color: #334155; font-size: 13px; line-height: 1.6; margin-top: 8px; }
        .error, .success { padding: 12px 14px; border-radius: 10px; margin-bottom: 14px; font-weight: 700; }
        .error { background: #fef2f2; color: #b91c1c; }
        .success { background: #ecfdf5; color: #166534; }
        .list { display: flex; flex-direction: column; gap: 10px; margin-top: 12px; }
        .list-item { border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; background: #fbfdff; }
        .list-item strong { display: block; margin-bottom: 4px; color: #0f172a; }
        .muted { color: #64748b; font-size: 13px; }
        .list-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
        .mini-btn { display: inline-block; padding: 7px 10px; border-radius: 999px; text-decoration: none; font-size: 13px; font-weight: 700; border: 1px solid #cbd5e1; background: white; color: #334155; cursor: pointer; }
        .mini-btn.primary { background: linear-gradient(135deg, #2563eb, #4f46e5); color: white; border-color: #2563eb; }
        .mini-btn.secondary { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .section-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .section-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 18px; box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06); cursor: pointer; transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease; }
        .section-card:hover { transform: translateY(-2px); border-color: #2563eb; box-shadow: 0 16px 30px rgba(37, 99, 235, 0.12); }
        .section-card.active { border-color: #2563eb; background: #eff6ff; }
        .section-card h3 { margin: 10px 0 8px; font-size: 18px; color: #1e3a8a; }
        .section-card p { color: #475569; font-size: 13px; line-height: 1.6; margin-bottom: 12px; }
        .section-meta { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; }
        .question-extra { display: none; margin-top: 8px; padding: 10px 12px; border-radius: 10px; background: #f8fafc; border: 1px solid #e2e8f0; font-size: 13px; color: #334155; line-height: 1.6; }
        .question-extra.show { display: block; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="navbar">
    <h2><?php echo safe($txt['page_title']); ?></h2>
    <div style="display:flex; align-items:center; flex-wrap:wrap; gap:8px;">
        <div class="lang-switch">
            <a class="<?php echo $lang === 'am' ? 'active' : ''; ?>" href="admin_add_question.php?lang=am&view=<?php echo urlencode($view); ?>"><?php echo safe($txt['am_lang']); ?></a>
            <a class="<?php echo $lang === 'en' ? 'active' : ''; ?>" href="admin_add_question.php?lang=en&view=<?php echo urlencode($view); ?>"><?php echo safe($txt['en_lang']); ?></a>
        </div>
        <a href="admin_dashboard.php"><?php echo safe($txt['back']); ?></a>
    </div>
</div>
<div class="container">
    <div class="hero">
        <h1><?php echo safe($txt['hero_title']); ?></h1>
        <p><?php echo safe($txt['hero_text']); ?></p>
        <div class="summary-grid">
            <div class="summary-card">
                <h3><?php echo safe($txt['summary_sections']); ?></h3>
                <div class="value"><?php echo (int)$sectionCount; ?></div>
            </div>
            <div class="summary-card">
                <h3><?php echo safe($txt['summary_questions']); ?></h3>
                <div class="value"><?php echo (int)$questionCount; ?></div>
            </div>
            <div class="summary-card">
                <h3><?php echo safe($txt['summary_latest']); ?></h3>
                <div class="value"><?php echo safe($txt['ready']); ?></div>
            </div>
        </div>
        <div class="tabs">
            <a class="tab-btn <?php echo $view === 'sections' ? 'active' : ''; ?>" href="admin_add_question.php?lang=<?php echo urlencode($lang); ?>&view=sections"><?php echo safe($txt['tabs_sections']); ?></a>
            <a class="tab-btn <?php echo $view === 'questions' ? 'active' : ''; ?>" href="admin_add_question.php?lang=<?php echo urlencode($lang); ?>&view=questions"><?php echo safe($txt['tabs_questions']); ?></a>
        </div>
        <?php if ($error): ?><div class="error"><?php echo safe($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?php echo safe($success); ?></div><?php endif; ?>
    </div>

    <div class="grid">
        <?php if ($view === 'sections'): ?>
            <div class="panel-card">
                <h2><?php echo safe($txt['section_form_title']); ?></h2>
                <p><?php echo safe($txt['section_form_desc']); ?></p>
                <form method="post">
                    <input type="hidden" name="add_section" value="1">
                    <input type="hidden" name="lang" value="<?php echo safe($lang); ?>">
                    <input type="hidden" name="view" value="sections">
                    <div class="form-group">
                        <label for="section_title"><?php echo safe($txt['section_title']); ?></label>
                        <input id="section_title" name="section_title" type="text" placeholder="<?php echo safe($txt['section_placeholder']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="instruction"><?php echo safe($txt['instruction']); ?></label>
                        <textarea id="instruction" name="instruction" placeholder="<?php echo safe($txt['instruction_placeholder']); ?>"></textarea>
                    </div>
                    <button type="submit"><?php echo safe($txt['section_submit']); ?></button>
                </form>
            </div>
            <div class="panel-card">
                <h2><?php echo safe($txt['sections_list_title']); ?></h2>
                <p><?php echo safe($txt['sections_list_desc']); ?></p>
                <?php if (!empty($sections)): ?>
                    <div class="list">
                        <?php foreach ($sections as $section): ?>
                            <div class="list-item">
                                <strong><?php echo safe($section['title'] ?? ''); ?></strong>
                                <div class="muted"><?php echo safe($section['instruction'] ?: ($lang === 'am' ? 'ምንም መመሪያ አልተሰጠም።' : 'No instruction provided.')); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="help-box"><?php echo safe($txt['empty_sections']); ?></div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="panel-card">
                <h2><?php echo safe($txt['question_form_title']); ?></h2>
                <p><?php echo safe($txt['question_form_desc']); ?></p>
                <?php if (!empty($sections)): ?>
                    <div class="section-grid">
                        <?php foreach ($sections as $section): ?>
                            <div class="section-card" data-section-id="<?php echo (int)$section['id']; ?>" onclick="selectSection(<?php echo (int)$section['id']; ?>)">
                                <span class="pill info">Section</span>
                                <h3><?php echo safe($section['title'] ?? 'Untitled'); ?></h3>
                                <p><?php echo safe($section['instruction'] ?: $txt['no_instruction']); ?></p>
                                <div class="section-meta">
                                    <span class="pill success"><?php echo (int)($section['question_count'] ?? 0); ?> <?php echo safe($txt['questions']); ?></span>
                                    <button type="button" class="mini-btn secondary" onclick="event.stopPropagation(); selectSection(<?php echo (int)$section['id']; ?>)"><?php echo safe($txt['select_section_button']); ?></button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="help-box"><?php echo safe($txt['empty_sections']); ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="add_question" value="1">
                    <input type="hidden" name="lang" value="<?php echo safe($lang); ?>">
                    <input type="hidden" name="view" value="questions">
                    <div class="form-group">
                        <label for="section_id"><?php echo safe($txt['select_section']); ?></label>
                        <select id="section_id" name="section_id" required>
                            <option value=""><?php echo safe($txt['select_section_placeholder']); ?></option>
                            <option value="0">Auto-assign (fill sections up to 10 questions)</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo (int)($section['id'] ?? 0); ?>"><?php echo safe($section['title'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="question_type"><?php echo safe($txt['question_type']); ?></label>
                        <select id="question_type" name="question_type" onchange="toggleQuestionType(this.value)">
                            <?php foreach ($questionTypeOptions as $value => $label): ?>
                                <option value="<?php echo safe($value); ?>"><?php echo safe($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="question"><?php echo safe($txt['question_label']); ?></label>
                        <textarea id="question" name="question" placeholder="<?php echo safe($txt['question_placeholder']); ?>" required></textarea>
                    </div>
                    <div id="mcqFields">
                        <div class="form-group">
                            <label for="a" id="labelA"><?php echo safe($txt['option_a']); ?></label>
                            <input id="a" type="text" name="a" placeholder="<?php echo safe($txt['option_a']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="b" id="labelB"><?php echo safe($txt['option_b']); ?></label>
                            <input id="b" type="text" name="b" placeholder="<?php echo safe($txt['option_b']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="c"><?php echo safe($txt['option_c']); ?></label>
                            <input id="c" type="text" name="c" placeholder="<?php echo safe($txt['option_c']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="d"><?php echo safe($txt['option_d']); ?></label>
                            <input id="d" type="text" name="d" placeholder="<?php echo safe($txt['option_d']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="correct"><?php echo safe($txt['correct_answer']); ?></label>
                            <select id="correct" name="correct">
                                <?php foreach ($answerChoices as $value => $label): ?>
                                    <option value="<?php echo safe($value); ?>"><?php echo safe($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div id="shortAnswerFields" style="display:none;">
                        <div class="form-group">
                            <label for="answer"><?php echo safe($txt['answer_label']); ?></label>
                            <input id="answer" type="text" name="answer" placeholder="<?php echo safe($txt['answer_placeholder']); ?>">
                            <div class="help-box"><?php echo safe($txt['answer_help']); ?></div>
                        </div>
                    </div>
                    <button type="submit"><?php echo safe($txt['question_submit']); ?></button>
                </form>
            </div>
            <div class="panel-card">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                    <div>
                        <h2><?php echo safe($txt['recent_questions_title']); ?></h2>
                        <p><?php echo safe($txt['recent_questions_desc']); ?></p>
                    </div>
                    <button type="button" id="toggleAllQuestionsBtn" class="mini-btn secondary" onclick="toggleAllQuestionDetails()"><?php echo safe($txt['show_more']); ?> All</button>
                </div>
                <?php if (!empty($recentQuestions)): ?>
                    <div class="list">
                        <?php foreach ($recentQuestions as $question): ?>
                            <div class="list-item">
                                <strong><?php echo safe($question['question_text'] ?? ''); ?></strong>
                                <div class="muted"><?php echo safe($txt['sections'] . ': ' . ($question['section_title'] ?: $txt['unassigned'])); ?> • <?php echo safe($txt['questions'] . ': ' . ($question['question_type'] ?? 'multiple_choice')); ?></div>
                                <div class="list-actions">
                                    <button type="button" class="mini-btn secondary" onclick="toggleQuestionDetails('q-<?php echo (int)$question['id']; ?>', this)"><?php echo safe($txt['show_more']); ?></button>
                                    <a href="admin_edit_question.php?id=<?php echo (int)$question['id']; ?>" class="mini-btn primary"><?php echo safe($txt['edit_question']); ?></a>
                                </div>
                                <div id="q-<?php echo (int)$question['id']; ?>" class="question-extra">
                                    <div><strong><?php echo safe($txt['question_type_label']); ?>:</strong> <?php echo safe($question['question_type'] ?? 'multiple_choice'); ?></div>
                                    <?php if (($question['question_type'] ?? 'multiple_choice') === 'short_answer' || ($question['question_type'] ?? 'multiple_choice') === 'fill_in_blank'): ?>
                                        <div><strong><?php echo safe($txt['correct_label']); ?>:</strong> <?php echo safe($question['correct_answer'] ?: $question['option_a']); ?></div>
                                    <?php else: ?>
                                        <div><strong><?php echo safe($txt['options_label']); ?>:</strong> A. <?php echo safe($question['option_a'] ?? ''); ?> • B. <?php echo safe($question['option_b'] ?? ''); ?><?php if (($question['option_c'] ?? '') !== ''): ?> • C. <?php echo safe($question['option_c'] ?? ''); ?><?php endif; ?><?php if (($question['option_d'] ?? '') !== ''): ?> • D. <?php echo safe($question['option_d'] ?? ''); ?><?php endif; ?></div>
                                        <div><strong><?php echo safe($txt['correct_label']); ?>:</strong> <?php echo safe($question['correct_answer'] ?? ''); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="help-box"><?php echo safe($txt['empty_questions']); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
function toggleQuestionDetails(id, button) {
    const panel = document.getElementById(id);
    if (!panel) {
        return;
    }
    const isShown = panel.classList.toggle('show');
    if (button) {
        button.textContent = isShown ? '<?php echo safe($txt['hide']); ?>' : '<?php echo safe($txt['show_more']); ?>';
    }
}

function selectSection(sectionId) {
    const select = document.getElementById('section_id');
    const cards = document.querySelectorAll('.section-card');
    if (select) {
        select.value = sectionId;
        select.focus();
    }
    cards.forEach(card => {
        card.classList.toggle('active', card.dataset.sectionId === String(sectionId));
    });
}

function toggleAllQuestionDetails() {
    const panels = document.querySelectorAll('.question-extra');
    const button = document.getElementById('toggleAllQuestionsBtn');
    if (!panels.length || !button) {
        return;
    }

    const anyHidden = Array.from(panels).some((panel) => !panel.classList.contains('show'));
    panels.forEach((panel) => {
        panel.classList.toggle('show', anyHidden);
        const toggleBtn = document.querySelector(`button[onclick*="${panel.id}"]`);
        if (toggleBtn) {
            toggleBtn.textContent = anyHidden ? '<?php echo safe($txt['hide']); ?>' : '<?php echo safe($txt['show_more']); ?>';
        }
    });
    button.textContent = anyHidden ? '<?php echo safe($txt['hide']); ?> All' : '<?php echo safe($txt['show_more']); ?> All';
}

function toggleQuestionType(type) {
    const mcq = document.getElementById('mcqFields');
    const shortAnswer = document.getElementById('shortAnswerFields');
    const labelA = document.getElementById('labelA');
    const labelB = document.getElementById('labelB');
    const correct = document.getElementById('correct');
    const a = document.getElementById('a');
    const b = document.getElementById('b');
    const c = document.getElementById('c');
    const d = document.getElementById('d');
    const answer = document.getElementById('answer');
    const isTextType = type === 'short_answer' || type === 'fill_in_blank';
    const trueLabel = <?php echo json_encode($txt['true_label'], JSON_UNESCAPED_UNICODE); ?>;
    const falseLabel = <?php echo json_encode($txt['false_label'], JSON_UNESCAPED_UNICODE); ?>;
    const optionA = <?php echo json_encode($txt['option_a'], JSON_UNESCAPED_UNICODE); ?>;
    const optionB = <?php echo json_encode($txt['option_b'], JSON_UNESCAPED_UNICODE); ?>;
    const optionC = <?php echo json_encode($txt['option_c'], JSON_UNESCAPED_UNICODE); ?>;
    const optionD = <?php echo json_encode($txt['option_d'], JSON_UNESCAPED_UNICODE); ?>;

    if (isTextType) {
        mcq.style.display = 'none';
        shortAnswer.style.display = 'block';
        a.removeAttribute('required');
        b.removeAttribute('required');
        c.removeAttribute('required');
        d.removeAttribute('required');
        answer.setAttribute('required', 'required');
        return;
    }

    mcq.style.display = 'block';
    shortAnswer.style.display = 'none';
    a.setAttribute('required', 'required');
    b.setAttribute('required', 'required');
    c.setAttribute('required', 'required');
    d.setAttribute('required', 'required');
    answer.removeAttribute('required');

    if (type === 'true_false') {
        labelA.textContent = trueLabel;
        labelB.textContent = falseLabel;
        a.value = trueLabel;
        b.value = falseLabel;
        correct.innerHTML = '<option value="TRUE">' + trueLabel + '</option><option value="FALSE">' + falseLabel + '</option>';
    } else {
        labelA.textContent = optionA;
        labelB.textContent = optionB;
        a.value = '';
        b.value = '';
        c.value = '';
        d.value = '';
        correct.innerHTML = '<option value="A">' + optionA + '</option><option value="B">' + optionB + '</option><option value="C">' + optionC + '</option><option value="D">' + optionD + '</option>';
    }
}

toggleQuestionType(document.getElementById('question_type').value);

document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('section_id');
    if (!select) {
        return;
    }
    const sectionId = select.value;
    const cards = document.querySelectorAll('.section-card');
    cards.forEach(card => {
        card.classList.toggle('active', card.dataset.sectionId === sectionId);
    });
});
</script>
</body>
</html>