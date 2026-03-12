<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
requirePermission('testimonials');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $pdo->prepare("DELETE FROM testimonials WHERE testimonial_id = :id")->execute([':id' => (int)$_POST['delete_id']]);
    }
    header('Location: /admin/testimonials.php?msg=deleted');
    exit;
}

$pageTitle = 'Manage Testimonials';
require_once __DIR__ . '/../includes/admin_header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    } else {
        $patientName = trim($_POST['patient_name'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $rating = (int)($_POST['rating'] ?? 5);
        $status = (int)($_POST['status'] ?? 1);

        if (empty($patientName) || empty($content)) {
            $error = 'Name and content are required.';
        } else {
            if (isset($_POST['testimonial_id']) && $_POST['testimonial_id']) {
                $pdo->prepare("UPDATE testimonials SET patient_name=:name, content=:content, rating=:rating, status=:status WHERE testimonial_id=:id")
                    ->execute([':name'=>$patientName, ':content'=>$content, ':rating'=>$rating, ':status'=>$status, ':id'=>(int)$_POST['testimonial_id']]);
                $success = 'Testimonial updated.';
            } else {
                $pdo->prepare("INSERT INTO testimonials (patient_name, content, rating, status) VALUES (:name, :content, :rating, :status)")
                    ->execute([':name'=>$patientName, ':content'=>$content, ':rating'=>$rating, ':status'=>$status]);
                $success = 'Testimonial added.';
            }
            $action = 'list';
        }
    }
}

$editTest = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM testimonials WHERE testimonial_id = :id");
    $stmt->execute([':id' => $id]);
    $editTest = $stmt->fetch();
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') $success = 'Testimonial deleted.';
$allTests = getTestimonials($pdo, false);
?>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="form-card">
    <h5 class="mb-4"><?= $editTest ? 'Edit' : 'Add' ?> Testimonial</h5>
    <form method="POST">
        <?= csrfField() ?>
        <?php if ($editTest): ?><input type="hidden" name="testimonial_id" value="<?= $editTest['testimonial_id'] ?>"><?php endif; ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Patient Name <span class="text-danger">*</span></label>
                <input type="text" name="patient_name" class="form-control" value="<?= e($editTest['patient_name'] ?? '') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Rating</label>
                <select name="rating" class="form-select">
                    <?php for($i=5;$i>=1;$i--): ?>
                    <option value="<?= $i ?>" <?= ($editTest['rating'] ?? 5) == $i ? 'selected' : '' ?>><?= $i ?> Stars</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="1" <?= ($editTest['status'] ?? 1) == 1 ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= ($editTest['status'] ?? 1) == 0 ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Content <span class="text-danger">*</span></label>
                <textarea name="content" class="form-control" rows="4" required><?= e($editTest['content'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary px-4">Save</button>
                <a href="/admin/testimonials.php" class="btn btn-secondary px-4">Cancel</a>
            </div>
        </div>
    </form>
</div>
<?php else: ?>
<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Testimonials (<?= count($allTests) ?>)</h5>
        <a href="/admin/testimonials.php?action=add" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add Testimonial</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Patient</th><th>Rating</th><th>Content</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($allTests as $t): ?>
                <tr>
                    <td><?= e($t['patient_name']) ?></td>
                    <td><?php for($i=0;$i<$t['rating'];$i++) echo '<i class="fas fa-star text-warning"></i>'; ?></td>
                    <td><?= e(truncateText($t['content'], 80)) ?></td>
                    <td><span class="badge <?= $t['status'] ? 'bg-success' : 'bg-secondary' ?>"><?= $t['status'] ? 'Active' : 'Inactive' ?></span></td>
                    <td>
                        <a href="/admin/testimonials.php?action=edit&id=<?= $t['testimonial_id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(generateCSRFToken()) ?>">
                            <input type="hidden" name="delete_id" value="<?= $t['testimonial_id'] ?>">
                            <button type="button" class="btn btn-sm btn-outline-danger" data-delete-trigger data-delete-label="the testimonial by '<?= e($t['patient_name']) ?>'"><i class="fas fa-trash"></i></button>
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
