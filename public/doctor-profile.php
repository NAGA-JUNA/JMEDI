<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$doctor = null;
if (!empty($_GET['slug'])) {
    $doctor = getDoctorBySlug($pdo, $_GET['slug']);
} elseif (!empty($_GET['id'])) {
    $doctor = getDoctor($pdo, (int)$_GET['id']);
    if ($doctor && $doctor['slug']) {
        header('Location: /doctor/' . $doctor['slug'], true, 301);
        exit;
    }
}

if (!$doctor) {
    header('Location: /public/doctors.php');
    exit;
}

$pageTitle = $doctor['name'] . ' | ' . ($doctor['specialization'] ?? 'Doctor') . ' | JMedi';
$reviews = [];
try { $reviews = getDoctorReviews($pdo, $doctor['doctor_id'], 10); } catch (\Throwable $e) {}
$relatedDoctors = [];
try { if ($doctor['department_id']) $relatedDoctors = getRelatedDoctors($pdo, $doctor['department_id'], $doctor['doctor_id'], 4); } catch (\Throwable $e) {}
$schedules = [];
try { $schedules = getDoctorSchedulesByDay($pdo, $doctor['doctor_id']); } catch (\Throwable $e) {}

$dayNames = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$template = (int)($doctor['profile_template'] ?? 1);
$services = $doctor['services'] ? array_map('trim', explode(',', $doctor['services'])) : [];
$certifications = $doctor['certifications'] ? array_map('trim', explode(',', $doctor['certifications'])) : [];
$languages = $doctor['languages'] ? array_map('trim', explode(',', $doctor['languages'] ?? 'English')) : ['English'];
$rating = (float)($doctor['rating'] ?? 5.0);
$fullStars  = (int)floor($rating);
$halfStar   = ($rating - $fullStars) >= 0.5;
$emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($template === 1): /* ===== TEMPLATE 1: Classic Blue Hero ===== */ ?>

<div class="page-header">
    <div class="container">
        <h1><?= e($doctor['name']) ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/">Home</a></li>
                <li class="breadcrumb-item"><a href="/public/doctors.php">Doctors</a></li>
                <li class="breadcrumb-item active"><?= e($doctor['name']) ?></li>
            </ol>
        </nav>
    </div>
</div>

<section class="dp-hero">
    <div class="container">
        <div class="dp-hero-card">
            <div class="row g-0 align-items-stretch">
                <div class="col-lg-4 col-md-5 dp-hero-img-col">
                    <?php if ($doctor['photo']): ?>
                    <img src="<?= e($doctor['photo']) ?>" alt="<?= e($doctor['name']) ?>" class="dp-hero-img" loading="lazy">
                    <?php else: ?>
                    <div class="dp-hero-img-placeholder"><i class="fas fa-user-md"></i></div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-8 col-md-7 dp-hero-info">
                    <div class="dp-badges mb-3">
                        <?php if ($doctor['department_name']): ?>
                        <span class="dp-badge-dept"><i class="fas fa-hospital me-1"></i><?= e($doctor['department_name']) ?></span>
                        <?php endif; ?>
                        <?php if (in_array($doctor['consultation_types'] ?? '', ['both', 'online']) || ($doctor['video_consultation'] ?? 0)): ?>
                        <span class="dp-badge-online"><i class="fas fa-video me-1"></i>Online Available</span>
                        <?php endif; ?>
                    </div>
                    <h1 class="dp-hero-name"><?= e($doctor['name']) ?></h1>
                    <?php if ($doctor['specialization']): ?>
                    <p class="dp-hero-spec"><?= e($doctor['specialization']) ?></p>
                    <?php endif; ?>
                    <?php if ($doctor['qualification']): ?>
                    <p class="dp-hero-qual"><i class="fas fa-graduation-cap me-2"></i><?= e($doctor['qualification']) ?></p>
                    <?php endif; ?>
                    <div class="dp-rating mb-3">
                        <div class="dp-stars">
                            <?php for ($i = 0; $i < $fullStars; $i++): ?><i class="fas fa-star"></i><?php endfor; ?>
                            <?php if ($halfStar): ?><i class="fas fa-star-half-alt"></i><?php endif; ?>
                            <?php for ($i = 0; $i < $emptyStars; $i++): ?><i class="far fa-star"></i><?php endfor; ?>
                        </div>
                        <span class="dp-rating-num"><?= number_format($rating, 1) ?></span>
                        <span class="dp-rating-count">(<?= number_format((int)($doctor['reviews_count'] ?? 0)) ?> reviews)</span>
                    </div>
                    <div class="dp-quick-stats">
                        <div class="dp-stat">
                            <i class="fas fa-briefcase-medical"></i>
                            <div><strong><?= e($doctor['experience'] ?? 'N/A') ?></strong><small>Experience</small></div>
                        </div>
                        <div class="dp-stat">
                            <i class="fas fa-users"></i>
                            <div><strong><?= $doctor['patients_treated'] ? number_format($doctor['patients_treated']) . '+' : 'N/A' ?></strong><small>Patients</small></div>
                        </div>
                        <div class="dp-stat">
                            <i class="fas fa-check-circle"></i>
                            <div><strong><?= (int)($doctor['success_rate'] ?? 98) ?>%</strong><small>Success Rate</small></div>
                        </div>
                        <div class="dp-stat">
                            <i class="fas fa-rupee-sign"></i>
                            <div><strong>₹<?= number_format((float)($doctor['consultation_fee'] ?? 500), 0) ?></strong><small>Consult Fee</small></div>
                        </div>
                    </div>
                    <div class="dp-hero-actions mt-4">
                        <a href="/public/appointment.php?doctor=<?= $doctor['doctor_id'] ?>" class="btn dp-btn-primary">
                            <i class="fas fa-calendar-check me-2"></i>Book Appointment
                        </a>
                        <?php if (in_array($doctor['consultation_types'] ?? '', ['both', 'online']) || ($doctor['video_consultation'] ?? 0)): ?>
                        <a href="/public/appointment.php?doctor=<?= $doctor['doctor_id'] ?>&type=video" class="btn dp-btn-outline">
                            <i class="fas fa-video me-2"></i>Video Consult
                        </a>
                        <?php endif; ?>
                        <button class="btn dp-btn-share" onclick="shareProfile()" title="Share"><i class="fas fa-share-alt"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php else: /* ===== TEMPLATE 2: Modern Light Hero ===== */ ?>

<section class="t2-hero">
    <div class="container">
        <div class="t2-hero-grid">

            <div class="t2-hero-content">
                <nav aria-label="breadcrumb" class="t2-breadcrumb mb-3">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/">Home</a></li>
                        <li class="breadcrumb-item"><a href="/public/doctors.php">Doctors</a></li>
                        <li class="breadcrumb-item active"><?= e($doctor['name']) ?></li>
                    </ol>
                </nav>

                <div class="t2-rating-row mb-3">
                    <div class="t2-mini-avatars">
                        <div class="t2-ma" style="background:#0D6EFD">J</div>
                        <div class="t2-ma" style="background:#0891b2">M</div>
                        <div class="t2-ma" style="background:#06b6d4">S</div>
                        <div class="t2-ma" style="background:#0284c7">R</div>
                    </div>
                    <div class="t2-rating-info">
                        <div class="t2-stars-row">
                            <?php for ($i = 0; $i < $fullStars; $i++): ?><i class="fas fa-star"></i><?php endfor; ?>
                            <?php if ($halfStar): ?><i class="fas fa-star-half-alt"></i><?php endif; ?>
                            <?php for ($i = 0; $i < $emptyStars; $i++): ?><i class="far fa-star"></i><?php endfor; ?>
                            <strong class="ms-1"><?= number_format($rating, 1) ?></strong>
                        </div>
                        <small>Based on <?= number_format((int)($doctor['reviews_count'] ?? 0)) ?>+ Reviews</small>
                    </div>
                </div>

                <h1 class="t2-headline">
                    Get the Care<br>— You Need &amp;<br><span class="t2-hl-accent">Faster.</span>
                </h1>

                <p class="t2-doctor-name-sub">
                    <span class="t2-name-tag"><?= e($doctor['name']) ?></span>
                    <?php if ($doctor['department_name']): ?>
                    <span class="t2-dept-tag"><i class="fas fa-hospital me-1"></i><?= e($doctor['department_name']) ?></span>
                    <?php endif; ?>
                    <?php if (in_array($doctor['consultation_types'] ?? '', ['both', 'online']) || ($doctor['video_consultation'] ?? 0)): ?>
                    <span class="t2-online-tag"><i class="fas fa-video me-1"></i>Online Available</span>
                    <?php endif; ?>
                </p>

                <p class="t2-bio-text"><?= e(mb_strimwidth($doctor['bio'] ?? 'Our team of experienced medical professionals delivers advanced patient-centered care tailored to your unique health needs — in a safe, trusted environment.', 0, 160, '...')) ?></p>

                <div class="t2-cta-row mb-4">
                    <a href="/public/appointment.php?doctor=<?= $doctor['doctor_id'] ?>" class="btn t2-btn-book">
                        <i class="fas fa-calendar-check me-2"></i>Book Appointment
                    </a>
                    <?php if (in_array($doctor['consultation_types'] ?? '', ['both', 'online']) || ($doctor['video_consultation'] ?? 0)): ?>
                    <a href="/public/appointment.php?doctor=<?= $doctor['doctor_id'] ?>&type=video" class="btn t2-btn-video">
                        <i class="fas fa-video me-2"></i>Video Consult
                    </a>
                    <?php endif; ?>
                    <button class="btn t2-btn-share-sm" onclick="shareProfile()" title="Share"><i class="fas fa-share-alt"></i></button>
                </div>

                <div class="t2-patient-widget">
                    <div class="t2-pw-avatars">
                        <div class="t2-pwa" style="background:#3b82f6">A</div>
                        <div class="t2-pwa" style="background:#0891b2">N</div>
                        <div class="t2-pwa" style="background:#7c3aed">P</div>
                    </div>
                    <div class="t2-pw-text">
                        <strong><?= $doctor['patients_treated'] ? number_format($doctor['patients_treated']) . '+' : '10K+' ?></strong>
                        <small>Current Satisfied Patients<br>Around the State</small>
                    </div>
                    <a href="/public/appointment.php?doctor=<?= $doctor['doctor_id'] ?>" class="t2-pw-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <div class="t2-hero-photo-wrap">
                <div class="t2-spinning-badge">
                    <svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg">
                        <path id="t2-cp" d="M 60,60 m -42,0 a 42,42 0 1,1 84,0 a 42,42 0 1,1 -84,0" fill="none"/>
                        <text font-size="10" fill="#1e293b" font-weight="600" letter-spacing="2">
                            <textPath href="#t2-cp">Best Medical &amp; Health Services • World Wide •</textPath>
                        </text>
                    </svg>
                    <div class="t2-badge-center"><i class="fas fa-briefcase-medical"></i></div>
                </div>

                <?php if ($doctor['photo']): ?>
                <img src="<?= e($doctor['photo']) ?>" alt="<?= e($doctor['name']) ?>" class="t2-doc-photo" loading="lazy">
                <?php else: ?>
                <div class="t2-doc-photo-ph"><i class="fas fa-user-md"></i></div>
                <?php endif; ?>

                <div class="t2-float-card t2-fc-left">
                    <div class="t2-fc-icon t2-fc-blue"><i class="fas fa-briefcase-medical"></i></div>
                    <div>
                        <strong><?= e($doctor['experience'] ?? '15 Yrs') ?></strong>
                        <small>Experience</small>
                    </div>
                </div>

                <div class="t2-float-card t2-fc-right">
                    <div class="t2-fc-icon t2-fc-green"><i class="fas fa-chart-line"></i></div>
                    <div>
                        <strong><?= (int)($doctor['success_rate'] ?? 98) ?>%</strong>
                        <small>Success Rate</small>
                    </div>
                </div>

                <div class="t2-fee-chip">
                    <i class="fas fa-rupee-sign me-1"></i><?= number_format((float)($doctor['consultation_fee'] ?? 500), 0) ?> <small>Consult</small>
                </div>
            </div>

        </div>
    </div>
    <div class="t2-marquee-wrap">
        <div class="t2-marquee-track">
            <span>Passionate &bull; Trusted &bull; Experienced &bull; Caring &bull; Reliable &bull; Skilled &bull; Friendly &bull; Supportive &bull; Professional &bull; Dedicated &bull;&nbsp;</span>
            <span>Passionate &bull; Trusted &bull; Experienced &bull; Caring &bull; Reliable &bull; Skilled &bull; Friendly &bull; Supportive &bull; Professional &bull; Dedicated &bull;&nbsp;</span>
        </div>
    </div>
</section>

<?php endif; /* end template conditional */ ?>

<section class="dp-body py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-8">

                <div class="dp-card mb-4">
                    <div class="dp-card-head"><i class="fas fa-user-md"></i>About the Doctor</div>
                    <div class="dp-card-body">
                        <?php if ($doctor['bio']): ?><p class="mb-0"><?= nl2br(e($doctor['bio'])) ?></p>
                        <?php else: ?><p class="text-muted mb-0">No biography available.</p><?php endif; ?>
                    </div>
                </div>

                <div class="dp-card mb-4">
                    <div class="dp-card-head"><i class="fas fa-graduation-cap"></i>Education & Qualifications</div>
                    <div class="dp-card-body">
                        <div class="dp-qual-list">
                            <?php if ($doctor['qualification']): ?>
                            <div class="dp-qual-item">
                                <div class="dp-qual-icon"><i class="fas fa-certificate"></i></div>
                                <div><strong><?= e($doctor['qualification']) ?></strong><small class="text-muted d-block">Medical Qualification</small></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($doctor['specialization']): ?>
                            <div class="dp-qual-item">
                                <div class="dp-qual-icon"><i class="fas fa-stethoscope"></i></div>
                                <div><strong><?= e($doctor['specialization']) ?></strong><small class="text-muted d-block">Specialization</small></div>
                            </div>
                            <?php endif; ?>
                            <?php foreach ($certifications as $cert): ?>
                            <div class="dp-qual-item">
                                <div class="dp-qual-icon"><i class="fas fa-award"></i></div>
                                <div><strong><?= e($cert) ?></strong><small class="text-muted d-block">Certification</small></div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (!$doctor['qualification'] && !$doctor['specialization'] && empty($certifications)): ?>
                            <p class="text-muted mb-0">No qualification details available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($services)): ?>
                <div class="dp-card mb-4">
                    <div class="dp-card-head"><i class="fas fa-clipboard-list"></i>Services & Treatments</div>
                    <div class="dp-card-body">
                        <div class="dp-services-grid">
                            <?php foreach ($services as $svc): ?>
                            <div class="dp-service-chip"><i class="fas fa-check-circle me-1"></i><?= e($svc) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($doctor['clinic_name'] || $doctor['clinic_address'] || $doctor['available_days']): ?>
                <div class="dp-card mb-4">
                    <div class="dp-card-head"><i class="fas fa-hospital-alt"></i>Clinic & Hospital Information</div>
                    <div class="dp-card-body">
                        <div class="row g-3">
                            <?php if ($doctor['clinic_name']): ?>
                            <div class="col-md-6"><div class="dp-info-row"><i class="fas fa-building text-primary"></i><div><small class="text-muted d-block">Clinic / Hospital</small><strong><?= e($doctor['clinic_name']) ?></strong></div></div></div>
                            <?php endif; ?>
                            <?php if ($doctor['clinic_address']): ?>
                            <div class="col-md-6"><div class="dp-info-row"><i class="fas fa-map-marker-alt text-danger"></i><div><small class="text-muted d-block">Address</small><strong><?= e($doctor['clinic_address']) ?></strong></div></div></div>
                            <?php endif; ?>
                            <?php if ($doctor['available_days']): ?>
                            <div class="col-md-6"><div class="dp-info-row"><i class="fas fa-calendar-alt text-success"></i><div><small class="text-muted d-block">Working Days</small><strong><?= e($doctor['available_days']) ?></strong></div></div></div>
                            <?php endif; ?>
                            <?php if ($doctor['available_time']): ?>
                            <div class="col-md-6"><div class="dp-info-row"><i class="fas fa-clock text-warning"></i><div><small class="text-muted d-block">Working Hours</small><strong><?= e($doctor['available_time']) ?></strong></div></div></div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($doctor['clinic_location'])): ?>
                        <div class="dp-map mt-3">
                            <iframe src="<?= e($doctor['clinic_location']) ?>" width="100%" height="250" style="border:0;border-radius:12px;" allowfullscreen loading="lazy"></iframe>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($schedules)): ?>
                <div class="dp-card mb-4">
                    <div class="dp-card-head"><i class="fas fa-calendar-week"></i>Weekly Schedule</div>
                    <div class="dp-card-body">
                        <div class="dp-schedule-grid">
                            <?php foreach ($schedules as $day => $sessions): ?>
                            <div class="dp-schedule-day <?= !empty($sessions) ? 'active' : '' ?>">
                                <div class="dp-schedule-day-name"><?= $dayNames[(int)$day] ?? 'Day ' . $day ?></div>
                                <?php if (!empty($sessions)): ?>
                                <?php foreach ($sessions as $s): ?>
                                <?php if ($s['is_active']): ?>
                                <div class="dp-schedule-slot">
                                    <small><?= e($s['session_label']) ?></small>
                                    <span><?= date('g:i A', strtotime($s['start_time'])) ?> – <?= date('g:i A', strtotime($s['end_time'])) ?></span>
                                </div>
                                <?php endif; ?>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <div class="dp-schedule-off">Off</div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php elseif ($doctor['available_days']): ?>
                <div class="dp-card mb-4">
                    <div class="dp-card-head"><i class="fas fa-calendar-week"></i>Availability</div>
                    <div class="dp-card-body d-flex flex-wrap gap-2">
                        <div class="dp-avail-chip"><i class="fas fa-calendar-alt me-2"></i><?= e($doctor['available_days']) ?></div>
                        <?php if ($doctor['available_time']): ?>
                        <div class="dp-avail-chip"><i class="fas fa-clock me-2"></i><?= e($doctor['available_time']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($reviews)): ?>
                <div class="dp-card mb-4">
                    <div class="dp-card-head">
                        <i class="fas fa-comments"></i>Patient Reviews
                        <span class="ms-auto dp-review-summary">
                            <span class="dp-review-avg"><?= number_format($rating, 1) ?></span>
                            <span class="dp-stars-sm">
                                <?php for ($i = 0; $i < $fullStars; $i++): ?><i class="fas fa-star"></i><?php endfor; ?>
                                <?php if ($halfStar): ?><i class="fas fa-star-half-alt"></i><?php endif; ?>
                                <?php for ($i = 0; $i < $emptyStars; $i++): ?><i class="far fa-star"></i><?php endfor; ?>
                            </span>
                        </span>
                    </div>
                    <div class="dp-card-body">
                        <div class="dp-reviews-list">
                            <?php foreach ($reviews as $rev): ?>
                            <div class="dp-review-item">
                                <div class="dp-review-avatar"><?= strtoupper(substr($rev['patient_name'], 0, 1)) ?></div>
                                <div class="dp-review-content">
                                    <div class="dp-review-header">
                                        <strong><?= e($rev['patient_name']) ?></strong>
                                        <?php if ($rev['is_verified']): ?><span class="dp-verified"><i class="fas fa-check-circle me-1"></i>Verified</span><?php endif; ?>
                                        <small class="ms-auto text-muted"><?= date('M j, Y', strtotime($rev['created_at'])) ?></small>
                                    </div>
                                    <div class="dp-review-stars"><?php for ($i = 1; $i <= 5; $i++): ?><i class="<?= $i <= $rev['rating'] ? 'fas' : 'far' ?> fa-star"></i><?php endfor; ?></div>
                                    <p class="dp-review-text"><?= e($rev['comment']) ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <div class="col-lg-4">
                <div class="dp-sidebar">

                    <div class="dp-sidebar-card dp-appt-card">
                        <h6><i class="fas fa-calendar-check me-2"></i>Book Appointment</h6>
                        <p style="color:rgba(255,255,255,0.8);font-size:0.85rem;margin-bottom:0.75rem;">Schedule your visit today</p>
                        <div class="dp-fee-badge mb-3">
                            Consultation Fee: <strong>₹<?= number_format((float)($doctor['consultation_fee'] ?? 500), 0) ?></strong>
                        </div>
                        <a href="/public/appointment.php?doctor=<?= $doctor['doctor_id'] ?>" class="btn dp-btn-primary w-100 mb-2">
                            <i class="fas fa-calendar-check me-2"></i>Book Clinic Visit
                        </a>
                        <?php if (in_array($doctor['consultation_types'] ?? '', ['both', 'online']) || ($doctor['video_consultation'] ?? 0)): ?>
                        <a href="/public/appointment.php?doctor=<?= $doctor['doctor_id'] ?>&type=video" class="btn dp-btn-outline w-100">
                            <i class="fas fa-video me-2"></i>Video Consultation
                        </a>
                        <?php endif; ?>
                    </div>

                    <div class="dp-sidebar-card">
                        <h6><i class="fas fa-chart-bar me-2"></i>Doctor Statistics</h6>
                        <div class="dp-stats-grid">
                            <div class="dp-stats-item">
                                <span class="dp-stats-num"><?= $doctor['patients_treated'] ? number_format($doctor['patients_treated']) . '+' : '—' ?></span>
                                <span class="dp-stats-label">Patients</span>
                            </div>
                            <div class="dp-stats-item">
                                <span class="dp-stats-num"><?= e($doctor['experience'] ?? '—') ?></span>
                                <span class="dp-stats-label">Experience</span>
                            </div>
                            <div class="dp-stats-item">
                                <span class="dp-stats-num"><?= (int)($doctor['success_rate'] ?? 98) ?>%</span>
                                <span class="dp-stats-label">Success Rate</span>
                            </div>
                            <div class="dp-stats-item">
                                <span class="dp-stats-num"><?= number_format($rating, 1) ?>★</span>
                                <span class="dp-stats-label">Rating</span>
                            </div>
                        </div>
                    </div>

                    <div class="dp-sidebar-card">
                        <h6><i class="fas fa-info-circle me-2"></i>Contact & Info</h6>
                        <ul class="dp-info-list">
                            <?php if ($doctor['email']): ?><li><i class="fas fa-envelope"></i><span><?= e($doctor['email']) ?></span></li><?php endif; ?>
                            <?php if ($doctor['phone']): ?><li><i class="fas fa-phone"></i><a href="tel:<?= e($doctor['phone']) ?>"><?= e($doctor['phone']) ?></a></li><?php endif; ?>
                            <?php if (!empty($languages)): ?><li><i class="fas fa-language"></i><span><?= e(implode(', ', $languages)) ?></span></li><?php endif; ?>
                            <?php if ($doctor['consultation_types']): ?>
                            <li><i class="fas fa-clinic-medical"></i><span>
                                <?php if ($doctor['consultation_types'] === 'both'): ?>Online & Clinic
                                <?php elseif ($doctor['consultation_types'] === 'online'): ?>Online Only
                                <?php else: ?>Clinic Only<?php endif; ?></span>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="dp-sidebar-card">
                        <h6><i class="fas fa-share-alt me-2"></i>Share Profile</h6>
                        <div class="dp-share-btns">
                            <a href="https://wa.me/?text=<?= urlencode($doctor['name'] . ' – ' . ($doctor['specialization'] ?? '') . ' | JMedi') ?>" target="_blank" class="dp-share-btn dp-share-wa"><i class="fab fa-whatsapp"></i></a>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/doctor/' . ($doctor['slug'] ?? '')) ?>" target="_blank" class="dp-share-btn dp-share-fb"><i class="fab fa-facebook-f"></i></a>
                            <a href="https://twitter.com/intent/tweet?text=<?= urlencode('Book an appointment with ' . $doctor['name'] . ' at JMedi') ?>&url=<?= urlencode((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/doctor/' . ($doctor['slug'] ?? '')) ?>" target="_blank" class="dp-share-btn dp-share-tw"><i class="fab fa-twitter"></i></a>
                            <button onclick="copyLink()" class="dp-share-btn dp-share-copy"><i class="fas fa-link"></i></button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($relatedDoctors)): ?>
<section class="dp-related py-5">
    <div class="container">
        <div class="section-header text-center mb-4">
            <h2 class="section-title">Related Doctors</h2>
            <p class="section-subtitle">Other specialists in <?= e($doctor['department_name'] ?? 'this department') ?></p>
        </div>
        <div class="row g-4">
            <?php foreach ($relatedDoctors as $rd): ?>
            <div class="col-lg-3 col-md-6">
                <div class="card doctor-card">
                    <div class="doctor-img">
                        <?php if ($rd['photo']): ?><img src="<?= e($rd['photo']) ?>" alt="<?= e($rd['name']) ?>" loading="lazy">
                        <?php else: ?><i class="fas fa-user-md placeholder-icon"></i><?php endif; ?>
                    </div>
                    <div class="card-body">
                        <h5><?= e($rd['name']) ?></h5>
                        <span class="dept-badge"><?= e($rd['department_name'] ?? '') ?></span>
                        <p class="text-muted small mt-2 mb-2"><?= e($rd['specialization'] ?? '') ?></p>
                        <a href="<?= $rd['slug'] ? '/doctor/' . e($rd['slug']) : '/public/doctor-profile.php?id=' . $rd['doctor_id'] ?>" class="btn btn-sm btn-outline-primary">View Profile</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<style>
.dp-hero { background: linear-gradient(135deg, #0D6EFD 0%, #0891b2 60%, #06b6d4 100%); padding: 2.5rem 0 3rem; }
.dp-hero-card { background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 24px 64px rgba(0,0,0,0.14); }
.dp-hero-img-col { position: relative; min-height: 360px; background: linear-gradient(145deg, #dbeafe, #e0f2fe); }
.dp-hero-img { width: 100%; height: 100%; min-height: 360px; max-height: 440px; object-fit: cover; object-position: top center; display: block; }
.dp-hero-img-placeholder { width: 100%; min-height: 360px; display: flex; align-items: center; justify-content: center; font-size: 6rem; color: #0D6EFD; opacity: 0.2; }
.dp-hero-info { padding: 2.5rem; }
.dp-badges { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.dp-badge-dept { background: #dbeafe; color: #1d4ed8; padding: 0.3rem 0.85rem; border-radius: 50px; font-size: 0.76rem; font-weight: 700; }
.dp-badge-online { background: #d1fae5; color: #065f46; padding: 0.3rem 0.85rem; border-radius: 50px; font-size: 0.76rem; font-weight: 700; }
.dp-hero-name { font-size: 1.9rem; font-weight: 800; color: #0f172a; margin: 0.5rem 0 0.25rem; line-height: 1.2; }
.dp-hero-spec { font-size: 0.98rem; color: #0D6EFD; font-weight: 700; margin-bottom: 0.2rem; }
.dp-hero-qual { font-size: 0.85rem; color: #64748b; margin-bottom: 0.75rem; }
.dp-rating { display: flex; align-items: center; gap: 0.5rem; }
.dp-stars { color: #f59e0b; font-size: 0.9rem; letter-spacing: 0.02em; }
.dp-rating-num { font-size: 1rem; font-weight: 800; color: #f59e0b; }
.dp-rating-count { font-size: 0.78rem; color: #94a3b8; }
.dp-quick-stats { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.75rem; }
.dp-stat { display: flex; align-items: center; gap: 0.55rem; background: #f8fafc; border-radius: 12px; padding: 0.55rem 0.85rem; border: 1px solid #f1f5f9; }
.dp-stat i { font-size: 1.15rem; color: #0D6EFD; width: 22px; text-align: center; }
.dp-stat strong { display: block; font-size: 0.88rem; font-weight: 700; color: #0f172a; line-height: 1.1; }
.dp-stat small { font-size: 0.68rem; color: #94a3b8; }
.dp-hero-actions { display: flex; flex-wrap: wrap; gap: 0.65rem; }
.dp-btn-primary { background: linear-gradient(135deg, #0D6EFD, #0891b2); color: #fff !important; border: none; border-radius: 50px; padding: 0.6rem 1.4rem; font-weight: 700; font-size: 0.87rem; transition: all 0.3s; box-shadow: 0 4px 14px rgba(13,110,253,0.3); }
.dp-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(13,110,253,0.45); }
.dp-btn-outline { background: transparent; color: #0D6EFD !important; border: 2px solid #0D6EFD; border-radius: 50px; padding: 0.55rem 1.4rem; font-weight: 700; font-size: 0.87rem; transition: all 0.3s; }
.dp-btn-outline:hover { background: #0D6EFD; color: #fff !important; }
.dp-btn-share { background: #f1f5f9; color: #64748b; border: none; border-radius: 50%; width: 42px; height: 42px; padding: 0; display: flex; align-items: center; justify-content: center; }
.dp-btn-share:hover { background: #e2e8f0; }
.dp-card { background: #fff; border-radius: 16px; box-shadow: 0 2px 16px rgba(0,0,0,0.06); border: 1px solid #f1f5f9; overflow: hidden; }
.dp-card-head { background: #f8fafc; padding: 0.9rem 1.5rem; font-weight: 700; color: #0f172a; font-size: 0.92rem; display: flex; align-items: center; gap: 0.6rem; border-bottom: 1px solid #f1f5f9; }
.dp-card-head i { color: #0D6EFD; width: 18px; text-align: center; }
.dp-card-body { padding: 1.5rem; }
.dp-qual-list { display: flex; flex-direction: column; gap: 1rem; }
.dp-qual-item { display: flex; align-items: flex-start; gap: 1rem; }
.dp-qual-icon { width: 38px; height: 38px; border-radius: 10px; background: #dbeafe; display: flex; align-items: center; justify-content: center; color: #0D6EFD; flex-shrink: 0; font-size: 0.9rem; }
.dp-services-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.dp-service-chip { background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; border-radius: 50px; padding: 0.3rem 0.8rem; font-size: 0.8rem; font-weight: 500; }
.dp-service-chip i { color: #0D6EFD; font-size: 0.72rem; }
.dp-info-row { display: flex; align-items: flex-start; gap: 0.75rem; }
.dp-info-row i { width: 20px; text-align: center; margin-top: 2px; flex-shrink: 0; font-size: 0.9rem; }
.dp-map { border-radius: 12px; overflow: hidden; }
.dp-schedule-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.6rem; }
.dp-schedule-day { border-radius: 10px; padding: 0.7rem 0.6rem; text-align: center; border: 1px solid #e2e8f0; background: #fafafa; }
.dp-schedule-day.active { border-color: #bfdbfe; background: #eff6ff; }
.dp-schedule-day-name { font-size: 0.72rem; font-weight: 800; color: #374151; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.04em; }
.dp-schedule-slot { font-size: 0.68rem; color: #0D6EFD; margin-top: 0.3rem; line-height: 1.3; }
.dp-schedule-slot small { display: block; color: #94a3b8; font-size: 0.65rem; }
.dp-schedule-off { font-size: 0.72rem; color: #cbd5e1; font-style: italic; }
.dp-avail-chip { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; border-radius: 50px; padding: 0.45rem 1rem; font-size: 0.82rem; font-weight: 500; display: inline-flex; align-items: center; }
.dp-review-summary { display: flex; align-items: center; gap: 0.4rem; }
.dp-review-avg { font-size: 1.1rem; font-weight: 800; color: #f59e0b; }
.dp-stars-sm { color: #f59e0b; font-size: 0.75rem; }
.dp-reviews-list { display: flex; flex-direction: column; gap: 1.25rem; }
.dp-review-item { display: flex; gap: 0.85rem; }
.dp-review-avatar { width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, #0D6EFD, #06b6d4); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem; flex-shrink: 0; }
.dp-review-content { flex: 1; }
.dp-review-header { display: flex; align-items: center; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.2rem; font-size: 0.88rem; }
.dp-verified { font-size: 0.7rem; color: #059669; font-weight: 700; background: #d1fae5; padding: 0.1rem 0.5rem; border-radius: 50px; }
.dp-review-stars { color: #f59e0b; font-size: 0.75rem; margin-bottom: 0.25rem; }
.dp-review-text { font-size: 0.85rem; color: #475569; margin: 0; line-height: 1.6; }
.dp-sidebar { position: sticky; top: 90px; }
.dp-sidebar-card { background: #fff; border-radius: 16px; box-shadow: 0 2px 16px rgba(0,0,0,0.06); border: 1px solid #f1f5f9; padding: 1.25rem; margin-bottom: 1rem; }
.dp-sidebar-card h6 { font-weight: 700; color: #0f172a; margin-bottom: 0.85rem; font-size: 0.88rem; display: flex; align-items: center; gap: 0.4rem; }
.dp-sidebar-card h6 i { color: #0D6EFD; }
.dp-appt-card { background: linear-gradient(135deg, #0D6EFD 0%, #0891b2 100%); }
.dp-appt-card h6 { color: #fff !important; }
.dp-appt-card h6 i { color: rgba(255,255,255,0.8) !important; }
.dp-fee-badge { background: rgba(255,255,255,0.18); border-radius: 8px; padding: 0.45rem 0.75rem; font-size: 0.82rem; color: #fff; }
.dp-appt-card .dp-btn-primary { background: #fff; color: #0D6EFD !important; box-shadow: 0 4px 14px rgba(0,0,0,0.15); }
.dp-appt-card .dp-btn-primary:hover { background: #f0f9ff; }
.dp-appt-card .dp-btn-outline { border-color: rgba(255,255,255,0.5); color: #fff !important; }
.dp-appt-card .dp-btn-outline:hover { background: rgba(255,255,255,0.15); border-color: #fff; }
.dp-stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; }
.dp-stats-item { background: #f8fafc; border-radius: 10px; padding: 0.75rem 0.6rem; text-align: center; border: 1px solid #f1f5f9; }
.dp-stats-num { display: block; font-size: 1rem; font-weight: 800; color: #0D6EFD; }
.dp-stats-label { font-size: 0.65rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.04em; }
.dp-info-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.55rem; }
.dp-info-list li { display: flex; align-items: flex-start; gap: 0.55rem; font-size: 0.83rem; color: #475569; word-break: break-word; }
.dp-info-list i { color: #0D6EFD; width: 15px; text-align: center; margin-top: 2px; flex-shrink: 0; font-size: 0.8rem; }
.dp-info-list a { color: #0D6EFD; text-decoration: none; }
.dp-share-btns { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.dp-share-btn { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 0.95rem; border: none; cursor: pointer; transition: all 0.2s; color: #fff; }
.dp-share-wa { background: #25d366; } .dp-share-fb { background: #1877f2; } .dp-share-tw { background: #1da1f2; }
.dp-share-copy { background: #f1f5f9; color: #64748b; }
.dp-share-btn:hover { transform: translateY(-2px); filter: brightness(1.1); }
.dp-related { background: #f8fafc; }
@media (max-width: 991px) { .dp-sidebar { position: static; } }
@media (max-width: 767px) {
    .dp-hero { padding: 1.5rem 0 2rem; }
    .dp-hero-img-col { min-height: 220px; }
    .dp-hero-img { min-height: 220px; max-height: 260px; }
    .dp-hero-img-placeholder { min-height: 220px; }
    .dp-hero-name { font-size: 1.35rem; }
    .dp-hero-info { padding: 1.25rem; }
    .dp-quick-stats { gap: 0.4rem; }
    .dp-stat { padding: 0.4rem 0.65rem; }
    .dp-schedule-grid { grid-template-columns: repeat(3, 1fr); }
}

/* ============================================================
   TEMPLATE 2 — Modern Light Hero
   ============================================================ */
.t2-hero { background: linear-gradient(140deg, #e8f4ff 0%, #f0faff 45%, #eaf0ff 100%); position: relative; overflow: clip; padding: 0; }
.t2-hero-grid { display: grid; grid-template-columns: 1fr 430px; align-items: center; min-height: 530px; gap: 1rem; }
.t2-hero-content { padding: 2.5rem 0 2rem; }
.t2-breadcrumb .breadcrumb-item a { color: #64748b; font-size: 0.8rem; text-decoration: none; }
.t2-breadcrumb .breadcrumb-item a:hover { color: #0D6EFD; }
.t2-breadcrumb .breadcrumb-item.active { color: #0D6EFD; font-size: 0.8rem; }
.t2-breadcrumb .breadcrumb-item + .breadcrumb-item::before { color: #94a3b8; }
.t2-rating-row { display: flex; align-items: center; gap: 0.75rem; }
.t2-mini-avatars { display: flex; }
.t2-ma { width: 30px; height: 30px; border-radius: 50%; color: #fff; font-size: 0.68rem; font-weight: 700; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; margin-left: -8px; flex-shrink: 0; }
.t2-ma:first-child { margin-left: 0; }
.t2-rating-info .t2-stars-row { color: #f59e0b; font-size: 0.82rem; display: flex; align-items: center; }
.t2-rating-info small { color: #94a3b8; font-size: 0.72rem; display: block; margin-top: 0.1rem; }
.t2-headline { font-size: clamp(2rem, 3.2vw, 3rem); font-weight: 900; color: #0f172a; line-height: 1.15; margin: 0.5rem 0 0.4rem; letter-spacing: -0.02em; }
.t2-hl-accent { color: #0D6EFD; }
.t2-doctor-name-sub { display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem; }
.t2-name-tag { font-weight: 700; color: #0D6EFD; font-size: 0.95rem; }
.t2-dept-tag { background: #dbeafe; color: #1d4ed8; padding: 0.2rem 0.7rem; border-radius: 50px; font-size: 0.72rem; font-weight: 700; }
.t2-online-tag { background: #d1fae5; color: #065f46; padding: 0.2rem 0.7rem; border-radius: 50px; font-size: 0.72rem; font-weight: 700; }
.t2-bio-text { color: #475569; font-size: 0.92rem; line-height: 1.75; max-width: 480px; margin-bottom: 1.4rem; }
.t2-cta-row { display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: center; }
.t2-btn-book { background: linear-gradient(135deg, #0D6EFD, #0891b2); color: #fff !important; border: none; border-radius: 50px; padding: 0.7rem 1.6rem; font-weight: 700; font-size: 0.88rem; box-shadow: 0 6px 20px rgba(13,110,253,0.35); transition: all 0.3s; }
.t2-btn-book:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(13,110,253,0.45); }
.t2-btn-video { background: #fff; color: #0D6EFD !important; border: 2px solid #0D6EFD; border-radius: 50px; padding: 0.65rem 1.5rem; font-weight: 700; font-size: 0.88rem; transition: all 0.3s; }
.t2-btn-video:hover { background: #0D6EFD; color: #fff !important; }
.t2-btn-share-sm { background: #f1f5f9; color: #64748b; border: none; border-radius: 50%; width: 44px; height: 44px; padding: 0; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
.t2-btn-share-sm:hover { background: #e2e8f0; }
.t2-patient-widget { display: inline-flex; align-items: center; gap: 0.7rem; background: #fff; border-radius: 14px; padding: 0.65rem 1rem; box-shadow: 0 4px 18px rgba(0,0,0,0.09); border: 1px solid #f1f5f9; max-width: 100%; }
.t2-pw-avatars { display: flex; flex-shrink: 0; }
.t2-pwa { width: 27px; height: 27px; border-radius: 50%; color: #fff; font-size: 0.62rem; font-weight: 700; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; margin-left: -7px; }
.t2-pwa:first-child { margin-left: 0; }
.t2-pw-text strong { display: block; font-size: 0.88rem; color: #0f172a; font-weight: 800; line-height: 1.1; }
.t2-pw-text small { font-size: 0.67rem; color: #64748b; line-height: 1.3; }
.t2-pw-arrow { width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg, #0D6EFD, #06b6d4); color: #fff; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 0.78rem; flex-shrink: 0; transition: transform 0.2s; }
.t2-pw-arrow:hover { transform: scale(1.12); }
.t2-hero-photo-wrap { position: relative; align-self: flex-end; height: 530px; }
.t2-doc-photo { width: 100%; height: 100%; object-fit: cover; object-position: top center; border-radius: 20px 20px 0 0; display: block; }
.t2-doc-photo-ph { width: 100%; height: 100%; background: linear-gradient(145deg, #dbeafe, #e0f2fe); border-radius: 20px 20px 0 0; display: flex; align-items: center; justify-content: center; font-size: 7rem; color: #0D6EFD; opacity: 0.3; }
.t2-spinning-badge { position: absolute; top: 22px; right: -22px; width: 104px; height: 104px; z-index: 10; }
.t2-spinning-badge svg { width: 100%; height: 100%; animation: t2-spin 16s linear infinite; display: block; }
.t2-badge-center { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 38px; height: 38px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #0D6EFD; font-size: 0.95rem; box-shadow: 0 4px 14px rgba(0,0,0,0.14); }
@keyframes t2-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.t2-float-card { position: absolute; background: #fff; border-radius: 14px; padding: 0.55rem 0.9rem; box-shadow: 0 8px 26px rgba(0,0,0,0.12); display: flex; align-items: center; gap: 0.6rem; z-index: 10; border: 1px solid #f1f5f9; }
.t2-float-card strong { display: block; font-size: 0.87rem; font-weight: 800; color: #0f172a; line-height: 1.1; }
.t2-float-card small { font-size: 0.65rem; color: #94a3b8; }
.t2-fc-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.88rem; flex-shrink: 0; }
.t2-fc-blue { background: rgba(13,110,253,0.12); color: #0D6EFD; }
.t2-fc-green { background: rgba(5,150,105,0.12); color: #059669; }
.t2-fc-left { bottom: 55px; left: -28px; }
.t2-fc-right { top: 80px; right: -22px; }
.t2-fee-chip { position: absolute; bottom: 22px; right: 12px; background: linear-gradient(135deg, #0D6EFD, #0891b2); color: #fff; border-radius: 50px; padding: 0.35rem 0.85rem; font-size: 0.8rem; font-weight: 700; z-index: 10; box-shadow: 0 4px 14px rgba(13,110,253,0.35); }
.t2-fee-chip small { font-size: 0.68rem; opacity: 0.85; }
.t2-marquee-wrap { background: linear-gradient(135deg, #0891b2, #06b6d4); overflow: hidden; padding: 0.65rem 0; }
.t2-marquee-track { display: inline-flex; white-space: nowrap; animation: t2-marquee 24s linear infinite; }
.t2-marquee-track span { color: #fff; font-weight: 600; font-size: 0.875rem; letter-spacing: 0.05em; white-space: nowrap; }
@keyframes t2-marquee { from { transform: translateX(0); } to { transform: translateX(-50%); } }
@media (max-width: 991px) {
    .t2-hero-grid { grid-template-columns: 1fr; min-height: auto; }
    .t2-hero-content { padding: 2rem 0 1.5rem; }
    .t2-hero-photo-wrap { height: 360px; border-radius: 16px 16px 0 0; overflow: hidden; margin: 0 -12px; }
    .t2-spinning-badge { top: 12px; right: 10px; width: 84px; height: 84px; }
    .t2-fc-left { left: 10px; bottom: 12px; }
    .t2-fc-right { right: 10px; top: 12px; }
    .t2-fee-chip { display: none; }
}
@media (max-width: 575px) {
    .t2-headline { font-size: 1.85rem; }
    .t2-hero-photo-wrap { height: 280px; }
    .t2-bio-text { font-size: 0.87rem; }
    .t2-patient-widget { font-size: 0.82rem; }
}
/* ============================================================
   END TEMPLATE 2
   ============================================================ */
</style>

<script>
function shareProfile() {
    if (navigator.share) {
        navigator.share({ title: '<?= addslashes(e($doctor['name'])) ?>', text: 'Book an appointment with <?= addslashes(e($doctor['name'])) ?> at JMedi', url: window.location.href });
    } else { copyLink(); }
}
function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        document.querySelectorAll('.dp-share-copy, .dp-btn-share').forEach(b => {
            const orig = b.innerHTML;
            b.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(() => b.innerHTML = orig, 2000);
        });
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
