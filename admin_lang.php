<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$admin_lang = $_GET['lang'] ?? $_SESSION['admin_lang'] ?? 'am';
if (!in_array($admin_lang, ['am', 'en'], true)) {
    $admin_lang = 'am';
}
$_SESSION['admin_lang'] = $admin_lang;

$redirectTarget = trim($_GET['redirect'] ?? '');
if ($redirectTarget !== '') {
    $redirectTarget = ltrim($redirectTarget, '/');
    $redirectTarget = str_replace('..', '', $redirectTarget);
    if (preg_match('/\.(php|html?)$/i', $redirectTarget) === 1) {
        header('Location: ' . $redirectTarget);
        exit;
    }
}

header('Location: admin_dashboard.php');
exit;

function admin_text($key, $lang = null) {
    static $translations = [
        'dashboard_title' => ['am' => 'አስተዳዳሪ ዳሽቦርድ', 'en' => 'Admin Dashboard'],
        'welcome' => ['am' => 'እንኳን ደህና መጡ', 'en' => 'Welcome'],
        'back' => ['am' => 'ወደ ዳሽቦርድ ተመለስ', 'en' => 'Back to dashboard'],
        'add_news' => ['am' => 'አዲስ ዜና ጨምር', 'en' => 'Add New News'],
        'add_blog' => ['am' => 'ብሎግ ጨምር', 'en' => 'Add Blog'],
        'add_announcement' => ['am' => 'ማስታወቂያ ጨምር', 'en' => 'Add Announcement'],
        'manage_exam_reminders' => ['am' => 'የፈተና ማስታወሻ አስተዳደር', 'en' => 'Exam Reminder Manager'],
        'title_label' => ['am' => 'ርዕስ', 'en' => 'Title'],
        'content_label' => ['am' => 'ይዘት', 'en' => 'Content'],
        'link_label' => ['am' => 'ማስፈንጠሪያ ሊንክ (አማራጭ)', 'en' => 'Link (optional)'],
        'required_message' => ['am' => 'ርዕስ እና ይዘት ማስገባት አለብዎት።', 'en' => 'Title and content are required.'],
        'save_news' => ['am' => 'ዜና አስቀምጥ', 'en' => 'Save News'],
        'save_blog' => ['am' => 'ብሎግ አስቀምጥ', 'en' => 'Save Blog'],
        'save_announcement' => ['am' => 'ማስታወቂያ አስቀምጥ', 'en' => 'Save Announcement'],
        'update' => ['am' => 'አስተካክል', 'en' => 'Update'],
        'save' => ['am' => 'አስቀምጥ', 'en' => 'Save'],
        'edit_content' => ['am' => 'ይዘት አስተካክል', 'en' => 'Edit Content'],
        'date_label' => ['am' => 'ቀን', 'en' => 'Date'],
        'student_id_label' => ['am' => 'ተቀባይ ይምረጡ', 'en' => 'Choose Recipient'],
        'all_students' => ['am' => 'ሁሉም ተማሪዎች', 'en' => 'All Students'],
        'exam_type_label' => ['am' => 'የፈተና አይነት', 'en' => 'Exam Type'],
        'mid_exam' => ['am' => 'መካከለኛ ፈተና', 'en' => 'Mid Exam'],
        'final_exam' => ['am' => 'ፍተሻ ፈተና', 'en' => 'Final Exam'],
        'short_exam' => ['am' => 'አጭር ፈተና', 'en' => 'Short Exam'],
        'exam_date_label' => ['am' => 'የፈተና ቀን', 'en' => 'Exam Date'],
        'reminder_message_label' => ['am' => 'ማስታወሻ / መመሪያ', 'en' => 'Reminder / Instructions'],
        'reminder_placeholder' => ['am' => 'ለተማሪዎች የፈተና መመሪያ ወይም ማስታወሻ ይጻፉ', 'en' => 'Write exam instructions or a reminder for students'],
        'reminder_hint' => ['am' => 'ይህ መልእክት ተማሪዎች ዳሽቦርድ ላይ ይታያል።', 'en' => 'This message will appear on the student dashboard.'],
        'add_reminder' => ['am' => 'ማስታወሻ አክል', 'en' => 'Add Reminder'],
        'update_reminder' => ['am' => 'ማስታወሻ አስተካክል', 'en' => 'Update Reminder'],
        'existing_reminders' => ['am' => 'የተመዘገቡ ማስታወሻዎች', 'en' => 'Saved Reminders'],
        'no_reminders' => ['am' => 'ምንም ማስታወሻ የለም', 'en' => 'No reminders yet'],
        'student' => ['am' => 'ተማሪ', 'en' => 'Student'],
        'exam' => ['am' => 'ፈተና', 'en' => 'Exam'],
        'message' => ['am' => 'መልእክት', 'en' => 'Message'],
        'actions' => ['am' => 'እርምጃ', 'en' => 'Actions'],
        'edit' => ['am' => 'አስተካክል', 'en' => 'Edit'],
        'language_switch' => ['am' => 'English', 'en' => 'አማርኛ'],
        'reminder_intro' => ['am' => 'የተማሪዎች ፈተና ዕቅድ እና ማስታወሻ መልእክት እዚህ ያስቀምጡ።', 'en' => 'Plan exams and save reminder notes for students here.'],
        'dashboard_intro' => ['am' => 'የዲያቆን ሶፎንያስ ዌቭሳይት ደመቀ(የቤተ ገብርኤል አጠቃላይ መረጃ)', 'en' => 'Diacon Sofonias Website Dashboard (Church Administration Overview)'],
    ];

    if ($lang === null) {
        $lang = $_SESSION['admin_lang'] ?? 'am';
    }

    return $translations[$key][$lang] ?? $translations[$key]['am'] ?? $key;
}

function admin_switch_url($currentPage = null) {
    $page = $currentPage ?: basename($_SERVER['PHP_SELF']);
    $params = $_GET;
    $params['lang'] = ($_SESSION['admin_lang'] ?? 'am') === 'am' ? 'en' : 'am';
    return $page . '?' . http_build_query($params);
}
?>
