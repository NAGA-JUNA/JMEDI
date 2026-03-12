<?php
$pageTitle = 'Schedule Appointment';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

$departments = getDepartments($pdo);
$doctors = getDoctors($pdo);
$settings = getSettings($pdo);
$currency = $settings['currency_symbol'] ?? '₹';
$success = $error = '';

$patientLoggedIn = isset($_SESSION['patient_id']);
$patientData = ['name' => '', 'email' => '', 'phone' => ''];
if ($patientLoggedIn) {
    $pStmt = $pdo->prepare("SELECT name, email, phone FROM patients WHERE patient_id = :id");
    $pStmt->execute([':id' => $_SESSION['patient_id']]);
    $pRow = $pStmt->fetch();
    if ($pRow) {
        $patientData = $pRow;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $name = trim($_POST['patient_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $deptId = (int)($_POST['department_id'] ?? 0);
        $docId = (int)($_POST['doctor_id'] ?? 0);
        $date = $_POST['appointment_date'] ?? '';
        $time = $_POST['appointment_time'] ?? '';
        $consultType = $_POST['consultation_type'] ?? 'clinic';
        $message = trim($_POST['message'] ?? '');

        if (empty($name) || empty($email) || empty($phone) || empty($date) || empty($time)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $slotTaken = false;
            if ($docId) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = :doc AND appointment_date = :date AND appointment_time = :time AND status != 'cancelled'");
                $chk->execute([':doc' => $docId, ':date' => $date, ':time' => $time]);
                if ((int)$chk->fetchColumn() > 0) {
                    $slotTaken = true;
                    $error = 'This time slot has already been booked. Please choose a different time.';
                }
            }
            if (!$slotTaken) {
                $msgFull = ($consultType === 'online' ? '[Online Consult] ' : '[Clinic Visit] ') . $message;
                $patientId = isset($_SESSION['patient_id']) ? (int)$_SESSION['patient_id'] : null;
                $stmt = $pdo->prepare("INSERT INTO appointments (patient_name, email, phone, department_id, doctor_id, appointment_date, appointment_time, message, consultation_type, patient_id) VALUES (:name, :email, :phone, :dept, :doc, :date, :time, :msg, :ctype, :pid)");
                $stmt->execute([
                    ':name' => $name, ':email' => $email, ':phone' => $phone,
                    ':dept' => $deptId ?: null, ':doc' => $docId ?: null,
                    ':date' => $date, ':time' => $time, ':msg' => $msgFull,
                    ':ctype' => $consultType, ':pid' => $patientId
                ]);
                $success = 'Your appointment has been booked successfully! We will confirm it shortly.';
                $_SESSION['csrf_token'] = '';
            }
        }
    }
}

$preselectedDoctor = (int)($_GET['doctor'] ?? 0);
$preselectedDoc = null;
if ($preselectedDoctor) {
    $preselectedDoc = getDoctor($pdo, $preselectedDoctor);
}
?>

<style>
.schedule-page { background: #f5f7fa; min-height: 80vh; }
.schedule-container { max-width: 680px; margin: 0 auto; padding: 24px 16px; }
.schedule-back { display: inline-flex; align-items: center; gap: 8px; color: #1a1a1a; font-size: 1.1rem; font-weight: 700; text-decoration: none; margin-bottom: 20px; }
.schedule-back:hover { color: var(--primary); }
.schedule-title { font-size: 1.5rem; font-weight: 800; color: #1a1a1a; margin-bottom: 24px; }

.doctor-card { background: #fff; border-radius: 16px; padding: 20px; margin-bottom: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 16px; }
.doctor-card img { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 3px solid #e8f4fd; }
.doctor-card .doc-info h5 { margin: 0; font-weight: 700; font-size: 1.1rem; }
.doctor-card .doc-info p { margin: 2px 0; color: #666; font-size: 0.9rem; }
.doctor-card .doc-info a { color: #0d9488; font-weight: 600; font-size: 0.9rem; text-decoration: none; }
.doctor-card .doc-info a:hover { text-decoration: underline; }

.consult-toggle { display: flex; gap: 0; background: #f0f0f0; border-radius: 10px; overflow: hidden; margin-bottom: 16px; }
.consult-toggle button { flex: 1; padding: 12px; border: none; background: transparent; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: all 0.2s; color: #555; }
.consult-toggle button.active { background: #0d9488; color: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(13,148,136,0.25); }

.clinic-card { background: #fff; border-radius: 14px; padding: 16px 20px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); display: flex; align-items: center; justify-content: space-between; }
.clinic-card .clinic-info h6 { margin: 0; font-weight: 700; font-size: 0.95rem; color: #333; }
.clinic-card .clinic-info p { margin: 2px 0 0; color: #888; font-size: 0.85rem; }
.clinic-card .map-icon { width: 44px; height: 44px; background: #e8f4fd; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #0d9488; font-size: 1.2rem; }

.week-picker { background: #fff; border-radius: 16px; padding: 16px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.week-nav { display: flex; align-items: center; justify-content: space-between; gap: 4px; }
.week-nav button { background: none; border: none; font-size: 1.2rem; color: #999; cursor: pointer; padding: 6px 10px; border-radius: 8px; }
.week-nav button:hover { background: #f0f0f0; }
.week-days { display: flex; flex: 1; justify-content: space-around; }
.day-cell { text-align: center; cursor: pointer; padding: 8px 6px; border-radius: 14px; transition: all 0.2s; min-width: 52px; }
.day-cell .day-name { font-size: 0.75rem; font-weight: 600; color: #aaa; text-transform: uppercase; }
.day-cell .day-num { font-size: 1.3rem; font-weight: 800; color: #333; margin: 2px 0; }
.day-cell .day-month { font-size: 0.72rem; color: #999; }
.day-cell.active { background: #0d9488; }
.day-cell.active .day-name, .day-cell.active .day-num, .day-cell.active .day-month { color: #fff; }
.day-cell.past { opacity: 0.35; pointer-events: none; }
.day-cell:hover:not(.active):not(.past) { background: #e8f4fd; }

.session-block { margin-bottom: 16px; }
.session-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.session-header .session-name { font-weight: 700; font-size: 1rem; color: #333; display: flex; align-items: center; gap: 6px; }
.session-header .slot-count { font-size: 0.85rem; color: #888; font-weight: 600; }

.slots-grid { display: flex; flex-wrap: wrap; gap: 10px; }
.slot-btn { padding: 10px 16px; border: 2px solid #e0e0e0; border-radius: 10px; background: #fff; font-size: 0.9rem; font-weight: 600; color: #333; cursor: pointer; transition: all 0.15s; min-width: 100px; text-align: center; }
.slot-btn:hover { border-color: #0d9488; color: #0d9488; }
.slot-btn.selected { border-color: #0d9488; background: #0d9488; color: #fff; }

.fee-bar { background: #fff; border-radius: 16px; padding: 20px; margin-top: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); display: flex; align-items: center; justify-content: space-between; }
.fee-bar .fee-amount { font-size: 1.5rem; font-weight: 800; color: #1a1a1a; }
.fee-bar .fee-label { font-size: 0.85rem; color: #888; }
.fee-bar .continue-btn { padding: 12px 36px; background: #0d9488; color: #fff; border: none; border-radius: 10px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: background 0.2s; }
.fee-bar .continue-btn:hover { background: #0b7c72; }
.fee-bar .continue-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.no-slots { text-align: center; padding: 40px 20px; color: #999; }
.no-slots i { font-size: 2.5rem; margin-bottom: 12px; color: #ddd; }
.no-slots p { font-size: 0.95rem; }

.doctor-select-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin-top: 16px; }
.doctor-select-card { background: #fff; border-radius: 16px; padding: 20px; cursor: pointer; border: 2px solid transparent; transition: all 0.2s; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.doctor-select-card:hover { border-color: #0d9488; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.doctor-select-card .doc-avatar { width: 64px; height: 64px; border-radius: 50%; object-fit: cover; }
.doctor-select-card h6 { margin: 10px 0 2px; font-weight: 700; }
.doctor-select-card .text-muted { font-size: 0.85rem; }

.patient-form { background: #fff; border-radius: 16px; padding: 28px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.patient-form h5 { font-weight: 800; margin-bottom: 20px; }

.step-indicator { display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 28px; }
.step-dot { width: 10px; height: 10px; border-radius: 50%; background: #ddd; transition: all 0.3s; }
.step-dot.active { background: #0d9488; width: 28px; border-radius: 10px; }
.step-dot.done { background: #0d9488; }

.summary-card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.summary-card .summary-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
.summary-card .summary-row:last-child { border-bottom: none; }
.summary-card .summary-label { color: #888; font-size: 0.9rem; }
.summary-card .summary-value { font-weight: 600; font-size: 0.95rem; }

.loading-spinner { display: flex; align-items: center; justify-content: center; padding: 40px; }
.loading-spinner .spinner-border { color: #0d9488; }

.dept-filter { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
.dept-filter button { padding: 6px 16px; border: 2px solid #e0e0e0; border-radius: 20px; background: #fff; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.15s; }
.dept-filter button.active { border-color: #0d9488; background: #0d9488; color: #fff; }

@media (max-width: 575.98px) {
    .schedule-container { padding: 16px 12px; }
    .day-cell { min-width: 0; flex: 1; padding: 6px 2px; }
    .day-cell .day-num { font-size: 1.05rem; }
    .day-cell .day-name { font-size: 0.65rem; }
    .day-cell .day-month { font-size: 0.62rem; }
    .week-picker { padding: 12px 8px; }
    .slot-btn { min-width: 80px; padding: 8px 10px; font-size: 0.82rem; }
    .fee-bar { flex-direction: column; align-items: stretch; gap: 12px; text-align: center; }
    .fee-bar .continue-btn { width: 100%; padding: 13px; }
    .doctor-select-grid { grid-template-columns: 1fr; }
    .patient-form { padding: 18px 14px; }
    .summary-card { padding: 16px 14px; }
}
</style>

<div class="schedule-page">
<div class="schedule-container">

<?php if ($success): ?>
    <div class="step-indicator">
        <div class="step-dot done"></div>
        <div class="step-dot done"></div>
        <div class="step-dot done"></div>
        <div class="step-dot active"></div>
    </div>
    <div class="summary-card text-center py-5">
        <div style="font-size:3rem;color:#0d9488;margin-bottom:16px;"><i class="fas fa-check-circle"></i></div>
        <h4 style="font-weight:800;">Appointment Booked!</h4>
        <p class="text-muted mb-4"><?= e($success) ?></p>
        <a href="/" class="btn px-4 py-2" style="background:#0d9488;color:#fff;border-radius:10px;font-weight:600;">Back to Home</a>
    </div>
<?php else: ?>

<div id="stepDoctor" <?= $preselectedDoc ? 'style="display:none"' : '' ?>>
    <a href="/" class="schedule-back"><i class="fas fa-arrow-left"></i> Back</a>
    <h2 class="schedule-title">Choose Your Doctor</h2>

    <div class="dept-filter">
        <button class="active" onclick="filterDoctors(0, this)">All</button>
        <?php foreach ($departments as $dept): ?>
        <button onclick="filterDoctors(<?= $dept['department_id'] ?>, this)"><?= e($dept['name']) ?></button>
        <?php endforeach; ?>
    </div>

    <div class="doctor-select-grid" id="doctorGrid">
        <?php foreach ($doctors as $doc): ?>
        <div class="doctor-select-card" data-dept="<?= $doc['department_id'] ?>" onclick="selectDoctor(<?= $doc['doctor_id'] ?>)">
            <div class="d-flex align-items-center gap-3">
                <img src="<?= e($doc['photo'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($doc['name']) . '&background=e8f4fd&color=0d9488&size=64') ?>" class="doc-avatar" alt="">
                <div>
                    <h6><?= e($doc['name']) ?></h6>
                    <p class="text-muted mb-0"><?= e($doc['experience'] ?? '') ?> &middot; <?= e($doc['department_name'] ?? '') ?></p>
                    <small class="text-muted"><?= e($doc['specialization'] ?? '') ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="stepSchedule" style="display:none">
    <a href="javascript:void(0)" class="schedule-back" onclick="backToStep('doctor')"><i class="fas fa-arrow-left"></i> Schedule Appointment</a>

    <div class="step-indicator">
        <div class="step-dot done"></div>
        <div class="step-dot active"></div>
        <div class="step-dot"></div>
    </div>

    <div class="doctor-card" id="selectedDocCard">
        <img id="docPhoto" src="" alt="">
        <div class="doc-info">
            <h5 id="docName"></h5>
            <p id="docMeta"></p>
            <a href="#" id="docProfileLink">View Profile</a>
        </div>
    </div>

    <div class="consult-toggle" id="consultToggle">
        <button onclick="setConsultType('online', this)">ONLINE CONSULT</button>
        <button class="active" onclick="setConsultType('clinic', this)">CLINIC VISIT</button>
    </div>

    <div class="clinic-card" id="clinicCard">
        <div class="clinic-info">
            <h6 id="clinicName"></h6>
            <p id="clinicAddr"></p>
        </div>
        <div class="map-icon"><i class="fas fa-map-marker-alt"></i></div>
    </div>

    <div class="week-picker">
        <div class="week-nav">
            <button onclick="shiftWeek(-1)" id="weekPrev"><i class="fas fa-chevron-left"></i></button>
            <div class="week-days" id="weekDays"></div>
            <button onclick="shiftWeek(1)"><i class="fas fa-chevron-right"></i></button>
        </div>
    </div>

    <div id="slotsContainer">
        <div class="loading-spinner"><div class="spinner-border"></div></div>
    </div>

    <div class="fee-bar" id="feeBar" style="display:none">
        <div>
            <div class="fee-amount" id="feeAmount"></div>
            <div class="fee-label">Pay at Clinic</div>
        </div>
        <button class="continue-btn" id="continueBtn" disabled onclick="goToPatientForm()">Continue</button>
    </div>
</div>

<div id="stepPatient" style="display:none">
    <a href="javascript:void(0)" class="schedule-back" onclick="backToStep('schedule')"><i class="fas fa-arrow-left"></i> Patient Details</a>

    <div class="step-indicator">
        <div class="step-dot done"></div>
        <div class="step-dot done"></div>
        <div class="step-dot active"></div>
    </div>

    <div class="patient-form">
        <h5><i class="fas fa-user-edit me-2" style="color:#0d9488;"></i>Enter Your Details</h5>
        <form method="POST" id="scheduleAppointmentForm">
            <?= csrfField() ?>
            <input type="hidden" name="book_appointment" value="1">
            <input type="hidden" name="doctor_id" id="formDoctorId">
            <input type="hidden" name="department_id" id="formDeptId">
            <input type="hidden" name="appointment_date" id="formDate">
            <input type="hidden" name="appointment_time" id="formTime">
            <input type="hidden" name="consultation_type" id="formConsultType" value="clinic">

            <div class="summary-card mb-4">
                <h6 class="mb-3" style="font-weight:700;color:#0d9488;"><i class="fas fa-calendar-check me-2"></i>Appointment Summary</h6>
                <div class="summary-row">
                    <span class="summary-label">Doctor</span>
                    <span class="summary-value" id="summaryDoc"></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Date</span>
                    <span class="summary-value" id="summaryDate"></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Time</span>
                    <span class="summary-value" id="summaryTime"></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Type</span>
                    <span class="summary-value" id="summaryType"></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Fee</span>
                    <span class="summary-value" id="summaryFee"></span>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="patient_name" class="form-control form-control-lg" style="border-radius:10px;" required placeholder="Enter your full name" value="<?= e($patientData['name']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control form-control-lg" style="border-radius:10px;" required placeholder="your@email.com" value="<?= e($patientData['email']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
                    <input type="tel" name="phone" class="form-control form-control-lg" style="border-radius:10px;" required placeholder="+91 98765 43210" value="<?= e($patientData['phone'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Reason for Visit</label>
                    <textarea name="message" class="form-control" rows="3" style="border-radius:10px;" placeholder="Describe your symptoms or reason..."></textarea>
                </div>
                <div class="col-12 mt-2">
                    <button type="submit" class="continue-btn w-100" style="background:#0d9488;color:#fff;border:none;border-radius:10px;padding:14px;font-size:1.05rem;font-weight:700;cursor:pointer;">
                        <i class="fas fa-check-circle me-2"></i>Confirm Appointment
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($error): ?>
<script>document.getElementById('stepPatient').style.display = 'block';</script>
<div class="alert alert-danger mt-3"><i class="fas fa-exclamation-circle me-2"></i><?= e($error) ?></div>
<?php endif; ?>

<?php endif; ?>

</div>
</div>

<script>
const CURRENCY = <?= json_encode($currency) ?>;
let currentDoctor = null;
let selectedDate = null;
let selectedTime = null;
let selectedTimeDisplay = null;
let consultType = 'clinic';
let weekOffset = 0;

<?php if ($preselectedDoc): ?>
(function() {
    const doc = <?= json_encode([
        'doctor_id' => $preselectedDoc['doctor_id'],
        'name' => $preselectedDoc['name'],
        'photo' => $preselectedDoc['photo'],
        'specialization' => $preselectedDoc['specialization'],
        'experience' => $preselectedDoc['experience'],
        'department_name' => $preselectedDoc['department_name'] ?? '',
        'department_id' => $preselectedDoc['department_id'],
        'consultation_fee' => (float)($preselectedDoc['consultation_fee'] ?? 500),
        'clinic_address' => $preselectedDoc['clinic_address'] ?? '',
        'consultation_types' => $preselectedDoc['consultation_types'] ?? 'both'
    ]) ?>;
    currentDoctor = doc;
    showScheduleStep(doc);
})();
<?php endif; ?>

function filterDoctors(deptId, btn) {
    document.querySelectorAll('.dept-filter button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.doctor-select-card').forEach(card => {
        card.style.display = (!deptId || card.dataset.dept == deptId) ? '' : 'none';
    });
}

function selectDoctor(doctorId) {
    fetch('/public/api/slots.php?doctor_id=' + doctorId + '&date=' + getTodayStr())
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            currentDoctor = data.doctor;
            showScheduleStep(data.doctor);
        }
    });
}

function showScheduleStep(doc) {
    document.getElementById('stepDoctor').style.display = 'none';
    document.getElementById('stepSchedule').style.display = 'block';
    document.getElementById('docPhoto').src = doc.photo || ('https://ui-avatars.com/api/?name=' + encodeURIComponent(doc.name) + '&background=e8f4fd&color=0d9488&size=72');
    document.getElementById('docName').textContent = doc.name;
    document.getElementById('docMeta').textContent = (doc.experience || '') + ' \u00B7 ' + (doc.specialization || doc.department_name || '');
    document.getElementById('docProfileLink').href = '/public/doctors.php';

    const types = doc.consultation_types || 'both';
    const toggle = document.getElementById('consultToggle');
    const btns = toggle.querySelectorAll('button');
    if (types === 'online') { btns[0].style.display = ''; btns[1].style.display = 'none'; setConsultType('online', btns[0]); }
    else if (types === 'clinic') { btns[0].style.display = 'none'; btns[1].style.display = ''; setConsultType('clinic', btns[1]); }
    else { btns[0].style.display = ''; btns[1].style.display = ''; }

    const addr = doc.clinic_address || 'Clinic Address';
    document.getElementById('clinicName').textContent = doc.name + "'s Clinic";
    document.getElementById('clinicAddr').textContent = addr;

    weekOffset = 0;
    renderWeek();
}

function setConsultType(type, btn) {
    consultType = type;
    document.querySelectorAll('.consult-toggle button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('clinicCard').style.display = type === 'online' ? 'none' : 'flex';
    document.getElementById('formConsultType').value = type;
}

function getTodayStr() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}

function renderWeek() {
    const container = document.getElementById('weekDays');
    container.innerHTML = '';
    const today = new Date();
    today.setHours(0,0,0,0);
    const startOfWeek = new Date(today);
    startOfWeek.setDate(today.getDate() + (weekOffset * 7));
    const dow = startOfWeek.getDay();
    startOfWeek.setDate(startOfWeek.getDate() - dow);

    const dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    let firstAvailable = null;

    for (let i = 0; i < 7; i++) {
        const d = new Date(startOfWeek);
        d.setDate(startOfWeek.getDate() + i);
        const isPast = d < today;
        const dateStr = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
        const isActive = selectedDate === dateStr;

        const cell = document.createElement('div');
        cell.className = 'day-cell' + (isActive ? ' active' : '') + (isPast ? ' past' : '');
        cell.innerHTML = '<div class="day-name">' + dayNames[i] + '</div><div class="day-num">' + String(d.getDate()).padStart(2,'0') + '</div><div class="day-month">' + monthNames[d.getMonth()] + '</div>';
        cell.onclick = () => { if (!isPast) selectDate(dateStr); };
        container.appendChild(cell);

        if (!isPast && !firstAvailable) firstAvailable = dateStr;
    }

    document.getElementById('weekPrev').disabled = weekOffset <= 0;

    if (!selectedDate && firstAvailable) {
        selectDate(firstAvailable);
    } else if (selectedDate) {
        const selD = new Date(selectedDate + 'T00:00:00');
        const weekStart = new Date(startOfWeek);
        const weekEnd = new Date(startOfWeek);
        weekEnd.setDate(weekEnd.getDate() + 6);
        if (selD < weekStart || selD > weekEnd) {
            if (firstAvailable) selectDate(firstAvailable);
        }
    }
}

function selectDate(dateStr) {
    selectedDate = dateStr;
    selectedTime = null;
    selectedTimeDisplay = null;
    document.getElementById('continueBtn').disabled = true;

    document.querySelectorAll('.day-cell').forEach(c => c.classList.remove('active'));
    const cells = document.querySelectorAll('.day-cell');
    const today = new Date();
    today.setHours(0,0,0,0);
    const startOfWeek = new Date(today);
    startOfWeek.setDate(today.getDate() + (weekOffset * 7));
    startOfWeek.setDate(startOfWeek.getDate() - startOfWeek.getDay());

    cells.forEach((cell, i) => {
        const d = new Date(startOfWeek);
        d.setDate(startOfWeek.getDate() + i);
        const ds = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
        if (ds === dateStr) cell.classList.add('active');
    });

    loadSlots(dateStr);
}

function loadSlots(dateStr) {
    const container = document.getElementById('slotsContainer');
    container.innerHTML = '<div class="loading-spinner"><div class="spinner-border"></div></div>';
    document.getElementById('feeBar').style.display = 'none';

    fetch('/public/api/slots.php?doctor_id=' + currentDoctor.doctor_id + '&date=' + dateStr)
    .then(r => r.json())
    .then(data => {
        if (!data.success || !data.sessions || data.sessions.length === 0) {
            container.innerHTML = '<div class="no-slots"><i class="fas fa-calendar-times d-block"></i><p>No available slots on this date.</p><p class="text-muted small">Try selecting a different date.</p></div>';
            return;
        }

        let html = '';
        const icons = { 'Morning': 'fa-sun', 'Afternoon': 'fa-cloud-sun', 'Evening': 'fa-moon' };
        data.sessions.forEach(session => {
            const icon = icons[session.session] || 'fa-clock';
            html += '<div class="session-block"><div class="session-header"><div class="session-name"><i class="fas ' + icon + '"></i> ' + session.session + '</div><div class="slot-count">' + session.count + ' SLOTS</div></div><div class="slots-grid">';
            session.slots.forEach(slot => {
                html += '<button type="button" class="slot-btn" onclick="pickSlot(this, \'' + slot.time + '\', \'' + slot.display + '\')">' + slot.display + '</button>';
            });
            html += '</div></div>';
        });
        container.innerHTML = html;

        const fee = data.doctor.consultation_fee || currentDoctor.consultation_fee || 500;
        document.getElementById('feeAmount').textContent = data.currency + fee;
        document.getElementById('feeBar').style.display = 'flex';
    })
    .catch(() => {
        container.innerHTML = '<div class="no-slots"><i class="fas fa-exclamation-triangle d-block"></i><p>Failed to load slots. Please try again.</p></div>';
    });
}

function pickSlot(btn, time, display) {
    document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    selectedTime = time;
    selectedTimeDisplay = display;
    document.getElementById('continueBtn').disabled = false;
}

function goToPatientForm() {
    if (!selectedDate || !selectedTime) return;
    document.getElementById('stepSchedule').style.display = 'none';
    document.getElementById('stepPatient').style.display = 'block';

    document.getElementById('formDoctorId').value = currentDoctor.doctor_id;
    document.getElementById('formDeptId').value = currentDoctor.department_id || '';
    document.getElementById('formDate').value = selectedDate;
    document.getElementById('formTime').value = selectedTime;

    document.getElementById('summaryDoc').textContent = currentDoctor.name;
    const d = new Date(selectedDate + 'T00:00:00');
    document.getElementById('summaryDate').textContent = d.toLocaleDateString('en-US', {weekday:'long', year:'numeric', month:'long', day:'numeric'});
    document.getElementById('summaryTime').textContent = selectedTimeDisplay;
    document.getElementById('summaryType').textContent = consultType === 'online' ? 'Online Consultation' : 'Clinic Visit';
    document.getElementById('summaryFee').textContent = document.getElementById('feeAmount').textContent;
}

function backToStep(step) {
    document.getElementById('stepDoctor').style.display = step === 'doctor' ? 'block' : 'none';
    document.getElementById('stepSchedule').style.display = step === 'schedule' ? 'block' : 'none';
    document.getElementById('stepPatient').style.display = 'none';
    if (step === 'doctor') {
        currentDoctor = null;
        selectedDate = null;
        selectedTime = null;
    }
}

function shiftWeek(dir) {
    weekOffset += dir;
    if (weekOffset < 0) weekOffset = 0;
    selectedDate = null;
    renderWeek();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
