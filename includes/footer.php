<footer class="footer text-light">
    <div class="container">
        <div class="row g-4 pb-4">
            <div class="col-12 col-lg-4">
                <?php $footerLogo = $settings['footer_logo'] ?? ($settings['frontend_logo'] ?? ''); ?>
                <?php if ($footerLogo): ?>
                <a href="/" class="d-inline-block mb-3"><img src="<?= e($footerLogo) ?>" alt="<?= e($settings['site_name'] ?? 'JMedi') ?>" style="height:50px;width:auto;object-fit:contain;filter:brightness(0) invert(1);"></a>
                <?php else: ?>
                <h5 class="mb-3" style="font-size:1.8rem;"><a href="/" class="text-decoration-none"><span style="color:var(--primary);">J</span><span style="color:var(--secondary);">Medi</span></a></h5>
                <?php endif; ?>
                <p style="color:#8899aa;line-height:1.8;"><?= e($settings['tagline'] ?? 'Smart Medical Platform') ?>. Providing world-class healthcare services with compassion and excellence.</p>
                <ul class="list-unstyled" style="color:#8899aa;">
                    <li class="mb-2"><i class="fas fa-map-marker-alt me-2" style="color:var(--primary);width:18px;"></i><?= e($settings['address'] ?? '') ?></li>
                    <li class="mb-2"><i class="fas fa-phone me-2" style="color:var(--primary);width:18px;"></i><?= e($settings['phone'] ?? '') ?></li>
                    <li class="mb-2"><i class="fas fa-envelope me-2" style="color:var(--primary);width:18px;"></i><?= e($settings['email'] ?? '') ?></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h6 class="mb-3 pb-2" style="border-bottom:2px solid var(--primary);display:inline-block;">Quick Links</h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="/">Home</a></li>
                    <li><a href="/public/departments.php">Departments</a></li>
                    <li><a href="/public/doctors.php">Our Doctors</a></li>
                    <li><a href="/public/appointment.php">Appointment</a></li>
                    <li><a href="/public/blog.php">Blog</a></li>
                    <li><a href="/public/contact.php">Contact Us</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h6 class="mb-3 pb-2" style="border-bottom:2px solid var(--primary);display:inline-block;">Departments</h6>
                <ul class="list-unstyled footer-links">
                    <?php
                    $footerDepts = getDepartments($pdo);
                    foreach (array_slice($footerDepts, 0, 6) as $dept): ?>
                    <li><a href="/public/departments.php?slug=<?= e($dept['slug']) ?>"><?= e($dept['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="col-12 col-lg-3">
                <h6 class="mb-3 pb-2" style="border-bottom:2px solid var(--primary);display:inline-block;">Emergency</h6>
                <div class="p-3 rounded-3 mb-3" style="background:rgba(13,110,253,0.12);border-left:4px solid var(--primary);">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-phone-alt" style="color:var(--primary);"></i>
                        <span style="color:#ccc;font-size:0.9rem;">Emergency Line</span>
                    </div>
                    <p class="h5 mb-1" style="color:var(--primary);font-weight:700;"><?= e($settings['emergency_phone'] ?? '') ?></p>
                    <small style="color:#8899aa;">Available 24/7 for emergencies</small>
                </div>
                <div class="social-icons mt-3">
                    <?php if (!empty($settings['facebook'])): ?><a href="<?= e($settings['facebook']) ?>"><i class="fab fa-facebook-f"></i></a><?php endif; ?>
                    <?php if (!empty($settings['twitter'])): ?><a href="<?= e($settings['twitter']) ?>"><i class="fab fa-twitter"></i></a><?php endif; ?>
                    <?php if (!empty($settings['instagram'])): ?><a href="<?= e($settings['instagram']) ?>"><i class="fab fa-instagram"></i></a><?php endif; ?>
                    <?php if (!empty($settings['linkedin'])): ?><a href="<?= e($settings['linkedin']) ?>"><i class="fab fa-linkedin-in"></i></a><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="footer-bottom text-center">
            <p class="mb-0" style="color:#6c7a8a;font-size:0.9rem;"><?= e($settings['footer_text'] ?? '© 2026 JMedi. All Rights Reserved.') ?></p>
        </div>
    </div>
</footer>

<?php
$whatsappNum = $settings['whatsapp_number'] ?? '';
$floatDepartments = getDepartments($pdo);
$floatDoctors = getDoctors($pdo);
?>

<?php
$waMsgTemplate = $settings['whatsapp_message'] ?? '';
if (empty($waMsgTemplate)) {
    $waMsgTemplate = "Hi {site_name}! 👋\n\nI visited your website and I am interested in your healthcare services. Could you please provide me with more information?\n\nThank you!";
}
$waMsg  = str_replace('{site_name}', ($settings['site_name'] ?? 'JMedi'), $waMsgTemplate);
$waText = urlencode($waMsg);
$waBase = $whatsappNum ? 'https://wa.me/' . rawurlencode($whatsappNum) . '?text=' . $waText : '/public/contact.php';
$waTarget = $whatsappNum ? 'target="_blank"' : '';
?>
<a href="<?= $waBase ?>" <?= $waTarget ?> class="whatsapp-float" title="<?= $whatsappNum ? 'Chat on WhatsApp' : 'Contact Us' ?>">
    <i class="fab fa-whatsapp"></i>
</a>

<button type="button" class="appointment-float" data-bs-toggle="modal" data-bs-target="#appointmentModal" title="Book Appointment">
    <i class="fas fa-calendar-check"></i>
    <span>Book Now</span>
</button>

<!-- Mobile Fixed Footer CTA Bar -->
<div class="mobile-cta-bar d-lg-none">
    <button type="button" class="mob-cta-appt" data-bs-toggle="modal" data-bs-target="#appointmentModal">
        <i class="fas fa-calendar-check"></i>
        <span>Book Appointment</span>
    </button>
    <?php if ($whatsappNum): ?>
    <a href="<?= $waBase ?>" target="_blank" class="mob-cta-wa">
        <i class="fab fa-whatsapp"></i>
        <span>WhatsApp Us</span>
    </a>
    <?php else: ?>
    <a href="/public/contact.php" class="mob-cta-wa">
        <i class="fas fa-envelope"></i>
        <span>Contact Us</span>
    </a>
    <?php endif; ?>
</div>

<div class="modal fade" id="appointmentModal" tabindex="-1" aria-labelledby="appointmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <div class="modal-header border-0 text-white px-4 py-3" style="background:linear-gradient(135deg,#0D6EFD 0%,#0b5ed7 100%);">
                <div>
                    <h5 class="modal-title mb-0" id="appointmentModalLabel"><i class="fas fa-calendar-check me-2"></i>Book an Appointment</h5>
                    <small class="opacity-75">Fill the form below and we'll get back within 24 hours</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="apptAlert" class="alert d-none mb-3"></div>
                <form id="appointmentForm">
                    <?= csrfField() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><i class="fas fa-user me-1 text-primary"></i> Full Name <span class="text-danger">*</span></label>
                            <input type="text" id="apptName" class="form-control" placeholder="John Doe" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><i class="fas fa-envelope me-1 text-primary"></i> Email <span class="text-danger">*</span></label>
                            <input type="email" id="apptEmail" class="form-control" placeholder="john@example.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><i class="fas fa-phone me-1 text-primary"></i> Phone <span class="text-danger">*</span></label>
                            <input type="tel" id="apptPhone" class="form-control" placeholder="+1 (555) 123-4567" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><i class="fas fa-hospital me-1 text-primary"></i> Department</label>
                            <select id="apptDepartment" class="form-select">
                                <option value="">Select Department</option>
                                <?php foreach ($floatDepartments as $dept): ?>
                                <option value="<?= $dept['department_id'] ?>"><?= e($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><i class="fas fa-user-md me-1 text-primary"></i> Doctor</label>
                            <select id="apptDoctor" class="form-select">
                                <option value="">Select Doctor</option>
                                <?php foreach ($floatDoctors as $doc): ?>
                                <option value="<?= $doc['doctor_id'] ?>"><?= e($doc['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold"><i class="fas fa-calendar me-1 text-primary"></i> Date <span class="text-danger">*</span></label>
                            <input type="date" id="apptDate" class="form-control" required min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold"><i class="fas fa-clock me-1 text-primary"></i> Time <span class="text-danger">*</span></label>
                            <input type="time" id="apptTime" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold"><i class="fas fa-comment-medical me-1 text-primary"></i> Message</label>
                            <textarea id="apptMessage" class="form-control" rows="3" placeholder="Describe your symptoms or reason for visit..."></textarea>
                        </div>
                    </div>
                </form>
                <div id="apptSuccess" class="text-center d-none py-4">
                    <div class="mb-3">
                        <div style="width:80px;height:80px;border-radius:50%;background:#d1fae5;display:inline-flex;align-items:center;justify-content:center;">
                            <i class="fas fa-check-circle text-success" style="font-size:2.5rem;"></i>
                        </div>
                    </div>
                    <h5 class="text-success mb-2">Appointment Booked!</h5>
                    <p class="text-muted mb-3">We'll confirm your appointment within 24 hours via email.</p>
                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0" id="apptFooter">
                <a href="/public/appointment.php" class="btn btn-link text-muted me-auto px-0"><i class="fas fa-external-link-alt me-1"></i>Full page form</a>
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4" id="apptSubmitBtn">
                    <span id="apptBtnText"><i class="fas fa-calendar-check me-1"></i> Book Appointment</span>
                    <span id="apptSpinner" class="d-none"><span class="spinner-border spinner-border-sm me-1"></span> Booking...</span>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.whatsapp-float {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 56px;
    height: 56px;
    background: #25D366;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    box-shadow: 0 4px 15px rgba(37,211,102,0.4);
    z-index: 999;
    text-decoration: none;
    transition: all 0.3s;
}
.whatsapp-float:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(37,211,102,0.5);
    color: #fff;
}
.appointment-float {
    position: fixed;
    right: -2px;
    top: 50%;
    transform: translateY(-50%);
    background: linear-gradient(135deg, #0D6EFD 0%, #0b5ed7 100%);
    color: #fff;
    border: none;
    padding: 14px 16px;
    border-radius: 14px 0 0 14px;
    writing-mode: vertical-lr;
    text-orientation: mixed;
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    z-index: 1050;
    box-shadow: -3px 0 20px rgba(13,110,253,0.3);
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}
.appointment-float i {
    font-size: 1.15rem;
    writing-mode: horizontal-tb;
}
.appointment-float:hover {
    right: 0;
    padding-right: 20px;
    box-shadow: -5px 0 25px rgba(13,110,253,0.45);
}
.appointment-float:active {
    transform: translateY(-50%) scale(0.97);
}
@media (max-width: 991.98px) {
    .appointment-float { display: none !important; }
    .whatsapp-float    { display: none !important; }
    .footer { padding-bottom: 80px; }
}

/* Mobile fixed footer CTA bar */
.mobile-cta-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    z-index: 1055;
    box-shadow: 0 -4px 20px rgba(0,0,0,0.12);
    padding-bottom: env(safe-area-inset-bottom);
}
.mob-cta-appt,
.mob-cta-wa {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    font-weight: 700;
    font-size: 0.9rem;
    padding: 14px 10px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: filter 0.2s;
    letter-spacing: 0.2px;
}
.mob-cta-appt {
    background: var(--primary);
    color: #fff;
    border-right: 1px solid rgba(255,255,255,0.2);
}
.mob-cta-wa {
    background: #25D366;
    color: #fff;
}
.mob-cta-appt:hover { filter: brightness(1.1); color: #fff; }
.mob-cta-wa:hover   { filter: brightness(1.08); color: #fff; }
.mob-cta-appt i,
.mob-cta-wa i { font-size: 1.15rem; }
#appointmentModal .form-control,
#appointmentModal .form-select {
    border-radius: 8px;
    padding: 10px 14px;
    border-color: #dee2e6;
    transition: border-color 0.2s, box-shadow 0.2s;
}
#appointmentModal .form-control:focus,
#appointmentModal .form-select:focus {
    border-color: #0D6EFD;
    box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
}
#appointmentModal .modal-content {
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script src="/assets/js/main.js"></script>
<script src="/assets/js/hero-slider.js"></script>
<script>
(function() {
    var modal = document.getElementById('loginModal');
    if (!modal) return;

    function showAlert(el, message, type) {
        el.className = 'alert alert-' + type + ' mb-3';
        el.textContent = message;
        el.classList.remove('d-none');
    }

    function setLoading(btn, loading) {
        btn.disabled = loading;
        btn.querySelector('.btn-label').classList.toggle('d-none', loading);
        btn.querySelector('.btn-spinner').classList.toggle('d-none', !loading);
    }

    var staffForm = document.getElementById('loginForm');
    var staffAlert = document.getElementById('loginAlert');
    var staffBtn = document.getElementById('loginSubmitBtn');
    var staffBtnText = document.getElementById('loginBtnText');
    var staffSpinner = document.getElementById('loginSpinner');
    var toggleBtn = document.getElementById('togglePassword');
    var passInput = document.getElementById('loginPassword');

    if (toggleBtn && passInput) {
        toggleBtn.addEventListener('click', function() {
            passInput.type = passInput.type === 'password' ? 'text' : 'password';
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    }

    if (staffForm) {
        staffForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            var username = document.getElementById('loginUsername').value.trim();
            var password = passInput.value;
            if (!username || !password) { showAlert(staffAlert, 'Please enter both username and password.', 'danger'); return; }

            staffBtn.disabled = true;
            staffBtnText.classList.add('d-none');
            staffSpinner.classList.remove('d-none');
            staffAlert.classList.add('d-none');

            try {
                var csrfToken = staffForm.querySelector('input[name="csrf_token"]').value;
                var res = await fetch('/public/api/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username: username, password: password, csrf_token: csrfToken })
                });
                var data = await res.json();
                if (data.success) {
                    showAlert(staffAlert, data.message, 'success');
                    setTimeout(function() { window.location.href = data.redirect || '/admin/'; }, 800);
                } else {
                    showAlert(staffAlert, data.message, 'danger');
                    staffBtn.disabled = false; staffBtnText.classList.remove('d-none'); staffSpinner.classList.add('d-none');
                }
            } catch (err) {
                showAlert(staffAlert, 'Connection error. Please try again.', 'danger');
                staffBtn.disabled = false; staffBtnText.classList.remove('d-none'); staffSpinner.classList.add('d-none');
            }
        });
    }

    var patientLoginForm = document.getElementById('patientLoginForm');
    var patientRegisterForm = document.getElementById('patientRegisterForm');
    var patientAlert = document.getElementById('patientLoginAlert');
    var patientLoginBtn = document.getElementById('patientLoginBtn');
    var patientRegisterBtn = document.getElementById('patientRegisterBtn');
    var loginView = document.getElementById('patientLoginView');
    var registerView = document.getElementById('patientRegisterView');
    var showRegisterLink = document.getElementById('showRegisterLink');
    var showLoginLink = document.getElementById('showLoginLink');

    if (showRegisterLink) {
        showRegisterLink.addEventListener('click', function(e) {
            e.preventDefault();
            loginView.classList.add('d-none');
            registerView.classList.remove('d-none');
            patientAlert.classList.add('d-none');
        });
    }
    if (showLoginLink) {
        showLoginLink.addEventListener('click', function(e) {
            e.preventDefault();
            registerView.classList.add('d-none');
            loginView.classList.remove('d-none');
            patientAlert.classList.add('d-none');
        });
    }

    if (patientLoginForm) {
        patientLoginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            var email = document.getElementById('patientEmail').value.trim();
            var password = document.getElementById('patientPassword').value;
            if (!email || !password) { showAlert(patientAlert, 'Please enter both email and password.', 'danger'); return; }

            setLoading(patientLoginBtn, true);
            patientAlert.classList.add('d-none');

            try {
                var csrfToken = patientLoginForm.querySelector('input[name="csrf_token"]').value;
                var res = await fetch('/public/api/patient-login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'login', email: email, password: password, csrf_token: csrfToken })
                });
                var data = await res.json();
                if (data.success) {
                    showAlert(patientAlert, data.message, 'success');
                    setTimeout(function() { window.location.href = data.redirect || '/public/patient-dashboard.php'; }, 800);
                } else {
                    showAlert(patientAlert, data.message, 'danger');
                    setLoading(patientLoginBtn, false);
                }
            } catch (err) {
                showAlert(patientAlert, 'Connection error. Please try again.', 'danger');
                setLoading(patientLoginBtn, false);
            }
        });
    }

    if (patientRegisterForm) {
        patientRegisterForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            var name = document.getElementById('regName').value.trim();
            var email = document.getElementById('regEmail').value.trim();
            var phone = document.getElementById('regPhone').value.trim();
            var password = document.getElementById('regPassword').value;
            var confirmPassword = document.getElementById('regConfirmPassword').value;

            if (!name || !email || !phone || !password) { showAlert(patientAlert, 'All fields are required.', 'danger'); return; }
            if (password.length < 6) { showAlert(patientAlert, 'Password must be at least 6 characters.', 'danger'); return; }
            if (password !== confirmPassword) { showAlert(patientAlert, 'Passwords do not match.', 'danger'); return; }

            setLoading(patientRegisterBtn, true);
            patientAlert.classList.add('d-none');

            try {
                var csrfToken = patientRegisterForm.querySelector('input[name="csrf_token"]').value;
                var res = await fetch('/public/api/patient-login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'register', name: name, email: email, phone: phone, password: password, confirm_password: confirmPassword, csrf_token: csrfToken })
                });
                var data = await res.json();
                if (data.success) {
                    showAlert(patientAlert, data.message, 'success');
                    setTimeout(function() { window.location.href = data.redirect || '/public/patient-dashboard.php'; }, 800);
                } else {
                    showAlert(patientAlert, data.message, 'danger');
                    setLoading(patientRegisterBtn, false);
                }
            } catch (err) {
                showAlert(patientAlert, 'Connection error. Please try again.', 'danger');
                setLoading(patientRegisterBtn, false);
            }
        });
    }

    modal.addEventListener('hidden.bs.modal', function() {
        if (staffForm) { staffForm.reset(); staffAlert.classList.add('d-none'); staffBtn.disabled = false; staffBtnText.classList.remove('d-none'); staffSpinner.classList.add('d-none'); }
        if (passInput) { passInput.type = 'password'; }
        if (toggleBtn) { toggleBtn.querySelector('i').className = 'fas fa-eye'; }
        if (patientLoginForm) { patientLoginForm.reset(); }
        if (patientRegisterForm) { patientRegisterForm.reset(); }
        if (patientAlert) { patientAlert.classList.add('d-none'); }
        if (patientLoginBtn) { setLoading(patientLoginBtn, false); }
        if (patientRegisterBtn) { setLoading(patientRegisterBtn, false); }
        if (loginView) { loginView.classList.remove('d-none'); }
        if (registerView) { registerView.classList.add('d-none'); }
    });
})();

(function() {
    const apptForm = document.getElementById('appointmentForm');
    if (!apptForm) return;

    const apptAlert = document.getElementById('apptAlert');
    const apptBtnText = document.getElementById('apptBtnText');
    const apptSpinner = document.getElementById('apptSpinner');
    const apptSubmitBtn = document.getElementById('apptSubmitBtn');
    const apptSuccess = document.getElementById('apptSuccess');
    const apptFooter = document.getElementById('apptFooter');

    function showApptAlert(message, type) {
        apptAlert.className = 'alert alert-' + type + ' mb-3';
        apptAlert.textContent = message;
        apptAlert.classList.remove('d-none');
    }

    apptSubmitBtn.addEventListener('click', async function() {
        const name = document.getElementById('apptName').value.trim();
        const email = document.getElementById('apptEmail').value.trim();
        const phone = document.getElementById('apptPhone').value.trim();
        const date = document.getElementById('apptDate').value;
        const time = document.getElementById('apptTime').value;

        if (!name || !email || !phone || !date || !time) {
            showApptAlert('Please fill in all required fields.', 'danger');
            return;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showApptAlert('Please enter a valid email address.', 'danger');
            return;
        }

        apptSubmitBtn.disabled = true;
        apptBtnText.classList.add('d-none');
        apptSpinner.classList.remove('d-none');
        apptAlert.classList.add('d-none');

        try {
            const csrfToken = apptForm.querySelector('input[name="csrf_token"]').value;
            const res = await fetch('/public/api/appointment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    patient_name: name,
                    email: email,
                    phone: phone,
                    department_id: document.getElementById('apptDepartment').value,
                    doctor_id: document.getElementById('apptDoctor').value,
                    appointment_date: date,
                    appointment_time: time,
                    message: document.getElementById('apptMessage').value.trim(),
                    csrf_token: csrfToken
                })
            });
            const data = await res.json();

            if (data.success) {
                apptForm.classList.add('d-none');
                apptFooter.classList.add('d-none');
                apptSuccess.classList.remove('d-none');
            } else {
                showApptAlert(data.message, 'danger');
                apptSubmitBtn.disabled = false;
                apptBtnText.classList.remove('d-none');
                apptSpinner.classList.add('d-none');
            }
        } catch (err) {
            showApptAlert('Connection error. Please try again.', 'danger');
            apptSubmitBtn.disabled = false;
            apptBtnText.classList.remove('d-none');
            apptSpinner.classList.add('d-none');
        }
    });

    function resetApptModal() {
        apptForm.reset();
        document.getElementById('apptName').value = '';
        document.getElementById('apptEmail').value = '';
        document.getElementById('apptPhone').value = '';
        document.getElementById('apptDepartment').selectedIndex = 0;
        document.getElementById('apptDoctor').selectedIndex = 0;
        document.getElementById('apptDate').value = '';
        document.getElementById('apptTime').value = '';
        document.getElementById('apptMessage').value = '';
        apptForm.classList.remove('d-none');
        apptFooter.classList.remove('d-none');
        apptSuccess.classList.add('d-none');
        apptAlert.classList.add('d-none');
        apptSubmitBtn.disabled = false;
        apptBtnText.classList.remove('d-none');
        apptSpinner.classList.add('d-none');
    }

    document.getElementById('appointmentModal').addEventListener('hidden.bs.modal', resetApptModal);
    document.getElementById('appointmentModal').addEventListener('show.bs.modal', resetApptModal);
})();
</script>
</body>
</html>
