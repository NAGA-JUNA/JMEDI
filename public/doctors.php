<?php
$pageTitle = 'Our Doctors';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

$deptId = isset($_GET['department']) ? (int)$_GET['department'] : null;
$search = $_GET['search'] ?? '';
$departments = getDepartments($pdo);

if ($search) {
    $stmt = $pdo->prepare("SELECT d.*, dep.name as department_name FROM doctors d LEFT JOIN departments dep ON d.department_id = dep.department_id WHERE d.status = 1 AND (d.name LIKE :s OR d.specialization LIKE :s2) ORDER BY d.name");
    $stmt->execute([':s' => "%$search%", ':s2' => "%$search%"]);
    $doctors = $stmt->fetchAll();
} else {
    $doctors = getDoctors($pdo, $deptId);
}
?>

<div class="page-header">
    <div class="container">
        <h1>Our Doctors</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/">Home</a></li>
                <li class="breadcrumb-item active">Doctors</li>
            </ol>
        </nav>
    </div>
</div>

<section class="py-5">
    <div class="container">
        <div class="row mb-4">
            <div class="col-md-6">
                <form class="d-flex" method="GET">
                    <input type="text" name="search" class="form-control me-2" placeholder="Search doctors..." value="<?= e($search) ?>">
                    <button class="btn btn-primary" type="submit">Search</button>
                </form>
            </div>
            <div class="col-md-4 ms-auto mt-3 mt-md-0">
                <select id="deptFilter" class="form-select">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?= $dept['department_id'] ?>" <?= $deptId == $dept['department_id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row g-4">
            <?php if (empty($doctors)): ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-user-md text-muted" style="font-size:3rem;"></i>
                    <p class="text-muted mt-3">No doctors found.</p>
                </div>
            <?php else: ?>
                <?php foreach ($doctors as $doc): ?>
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
                            <p class="text-muted small mt-2 mb-2"><?= e($doc['specialization'] ?? '') ?></p>
                            <a href="<?= $doc['slug'] ? '/doctor/' . e($doc['slug']) : '/public/doctor-profile.php?id=' . $doc['doctor_id'] ?>" class="btn btn-sm btn-outline-primary">View Profile</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
