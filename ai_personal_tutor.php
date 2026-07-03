<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit;
}

$studentId = (string)$_SESSION['student_id'];
$stmt = $pdo->prepare('SELECT name, email FROM students WHERE student_id = :student_id LIMIT 1');
$stmt->execute([':student_id' => $studentId]);
$student = $stmt->fetch();
$studentName = trim((string)($student['name'] ?? $student['student_name'] ?? 'Student'));
$studentEmail = trim((string)($student['email'] ?? ''));

$stmt = $pdo->prepare('SELECT c.id, c.course_name FROM courses c JOIN registrations r ON r.course_id = c.id WHERE r.student_id = :student_id ORDER BY r.created_at DESC LIMIT 8');
$stmt->execute([':student_id' => $studentId]);
$enrolledCourses = $stmt->fetchAll();

$totalCourses = count($enrolledCourses);
$stmt = $pdo->prepare('SELECT COUNT(*) AS completed_lessons FROM lesson_progress WHERE student_id = :student_id');
$stmt->execute([':student_id' => $studentId]);
$completedLessons = (int)($stmt->fetch()['completed_lessons'] ?? 0);

$stmt = $pdo->prepare('SELECT AVG(score) AS avg_score FROM quiz_results WHERE student_id = :student_id');
$stmt->execute([':student_id' => $studentId]);
$avgQuizScore = (int)round((float)($stmt->fetch()['avg_score'] ?? 0));

$advice = 'ጥያቄዎችን ለማቅረብ፣ እባኮትን የኮርስ ስም ይመረጡ ወይም ጀምር ቦታዎን ይግለጹ።';
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Personal Tutor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            --bg: #f8fafc;
            --surface: #ffffff;
            --surface-strong: #eef2ff;
            --surface-soft: #eef2ff;
            --text: #0f172a;
            --muted: #475569;
            --primary: #2563eb;
            --secondary: #7c3aed;
            --border: #d1d5db;
            --shadow: 0 24px 70px rgba(37, 99, 235, 0.12);
        }
        body {
            background: linear-gradient(180deg, #eff6ff 0%, #f8fafc 100%);
            color: var(--text);
            margin: 0;
            padding: 0;
        }
        .page-shell {
            max-width: 1180px;
            margin: 0 auto;
            padding: 24px;
        }
        .hero-card {
            border-radius: 28px;
            background: linear-gradient(135deg, #ffffff, #eef2ff);
            border: 1px solid rgba(37, 99, 235, 0.12);
            box-shadow: var(--shadow);
            padding: 30px;
        }
        .hero-title {
            font-size: clamp(2rem, 2.6vw, 3.4rem);
            margin-bottom: 12px;
            letter-spacing: -0.04em;
        }
        .hero-subtitle {
            max-width: 720px;
            margin-bottom: 22px;
            color: var(--muted);
            font-size: 1.05rem;
            line-height: 1.8;
        }
        .hero-actions .btn {
            min-width: 180px;
            border-radius: 999px;
            padding: 12px 22px;
        }
        .feature-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 24px;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
        }
        .feature-card h3 {
            margin-top: 0;
            margin-bottom: 12px;
            color: #1d4ed8;
        }
        .metrics-grid {
            display: grid;
            gap: 18px;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            margin-top: 24px;
        }
        .metric-box {
            border-radius: 20px;
            background: #ffffff;
            border: 1px solid rgba(37, 99, 235, 0.12);
            padding: 22px;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.05);
        }
        .metric-box h4 {
            margin: 0 0 10px;
            font-size: 0.95rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .metric-box strong {
            display: block;
            margin-top: 10px;
            font-size: 2rem;
            color: var(--text);
        }
        .tutor-panel {
            background: #ffffff;
            border-radius: 24px;
            border: 1px solid rgba(148, 163, 184, 0.24);
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.08);
            padding: 24px;
            margin-top: 24px;
        }
        .tutor-panel h3 {
            margin-top: 0;
            margin-bottom: 12px;
            color: #111827;
        }
        .chat-history {
            max-height: 340px;
            overflow-y: auto;
            border-radius: 18px;
            background: #f8fafc;
            padding: 18px;
            border: 1px solid rgba(148, 163, 184, 0.24);
        }
        .chat-message {
            padding: 14px 18px;
            border-radius: 18px;
            line-height: 1.65;
            max-width: 86%;
            margin-bottom: 14px;
        }
        .chat-message.student {
            align-self: flex-end;
            background: linear-gradient(135deg, #2563eb, #4f46e5);
            color: white;
        }
        .chat-message.ai {
            align-self: flex-start;
            background: #e0f2fe;
            color: #0f172a;
        }
        .chat-footer {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            margin-top: 18px;
        }
        .chat-input {
            width: 100%;
            min-height: 52px;
            border-radius: 18px;
            border: 1px solid #cbd5e1;
            padding: 14px 16px;
            font-size: 1rem;
            resize: vertical;
        }
        .chat-send {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            color: white;
            border-radius: 18px;
            padding: 14px 20px;
            font-weight: 700;
            box-shadow: 0 14px 24px rgba(37, 99, 235, 0.22);
        }
        .card-grid {
            display: grid;
            gap: 22px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-top: 22px;
        }
        .card-grid .feature-card {
            margin: 0;
        }
        .response-panel {
            background: #f8fafc;
            border-radius: 18px;
            padding: 18px;
            border: 1px solid rgba(148, 163, 184, 0.24);
            min-height: 160px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
        .btn-secondary-outline {
            border: 1px solid rgba(37, 99, 235, 0.18);
            color: #2563eb;
            background: white;
        }
        @media (max-width: 900px) {
            .card-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="page-shell">
    <div class="hero-card">
        <div class="row align-items-center gy-4">
            <div class="col-lg-7">
                <span class="badge bg-primary text-white px-3 py-2 mb-3">የAI የግል መማር ጓደኛ</span>
                <h1 class="hero-title">Your AI Personal Tutor</h1>
                <p class="hero-subtitle">ይህ ገጽ ቀላል እና የሞያ የAI ትምህርት እርዳታ እንዲሰጥዎ የተነደፈ ነው። ኮርሶች ተመራማሪ፣ ትምህርት እቅድ፣ እና ፈተና ጥያቄዎችን ይዘው እየተማሩ እርስዎን በቀጥታ እና በስራ ይመራሉ።</p>
                <div class="hero-actions d-flex flex-wrap gap-3">
                    <button class="btn btn-primary btn-lg" type="button" onclick="document.getElementById('tutorChatInput').focus();">Start a Tutor Chat</button>
                    <button class="btn btn-outline-primary btn-lg" type="button" onclick="scrollToSection('studyGuidePanel');">Generate Study Guide</button>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="feature-card">
                    <h3>የእርስዎ ትምህርት ማስታወቂያ</h3>
                    <p class="muted">ማስተላለፊያዎችን ለማግኘት እና ለመማረክ እጅግ በቀላሉ ተጠቃሚ ነው።</p>
                    <ul class="list-unstyled">
                        <li class="mb-3"><strong>✔</strong> የኮርስ ምክር እና ጥቆማ ማድረግ</li>
                        <li class="mb-3"><strong>✔</strong> ስርየት የእውቀት እቅድ እና ጥያቄዎች</li>
                        <li class="mb-3"><strong>✔</strong> ቀጣይ እርምጃ እና ምክር ማስተካከል</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="metrics-grid">
            <div class="metric-box">
                <h4>Enrolled Courses</h4>
                <strong><?php echo $totalCourses; ?></strong>
            </div>
            <div class="metric-box">
                <h4>Completed Lessons</h4>
                <strong><?php echo $completedLessons; ?></strong>
            </div>
            <div class="metric-box">
                <h4>Average Quiz Score</h4>
                <strong><?php echo $avgQuizScore; ?>%</strong>
            </div>
            <div class="metric-box">
                <h4>Advisor Tip</h4>
                <strong><?php echo safe($advice); ?></strong>
            </div>
        </div>
    </div>

    <div class="tutor-panel" id="tutorChatPanel">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h3>Ask Your AI Tutor</h3>
                <p class="muted">ምንም ጥያቄ ከሆነ ይጠይቁ፣ የሚረዱ ምክሮች ይሰጣሉ።</p>
            </div>
            <span class="badge bg-secondary text-white py-2 px-3">እንኳን ደህና መጡ, <?php echo safe($studentName); ?></span>
        </div>
        <div class="chat-history d-flex flex-column" id="chatHistory">
            <div class="chat-message ai">ሰላም! እባክዎን ከAI የግል ተማሪ አገልግሎት ጋር የሚፈልጉትን ጥያቄ ይጻፉ።</div>
        </div>
        <div class="chat-footer mt-3">
            <textarea id="tutorChatInput" class="chat-input" placeholder="Ask about courses, study plans, quizzes, or progress..."></textarea>
            <button class="chat-send" type="button" id="tutorSendButton">Send</button>
        </div>
    </div>

    <div class="card-grid">
        <div class="feature-card" id="studyGuidePanel">
            <h3>Generate a Personalized Study Guide</h3>
            <p class="muted">ስለ ኮርሱ ጥቅም ያለው የማስታወቂያ ዕቅድ እንዲሰጥዎ ይረዳል።</p>
            <div class="mb-3">
                <label for="studyGuideCourse" class="form-label">Select course</label>
                <select id="studyGuideCourse" class="form-select">
                    <option value="">-- Select an enrolled course --</option>
                    <?php foreach ($enrolledCourses as $course): ?>
                        <option value="<?php echo (int)$course['id']; ?>"><?php echo safe($course['course_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="button" id="generateStudyGuideButton">Generate Study Guide</button>
            <div class="response-panel mt-4" id="studyGuideResponse">Your study guide will appear here.</div>
        </div>

        <div class="feature-card">
            <h3>Build a Practice Quiz</h3>
            <p class="muted">እረፍት ከውጤታማ ጥያቄዎች ጋር ልምድ ይገንዘቡ።</p>
            <div class="mb-3">
                <label for="quizCourse" class="form-label">Select course</label>
                <select id="quizCourse" class="form-select">
                    <option value="">-- Select an enrolled course --</option>
                    <?php foreach ($enrolledCourses as $course): ?>
                        <option value="<?php echo (int)$course['id']; ?>"><?php echo safe($course['course_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="quizCount" class="form-label">Question count</label>
                <input type="number" id="quizCount" class="form-control" min="3" max="12" value="5">
            </div>
            <button class="btn btn-outline-primary" type="button" id="generateQuizButton">Generate Quiz</button>
            <div class="response-panel mt-4" id="quizResponse">Practice quiz results will display here.</div>
        </div>
    </div>

    <div class="feature-card mt-4">
        <h3>Top AI Tutor Recommendations</h3>
        <p class="muted">እንዴት ከመማር ይበልጣሉ፣ ኮርሶችን እና ትምህርት ሥርዓትን ያሳያሉ።</p>
        <div id="recommendationsList" class="row gy-3 mt-3"></div>
    </div>
</div>

<script>
    const chatHistory = document.getElementById('chatHistory');
    const chatInput = document.getElementById('tutorChatInput');
    const sendButton = document.getElementById('tutorSendButton');
    const recommendationsList = document.getElementById('recommendationsList');
    const studyGuideResponse = document.getElementById('studyGuideResponse');
    const quizResponse = document.getElementById('quizResponse');
    const baseUrl = 'ai_tools.php';

    const createBubble = (text, type) => {
        const bubble = document.createElement('div');
        bubble.className = `chat-message ${type}`;
        bubble.textContent = text;
        return bubble;
    };

    const appendChat = (text, type = 'ai') => {
        const bubble = createBubble(text, type);
        chatHistory.appendChild(bubble);
        chatHistory.scrollTop = chatHistory.scrollHeight;
    };

    const sendTutorChat = async () => {
        const message = chatInput.value.trim();
        if (!message) return;
        appendChat(message, 'student');
        chatInput.value = '';
        appendChat('Working on your custom response...', 'ai');

        try {
            const response = await fetch(`${baseUrl}?action=chat&message=${encodeURIComponent(message)}`);
            const data = await response.json();
            const aiBubble = chatHistory.querySelector('.chat-message.ai:last-child');
            if (data.success && data.reply) {
                aiBubble.textContent = data.reply;
            } else {
                aiBubble.textContent = data.message || 'Unable to reach AI tutor right now.';
            }
        } catch (error) {
            appendChat('ይቅርታ፣ አንዲኛው ሴርቨር እንደተሰበረ ተገነዘበ።', 'ai');
        }
    };

    const loadRecommendations = async () => {
        recommendationsList.innerHTML = '<div class="col-12"><div class="response-panel">Loading recommendations…</div></div>';
        try {
            const response = await fetch(`${baseUrl}?action=recommend_courses`);
            const data = await response.json();
            if (data.success && Array.isArray(data.recommendations) && data.recommendations.length > 0) {
                recommendationsList.innerHTML = '';
                data.recommendations.slice(0, 6).forEach(course => {
                    const card = document.createElement('div');
                    card.className = 'col-md-4';
                    card.innerHTML = `
                        <div class="feature-card">
                            <h5>${course.course_name}</h5>
                            <p class="muted">${course.short_description || 'No description available.'}</p>
                            <span class="badge bg-primary">${course.category || 'Recommended'}</span>
                            <span class="badge bg-secondary ms-1">${course.level || 'Any level'}</span>
                        </div>`;
                    recommendationsList.appendChild(card);
                });
            } else {
                recommendationsList.innerHTML = '<div class="col-12"><div class="response-panel">No personalized recommendations were found. Try enrolling in a course first.</div></div>';
            }
        } catch (error) {
            recommendationsList.innerHTML = '<div class="col-12"><div class="response-panel">Unable to load recommendations at the moment.</div></div>';
        }
    };

    const generateStudyGuide = async () => {
        const courseId = document.getElementById('studyGuideCourse').value;
        if (!courseId) {
            studyGuideResponse.textContent = 'Please select a course before generating a study guide.';
            return;
        }
        studyGuideResponse.textContent = 'Generating your study guide…';
        try {
            const response = await fetch(`${baseUrl}?action=generate_study_guide&course_id=${encodeURIComponent(courseId)}`);
            const data = await response.json();
            if (data.success && data.study_guide) {
                const guide = data.study_guide;
                studyGuideResponse.innerHTML = `
                    <strong>${guide.title}</strong>
                    <p>${guide.introduction}</p>
                    <p><strong>Summary:</strong> ${guide.course_summary}</p>
                    <p><strong>Learning objectives:</strong></p>
                    <ul>${guide.learning_objectives.map(item => `<li>${item}</li>`).join('')}</ul>
                    <p><strong>Study plan:</strong></p>
                    <ol>${guide.recommended_plan.map(item => `<li>${item}</li>`).join('')}</ol>
                    <p><strong>Revision checklist:</strong></p>
                    <ul>${guide.revision_checklist.map(item => `<li>${item}</li>`).join('')}</ul>
                `;
            } else {
                studyGuideResponse.textContent = data.message || 'Unable to generate study guide.';
            }
        } catch (error) {
            studyGuideResponse.textContent = 'There was an error generating the study guide.';
        }
    };

    const generateQuiz = async () => {
        const courseId = document.getElementById('quizCourse').value;
        const questionCount = document.getElementById('quizCount').value || 5;
        if (!courseId) {
            quizResponse.textContent = 'Please select a course before generating a quiz.';
            return;
        }
        quizResponse.textContent = 'Preparing practice quiz…';
        try {
            const response = await fetch(`${baseUrl}?action=generate_quiz&course_id=${encodeURIComponent(courseId)}&questions=${encodeURIComponent(questionCount)}`);
            const data = await response.json();
            if (data.success && Array.isArray(data.quiz) && data.quiz.length > 0) {
                quizResponse.innerHTML = data.quiz.map((item, index) => `
                    <div style="margin-bottom:18px;">
                        <strong>Q${index + 1}: ${item.question}</strong>
                        <div style="margin-top:6px; color:#475569;">Answer: ${item.answer}</div>
                    </div>
                `).join('');
            } else {
                quizResponse.textContent = data.message || 'No quiz questions could be generated.';
            }
        } catch (error) {
            quizResponse.textContent = 'Unable to generate the quiz at this time.';
        }
    };

    const scrollToSection = (id) => {
        const element = document.getElementById(id);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    sendButton.addEventListener('click', sendTutorChat);
    chatInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendTutorChat();
        }
    });
    document.getElementById('generateStudyGuideButton').addEventListener('click', generateStudyGuide);
    document.getElementById('generateQuizButton').addEventListener('click', generateQuiz);

    loadRecommendations();
</script>
</body>
</html>
