<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
requireLogin();

$adminPage = basename($_SERVER['PHP_SELF'], '.php');
$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminEmail = $_SESSION['admin_email'] ?? '';
$adminRole = getRole();
$adminInitials = strtoupper(substr($adminName, 0, 1));
$adminAvatar = $_SESSION['admin_avatar'] ?? '';

$pendingNotif = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn();

$adminLogoUrl  = getSetting($pdo, 'admin_logo', '');
$siteNameFull  = getSetting($pdo, 'site_name', 'JMedi');
$adminUsername = $_SESSION['admin_username'] ?? 'admin';

if (isDoctor()) {
    $docId = (int)($_SESSION['admin_doctor_id'] ?? 0);
    $stDocPending = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = :d AND status = 'pending'");
    $stDocPending->execute([':d' => $docId]);
    $pendingNotif = (int)$stDocPending->fetchColumn();

    $stRecentPend = $pdo->prepare("SELECT patient_name, appointment_date, appointment_time, appointment_id FROM appointments WHERE doctor_id = :d AND status = 'pending' ORDER BY created_at DESC LIMIT 5");
    $stRecentPend->execute([':d' => $docId]);
    $recentPending = $stRecentPend->fetchAll();

    $stSidebarDoc = $pdo->prepare("SELECT d.name, d.photo, d.specialization, d.qualification, dep.name AS department_name FROM doctors d LEFT JOIN departments dep ON d.department_id = dep.department_id WHERE d.doctor_id = :id");
    $stSidebarDoc->execute([':id' => $docId]);
    $sidebarDoctor = $stSidebarDoc->fetch();
} else {
    $recentPending = $pdo->query("SELECT patient_name, appointment_date, appointment_time, appointment_id FROM appointments WHERE status = 'pending' ORDER BY created_at DESC LIMIT 5")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' – ' : '' ?>JMedi Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/admin.css" rel="stylesheet">
</head>
<body>
<div class="admin-wrapper">
    <nav class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <a href="/admin/dashboard.php" class="sidebar-brand text-white text-decoration-none">
                <?php if (!empty($adminLogoUrl)): ?>
                <img src="<?= e($adminLogoUrl) ?>" class="sidebar-brand-logo" alt="<?= e($siteNameFull) ?>">
                <?php else: ?>
                <span class="brand-icon"><i class="fas fa-heartbeat"></i></span>
                <span class="brand-text"><?= e($siteNameFull) ?></span>
                <?php endif; ?>
            </a>
            <button class="sidebar-collapse-btn" id="sbCollapseBtn" title="Collapse sidebar"><i class="fas fa-chevron-left"></i></button>
        </div>
        <div class="sidebar-body">

        <?php if (isDoctor() && !empty($sidebarDoctor)): ?>
        <div class="dr-sidebar-profile">
            <?php $drSidebarName = preg_replace('/^Dr\.?\s*/i', '', $sidebarDoctor['name']); ?>
            <?php if (!empty($sidebarDoctor['photo'])): ?>
            <img src="<?= e($sidebarDoctor['photo']) ?>" class="dr-sb-photo" alt="Dr. <?= e($drSidebarName) ?>">
            <?php else: ?>
            <div class="dr-sb-avatar"><?= strtoupper(substr($drSidebarName, 0, 1)) ?></div>
            <?php endif; ?>
            <div class="dr-sb-name">Dr. <?= e($drSidebarName) ?></div>
            <?php if (!empty($sidebarDoctor['specialization'])): ?><div class="dr-sb-spec"><?= e($sidebarDoctor['specialization']) ?></div><?php endif; ?>
            <?php if (!empty($sidebarDoctor['qualification'])): ?>
            <div class="dr-sb-qual"><?= e($sidebarDoctor['qualification']) ?></div>
            <?php elseif (!empty($sidebarDoctor['department_name'])): ?>
            <div class="dr-sb-qual"><?= e($sidebarDoctor['department_name']) ?></div>
            <?php endif; ?>
            <div class="dr-sb-divider"></div>
        </div>
        <?php endif; ?>

        <div class="sidebar-section-label" data-section="menu" role="button" tabindex="0" aria-expanded="true" aria-controls="section-menu"><span>Overview</span><i class="fas fa-chevron-down section-arrow"></i></div>
        <ul class="sidebar-nav" data-section-list="menu" id="section-menu">
            <li class="<?= $adminPage === 'dashboard' ? 'active' : '' ?>">
                <a href="/admin/dashboard.php" data-tooltip="Dashboard"><i class="fas fa-th-large"></i><span class="nav-label">Dashboard</span></a>
            </li>
            <?php if (hasPermission('doctors')): ?>
            <li class="<?= $adminPage === 'doctors' ? 'active' : '' ?>">
                <a href="/admin/doctors.php" data-tooltip="Doctors"><i class="fas fa-user-md"></i><span class="nav-label">Doctors</span></a>
            </li>
            <li class="<?= $adminPage === 'doctor-schedules' ? 'active' : '' ?>">
                <a href="/admin/doctor-schedules.php" data-tooltip="Schedules"><i class="fas fa-calendar-alt"></i><span class="nav-label">Schedules</span></a>
            </li>
            <?php endif; ?>
            <?php if (hasPermission('departments')): ?>
            <li class="<?= $adminPage === 'departments' ? 'active' : '' ?>">
                <a href="/admin/departments.php" data-tooltip="Departments"><i class="fas fa-hospital"></i><span class="nav-label">Departments</span></a>
            </li>
            <?php endif; ?>
            <?php if (hasPermission('appointments')): ?>
            <li class="<?= $adminPage === 'appointments' ? 'active' : '' ?>">
                <a href="/admin/appointments.php" data-tooltip="Appointments">
                    <i class="fas fa-calendar-check"></i>
                    <span class="nav-label">Appointments</span>
                    <?php if ($pendingNotif > 0): ?><span class="sb-badge"><?= $pendingNotif ?></span><?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            <?php if (isDoctor()): ?>
            <li class="<?= $adminPage === 'appointments' ? 'active' : '' ?>">
                <a href="/admin/appointments.php" data-tooltip="My Appointments">
                    <i class="fas fa-calendar-check"></i>
                    <span class="nav-label">My Appointments</span>
                    <?php if ($pendingNotif > 0): ?><span class="sb-badge"><?= $pendingNotif ?></span><?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            <?php if (hasPermission('blog')): ?>
            <li class="<?= $adminPage === 'blog' ? 'active' : '' ?>">
                <a href="/admin/blog.php" data-tooltip="Blog Posts"><i class="fas fa-newspaper"></i><span class="nav-label">Blog Posts</span></a>
            </li>
            <?php endif; ?>
            <?php if (hasPermission('testimonials')): ?>
            <li class="<?= $adminPage === 'testimonials' ? 'active' : '' ?>">
                <a href="/admin/testimonials.php" data-tooltip="Testimonials"><i class="fas fa-comments"></i><span class="nav-label">Testimonials</span></a>
            </li>
            <?php endif; ?>
        </ul>

        <?php if (hasPermission('home_sections') || hasPermission('menu_manager') || hasPermission('pages')): ?>
        <div class="sidebar-section-label" data-section="cms" role="button" tabindex="0" aria-expanded="true" aria-controls="section-cms"><span>CMS</span><i class="fas fa-chevron-down section-arrow"></i></div>
        <ul class="sidebar-nav" data-section-list="cms" id="section-cms">
            <?php if (hasPermission('home_sections')): ?>
            <li class="<?= $adminPage === 'home-sections' ? 'active' : '' ?>">
                <a href="/admin/home-sections.php" data-tooltip="Home Sections"><i class="fas fa-home"></i><span class="nav-label">Home Sections</span></a>
            </li>
            <?php endif; ?>
            <?php if (hasPermission('menu_manager')): ?>
            <li class="<?= $adminPage === 'menu-manager' ? 'active' : '' ?>">
                <a href="/admin/menu-manager.php" data-tooltip="Menu Manager"><i class="fas fa-bars"></i><span class="nav-label">Menu Manager</span></a>
            </li>
            <?php endif; ?>
            <?php if (hasPermission('pages')): ?>
            <li class="<?= $adminPage === 'pages' ? 'active' : '' ?>">
                <a href="/admin/pages.php" data-tooltip="Page Editor"><i class="fas fa-file-alt"></i><span class="nav-label">Page Editor</span></a>
            </li>
            <?php endif; ?>
        </ul>
        <?php endif; ?>

        <?php if (isSuperAdmin()): ?>
        <div class="sidebar-section-label" data-section="superadmin" role="button" tabindex="0" aria-expanded="true" aria-controls="section-superadmin"><span>Super Admin</span><i class="fas fa-chevron-down section-arrow"></i></div>
        <ul class="sidebar-nav" data-section-list="superadmin" id="section-superadmin">
            <li class="<?= $adminPage === 'users' ? 'active' : '' ?>">
                <a href="/admin/users.php" data-tooltip="Users"><i class="fas fa-users-cog"></i><span class="nav-label">User Management</span></a>
            </li>
            <li class="<?= $adminPage === 'database' ? 'active' : '' ?>">
                <a href="/admin/database.php" data-tooltip="Database"><i class="fas fa-database"></i><span class="nav-label">Database Tools</span></a>
            </li>
            <li class="<?= $adminPage === 'backup' ? 'active' : '' ?>">
                <a href="/admin/backup.php" data-tooltip="Backup"><i class="fas fa-download"></i><span class="nav-label">Site Backup</span></a>
            </li>
        </ul>
        <?php endif; ?>

        <div class="sidebar-section-label" data-section="other" role="button" tabindex="0" aria-expanded="true" aria-controls="section-other"><span>General</span><i class="fas fa-chevron-down section-arrow"></i></div>
        <ul class="sidebar-nav" data-section-list="other" id="section-other">
            <?php if (hasPermission('settings')): ?>
            <li class="<?= $adminPage === 'settings' ? 'active' : '' ?>">
                <a href="/admin/settings.php" data-tooltip="Settings"><i class="fas fa-cog"></i><span class="nav-label">Settings</span></a>
            </li>
            <li class="<?= $adminPage === 'email-templates' ? 'active' : '' ?>">
                <a href="/admin/email-templates.php" data-tooltip="Email Templates"><i class="fas fa-envelope-open-text"></i><span class="nav-label">Email Templates</span></a>
            </li>
            <?php endif; ?>
            <li class="<?= $adminPage === 'profile' ? 'active' : '' ?>">
                <a href="/admin/profile.php" data-tooltip="My Profile"><i class="fas fa-user-circle"></i><span class="nav-label">My Profile</span></a>
            </li>
            <li>
                <a href="/" data-tooltip="View Website" target="_blank"><i class="fas fa-globe"></i><span class="nav-label">View Website</span></a>
            </li>
            <li>
                <a href="/admin/logout.php" data-tooltip="Logout" style="color:rgba(239,68,68,0.7);" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='rgba(239,68,68,0.7)'"><i class="fas fa-sign-out-alt"></i><span class="nav-label">Logout</span></a>
            </li>
        </ul>

        </div><!-- end sidebar-body -->

        <div class="sidebar-footer">
            <?php if ($adminAvatar): ?>
            <div class="sb-footer-avatar"><img src="<?= e($adminAvatar) ?>" alt=""></div>
            <?php else: ?>
            <div class="sb-footer-avatar"><?= $adminInitials ?></div>
            <?php endif; ?>
            <div class="sb-footer-info">
                <div class="sb-footer-name"><?= e($adminName) ?></div>
                <div class="sb-footer-role"><?= ucfirst($adminRole) ?></div>
            </div>
            <a href="/admin/logout.php" class="sb-footer-logout" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </nav>

    <div class="admin-content">
        <header class="admin-topbar d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-link p-0 text-dark" id="sidebarToggle" title="Toggle Sidebar"><i class="fas fa-bars fs-5" id="sidebarToggleIcon"></i></button>
                <div class="topbar-search d-none d-md-block">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search here..." class="form-control">
                </div>
            </div>
            <div class="topbar-actions">
                <button class="topbar-icon-btn" id="darkModeToggle" title="Toggle Dark / Light Mode">
                    <i class="fas fa-moon" id="darkModeIcon"></i>
                </button>

                <button class="topbar-icon-btn" id="fullscreenBtn" title="Toggle Fullscreen" onclick="toggleFullscreen()">
                    <i class="fas fa-expand" id="fullscreenIcon"></i>
                </button>

                <div class="dropdown">
                    <button class="notification-btn" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell"></i>
                        <?php if ($pendingNotif > 0): ?><span class="notification-dot"></span><?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end notification-dropdown" style="width:320px;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,0.12);border:none;padding:0;">
                        <div style="padding:14px 18px;border-bottom:1px solid #f0f0f0;">
                            <h6 style="margin:0;font-weight:700;">Notifications</h6>
                            <?php if ($pendingNotif > 0): ?><small class="text-muted"><?= $pendingNotif ?> pending appointment(s)</small><?php endif; ?>
                        </div>
                        <div style="max-height:280px;overflow-y:auto;">
                            <?php if (empty($recentPending)): ?>
                            <div style="padding:20px;text-align:center;color:#999;"><i class="fas fa-check-circle" style="font-size:1.5rem;"></i><p class="mb-0 mt-2 small">All caught up!</p></div>
                            <?php else: ?>
                            <?php foreach ($recentPending as $np): ?>
                            <a href="/admin/appointments.php?view=<?= $np['appointment_id'] ?>" class="d-flex align-items-center gap-3 text-decoration-none" style="padding:10px 18px;border-bottom:1px solid #f8f8f8;color:#333;">
                                <div style="width:36px;height:36px;border-radius:50%;background:#fff3cd;color:#856404;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-clock" style="font-size:0.85rem;"></i></div>
                                <div>
                                    <div style="font-weight:600;font-size:0.88rem;"><?= e($np['patient_name']) ?></div>
                                    <small style="color:#999;"><?= date('M d', strtotime($np['appointment_date'])) ?> at <?= date('h:i A', strtotime($np['appointment_time'])) ?></small>
                                </div>
                            </a>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <a href="/admin/appointments.php?status=pending" class="d-block text-center text-decoration-none" style="padding:10px;font-size:0.85rem;font-weight:600;color:#0d9488;border-top:1px solid #f0f0f0;">View All Pending</a>
                    </div>
                </div>

                <div class="dropdown">
                    <button class="topbar-avatar-btn" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php if ($adminAvatar): ?>
                        <img src="<?= e($adminAvatar) ?>" class="topbar-avatar-img" alt="">
                        <?php else: ?>
                        <div class="topbar-avatar"><?= $adminInitials ?></div>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end pd-wrap">
                        <?php if (!empty($adminLogoUrl)): ?>
                        <div class="pd-brand-bar">
                            <img src="<?= e($adminLogoUrl) ?>" class="pd-brand-img" alt="<?= e($siteNameFull) ?>">
                        </div>
                        <?php endif; ?>
                        <div class="pd-user-block">
                            <div class="pd-avatar-ring">
                                <?php if ($adminAvatar): ?>
                                <img src="<?= e($adminAvatar) ?>" class="pd-avatar-img" alt="">
                                <?php else: ?>
                                <div class="pd-avatar-init"><?= $adminInitials ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="pd-name"><?= e($adminName) ?></div>
                            <div class="pd-email"><?= e($adminEmail) ?></div>
                            <div class="pd-badge-wrap"><?= getRoleBadge($adminRole) ?></div>
                        </div>
                        <div class="pd-meta">
                            <div class="pd-meta-row">
                                <span class="pd-meta-lbl">Username</span>
                                <span class="pd-meta-val"><?= e($adminUsername) ?></span>
                            </div>
                            <div class="pd-meta-row">
                                <span class="pd-meta-lbl">Role</span>
                                <span class="pd-meta-val"><?= ucfirst($adminRole) ?></span>
                            </div>
                        </div>
                        <div class="pd-actions">
                            <a href="/admin/profile.php" class="pd-action"><i class="fas fa-user-circle"></i>My Profile</a>
                            <a href="/admin/profile.php?tab=password" class="pd-action"><i class="fas fa-key"></i>Change Password</a>
                            <?php if (isSuperAdmin()): ?>
                            <a href="/admin/users.php" class="pd-action"><i class="fas fa-users-cog"></i>User Management</a>
                            <?php endif; ?>
                        </div>
                        <div class="pd-footer-bar">
                            <a href="/admin/logout.php" class="pd-logout"><i class="fas fa-sign-out-alt"></i>Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <main class="admin-main p-4">
