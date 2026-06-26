<?php
$questions = [
    [
        'question' => '1.እግዚአብሔር ወልድ ከቅድስት ድንግል ማርያም ከሥጋዋ ሥጋን
ከነፍሷ ነፍስን ነስቶ ሲዋሐድ ለወልድ ብቻ የሚሰጥ ግብር የትኛው
ነው?',
        'options' => ['ሀ.ሥጋን መክፈል', 'ሥጋን ማክበር', 'ሐ.ሥጋንና መለኮትን ማዋሐድ', 'መ.ለሥጋ ክብር መሆን'],
        'correct' => 'መ.ለሥጋ ክብር መሆን',
    ],
    [
        'question' => '2.ስለምስጠመረ ሥላሴ ትክክል ያልሆነው የቱ ነው?',
        'options' => ['ሀ.ሥላሴ ማለት ሦስት ማለት ነው', 'ለ.አብ አምላክ፤ወልድ አምላክ፤መንፈስ ቅዱስ አምላክ፤አንድ
አምላክ', 'ሐ.ሥላሴ በስም በአካል በግብር በኩነት ሦስት ሲሆኑ በመለኮት
አንድ ናቸው።', 'መ.መልስ የለም።'],
        'correct' => 'ሀ.ሥላሴ ማለትሦስት ማለት ነው።',
    ],
    [
        'question' => '3.ስለ እግዚአብሔር ወልድ ዳግም ልደትና የማዳን ስራ የሚያስረዳን
ምስጢር ምን ይባላል?',
        'options' => ['ሀ.ምስጢረ ሥላሴ', 'ለ.ትንሣኤ ዘክርስቶስ', 'ሐ.ምስጢረ ሥጋዌ', 'መ.ምስጢረ ቁርባን'],
        'correct' => 'ሐ.ምስጢረ ሥጋዌ',
    ],
];

$questionChunks = array_chunk($questions, 10);
$totalSections = count($questionChunks);
$score = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($questions as $index => $item) {
        $answer = $_POST['q' . $index] ?? '';
        if ($answer === $item['correct']) {
            $score++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Online Multiple Choice Exam</title>
    <style>
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #f8fbff 0%, #eef2ff 100%); color: #0f172a; margin: 0; }
        .wrap { max-width: 960px; margin: 30px auto; padding: 20px; }
        .card { background: rgba(255,255,255,0.96); border-radius: 20px; padding: 24px; box-shadow: 0 18px 40px rgba(15,23,42,0.08); border: 1px solid #e2e8f0; }
        h1 { color: #4f46e5; margin-top: 0; }
        .question { margin-bottom: 18px; padding: 14px; border-radius: 14px; border: 1px solid #e2e8f0; background: #fcfdff; }
        .question p { font-weight: 700; margin-bottom: 10px; }
        label { display: block; margin: 6px 0; cursor: pointer; padding: 8px 10px; border-radius: 10px; background: #f8fafc; border: 1px solid transparent; }
        label:hover { border-color: #c7d2fe; background: #f5f7ff; }
        .section-block { display: none; }
        .section-block.active { display: block; }
        .section-heading { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 12px; color: #1d4ed8; font-weight: 700; }
        .nav-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-top: 16px; }
        .nav-btn { background: linear-gradient(135deg, #4f46e5, #2563eb); color: white; border: none; padding: 12px 18px; border-radius: 999px; font-size: 15px; cursor: pointer; box-shadow: 0 10px 20px rgba(79,70,229,0.18); }
        .nav-btn.ghost { background: #e2e8f0; color: #334155; box-shadow: none; }
        .progress { height: 8px; background: #e2e8f0; border-radius: 999px; overflow: hidden; margin-bottom: 16px; }
        .progress > span { display: block; height: 100%; width: 0%; background: linear-gradient(90deg, #4f46e5, #2563eb); transition: width 0.25s ease; }
        .result { background: #ecfdf5; border: 1px solid #a7f3d0; padding: 14px; border-radius: 12px; margin-top: 16px; }
        .small { color: #555; font-size: 13px; }
        .chip { display: inline-block; padding: 6px 10px; border-radius: 999px; background: #eff6ff; color: #1d4ed8; font-size: 12px; font-weight: 700; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Online Multiple Choice Exam</h1>
        <p class="small">ቀላል እና ቀጥታ የሚሰራ ፒ.ኤች.ፒ መልመጃ ገጽ።</p>

        <form method="post" id="examForm">
            <div class="progress" aria-hidden="true"><span id="progressFill"></span></div>
            <div class="small chip" id="sectionInfo">Section 1 of <?php echo max(1, $totalSections); ?></div>

            <?php $globalIndex = 0; ?>
            <?php foreach ($questionChunks as $chunkIndex => $chunk): ?>
                <div class="section-block <?php echo $chunkIndex === 0 ? 'active' : ''; ?>" data-section="<?php echo $chunkIndex + 1; ?>">
                    <div class="section-heading">
                        <span>Section <?php echo $chunkIndex + 1; ?></span>
                        <span><?php echo count($chunk); ?> Questions</span>
                    </div>
                    <?php foreach ($chunk as $item): ?>
                        <div class="question">
                            <p><?php echo $globalIndex + 1; ?>. <?php echo htmlspecialchars($item['question']); ?></p>
                            <?php foreach ($item['options'] as $option): ?>
                                <label>
                                    <input type="radio" name="q<?php echo $globalIndex; ?>" value="<?php echo htmlspecialchars($option); ?>" />
                                    <?php echo htmlspecialchars($option); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php $globalIndex++; ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="nav-row">
                <button type="button" class="nav-btn ghost" id="prevBtn" style="display:none;">Previous</button>
                <button type="button" class="nav-btn" id="nextBtn">Next</button>
                <button type="submit" class="nav-btn" id="submitBtn" style="display:none;">Submit</button>
            </div>
        </form>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="result">
                <strong>ውጤት:</strong> እርስዎ <?php echo $score; ?> / <?php echo count($questions); ?> መልሳት በትክክል መለሱ።
            </div>
        <?php endif; ?>
    </div>
</div>
    <script>
        const totalSections = <?php echo max(1, $totalSections); ?>;
        let currentSection = 0;
        const sections = Array.from(document.querySelectorAll('.section-block'));
        const progressFill = document.getElementById('progressFill');
        const sectionInfo = document.getElementById('sectionInfo');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const submitBtn = document.getElementById('submitBtn');

        function updateNav() {
            sections.forEach((section, index) => {
                section.classList.toggle('active', index === currentSection);
            });

            const progress = ((currentSection + 1) / totalSections) * 100;
            progressFill.style.width = progress + '%';
            sectionInfo.textContent = 'Section ' + (currentSection + 1) + ' of ' + totalSections;
            prevBtn.style.display = currentSection === 0 ? 'none' : 'inline-block';
            nextBtn.style.display = currentSection === totalSections - 1 ? 'none' : 'inline-block';
            submitBtn.style.display = currentSection === totalSections - 1 ? 'inline-block' : 'none';
        }

        prevBtn.addEventListener('click', () => {
            if (currentSection > 0) {
                currentSection--;
                updateNav();
            }
        });

        nextBtn.addEventListener('click', () => {
            if (currentSection < totalSections - 1) {
                currentSection++;
                updateNav();
            }
        });

        updateNav();
    </script>
</body>
</html>
