<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$uploadsDir = __DIR__ . '/uploads/whiteboard/';
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0755, true);
}

$lastSavedFile = ''; 
$lastSavedUrl = '';
$whiteboardFiles = glob($uploadsDir . 'whiteboard_*.png');
if (!empty($whiteboardFiles)) {
    usort($whiteboardFiles, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    $lastSavedFile = basename($whiteboardFiles[0]);
    $lastSavedUrl = 'uploads/whiteboard/' . rawurlencode($lastSavedFile);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!is_array($data) || empty($data['image_data'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid payload.']);
        exit;
    }

    $imageData = $data['image_data'];
    if (preg_match('#^data:image/(png|jpeg);base64,(.+)$#', $imageData, $matches)) {
        $mime = $matches[1];
        $base64 = $matches[2];
        $decoded = base64_decode($base64);
        if ($decoded === false) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Could not decode image data.']);
            exit;
        }

        $filename = 'whiteboard_' . date('Ymd_His') . '.png';
        $target = $uploadsDir . $filename;
        if (file_put_contents($target, $decoded) === false) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Could not save image.']);
            exit;
        }

        $lastSavedFile = $filename;
        $lastSavedUrl = 'uploads/whiteboard/' . rawurlencode($lastSavedFile);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Whiteboard saved successfully.', 'file' => $lastSavedUrl]);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Only PNG or JPEG images are supported.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Whiteboard</title>
    <style>
        :root {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #eef2ff;
            color: #0f172a;
        }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; }
        .page-shell { max-width: 1180px; margin: 0 auto; padding: 20px; }
        .topbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 18px 20px; background: linear-gradient(135deg, #4338ca 0%, #8b5cf6 100%); color: white; border-radius: 18px 18px 0 0; }
        .topbar h1 { margin: 0; font-size: clamp(1.6rem, 2.2vw, 2.4rem); }
        .topbar a { color: white; text-decoration: none; font-weight: 700; border: 1px solid rgba(255,255,255,0.3); padding: 10px 14px; border-radius: 12px; background: rgba(255,255,255,0.08); }
        .board-card { background: white; border-radius: 0 0 18px 18px; box-shadow: 0 24px 60px rgba(15,23,42,0.08); padding: 24px; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 14px; }
        .toolbar label { font-weight: 700; color: #334155; }
        .toolbar input[type="color"], .toolbar input[type="range"] { cursor: pointer; }
        .toolbar button { border: none; border-radius: 10px; padding: 10px 14px; background: #4338ca; color: white; cursor: pointer; transition: transform .18s ease, background .18s ease; }
        .toolbar button:hover { transform: translateY(-1px); background: #5b21b6; }
        .board-area { border: 2px solid #c7d2fe; border-radius: 18px; overflow: hidden; background: white; position: relative; }
        #whiteboard { display: block; width: 100%; height: 540px; touch-action: none; }
        .panel { display: grid; gap: 16px; margin-top: 24px; }
        .panel h2 { margin: 0 0 10px; font-size: 1.2rem; color: #111827; }
        .panel .preview-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px; }
        .preview-card img { width: 100%; border-radius: 12px; display: block; }
        .status { padding: 12px 14px; border-radius: 12px; background: #f8fafc; color: #334155; font-family: monospace; white-space: pre-wrap; }
        @media (max-width: 900px) { .toolbar { flex-direction: column; align-items: stretch; } }
    </style>
</head>
<body>
<div class="page-shell">
    <div class="topbar">
        <div>
            <h1>🎥 Interactive Whiteboard</h1>
            <div style="opacity:.85; margin-top:6px;">የአስተዳዳሪ ቦርድ ለቀጥታ መጻፍና ለማስቀመጥ</div>
        </div>
        <a href="admin_dashboard.php">← ወደ ዳሽቦርድ ተመለስ</a>
    </div>
    <div class="board-card">
        <div class="toolbar">
            <label>Brush color:</label>
            <input id="colorPicker" type="color" value="#1f2937">
            <label>Size:</label>
            <input id="brushSize" type="range" min="2" max="24" value="6">
            <button id="clearBtn" type="button">Clear Board</button>
            <button id="saveBtn" type="button">Save Whiteboard</button>
            <button id="downloadBtn" type="button">Download PNG</button>
        </div>
        <div class="board-area">
            <canvas id="whiteboard" width="1200" height="540"></canvas>
        </div>
        <div class="panel">
            <div class="preview-card">
                <h2>Last saved drawing</h2>
                <?php if ($lastSavedUrl): ?>
                    <a href="<?php echo safe($lastSavedUrl); ?>" target="_blank" rel="noopener">
                        <img src="<?php echo safe($lastSavedUrl); ?>" alt="Latest whiteboard snapshot">
                    </a>
                    <p style="margin-top:10px; color:#475569;">Saved file: <?php echo safe($lastSavedFile); ?></p>
                <?php else: ?>
                    <p style="color:#475569;">No saved whiteboard yet. Draw on the board and press Save.</p>
                <?php endif; ?>
            </div>
            <div class="status" id="boardStatus">Ready to draw.</div>
        </div>
    </div>
</div>
<script>
(function() {
    const canvas = document.getElementById('whiteboard');
    const ctx = canvas.getContext('2d');
    const colorPicker = document.getElementById('colorPicker');
    const brushSize = document.getElementById('brushSize');
    const clearBtn = document.getElementById('clearBtn');
    const saveBtn = document.getElementById('saveBtn');
    const downloadBtn = document.getElementById('downloadBtn');
    const status = document.getElementById('boardStatus');

    let drawing = false;
    let lastX = 0;
    let lastY = 0;

    const setStatus = (text) => {
        status.textContent = text;
    };

    const resizeCanvas = () => {
        const ratio = window.devicePixelRatio || 1;
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * ratio;
        canvas.height = rect.height * ratio;
        ctx.scale(ratio, ratio);
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    };

    const getPointer = (event) => {
        const rect = canvas.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const y = event.clientY - rect.top;
        return { x, y };
    };

    const startDrawing = (event) => {
        drawing = true;
        const pos = getPointer(event);
        lastX = pos.x;
        lastY = pos.y;
    };

    const stopDrawing = () => {
        drawing = false;
    };

    const draw = (event) => {
        if (!drawing) return;
        const pos = getPointer(event);
        ctx.strokeStyle = colorPicker.value;
        ctx.lineWidth = brushSize.value;
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        lastX = pos.x;
        lastY = pos.y;
    };

    const setupEvents = () => {
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        window.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('touchstart', (e) => { e.preventDefault(); startDrawing(e.touches[0]); });
        canvas.addEventListener('touchmove', (e) => { e.preventDefault(); draw(e.touches[0]); });
        canvas.addEventListener('touchend', stopDrawing);
    };

    const clearBoard = () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        setStatus('Board cleared.');
    };

    const saveBoard = async () => {
        const dataUrl = canvas.toDataURL('image/png');
        setStatus('Saving...');
        try {
            const response = await fetch('admin_whiteboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ image_data: dataUrl })
            });
            const json = await response.json();
            if (json.success) {
                setStatus('Saved successfully. Refresh the page to view the latest image.');
            } else {
                setStatus('Save failed: ' + json.message);
            }
        } catch (error) {
            setStatus('Save error: ' + error.message);
        }
    };

    const downloadBoard = () => {
        const link = document.createElement('a');
        link.href = canvas.toDataURL('image/png');
        link.download = 'whiteboard_' + new Date().toISOString().replace(/[:.]/g, '-') + '.png';
        link.click();
    };

    window.addEventListener('resize', () => {
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        resizeCanvas();
        ctx.putImageData(imageData, 0, 0);
    });

    resizeCanvas();
    setupEvents();
    clearBtn.addEventListener('click', clearBoard);
    saveBtn.addEventListener('click', saveBoard);
    downloadBtn.addEventListener('click', downloadBoard);
})();
</script>
</body>
</html>
