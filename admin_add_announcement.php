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

function ensureAnnouncementTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_announcements (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        admin_id INT UNSIGNED NOT NULL DEFAULT 0,
        is_deleted TINYINT(1) NOT NULL DEFAULT 0,
        deleted_at DATETIME NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    ensureColumn($pdo, 'site_announcements', 'admin_id', "ALTER TABLE site_announcements ADD COLUMN admin_id INT UNSIGNED NOT NULL DEFAULT 0");
    ensureColumn($pdo, 'site_announcements', 'is_deleted', "ALTER TABLE site_announcements ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0");
    ensureColumn($pdo, 'site_announcements', 'deleted_at', "ALTER TABLE site_announcements ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
}

ensureAnnouncementTable($pdo);

$message = '';
$messageType = '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;
$editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editingItem = null;

if ($editingId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM site_announcements WHERE id = ? AND is_deleted = 0 LIMIT 1');
    $stmt->execute([$editingId]);
    $editingItem = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $stmt = $pdo->prepare('UPDATE site_announcements SET is_deleted = 1, deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
        $message = 'Announcement moved to archive safely.';
        $messageType = 'success';
    } elseif ($action === 'update' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($title === '' || $description === '') {
            $message = 'Title and description are required.';
            $messageType = 'danger';
        } else {
            $stmt = $pdo->prepare('UPDATE site_announcements SET title = ?, description = ? WHERE id = ? AND is_deleted = 0');
            $stmt->execute([$title, $description, $id]);
            $message = 'Announcement updated successfully.';
            $messageType = 'success';
        }
    } else {
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($title === '' || $description === '') {
            $message = 'Title and description are required.';
            $messageType = 'danger';
        } else {
            $stmt = $pdo->prepare('INSERT INTO site_announcements (title, description, created_at, admin_id) VALUES (?, ?, ?, ?)');
            $stmt->execute([$title, $description, date('Y-m-d H:i:s'), (int) ($_SESSION['admin_id'] ?? 0)]);
            $message = 'Announcement saved successfully.';
            $messageType = 'success';
        }
    }
}

$totalStmt = $pdo->query('SELECT COUNT(*) AS total FROM site_announcements WHERE is_deleted = 0');
$totalRows = (int) $totalStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

$recordsStmt = $pdo->prepare('SELECT * FROM site_announcements WHERE is_deleted = 0 ORDER BY created_at DESC LIMIT ? OFFSET ?');
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
    <title>Announcements CRUD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">📢 Announcements</h2>
            <p class="text-muted mb-0">Manage public announcements securely.</p>
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
                    <h5 class="card-title"><?php echo $editingItem ? 'Edit Announcement' : 'Add Announcement'; ?></h5>
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="<?php echo $editingItem ? 'update' : 'create'; ?>">
                        <?php if ($editingItem): ?>
                            <input type="hidden" name="id" value="<?php echo (int) $editingItem['id']; ?>">
                        <?php endif; ?>
                        <div class="col-12">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" value="<?php echo e($editingItem['title'] ?? ''); ?>" required maxlength="255">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="6" required><?php echo e($editingItem['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-danger"><?php echo $editingItem ? 'Update' : 'Save'; ?></button>
                            <?php if ($editingItem): ?>
                                <a href="admin_add_announcement.php" class="btn btn-outline-secondary">Cancel</a>
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
                        <h5 class="card-title mb-0">Saved Announcements</h5>
                        <span class="badge bg-danger"><?php echo $totalRows; ?> items</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($records): ?>
                                    <?php foreach ($records as $row): ?>
                                        <tr>
                                            <td><?php echo e($row['title']); ?></td>
                                            <td><?php echo e(mb_substr($row['description'], 0, 90)); ?><?php echo mb_strlen($row['description']) > 90 ? '…' : ''; ?></td>
                                            <td><?php echo e($row['created_at']); ?></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="admin_add_announcement.php?edit=<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Archive this announcement?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-muted text-center py-4">No announcements yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav class="mt-3">
                            <ul class="pagination pagination-sm justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="admin_add_announcement.php?page=<?php echo max(1, $page - 1); ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="admin_add_announcement.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="admin_add_announcement.php?page=<?php echo min($totalPages, $page + 1); ?>">Next</a>
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
