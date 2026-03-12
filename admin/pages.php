<?php
$pageTitle = 'Page Editor';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../includes/admin_header.php';
requirePermission('pages');

$success = '';
$error   = '';
$editPage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        $id       = (int)($_POST['id'] ?? 0);
        $title    = trim($_POST['page_title'] ?? '');
        $content  = $_POST['page_content'] ?? '';
        $pageMeta = trim($_POST['page_meta'] ?? '');

        if ($id) {
            $stmt = $pdo->prepare("UPDATE pages SET page_title=:title, page_content=:content, page_meta=:meta, updated_at=CURRENT_TIMESTAMP WHERE id=:id");
            $stmt->execute([':title' => $title, ':content' => $content, ':meta' => $pageMeta, ':id' => $id]);
            $success = 'Page content updated successfully.';
        }
    }

    if ($action === 'create') {
        $name    = trim($_POST['page_name'] ?? '');
        $slug    = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_POST['page_slug'] ?? '')));
        $title   = trim($_POST['page_title'] ?? '');
        if ($name && $slug) {
            try {
                $stmt = $pdo->prepare("INSERT INTO pages (page_name, page_slug, page_title, page_content, page_meta) VALUES (:name, :slug, :title, '', '')");
                $stmt->execute([':name' => $name, ':slug' => $slug, ':title' => $title]);
                $success = "Page \"$name\" created.";
            } catch (\PDOException $e) {
                $error = 'Slug already exists. Choose a different slug.';
            }
        } else {
            $error = 'Page name and slug are required.';
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM pages WHERE id=:id")->execute([':id' => $id]);
            $success = 'Page deleted.';
        }
    }
}

if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE id=:id");
    $stmt->execute([':id' => $editId]);
    $editPage = $stmt->fetch();
}

$pages = $pdo->query("SELECT * FROM pages ORDER BY id ASC")->fetchAll();

$pageIcons = [
    'home'        => 'fa-home',
    'about'       => 'fa-info-circle',
    'doctors'     => 'fa-user-md',
    'departments' => 'fa-hospital',
    'blog'        => 'fa-newspaper',
    'contact'     => 'fa-envelope',
    'appointment' => 'fa-calendar-check',
    'privacy'     => 'fa-shield-alt',
    'terms'       => 'fa-file-contract',
];
?>

<?php if ($success): ?><div class="alert alert-success alert-dismissible fade show" style="border-radius:10px;border:none;"><i class="fas fa-check-circle me-2"></i><?= e($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show" style="border-radius:10px;border:none;"><i class="fas fa-exclamation-circle me-2"></i><?= e($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<?php if ($editPage): ?>
<div class="dash-card mb-4">
    <div class="card-header-row">
        <h6><i class="fas fa-edit me-2" style="color:var(--admin-accent);"></i>Editing: <?= e($editPage['page_name']) ?></h6>
        <a href="/admin/pages.php" class="tab-btn"><i class="fas fa-arrow-left me-1"></i>Back to Pages</a>
    </div>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $editPage['id'] ?>">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Page Title <small class="text-muted">(shown in browser tab)</small></label>
                <input type="text" name="page_title" class="form-control" value="<?= e($editPage['page_title'] ?? '') ?>" style="border-radius:10px;">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Meta Description <small class="text-muted">(for SEO)</small></label>
                <input type="text" name="page_meta" class="form-control" value="<?= e($editPage['page_meta'] ?? '') ?>" style="border-radius:10px;" placeholder="Brief description for search engines">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Page Content</label>
            <textarea name="page_content" id="pageEditor" class="form-control" rows="15"><?= e($editPage['page_content'] ?? '') ?></textarea>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4" style="border-radius:10px;"><i class="fas fa-save me-2"></i>Save Changes</button>
            <a href="/admin/pages.php" class="btn btn-outline-secondary px-4" style="border-radius:10px;">Cancel</a>
        </div>
    </form>
</div>
<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
<script>
ClassicEditor.create(document.querySelector('#pageEditor'), {
    toolbar: ['heading', '|', 'bold', 'italic', 'underline', 'strikethrough', '|', 'bulletedList', 'numberedList', '|', 'link', 'blockQuote', '|', 'insertTable', '|', 'undo', 'redo'],
    heading: {
        options: [
            { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
            { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
            { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' },
            { model: 'heading4', view: 'h4', title: 'Heading 4', class: 'ck-heading_heading4' }
        ]
    }
}).then(function(editor) {
    editor.editing.view.change(function(writer) {
        writer.setStyle('min-height', '300px', editor.editing.view.document.getRoot());
    });
}).catch(console.error);
</script>

<?php else: ?>

<div class="row g-4">
    <div class="col-12">
        <div class="dash-card">
            <div class="card-header-row">
                <h6><i class="fas fa-file-alt me-2" style="color:var(--admin-accent);"></i>Pages</h6>
                <button class="tab-btn" onclick="document.getElementById('createPagePanel').classList.toggle('d-none')">
                    <i class="fas fa-plus me-1"></i>New Page
                </button>
            </div>

            <div id="createPagePanel" class="d-none mb-4 p-3 rounded-3" style="background:var(--admin-bg);border:1px dashed var(--admin-border);">
                <h6 class="fw-semibold mb-3"><i class="fas fa-plus-circle me-2 text-primary"></i>Create New Page</h6>
                <form method="POST" class="row g-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Page Name <span class="text-danger">*</span></label>
                        <input type="text" name="page_name" class="form-control" placeholder="e.g. Privacy Policy" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Slug <span class="text-danger">*</span> <small class="text-muted">(URL key)</small></label>
                        <div class="input-group">
                            <span class="input-group-text text-muted small">/</span>
                            <input type="text" name="page_slug" id="newSlug" class="form-control" placeholder="privacy-policy" required pattern="[a-z0-9\-]+">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Page Title</label>
                        <input type="text" name="page_title" class="form-control" placeholder="SEO title (optional)">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus me-1"></i>Create</button>
                    </div>
                </form>
            </div>

            <?php if (empty($pages)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-file-alt fa-3x mb-3 opacity-25"></i>
                <p class="mb-1">No pages yet.</p>
                <small>Click <strong>New Page</strong> above to create your first page.</small>
            </div>
            <?php else: ?>
            <div class="row g-3">
                <?php foreach ($pages as $page): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="page-card">
                        <div class="page-card-icon">
                            <i class="fas <?= $pageIcons[$page['page_slug']] ?? ($page['page_icon'] ?? 'fa-file') ?>"></i>
                        </div>
                        <h6 class="page-card-name"><?= e($page['page_name']) ?></h6>
                        <p class="page-card-slug">/<?= e($page['page_slug']) ?></p>
                        <p class="page-card-title"><?= e($page['page_title'] ?: 'No title set') ?></p>
                        <div class="page-card-meta">
                            <small><i class="fas fa-clock me-1"></i>Updated: <?= $page['updated_at'] ? date('M j, Y', strtotime($page['updated_at'])) : 'Never' ?></small>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <a href="/admin/pages.php?edit=<?= $page['id'] ?>" class="btn btn-primary btn-sm flex-grow-1" style="border-radius:10px;">
                                <i class="fas fa-pen me-1"></i>Edit
                            </a>
                            <form method="POST" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $page['id'] ?>">
                                <button type="button" class="btn btn-outline-danger btn-sm" style="border-radius:10px;" data-delete-trigger data-delete-label="the '<?= e(addslashes($page['page_name'])) ?>' page">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('newSlug') && document.querySelector('[name="page_name"]').addEventListener('input', function() {
    document.getElementById('newSlug').value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
});
</script>

<?php endif; ?>

<style>
.page-card {
    background: var(--admin-card);
    border: 1px solid var(--admin-border);
    border-radius: 14px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.page-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.06);
    border-color: var(--admin-accent);
}
.page-card-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: rgba(34,197,94,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 1.3rem;
    color: var(--admin-accent);
}
.page-card-name {
    font-weight: 700;
    color: var(--admin-text);
    margin-bottom: 0.25rem;
    font-size: 1rem;
}
.page-card-slug {
    font-size: 0.75rem;
    color: var(--admin-accent);
    font-weight: 600;
    margin-bottom: 0.5rem;
    font-family: monospace;
}
.page-card-title {
    font-size: 0.82rem;
    color: var(--admin-text-muted);
    flex: 1;
}
.page-card-meta {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
    border-top: 1px solid var(--admin-border);
    padding-top: 0.75rem;
    margin-top: 0.5rem;
}
.ck-editor__editable {
    min-height: 300px;
    border-radius: 0 0 10px 10px !important;
}
</style>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
