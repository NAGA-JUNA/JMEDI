<?php
$pageTitle = 'My Dashboard';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['patient_id'])) {
    header('Location: /public/patient-login.php');
    exit;
}

$patientId = (int)$_SESSION['patient_id'];
$patientName = $_SESSION['patient_name'] ?? 'Patient';
$patientEmail = $_SESSION['patient_email'] ?? '';

$statusFilter = $_GET['status'] ?? 'all';
$allowedStatuses = ['all', 'pending', 'confirmed', 'completed', 'cancelled'];
if (!in_array($statusFilter, $allowedStatuses)) {
    $statusFilter = 'all';
}

$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) as total,
        COUNT(*) FILTER (WHERE appointment_date >= CURRENT_DATE AND status IN ('pending','confirmed')) as upcoming,
        COUNT(*) FILTER (WHERE status = 'completed') as completed,
        COUNT(*) FILTER (WHERE status = 'cancelled') as cancelled
    FROM appointments
    WHERE patient_id = :pid OR email = :email
");
$statsStmt->execute([':pid' => $patientId, ':email' => $patientEmail]);
$stats = $statsStmt->fetch();

$sql = "SELECT a.*, d.name as doctor_name, dep.name as department_name
        FROM appointments a
        LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
        LEFT JOIN departments dep ON a.department_id = dep.department_id
        WHERE (a.patient_id = :pid OR a.email = :email)";
$params = [':pid' => $patientId, ':email' => $patientEmail];

if ($statusFilter !== 'all') {
    $sql .= " AND a.status = :status";
    $params[':status'] = $statusFilter;
}

$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.dashboard-hero {
    background: linear-gradient(135deg, #0D6EFD 0%, #0a58ca 60%, #0842a0 100%);
    padding: 48px 0 80px;
    color: #fff;
    position: relative;
    overflow: hidden;
}
.dashboard-hero::before {
    content: '';
    position: absolute;
    top: -80px;
    right: -60px;
    width: 300px;
    height: 300px;
    border-radius: 50%;
    background: rgba(255,255,255,0.05);
}
.dashboard-hero::after {
    content: '';
    position: absolute;
    bottom: -100px;
    left: -40px;
    width: 250px;
    height: 250px;
    border-radius: 50%;
    background: rgba(255,255,255,0.03);
}
.stat-cards {
    margin-top: -50px;
    position: relative;
    z-index: 10;
}
.stat-card {
    background: #fff;
    border-radius: 14px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
    gap: 16px;
    transition: transform 0.3s, box-shadow 0.3s;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(13,110,253,0.12);
}
.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}
.stat-card .stat-value {
    font-size: 1.8rem;
    font-weight: 800;
    line-height: 1;
    color: #0e1b2a;
}
.stat-card .stat-label {
    font-size: 0.85rem;
    color: #888;
    font-weight: 500;
    margin-top: 2px;
}
.filter-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 20px;
}
.filter-pills a {
    padding: 8px 20px;
    border-radius: 30px;
    font-size: 0.88rem;
    font-weight: 600;
    text-decoration: none;
    border: 2px solid #e9ecef;
    color: #555;
    transition: all 0.2s;
}
.filter-pills a:hover {
    border-color: #0D6EFD;
    color: #0D6EFD;
}
.filter-pills a.active {
    background: #0D6EFD;
    color: #fff;
    border-color: #0D6EFD;
}
.appt-table {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    overflow: hidden;
}
.appt-table table {
    margin-bottom: 0;
}
.appt-table thead th {
    background: #f8f9fa;
    border-bottom: 2px solid #e9ecef;
    font-weight: 700;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #555;
    padding: 14px 16px;
}
.appt-table tbody td {
    padding: 14px 16px;
    vertical-align: middle;
    font-size: 0.92rem;
    border-bottom: 1px solid #f0f0f0;
}
.appt-table tbody tr:last-child td {
    border-bottom: none;
}
.appt-table tbody tr:hover {
    background: #f8fbff;
}
.badge-status {
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: capitalize;
}
.badge-pending { background: #fff3cd; color: #856404; }
.badge-confirmed { background: #d1ecf1; color: #0c5460; }
.badge-completed { background: #d4edda; color: #155724; }
.badge-cancelled { background: #f8d7da; color: #721c24; }
.empty-state {
    text-align: center;
    padding: 60px 20px;
}
.empty-state i {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 16px;
}
.empty-state h5 {
    color: #adb5bd;
    font-weight: 700;
}
.empty-state p {
    color: #adb5bd;
    max-width: 400px;
    margin: 0 auto;
}
</style>

<div class="dashboard-hero">
    <div class="container">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h2 class="fw-bold mb-1" style="color:#fff;">
                    <i class="fas fa-hand-wave me-2" style="opacity:0.8;"></i>Welcome, <?= e($patientName) ?>!
                </h2>
                <p class="mb-0" style="opacity:0.8;">Manage your appointments and health records</p>
            </div>
            <div class="d-flex gap-2">
                <a href="/public/appointment.php" class="btn btn-light fw-semibold px-4">
                    <i class="fas fa-calendar-plus me-1"></i> Book New Appointment
                </a>
                <a href="/public/patient-logout.php" class="btn btn-outline-light fw-semibold px-3">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="stat-cards mb-4">
        <div class="row g-3">
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(13,110,253,0.1);color:#0D6EFD;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?= (int)$stats['total'] ?></div>
                        <div class="stat-label">Total</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(255,193,7,0.1);color:#ffc107;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?= (int)$stats['upcoming'] ?></div>
                        <div class="stat-label">Upcoming</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(25,135,84,0.1);color:#198754;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?= (int)$stats['completed'] ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(220,53,69,0.1);color:#dc3545;">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?= (int)$stats['cancelled'] ?></div>
                        <div class="stat-label">Cancelled</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
            <h4 class="fw-bold mb-0"><i class="fas fa-list-alt me-2 text-primary"></i>My Appointments</h4>
        </div>

        <div class="filter-pills">
            <a href="?status=all" class="<?= $statusFilter === 'all' ? 'active' : '' ?>">All</a>
            <a href="?status=pending" class="<?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
            <a href="?status=confirmed" class="<?= $statusFilter === 'confirmed' ? 'active' : '' ?>">Confirmed</a>
            <a href="?status=completed" class="<?= $statusFilter === 'completed' ? 'active' : '' ?>">Completed</a>
            <a href="?status=cancelled" class="<?= $statusFilter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
        </div>

        <?php if (empty($appointments)): ?>
        <div class="appt-table">
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h5>No Appointments Found</h5>
                <p><?= $statusFilter !== 'all' ? 'No ' . e($statusFilter) . ' appointments.' : 'You haven\'t booked any appointments yet.' ?></p>
                <a href="/public/appointment.php" class="btn btn-primary mt-3 px-4">
                    <i class="fas fa-calendar-plus me-1"></i> Book Your First Appointment
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="appt-table">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Doctor</th>
                            <th>Department</th>
                            <th>Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appt): ?>
                        <tr>
                            <td>
                                <i class="fas fa-calendar-day text-primary me-1"></i>
                                <?= date('M d, Y', strtotime($appt['appointment_date'])) ?>
                            </td>
                            <td>
                                <i class="fas fa-clock text-muted me-1"></i>
                                <?= date('h:i A', strtotime($appt['appointment_time'])) ?>
                            </td>
                            <td>
                                <strong><?= e($appt['doctor_name'] ?? 'Not Assigned') ?></strong>
                            </td>
                            <td><?= e($appt['department_name'] ?? 'General') ?></td>
                            <td>
                                <?php
                                    $type = $appt['consultation_type'] ?? 'clinic';
                                    $typeIcon = $type === 'online' ? 'fa-video' : ($type === 'clinic' ? 'fa-hospital' : 'fa-exchange-alt');
                                    $typeLabel = ucfirst($type);
                                ?>
                                <i class="fas <?= $typeIcon ?> me-1 text-muted"></i><?= $typeLabel ?>
                            </td>
                            <td>
                                <span class="badge-status badge-<?= e($appt['status']) ?>"><?= ucfirst(e($appt['status'])) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div style="padding-bottom:40px;"></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
