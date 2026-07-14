<?php
session_start();
require_once __DIR__ . '/db.php';
requireRole(['admin', 'student'], $pdo);

$lang = getCurrentLanguage();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_lang'])) {
    $lang = setCurrentLanguage($_POST['set_lang']);
}

$role = strtolower((string)($_SESSION['user_role'] ?? ''));
$isAdmin = $role === 'admin' || strpos($role, 'admin') !== false;

$successMessage = '';
$errorMessage = '';
$postType = trim((string)($_GET['type'] ?? $_POST['post_type'] ?? 'blog'));
$postType = in_array($postType, ['blog', 'poetry'], true) ? $postType : 'blog';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content_submit'])) {
    $title = trim((string)($_POST['title'] ?? ''));
    $excerpt = trim((string)($_POST['excerpt'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    $categoryName = trim((string)($_POST['category_name'] ?? ''));
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $status = trim((string)($_POST['status'] ?? 'draft'));
    $status = in_array($status, ['draft', 'published'], true) ? $status : 'draft';
    $featured = !empty($_POST['is_featured']) ? 1 : 0;

    if ($title === '' || $body === '') {
        $errorMessage = 'Title and content are required.';
    } else {
        if ($categoryName === '' && $categoryId > 0) {
            $stmt = $pdo->prepare('SELECT name FROM content_categories WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $categoryId]);
            $categoryName = trim((string)$stmt->fetchColumn());
        }
        if ($categoryName === '') {
            $categoryName = 'General';
        }

        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
        if ($slug === '') {
            $slug = 'post-' . time();
        }

        $stmt = $pdo->prepare('SELECT id FROM content_categories WHERE type = :type AND slug = :slug LIMIT 1');
        $stmt->execute([':type' => $postType, ':slug' => strtolower(str_replace(' ', '-', $categoryName))]);
        $categoryRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($categoryRow) {
            $categoryId = (int)($categoryRow['id'] ?? 0);
        } else {
            $stmt = $pdo->prepare('INSERT INTO content_categories (name, slug, type) VALUES (:name, :slug, :type)');
            $stmt->execute([':name' => $categoryName, ':slug' => strtolower(str_replace(' ', '-', $categoryName)), ':type' => $postType]);
            $categoryId = (int)$pdo->lastInsertId();
        }

        $stmt = $pdo->prepare('INSERT INTO content_posts (author_type, author_id, author_name, title, slug, excerpt, body, post_type, category_id, category_name, status, is_featured) VALUES (:author_type, :author_id, :author_name, :title, :slug, :excerpt, :body, :post_type, :category_id, :category_name, :status, :is_featured)');
        $stmt->execute([
            ':author_type' => $isAdmin ? 'admin' : 'student',
            ':author_id' => $isAdmin ? ($_SESSION['admin_id'] ?? null) : ($_SESSION['student_id'] ?? null),
            ':author_name' => trim((string)($isAdmin ? ($_SESSION['admin_name'] ?? 'Admin') : ($_SESSION['student_name'] ?? 'Student'))),
            ':title' => $title,
            ':slug' => $slug,
            ':excerpt' => $excerpt,
            ':body' => $body,
            ':post_type' => $postType,
            ':category_id' => $categoryId,
            ':category_name' => $categoryName,
            ':status' => $status,
            ':is_featured' => $featured,
        ]);

        $successMessage = $postType === 'poetry' ? 'Poetry post submitted successfully.' : 'Blog post submitted successfully.';
    }
}

$stmt = $pdo->prepare('SELECT * FROM content_categories WHERE type = :type ORDER BY name ASC');
$stmt->execute([':type' => $postType]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT * FROM content_posts WHERE post_type = :type AND status = :status ORDER BY created_at DESC LIMIT 8');
$stmt->execute([':type' => $postType, ':status' => 'published']);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT * FROM content_posts WHERE post_type = :type AND status = :status ORDER BY is_featured DESC, created_at DESC LIMIT 3');
$stmt->execute([':type' => $postType, ':status' => 'published']);
$featuredPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $postType === 'poetry' ? 'Poetry' : 'Blog'; ?> Management</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: linear-gradient(135deg, #f8fbff 0%, #eef2ff 100%); color: #0f172a; }
        .wrap { max-width: 1200px; margin: 24px auto; padding: 24px; }
        .card { background: #fff; border-radius: 18px; box-shadow: 0 16px 38px rgba(15,23,42,0.08); padding: 20px; margin-bottom: 20px; }
        h1, h2, h3 { margin-top: 0; }
        form { display: grid; gap: 12px; }
        label { font-weight: 700; color: #334155; }
        input, textarea, select { width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid #cbd5e1; box-sizing: border-box; font-size: 15px; }
        textarea { min-height: 120px; resize: vertical; }
        .row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 12px 16px; border: none; border-radius: 999px; background: linear-gradient(135deg, #2563eb, #7c3aed); color: white; font-weight: 700; cursor: pointer; text-decoration: none; }
        .muted { color: #64748b; }
        .alert { padding: 12px 14px; border-radius: 12px; margin-bottom: 12px; }
        .alert-success { background: #ecfdf3; color: #047857; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .post-list { display: grid; gap: 12px; }
        .post-item { border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px; background: #f8fafc; }
        .tag { display: inline-block; padding: 4px 8px; border-radius: 999px; background: #ede9fe; color: #5b21b6; font-size: 12px; font-weight: 700; margin-right: 8px; }
        @media (max-width: 768px) { .wrap { padding: 16px; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
            <div>
                <h1><?php echo safe($postType === 'poetry' ? translateText('✍️ ግጥም መጻፍ', '✍️ Poetry Writing') : translateText('📝 ብሎግ እና የመማሪያ ልጥፎች', '📝 Blog & Learning Posts')); ?></h1>
                <p class="muted"><?php echo safe($postType === 'poetry' ? translateText('ግጥም እና የእምነት መልእክቶችን በምድብ እና በፍለጋ ይፃፉ።', 'Create poetry and devotional pieces with categories, search support, and a latest-post widget.') : translateText('ብሎግ ልጥፎችን በምድብ እና በፍለጋ ይፃፉ።', 'Create blog posts and learning updates with categories, search support, and a latest-post widget.')); ?></p>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <a class="btn" href="content_manage.php?type=<?php echo safe($postType); ?>&lang=am">አማርኛ</a>
                <a class="btn" href="content_manage.php?type=<?php echo safe($postType); ?>&lang=en">English</a>
            </div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
            <a class="btn" href="content_manage.php?type=blog"><?php echo safe(translateText('ብሎግ', 'Blog')); ?></a>
            <a class="btn" href="content_manage.php?type=poetry"><?php echo safe(translateText('ግጥም', 'Poetry')); ?></a>
            <a class="btn" href="sofonyas2.php"><?php echo safe(translateText('መነሻ ገፅ', 'Homepage')); ?></a>
            <?php if ($isAdmin): ?><a class="btn" href="admin_dashboard.php"><?php echo safe(translateText('የአስተዳደር ዳሽቦርድ', 'Admin Dashboard')); ?></a><?php endif; ?>
        </div>
    </div>

    <?php if ($successMessage): ?><div class="alert alert-success"><?php echo safe($successMessage); ?></div><?php endif; ?>
    <?php if ($errorMessage): ?><div class="alert alert-error"><?php echo safe($errorMessage); ?></div><?php endif; ?>

    <div class="card">
        <h2><?php echo safe(translateText('አዲስ ' . ($postType === 'poetry' ? 'ግጥም' : 'ልጥፍ') . ' ፍጠር', 'Create a new ' . ($postType === 'poetry' ? 'poem' : 'post'))); ?></h2>
        <form method="post">
            <input type="hidden" name="content_submit" value="1">
            <input type="hidden" name="post_type" value="<?php echo safe($postType); ?>">
            <div class="row">
                <div>
                    <label><?php echo safe(translateText('ርዕስ', 'Title')); ?></label>
                    <input type="text" name="title" required>
                </div>
                <div>
                    <label><?php echo safe(translateText('ምድብ', 'Category')); ?></label>
                    <input type="text" name="category_name" list="category-list" placeholder="<?php echo safe(translateText('አጠቃላይ', 'General')); ?>">
                    <datalist id="category-list">
                        <?php foreach ($categories as $category): ?><option value="<?php echo safe($category['name']); ?>"><?php endforeach; ?>
                    </datalist>
                </div>
            </div>
            <div class="row">
                <div>
                    <label><?php echo safe(translateText('አጭር ማጠቃለያ', 'Short excerpt')); ?></label>
                    <input type="text" name="excerpt" placeholder="<?php echo safe(translateText('አጭር ቅድመ እይታ...', 'Short preview...')); ?>">
                </div>
                <div>
                    <label><?php echo safe(translateText('ሁኔታ', 'Status')); ?></label>
                    <select name="status">
                        <option value="draft"><?php echo safe(translateText('ረቂቅ', 'Draft')); ?></option>
                        <option value="published" selected><?php echo safe(translateText('አሳይ', 'Publish')); ?></option>
                    </select>
                </div>
            </div>
            <label><?php echo safe(translateText('ይዘት', 'Content')); ?></label>
            <textarea name="body" required placeholder="<?php echo safe(translateText('ሙሉ ልጥፍዎን እዚህ ይጻፉ...', 'Write the full post here...')); ?>"></textarea>
            <label><input type="checkbox" name="is_featured" value="1"> <?php echo safe(translateText('እንደ ተመራጭ ምልክት ያድርጉ', 'Mark as featured')); ?></label>
            <button class="btn" type="submit"><?php echo safe(translateText('አስቀምጥ', 'Save') . ' ' . ($postType === 'poetry' ? translateText('ግጥም', 'poem') : translateText('ልጥፍ', 'post'))); ?></button>
        </form>
    </div>

    <div class="card">
        <h2><?php echo safe(translateText('የቅርብ ጊዜ የታተመ', 'Latest published') . ' ' . ($postType === 'poetry' ? translateText('ግጥም', 'poetry') : translateText('ልጥፎች', 'posts'))); ?></h2>
        <?php if (empty($posts)): ?>
            <p class="muted"><?php echo safe(translateText('አሁን ምንም የታተመ ይዘት የለም።', 'No published content yet.')); ?></p>
        <?php else: ?>
            <div class="post-list">
                <?php foreach ($posts as $post): ?>
                    <div class="post-item">
                        <h3><?php echo safe($post['title']); ?></h3>
                        <p class="muted"><?php echo safe($post['excerpt'] ?: substr(strip_tags((string)$post['body']), 0, 140)); ?></p>
                        <div>
                            <span class="tag"><?php echo safe($post['category_name'] ?: 'General'); ?></span>
                            <span class="tag"><?php echo safe($post['post_type']); ?></span>
                            <span class="tag"><?php echo safe($post['status']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
