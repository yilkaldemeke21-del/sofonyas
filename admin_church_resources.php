<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/admin_management_i18n.php';

if (!($pdo instanceof PDO)) {
    die(adminManagementText('db_unavailable'));
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function getChurchResourceFlashMessage(): array
{
    $flash = [
        'message' => '',
        'type' => 'info',
    ];

    if (!empty($_SESSION['church_resource_message'])) {
        $flash['message'] = (string) $_SESSION['church_resource_message'];
        $flash['type'] = !empty($_SESSION['church_resource_message_type']) ? (string) $_SESSION['church_resource_message_type'] : 'info';
        unset($_SESSION['church_resource_message'], $_SESSION['church_resource_message_type']);
    }

    return $flash;
}

function createUploadsDirectory(string $path): void
{
    if (!is_dir($path)) {
        if (!mkdir($path, 0755, true) && !is_dir($path)) {
            throw new RuntimeException(adminManagementText('resource_save_failed'));
        }
    }
}

function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!columnExists($pdo, $table, $column)) {
        $pdo->exec($definition);
    }
}

function uploadChurchResourceFile(array $file, array $allowedExtensions, string $uploadDir): ?string
{
    if (empty($file['name']) || empty($file['tmp_name'])) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(adminManagementText('resource_upload_failed'));
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException(adminManagementText('resource_upload_invalid'));
    }

    $sizeLimit = 20 * 1024 * 1024;
    if ($file['size'] > $sizeLimit) {
        throw new RuntimeException(adminManagementText('resource_upload_large'));
    }

    $destinationDir = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR;
    createUploadsDirectory($destinationDir);

    $fileName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
    $destinationPath = $destinationDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
        throw new RuntimeException(adminManagementText('resource_upload_failed'));
    }

    return str_replace('\\', '/', 'uploads/resources/' . $fileName);
}

function ensureChurchResourcesAdminTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS church_resources (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(30) NOT NULL DEFAULT 'daily_verse',
        title VARCHAR(255) NOT NULL,
        subtitle VARCHAR(255) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        reference VARCHAR(255) DEFAULT NULL,
        scripture_text LONGTEXT DEFAULT NULL,
        media_image VARCHAR(255) DEFAULT NULL,
        pdf_url VARCHAR(255) DEFAULT NULL,
        audio_url VARCHAR(255) DEFAULT NULL,
        document_url VARCHAR(255) DEFAULT NULL,
        video_url VARCHAR(255) DEFAULT NULL,
        notes LONGTEXT DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category (category),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    addColumnIfMissing($pdo, 'church_resources', 'document_url', 'ALTER TABLE church_resources ADD COLUMN document_url VARCHAR(255) DEFAULT NULL');
    addColumnIfMissing($pdo, 'church_resources', 'video_url', 'ALTER TABLE church_resources ADD COLUMN video_url VARCHAR(255) DEFAULT NULL');
}

ensureChurchResourcesAdminTable($pdo);

$message = '';
$messageType = 'info';
$editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editingItem = null;

// Seed a default Daily Verse resource if none exists (useful when admin page first installed)
try {
    $seedStmt = $pdo->prepare('SELECT COUNT(*) FROM church_resources WHERE category = :category');
    $seedStmt->execute([':category' => 'daily_verse']);
    if ((int)$seedStmt->fetchColumn() === 0) {
        $insert = $pdo->prepare('INSERT INTO church_resources (category, title, subtitle, description, reference, scripture_text, is_active, created_at, updated_at) VALUES (:category, :title, :subtitle, :description, :reference, :scripture_text, :is_active, NOW(), NOW())');
        $insert->execute([
            ':category' => 'daily_verse',
            ':title' => 'የዕለቱ ት�ዛዝ',
            ':subtitle' => null,
            ':description' => 'የዕለቱ ትእዛዝ የምክር ቃል እና ዕርዳታ ነው።',
            ':reference' => 'ማቴ 9:13',
            ':scripture_text' => "ማቴ 9:13\nምሕረት እወዳለሁ መስዋእትን አይደለም ያለው ምን እንደሆነ ተማሩ",
            ':is_active' => 1,
        ]);
    }
} catch (Throwable $e) {
    error_log('Daily verse seed failed: ' . $e->getMessage());
}

if ($editingId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM church_resources WHERE id = ? LIMIT 1');
    $stmt->execute([$editingId]);
    $editingItem = $stmt->fetch();
}

$uploadDirectory = __DIR__ . '/uploads/resources';
$flash = getChurchResourceFlashMessage();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $category = trim((string)($_POST['category'] ?? 'daily_verse'));
    $title = trim((string)($_POST['title'] ?? ''));
    $reference = trim((string)($_POST['reference'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $scriptureText = trim((string)($_POST['scripture_text'] ?? ''));
    $subtitle = trim((string)($_POST['subtitle'] ?? ''));
    $pdfUrl = trim((string)($_POST['pdf_url'] ?? ''));
    $audioUrl = trim((string)($_POST['audio_url'] ?? ''));
    $documentUrl = trim((string)($_POST['document_url'] ?? ''));
    $videoUrl = trim((string)($_POST['video_url'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($category === '' || $title === '') {
        $message = adminManagementText('resource_required');
        $messageType = 'danger';
    } else {
        try {
            createUploadsDirectory($uploadDirectory);

            $existingResource = [];
            if ($action === 'update' && isset($_POST['id'])) {
                $stmt = $pdo->prepare('SELECT * FROM church_resources WHERE id = ? LIMIT 1');
                $stmt->execute([(int) $_POST['id']]);
                $existingResource = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            }

            $uploadedImage = uploadChurchResourceFile($_FILES['media_image'] ?? [], ['jpg', 'jpeg', 'png', 'webp', 'gif'], $uploadDirectory);
            $uploadedPdf = uploadChurchResourceFile($_FILES['pdf_file'] ?? [], ['pdf', 'doc', 'docx'], $uploadDirectory);
            $uploadedAudio = uploadChurchResourceFile($_FILES['audio_file'] ?? [], ['mp3', 'wav', 'ogg', 'm4a'], $uploadDirectory);
            $uploadedVideo = uploadChurchResourceFile($_FILES['video_file'] ?? [], ['mp4', 'webm', 'mov', 'avi'], $uploadDirectory);

            $mediaImage = $existingResource['media_image'] ?? null;
            $existingPdfUrl = $existingResource['pdf_url'] ?? null;
            $existingAudioUrl = $existingResource['audio_url'] ?? null;
            $existingDocumentUrl = $existingResource['document_url'] ?? null;
            $existingVideoUrl = $existingResource['video_url'] ?? null;

            if ($uploadedImage !== null) {
                $mediaImage = $uploadedImage;
            } elseif (!empty($_POST['media_image_url'])) {
                $mediaImage = trim((string) $_POST['media_image_url']);
            }

            if ($uploadedPdf !== null) {
                $pdfUrl = $uploadedPdf;
            } elseif ($pdfUrl === '' && $existingPdfUrl !== null) {
                $pdfUrl = $existingPdfUrl;
            }

            if ($uploadedAudio !== null) {
                $audioUrl = $uploadedAudio;
            } elseif ($audioUrl === '' && $existingAudioUrl !== null) {
                $audioUrl = $existingAudioUrl;
            }

            if ($uploadedVideo !== null) {
                $videoUrl = $uploadedVideo;
            } elseif ($videoUrl === '' && $existingVideoUrl !== null) {
                $videoUrl = $existingVideoUrl;
            }

            if ($documentUrl === '' && $existingDocumentUrl !== null) {
                $documentUrl = $existingDocumentUrl;
            }
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            $messageType = 'danger';
        }
    }

    if ($messageType !== 'danger') {
        try {
            if ($action === 'delete' && isset($_POST['id'])) {
                $id = (int) $_POST['id'];
                $stmt = $pdo->prepare('DELETE FROM church_resources WHERE id = ?');
                $stmt->execute([$id]);
                $_SESSION['church_resource_message'] = 'resource_removed';
                $_SESSION['church_resource_message_type'] = 'success';
                header('Location: admin_church_resources.php');
                exit;
            }

            if ($action === 'update' && isset($_POST['id'])) {
                $id = (int) $_POST['id'];
                $stmt = $pdo->prepare('UPDATE church_resources SET category = :category, title = :title, subtitle = :subtitle, description = :description, reference = :reference, scripture_text = :scripture_text, media_image = :media_image, pdf_url = :pdf_url, audio_url = :audio_url, document_url = :document_url, video_url = :video_url, notes = :notes, is_active = :is_active WHERE id = :id');
                $stmt->execute([
                    ':category' => $category,
                    ':title' => $title,
                    ':subtitle' => $subtitle,
                    ':description' => $description,
                    ':reference' => $reference,
                    ':scripture_text' => $scriptureText,
                    ':media_image' => $mediaImage ?? null,
                    ':pdf_url' => $pdfUrl ?: null,
                    ':audio_url' => $audioUrl ?: null,
                    ':document_url' => $documentUrl ?: null,
                    ':video_url' => $videoUrl ?: null,
                    ':notes' => $notes,
                    ':is_active' => $isActive,
                    ':id' => $id,
                ]);
                $_SESSION['church_resource_message'] = 'resource_updated';
                $_SESSION['church_resource_message_type'] = 'success';
                header('Location: admin_church_resources.php');
                exit;
            }

            if ($action === 'create') {
                $stmt = $pdo->prepare('INSERT INTO church_resources (category, title, subtitle, description, reference, scripture_text, media_image, pdf_url, audio_url, document_url, video_url, notes, is_active) VALUES (:category, :title, :subtitle, :description, :reference, :scripture_text, :media_image, :pdf_url, :audio_url, :document_url, :video_url, :notes, :is_active)');
                $stmt->execute([
                    ':category' => $category,
                    ':title' => $title,
                    ':subtitle' => $subtitle,
                    ':description' => $description,
                    ':reference' => $reference,
                    ':scripture_text' => $scriptureText,
                    ':media_image' => $mediaImage ?? null,
                    ':pdf_url' => $pdfUrl ?: null,
                    ':audio_url' => $audioUrl ?: null,
                    ':document_url' => $documentUrl ?: null,
                    ':video_url' => $videoUrl ?: null,
                    ':notes' => $notes,
                    ':is_active' => $isActive,
                ]);
                $_SESSION['church_resource_message'] = 'resource_created';
                $_SESSION['church_resource_message_type'] = 'success';
                header('Location: admin_church_resources.php');
                exit;
            }
        } catch (Throwable $e) {
            error_log('Church resource save failed: ' . $e->getMessage());
            $message = adminManagementText('resource_save_failed');
            $messageType = 'danger';
        }
    }
}

$stmt = $pdo->query('SELECT * FROM church_resources ORDER BY category ASC, created_at DESC');
$resources = $stmt->fetchAll();

$categories = [
    'daily_verse' => 'resource_cat_daily_verse',
    'saint' => 'resource_cat_saint',
    'bible_passage' => 'resource_cat_bible_passage',
];
?>
<!DOCTYPE html>
<html lang="<?php echo e($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(adminManagementText('church_resources_title')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="mb-1">✝️ <?php echo e(adminManagementText('church_resources_title')); ?></h2>
            <p class="text-muted mb-0"><?php echo e(adminManagementText('church_resources_intro')); ?></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo e($adminManagementSwitchUrl); ?>" class="btn btn-outline-primary"><?php echo e(adminManagementText('language_switch')); ?></a>
            <a href="admin_dashboard.php" class="btn btn-outline-secondary"><?php echo e(adminManagementText('back_dashboard')); ?></a>
            <a href="admin_church_resources.php" class="btn btn-primary"><?php echo e(adminManagementText('resource_new')); ?></a>
        </div>
    </div>

    <?php if ($flash['message'] !== ''): ?>
        <div class="alert alert-<?php echo e($flash['type'] === 'success' ? 'success' : ($flash['type'] === 'danger' ? 'danger' : 'info')); ?>" role="alert"><?php echo e(adminManagementText($flash['message'])); ?></div>
    <?php elseif ($message !== ''): ?>
        <div class="alert alert-<?php echo e($messageType === 'success' ? 'success' : ($messageType === 'danger' ? 'danger' : 'info')); ?>" role="alert"><?php echo e($message); ?></div>
    <?php endif; ?>

    <div class="row gy-4">
        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title mb-3"><?php echo e(adminManagementText($editingItem ? 'resource_edit' : 'resource_add')); ?></h5>
                    <form method="post" enctype="multipart/form-data" class="row g-3">
                        <input type="hidden" name="action" value="<?php echo $editingItem ? 'update' : 'create'; ?>">
                        <?php if ($editingItem): ?>
                            <input type="hidden" name="id" value="<?php echo (int) $editingItem['id']; ?>">
                        <?php endif; ?>

                        <div class="col-12">
                            <label class="form-label"><?php echo e(adminManagementText('resource_category')); ?></label>
                            <select name="category" class="form-select" required>
                                <?php foreach ($categories as $key => $label): ?>
                                    <option value="<?php echo e($key); ?>" <?php echo ($editingItem && $editingItem['category'] === $key) ? 'selected' : ''; ?>><?php echo e(adminManagementText($label)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?php echo e(adminManagementText('resource_title')); ?></label>
                            <input type="text" name="title" class="form-control" value="<?php echo e($editingItem['title'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?php echo e(adminManagementText('resource_subtitle')); ?></label>
                            <input type="text" name="subtitle" class="form-control" value="<?php echo e($editingItem['subtitle'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?php echo e(adminManagementText('resource_reference')); ?></label>
                            <input type="text" name="reference" class="form-control" value="<?php echo e($editingItem['reference'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?php echo e(adminManagementText('resource_description')); ?></label>
                            <textarea name="description" class="form-control" rows="3"><?php echo e($editingItem['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?php echo e(adminManagementText('resource_scripture')); ?></label>
                            <textarea name="scripture_text" class="form-control" rows="4"><?php echo e($editingItem['scripture_text'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12 row g-3">
                            <div class="col-6">
                                <label class="form-label"><?php echo e(adminManagementText('resource_image_file')); ?></label>
                                <input type="file" name="media_image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                                <?php if ($editingItem && !empty($editingItem['media_image'])): ?>
                                    <div class="mt-2">
                                        <small class="text-muted"><?php echo e(adminManagementText('resource_current_image')); ?>:</small>
                                        <div class="border rounded p-2 mt-1" style="max-width:100%;">
                                            <img src="<?php echo e($editingItem['media_image']); ?>" alt="<?php echo e(adminManagementText('resource_current_image')); ?>" style="max-width:100%; height:auto; display:block; border-radius:8px;">
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-6">
                                <label class="form-label"><?php echo e(adminManagementText('resource_image_url')); ?></label>
                                <input type="url" name="media_image_url" class="form-control" value="<?php echo e($editingItem['media_image'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-12 row g-3">
                            <div class="col-6">
                                <label class="form-label"><?php echo e(adminManagementText('resource_pdf_file')); ?></label>
                                <input type="file" name="pdf_file" class="form-control" accept="application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                                <?php if ($editingItem && !empty($editingItem['pdf_url'])): ?>
                                    <div class="mt-2"><a href="<?php echo e($editingItem['pdf_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo e(adminManagementText('resource_view_pdf')); ?></a></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-6">
                                <label class="form-label"><?php echo e(adminManagementText('resource_pdf_url')); ?></label>
                                <input type="url" name="pdf_url" class="form-control" value="<?php echo e($editingItem['pdf_url'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?php echo e(adminManagementText('resource_document_url')); ?></label>
                            <input type="url" name="document_url" class="form-control" value="<?php echo e($editingItem['document_url'] ?? ''); ?>">
                            <?php if ($editingItem && !empty($editingItem['document_url'])): ?>
                                <div class="mt-2"><a href="<?php echo e($editingItem['document_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo e(adminManagementText('resource_view_document')); ?></a></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 row g-3">
                            <div class="col-6">
                                <label class="form-label"><?php echo e(adminManagementText('resource_audio_file')); ?></label>
                                <input type="file" name="audio_file" class="form-control" accept="audio/*">
                                <?php if ($editingItem && !empty($editingItem['audio_url'])): ?>
                                    <div class="mt-2"><a href="<?php echo e($editingItem['audio_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo e(adminManagementText('resource_play_audio')); ?></a></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-6">
                                <label class="form-label"><?php echo e(adminManagementText('resource_audio_url')); ?></label>
                                <input type="url" name="audio_url" class="form-control" value="<?php echo e($editingItem['audio_url'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-12 row g-3">
                            <div class="col-6">
                                <label class="form-label"><?php echo e(adminManagementText('resource_video_file')); ?></label>
                                <input type="file" name="video_file" class="form-control" accept="video/*">
                                <?php if ($editingItem && !empty($editingItem['video_url'])): ?>
                                    <div class="mt-2"><a href="<?php echo e($editingItem['video_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo e(adminManagementText('resource_watch_video')); ?></a></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-6">
                                <label class="form-label"><?php echo e(adminManagementText('resource_video_url')); ?></label>
                                <input type="url" name="video_url" class="form-control" value="<?php echo e($editingItem['video_url'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?php echo e(adminManagementText('resource_notes')); ?></label>
                            <textarea name="notes" class="form-control" rows="3"><?php echo e($editingItem['notes'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12 form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?php echo ($editingItem && $editingItem['is_active']) || !$editingItem ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active"><?php echo e(adminManagementText('resource_active')); ?></label>
                        </div>
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-primary"><?php echo e(adminManagementText($editingItem ? 'resource_update' : 'resource_create')); ?></button>
                            <?php if ($editingItem): ?>
                                <a href="admin_church_resources.php" class="btn btn-outline-secondary"><?php echo e(adminManagementText('resource_cancel')); ?></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title mb-3"><?php echo e(adminManagementText('resource_existing')); ?></h5>
                    <?php if (empty($resources)): ?>
                        <p class="text-muted"><?php echo e(adminManagementText('resource_none')); ?></p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col"><?php echo e(adminManagementText('resource_category')); ?></th>
                                        <th scope="col"><?php echo e(adminManagementText('resource_title')); ?></th>
                                        <th scope="col"><?php echo e(adminManagementText('resource_reference')); ?></th>
                                        <th scope="col"><?php echo e(adminManagementText('resource_status')); ?></th>
                                        <th scope="col"><?php echo e(adminManagementText('resource_preview')); ?></th>
                                        <th scope="col" class="text-end"><?php echo e(adminManagementText('resource_actions')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resources as $resource): ?>
                                        <tr>
                                            <td><?php echo e(isset($categories[$resource['category']]) ? adminManagementText($categories[$resource['category']]) : $resource['category']); ?></td>
                                            <td><?php echo e($resource['title']); ?></td>
                                            <td><?php echo e($resource['reference']); ?></td>
                                            <td><?php echo e(adminManagementText($resource['is_active'] ? 'resource_active' : 'resource_inactive')); ?></td>
                                            <td>
                                                <?php if (!empty($resource['media_image'])): ?>
                                                    <a href="<?php echo e($resource['media_image']); ?>" target="_blank" rel="noopener noreferrer" class="d-block"><?php echo e(adminManagementText('resource_image')); ?></a>
                                                <?php endif; ?>
                                                <?php if (!empty($resource['pdf_url'])): ?>
                                                    <a href="<?php echo e($resource['pdf_url']); ?>" target="_blank" rel="noopener noreferrer" class="d-block"><?php echo e(adminManagementText('resource_pdf')); ?></a>
                                                <?php endif; ?>
                                                <?php if (!empty($resource['document_url'])): ?>
                                                    <a href="<?php echo e($resource['document_url']); ?>" target="_blank" rel="noopener noreferrer" class="d-block"><?php echo e(adminManagementText('resource_document')); ?></a>
                                                <?php endif; ?>
                                                <?php if (!empty($resource['audio_url'])): ?>
                                                    <a href="<?php echo e($resource['audio_url']); ?>" target="_blank" rel="noopener noreferrer" class="d-block"><?php echo e(adminManagementText('resource_audio')); ?></a>
                                                <?php endif; ?>
                                                <?php if (!empty($resource['video_url'])): ?>
                                                    <a href="<?php echo e($resource['video_url']); ?>" target="_blank" rel="noopener noreferrer" class="d-block"><?php echo e(adminManagementText('resource_video')); ?></a>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <a href="admin_church_resources.php?edit=<?php echo (int) $resource['id']; ?>" class="btn btn-sm btn-outline-primary"><?php echo e(adminManagementText('resource_edit_action')); ?></a>
                                                <form action="admin_church_resources.php" method="post" class="d-inline-block ms-1" onsubmit="return confirm(<?php echo htmlspecialchars(json_encode(adminManagementText('resource_delete_confirm')), ENT_QUOTES, 'UTF-8'); ?>);">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int) $resource['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo e(adminManagementText('resource_delete')); ?></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
