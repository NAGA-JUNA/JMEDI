<?php
$pageTitle = 'Doctor Schedules';
require_once __DIR__ . '/../includes/admin_header.php';
requirePermission('doctors');

$allDoctors = getDoctors($pdo, null, false);
$selectedDoctorId = (int)($_GET['doctor_id'] ?? 0);
$success = $error = '';
$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$sessions = ['Morning', 'Evening'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    } else {
        $postAction = $_POST['form_action'] ?? '';
        $doctorId = (int)($_POST['doctor_id'] ?? 0);

        if ($postAction === 'save_schedule' && $doctorId) {
            $scheduleData = $_POST['schedule'] ?? [];
            foreach ($scheduleData as $day => $sessionData) {
                foreach ($sessionData as $label => $fields) {
                    $isActive = isset($fields['is_active']) ? 1 : 0;
                    $startTime = $fields['start_time'] ?? '09:00';
                    $endTime = $fields['end_time'] ?? '17:00';
                    $slotDuration = (int)($fields['slot_duration'] ?? 15);
                    if ($slotDuration < 5) $slotDuration = 15;

                    saveDoctorSchedule($pdo, $doctorId, (int)$day, $label, [
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'slot_duration' => $slotDuration,
                        'is_active' => $isActive
                    ]);
                }
            }
            $success = 'Schedule updated successfully.';
            $selectedDoctorId = $doctorId;
        }

        if ($postAction === 'save_doctor_details' && $doctorId) {
            $fee = $_POST['consultation_fee'] ?? '';
            $address = trim($_POST['clinic_address'] ?? '');
            $types = $_POST['consultation_types'] ?? 'both';

            $stmt = $pdo->prepare("UPDATE doctors SET consultation_fee = :fee, clinic_address = :addr, consultation_types = :types WHERE doctor_id = :id");
            $stmt->execute([
                ':fee' => $fee !== '' ? (float)$fee : null,
                ':addr' => $address ?: null,
                ':types' => $types,
                ':id' => $doctorId
            ]);
            $success = 'Doctor details updated successfully.';
            $selectedDoctorId = $doctorId;
        }

        if ($postAction === 'delete_schedule') {
            $scheduleId = (int)($_POST['schedule_id'] ?? 0);
            if ($scheduleId && deleteDoctorSchedule($pdo, $scheduleId)) {
                $success = 'Schedule entry deleted.';
            }
            $selectedDoctorId = $doctorId;
        }
    }
}

$selectedDoctor = null;
$doctorSchedules = [];
if ($selectedDoctorId) {
    $selectedDoctor = getDoctor($pdo, $selectedDoctorId);
    if ($selectedDoctor) {
        $doctorSchedules = getDoctorSchedulesByDay($pdo, $selectedDoctorId);
    }
}

$defaultTimes = [
    'Morning' => ['start' => '09:00', 'end' => '12:00'],
    'Evening' => ['start' => '16:00', 'end' => '19:00']
];
?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show"><?= e($success) ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show"><?= e($error) ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="form-card mb-4">
    <h5 class="mb-3"><i class="fas fa-calendar-alt me-2"></i>Doctor Schedule Management</h5>
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-6">
            <label class="form-label">Select Doctor</label>
            <select name="doctor_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Choose a Doctor --</option>
                <?php foreach ($allDoctors as $doc): ?>
                <option value="<?= $doc['doctor_id'] ?>" <?= $selectedDoctorId == $doc['doctor_id'] ? 'selected' : '' ?>><?= e($doc['name']) ?> — <?= e($doc['department_name'] ?? 'No Dept') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if ($selectedDoctor): ?>

<div class="form-card mb-4">
    <h5 class="mb-3"><i class="fas fa-user-md me-2"></i>Consultation Details — <?= e($selectedDoctor['name']) ?></h5>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="save_doctor_details">
        <input type="hidden" name="doctor_id" value="<?= $selectedDoctor['doctor_id'] ?>">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Consultation Fee</label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" name="consultation_fee" class="form-control" step="0.01" min="0" value="<?= e($selectedDoctor['consultation_fee'] ?? '') ?>" placeholder="500.00">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Consultation Types</label>
                <select name="consultation_types" class="form-select">
                    <option value="both" <?= ($selectedDoctor['consultation_types'] ?? '') === 'both' ? 'selected' : '' ?>>Both (Online & Clinic)</option>
                    <option value="online" <?= ($selectedDoctor['consultation_types'] ?? '') === 'online' ? 'selected' : '' ?>>Online Only</option>
                    <option value="clinic" <?= ($selectedDoctor['consultation_types'] ?? '') === 'clinic' ? 'selected' : '' ?>>Clinic Only</option>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i> Save Details</button>
            </div>
            <div class="col-12">
                <label class="form-label">Clinic Address</label>
                <textarea name="clinic_address" class="form-control" rows="2" placeholder="Enter clinic address..."><?= e($selectedDoctor['clinic_address'] ?? '') ?></textarea>
            </div>
        </div>
    </form>
</div>

<div class="form-card">
    <h5 class="mb-3"><i class="fas fa-clock me-2"></i>Weekly Schedule</h5>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="save_schedule">
        <input type="hidden" name="doctor_id" value="<?= $selectedDoctor['doctor_id'] ?>">

        <div class="accordion" id="scheduleAccordion">
            <?php for ($day = 0; $day < 7; $day++): ?>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button <?= empty($doctorSchedules[$day]) ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#day<?= $day ?>">
                        <span class="fw-semibold"><?= $dayNames[$day] ?></span>
                        <?php if (!empty($doctorSchedules[$day])): ?>
                        <span class="badge bg-success ms-2"><?= count($doctorSchedules[$day]) ?> session(s)</span>
                        <?php else: ?>
                        <span class="badge bg-secondary ms-2">No sessions</span>
                        <?php endif; ?>
                    </button>
                </h2>
                <div id="day<?= $day ?>" class="accordion-collapse collapse <?= !empty($doctorSchedules[$day]) ? 'show' : '' ?>" data-bs-parent="#scheduleAccordion">
                    <div class="accordion-body">
                        <?php foreach ($sessions as $sessionLabel): ?>
                        <?php
                            $existing = null;
                            if (!empty($doctorSchedules[$day])) {
                                foreach ($doctorSchedules[$day] as $s) {
                                    if ($s['session_label'] === $sessionLabel) {
                                        $existing = $s;
                                        break;
                                    }
                                }
                            }
                            $isActive = $existing ? (int)$existing['is_active'] : 0;
                            $startTime = $existing ? substr($existing['start_time'], 0, 5) : $defaultTimes[$sessionLabel]['start'];
                            $endTime = $existing ? substr($existing['end_time'], 0, 5) : $defaultTimes[$sessionLabel]['end'];
                            $slotDur = $existing ? (int)$existing['slot_duration_minutes'] : 15;
                        ?>
                        <div class="row g-2 align-items-center mb-3 p-2 rounded <?= $isActive ? 'bg-light border border-success' : 'bg-light' ?>">
                            <div class="col-md-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="schedule[<?= $day ?>][<?= $sessionLabel ?>][is_active]" value="1" <?= $isActive ? 'checked' : '' ?> id="active_<?= $day ?>_<?= $sessionLabel ?>">
                                    <label class="form-check-label fw-semibold" for="active_<?= $day ?>_<?= $sessionLabel ?>">
                                        <?php if ($sessionLabel === 'Morning'): ?>
                                        ☀️ Morning
                                        <?php else: ?>
                                        🌙 Evening
                                        <?php endif; ?>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-0">Start Time</label>
                                <input type="time" name="schedule[<?= $day ?>][<?= $sessionLabel ?>][start_time]" class="form-control form-control-sm" value="<?= $startTime ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-0">End Time</label>
                                <input type="time" name="schedule[<?= $day ?>][<?= $sessionLabel ?>][end_time]" class="form-control form-control-sm" value="<?= $endTime ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0">Slot (min)</label>
                                <select name="schedule[<?= $day ?>][<?= $sessionLabel ?>][slot_duration]" class="form-select form-select-sm">
                                    <?php foreach ([10, 15, 20, 30, 45, 60] as $dur): ?>
                                    <option value="<?= $dur ?>" <?= $slotDur == $dur ? 'selected' : '' ?>><?= $dur ?> min</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 text-end">
                                <?php if ($existing): ?>
                                <span class="badge bg-info"><?= date('h:i A', strtotime($startTime)) ?> - <?= date('h:i A', strtotime($endTime)) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> Save All Schedules</button>
            <a href="/admin/doctor-schedules.php?doctor_id=<?= $selectedDoctorId ?>" class="btn btn-secondary px-4">Reset</a>
        </div>
    </form>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
