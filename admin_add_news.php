<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once 'db.php';

if (!($pdo instanceof PDO)) {
    die('Database connection is unavailable.');
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column");
    $stmt->execute([':column' => $column]);
    if ($stmt->fetch()) {
        return;
    }
    $pdo->exec($definition);
}

function ensureNewsTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_news (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        short_description TEXT NOT NULL,
        full_content TEXT NOT NULL,
        image VARCHAR(255) NULL DEFAULT NULL,
        created_at DATETIME NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        is_deleted TINYINT(1) NOT NULL DEFAULT 0,
        deleted_at DATETIME NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    ensureColumn($pdo, 'site_news', 'image', "ALTER TABLE site_news ADD COLUMN image VARCHAR(255) NULL DEFAULT NULL");
    ensureColumn($pdo, 'site_news', 'status', "ALTER TABLE site_news ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'draft'");
    ensureColumn($pdo, 'site_news', 'is_deleted', "ALTER TABLE site_news ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0");
    ensureColumn($pdo, 'site_news', 'deleted_at', "ALTER TABLE site_news ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
}

ensureNewsTable($pdo);
$uploadDir = __DIR__ . '/uploads/news';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$message = '';
$messageType = '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;
$editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editingItem = null;

if ($editingId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM site_news WHERE id = ? AND is_deleted = 0 LIMIT 1');
    $stmt->execute([$editingId]);
    $editingItem = $stmt->fetch();
}

function validateImageUpload(array $file): array
{
    $errors = [];
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['valid' => true, 'path' => null, 'error' => ''];
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $maxBytes = 2 * 1024 * 1024;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Upload failed. Please try another image.';
    }
    if ($file['size'] > $maxBytes) {
        $errors[] = 'Image size must be 2MB or less.';
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowedTypes, true)) {
        $errors[] = 'Only JPG, PNG, WEBP, and GIF files are allowed.';
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        $errors[] = 'Unsupported file extension.';
    }

    if ($errors) {
        return ['valid' => false, 'path' => null, 'error' => implode(' ', $errors)];
    }

    $fileName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destination = __DIR__ . '/uploads/news/' . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['valid' => false, 'path' => null, 'error' => 'File could not be stored.'];
    }

    return ['valid' => true, 'path' => 'uploads/news/' . $fileName, 'error' => ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $stmt = $pdo->prepare('UPDATE site_news SET is_deleted = 1, deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
        $message = 'News moved to archive safely.';
        $messageType = 'success';
    } elseif ($action === 'update' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $title = trim((string) ($_POST['title'] ?? ''));
        $shortDescription = trim((string) ($_POST['short_description'] ?? ''));
        $fullContent = trim((string) ($_POST['full_content'] ?? ''));
        $status = in_array($_POST['status'] ?? 'draft', ['draft', 'published'], true) ? $_POST['status'] : 'draft';

        if ($title === '' || $shortDescription === '' || $fullContent === '') {
            $message = 'Title, short description, and full content are required.';
            $messageType = 'danger';
        } else {
            $imageValue = null;
            $uploadResult = validateImageUpload($_FILES['image'] ?? []);
            if ($uploadResult['valid'] && $uploadResult['path'] !== null) {
                $imageValue = $uploadResult['path'];
            } elseif (!empty($uploadResult['error'])) {
                $message = $uploadResult['error'];
                $messageType = 'danger';
            }

            if ($message === '') {
                $existingStmt = $pdo->prepare('SELECT image FROM site_news WHERE id = ? AND is_deleted = 0 LIMIT 1');
                $existingStmt->execute([$id]);
                $existing = $existingStmt->fetch();
                if ($imageValue === null && $existing) {
                    $imageValue = $existing['image'];
                }

                $stmt = $pdo->prepare('UPDATE site_news SET title = ?, short_description = ?, full_content = ?, image = ?, status = ? WHERE id = ? AND is_deleted = 0');
                $stmt->execute([$title, $shortDescription, $fullContent, $imageValue, $status, $id]);
                $message = 'News updated successfully.';
                $messageType = 'success';
            }
        }
    } else {
        $title = trim((string) ($_POST['title'] ?? ''));
        $shortDescription = trim((string) ($_POST['short_description'] ?? ''));
        $fullContent = trim((string) ($_POST['full_content'] ?? ''));
        $status = in_array($_POST['status'] ?? 'draft', ['draft', 'published'], true) ? $_POST['status'] : 'draft';

        if ($title === '' || $shortDescription === '' || $fullContent === '') {
            $message = 'Title, short description, and full content are required.';
            $messageType = 'danger';
        } else {
            $uploadResult = validateImageUpload($_FILES['image'] ?? []);
            if ($uploadResult['valid'] === false && $uploadResult['path'] === null && $uploadResult['error'] !== '') {
                $message = $uploadResult['error'];
                $messageType = 'danger';
            } else {
                $imageValue = $uploadResult['path'];
                $stmt = $pdo->prepare('INSERT INTO site_news (title, short_description, full_content, image, created_at, status) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$title, $shortDescription, $fullContent, $imageValue, date('Y-m-d H:i:s'), $status]);
                $message = 'News saved successfully.';
                $messageType = 'success';
            }
        }
    }
}

$totalStmt = $pdo->query('SELECT COUNT(*) AS total FROM site_news WHERE is_deleted = 0');
$totalRows = (int) $totalStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

$recordsStmt = $pdo->prepare('SELECT * FROM site_news WHERE is_deleted = 0 ORDER BY created_at DESC LIMIT ? OFFSET ?');
$recordsStmt->bindValue(1, $perPage, PDO::PARAM_INT);
$recordsStmt->bindValue(2, $offset, PDO::PARAM_INT);
$recordsStmt->execute();
$records = $recordsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News CRUD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">📰 News</h2>
            <p class="text-muted mb-0">Publish and manage site news articles.</p>
        </div>
        <a href="admin_dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo e($messageType ?: 'info'); ?>" role="alert"><?php echo e($message); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $editingItem ? 'Edit News' : 'Add News'; ?></h5>
                    <form method="post" class="row g-3" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="<?php echo $editingItem ? 'update' : 'create'; ?>">
                        <?php if ($editingItem): ?>
                            <input type="hidden" name="id" value="<?php echo (int) $editingItem['id']; ?>">
                        <?php endif; ?>
                        <div class="col-12">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" value="<?php echo e($editingItem['title'] ?? ''); ?>" required maxlength="255">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Short Description</label>
                            <textarea name="short_description" class="form-control" rows="3" required><?php echo e($editingItem['short_description'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Full Content</label>
                            <textarea name="full_content" class="form-control" rows="6" required><?php echo e($editingItem['full_content'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Image</label>
                            <input type="file" name="image" class="form-control" accept="image/png,image/jpeg,image/webp,image/gif">
                            <div class="form-text">JPG, PNG, WEBP, GIF only. Max 2MB.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="draft" <?php echo (($editingItem['status'] ?? 'draft') === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                <option value="published" <?php echo (($editingItem['status'] ?? 'draft') === 'published') ? 'selected' : ''; ?>>Published</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><?php echo $editingItem ? 'Update' : 'Save'; ?></button>
                            <?php if ($editingItem): ?>
                                <a href="admin_add_news.php" class="btn btn-outline-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">News Articles</h5>
                        <span class="badge bg-primary"><?php echo $totalRows; ?> items</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($records): ?>
                                    <?php foreach ($records as $row): ?>
                                        <tr>
                                            <td><?php echo e($row['title']); ?></td>
                                            <td><span class="badge <?php echo $row['status'] === 'published' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo e($row['status']); ?></span></td>
                                            <td><?php echo e($row['created_at']); ?></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="admin_add_news.php?edit=<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Archive this news article?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-muted text-center py-4">No news yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav class="mt-3">
                            <ul class="pagination pagination-sm justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="admin_add_news.php?page=<?php echo max(1, $page - 1); ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="admin_add_news.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="admin_add_news.php?page=<?php echo min($totalPages, $page + 1); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
