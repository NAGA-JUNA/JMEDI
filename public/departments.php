<?php
$pageTitle = 'Departments';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

$slug = $_GET['slug'] ?? '';
$departments = getDepartments($pdo);

if ($slug) {
    $department = getDepartmentBySlug($pdo, $slug);
    if (!$department) {
        header('Location: /public/departments.php');
        exit;
    }
    $deptDoctors = getDoctors($pdo, $department['department_id']);
    $pageTitle = $department['name'];
}
?>

<div class="page-header">
    <div class="container">
        <h1><?= $slug ? e($department['name']) : 'Our Departments' ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/">Home</a></li>
                <?php if ($slug): ?>
                <li class="breadcrumb-item"><a href="/public/departments.php">Departments</a></li>
                <li class="breadcrumb-item active"><?= e($department['name']) ?></li>
                <?php else: ?>
                <li class="breadcrumb-item active">Departments</li>
                <?php endif; ?>
            </ol>
        </nav>
    </div>
</div>

<?php if ($slug && isset($department)): ?>
<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="bg-white rounded-3 p-4 shadow-sm">
                    <div class="d-flex align-items-center mb-4">
                        <div class="icon-box me-3" style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:white;">
                            <i class="fas <?= e($department['icon'] ?? 'fa-heartbeat') ?>"></i>
                        </div>
                        <h3 class="mb-0"><?= e($department['name']) ?></h3>
                    </div>
                    <p class="text-muted"><?= nl2br(e($department['description'] ?? '')) ?></p>

                    <?php if (!empty($department['services'])): ?>
                    <h5 class="mt-4 mb-3">Our Services</h5>
                    <div class="row">
                        <?php foreach (explode(',', $department['services']) as $service): ?>
                        <div class="col-md-6 mb-2">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <span><?= e(trim($service)) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="bg-white rounded-3 p-4 shadow-sm">
                    <h5 class="mb-3">All Departments</h5>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($departments as $d): ?>
                        <li class="list-group-item <?= $d['slug'] === $slug ? 'active' : '' ?>">
                            <a href="/public/departments.php?slug=<?= e($d['slug']) ?>" class="text-decoration-none <?= $d['slug'] === $slug ? 'text-white' : '' ?>">
                                <i class="fas <?= e($d['icon'] ?? 'fa-heartbeat') ?> me-2"></i> <?= e($d['name']) ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="bg-primary text-white rounded-3 p-4 mt-4 text-center">
                    <i class="fas fa-calendar-check fs-1 mb-3"></i>
                    <h5>Book an Appointment</h5>
                    <p class="opacity-75 small">Schedule a visit with our specialists</p>
                    <a href="/public/appointment.php" class="btn btn-light">Book Now</a>
                </div>
            </div>
        </div>

        <?php if (!empty($deptDoctors)): ?>
        <div class="mt-5">
            <h4 class="mb-4">Doctors in <?= e($department['name']) ?></h4>
            <div class="row g-4">
                <?php foreach ($deptDoctors as $doc): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="card doctor-card">
                        <div class="doctor-img">
                            <?php if ($doc['photo']): ?>
                                <img src="<?= e($doc['photo']) ?>" alt="<?= e($doc['name']) ?>">
                            <?php else: ?>
                                <i class="fas fa-user-md placeholder-icon"></i>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h5><?= e($doc['name']) ?></h5>
                            <span class="dept-badge"><?= e($doc['department_name'] ?? '') ?></span>
                            <div class="mt-3">
                                <a href="/public/doctor-profile.php?id=<?= $doc['doctor_id'] ?>" class="btn btn-sm btn-outline-primary">View Profile</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php else: ?>
<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <?php foreach ($departments as $dept): ?>
            <div class="col-lg-4 col-md-6">
                <div class="dept-card">
                    <div class="icon-box">
                        <i class="fas <?= e($dept['icon'] ?? 'fa-heartbeat') ?>"></i>
                    </div>
                    <h5><?= e($dept['name']) ?></h5>
                    <p><?= e(truncateText($dept['description'] ?? '', 120)) ?></p>
                    <a href="/public/departments.php?slug=<?= e($dept['slug']) ?>" class="btn btn-sm btn-outline-primary mt-2">Learn More</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
