<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (loginAdmin($pdo, $username, $password)) {
            header('Location: /admin/dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login – JMedi</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        * { font-family: 'Plus Jakarta Sans', sans-serif; box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            min-height: 100vh;
            background: #f0f4fb;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .login-wrapper {
            display: flex;
            width: 100%;
            max-width: 960px;
            min-height: 560px;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 24px 70px rgba(26,46,94,0.18);
        }

        /* ── Left panel ───────────────────────── */
        .login-left {
            width: 42%;
            background: linear-gradient(160deg, #1e3a8a 0%, #1a2e5e 55%, #111e42 100%);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }

        .login-left-img {
            flex: 1;
            position: relative;
            overflow: hidden;
        }

        .login-left-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
            display: block;
            mix-blend-mode: luminosity;
            opacity: 0.85;
        }

        .login-left-img .img-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(30,58,138,0.25) 0%, rgba(17,30,66,0.85) 100%);
        }

        .login-left-bottom {
            padding: 2rem 2rem 2.2rem;
            position: relative;
            z-index: 2;
        }

        .left-logo {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 1.1rem;
        }

        .left-logo-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #3b82f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: #fff;
        }

        .left-logo span {
            font-size: 1.1rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: 0.5px;
        }

        .left-tagline {
            font-size: 1.25rem;
            font-weight: 800;
            color: #fff;
            line-height: 1.35;
            margin-bottom: 0.6rem;
        }

        .left-tagline strong {
            color: #60a5fa;
        }

        .left-sub {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.6);
            line-height: 1.55;
        }

        /* Decorative circles */
        .deco-circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(59,130,246,0.08);
            pointer-events: none;
        }
        .deco-circle-1 { width: 220px; height: 220px; top: -60px; right: -60px; }
        .deco-circle-2 { width: 140px; height: 140px; top: 80px; right: 60px; background: rgba(96,165,250,0.06); }
        .deco-circle-3 { width: 100px; height: 100px; bottom: 120px; left: -30px; background: rgba(59,130,246,0.05); }

        /* ── Right panel ───────────────────────── */
        .login-right {
            flex: 1;
            background: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem 3rem;
        }

        .right-logo {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 2rem;
        }

        .right-logo-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, #1d4ed8, #3b82f6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.88rem;
            color: #fff;
        }

        .right-logo span {
            font-size: 1.2rem;
            font-weight: 800;
            color: #1e293b;
        }

        .login-title {
            font-size: 1.75rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 0.4rem;
        }

        .login-subtitle {
            font-size: 0.88rem;
            color: #64748b;
            margin-bottom: 1.75rem;
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.4rem;
        }

        .form-control {
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.65rem 1rem;
            font-size: 0.9rem;
            color: #1e293b;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: #f8faff;
        }

        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.12);
            outline: none;
            background: #fff;
        }

        .input-group-text {
            border: 1.5px solid #e2e8f0;
            border-right: none;
            background: #f8faff;
            color: #94a3b8;
            border-radius: 10px 0 0 10px;
            padding: 0 0.85rem;
        }

        .input-group .form-control {
            border-radius: 0 10px 10px 0;
            border-left: none;
        }

        .input-group:focus-within .input-group-text {
            border-color: #3b82f6;
            color: #3b82f6;
        }

        .input-group:focus-within .form-control {
            border-color: #3b82f6;
        }

        .password-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.4rem;
        }

        .forgot-link {
            font-size: 0.8rem;
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
        }

        .forgot-link:hover { color: #1d4ed8; }

        .btn-signin {
            background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            font-size: 0.95rem;
            font-weight: 700;
            width: 100%;
            cursor: pointer;
            transition: all 0.25s;
            box-shadow: 0 6px 20px rgba(59,130,246,0.3);
            margin-top: 0.5rem;
        }

        .btn-signin:hover {
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
            box-shadow: 0 8px 28px rgba(59,130,246,0.4);
            transform: translateY(-1px);
        }

        .login-footer {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.82rem;
            color: #94a3b8;
        }

        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 10px;
            padding: 0.7rem 1rem;
            font-size: 0.88rem;
            margin-bottom: 1rem;
        }

        .back-to-site {
            position: fixed;
            top: 1.25rem;
            left: 1.25rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff;
            color: #1e293b;
            border: 1.5px solid #e2e8f0;
            border-radius: 30px;
            padding: 0.45rem 1rem;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.2s;
            z-index: 100;
        }
        .back-to-site:hover {
            background: #1a2e5e;
            color: #fff;
            border-color: #1a2e5e;
            box-shadow: 0 4px 16px rgba(26,46,94,0.2);
            transform: translateX(-2px);
        }
        .back-to-site i { font-size: 0.75rem; }

        @media (max-width: 680px) {
            .login-wrapper { flex-direction: column; max-width: 420px; min-height: auto; }
            .login-left { width: 100%; min-height: 200px; }
            .login-left-img { min-height: 120px; }
            .login-right { padding: 2rem 1.75rem; }
            .back-to-site { top: 0.75rem; left: 0.75rem; font-size: 0.75rem; padding: 0.35rem 0.8rem; }
        }
    </style>
</head>
<body>

<a href="/" class="back-to-site">
    <i class="fas fa-arrow-left"></i> Back to Website
</a>

<div class="login-wrapper">

    <!-- Left panel -->
    <div class="login-left">
        <div class="deco-circle deco-circle-1"></div>
        <div class="deco-circle deco-circle-2"></div>
        <div class="deco-circle deco-circle-3"></div>

        <div class="login-left-img">
            <img src="https://images.unsplash.com/photo-1622253692010-333f2da6031d?w=600&h=600&fit=crop&q=80" alt="Doctor">
            <div class="img-overlay"></div>
        </div>

        <div class="login-left-bottom">
            <div class="left-logo">
                <div class="left-logo-icon"><i class="fas fa-heartbeat"></i></div>
                <span>JMedi</span>
            </div>
            <div class="left-tagline">Welcome to <strong>JMedi</strong><br>Hospital Management System</div>
            <div class="left-sub">Cloud-based Smart Medical Platform with centralised, user-friendly admin panel.</div>
        </div>
    </div>

    <!-- Right panel -->
    <div class="login-right">
        <div class="right-logo">
            <div class="right-logo-icon"><i class="fas fa-heartbeat"></i></div>
            <span>JMedi</span>
        </div>

        <h1 class="login-title">Login</h1>
        <p class="login-subtitle">Enter your credentials to access your account</p>

        <?php if ($error): ?>
        <div class="alert-danger"><i class="fas fa-exclamation-circle me-1"></i> <?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>

            <div class="mb-3">
                <label class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user fa-sm"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="Enter your username" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-1">
                <div class="password-row">
                    <label class="form-label mb-0">Password</label>
                    <a href="/admin/profile.php" class="forgot-link">Forgot Password?</a>
                </div>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock fa-sm"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="••••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn-signin"><i class="fas fa-sign-in-alt me-2"></i>Sign In</button>
        </form>

        <div class="login-footer">
            Powered by <strong style="color:#1e293b;">JNVWeb</strong> &nbsp;·&nbsp; &copy; <?= date('Y') ?> JMedi
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
