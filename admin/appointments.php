<?php
$pageTitle = 'Manage Appointments';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
if (!isDoctor()) {
    requirePermission('appointments');
}

$filterStatus  = $_GET['status'] ?? '';
$search        = $_GET['search'] ?? '';
$myDoctorId    = isDoctor() ? ((int)($_SESSION['admin_doctor_id'] ?? 0) ?: null) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $aptId = (int)($_POST['appointment_id'] ?? 0);
    $aptAction = $_POST['apt_action'] ?? '';

    if ($aptId && $myDoctorId) {
        $ownerCheck = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_id = :id AND doctor_id = :did");
        $ownerCheck->execute([':id' => $aptId, ':did' => $myDoctorId]);
        if ((int)$ownerCheck->fetchColumn() === 0) {
            header('Location: /admin/appointments.php?error=access_denied');
            exit;
        }
    }

    if ($aptId && $aptAction === 'update_status') {
        $newStatus = $_POST['new_status'] ?? '';
        $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled', 'rescheduled'];
        if (in_array($newStatus, $validStatuses, true)) {
            $existing = getAppointment($pdo, $aptId);
            $oldStatus = $existing['status'] ?? '';
            updateAppointment($pdo, $aptId, ['status' => $newStatus]);

            $updated = getAppointment($pdo, $aptId);
            if ($updated) {
                sendAppointmentStatusNotification($pdo, $updated, $oldStatus, $newStatus);
            } else {
                error_log('appointments.php: could not load appointment after status update #' . $aptId);
            }

            header('Location: /admin/appointments.php?msg=' . $newStatus . '&' . http_build_query(['status' => $filterStatus, 'search' => $search]));
            exit;
        }
    } elseif ($aptId && $aptAction === 'update_details') {
        $updateData = [];
        if (!empty($_POST['edit_date'])) $updateData['appointment_date'] = $_POST['edit_date'];
        if (!empty($_POST['edit_time'])) $updateData['appointment_time'] = $_POST['edit_time'];
        if (isset($_POST['edit_doctor'])) $updateData['doctor_id'] = (int)$_POST['edit_doctor'] ?: null;
        if (isset($_POST['edit_department'])) $updateData['department_id'] = (int)$_POST['edit_department'] ?: null;
        if (isset($_POST['edit_status'])) $updateData['status'] = $_POST['edit_status'];
        if (isset($_POST['edit_notes'])) $updateData['admin_notes'] = trim($_POST['edit_notes']);
        if (isset($_POST['edit_consult_type'])) $updateData['consultation_type'] = $_POST['edit_consult_type'];

        $editDate = $updateData['appointment_date'] ?? null;
        $editTime = $updateData['appointment_time'] ?? null;
        $editDoc = $updateData['doctor_id'] ?? null;
        $conflict = false;
        if ($editDoc && $editDate && $editTime) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = :doc AND appointment_date = :date AND appointment_time = :time AND status != 'cancelled' AND appointment_id != :aid");
            $chk->execute([':doc' => $editDoc, ':date' => $editDate, ':time' => $editTime, ':aid' => $aptId]);
            if ((int)$chk->fetchColumn() > 0) $conflict = true;
        }
        if ($conflict) {
            header('Location: /admin/appointments.php?msg=conflict&edit=' . $aptId . '&' . http_build_query(['status' => $filterStatus, 'search' => $search]));
        } else {
            $existing = getAppointment($pdo, $aptId);
            $oldStatus = $existing['status'] ?? '';
            updateAppointment($pdo, $aptId, $updateData);

            if (array_key_exists('status', $updateData)) {
                $updated = getAppointment($pdo, $aptId);
                if ($updated) {
                    sendAppointmentStatusNotification($pdo, $updated, $oldStatus, (string)$updateData['status']);
                } else {
                    error_log('appointments.php: could not load appointment after detail update #' . $aptId);
                }
            }

            header('Location: /admin/appointments.php?msg=updated&' . http_build_query(['status' => $filterStatus, 'search' => $search]));
        }
        exit;
    } elseif ($aptId && $aptAction === 'save_notes') {
        updateAppointment($pdo, $aptId, ['admin_notes' => trim($_POST['admin_notes'] ?? '')]);
        header('Location: /admin/appointments.php?msg=notes_saved&view=' . $aptId . '&' . http_build_query(['status' => $filterStatus, 'search' => $search]));
        exit;
    } elseif ($aptId && $aptAction === 'delete') {
        $pdo->prepare("DELETE FROM appointments WHERE appointment_id = :id")->execute([':id' => $aptId]);
        header('Location: /admin/appointments.php?msg=deleted&' . http_build_query(['status' => $filterStatus, 'search' => $search]));
        exit;
    }
}

require_once __DIR__ . '/../includes/admin_header.php';

$success = $error = '';
$viewApt = null;
$editApt = null;

$settings = getSettings($pdo);
$waNumber = $settings['whatsapp_number'] ?? '';
$currency = $settings['currency_symbol'] ?? '₹';
$siteName = $settings['site_name'] ?? 'JMedi';
$sitePhone = $settings['phone'] ?? '';

$stats = getAppointmentStats($pdo);
$doctors = getDoctors($pdo, null, false);
$departments = getDepartments($pdo, false);

$msgMap = [
    'confirmed' => 'Appointment confirmed successfully.',
    'completed' => 'Appointment marked as completed.',
    'cancelled' => 'Appointment cancelled.',
    'rescheduled' => 'Appointment marked as rescheduled.',
    'pending' => 'Appointment set back to pending.',
    'deleted' => 'Appointment deleted.',
    'updated' => 'Appointment updated successfully.',
    'notes_saved' => 'Admin notes saved.',
    'conflict' => 'Cannot save: another appointment already exists for this doctor at the selected date and time.',
];
$msg = $_GET['msg'] ?? '';
if ($msg === 'conflict') {
    $error = $msgMap['conflict'];
} else {
    $success = $msgMap[$msg] ?? '';
}

if (isset($_GET['view']) && (int)$_GET['view']) {
    $viewApt = getAppointment($pdo, (int)$_GET['view']);
    if ($viewApt && $myDoctorId && (int)($viewApt['doctor_id'] ?? 0) !== $myDoctorId) {
        $viewApt = null;
    }
}
if (isset($_GET['edit']) && (int)$_GET['edit']) {
    $editApt = getAppointment($pdo, (int)$_GET['edit']);
    if ($editApt && $myDoctorId && (int)($editApt['doctor_id'] ?? 0) !== $myDoctorId) {
        $editApt = null;
    }
}

$appointments = getAppointments($pdo, $filterStatus ?: null, $search ?: null, $myDoctorId);
$csrfToken = generateCSRFToken();

$statusColors = [
    'pending' => ['bg' => '#fff3cd', 'text' => '#856404', 'icon' => 'fa-clock'],
    'confirmed' => ['bg' => '#d1e7dd', 'text' => '#0f5132', 'icon' => 'fa-check-circle'],
    'completed' => ['bg' => '#cff4fc', 'text' => '#055160', 'icon' => 'fa-check-double'],
    'cancelled' => ['bg' => '#f8d7da', 'text' => '#842029', 'icon' => 'fa-times-circle'],
    'rescheduled' => ['bg' => '#e2e3ff', 'text' => '#3d3d9e', 'icon' => 'fa-calendar-alt'],
];

function getStatusBadge(string $status, array $colors): string {
    $c = $colors[$status] ?? ['bg' => '#e9ecef', 'text' => '#495057', 'icon' => 'fa-question'];
    return '<span style="background:' . $c['bg'] . ';color:' . $c['text'] . ';padding:4px 12px;border-radius:20px;font-size:0.8rem;font-weight:600;"><i class="fas ' . $c['icon'] . ' me-1"></i>' . ucfirst($status) . '</span>';
}

function buildWhatsAppLink(string $waNumber, array $apt, string $siteName, string $currency): string {
    $date = date('D, M d, Y', strtotime($apt['appointment_date']));
    $time = date('h:i A', strtotime($apt['appointment_time']));
    $doctor = $apt['doctor_name'] ?? 'Our Doctor';
    $dept = $apt['department_name'] ?? '';
    $fee = $apt['consultation_fee'] ?? '';

    $msg = "Hello {$apt['patient_name']},\n\n";
    $msg .= "Your appointment at *{$siteName}* has been *confirmed*.\n\n";
    $msg .= "📋 *Appointment Details:*\n";
    $msg .= "👨‍⚕️ Doctor: {$doctor}\n";
    if ($dept) $msg .= "🏥 Department: {$dept}\n";
    $msg .= "📅 Date: {$date}\n";
    $msg .= "⏰ Time: {$time}\n";
    if ($fee) $msg .= "💰 Fee: {$currency}{$fee}\n";
    $msg .= "\nPlease arrive 10 minutes early. For any changes, contact us.\n";
    $msg .= "\nThank you!\n{$siteName}";

    $phone = preg_replace('/[^0-9]/', '', $apt['phone']);
    return 'https://wa.me/' . $phone . '?text=' . rawurlencode($msg);
}

function buildEmailLink(array $apt, string $siteName, string $currency): string {
    $date = date('D, M d, Y', strtotime($apt['appointment_date']));
    $time = date('h:i A', strtotime($apt['appointment_time']));
    $doctor = $apt['doctor_name'] ?? 'Our Doctor';
    $dept = $apt['department_name'] ?? '';
    $fee = $apt['consultation_fee'] ?? '';

    $subject = "Appointment Confirmation - {$siteName} ({$date})";
    $body = "Dear {$apt['patient_name']},\n\n";
    $body .= "Your appointment at {$siteName} has been confirmed.\n\n";
    $body .= "Appointment Details:\n";
    $body .= "Doctor: {$doctor}\n";
    if ($dept) $body .= "Department: {$dept}\n";
    $body .= "Date: {$date}\n";
    $body .= "Time: {$time}\n";
    if ($fee) $body .= "Fee: {$currency}{$fee}\n";
    $body .= "\nPlease arrive 10 minutes early.\n\nThank you,\n{$siteName}";

    return 'mailto:' . $apt['email'] . '?subject=' . rawurlencode($subject) . '&body=' . rawurlencode($body);
}
?>

<style>
.stat-card-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 24px; }
.stat-mini { background: #fff; border-radius: 14px; padding: 18px 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 14px; transition: transform 0.15s; }
.stat-mini:hover { transform: translateY(-2px); }
.stat-mini .stat-icon-box { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
.stat-mini .stat-num { font-size: 1.6rem; font-weight: 800; line-height: 1; }
.stat-mini .stat-lbl { font-size: 0.78rem; color: #888; font-weight: 500; margin-top: 2px; }

.apt-table th { font-size: 0.8rem; text-transform: uppercase; color: #888; font-weight: 600; border-bottom: 2px solid #f0f0f0; padding: 10px 12px; white-space: nowrap; }
.apt-table td { padding: 12px; vertical-align: middle; font-size: 0.9rem; }
.apt-table tr { transition: background 0.15s; }
.apt-table tr:hover { background: #f8fffe; }

.patient-cell { display: flex; align-items: center; gap: 10px; }
.patient-avatar { width: 36px; height: 36px; border-radius: 50%; background: #e8f4fd; color: #0d9488; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; flex-shrink: 0; }
.patient-name { font-weight: 600; color: #1a1a1a; }
.patient-contact { font-size: 0.78rem; color: #999; }

.action-btn { width: 32px; height: 32px; border-radius: 8px; border: none; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem; cursor: pointer; transition: all 0.15s; }
.action-btn:hover { transform: scale(1.1); }
.action-btn.view { background: #e8f4fd; color: #0d6efd; }
.action-btn.whatsapp { background: #d4edda; color: #25d366; }
.action-btn.email { background: #fff3cd; color: #e67e22; }
.action-btn.call { background: #e8f4fd; color: #0d9488; }
.action-btn.edit { background: #f0e6ff; color: #7c3aed; }
.action-btn.delete { background: #f8d7da; color: #dc3545; }

.detail-panel { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 28px; margin-bottom: 24px; }
.detail-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #f0f0f0; }
.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.detail-item { padding: 12px 16px; background: #f8f9fa; border-radius: 10px; }
.detail-item .label { font-size: 0.75rem; text-transform: uppercase; color: #999; font-weight: 600; margin-bottom: 4px; }
.detail-item .value { font-size: 0.95rem; font-weight: 600; color: #1a1a1a; }
.detail-item .value a { color: #0d9488; text-decoration: none; }
.detail-item .value a:hover { text-decoration: underline; }

.contact-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.contact-btn { padding: 8px 16px; border-radius: 10px; border: none; font-size: 0.85rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; transition: all 0.15s; }
.contact-btn:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.12); }
.contact-btn.wa { background: #25d366; color: #fff; }
.contact-btn.em { background: #e67e22; color: #fff; }
.contact-btn.ph { background: #0d9488; color: #fff; }
.contact-btn.print { background: #6c757d; color: #fff; }

.notes-area { background: #fffef5; border: 2px solid #fff3cd; border-radius: 12px; padding: 16px; }
.notes-area textarea { border: none; background: transparent; width: 100%; resize: vertical; min-height: 80px; font-size: 0.9rem; }
.notes-area textarea:focus { outline: none; }

.edit-panel { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 28px; margin-bottom: 24px; }

.status-flow { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
.status-flow-btn { padding: 6px 14px; border-radius: 8px; border: 2px solid; font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: all 0.15s; background: transparent; }
.status-flow-btn:hover { transform: scale(1.03); }
.status-flow-btn.s-confirmed { border-color: #0f5132; color: #0f5132; }
.status-flow-btn.s-confirmed:hover { background: #d1e7dd; }
.status-flow-btn.s-completed { border-color: #055160; color: #055160; }
.status-flow-btn.s-completed:hover { background: #cff4fc; }
.status-flow-btn.s-cancelled { border-color: #842029; color: #842029; }
.status-flow-btn.s-cancelled:hover { background: #f8d7da; }
.status-flow-btn.s-rescheduled { border-color: #3d3d9e; color: #3d3d9e; }
.status-flow-btn.s-rescheduled:hover { background: #e2e3ff; }
.status-flow-btn.s-pending { border-color: #856404; color: #856404; }
.status-flow-btn.s-pending:hover { background: #fff3cd; }

.msg-preview { max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 0.82rem; color: #888; }

@media print {
    .admin-sidebar, .admin-topbar, .stat-card-row, .table-card, .no-print { display: none !important; }
    .detail-panel { box-shadow: none; border: 1px solid #ddd; }
}
</style>

<?php if ($success): ?><div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= e($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-2"></i><?= e($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<?php if ($viewApt): ?>
<div class="detail-panel" id="detailPanel">
    <div class="detail-header">
        <div>
            <h5 style="font-weight:800;margin:0;">Appointment #<?= $viewApt['appointment_id'] ?></h5>
            <small class="text-muted">Booked on <?= date('M d, Y \a\t h:i A', strtotime($viewApt['created_at'])) ?></small>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?= getStatusBadge($viewApt['status'], $statusColors) ?>
            <a href="/admin/appointments.php?<?= http_build_query(['status' => $filterStatus, 'search' => $search]) ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i></a>
        </div>
    </div>

    <div class="detail-grid">
        <div class="detail-item">
            <div class="label">Patient Name</div>
            <div class="value"><i class="fas fa-user me-2 text-muted"></i><?= e($viewApt['patient_name']) ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Email</div>
            <div class="value"><a href="mailto:<?= e($viewApt['email']) ?>"><i class="fas fa-envelope me-2"></i><?= e($viewApt['email']) ?></a></div>
        </div>
        <div class="detail-item">
            <div class="label">Phone</div>
            <div class="value"><a href="tel:<?= e($viewApt['phone']) ?>"><i class="fas fa-phone me-2"></i><?= e($viewApt['phone']) ?></a></div>
        </div>
        <div class="detail-item">
            <div class="label">Consultation Type</div>
            <div class="value">
                <?php $ct = $viewApt['consultation_type'] ?? 'clinic';
                if (strpos($viewApt['message'] ?? '', '[Online Consult]') === 0) $ct = 'online';
                elseif (strpos($viewApt['message'] ?? '', '[Clinic Visit]') === 0) $ct = 'clinic';
                ?>
                <i class="fas <?= $ct === 'online' ? 'fa-video' : 'fa-hospital' ?> me-2"></i><?= $ct === 'online' ? 'Online Consultation' : 'Clinic Visit' ?>
            </div>
        </div>
        <div class="detail-item">
            <div class="label">Doctor</div>
            <div class="value">
                <?php if ($viewApt['doctor_name']): ?>
                <i class="fas fa-user-md me-2 text-muted"></i><?= e($viewApt['doctor_name']) ?>
                <?php if ($viewApt['doctor_specialization']): ?><br><small class="text-muted"><?= e($viewApt['doctor_specialization']) ?></small><?php endif; ?>
                <?php else: ?>–<?php endif; ?>
            </div>
        </div>
        <div class="detail-item">
            <div class="label">Department</div>
            <div class="value"><i class="fas fa-building me-2 text-muted"></i><?= e($viewApt['department_name'] ?? '–') ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Appointment Date</div>
            <div class="value"><i class="fas fa-calendar me-2 text-muted"></i><?= date('l, M d, Y', strtotime($viewApt['appointment_date'])) ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Appointment Time</div>
            <div class="value"><i class="fas fa-clock me-2 text-muted"></i><?= date('h:i A', strtotime($viewApt['appointment_time'])) ?></div>
        </div>
        <?php if ($viewApt['consultation_fee']): ?>
        <div class="detail-item">
            <div class="label">Consultation Fee</div>
            <div class="value"><i class="fas fa-rupee-sign me-2 text-muted"></i><?= $currency ?><?= e($viewApt['consultation_fee']) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($viewApt['message']): ?>
    <div class="mt-3 p-3 bg-light rounded-3">
        <div style="font-size:0.75rem;text-transform:uppercase;color:#999;font-weight:600;margin-bottom:6px;">Patient Message</div>
        <p class="mb-0" style="font-size:0.9rem;"><?= nl2br(e($viewApt['message'])) ?></p>
    </div>
    <?php endif; ?>

    <div class="mt-4">
        <div style="font-size:0.8rem;text-transform:uppercase;color:#999;font-weight:600;margin-bottom:10px;">Quick Actions</div>
        <div class="contact-actions">
            <a href="<?= buildWhatsAppLink($waNumber, $viewApt, $siteName, $currency) ?>" target="_blank" class="contact-btn wa"><i class="fab fa-whatsapp"></i> Send WhatsApp</a>
            <a href="<?= buildEmailLink($viewApt, $siteName, $currency) ?>" class="contact-btn em"><i class="fas fa-envelope"></i> Send Email</a>
            <a href="tel:<?= e($viewApt['phone']) ?>" class="contact-btn ph"><i class="fas fa-phone"></i> Call Patient</a>
            <a href="/admin/appointments.php?edit=<?= $viewApt['appointment_id'] ?>&<?= http_build_query(['status' => $filterStatus, 'search' => $search]) ?>" class="contact-btn" style="background:#7c3aed;color:#fff;"><i class="fas fa-edit"></i> Edit</a>
            <button onclick="window.print()" class="contact-btn print"><i class="fas fa-print"></i> Print</button>
        </div>
    </div>

    <div class="mt-4">
        <div style="font-size:0.8rem;text-transform:uppercase;color:#999;font-weight:600;margin-bottom:10px;">Change Status</div>
        <div class="status-flow">
            <?php
            $statusTransitions = [
                'pending' => ['confirmed', 'cancelled'],
                'confirmed' => ['completed', 'rescheduled', 'cancelled'],
                'rescheduled' => ['confirmed', 'cancelled'],
                'completed' => ['pending'],
                'cancelled' => ['pending'],
            ];
            $available = $statusTransitions[$viewApt['status']] ?? [];
            foreach ($available as $ns):
            ?>
            <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="appointment_id" value="<?= $viewApt['appointment_id'] ?>">
                <input type="hidden" name="apt_action" value="update_status">
                <input type="hidden" name="new_status" value="<?= $ns ?>">
                <button class="status-flow-btn s-<?= $ns ?>"><i class="fas <?= $statusColors[$ns]['icon'] ?? 'fa-circle' ?> me-1"></i>Mark <?= ucfirst($ns) ?></button>
            </form>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="mt-4">
        <div style="font-size:0.8rem;text-transform:uppercase;color:#999;font-weight:600;margin-bottom:10px;">Admin Notes</div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="appointment_id" value="<?= $viewApt['appointment_id'] ?>">
            <input type="hidden" name="apt_action" value="save_notes">
            <div class="notes-area">
                <textarea name="admin_notes" placeholder="Add internal notes about this appointment..."><?= e($viewApt['admin_notes'] ?? '') ?></textarea>
            </div>
            <button class="btn btn-sm btn-dark mt-2"><i class="fas fa-save me-1"></i>Save Notes</button>
        </form>
    </div>

    <?php if ($viewApt['doctor_phone'] || $viewApt['doctor_email']): ?>
    <div class="mt-4">
        <div style="font-size:0.8rem;text-transform:uppercase;color:#999;font-weight:600;margin-bottom:10px;">Doctor Contact</div>
        <div class="contact-actions">
            <?php if ($viewApt['doctor_phone']): ?>
            <a href="tel:<?= e($viewApt['doctor_phone']) ?>" class="contact-btn ph"><i class="fas fa-phone"></i> <?= e($viewApt['doctor_phone']) ?></a>
            <?php endif; ?>
            <?php if ($viewApt['doctor_email']): ?>
            <a href="mailto:<?= e($viewApt['doctor_email']) ?>" class="contact-btn em"><i class="fas fa-envelope"></i> <?= e($viewApt['doctor_email']) ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($editApt): ?>
<div class="edit-panel no-print" id="editPanel">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h5 style="font-weight:800;margin:0;"><i class="fas fa-edit me-2" style="color:#7c3aed;"></i>Edit Appointment #<?= $editApt['appointment_id'] ?></h5>
        <a href="/admin/appointments.php?view=<?= $editApt['appointment_id'] ?>&<?= http_build_query(['status' => $filterStatus, 'search' => $search]) ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i></a>
    </div>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="appointment_id" value="<?= $editApt['appointment_id'] ?>">
        <input type="hidden" name="apt_action" value="update_details">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Patient Name</label>
                <input type="text" class="form-control" value="<?= e($editApt['patient_name']) ?>" disabled>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Status</label>
                <select name="edit_status" class="form-select">
                    <?php foreach (['pending','confirmed','completed','cancelled','rescheduled'] as $st): ?>
                    <option value="<?= $st ?>" <?= $editApt['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Appointment Date</label>
                <input type="date" name="edit_date" class="form-control" value="<?= e($editApt['appointment_date']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Appointment Time</label>
                <input type="time" name="edit_time" class="form-control" value="<?= e(substr($editApt['appointment_time'], 0, 5)) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Consultation Type</label>
                <select name="edit_consult_type" class="form-select">
                    <option value="clinic" <?= ($editApt['consultation_type'] ?? '') === 'clinic' ? 'selected' : '' ?>>Clinic Visit</option>
                    <option value="online" <?= ($editApt['consultation_type'] ?? '') === 'online' ? 'selected' : '' ?>>Online Consult</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Doctor</label>
                <select name="edit_doctor" class="form-select">
                    <option value="">– No Doctor –</option>
                    <?php foreach ($doctors as $doc): ?>
                    <option value="<?= $doc['doctor_id'] ?>" <?= $editApt['doctor_id'] == $doc['doctor_id'] ? 'selected' : '' ?>><?= e($doc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Department</label>
                <select name="edit_department" class="form-select">
                    <option value="">– No Department –</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?= $dept['department_id'] ?>" <?= $editApt['department_id'] == $dept['department_id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold small">Admin Notes</label>
                <textarea name="edit_notes" class="form-control" rows="3" placeholder="Internal notes..."><?= e($editApt['admin_notes'] ?? '') ?></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-dark"><i class="fas fa-save me-1"></i>Save Changes</button>
                <a href="/admin/appointments.php?view=<?= $editApt['appointment_id'] ?>&<?= http_build_query(['status' => $filterStatus, 'search' => $search]) ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="stat-card-row no-print">
    <div class="stat-mini">
        <div class="stat-icon-box" style="background:#e8f4fd;color:#0d6efd;"><i class="fas fa-calendar-day"></i></div>
        <div><div class="stat-num"><?= $stats['today'] ?></div><div class="stat-lbl">Today</div></div>
    </div>
    <div class="stat-mini">
        <div class="stat-icon-box" style="background:#fff3cd;color:#856404;"><i class="fas fa-clock"></i></div>
        <div><div class="stat-num"><?= $stats['pending'] ?></div><div class="stat-lbl">Pending</div></div>
    </div>
    <div class="stat-mini">
        <div class="stat-icon-box" style="background:#d1e7dd;color:#0f5132;"><i class="fas fa-check-circle"></i></div>
        <div><div class="stat-num"><?= $stats['confirmed'] ?></div><div class="stat-lbl">Confirmed</div></div>
    </div>
    <div class="stat-mini">
        <div class="stat-icon-box" style="background:#cff4fc;color:#055160;"><i class="fas fa-check-double"></i></div>
        <div><div class="stat-num"><?= $stats['completed'] ?></div><div class="stat-lbl">Completed</div></div>
    </div>
    <div class="stat-mini">
        <div class="stat-icon-box" style="background:#f8d7da;color:#842029;"><i class="fas fa-times-circle"></i></div>
        <div><div class="stat-num"><?= $stats['cancelled'] ?></div><div class="stat-lbl">Cancelled</div></div>
    </div>
    <div class="stat-mini">
        <div class="stat-icon-box" style="background:#f0e6ff;color:#7c3aed;"><i class="fas fa-calendar-alt"></i></div>
        <div><div class="stat-num"><?= $stats['this_month'] ?></div><div class="stat-lbl">This Month</div></div>
    </div>
</div>

<div class="table-card no-print">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h5 class="mb-0" style="font-weight:700;">All Appointments <span class="badge bg-dark"><?= count($appointments) ?></span></h5>
        <div class="d-flex gap-2">
            <form method="GET" class="d-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search patient..." value="<?= e($search) ?>" style="width:180px;border-radius:8px;">
                <select name="status" class="form-select form-select-sm" style="width:150px;border-radius:8px;" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="confirmed" <?= $filterStatus === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="rescheduled" <?= $filterStatus === 'rescheduled' ? 'selected' : '' ?>>Rescheduled</option>
                </select>
                <button class="btn btn-sm btn-primary" style="border-radius:8px;"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table apt-table">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Doctor / Dept</th>
                    <th>Schedule</th>
                    <th>Type</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-calendar-times me-2"></i>No appointments found</td></tr>
                <?php else: ?>
                <?php foreach ($appointments as $apt):
                    $initials = strtoupper(substr($apt['patient_name'], 0, 1));
                    $ct = $apt['consultation_type'] ?? 'clinic';
                    if (strpos($apt['message'] ?? '', '[Online Consult]') === 0) $ct = 'online';
                    $msgClean = preg_replace('/^\[(Online Consult|Clinic Visit)\]\s*/', '', $apt['message'] ?? '');
                ?>
                <tr>
                    <td>
                        <div class="patient-cell">
                            <div class="patient-avatar"><?= $initials ?></div>
                            <div>
                                <div class="patient-name"><?= e($apt['patient_name']) ?></div>
                                <div class="patient-contact"><?= e($apt['phone']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600;font-size:0.88rem;"><?= e($apt['doctor_name'] ?? '–') ?></div>
                        <div style="font-size:0.78rem;color:#999;"><?= e($apt['department_name'] ?? '') ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600;font-size:0.88rem;"><?= date('M d, Y', strtotime($apt['appointment_date'])) ?></div>
                        <div style="font-size:0.82rem;color:#0d9488;font-weight:500;"><?= date('h:i A', strtotime($apt['appointment_time'])) ?></div>
                    </td>
                    <td>
                        <span style="font-size:0.8rem;padding:3px 8px;border-radius:6px;background:<?= $ct === 'online' ? '#e8f4fd' : '#f0f0f0' ?>;color:<?= $ct === 'online' ? '#0d6efd' : '#555' ?>;">
                            <i class="fas <?= $ct === 'online' ? 'fa-video' : 'fa-hospital' ?> me-1"></i><?= $ct === 'online' ? 'Online' : 'Clinic' ?>
                        </span>
                    </td>
                    <td><div class="msg-preview" title="<?= e($msgClean) ?>"><?= e($msgClean ?: '–') ?></div></td>
                    <td><?= getStatusBadge($apt['status'], $statusColors) ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="/admin/appointments.php?view=<?= $apt['appointment_id'] ?>&<?= http_build_query(['status' => $filterStatus, 'search' => $search]) ?>" class="action-btn view" title="View Details"><i class="fas fa-eye"></i></a>
                            <a href="<?= buildWhatsAppLink($waNumber, $apt, $siteName, $currency) ?>" target="_blank" class="action-btn whatsapp" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                            <a href="<?= buildEmailLink($apt, $siteName, $currency) ?>" class="action-btn email" title="Email"><i class="fas fa-envelope"></i></a>
                            <a href="tel:<?= e($apt['phone']) ?>" class="action-btn call" title="Call"><i class="fas fa-phone"></i></a>
                            <a href="/admin/appointments.php?edit=<?= $apt['appointment_id'] ?>&<?= http_build_query(['status' => $filterStatus, 'search' => $search]) ?>" class="action-btn edit" title="Edit"><i class="fas fa-edit"></i></a>
                            <?php if ($apt['status'] === 'pending'): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="appointment_id" value="<?= $apt['appointment_id'] ?>">
                                <input type="hidden" name="apt_action" value="update_status">
                                <input type="hidden" name="new_status" value="confirmed">
                                <button class="action-btn" style="background:#d1e7dd;color:#0f5132;" title="Confirm"><i class="fas fa-check"></i></button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="appointment_id" value="<?= $apt['appointment_id'] ?>">
                                <input type="hidden" name="apt_action" value="delete">
                                <button type="button" class="action-btn delete" title="Delete" data-delete-trigger data-delete-label="appointment #<?= $apt['appointment_id'] ?> for <?= e($apt['patient_name']) ?>"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
