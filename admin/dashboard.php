<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/admin_header.php';

if (isDoctor()) {
    $docId = $_SESSION['admin_doctor_id'] ?? 0;

    $doctorInfo = $pdo->prepare("SELECT d.*, dep.name as department_name FROM doctors d LEFT JOIN departments dep ON d.department_id = dep.department_id WHERE d.doctor_id = :id");
    $doctorInfo->execute([':id' => $docId]);
    $doctorInfo = $doctorInfo->fetch();

    $today = date('Y-m-d');

    $todayAppts = $pdo->prepare("SELECT * FROM appointments WHERE doctor_id = :d AND appointment_date = :t ORDER BY appointment_time ASC");
    $todayAppts->execute([':d' => $docId, ':t' => $today]);
    $todayAppts = $todayAppts->fetchAll();
    $myTodayCount = count($todayAppts);

    $stTotal = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = :d");
    $stTotal->execute([':d' => $docId]); $myTotalAppts = (int)$stTotal->fetchColumn();

    $stPend = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = :d AND status = 'pending'");
    $stPend->execute([':d' => $docId]); $myPending = (int)$stPend->fetchColumn();

    $stConf = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = :d AND status = 'confirmed'");
    $stConf->execute([':d' => $docId]); $myConfirmed = (int)$stConf->fetchColumn();

    $stComp = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = :d AND status = 'completed'");
    $stComp->execute([':d' => $docId]); $myCompleted = (int)$stComp->fetchColumn();

    $stCanc = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = :d AND status = 'cancelled'");
    $stCanc->execute([':d' => $docId]); $myCancelled = (int)$stCanc->fetchColumn();

    $nextPatient = $pdo->prepare("SELECT * FROM appointments WHERE doctor_id = :d AND appointment_date >= :t AND status IN ('pending','confirmed') ORDER BY appointment_date ASC, appointment_time ASC LIMIT 1");
    $nextPatient->execute([':d' => $docId, ':t' => $today]);
    $nextPatient = $nextPatient->fetch();

    $pendingRequests = $pdo->prepare("SELECT * FROM appointments WHERE doctor_id = :d AND status = 'pending' ORDER BY created_at DESC LIMIT 5");
    $pendingRequests->execute([':d' => $docId]);
    $pendingRequests = $pendingRequests->fetchAll();

    $dayOfWeek = date('N');
    $weekStart = date('Y-m-d', strtotime('-' . ($dayOfWeek - 1) . ' days'));
    $calDays = [];
    for ($i = 0; $i < 7; $i++) {
        $d = date('Y-m-d', strtotime("+$i days", strtotime($weekStart)));
        $calDays[] = ['date' => $d, 'num' => date('j', strtotime($d)), 'name' => strtolower(substr(date('D', strtotime($d)), 0, 2)), 'active' => $d === $today];
    }

    $drName = $doctorInfo['name'] ?? $_SESSION['admin_name'];
    $drName = preg_replace('/^Dr\.?\s*/i', '', $drName);
?>

<div class="greeting-section d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4>Welcome back, Dr. <?= e($drName) ?> 👋</h4>
        <p><?= e($doctorInfo['specialization'] ?? 'Doctor') ?><?= $doctorInfo['department_name'] ? ' — ' . e($doctorInfo['department_name']) : '' ?></p>
    </div>
    <div class="greeting-date"><i class="fas fa-calendar-alt"></i> <?= date('l, jS F Y') ?></div>
</div>

<!-- Top Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="dr-stat-card" style="background:#eef4ff;">
            <div class="dr-stat-icon" style="background:#c7d9ff;color:#3b5bdb;"><i class="fas fa-users"></i></div>
            <div>
                <div class="dr-stat-label">Total Patients</div>
                <div class="dr-stat-num"><?= $myTotalAppts ?></div>
                <div class="dr-stat-sub">Till today</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dr-stat-card" style="background:#f0fdf4;">
            <div class="dr-stat-icon" style="background:#bbf7d0;color:#15803d;"><i class="fas fa-user-check"></i></div>
            <div>
                <div class="dr-stat-label">Today's Patients</div>
                <div class="dr-stat-num"><?= str_pad($myTodayCount, 2, '0', STR_PAD_LEFT) ?></div>
                <div class="dr-stat-sub"><?= date('d M Y') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dr-stat-card" style="background:#fdf4ff;">
            <div class="dr-stat-icon" style="background:#e9d5ff;color:#7c3aed;"><i class="fas fa-calendar-check"></i></div>
            <div>
                <div class="dr-stat-label">Today's Appointments</div>
                <div class="dr-stat-num"><?= str_pad($myTodayCount, 2, '0', STR_PAD_LEFT) ?></div>
                <div class="dr-stat-sub"><?= date('d M Y') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Middle Row -->
<div class="row g-3 mb-4">
    <!-- Donut Chart -->
    <div class="col-xl-4">
        <div class="table-card h-100">
            <h6 class="fw-bold mb-3"><i class="fas fa-chart-pie me-2" style="color:var(--admin-accent);"></i>Patient Summary</h6>
            <div style="position:relative;height:180px;display:flex;align-items:center;justify-content:center;">
                <canvas id="drDonutChart" style="max-height:180px;"></canvas>
            </div>
            <div class="mt-3 d-flex flex-column gap-2">
                <div class="dr-legend-item"><span class="dr-legend-dot" style="background:#e5e7eb;"></span>Pending<span class="ms-auto fw-bold"><?= $myPending ?></span></div>
                <div class="dr-legend-item"><span class="dr-legend-dot" style="background:#f59e0b;"></span>Confirmed<span class="ms-auto fw-bold"><?= $myConfirmed ?></span></div>
                <div class="dr-legend-item"><span class="dr-legend-dot" style="background:#22c55e;"></span>Completed<span class="ms-auto fw-bold"><?= $myCompleted ?></span></div>
                <div class="dr-legend-item"><span class="dr-legend-dot" style="background:#ef4444;"></span>Cancelled<span class="ms-auto fw-bold"><?= $myCancelled ?></span></div>
            </div>
        </div>
    </div>

    <!-- Today's Appointments -->
    <div class="col-xl-4">
        <div class="table-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="fas fa-calendar-day me-2" style="color:var(--admin-accent);"></i>Today's Appointments</h6>
                <span class="badge" style="background:rgba(34,197,94,0.12);color:#15803d;border-radius:20px;padding:0.3em 0.7em;font-size:0.75rem;"><?= $myTodayCount ?> total</span>
            </div>
            <?php if (empty($todayAppts)): ?>
            <div class="text-center py-4 text-muted">
                <i class="fas fa-calendar-check" style="font-size:2rem;opacity:0.25;"></i>
                <p class="mt-2 mb-0 small">No appointments today</p>
            </div>
            <?php else: ?>
            <div style="max-height:300px;overflow-y:auto;">
                <?php foreach ($todayAppts as $ta): ?>
                <div class="dr-appt-row">
                    <div class="dr-appt-avatar"><?= strtoupper(substr($ta['patient_name'], 0, 1)) ?></div>
                    <div class="dr-appt-info">
                        <div class="dr-appt-name"><?= e($ta['patient_name']) ?></div>
                        <div class="dr-appt-note"><?= e($ta['appointment_notes'] ?? $ta['consultation_type'] ?? 'Consultation') ?></div>
                    </div>
                    <?php if ($ta['appointment_time']): ?>
                    <div class="dr-appt-time"><?= date('h:i A', strtotime($ta['appointment_time'])) ?></div>
                    <?php else: ?>
                    <span class="dr-appt-status <?= $ta['status'] ?>"><?= ucfirst($ta['status']) ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <a href="/admin/appointments.php" class="d-block text-center mt-3" style="font-size:0.82rem;font-weight:600;color:var(--admin-accent);text-decoration:none;">See All &raquo;</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Next Patient Details -->
    <div class="col-xl-4">
        <div class="table-card h-100">
            <h6 class="fw-bold mb-3"><i class="fas fa-user-clock me-2" style="color:var(--admin-accent);"></i>Next Patient</h6>
            <?php if ($nextPatient): ?>
            <div class="text-center mb-3">
                <div class="dr-next-avatar"><?= strtoupper(substr($nextPatient['patient_name'], 0, 1)) ?></div>
                <div class="fw-bold mt-2"><?= e($nextPatient['patient_name']) ?></div>
                <div class="text-muted small"><?= e($nextPatient['appointment_notes'] ?? $nextPatient['consultation_type'] ?? 'Consultation') ?></div>
            </div>
            <div class="dr-info-grid">
                <div class="dr-info-cell"><div class="dr-info-label">Date</div><div class="dr-info-val"><?= date('d M Y', strtotime($nextPatient['appointment_date'])) ?></div></div>
                <div class="dr-info-cell"><div class="dr-info-label">Time</div><div class="dr-info-val"><?= $nextPatient['appointment_time'] ? date('h:i A', strtotime($nextPatient['appointment_time'])) : '—' ?></div></div>
                <div class="dr-info-cell"><div class="dr-info-label">Type</div><div class="dr-info-val"><?= e(ucfirst($nextPatient['consultation_type'] ?? 'In-person')) ?></div></div>
                <div class="dr-info-cell"><div class="dr-info-label">Status</div><div class="dr-info-val"><span class="rp-status <?= $nextPatient['status'] ?>"><?= ucfirst($nextPatient['status']) ?></span></div></div>
            </div>
            <?php if ($nextPatient['patient_email'] ?? ''): ?>
            <div class="mt-3 d-flex gap-2">
                <a href="mailto:<?= e($nextPatient['patient_email']) ?>" class="btn btn-sm btn-outline-primary flex-fill" style="border-radius:8px;font-size:0.8rem;"><i class="fas fa-envelope me-1"></i>Email</a>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="text-center py-4 text-muted">
                <i class="fas fa-user-clock" style="font-size:2rem;opacity:0.25;"></i>
                <p class="mt-2 mb-0 small">No upcoming patients</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bottom Row -->
<div class="row g-3 mb-4">
    <!-- Patient Review -->
    <div class="col-xl-4">
        <div class="table-card h-100">
            <h6 class="fw-bold mb-3"><i class="fas fa-star me-2" style="color:#f59e0b;"></i>Appointment Status</h6>
            <?php
            $reviewTotal = max(1, $myTotalAppts);
            $reviews = [
                ['label' => 'Completed', 'count' => $myCompleted, 'color' => '#22c55e'],
                ['label' => 'Confirmed', 'count' => $myConfirmed, 'color' => '#3b82f6'],
                ['label' => 'Pending',   'count' => $myPending,   'color' => '#f59e0b'],
                ['label' => 'Cancelled', 'count' => $myCancelled, 'color' => '#ef4444'],
            ];
            foreach ($reviews as $rv): $pct = round(($rv['count'] / $reviewTotal) * 100);
            ?>
            <div class="dr-review-row">
                <span class="dr-review-label"><?= $rv['label'] ?></span>
                <div class="dr-review-bar-wrap">
                    <div class="dr-review-bar" style="width:<?= $pct ?>%;background:<?= $rv['color'] ?>;"></div>
                </div>
                <span class="dr-review-count"><?= $rv['count'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Appointment Requests -->
    <div class="col-xl-4">
        <div class="table-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="fas fa-bell me-2" style="color:#f59e0b;"></i>Appointment Requests</h6>
                <?php if ($myPending > 0): ?><span class="badge bg-warning text-dark" style="border-radius:20px;"><?= $myPending ?> pending</span><?php endif; ?>
            </div>
            <?php if (empty($pendingRequests)): ?>
            <div class="text-center py-4 text-muted">
                <i class="fas fa-check-circle" style="font-size:2rem;opacity:0.25;color:#22c55e;"></i>
                <p class="mt-2 mb-0 small">No pending requests</p>
            </div>
            <?php else: ?>
            <?php foreach ($pendingRequests as $pr): ?>
            <div class="dr-req-row">
                <div class="dr-appt-avatar" style="width:36px;height:36px;font-size:0.8rem;"><?= strtoupper(substr($pr['patient_name'], 0, 1)) ?></div>
                <div class="dr-appt-info">
                    <div class="dr-appt-name"><?= e($pr['patient_name']) ?></div>
                    <div class="dr-appt-note"><?= e($pr['appointment_notes'] ?? $pr['consultation_type'] ?? 'Consultation') ?></div>
                </div>
                <div class="d-flex gap-1">
                    <form method="POST" action="/admin/appointments.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="appointment_id" value="<?= $pr['appointment_id'] ?>">
                        <input type="hidden" name="apt_action" value="confirm">
                        <button type="submit" class="dr-req-btn confirm" title="Confirm"><i class="fas fa-check"></i></button>
                    </form>
                    <form method="POST" action="/admin/appointments.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="appointment_id" value="<?= $pr['appointment_id'] ?>">
                        <input type="hidden" name="apt_action" value="cancel">
                        <button type="button" class="dr-req-btn cancel" title="Cancel" data-delete-trigger data-delete-label="appointment for <?= e($pr['patient_name']) ?>"><i class="fas fa-times"></i></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            <a href="/admin/appointments.php?status=pending" class="d-block text-center mt-3" style="font-size:0.82rem;font-weight:600;color:var(--admin-accent);text-decoration:none;">See All &raquo;</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Calendar -->
    <div class="col-xl-4">
        <div class="table-card h-100 calendar-widget">
            <div class="cal-header">
                <h6><i class="fas fa-calendar me-2" style="color:var(--admin-accent);"></i><?= date('F Y') ?></h6>
            </div>
            <div class="cal-days mb-3">
                <?php foreach ($calDays as $cd): ?>
                <div class="cal-day <?= $cd['active'] ? 'active' : '' ?>">
                    <span class="day-num"><?= $cd['num'] ?></span>
                    <span class="day-name"><?= $cd['name'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php
            $todayHasAppts = !empty($todayAppts);
            if ($todayHasAppts): ?>
            <?php foreach (array_slice($todayAppts, 0, 3) as $ta): ?>
            <div class="schedule-item">
                <span class="schedule-time"><?= $ta['appointment_time'] ? date('H:i', strtotime($ta['appointment_time'])) : '--:--' ?></span>
                <div class="schedule-event">
                    <h6><?= e($ta['patient_name']) ?></h6>
                    <small><?= ucfirst($ta['status']) ?></small>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="schedule-item">
                <span class="schedule-time">--:--</span>
                <div class="schedule-event"><h6>No appointments today</h6><small>Schedule is clear</small></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
    var ctx = document.getElementById('drDonutChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Pending','Confirmed','Completed','Cancelled'],
            datasets: [{
                data: [<?= $myPending ?>, <?= $myConfirmed ?>, <?= $myCompleted ?>, <?= $myCancelled ?>],
                backgroundColor: ['#e5e7eb','#f59e0b','#22c55e','#ef4444'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            cutout: '68%',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1a2e1f',
                    cornerRadius: 8,
                    padding: 10,
                    titleFont: { family: 'Plus Jakarta Sans', weight: '600' },
                    bodyFont: { family: 'Plus Jakarta Sans' }
                }
            }
        }
    });
})();
</script>

<?php
} else {
    $totalDoctors = getCount($pdo, 'doctors');
    $totalDepts = getCount($pdo, 'departments');
    $totalAppointments = getCount($pdo, 'appointments');
    $totalPosts = getCount($pdo, 'posts');
    $totalTestimonials = getCount($pdo, 'testimonials');
    $totalSlides = getCount($pdo, 'hero_slides');

    $recentAppointments = $pdo->query("SELECT a.*, d.name as doctor_name, dep.name as department_name FROM appointments a LEFT JOIN doctors d ON a.doctor_id = d.doctor_id LEFT JOIN departments dep ON a.department_id = dep.department_id ORDER BY a.created_at DESC LIMIT 5")->fetchAll();

    $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn();
    $confirmedCount = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'confirmed'")->fetchColumn();
    $cancelledCount = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'cancelled'")->fetchColumn();
    $completedCount = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'completed'")->fetchColumn();

    $monthlyData = [];
    for ($i = 11; $i >= 0; $i--) {
        $monthStart = date('Y-m-01', strtotime("-$i months"));
        $monthEnd = date('Y-m-t', strtotime("-$i months"));
        $count = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE created_at >= '$monthStart' AND created_at <= '$monthEnd 23:59:59'")->fetchColumn();
        $monthlyData[] = [
            'label' => date('M', strtotime("-$i months")),
            'count' => $count
        ];
    }

    $today = date('Y-m-d');
    $todayAppointments = $pdo->query("SELECT a.*, d.name as doctor_name FROM appointments a LEFT JOIN doctors d ON a.doctor_id = d.doctor_id WHERE a.appointment_date = '$today' ORDER BY a.appointment_time ASC LIMIT 4")->fetchAll();

    $recentPosts = $pdo->query("SELECT title, slug, author, created_at FROM posts WHERE status='published' ORDER BY created_at DESC LIMIT 3")->fetchAll();
    $topDoctors  = $pdo->query("SELECT d.doctor_id, d.name, d.photo, d.specialization, d.status, dep.name AS department_name FROM doctors d LEFT JOIN departments dep ON d.department_id = dep.department_id WHERE d.status = 1 ORDER BY d.doctor_id ASC LIMIT 5")->fetchAll();
    $recentPatients = $pdo->query("SELECT appointment_id, patient_name, appointment_date, appointment_time, status FROM appointments ORDER BY created_at DESC LIMIT 8")->fetchAll();

    $dayOfWeek = date('N');
    $weekStart = date('Y-m-d', strtotime('-' . ($dayOfWeek - 1) . ' days'));
    $calDays = [];
    for ($i = 0; $i < 7; $i++) {
        $d = date('Y-m-d', strtotime("+$i days", strtotime($weekStart)));
        $calDays[] = [
            'date' => $d,
            'num' => date('j', strtotime($d)),
            'name' => strtolower(date('D', strtotime($d))),
            'active' => $d === $today
        ];
    }

    $totalUsers = getCount($pdo, 'admins');
?>

<!-- ======= GREETING ======= -->
<div class="dsh-greeting">
    <div>
        <h4 class="dsh-hello">Welcome back, <?= e($_SESSION['admin_name'] ?? 'Admin') ?> 👋</h4>
        <p class="dsh-sub">Here is your latest clinic overview for <?= date('l, jS F Y') ?></p>
    </div>
    <div class="dsh-greeting-right">
        <?php if ($pendingCount > 0): ?>
        <a href="/admin/appointments.php?status=pending" class="dsh-pending-pill">
            <i class="fas fa-clock"></i> <?= $pendingCount ?> Pending
        </a>
        <?php endif; ?>
        <a href="/admin/appointments.php" class="dsh-primary-btn">
            <i class="fas fa-calendar-plus me-1"></i> New Appointment
        </a>
    </div>
</div>

<!-- ======= KPI CARDS ======= -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="dsh-kpi">
            <div class="dsh-kpi-top">
                <div class="dsh-kpi-icon" style="background:#eef2ff;color:#4f6ef7"><i class="fas fa-calendar-check"></i></div>
                <span class="dsh-kpi-label">Total Appointments</span>
            </div>
            <div class="dsh-kpi-num"><?= number_format($totalAppointments) ?></div>
            <div class="dsh-kpi-foot">
                <span class="dsh-kpi-trend <?= $pendingCount > 0 ? 'warn' : 'up' ?>">
                    <i class="fas fa-arrow-<?= $pendingCount > 0 ? 'right' : 'up' ?>"></i> <?= $pendingCount ?> pending
                </span>
                <svg class="dsh-spark" viewBox="0 0 60 28" fill="none"><polyline points="0,22 10,16 20,20 30,10 40,14 50,6 60,10" stroke="#4f6ef7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><polyline points="0,22 10,16 20,20 30,10 40,14 50,6 60,10" stroke="#4f6ef7" stroke-width="6" opacity="0.08"/></svg>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="dsh-kpi">
            <div class="dsh-kpi-top">
                <div class="dsh-kpi-icon" style="background:#ecfdf5;color:#059669"><i class="fas fa-user-md"></i></div>
                <span class="dsh-kpi-label">Active Doctors</span>
            </div>
            <div class="dsh-kpi-num"><?= $totalDoctors ?></div>
            <div class="dsh-kpi-foot">
                <span class="dsh-kpi-trend up"><i class="fas fa-arrow-up"></i> <?= $totalDepts ?> departments</span>
                <svg class="dsh-spark" viewBox="0 0 60 28" fill="none"><polyline points="0,20 10,14 20,18 30,8 40,12 50,6 60,10" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><polyline points="0,20 10,14 20,18 30,8 40,12 50,6 60,10" stroke="#059669" stroke-width="6" opacity="0.08"/></svg>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="dsh-kpi">
            <div class="dsh-kpi-top">
                <div class="dsh-kpi-icon" style="background:#eff6ff;color:#3b82f6"><i class="fas fa-hospital-alt"></i></div>
                <span class="dsh-kpi-label">Departments</span>
            </div>
            <div class="dsh-kpi-num"><?= $totalDepts ?></div>
            <div class="dsh-kpi-foot">
                <span class="dsh-kpi-trend up"><i class="fas fa-check-circle"></i> all active</span>
                <svg class="dsh-spark" viewBox="0 0 60 28" fill="none"><polyline points="0,18 10,12 20,16 30,6 40,10 50,4 60,8" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><polyline points="0,18 10,12 20,16 30,6 40,10 50,4 60,8" stroke="#3b82f6" stroke-width="6" opacity="0.08"/></svg>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="dsh-kpi">
            <div class="dsh-kpi-top">
                <div class="dsh-kpi-icon" style="background:#faf5ff;color:#8b5cf6"><i class="fas fa-users"></i></div>
                <span class="dsh-kpi-label">Confirmed Patients</span>
            </div>
            <div class="dsh-kpi-num"><?= number_format($confirmedCount) ?></div>
            <div class="dsh-kpi-foot">
                <span class="dsh-kpi-trend up"><i class="fas fa-arrow-up"></i> <?= $completedCount ?> completed</span>
                <svg class="dsh-spark" viewBox="0 0 60 28" fill="none"><polyline points="0,24 10,18 20,22 30,12 40,16 50,8 60,12" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><polyline points="0,24 10,18 20,22 30,12 40,16 50,8 60,12" stroke="#8b5cf6" stroke-width="6" opacity="0.08"/></svg>
            </div>
        </div>
    </div>
</div>

<!-- ======= CHART + CALENDAR ROW ======= -->
<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="dsh-panel">
            <div class="dsh-panel-head">
                <div>
                    <h6 class="dsh-panel-title">Patient Arrival Statistics</h6>
                    <p class="dsh-panel-sub">Monthly appointment volume</p>
                </div>
                <div class="dsh-tab-group">
                    <button class="dsh-tab" data-range="week">Week</button>
                    <button class="dsh-tab" data-range="month">Month</button>
                    <button class="dsh-tab active" data-range="year">Year</button>
                </div>
            </div>
            <div class="dsh-chart-wrap">
                <canvas id="patientChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="dsh-panel dsh-cal-panel">
            <div class="dsh-panel-head">
                <h6 class="dsh-panel-title"><i class="fas fa-calendar-alt me-2" style="color:var(--admin-accent)"></i>Today <?= date('d M Y') ?></h6>
            </div>
            <div class="dsh-cal-strip">
                <?php foreach ($calDays as $cd): ?>
                <div class="dsh-cal-day <?= $cd['active'] ? 'active' : '' ?>">
                    <span class="dsh-cal-num"><?= $cd['num'] ?></span>
                    <span class="dsh-cal-name"><?= substr($cd['name'], 0, 3) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="dsh-schedule-list">
                <?php if (!empty($todayAppointments)): ?>
                    <?php foreach ($todayAppointments as $ta): ?>
                    <div class="dsh-sched-item">
                        <div class="dsh-sched-avatar"><?= strtoupper(substr($ta['patient_name'], 0, 1)) ?></div>
                        <div class="dsh-sched-info">
                            <div class="dsh-sched-name"><?= e($ta['patient_name']) ?></div>
                            <div class="dsh-sched-doc"><?= e($ta['doctor_name'] ?? 'TBD') ?></div>
                        </div>
                        <span class="dsh-sched-time"><?= $ta['appointment_time'] ? date('h:i A', strtotime($ta['appointment_time'])) : '--' ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="dsh-sched-empty">
                        <i class="fas fa-calendar-check"></i>
                        <p>No appointments today</p>
                        <small>Schedule is clear</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ======= OVERVIEW + CONTENT + POSTS ROW ======= -->
<div class="row g-3 mb-4">
    <div class="col-xl-4">
        <div class="dsh-panel">
            <div class="dsh-panel-head">
                <h6 class="dsh-panel-title">Appointments Overview</h6>
                <a href="/admin/appointments.php" class="dsh-panel-link">View all</a>
            </div>
            <div class="dsh-donut-wrap">
                <svg width="100" height="100" viewBox="0 0 100 100" class="dsh-donut-svg">
                    <circle cx="50" cy="50" r="40" fill="none" stroke="#f1f5f9" stroke-width="10"/>
                    <circle cx="50" cy="50" r="40" fill="none" stroke="#22c55e" stroke-width="10"
                        stroke-dasharray="<?= $totalAppointments > 0 ? round(($confirmedCount/$totalAppointments)*251.2, 1) : 0 ?> 251.2"
                        stroke-linecap="round" transform="rotate(-90 50 50)"/>
                </svg>
                <div class="dsh-donut-center">
                    <div class="dsh-donut-pct"><?= $totalAppointments > 0 ? round(($confirmedCount/$totalAppointments)*100) : 0 ?>%</div>
                    <div class="dsh-donut-lbl">Confirmed</div>
                </div>
            </div>
            <div class="dsh-stat-rows">
                <div class="dsh-stat-row"><span class="dsh-dot" style="background:#22c55e"></span><span>Confirmed</span><strong class="ms-auto"><?= $confirmedCount ?></strong></div>
                <div class="dsh-stat-row"><span class="dsh-dot" style="background:#f59e0b"></span><span>Pending</span><strong class="ms-auto"><?= $pendingCount ?></strong></div>
                <div class="dsh-stat-row"><span class="dsh-dot" style="background:#3b82f6"></span><span>Completed</span><strong class="ms-auto"><?= $completedCount ?></strong></div>
                <div class="dsh-stat-row"><span class="dsh-dot" style="background:#ef4444"></span><span>Cancelled</span><strong class="ms-auto"><?= $cancelledCount ?></strong></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="dsh-panel">
            <div class="dsh-panel-head">
                <h6 class="dsh-panel-title">Content Stats</h6>
            </div>
            <div class="dsh-content-num-wrap">
                <div class="dsh-content-big"><?= $totalPosts + $totalSlides + $totalTestimonials ?></div>
                <span class="dsh-content-badge"><i class="fas fa-arrow-up me-1"></i>Total items</span>
            </div>
            <div class="dsh-content-rows">
                <div class="dsh-content-row">
                    <div class="dsh-content-icon" style="background:#eef2ff;color:#4f6ef7"><i class="fas fa-newspaper"></i></div>
                    <span class="dsh-content-lbl">Blog Posts</span>
                    <strong class="ms-auto"><?= $totalPosts ?></strong>
                </div>
                <div class="dsh-content-row">
                    <div class="dsh-content-icon" style="background:#ecfdf5;color:#059669"><i class="fas fa-images"></i></div>
                    <span class="dsh-content-lbl">Hero Slides</span>
                    <strong class="ms-auto"><?= $totalSlides ?></strong>
                </div>
                <div class="dsh-content-row">
                    <div class="dsh-content-icon" style="background:#fffbeb;color:#d97706"><i class="fas fa-comments"></i></div>
                    <span class="dsh-content-lbl">Testimonials</span>
                    <strong class="ms-auto"><?= $totalTestimonials ?></strong>
                </div>
                <div class="dsh-content-row">
                    <div class="dsh-content-icon" style="background:#faf5ff;color:#8b5cf6"><i class="fas fa-users-cog"></i></div>
                    <span class="dsh-content-lbl">Admin Users</span>
                    <strong class="ms-auto"><?= $totalUsers ?></strong>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="dsh-panel">
            <div class="dsh-panel-head">
                <h6 class="dsh-panel-title">Recent Posts</h6>
                <a href="/admin/blog.php" class="dsh-panel-link">View all</a>
            </div>
            <?php if (empty($recentPosts)): ?>
            <div class="dsh-sched-empty"><i class="fas fa-newspaper"></i><p>No posts yet</p></div>
            <?php else: ?>
            <?php foreach ($recentPosts as $rp): ?>
            <div class="dsh-post-item">
                <div class="dsh-post-icon"><i class="fas fa-file-medical-alt"></i></div>
                <div class="dsh-post-info">
                    <div class="dsh-post-title"><?= e(mb_strimwidth($rp['title'], 0, 40, '…')) ?></div>
                    <div class="dsh-post-meta"><?= e($rp['author']) ?> · <?= date('d M', strtotime($rp['created_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ======= ACTIVE DOCTORS ROW ======= -->
<?php if (!empty($topDoctors)): ?>
<div class="dsh-section-head">
    <h6 class="dsh-section-title"><i class="fas fa-user-md me-2" style="color:var(--admin-accent)"></i>Active Doctors</h6>
    <a href="/admin/doctors.php" class="dsh-panel-link">View all &rsaquo;</a>
</div>
<div class="docs-slider-outer mb-4">
    <div class="docs-slider" id="docsSlider">
        <?php foreach ($topDoctors as $idx => $td): ?>
        <div class="docs-slide-item">
            <div class="doctor-card">
                <?php if ($td['status']): ?><span class="doc-status-badge"></span><?php endif; ?>
                <?php if ($td['photo']): ?>
                <img src="<?= e($td['photo']) ?>" class="doc-avatar" alt="">
                <?php else: ?>
                <div class="doc-avatar-placeholder"><?= strtoupper(substr($td['name'], 0, 1)) ?></div>
                <?php endif; ?>
                <div class="doc-name">Dr. <?= e($td['name']) ?></div>
                <div class="doc-spec"><?= e($td['specialization'] ?: $td['department_name'] ?: 'General') ?></div>
                <?php if ($td['department_name']): ?>
                <div class="doc-dept"><i class="fas fa-hospital me-1"></i><?= e($td['department_name']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="docs-dots d-md-none" id="docsDots">
        <?php foreach ($topDoctors as $idx => $td): ?>
        <span class="docs-dot <?= $idx === 0 ? 'active' : '' ?>" data-index="<?= $idx ?>"></span>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ======= APPOINTMENTS TABLE + RECENT PATIENTS ======= -->
<div class="row g-3 mb-2">
    <div class="col-xl-8">
        <div class="dsh-panel">
            <div class="dsh-panel-head">
                <h6 class="dsh-panel-title"><i class="fas fa-list-alt me-2" style="color:var(--admin-accent)"></i>Recent Appointments</h6>
                <a href="/admin/appointments.php" class="dsh-primary-btn-sm">View All</a>
            </div>
            <div class="table-responsive">
                <table class="dsh-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Assigned Doctor</th>
                            <th>Date</th>
                            <th>Department</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentAppointments)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No appointments yet</td></tr>
                        <?php else: ?>
                        <?php foreach ($recentAppointments as $apt): ?>
                        <tr>
                            <td>
                                <div class="dsh-tbl-patient">
                                    <div class="dsh-tbl-avatar"><?= strtoupper(substr($apt['patient_name'], 0, 1)) ?></div>
                                    <div>
                                        <div class="dsh-tbl-name"><?= e($apt['patient_name']) ?></div>
                                        <div class="dsh-tbl-sub"><?= e($apt['patient_phone'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="dsh-tbl-doc"><?= e($apt['doctor_name'] ?? '—') ?></td>
                            <td class="dsh-tbl-date"><?= date('d M Y', strtotime($apt['appointment_date'])) ?></td>
                            <td class="dsh-tbl-dept"><?= e($apt['department_name'] ?? '—') ?></td>
                            <td><span class="dsh-status-badge <?= $apt['status'] ?>"><?= ucfirst($apt['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="dsh-panel">
            <div class="dsh-panel-head">
                <h6 class="dsh-panel-title"><i class="fas fa-user-injured me-2" style="color:var(--admin-accent)"></i>Recent Patients</h6>
                <a href="/admin/appointments.php" class="dsh-panel-link">More &rsaquo;</a>
            </div>
            <?php if (empty($recentPatients)): ?>
            <div class="dsh-sched-empty"><i class="fas fa-users"></i><p>No patients yet</p></div>
            <?php else: ?>
            <?php foreach ($recentPatients as $rp): ?>
            <div class="dsh-patient-row">
                <div class="dsh-tbl-avatar"><?= strtoupper(substr($rp['patient_name'], 0, 1)) ?></div>
                <div class="dsh-pt-info">
                    <div class="dsh-pt-name"><?= e($rp['patient_name']) ?></div>
                    <div class="dsh-pt-date"><?= date('d M Y', strtotime($rp['appointment_date'])) ?></div>
                </div>
                <span class="dsh-status-badge <?= $rp['status'] ?>"><?= ucfirst($rp['status']) ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('patientChart').getContext('2d');
const labels = <?= json_encode(array_column($monthlyData, 'label')) ?>;
const data = <?= json_encode(array_column($monthlyData, 'count')) ?>;

const gradient = ctx.createLinearGradient(0, 0, 0, 250);
gradient.addColorStop(0, 'rgba(59,130,246,0.18)');
gradient.addColorStop(1, 'rgba(59,130,246,0)');

const gradient2 = ctx.createLinearGradient(0, 0, 0, 250);
gradient2.addColorStop(0, 'rgba(59,130,246,0.15)');
gradient2.addColorStop(1, 'rgba(59,130,246,0)');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Appointments',
                data: data,
                borderColor: '#1d4ed8',
                backgroundColor: gradient2,
                borderWidth: 2.5,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#1d4ed8',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 7
            },
            {
                label: 'Trend',
                data: data.map(function(v, i) { return Math.max(0, v + Math.sin(i) * 0.5); }),
                borderColor: '#93c5fd',
                backgroundColor: gradient,
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                borderDash: [5, 5]
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    padding: 20,
                    font: { size: 12, family: 'Plus Jakarta Sans' }
                }
            },
            tooltip: {
                backgroundColor: '#1d4ed8',
                titleFont: { family: 'Plus Jakarta Sans', weight: '600' },
                bodyFont: { family: 'Plus Jakarta Sans' },
                cornerRadius: 10,
                padding: 12
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { font: { size: 11, family: 'Plus Jakarta Sans' }, color: '#6b7f71' }
            },
            y: {
                grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                ticks: {
                    font: { size: 11, family: 'Plus Jakarta Sans' },
                    color: '#6b7f71',
                    stepSize: 1
                },
                beginAtZero: true
            }
        }
    }
});

// Top Rated Doctors — mobile slider dots
(function() {
    const slider = document.getElementById('docsSlider');
    const dots   = document.querySelectorAll('.docs-dot');
    if (!slider || !dots.length) return;

    function getActiveIndex() {
        const items = slider.querySelectorAll('.docs-slide-item');
        if (!items.length) return 0;
        const sliderLeft = slider.getBoundingClientRect().left;
        let closest = 0, minDist = Infinity;
        items.forEach((el, i) => {
            const dist = Math.abs(el.getBoundingClientRect().left - sliderLeft);
            if (dist < minDist) { minDist = dist; closest = i; }
        });
        return closest;
    }

    function updateDots(idx) {
        dots.forEach((d, i) => d.classList.toggle('active', i === idx));
    }

    slider.addEventListener('scroll', () => {
        updateDots(getActiveIndex());
    }, { passive: true });

    dots.forEach((dot, i) => {
        dot.addEventListener('click', () => {
            const items = slider.querySelectorAll('.docs-slide-item');
            if (items[i]) {
                slider.scrollTo({ left: items[i].offsetLeft - slider.offsetLeft, behavior: 'smooth' });
            }
        });
    });
})();
</script>

<?php } ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
