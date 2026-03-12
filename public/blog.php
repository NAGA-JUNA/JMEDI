<?php
$pageTitle = 'Blog';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

$slug = $_GET['slug'] ?? '';

if ($slug) {
    $post = getPostBySlug($pdo, $slug);
    if (!$post) {
        header('Location: /public/blog.php');
        exit;
    }
    $pageTitle = $post['title'];
}

$posts = $slug ? [] : getPosts($pdo, true);
?>

<div class="page-header">
    <div class="container">
        <h1><?= $slug && isset($post) ? e($post['title']) : 'Medical Blog' ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/">Home</a></li>
                <?php if ($slug): ?>
                <li class="breadcrumb-item"><a href="/public/blog.php">Blog</a></li>
                <li class="breadcrumb-item active"><?= e($post['title'] ?? '') ?></li>
                <?php else: ?>
                <li class="breadcrumb-item active">Blog</li>
                <?php endif; ?>
            </ol>
        </nav>
    </div>
</div>

<?php if ($slug && isset($post)): ?>
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <article class="bg-white rounded-3 p-4 shadow-sm">
                    <?php if ($post['featured_image']): ?>
                        <img src="<?= e($post['featured_image']) ?>" alt="<?= e($post['title']) ?>" class="img-fluid rounded-3 mb-4 w-100" style="max-height:400px;object-fit:cover;">
                    <?php endif; ?>
                    <div class="d-flex align-items-center text-muted mb-3">
                        <span class="me-3"><i class="fas fa-user me-1"></i> <?= e($post['author'] ?? 'Admin') ?></span>
                        <span><i class="fas fa-calendar me-1"></i> <?= formatDate($post['created_at']) ?></span>
                    </div>
                    <div class="post-content">
                        <?= nl2br(e($post['content'] ?? '')) ?>
                    </div>
                </article>
                <div class="mt-4">
                    <a href="/public/blog.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-2"></i>Back to Blog</a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php else: ?>
<section class="py-5">
    <div class="container">
        <?php if (empty($posts)): ?>
            <div class="text-center py-5">
                <i class="fas fa-newspaper text-muted" style="font-size:3rem;"></i>
                <p class="text-muted mt-3">No articles published yet.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($posts as $post): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card blog-card">
                        <div class="blog-img">
                            <?php if ($post['featured_image']): ?>
                                <img src="<?= e($post['featured_image']) ?>" alt="<?= e($post['title']) ?>">
                            <?php else: ?>
                                <i class="fas fa-newspaper placeholder-icon"></i>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <span class="blog-date"><?= formatDate($post['created_at']) ?></span>
                            <h5 class="mt-1"><?= e($post['title']) ?></h5>
                            <p class="text-muted small"><?= e(truncateText($post['content'] ?? '', 120)) ?></p>
                            <a href="/public/blog.php?slug=<?= e($post['slug']) ?>" class="btn btn-sm btn-outline-primary">Read More</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
