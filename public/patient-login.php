<?php
$pageTitle = 'Patient Login';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (isset($_SESSION['patient_id'])) {
    header('Location: /public/patient-dashboard.php');
    exit;
}

$loginError = $registerError = $registerSuccess = '';
$activeTab = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $loginError = 'Invalid form submission. Please try again.';
    } elseif (isset($_POST['action'])) {

        if ($_POST['action'] === 'login') {
            $activeTab = 'login';
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $loginError = 'Please enter both email and password.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $loginError = 'Please enter a valid email address.';
            } else {
                $stmt = $pdo->prepare("SELECT patient_id, name, email, password FROM patients WHERE email = :email");
                $stmt->execute([':email' => $email]);
                $patient = $stmt->fetch();

                if ($patient && password_verify($password, $patient['password'])) {
                    $_SESSION['patient_id'] = $patient['patient_id'];
                    $_SESSION['patient_name'] = $patient['name'];
                    $_SESSION['patient_email'] = $patient['email'];

                    $pdo->prepare("UPDATE patients SET last_login = CURRENT_TIMESTAMP WHERE patient_id = :id")
                        ->execute([':id' => $patient['patient_id']]);

                    $_SESSION['csrf_token'] = '';
                    header('Location: /public/patient-dashboard.php');
                    exit;
                } else {
                    $loginError = 'Invalid email or password.';
                }
            }
        }

        if ($_POST['action'] === 'register') {
            $activeTab = 'register';
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {
                $registerError = 'Please fill in all required fields.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $registerError = 'Please enter a valid email address.';
            } elseif (strlen($password) < 6) {
                $registerError = 'Password must be at least 6 characters.';
            } elseif ($password !== $confirmPassword) {
                $registerError = 'Passwords do not match.';
            } else {
                $existing = $pdo->prepare("SELECT patient_id FROM patients WHERE email = :email");
                $existing->execute([':email' => $email]);
                if ($existing->fetch()) {
                    $registerError = 'An account with this email already exists. Please login instead.';
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO patients (name, email, phone, password) VALUES (:name, :email, :phone, :password) RETURNING patient_id");
                    $stmt->execute([
                        ':name' => $name,
                        ':email' => $email,
                        ':phone' => $phone,
                        ':password' => $hashedPassword
                    ]);
                    $newPatient = $stmt->fetch();

                    $linkStmt = $pdo->prepare("UPDATE appointments SET patient_id = :pid WHERE email = :email AND patient_id IS NULL");
                    $linkStmt->execute([':pid' => $newPatient['patient_id'], ':email' => $email]);

                    $_SESSION['patient_id'] = $newPatient['patient_id'];
                    $_SESSION['patient_name'] = $name;
                    $_SESSION['patient_email'] = $email;

                    $pdo->prepare("UPDATE patients SET last_login = CURRENT_TIMESTAMP WHERE patient_id = :id")
                        ->execute([':id' => $newPatient['patient_id']]);

                    $_SESSION['csrf_token'] = '';
                    header('Location: /public/patient-dashboard.php');
                    exit;
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
* { font-family: 'Plus Jakarta Sans', sans-serif !important; }

.patient-auth-page {
    background: #f0f4fb;
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem 1rem;
}

.pa-wrapper {
    display: flex;
    width: 100%;
    max-width: 1000px;
    min-height: 600px;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 24px 70px rgba(26,46,94,0.16);
}

/* ── Left panel ── */
.pa-left {
    width: 40%;
    background: linear-gradient(160deg, #1e3a8a 0%, #1a2e5e 55%, #111e42 100%);
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
    flex-shrink: 0;
}
.pa-left-img {
    flex: 1;
    position: relative;
    overflow: hidden;
    min-height: 200px;
}
.pa-left-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center top;
    display: block;
    mix-blend-mode: luminosity;
    opacity: 0.8;
}
.pa-left-img .img-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to bottom, rgba(30,58,138,0.2) 0%, rgba(17,30,66,0.88) 100%);
}
.pa-left-bottom {
    padding: 1.75rem 1.75rem 2rem;
    position: relative;
    z-index: 2;
}
.pa-logo {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 1rem;
}
.pa-logo-icon {
    width: 30px; height: 30px;
    border-radius: 8px;
    background: #3b82f6;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; color: #fff;
}
.pa-logo span { font-size: 1.05rem; font-weight: 800; color: #fff; }
.pa-tagline {
    font-size: 1.15rem; font-weight: 800;
    color: #fff; line-height: 1.35; margin-bottom: 0.5rem;
}
.pa-tagline strong { color: #60a5fa; }
.pa-sub { font-size: 0.78rem; color: rgba(255,255,255,0.55); line-height: 1.55; }

.pa-deco {
    position: absolute; border-radius: 50%;
    background: rgba(59,130,246,0.07); pointer-events: none;
}
.pa-deco-1 { width: 200px; height: 200px; top: -50px; right: -50px; }
.pa-deco-2 { width: 120px; height: 120px; top: 90px; right: 60px; background: rgba(96,165,250,0.05); }

/* ── Right panel ── */
.pa-right {
    flex: 1;
    background: #fff;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

.pa-right-inner {
    padding: 2.5rem 2.75rem;
    flex: 1;
}

.pa-right-logo {
    display: flex; align-items: center; gap: 8px; margin-bottom: 1.5rem;
}
.pa-right-logo-icon {
    width: 30px; height: 30px; border-radius: 8px;
    background: linear-gradient(135deg, #1d4ed8, #3b82f6);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; color: #fff;
}
.pa-right-logo span { font-size: 1.1rem; font-weight: 800; color: #1e293b; }

.pa-title { font-size: 1.6rem; font-weight: 800; color: #1e293b; margin-bottom: 0.3rem; }
.pa-subtitle { font-size: 0.85rem; color: #64748b; margin-bottom: 1.5rem; }

/* Tabs */
.pa-tabs {
    display: flex;
    background: #f0f4fb;
    border-radius: 12px;
    padding: 4px;
    margin-bottom: 1.5rem;
    gap: 4px;
}
.pa-tab-btn {
    flex: 1;
    padding: 0.6rem 1rem;
    border: none;
    background: transparent;
    border-radius: 9px;
    font-size: 0.88rem;
    font-weight: 700;
    color: #64748b;
    cursor: pointer;
    transition: all 0.22s;
}
.pa-tab-btn.active {
    background: #fff;
    color: #1d4ed8;
    box-shadow: 0 2px 8px rgba(59,130,246,0.12);
}

/* Fields */
.pa-label {
    font-size: 0.83rem; font-weight: 600;
    color: #374151; margin-bottom: 0.35rem; display: block;
}
.pa-label .req { color: #ef4444; }
.pa-input-group {
    display: flex;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    transition: border-color 0.2s, box-shadow 0.2s;
    background: #f8faff;
    margin-bottom: 0.9rem;
}
.pa-input-group:focus-within {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
    background: #fff;
}
.pa-input-group .pa-icon {
    display: flex; align-items: center; justify-content: center;
    padding: 0 0.85rem;
    color: #94a3b8; font-size: 0.85rem;
    background: transparent;
}
.pa-input-group:focus-within .pa-icon { color: #3b82f6; }
.pa-input-group input {
    flex: 1; border: none; outline: none;
    background: transparent;
    padding: 0.65rem 0.85rem 0.65rem 0;
    font-size: 0.88rem; color: #1e293b;
}
.pa-input-group input::placeholder { color: #94a3b8; }

.pa-password-row {
    display: flex; justify-content: space-between;
    align-items: center; margin-bottom: 0.35rem;
}

.pa-btn {
    background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%);
    color: #fff; border: none; border-radius: 10px;
    padding: 0.72rem; font-size: 0.92rem; font-weight: 700;
    width: 100%; cursor: pointer; transition: all 0.22s;
    box-shadow: 0 6px 20px rgba(59,130,246,0.28);
    margin-top: 0.25rem;
}
.pa-btn:hover {
    background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
    box-shadow: 0 8px 26px rgba(59,130,246,0.38);
    transform: translateY(-1px);
}

.pa-switch {
    text-align: center; margin-top: 1rem;
    font-size: 0.82rem; color: #94a3b8;
}
.pa-switch a {
    color: #3b82f6; font-weight: 700; text-decoration: none;
}
.pa-switch a:hover { color: #1d4ed8; }

.pa-error {
    background: #fef2f2; border: 1px solid #fecaca;
    color: #b91c1c; border-radius: 10px;
    padding: 0.65rem 0.9rem; font-size: 0.84rem;
    margin-bottom: 0.9rem; display: flex; align-items: center; gap: 6px;
}
.pa-success {
    background: #f0fdf4; border: 1px solid #bbf7d0;
    color: #166534; border-radius: 10px;
    padding: 0.65rem 0.9rem; font-size: 0.84rem;
    margin-bottom: 0.9rem; display: flex; align-items: center; gap: 6px;
}

.pa-footer {
    text-align: center; padding: 1rem 2.75rem 1.5rem;
    font-size: 0.78rem; color: #94a3b8;
    border-top: 1px solid #f1f5f9;
}

.tab-panel { display: none; }
.tab-panel.active { display: block; }

@media (max-width: 720px) {
    .pa-wrapper { flex-direction: column; max-width: 460px; min-height: auto; }
    .pa-left { width: 100%; min-height: 200px; }
    .pa-left-img { min-height: 130px; }
    .pa-right-inner { padding: 1.75rem 1.5rem; }
    .pa-footer { padding: 0.75rem 1.5rem 1.25rem; }
}
</style>

<div class="patient-auth-page">
    <div class="pa-wrapper">

        <!-- Left panel -->
        <div class="pa-left">
            <div class="pa-deco pa-deco-1"></div>
            <div class="pa-deco pa-deco-2"></div>
            <div class="pa-left-img">
                <img src="https://images.unsplash.com/photo-1579684385127-1ef15d508118?w=600&h=700&fit=crop&q=80" alt="Patient Care">
                <div class="img-overlay"></div>
            </div>
            <div class="pa-left-bottom">
                <div class="pa-logo">
                    <div class="pa-logo-icon"><i class="fas fa-heartbeat"></i></div>
                    <span>JMedi</span>
                </div>
                <div class="pa-tagline">Your health,<br><strong>our priority.</strong></div>
                <div class="pa-sub">Book appointments, access records, and manage your care — all in one place.</div>
            </div>
        </div>

        <!-- Right panel -->
        <div class="pa-right">
            <div class="pa-right-inner">

                <div class="pa-right-logo">
                    <div class="pa-right-logo-icon"><i class="fas fa-heartbeat"></i></div>
                    <span>JMedi</span>
                </div>

                <h1 class="pa-title">Patient Portal</h1>
                <p class="pa-subtitle">Access your appointments and medical records</p>

                <!-- Tabs -->
                <div class="pa-tabs">
                    <button class="pa-tab-btn <?= $activeTab === 'login' ? 'active' : '' ?>" onclick="switchTab('login')" id="tabLogin">
                        <i class="fas fa-sign-in-alt me-1"></i> Sign In
                    </button>
                    <button class="pa-tab-btn <?= $activeTab === 'register' ? 'active' : '' ?>" onclick="switchTab('register')" id="tabRegister">
                        <i class="fas fa-user-plus me-1"></i> Register
                    </button>
                </div>

                <!-- Login tab -->
                <div id="panelLogin" class="tab-panel <?= $activeTab === 'login' ? 'active' : '' ?>">
                    <?php if ($loginError): ?>
                    <div class="pa-error"><i class="fas fa-exclamation-circle"></i> <?= e($loginError) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="login">

                        <label class="pa-label">Email Address</label>
                        <div class="pa-input-group">
                            <span class="pa-icon"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" placeholder="your@email.com" required
                                value="<?= e(($_POST['action'] ?? '') === 'login' ? ($_POST['email'] ?? '') : '') ?>">
                        </div>

                        <div class="pa-password-row">
                            <label class="pa-label mb-0">Password</label>
                        </div>
                        <div class="pa-input-group">
                            <span class="pa-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" placeholder="••••••••••" required>
                        </div>

                        <button type="submit" class="pa-btn"><i class="fas fa-sign-in-alt me-2"></i>Sign In</button>
                    </form>

                    <div class="pa-switch">
                        Don't have an account? <a href="javascript:void(0)" onclick="switchTab('register')">Register here</a>
                    </div>
                </div>

                <!-- Register tab -->
                <div id="panelRegister" class="tab-panel <?= $activeTab === 'register' ? 'active' : '' ?>">
                    <?php if ($registerError): ?>
                    <div class="pa-error"><i class="fas fa-exclamation-circle"></i> <?= e($registerError) ?></div>
                    <?php endif; ?>
                    <?php if ($registerSuccess): ?>
                    <div class="pa-success"><i class="fas fa-check-circle"></i> <?= e($registerSuccess) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="register">

                        <label class="pa-label">Full Name <span class="req">*</span></label>
                        <div class="pa-input-group">
                            <span class="pa-icon"><i class="fas fa-user"></i></span>
                            <input type="text" name="name" placeholder="John Doe" required
                                value="<?= e(($_POST['action'] ?? '') === 'register' ? ($_POST['name'] ?? '') : '') ?>">
                        </div>

                        <label class="pa-label">Email Address <span class="req">*</span></label>
                        <div class="pa-input-group">
                            <span class="pa-icon"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" placeholder="your@email.com" required
                                value="<?= e(($_POST['action'] ?? '') === 'register' ? ($_POST['email'] ?? '') : '') ?>">
                        </div>

                        <label class="pa-label">Phone Number</label>
                        <div class="pa-input-group">
                            <span class="pa-icon"><i class="fas fa-phone"></i></span>
                            <input type="tel" name="phone" placeholder="+91 98765 43210"
                                value="<?= e(($_POST['action'] ?? '') === 'register' ? ($_POST['phone'] ?? '') : '') ?>">
                        </div>

                        <label class="pa-label">Password <span class="req">*</span></label>
                        <div class="pa-input-group">
                            <span class="pa-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" placeholder="Min 6 characters" required minlength="6">
                        </div>

                        <label class="pa-label">Confirm Password <span class="req">*</span></label>
                        <div class="pa-input-group">
                            <span class="pa-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" name="confirm_password" placeholder="Re-enter password" required minlength="6">
                        </div>

                        <button type="submit" class="pa-btn"><i class="fas fa-user-plus me-2"></i>Create Account</button>
                    </form>

                    <div class="pa-switch">
                        Already have an account? <a href="javascript:void(0)" onclick="switchTab('login')">Sign in here</a>
                    </div>
                </div>

            </div>
            <div class="pa-footer">
                Powered by <strong style="color:#1e293b;">JNVWeb</strong> &nbsp;·&nbsp; &copy; <?= date('Y') ?> JMedi
            </div>
        </div>

    </div>
</div>

<script>
function switchTab(tab) {
    document.getElementById('tabLogin').classList.toggle('active', tab === 'login');
    document.getElementById('tabRegister').classList.toggle('active', tab === 'register');
    document.getElementById('panelLogin').classList.toggle('active', tab === 'login');
    document.getElementById('panelRegister').classList.toggle('active', tab === 'register');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
