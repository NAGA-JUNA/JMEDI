<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
requirePermission('departments');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $pdo->prepare("DELETE FROM departments WHERE department_id = :id")->execute([':id' => (int)$_POST['delete_id']]);
    }
    header('Location: /admin/departments.php?msg=deleted');
    exit;
}

$pageTitle = 'Manage Departments';
require_once __DIR__ . '/../includes/admin_header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $slug = slugify($name);
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fa-heartbeat');
        $services = trim($_POST['services'] ?? '');
        $status = (int)($_POST['status'] ?? 1);

        if (empty($name)) {
            $error = 'Department name is required.';
        } else {
            if (isset($_POST['department_id']) && $_POST['department_id']) {
                $pdo->prepare("UPDATE departments SET name=:name, slug=:slug, description=:desc, icon=:icon, services=:services, status=:status WHERE department_id=:id")
                    ->execute([':name'=>$name, ':slug'=>$slug, ':desc'=>$description, ':icon'=>$icon, ':services'=>$services, ':status'=>$status, ':id'=>(int)$_POST['department_id']]);
                $success = 'Department updated.';
            } else {
                $pdo->prepare("INSERT INTO departments (name, slug, description, icon, services, status) VALUES (:name, :slug, :desc, :icon, :services, :status)")
                    ->execute([':name'=>$name, ':slug'=>$slug, ':desc'=>$description, ':icon'=>$icon, ':services'=>$services, ':status'=>$status]);
                $success = 'Department added.';
            }
            $action = 'list';
        }
    }
}

$editDept = null;
if ($action === 'edit' && $id) {
    $editDept = getDepartment($pdo, $id);
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') $success = 'Department deleted.';
$allDepts = getDepartments($pdo, false);
?>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="form-card">
    <h5 class="mb-4"><?= $editDept ? 'Edit' : 'Add New' ?> Department</h5>
    <form method="POST">
        <?= csrfField() ?>
        <?php if ($editDept): ?><input type="hidden" name="department_id" value="<?= $editDept['department_id'] ?>"><?php endif; ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="<?= e($editDept['name'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Icon (Font Awesome class)</label>
                <input type="text" name="icon" class="form-control" value="<?= e($editDept['icon'] ?? 'fa-heartbeat') ?>" placeholder="fa-heartbeat">
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="4"><?= e($editDept['description'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label">Services (comma-separated)</label>
                <input type="text" name="services" class="form-control" value="<?= e($editDept['services'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="1" <?= ($editDept['status'] ?? 1) == 1 ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= ($editDept['status'] ?? 1) == 0 ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary px-4">Save</button>
                <a href="/admin/departments.php" class="btn btn-secondary px-4">Cancel</a>
            </div>
        </div>
    </form>
</div>
<?php else: ?>
<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">All Departments (<?= count($allDepts) ?>)</h5>
        <a href="/admin/departments.php?action=add" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add Department</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Icon</th><th>Name</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($allDepts as $d): ?>
                <tr>
                    <td><i class="fas <?= e($d['icon']) ?> text-primary fs-5"></i></td>
                    <td><?= e($d['name']) ?></td>
                    <td><span class="badge <?= $d['status'] ? 'bg-success' : 'bg-secondary' ?>"><?= $d['status'] ? 'Active' : 'Inactive' ?></span></td>
                    <td>
                        <a href="/admin/departments.php?action=edit&id=<?= $d['department_id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(generateCSRFToken()) ?>">
                            <input type="hidden" name="delete_id" value="<?= $d['department_id'] ?>">
                            <button type="button" class="btn btn-sm btn-outline-danger" data-delete-trigger data-delete-label="the '<?= e($d['name']) ?>' department"><i class="fas fa-trash"></i></button>
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
