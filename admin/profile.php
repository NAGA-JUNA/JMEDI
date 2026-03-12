<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/admin_header.php';

$success = '';
$error = '';
$tab = $_GET['tab'] ?? 'profile';

$stmt = $pdo->prepare("SELECT * FROM admins WHERE admin_id = :id");
$stmt->execute([':id' => $_SESSION['admin_id']]);
$admin = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (empty($fullName)) {
                $error = 'Full name is required.';
            } else {
                $avatarPath = $admin['avatar'] ?? '';

                if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $uploaded = uploadImage($_FILES['avatar'], 'uploads');
                    if ($uploaded) {
                        $avatarPath = $uploaded;
                    } else {
                        $error = 'Failed to upload avatar. Only JPEG, PNG, GIF, WebP under 5MB allowed.';
                    }
                }

                if (empty($error)) {
                    $stmt = $pdo->prepare("UPDATE admins SET full_name = :name, email = :email, avatar = :avatar WHERE admin_id = :id");
                    $stmt->execute([
                        ':name' => $fullName,
                        ':email' => $email,
                        ':avatar' => $avatarPath,
                        ':id' => $_SESSION['admin_id']
                    ]);

                    $_SESSION['admin_name'] = $fullName;
                    $_SESSION['admin_email'] = $email;
                    $_SESSION['admin_avatar'] = $avatarPath;

                    $admin['full_name'] = $fullName;
                    $admin['email'] = $email;
                    $admin['avatar'] = $avatarPath;

                    $success = 'Profile updated successfully.';
                    $tab = 'profile';
                }
            }
        }

        if ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $error = 'All password fields are required.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New password and confirmation do not match.';
            } elseif (strlen($newPassword) < 6) {
                $error = 'New password must be at least 6 characters.';
            } elseif (!password_verify($currentPassword, $admin['password'])) {
                $error = 'Current password is incorrect.';
            } else {
                $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE admins SET password = :pass WHERE admin_id = :id");
                $stmt->execute([':pass' => $hashed, ':id' => $_SESSION['admin_id']]);

                $success = 'Password changed successfully.';
                $tab = 'password';
            }
        }

        if ($action === 'remove_avatar') {
            $stmt = $pdo->prepare("UPDATE admins SET avatar = '' WHERE admin_id = :id");
            $stmt->execute([':id' => $_SESSION['admin_id']]);
            $_SESSION['admin_avatar'] = '';
            $admin['avatar'] = '';
            $success = 'Avatar removed successfully.';
            $tab = 'profile';
        }
    }
}

$initials = strtoupper(substr($admin['full_name'] ?? 'A', 0, 1));
?>

<div class="greeting-section d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h4>My Profile</h4>
        <p>Manage your account settings</p>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius:12px;border:none;">
    <i class="fas fa-check-circle me-2"></i><?= e($success) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius:12px;border:none;">
    <i class="fas fa-exclamation-circle me-2"></i><?= e($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="form-card text-center">
            <?php if (!empty($admin['avatar'])): ?>
            <img src="<?= e($admin['avatar']) ?>" alt="Avatar" style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:4px solid var(--admin-border);margin-bottom:1rem;">
            <?php else: ?>
            <div style="width:100px;height:100px;border-radius:50%;background:linear-gradient(135deg, var(--admin-primary), var(--admin-accent));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:2.5rem;margin:0 auto 1rem;"><?= $initials ?></div>
            <?php endif; ?>
            <h5 style="font-weight:700;margin-bottom:0.25rem;"><?= e($admin['full_name'] ?? '') ?></h5>
            <p class="text-muted mb-2" style="font-size:0.88rem;"><?= e($admin['email'] ?? '') ?></p>
            <?= getRoleBadge($admin['role'] ?? 'admin') ?>
            <hr style="margin:1.25rem 0;">
            <div class="text-start" style="font-size:0.88rem;">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Username</span>
                    <span style="font-weight:600;"><?= e($admin['username'] ?? '') ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Role</span>
                    <span style="font-weight:600;"><?= ucfirst($admin['role'] ?? 'admin') ?></span>
                </div>
                <?php if (!empty($admin['last_login'])): ?>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Last Login</span>
                    <span style="font-weight:600;"><?= date('M d, Y h:i A', strtotime($admin['last_login'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="form-card">
            <ul class="nav nav-tabs mb-4" style="border-bottom:2px solid var(--admin-border);">
                <li class="nav-item">
                    <a class="nav-link <?= $tab === 'profile' ? 'active' : '' ?>" href="/admin/profile.php" style="font-weight:600;font-size:0.9rem;border-radius:8px 8px 0 0;">
                        <i class="fas fa-user me-2"></i>Edit Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $tab === 'password' ? 'active' : '' ?>" href="/admin/profile.php?tab=password" style="font-weight:600;font-size:0.9rem;border-radius:8px 8px 0 0;">
                        <i class="fas fa-key me-2"></i>Change Password
                    </a>
                </li>
            </ul>

            <?php if ($tab === 'profile'): ?>
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_profile">

                <div class="mb-3">
                    <label class="form-label" style="font-weight:600;font-size:0.88rem;">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" value="<?= e($admin['full_name'] ?? '') ?>" required style="border-radius:10px;">
                </div>

                <div class="mb-3">
                    <label class="form-label" style="font-weight:600;font-size:0.88rem;">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?= e($admin['email'] ?? '') ?>" style="border-radius:10px;">
                </div>

                <div class="mb-3">
                    <label class="form-label" style="font-weight:600;font-size:0.88rem;">Username</label>
                    <input type="text" class="form-control" value="<?= e($admin['username'] ?? '') ?>" disabled style="border-radius:10px;background:#f8f9fa;">
                    <small class="text-muted">Username cannot be changed.</small>
                </div>

                <div class="mb-4">
                    <label class="form-label" style="font-weight:600;font-size:0.88rem;">Avatar</label>
                    <input type="file" name="avatar" class="form-control" accept="image/*" style="border-radius:10px;">
                    <small class="text-muted">JPEG, PNG, GIF, or WebP. Max 5MB.</small>
                    <?php if (!empty($admin['avatar'])): ?>
                    <div class="mt-2">
                        <img src="<?= e($admin['avatar']) ?>" alt="Current avatar" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid var(--admin-border);vertical-align:middle;">
                        <button type="submit" name="action" value="remove_avatar" class="btn btn-sm btn-outline-danger ms-2" style="border-radius:8px;font-size:0.8rem;" onclick="return confirm('Remove your avatar?')">
                            <i class="fas fa-trash me-1"></i>Remove
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary" style="border-radius:10px;padding:0.55rem 2rem;font-weight:600;">
                    <i class="fas fa-save me-2"></i>Save Changes
                </button>
            </form>

            <?php else: ?>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="change_password">

                <div class="mb-3">
                    <label class="form-label" style="font-weight:600;font-size:0.88rem;">Current Password <span class="text-danger">*</span></label>
                    <input type="password" name="current_password" class="form-control" required style="border-radius:10px;">
                </div>

                <div class="mb-3">
                    <label class="form-label" style="font-weight:600;font-size:0.88rem;">New Password <span class="text-danger">*</span></label>
                    <input type="password" name="new_password" class="form-control" required minlength="6" style="border-radius:10px;">
                    <small class="text-muted">Minimum 6 characters.</small>
                </div>

                <div class="mb-4">
                    <label class="form-label" style="font-weight:600;font-size:0.88rem;">Confirm New Password <span class="text-danger">*</span></label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6" style="border-radius:10px;">
                </div>

                <button type="submit" class="btn btn-primary" style="border-radius:10px;padding:0.55rem 2rem;font-weight:600;">
                    <i class="fas fa-lock me-2"></i>Update Password
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
