<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
    ]);
}

function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';
}

function isLoggedIn(): bool {
    return isset($_SESSION['admin_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function requireSuperAdmin(): void {
    requireLogin();
    if (getRole() !== 'superadmin') {
        header('Location: /admin/dashboard.php?error=access_denied');
        exit;
    }
}

function requirePermission(string $perm): void {
    requireLogin();
    if (!hasPermission($perm)) {
        header('Location: /admin/dashboard.php?error=access_denied');
        exit;
    }
}

function loginAdmin(PDO $pdo, string $username, string $password): bool {
    $stmt = $pdo->prepare("SELECT admin_id, username, password, full_name, email, role, permissions, avatar, doctor_id FROM admins WHERE username = :username");
    $stmt->execute([':username' => $username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_name'] = $admin['full_name'];
        $_SESSION['admin_email'] = $admin['email'] ?? '';
        $_SESSION['admin_role'] = $admin['role'] ?? 'admin';
        $_SESSION['admin_permissions'] = json_decode($admin['permissions'] ?? '{}', true) ?: [];
        $_SESSION['admin_avatar'] = $admin['avatar'] ?? '';
        $_SESSION['admin_doctor_id'] = $admin['doctor_id'];

        $pdo->prepare("UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE admin_id = :id")->execute([':id' => $admin['admin_id']]);
        return true;
    }
    return false;
}

function getRole(): string {
    return $_SESSION['admin_role'] ?? 'admin';
}

function isSuperAdmin(): bool {
    return getRole() === 'superadmin';
}

function isAdmin(): bool {
    return in_array(getRole(), ['superadmin', 'admin']);
}

function isDoctor(): bool {
    return getRole() === 'doctor';
}

function hasPermission(string $perm): bool {
    if (isSuperAdmin()) return true;
    $perms = $_SESSION['admin_permissions'] ?? [];
    if (!empty($perms['all'])) return true;
    return !empty($perms[$perm]);
}

function getRoleBadge(string $role): string {
    $map = [
        'superadmin' => ['Super Admin', '#dc3545', '#fff'],
        'admin' => ['Admin', '#0d6efd', '#fff'],
        'doctor' => ['Doctor', '#0d9488', '#fff'],
    ];
    $r = $map[$role] ?? ['User', '#6c757d', '#fff'];
    return '<span style="background:' . $r[1] . ';color:' . $r[2] . ';padding:2px 10px;border-radius:20px;font-size:0.72rem;font-weight:600;">' . $r[0] . '</span>';
}

function logoutAdmin(): void {
    session_destroy();
    header('Location: /admin/login.php');
    exit;
}
