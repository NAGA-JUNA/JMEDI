<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
requirePermission('blog');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $pdo->prepare("DELETE FROM posts WHERE post_id = :id")->execute([':id' => (int)$_POST['delete_id']]);
    }
    header('Location: /admin/blog.php?msg=deleted');
    exit;
}

$pageTitle = 'Manage Blog';
require_once __DIR__ . '/../includes/admin_header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $slug = slugify($title);
        $content = trim($_POST['content'] ?? '');
        $author = trim($_POST['author'] ?? 'Admin');
        $status = $_POST['status'] ?? 'draft';

        $featuredImage = null;
        if (!empty($_FILES['featured_image']['name'])) {
            $featuredImage = uploadImage($_FILES['featured_image']);
        }

        if (empty($title)) {
            $error = 'Post title is required.';
        } else {
            if (isset($_POST['post_id']) && $_POST['post_id']) {
                $sql = "UPDATE posts SET title=:title, slug=:slug, content=:content, author=:author, status=:status, updated_at=CURRENT_TIMESTAMP";
                $data = [':title'=>$title, ':slug'=>$slug, ':content'=>$content, ':author'=>$author, ':status'=>$status, ':id'=>(int)$_POST['post_id']];
                if ($featuredImage) {
                    $sql .= ", featured_image=:img";
                    $data[':img'] = $featuredImage;
                }
                $sql .= " WHERE post_id=:id";
                $pdo->prepare($sql)->execute($data);
                $success = 'Post updated.';
            } else {
                $pdo->prepare("INSERT INTO posts (title, slug, content, featured_image, author, status) VALUES (:title, :slug, :content, :img, :author, :status)")
                    ->execute([':title'=>$title, ':slug'=>$slug, ':content'=>$content, ':img'=>$featuredImage, ':author'=>$author, ':status'=>$status]);
                $success = 'Post created.';
            }
            $action = 'list';
        }
    }
}

$editPost = null;
if ($action === 'edit' && $id) {
    $editPost = getPost($pdo, $id);
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') $success = 'Post deleted.';
$allPosts = getPosts($pdo);
?>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="form-card">
    <h5 class="mb-4"><?= $editPost ? 'Edit' : 'New' ?> Blog Post</h5>
    <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <?php if ($editPost): ?><input type="hidden" name="post_id" value="<?= $editPost['post_id'] ?>"><?php endif; ?>
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Title <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" value="<?= e($editPost['title'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Author</label>
                <input type="text" name="author" class="form-control" value="<?= e($editPost['author'] ?? 'Admin') ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Content</label>
                <textarea name="content" class="form-control" rows="10"><?= e($editPost['content'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Featured Image</label>
                <input type="file" name="featured_image" class="form-control" accept="image/*">
            </div>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="draft" <?= ($editPost['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= ($editPost['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary px-4">Save Post</button>
                <a href="/admin/blog.php" class="btn btn-secondary px-4">Cancel</a>
            </div>
        </div>
    </form>
</div>
<?php else: ?>
<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Blog Posts (<?= count($allPosts) ?>)</h5>
        <a href="/admin/blog.php?action=add" class="btn btn-primary"><i class="fas fa-plus me-1"></i> New Post</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Title</th><th>Author</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($allPosts as $p): ?>
                <tr>
                    <td><?= e($p['title']) ?></td>
                    <td><?= e($p['author'] ?? 'Admin') ?></td>
                    <td><span class="badge <?= $p['status'] === 'published' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst($p['status']) ?></span></td>
                    <td><?= formatDate($p['created_at']) ?></td>
                    <td>
                        <a href="/admin/blog.php?action=edit&id=<?= $p['post_id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(generateCSRFToken()) ?>">
                            <input type="hidden" name="delete_id" value="<?= $p['post_id'] ?>">
                            <button type="button" class="btn btn-sm btn-outline-danger" data-delete-trigger data-delete-label="the post '<?= e($p['title']) ?>'"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
