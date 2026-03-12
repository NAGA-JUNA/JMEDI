<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
requirePermission('doctors');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $pdo->prepare("DELETE FROM doctors WHERE doctor_id = :id")->execute([':id' => (int)$_POST['delete_id']]);
    }
    header('Location: /admin/doctors.php?msg=deleted');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review_id'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $pdo->prepare("DELETE FROM doctor_reviews WHERE id = :id")->execute([':id' => (int)$_POST['delete_review_id']]);
        $doctorId = (int)($_POST['review_doctor_id'] ?? 0);
        if ($doctorId) {
            $cnt = (int)$pdo->prepare("SELECT COUNT(*) FROM doctor_reviews WHERE doctor_id=:id")->execute([':id' => $doctorId]) ? $pdo->query("SELECT COUNT(*) FROM doctor_reviews WHERE doctor_id={$doctorId}")->fetchColumn() : 0;
            $avg = $pdo->query("SELECT AVG(rating) FROM doctor_reviews WHERE doctor_id={$doctorId}")->fetchColumn();
            $pdo->prepare("UPDATE doctors SET reviews_count=:cnt, rating=:avg WHERE doctor_id=:id")->execute([':cnt' => $cnt, ':avg' => round((float)$avg, 1), ':id' => $doctorId]);
        }
    }
    header('Location: /admin/doctors.php?action=edit&id=' . (int)($_POST['review_doctor_id'] ?? 0) . '&msg=review_deleted');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_review'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $doctorId  = (int)($_POST['review_doctor_id'] ?? 0);
        $patName   = trim($_POST['patient_name'] ?? '');
        $rating    = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $comment   = trim($_POST['comment'] ?? '');
        $verified  = (int)(!empty($_POST['is_verified']));
        if ($doctorId && $patName) {
            $pdo->prepare("INSERT INTO doctor_reviews (doctor_id, patient_name, rating, comment, is_verified) VALUES (:d,:n,:r,:c,:v)")->execute([':d' => $doctorId, ':n' => $patName, ':r' => $rating, ':c' => $comment, ':v' => $verified]);
            $cnt = (int)$pdo->query("SELECT COUNT(*) FROM doctor_reviews WHERE doctor_id={$doctorId}")->fetchColumn();
            $avg = $pdo->query("SELECT AVG(rating) FROM doctor_reviews WHERE doctor_id={$doctorId}")->fetchColumn();
            $pdo->prepare("UPDATE doctors SET reviews_count=:cnt, rating=:avg WHERE doctor_id=:id")->execute([':cnt' => $cnt, ':avg' => round((float)$avg, 1), ':id' => $doctorId]);
        }
    }
    header('Location: /admin/doctors.php?action=edit&id=' . (int)($_POST['review_doctor_id'] ?? 0) . '&msg=review_added');
    exit;
}

$pageTitle = 'Manage Doctors';
require_once __DIR__ . '/../includes/admin_header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$departments = getDepartments($pdo, false);
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id']) && !isset($_POST['add_review']) && !isset($_POST['delete_review_id'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');

        if (empty($name)) {
            $error = 'Doctor name is required.';
        } else {
            $doctorId = isset($_POST['doctor_id']) && $_POST['doctor_id'] ? (int)$_POST['doctor_id'] : 0;
            if (!$slug) $slug = generateDoctorSlug($pdo, $name, $doctorId ?: null);

            $photo = null;
            if (!empty($_FILES['photo']['name'])) {
                $photo = uploadImage($_FILES['photo']);
            }

            $data = [
                ':name'               => $name,
                ':slug'               => $slug,
                ':department_id'      => (int)($_POST['department_id'] ?? 0) ?: null,
                ':qualification'      => trim($_POST['qualification'] ?? ''),
                ':experience'         => trim($_POST['experience'] ?? ''),
                ':specialization'     => trim($_POST['specialization'] ?? ''),
                ':languages'          => trim($_POST['languages'] ?? 'English'),
                ':bio'                => trim($_POST['bio'] ?? ''),
                ':certifications'     => trim($_POST['certifications'] ?? ''),
                ':services'           => trim($_POST['services'] ?? ''),
                ':email'              => trim($_POST['email'] ?? ''),
                ':phone'              => trim($_POST['phone'] ?? ''),
                ':available_days'     => trim($_POST['available_days'] ?? ''),
                ':available_time'     => trim($_POST['available_time'] ?? ''),
                ':consultation_fee'   => trim($_POST['consultation_fee'] ?? '') !== '' ? (float)$_POST['consultation_fee'] : 500.00,
                ':consultation_types' => trim($_POST['consultation_types'] ?? 'both'),
                ':video_consultation' => (int)(!empty($_POST['video_consultation'])),
                ':clinic_name'        => trim($_POST['clinic_name'] ?? ''),
                ':clinic_address'     => trim($_POST['clinic_address'] ?? ''),
                ':clinic_location'    => trim($_POST['clinic_location'] ?? ''),
                ':patients_treated'   => (int)($_POST['patients_treated'] ?? 0),
                ':success_rate'       => max(0, min(100, (int)($_POST['success_rate'] ?? 98))),
                ':rating'             => max(0, min(5, (float)($_POST['rating'] ?? 5.0))),
                ':profile_template'   => max(1, min(2, (int)($_POST['profile_template'] ?? 1))),
                ':status'             => (int)($_POST['status'] ?? 1),
            ];

            try {
                if ($doctorId) {
                    $oldDoctor = getDoctor($pdo, $doctorId);
                    $oldStatus = (int)($oldDoctor['status'] ?? 0);

                    $sql = "UPDATE doctors SET name=:name, slug=:slug, department_id=:department_id, qualification=:qualification, experience=:experience, specialization=:specialization, languages=:languages, bio=:bio, certifications=:certifications, services=:services, email=:email, phone=:phone, available_days=:available_days, available_time=:available_time, consultation_fee=:consultation_fee, consultation_types=:consultation_types, video_consultation=:video_consultation, clinic_name=:clinic_name, clinic_address=:clinic_address, clinic_location=:clinic_location, patients_treated=:patients_treated, success_rate=:success_rate, rating=:rating, profile_template=:profile_template, status=:status";
                    if ($photo) { $sql .= ", photo=:photo"; $data[':photo'] = $photo; }
                    $sql .= " WHERE doctor_id=:id";
                    $data[':id'] = $doctorId;
                    $pdo->prepare($sql)->execute($data);

                    $newStatus = (int)$data[':status'];
                    if ($oldStatus !== 1 && $newStatus === 1) {
                        $updatedDoctor = getDoctor($pdo, $doctorId);
                        if ($updatedDoctor) {
                            sendDoctorApprovedNotification($pdo, $updatedDoctor);
                        }
                    }

                    $success = 'Doctor "' . $name . '" updated successfully. Template ' . (int)($_POST['profile_template'] ?? 1) . ' saved.';
                    $action = 'edit';
                } else {
                    $data[':photo'] = $photo;
                    $pdo->prepare("INSERT INTO doctors (name, slug, photo, department_id, qualification, experience, specialization, languages, bio, certifications, services, email, phone, available_days, available_time, consultation_fee, consultation_types, video_consultation, clinic_name, clinic_address, clinic_location, patients_treated, success_rate, rating, profile_template, status) VALUES (:name, :slug, :photo, :department_id, :qualification, :experience, :specialization, :languages, :bio, :certifications, :services, :email, :phone, :available_days, :available_time, :consultation_fee, :consultation_types, :video_consultation, :clinic_name, :clinic_address, :clinic_location, :patients_treated, :success_rate, :rating, :profile_template, :status)")->execute($data);
                    $success = 'Dr. ' . $name . ' has been added successfully.';
                    $action = 'list';
                }
            } catch (\PDOException $e) {
                $error = 'Database error: ' . $e->getMessage() . ' — Please run the latest database migration (database/migrations/v2_doctor_profile_fields.sql) in phpMyAdmin.';
            }
        }
    }
}

$editDoctor = null;
$doctorReviews = [];
if (($action === 'edit') && $id) {
    $editDoctor = getDoctor($pdo, $id);
    if (!$editDoctor) { header('Location: /admin/doctors.php'); exit; }
    try { $doctorReviews = getDoctorReviews($pdo, $id, 50); } catch (\Throwable $e) {}
}

if (isset($_GET['msg'])) {
    $msgs = ['deleted' => 'Doctor deleted.', 'review_deleted' => 'Review deleted.', 'review_added' => 'Review added.'];
    if (isset($msgs[$_GET['msg']])) $success = $msgs[$_GET['msg']];
}

$allDoctors = getDoctors($pdo, null, false);
?>

<?php if ($success): ?>
<script>document.addEventListener('DOMContentLoaded',function(){ window.showAdminToast('Saved Successfully', <?= json_encode($success) ?>, 'success'); });</script>
<?php endif; ?>
<?php if ($error): ?>
<script>document.addEventListener('DOMContentLoaded',function(){ window.showAdminToast('Error', <?= json_encode($error) ?>, 'error'); });</script>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>

<div class="dash-card mb-4">
    <div class="card-header-row">
        <h6><i class="fas fa-user-md me-2" style="color:var(--admin-accent);"></i><?= $editDoctor ? 'Edit: ' . e($editDoctor['name']) : 'Add New Doctor' ?></h6>
        <a href="/admin/doctors.php" class="tab-btn"><i class="fas fa-arrow-left me-1"></i>Back to List</a>
    </div>
    <form method="POST" enctype="multipart/form-data" id="doctorForm">
        <?= csrfField() ?>
        <?php if ($editDoctor): ?>
        <input type="hidden" name="doctor_id" value="<?= $editDoctor['doctor_id'] ?>">
        <?php endif; ?>

        <div class="doc-section">
            <div class="doc-section-title"><i class="fas fa-id-card"></i>Basic Information</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="doctorName" class="form-control" value="<?= e($editDoctor['name'] ?? '') ?>" required placeholder="Dr. First Last">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">URL Slug <small class="text-muted">(auto-generated)</small></label>
                    <div class="input-group">
                        <span class="input-group-text text-muted small">/doctor/</span>
                        <input type="text" name="slug" id="doctorSlug" class="form-control" value="<?= e($editDoctor['slug'] ?? '') ?>" placeholder="dr-first-last" pattern="[a-z0-9\-]+">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">— Select Department —</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['department_id'] ?>" <?= ($editDoctor['department_id'] ?? '') == $dept['department_id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Photo</label>
                    <?php if (!empty($editDoctor['photo'])): ?>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <img src="<?= e($editDoctor['photo']) ?>" alt="" style="width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid var(--admin-border);">
                        <small class="text-muted">Upload new to replace</small>
                    </div>
                    <?php endif; ?>
                    <input type="file" name="photo" id="photoInput" class="form-control" accept="image/*">
                    <div id="photoPreview" class="mt-2 d-none"><img id="photoPreviewImg" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid var(--admin-accent);"></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="1" <?= ($editDoctor['status'] ?? 1) == 1 ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= ($editDoctor['status'] ?? 1) == 0 ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Video Consultation</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="video_consultation" id="videoConsult" <?= ($editDoctor['video_consultation'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="videoConsult">Enable Video Consult</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="doc-section">
            <div class="doc-section-title"><i class="fas fa-stethoscope"></i>Professional Information</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Qualification</label>
                    <input type="text" name="qualification" class="form-control" value="<?= e($editDoctor['qualification'] ?? '') ?>" placeholder="e.g. MD, FACC, FSCAI">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Experience</label>
                    <input type="text" name="experience" class="form-control" value="<?= e($editDoctor['experience'] ?? '') ?>" placeholder="e.g. 15 Years">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Languages</label>
                    <input type="text" name="languages" class="form-control" value="<?= e($editDoctor['languages'] ?? 'English') ?>" placeholder="English, Hindi">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Specialization</label>
                    <input type="text" name="specialization" class="form-control" value="<?= e($editDoctor['specialization'] ?? '') ?>" placeholder="e.g. Interventional Cardiology">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Biography</label>
                    <textarea name="bio" class="form-control" rows="4" placeholder="Detailed biography of the doctor..."><?= e($editDoctor['bio'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Certifications <small class="text-muted">(one per line or comma-separated)</small></label>
                    <textarea name="certifications" class="form-control" rows="3" placeholder="Board Certified Cardiologist&#10;FACC, FSCAI"><?= e($editDoctor['certifications'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Services / Treatments <small class="text-muted">(comma-separated)</small></label>
                    <textarea name="services" class="form-control" rows="3" placeholder="Cardiac Catheterization, Stent Placement, Echocardiography"><?= e($editDoctor['services'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="doc-section">
            <div class="doc-section-title"><i class="fas fa-clinic-medical"></i>Consultation & Fees</div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Consultation Fee (₹)</label>
                    <input type="number" name="consultation_fee" class="form-control" step="0.01" min="0" value="<?= e($editDoctor['consultation_fee'] ?? '500') ?>" placeholder="500.00">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Consultation Type</label>
                    <select name="consultation_types" class="form-select">
                        <option value="both"   <?= ($editDoctor['consultation_types'] ?? 'both') === 'both'   ? 'selected' : '' ?>>Both (Online & Clinic)</option>
                        <option value="online" <?= ($editDoctor['consultation_types'] ?? '') === 'online' ? 'selected' : '' ?>>Online Only</option>
                        <option value="clinic" <?= ($editDoctor['consultation_types'] ?? '') === 'clinic' ? 'selected' : '' ?>>Clinic Only</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Available Days</label>
                    <input type="text" name="available_days" class="form-control" value="<?= e($editDoctor['available_days'] ?? '') ?>" placeholder="Mon, Tue, Wed...">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Available Time</label>
                    <input type="text" name="available_time" class="form-control" value="<?= e($editDoctor['available_time'] ?? '') ?>" placeholder="09:00 AM – 05:00 PM">
                </div>
            </div>
        </div>

        <div class="doc-section">
            <div class="doc-section-title"><i class="fas fa-hospital-alt"></i>Clinic & Location</div>
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Clinic / Hospital Name</label>
                    <input type="text" name="clinic_name" class="form-control" value="<?= e($editDoctor['clinic_name'] ?? '') ?>" placeholder="JMedi Cardiology Center">
                </div>
                <div class="col-md-7">
                    <label class="form-label fw-semibold">Clinic Address</label>
                    <input type="text" name="clinic_address" class="form-control" value="<?= e($editDoctor['clinic_address'] ?? '') ?>" placeholder="123 Medical Lane, City, State 000000">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Google Maps Embed URL <small class="text-muted">Paste the src URL from Google Maps → Share → Embed a map</small></label>
                    <input type="url" name="clinic_location" class="form-control" value="<?= e($editDoctor['clinic_location'] ?? '') ?>" placeholder="https://www.google.com/maps/embed?pb=...">
                </div>
            </div>
        </div>

        <div class="doc-section">
            <div class="doc-section-title"><i class="fas fa-chart-bar"></i>Contact & Statistics</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= e($editDoctor['email'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= e($editDoctor['phone'] ?? '') ?>">
                </div>
                <div class="col-md-4"></div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Patients Treated</label>
                    <input type="number" name="patients_treated" class="form-control" min="0" value="<?= (int)($editDoctor['patients_treated'] ?? 0) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Success Rate (%)</label>
                    <input type="number" name="success_rate" class="form-control" min="0" max="100" value="<?= (int)($editDoctor['success_rate'] ?? 98) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Rating (0-5)</label>
                    <input type="number" name="rating" class="form-control" min="0" max="5" step="0.1" value="<?= number_format((float)($editDoctor['rating'] ?? 5.0), 1) ?>">
                </div>
            </div>
        </div>

        <div class="doc-section">
            <div class="doc-section-title"><i class="fas fa-palette"></i>Profile Page Template</div>
            <div class="row g-3">
                <div class="col-12">
                    <p class="text-muted small mb-2">Choose how this doctor's public profile page will look to patients.</p>
                    <div class="d-flex gap-3 flex-wrap" id="templatePicker">
                        <?php
                        $curTpl = (int)($editDoctor['profile_template'] ?? 1);
                        $templates = [
                            1 => ['icon' => 'fas fa-id-card', 'color' => '#0D6EFD', 'title' => 'Template 1 — Classic', 'desc' => 'Dark blue gradient hero · Card layout · Professional'],
                            2 => ['icon' => 'fas fa-star', 'color' => '#0891b2', 'title' => 'Template 2 — Modern', 'desc' => 'Light airy hero · Marquee strip · Floating stats · Spinning badge'],
                        ];
                        foreach ($templates as $tval => $tinfo):
                        $selected = $curTpl === $tval;
                        ?>
                        <label class="t-picker <?= $selected ? 'selected' : '' ?>" for="tpl<?= $tval ?>">
                            <input type="radio" name="profile_template" id="tpl<?= $tval ?>" value="<?= $tval ?>" <?= $selected ? 'checked' : '' ?> class="d-none">
                            <div class="t-picker-icon" style="background:<?= $tval === 2 ? 'rgba(8,145,178,0.14)' : 'rgba(13,110,253,0.14)' ?>;color:<?= $tinfo['color'] ?>"><i class="<?= $tinfo['icon'] ?>"></i></div>
                            <div class="t-picker-body">
                                <strong><?= $tinfo['title'] ?></strong>
                                <small><?= $tinfo['desc'] ?></small>
                            </div>
                            <i class="fas fa-check-circle t-picker-check" style="color:<?= $tinfo['color'] ?>"></i>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 px-3 pb-3 pt-2">
            <button type="submit" class="btn btn-primary px-4" style="border-radius:10px;"><i class="fas fa-save me-2"></i>Save Doctor</button>
            <a href="/admin/doctors.php" class="btn btn-outline-secondary px-4" style="border-radius:10px;">Cancel</a>
            <?php if ($editDoctor && $editDoctor['slug']): ?>
            <a href="/doctor/<?= e($editDoctor['slug']) ?>" target="_blank" class="btn btn-outline-primary px-4 ms-auto" style="border-radius:10px;"><i class="fas fa-external-link-alt me-1"></i>View Profile</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if ($editDoctor): ?>
<div class="dash-card mb-4">
    <div class="card-header-row">
        <h6><i class="fas fa-comments me-2" style="color:var(--admin-accent);"></i>Patient Reviews (<?= count($doctorReviews) ?>)</h6>
    </div>
    <div class="p-3">
        <form method="POST" class="row g-2 mb-4 p-3 rounded-3" style="background:var(--admin-bg);border:1px dashed var(--admin-border);">
            <?= csrfField() ?>
            <input type="hidden" name="add_review" value="1">
            <input type="hidden" name="review_doctor_id" value="<?= $editDoctor['doctor_id'] ?>">
            <div class="col-md-3">
                <input type="text" name="patient_name" class="form-control form-control-sm" placeholder="Patient Name" required>
            </div>
            <div class="col-md-2">
                <select name="rating" class="form-select form-select-sm">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?= $i ?>"><?= $i ?> Star<?= $i > 1 ? 's' : '' ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-5">
                <input type="text" name="comment" class="form-control form-control-sm" placeholder="Review comment...">
            </div>
            <div class="col-md-2 d-flex gap-2 align-items-center">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" name="is_verified" id="revVerified" checked>
                    <label class="form-check-label small" for="revVerified">Verified</label>
                </div>
                <button type="submit" class="btn btn-primary btn-sm flex-shrink-0"><i class="fas fa-plus"></i></button>
            </div>
        </form>

        <?php if (empty($doctorReviews)): ?>
        <p class="text-muted text-center py-3 mb-0"><i class="fas fa-comments fa-2x d-block mb-2 opacity-25"></i>No reviews yet. Add the first one above.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>Patient</th><th>Rating</th><th>Comment</th><th>Verified</th><th>Date</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($doctorReviews as $rev): ?>
                <tr>
                    <td><strong><?= e($rev['patient_name']) ?></strong></td>
                    <td><span style="color:#f59e0b;"><?= str_repeat('★', $rev['rating']) ?></span></td>
                    <td style="max-width:300px;"><small><?= e(mb_strimwidth($rev['comment'] ?? '', 0, 80, '…')) ?></small></td>
                    <td><?= $rev['is_verified'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
                    <td><small><?= date('M j, Y', strtotime($rev['created_at'])) ?></small></td>
                    <td>
                        <form method="POST" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="delete_review_id" value="<?= $rev['id'] ?>">
                            <input type="hidden" name="review_doctor_id" value="<?= $editDoctor['doctor_id'] ?>">
                            <button type="button" class="btn btn-sm btn-outline-danger" data-delete-trigger data-delete-label="this review"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="dash-card mb-4">
    <div class="card-header-row">
        <h6><i class="fas fa-calendar-alt me-2" style="color:var(--admin-accent);"></i>Doctor Schedule</h6>
        <a href="/admin/doctor-schedules.php?doctor=<?= $editDoctor['doctor_id'] ?>" class="tab-btn"><i class="fas fa-edit me-1"></i>Manage Schedule</a>
    </div>
    <div class="p-3">
        <?php
        $sched = [];
        try { $sched = getDoctorSchedulesByDay($pdo, $editDoctor['doctor_id']); } catch (\Throwable $e) {}
        $dayNames = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        ?>
        <?php if (empty($sched)): ?>
        <p class="text-muted mb-0 text-center py-2"><i class="fas fa-calendar-times me-2"></i>No schedule set. <a href="/admin/doctor-schedules.php?doctor=<?= $editDoctor['doctor_id'] ?>">Click here to add schedule</a>.</p>
        <?php else: ?>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($sched as $day => $sessions): ?>
            <?php foreach ($sessions as $s): ?>
            <?php if ($s['is_active']): ?>
            <div class="badge bg-primary" style="font-size:0.75rem;font-weight:500;padding:0.4rem 0.7rem;border-radius:8px;"><?= $dayNames[(int)$day] ?? "Day $day" ?> · <?= e($s['session_label']) ?> · <?= date('g:ia', strtotime($s['start_time'])) ?>–<?= date('g:ia', strtotime($s['end_time'])) ?></div>
            <?php endif; ?>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
document.getElementById('doctorName')?.addEventListener('input', function () {
    const slugField = document.getElementById('doctorSlug');
    if (slugField && !slugField.dataset.manual) {
        slugField.value = 'dr-' + this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }
});
document.getElementById('doctorSlug')?.addEventListener('input', function () {
    this.dataset.manual = '1';
    this.value = this.value.toLowerCase().replace(/[^a-z0-9\-]/g, '');
});
document.getElementById('photoInput')?.addEventListener('change', function () {
    const preview = document.getElementById('photoPreview');
    const img = document.getElementById('photoPreviewImg');
    if (this.files[0]) {
        img.src = URL.createObjectURL(this.files[0]);
        preview.classList.remove('d-none');
    }
});
<?php if ($editDoctor && $editDoctor['slug']): ?>
document.getElementById('doctorSlug').dataset.manual = '1';
<?php endif; ?>

document.querySelectorAll('#templatePicker input[type=radio]').forEach(radio => {
    radio.addEventListener('change', () => {
        document.querySelectorAll('#templatePicker .t-picker').forEach(l => l.classList.remove('selected'));
        radio.closest('.t-picker').classList.add('selected');
    });
});
</script>

<style>
.t-picker { display:flex; align-items:center; gap:0.85rem; border:2px solid var(--admin-border); border-radius:14px; padding:0.85rem 1.1rem; cursor:pointer; transition:all 0.2s; background:var(--admin-surface); flex:1; min-width:220px; max-width:340px; }
.t-picker:hover { border-color:var(--admin-accent); box-shadow:0 2px 12px rgba(59,130,246,0.12); }
.t-picker.selected { border-color:var(--admin-accent); background:rgba(59,130,246,0.04); box-shadow:0 2px 16px rgba(59,130,246,0.15); }
.t-picker-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.05rem; flex-shrink:0; }
.t-picker-body { flex:1; }
.t-picker-body strong { display:block; font-size:0.88rem; color:var(--admin-text); font-weight:700; margin-bottom:0.15rem; }
.t-picker-body small { font-size:0.74rem; color:var(--admin-text-muted); }
.t-picker-check { font-size:1.1rem; flex-shrink:0; opacity:0; transition:opacity 0.2s; }
.t-picker.selected .t-picker-check { opacity:1; }
</style>

<?php else: ?>

<div class="dash-card">
    <div class="card-header-row">
        <h6><i class="fas fa-user-md me-2" style="color:var(--admin-accent);"></i>All Doctors (<?= count($allDoctors) ?>)</h6>
        <a href="/admin/doctors.php?action=add" class="tab-btn"><i class="fas fa-plus me-1"></i>Add Doctor</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr><th>Photo</th><th>Doctor</th><th>Department</th><th>Specialization</th><th>Fee</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($allDoctors as $doc): ?>
                <tr>
                    <td>
                        <?php if ($doc['photo']): ?>
                        <img src="<?= e($doc['photo']) ?>" alt="" style="width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid var(--admin-border);">
                        <?php else: ?>
                        <div style="width:42px;height:42px;border-radius:50%;background:var(--admin-bg);display:flex;align-items:center;justify-content:center;border:2px solid var(--admin-border);"><i class="fas fa-user-md" style="color:var(--admin-accent);font-size:0.9rem;"></i></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= e($doc['name']) ?></strong>
                        <?php if ($doc['slug']): ?><br><small class="text-muted">/doctor/<?= e($doc['slug']) ?></small><?php endif; ?>
                    </td>
                    <td><?= e($doc['department_name'] ?? '–') ?></td>
                    <td><small><?= e($doc['specialization'] ?? '–') ?></small></td>
                    <td>₹<?= number_format((float)($doc['consultation_fee'] ?? 500), 0) ?></td>
                    <td><span class="badge <?= $doc['status'] ? 'bg-success' : 'bg-secondary' ?>"><?= $doc['status'] ? 'Active' : 'Inactive' ?></span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <?php if ($doc['slug']): ?>
                            <a href="/doctor/<?= e($doc['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-info" title="View Profile"><i class="fas fa-eye"></i></a>
                            <?php endif; ?>
                            <a href="/admin/doctors.php?action=edit&id=<?= $doc['doctor_id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                            <form method="POST" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="delete_id" value="<?= $doc['doctor_id'] ?>">
                                <button type="button" class="btn btn-sm btn-outline-danger" data-delete-trigger data-delete-label="Dr. <?= e(addslashes($doc['name'])) ?>"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<style>
.doc-section { padding: 1.5rem; border-bottom: 1px solid var(--admin-border); }
.doc-section:last-of-type { border-bottom: none; }
.doc-section-title { font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--admin-accent); display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; }
.doc-section-title i { font-size: 0.9rem; }
</style>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
