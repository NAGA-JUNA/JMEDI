<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
$settings = getSettings($pdo);
$siteName = $settings['site_name'] ?? 'JMedi';
$primaryColor = $settings['primary_color'] ?? '#0D6EFD';
$secondaryColor = $settings['secondary_color'] ?? '#20C997';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$frontendLogo = $settings['frontend_logo'] ?? '';

$navMenus = [];
try {
    $navMenus = $pdo->query("SELECT * FROM menus WHERE status=1 ORDER BY menu_order ASC")->fetchAll();
} catch (Exception $e) {
    $navMenus = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' – ' : '' ?><?= e($siteName) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <link href="/assets/css/hero-slider.css" rel="stylesheet">
    <style>
        :root {
            --primary: <?= e($primaryColor) ?>;
            --secondary: <?= e($secondaryColor) ?>;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body>
<div class="topbar text-white py-2 d-none d-md-block">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <span class="me-4"><i class="fas fa-clock me-1 opacity-75"></i> Mon-Sat: 8:00 AM - 7:00 PM</span>
            <span class="me-4"><i class="fas fa-phone-alt me-1 opacity-75"></i> <?= e($settings['phone'] ?? '') ?></span>
            <span><i class="fas fa-envelope me-1 opacity-75"></i> <?= e($settings['email'] ?? '') ?></span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php if (!empty($settings['facebook'])): ?><a href="<?= e($settings['facebook']) ?>" class="text-white"><i class="fab fa-facebook-f"></i></a><?php endif; ?>
            <?php if (!empty($settings['twitter'])): ?><a href="<?= e($settings['twitter']) ?>" class="text-white"><i class="fab fa-twitter"></i></a><?php endif; ?>
            <?php if (!empty($settings['instagram'])): ?><a href="<?= e($settings['instagram']) ?>" class="text-white"><i class="fab fa-instagram"></i></a><?php endif; ?>
            <?php if (!empty($settings['linkedin'])): ?><a href="<?= e($settings['linkedin']) ?>" class="text-white"><i class="fab fa-linkedin-in"></i></a><?php endif; ?>
        </div>
    </div>
</div>

<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/">
            <?php if ($frontendLogo): ?>
                <img src="<?= e($frontendLogo) ?>" alt="<?= e($siteName) ?>" style="height:45px;width:auto;object-fit:contain;">
            <?php else: ?>
                <span style="color:var(--primary);font-size:1.6rem;">J</span><span style="color:var(--secondary);font-size:1.6rem;">Medi</span>
            <?php endif; ?>
        </a>
        <button class="mob-hamburger d-lg-none" id="mobMenuBtn"
                data-bs-toggle="offcanvas" data-bs-target="#mobileNavDrawer"
                aria-controls="mobileNavDrawer" aria-label="Open navigation">
            <span class="ham-bar"></span>
            <span class="ham-bar"></span>
            <span class="ham-bar"></span>
        </button>
        <div class="collapse navbar-collapse d-none d-lg-flex" id="mainNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                <?php if (!empty($navMenus)): ?>
                    <?php foreach ($navMenus as $menuItem):
                        $menuSlug = basename($menuItem['menu_link'], '.php');
                        $isActive = ($menuItem['menu_link'] === '/' && $currentPage === 'index') || $menuSlug === $currentPage;
                    ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $isActive ? 'active' : '' ?>" href="<?= e($menuItem['menu_link']) ?>"><?= e($menuItem['menu_name']) ?></a>
                    </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>" href="/">Home</a></li>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'departments' ? 'active' : '' ?>" href="/public/departments.php">Departments</a></li>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'doctors' ? 'active' : '' ?>" href="/public/doctors.php">Doctors</a></li>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'blog' ? 'active' : '' ?>" href="/public/blog.php">Blog</a></li>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'contact' ? 'active' : '' ?>" href="/public/contact.php">Contact</a></li>
                <?php endif; ?>
                <li class="nav-item ms-lg-3">
                    <a class="btn btn-primary px-4" href="/public/appointment.php">
                        <i class="fas fa-calendar-check me-1"></i> Appointment
                    </a>
                </li>
                <?php $isPatientLoggedIn = !empty($_SESSION['patient_id']); ?>
                <?php $isAdminLoggedIn = isLoggedIn(); ?>
                <?php if ($isPatientLoggedIn || $isAdminLoggedIn): ?>
                <li class="nav-item ms-lg-2 dropdown">
                    <a class="btn btn-outline-primary px-3 dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php if ($isPatientLoggedIn): ?>
                            <i class="fas fa-user me-1"></i> <?= e($_SESSION['patient_name'] ?? 'Patient') ?>
                        <?php else: ?>
                            <i class="fas fa-user-shield me-1"></i> <?= e($_SESSION['admin_name'] ?? 'Admin') ?>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" style="border-radius:12px;box-shadow:0 8px 25px rgba(0,0,0,0.1);border:none;">
                        <?php if ($isPatientLoggedIn): ?>
                        <li><a class="dropdown-item py-2" href="/public/patient-dashboard.php"><i class="fas fa-th-large me-2 text-muted"></i>My Dashboard</a></li>
                        <li><a class="dropdown-item py-2" href="/public/patient-dashboard.php"><i class="fas fa-calendar-check me-2 text-muted"></i>My Appointments</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2 text-danger" href="/public/patient-logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        <?php endif; ?>
                        <?php if ($isAdminLoggedIn): ?>
                        <li><a class="dropdown-item py-2" href="/admin/"><i class="fas fa-tachometer-alt me-2 text-muted"></i>Admin Panel</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2 text-danger" href="/admin/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item ms-lg-2">
                    <button class="btn btn-outline-primary px-3" data-bs-toggle="modal" data-bs-target="#loginModal">
                        <i class="fas fa-sign-in-alt me-1"></i> Login
                    </button>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Mobile Navigation Drawer -->
<div class="offcanvas offcanvas-end mobile-nav-drawer" tabindex="-1" id="mobileNavDrawer" aria-labelledby="mobileNavLabel">
    <div class="mob-drawer-header">
        <div class="mob-drawer-logo">
            <?php if ($frontendLogo): ?>
                <img src="<?= e($frontendLogo) ?>" alt="<?= e($siteName) ?>" style="height:40px;width:auto;object-fit:contain;filter:brightness(0) invert(1);">
            <?php else: ?>
                <span style="color:#fff;font-size:1.7rem;font-weight:800;letter-spacing:-1px;">J<span style="color:var(--secondary);">Medi</span></span>
            <?php endif; ?>
        </div>
        <button type="button" class="mob-drawer-close" data-bs-dismiss="offcanvas" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="mob-drawer-body">
        <nav class="mob-nav-links">
            <?php if (!empty($navMenus)): ?>
                <?php foreach ($navMenus as $i => $menuItem):
                    $menuSlug = basename($menuItem['menu_link'], '.php');
                    $isActive = ($menuItem['menu_link'] === '/' && $currentPage === 'index') || $menuSlug === $currentPage;
                    $icons = ['/' => 'fa-home', 'departments' => 'fa-hospital', 'doctors' => 'fa-user-md', 'blog' => 'fa-newspaper', 'contact' => 'fa-envelope'];
                    $icon = $icons[$menuSlug] ?? $icons[$menuItem['menu_link']] ?? 'fa-circle';
                ?>
                <a href="<?= e($menuItem['menu_link']) ?>" class="mob-nav-link <?= $isActive ? 'active' : '' ?>" style="--i:<?= $i ?>;">
                    <span class="mob-link-icon"><i class="fas <?= $icon ?>"></i></span>
                    <?= e($menuItem['menu_name']) ?>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <?php
                $defaultLinks = [
                    ['href' => '/',                         'label' => 'Home',        'icon' => 'fa-home',     'key' => 'index'],
                    ['href' => '/public/departments.php',   'label' => 'Departments', 'icon' => 'fa-hospital', 'key' => 'departments'],
                    ['href' => '/public/doctors.php',       'label' => 'Doctors',     'icon' => 'fa-user-md',  'key' => 'doctors'],
                    ['href' => '/public/blog.php',          'label' => 'Blog',        'icon' => 'fa-newspaper','key' => 'blog'],
                    ['href' => '/public/contact.php',       'label' => 'Contact',     'icon' => 'fa-envelope', 'key' => 'contact'],
                ];
                foreach ($defaultLinks as $i => $link): ?>
                <a href="<?= $link['href'] ?>" class="mob-nav-link <?= $currentPage === $link['key'] ? 'active' : '' ?>" style="--i:<?= $i ?>;">
                    <span class="mob-link-icon"><i class="fas <?= $link['icon'] ?>"></i></span>
                    <?= $link['label'] ?>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </nav>

        <div class="mob-drawer-cta">
            <a href="/public/appointment.php" class="btn mob-appt-btn w-100">
                <i class="fas fa-calendar-check me-2"></i> Book Appointment
            </a>
            <?php if (!empty($_SESSION['patient_id'])): ?>
            <a href="/public/patient-dashboard.php" class="btn mob-login-btn w-100 mt-2">
                <i class="fas fa-th-large me-2"></i> My Dashboard
            </a>
            <?php elseif (isLoggedIn()): ?>
            <a href="/admin/" class="btn mob-login-btn w-100 mt-2">
                <i class="fas fa-tachometer-alt me-2"></i> Admin Panel
            </a>
            <?php else: ?>
            <button class="btn mob-login-btn w-100 mt-2" data-bs-dismiss="offcanvas" data-bs-toggle="modal" data-bs-target="#loginModal">
                <i class="fas fa-sign-in-alt me-2"></i> Login / Register
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="mob-drawer-footer">
        <?php if (!empty($settings['phone'])): ?>
        <a href="tel:<?= e($settings['phone']) ?>" class="mob-footer-item">
            <i class="fas fa-phone-alt"></i>
            <span><?= e($settings['phone']) ?></span>
        </a>
        <?php endif; ?>
        <?php if (!empty($settings['email'])): ?>
        <a href="mailto:<?= e($settings['email']) ?>" class="mob-footer-item">
            <i class="fas fa-envelope"></i>
            <span><?= e($settings['email']) ?></span>
        </a>
        <?php endif; ?>
        <div class="mob-footer-item">
            <i class="fas fa-clock"></i>
            <span>Mon–Sat: 8:00 AM – 7:00 PM</span>
        </div>
    </div>
</div>

<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header border-0 text-white px-4 pt-4 pb-3" style="background: linear-gradient(135deg, var(--primary) 0%, #0a58ca 100%);">
                <div>
                    <h5 class="modal-title fw-bold mb-1" id="loginModalLabel"><i class="fas fa-sign-in-alt me-2"></i>Welcome Back</h5>
                    <p class="mb-0 opacity-75 small">Sign in to your account</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <ul class="nav nav-tabs nav-fill border-0" id="loginTabs" role="tablist" style="background:#f8f9fa;">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-semibold py-3 border-0" id="tab-patient" data-bs-toggle="tab" data-bs-target="#panel-patient" type="button" role="tab" aria-controls="panel-patient" aria-selected="true" style="border-radius:0;">
                            <i class="fas fa-user me-1"></i> Patient
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold py-3 border-0" id="tab-staff" data-bs-toggle="tab" data-bs-target="#panel-staff" type="button" role="tab" aria-controls="panel-staff" aria-selected="false" style="border-radius:0;">
                            <i class="fas fa-user-shield me-1"></i> Staff
                        </button>
                    </li>
                </ul>
                <div class="tab-content px-4 py-4">
                    <div class="tab-pane fade show active" id="panel-patient" role="tabpanel" aria-labelledby="tab-patient">
                        <div id="patientLoginAlert" class="alert d-none mb-3" role="alert"></div>

                        <div id="patientLoginView">
                            <form id="patientLoginForm" novalidate>
                                <?= csrfField() ?>
                                <div class="mb-3">
                                    <label for="patientEmail" class="form-label fw-semibold">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-envelope text-muted"></i></span>
                                        <input type="email" class="form-control border-start-0 ps-0" id="patientEmail" placeholder="Enter your email" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="patientPassword" class="form-label fw-semibold">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-key text-muted"></i></span>
                                        <input type="password" class="form-control border-start-0 ps-0" id="patientPassword" placeholder="Enter your password" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" id="patientLoginBtn">
                                    <span class="btn-label"><i class="fas fa-sign-in-alt me-2"></i>Sign In</span>
                                    <span class="btn-spinner d-none"><span class="spinner-border spinner-border-sm me-2"></span>Signing in...</span>
                                </button>
                            </form>
                            <div class="text-center mt-3">
                                <span class="text-muted small">Don't have an account?</span>
                                <a href="#" class="small fw-semibold text-decoration-none" id="showRegisterLink">Create one</a>
                            </div>
                        </div>

                        <div id="patientRegisterView" class="d-none">
                            <form id="patientRegisterForm" novalidate>
                                <?= csrfField() ?>
                                <div class="mb-3">
                                    <label for="regName" class="form-label fw-semibold">Full Name</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-user text-muted"></i></span>
                                        <input type="text" class="form-control border-start-0 ps-0" id="regName" placeholder="Your full name" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="regEmail" class="form-label fw-semibold">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-envelope text-muted"></i></span>
                                        <input type="email" class="form-control border-start-0 ps-0" id="regEmail" placeholder="Your email" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="regPhone" class="form-label fw-semibold">Phone Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-phone text-muted"></i></span>
                                        <input type="tel" class="form-control border-start-0 ps-0" id="regPhone" placeholder="Your phone number" required>
                                    </div>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label for="regPassword" class="form-label fw-semibold">Password</label>
                                        <input type="password" class="form-control" id="regPassword" placeholder="Min 6 chars" required minlength="6">
                                    </div>
                                    <div class="col-6">
                                        <label for="regConfirmPassword" class="form-label fw-semibold">Confirm</label>
                                        <input type="password" class="form-control" id="regConfirmPassword" placeholder="Repeat" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success w-100 py-2 fw-semibold" id="patientRegisterBtn">
                                    <span class="btn-label"><i class="fas fa-user-plus me-2"></i>Create Account</span>
                                    <span class="btn-spinner d-none"><span class="spinner-border spinner-border-sm me-2"></span>Creating...</span>
                                </button>
                            </form>
                            <div class="text-center mt-3">
                                <span class="text-muted small">Already have an account?</span>
                                <a href="#" class="small fw-semibold text-decoration-none" id="showLoginLink">Sign in</a>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="panel-staff" role="tabpanel" aria-labelledby="tab-staff">
                        <div id="loginAlert" class="alert d-none mb-3" role="alert"></div>
                        <form id="loginForm" novalidate>
                            <?= csrfField() ?>
                            <div class="mb-3">
                                <label for="loginUsername" class="form-label fw-semibold">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-user text-muted"></i></span>
                                    <input type="text" class="form-control border-start-0 ps-0" id="loginUsername" name="username" placeholder="Enter your username" required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="loginPassword" class="form-label fw-semibold">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-key text-muted"></i></span>
                                    <input type="password" class="form-control border-start-0 ps-0" id="loginPassword" name="password" placeholder="Enter your password" required>
                                    <button class="btn btn-outline-secondary border-start-0" type="button" id="togglePassword" tabindex="-1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" id="loginSubmitBtn">
                                <span id="loginBtnText"><i class="fas fa-sign-in-alt me-2"></i>Sign In</span>
                                <span id="loginSpinner" class="d-none"><span class="spinner-border spinner-border-sm me-2"></span>Signing in...</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
