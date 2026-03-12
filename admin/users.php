<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $deleteId = (int)$_POST['delete_id'];
        if ($deleteId !== (int)$_SESSION['admin_id']) {
            $pdo->prepare("DELETE FROM admins WHERE admin_id = :id")->execute([':id' => $deleteId]);
        }
    }
    header('Location: /admin/users.php?msg=deleted');
    exit;
}

$pageTitle = 'User Management';
require_once __DIR__ . '/../includes/admin_header.php';
requireSuperAdmin();

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$success = $error = '';

$allDoctors = getDoctors($pdo, null, false);

$permissionsList = [
    'doctors' => 'Doctors',
    'departments' => 'Departments',
    'appointments' => 'Appointments',
    'blog' => 'Blog',
    'testimonials' => 'Testimonials',
    'home_sections' => 'Home Sections',
    'menu_manager' => 'Menu Manager',
    'pages' => 'Pages',
    'settings' => 'Settings',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $role = trim($_POST['role'] ?? 'admin');
        $password = $_POST['password'] ?? '';
        $doctorId = !empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null;

        $perms = [];
        if ($role === 'superadmin') {
            $perms = ['all' => true];
        } elseif ($role === 'doctor') {
            $perms = ['appointments' => true];
        } else {
            foreach ($permissionsList as $key => $label) {
                if (!empty($_POST['perm_' . $key])) {
                    $perms[$key] = true;
                }
            }
        }
        $permsJson = json_encode($perms);

        if (empty($username)) {
            $error = 'Username is required.';
        } elseif (empty($fullName)) {
            $error = 'Full name is required.';
        } elseif (!in_array($role, ['superadmin', 'admin', 'doctor'])) {
            $error = 'Invalid role selected.';
        } else {
            if (!empty($_POST['user_id'])) {
                $userId = (int)$_POST['user_id'];
                $existing = $pdo->prepare("SELECT admin_id FROM admins WHERE username = :u AND admin_id != :id");
                $existing->execute([':u' => $username, ':id' => $userId]);
                if ($existing->fetch()) {
                    $error = 'Username already taken by another user.';
                } else {
                    $sql = "UPDATE admins SET username = :username, email = :email, full_name = :full_name, role = :role, permissions = :permissions, doctor_id = :doctor_id";
                    $params = [
                        ':username' => $username,
                        ':email' => $email,
                        ':full_name' => $fullName,
                        ':role' => $role,
                        ':permissions' => $permsJson,
                        ':doctor_id' => $doctorId,
                        ':id' => $userId,
                    ];
                    if (!empty($password)) {
                        $sql .= ", password = :password";
                        $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $sql .= " WHERE admin_id = :id";
                    $pdo->prepare($sql)->execute($params);
                    $success = 'User updated successfully.';
                    $action = 'list';
                }
            } else {
                if (empty($password)) {
                    $error = 'Password is required for new users.';
                } else {
                    $existing = $pdo->prepare("SELECT admin_id FROM admins WHERE username = :u");
                    $existing->execute([':u' => $username]);
                    if ($existing->fetch()) {
                        $error = 'Username already exists.';
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $pdo->prepare("INSERT INTO admins (username, password, email, full_name, role, permissions, doctor_id) VALUES (:username, :password, :email, :full_name, :role, :permissions, :doctor_id)")->execute([
                            ':username' => $username,
                            ':password' => $hashedPassword,
                            ':email' => $email,
                            ':full_name' => $fullName,
                            ':role' => $role,
                            ':permissions' => $permsJson,
                            ':doctor_id' => $doctorId,
                        ]);
                        $success = 'User created successfully.';
                        $action = 'list';
                    }
                }
            }
        }
    }
}

$editUser = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE admin_id = :id");
    $stmt->execute([':id' => $id]);
    $editUser = $stmt->fetch();
    if (!$editUser) {
        header('Location: /admin/users.php');
        exit;
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') $success = 'User deleted successfully.';

$users = $pdo->query("SELECT admin_id, username, email, full_name, role, permissions, avatar, doctor_id, last_login, created_at FROM admins ORDER BY admin_id ASC")->fetchAll();
?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i><?= e($success) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?= e($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($action === 'list'): ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">User Management</h4>
        <p class="text-muted mb-0" style="font-size:0.9rem;">Manage admin users, roles, and permissions</p>
    </div>
    <a href="/admin/users.php?action=add" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Add New User
    </a>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Linked Doctor</th>
                    <th>Last Login</th>
                    <th>Created</th>
                    <th style="width:120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <?php
                    $userPerms = json_decode($user['permissions'] ?? '{}', true) ?: [];
                    $linkedDoctor = '';
                    if ($user['doctor_id']) {
                        $doc = getDoctor($pdo, (int)$user['doctor_id']);
                        $linkedDoctor = $doc ? $doc['name'] : 'Unknown';
                    }
                ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($user['avatar']): ?>
                            <img src="<?= e($user['avatar']) ?>" style="width:36px;height:36px;border-radius:10px;object-fit:cover;" alt="">
                            <?php else: ?>
                            <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--admin-primary),var(--admin-accent));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.8rem;"><?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)) ?></div>
                            <?php endif; ?>
                            <span class="fw-semibold"><?= e($user['full_name'] ?? '') ?></span>
                        </div>
                    </td>
                    <td><code><?= e($user['username']) ?></code></td>
                    <td><?= e($user['email'] ?? '—') ?></td>
                    <td><?= getRoleBadge($user['role'] ?? 'admin') ?></td>
                    <td><?= $linkedDoctor ? e($linkedDoctor) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= $user['last_login'] ? date('M d, Y h:i A', strtotime($user['last_login'])) : '<span class="text-muted">Never</span>' ?></td>
                    <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="/admin/users.php?action=edit&id=<?= $user['admin_id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                            <?php if ($user['admin_id'] !== (int)$_SESSION['admin_id']): ?>
                            <form method="POST" style="display:inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="delete_id" value="<?= $user['admin_id'] ?>">
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" data-delete-trigger data-delete-label="user '<?= e($user['username']) ?>'"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>

<?php
$isEdit = ($action === 'edit' && $editUser);
$formTitle = $isEdit ? 'Edit User' : 'Add New User';
$formUser = $isEdit ? $editUser : ['username' => '', 'email' => '', 'full_name' => '', 'role' => 'admin', 'permissions' => '{}', 'doctor_id' => null];
$formPerms = json_decode($formUser['permissions'] ?? '{}', true) ?: [];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><?= e($formTitle) ?></h4>
        <p class="text-muted mb-0" style="font-size:0.9rem;"><?= $isEdit ? 'Update user details, role, and permissions' : 'Create a new admin user account' ?></p>
    </div>
    <a href="/admin/users.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Users
    </a>
</div>

<form method="POST" class="form-card" style="max-width:800px;">
    <?= csrfField() ?>
    <input type="hidden" name="save_user" value="1">
    <?php if ($isEdit): ?>
    <input type="hidden" name="user_id" value="<?= $editUser['admin_id'] ?>">
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="username" value="<?= e($formUser['username']) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="full_name" value="<?= e($formUser['full_name'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Email</label>
            <input type="email" class="form-control" name="email" value="<?= e($formUser['email'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Password <?= $isEdit ? '<small class="text-muted">(leave blank to keep current)</small>' : '<span class="text-danger">*</span>' ?></label>
            <input type="password" class="form-control" name="password" <?= $isEdit ? '' : 'required' ?> autocomplete="new-password">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
            <select class="form-select" name="role" id="roleSelect" onchange="togglePermissions()">
                <option value="admin" <?= ($formUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="superadmin" <?= ($formUser['role'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Super Admin</option>
                <option value="doctor" <?= ($formUser['role'] ?? '') === 'doctor' ? 'selected' : '' ?>>Doctor</option>
            </select>
        </div>
        <div class="col-md-6" id="doctorLinkField" style="<?= ($formUser['role'] ?? '') === 'doctor' ? '' : 'display:none;' ?>">
            <label class="form-label fw-semibold">Link to Doctor</label>
            <select class="form-select" name="doctor_id">
                <option value="">— None —</option>
                <?php foreach ($allDoctors as $doc): ?>
                <option value="<?= $doc['doctor_id'] ?>" <?= ($formUser['doctor_id'] ?? null) == $doc['doctor_id'] ? 'selected' : '' ?>><?= e($doc['name']) ?> (<?= e($doc['department_name'] ?? '') ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div id="permissionsBlock" style="<?= in_array($formUser['role'] ?? '', ['superadmin', 'doctor']) ? 'display:none;' : '' ?>">
        <h6 class="fw-bold mb-3"><i class="fas fa-shield-alt me-2 text-muted"></i>Permissions</h6>
        <div class="row g-2 mb-4">
            <?php foreach ($permissionsList as $key => $label): ?>
            <div class="col-md-4 col-6">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="perm_<?= $key ?>" id="perm_<?= $key ?>" value="1" <?= !empty($formPerms[$key]) || !empty($formPerms['all']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="perm_<?= $key ?>"><?= e($label) ?></label>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> <?= $isEdit ? 'Update User' : 'Create User' ?></button>
        <a href="/admin/users.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
function togglePermissions() {
    var role = document.getElementById('roleSelect').value;
    var permBlock = document.getElementById('permissionsBlock');
    var doctorField = document.getElementById('doctorLinkField');
    permBlock.style.display = (role === 'admin') ? '' : 'none';
    doctorField.style.display = (role === 'doctor') ? '' : 'none';
}
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
