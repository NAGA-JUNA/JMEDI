<?php
$pageTitle = 'Website Settings';
require_once __DIR__ . '/../includes/admin_header.php';
requirePermission('settings');

$success = '';
$errors  = [];
$testRecipient = '';
$s = getSettings($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $settingsAction = $_POST['settings_action'] ?? 'save_settings';

        /* ── Remove logo action ── */
        if (!empty($_POST['remove_logo'])) {
            $removeKey = $_POST['remove_logo'];
            if (in_array($removeKey, ['frontend_logo','footer_logo','admin_logo','favicon'])) {
                $oldPath = $s[$removeKey] ?? '';
                if ($oldPath) {
                    $abs = __DIR__ . '/..' . $oldPath;
                    if (file_exists($abs)) @unlink($abs);
                }
                updateSetting($pdo, $removeKey, '');
                $success = 'Logo removed.';
                $s = getSettings($pdo);
            }
        } elseif ($settingsAction === 'send_test_email') {
            $testRecipient = trim($_POST['test_email_recipient'] ?? '');
            if ($testRecipient === '' || !filter_var($testRecipient, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid test recipient email address.';
            } else {
                $sender = getNotificationSenderConfig($pdo);
                $siteName = $sender['site_name'] ?? 'JMedi';
                $subject = 'SMTP Test Email - ' . $siteName;
                $body = "Hello,

This is a test email from {$siteName}.
";
                $body .= "If you received this message, your notification email configuration is working.

";
                $body .= 'Sent at: ' . date('Y-m-d H:i:s') . "
";
                $body .= "
Regards,
{$siteName}";
                $headers = buildNotificationMailHeaders($pdo);

                $sent = false;
                try {
                    $sent = sendNotificationEmail($pdo, $testRecipient, $subject, $body, $headers);
                } catch (Throwable $e) {
                    error_log('settings.php test email exception: ' . $e->getMessage());
                }

                if ($sent) {
                    $success = 'Test email sent successfully to ' . $testRecipient . '.';
                } else {
                    $errors[] = 'Test email could not be sent. Please verify SMTP/from settings and check server logs.';
                }
            }
            $s = getSettings($pdo);
        } else {
            /* ── Text fields ── */
            $settingKeys = [
                'site_name','tagline','phone','emergency_phone','email','address',
                'facebook','twitter','instagram','linkedin','youtube',
                'primary_color','secondary_color','footer_text','whatsapp_number','whatsapp_message',
                'appointment_email','google_maps_embed','meta_description','favicon','notify_on_booking','notify_on_status_change','mail_from_email','mail_from_name','mail_transport','smtp_host','smtp_port','smtp_encryption','smtp_username','smtp_password'
            ];
            foreach ($settingKeys as $key) {
                if (isset($_POST[$key])) {
                    updateSetting($pdo, $key, trim($_POST[$key]));
                }
            }
            if (($_GET['tab'] ?? 'general') === 'advanced') {
                updateSetting($pdo, 'notify_on_booking', isset($_POST['notify_on_booking']) ? '1' : '0');
                updateSetting($pdo, 'notify_on_status_change', isset($_POST['notify_on_status_change']) ? '1' : '0');
            }
            /* ── Logo uploads ── */
            $logoFields = [
                'frontend_logo' => 'Frontend',
                'footer_logo'   => 'Footer',
                'admin_logo'    => 'Admin Panel',
            ];
            foreach ($logoFields as $field => $label) {
                if (!empty($_FILES[$field]['name'])) {
                    $result = uploadImageDetailed($_FILES[$field], 'logos');
                    if (substr($result, 0, 1) === '/') {
                        updateSetting($pdo, $field, $result);
                    } else {
                        $errors[] = "$label Logo: $result";
                    }
                }
            }

            /* ── Favicon upload ── */
            if (!empty($_FILES['favicon_file']['name'])) {
                $result = uploadImageDetailed($_FILES['favicon_file'], 'logos');
                if (substr($result, 0, 1) === '/') {
                    updateSetting($pdo, 'favicon', $result);
                } else {
                    $errors[] = "Favicon: $result";
                }
            }

            $success = empty($errors) ? 'Settings saved successfully.' : 'Settings saved, but some uploads failed.';
            $s = getSettings($pdo);
        }
    } else {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }
}

$s = getSettings($pdo);

/* ── Fallback: define uploadImageDetailed here if functions.php is not yet updated ── */
if (!function_exists('uploadImageDetailed')) {
    function uploadImageDetailed(array $file, string $dir = 'uploads'): string {
        $uploadDir = __DIR__ . '/../assets/' . $dir . '/';
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0755, true)) {
                $e = 'Cannot create upload directory: ' . $uploadDir . ' — check server permissions.';
                error_log('[JMedi Upload] ' . $e); return $e;
            }
        }
        if (!is_writable($uploadDir)) {
            $e = 'Upload directory not writable: ' . $uploadDir;
            error_log('[JMedi Upload] ' . $e); return $e;
        }
        $phpErrMap = [
            UPLOAD_ERR_INI_SIZE   => 'File too large — exceeds PHP upload_max_filesize (' . ini_get('upload_max_filesize') . ').',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
            UPLOAD_ERR_PARTIAL    => 'File only partially uploaded. Try again.',
            UPLOAD_ERR_NO_FILE    => 'No file was sent.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server has no temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Server cannot write to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
        ];
        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            $e = $phpErrMap[$file['error']] ?? 'PHP upload error code: ' . $file['error'];
            error_log('[JMedi Upload] ' . $e); return $e;
        }
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $e = 'No valid uploaded file (tmp_name missing).';
            error_log('[JMedi Upload] ' . $e); return $e;
        }
        $allowedMimes = ['image/jpeg','image/png','image/gif','image/webp','image/x-icon','image/vnd.microsoft.icon'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($realMime, $allowedMimes)) {
            $e = 'Invalid file type: ' . $realMime . '. Allowed: JPG/PNG/GIF/WEBP/ICO.';
            error_log('[JMedi Upload] ' . $e); return $e;
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            $e = 'File too large (' . round($file['size']/1024/1024,1) . ' MB). Max 10 MB.';
            error_log('[JMedi Upload] ' . $e); return $e;
        }
        $allowedExts = ['jpg','jpeg','png','gif','webp','ico'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) {
            $ext = str_replace(['jpeg','x-icon','vnd.microsoft.icon'],['jpg','ico','ico'], explode('/',$realMime)[1] ?? 'jpg');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $filepath = $uploadDir . $filename;
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            error_log('[JMedi Upload] SUCCESS: ' . $filepath);
            return '/assets/' . $dir . '/' . $filename;
        }
        $e = 'move_uploaded_file() failed. Target: ' . $filepath . ' — check permissions.';
        error_log('[JMedi Upload] ' . $e); return $e;
    }
}

/* ── Active tab ── */
$tab = $_GET['tab'] ?? 'general';
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show" style="border-radius:10px;border:none;">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Some issues occurred:</strong>
    <ul class="mb-0 mt-1">
        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" style="border-radius:10px;border:none;">
    <i class="fas fa-check-circle me-2"></i><?= e($success) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<style>
.settings-tabs .nav-link{border-radius:10px;padding:.55rem 1rem;font-weight:600;font-size:.88rem;color:var(--admin-primary);transition:.2s;}
.settings-tabs .nav-link.active{background:var(--admin-accent);color:#fff;}
.settings-tabs .nav-link i{width:18px;}
.logo-card{background:#fff;border-radius:14px;padding:1.2rem;border:2px dashed #d0dbe8;transition:.3s;text-align:center;}
.logo-card:hover{border-color:var(--admin-accent);}
.logo-preview-box{min-height:90px;display:flex;align-items:center;justify-content:center;border-radius:10px;overflow:hidden;margin-bottom:.75rem;}
.logo-preview-box img{max-height:80px;max-width:100%;object-fit:contain;}
.logo-preview-box.dark{background:#0f2137;}
.logo-preview-box.light{background:#f5f7fb;}
.logo-preview-box.admin{background:#1a2e5e;}
.logo-placeholder{color:#b0bec5;font-size:.82rem;}
.logo-upload-btn{display:none;}
.logo-upload-label{cursor:pointer;display:block;border-radius:8px;border:1px solid var(--admin-accent);color:var(--admin-accent);padding:.3rem .8rem;font-size:.83rem;font-weight:600;transition:.2s;}
.logo-upload-label:hover{background:var(--admin-accent);color:#fff;}
.btn-remove-logo{font-size:.78rem;padding:.25rem .6rem;border-radius:7px;}
.settings-section-title{font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--admin-accent);border-bottom:2px solid #e8edf3;padding-bottom:.4rem;margin-bottom:1rem;}
.color-swatch{width:36px;height:36px;border-radius:7px;border:2px solid #dee2e6;cursor:pointer;}
</style>

<div class="row g-3">
    <!-- Sidebar tabs -->
    <div class="col-lg-2 col-md-3">
        <div class="dash-card p-2">
            <nav class="nav flex-column settings-tabs gap-1">
                <a class="nav-link <?= $tab==='general'?'active':'' ?>" href="?tab=general"><i class="fas fa-building me-2"></i>General</a>
                <a class="nav-link <?= $tab==='logos'?'active':'' ?>" href="?tab=logos"><i class="fas fa-image me-2"></i>Logos</a>
                <a class="nav-link <?= $tab==='contact'?'active':'' ?>" href="?tab=contact"><i class="fas fa-phone me-2"></i>Contact</a>
                <a class="nav-link <?= $tab==='social'?'active':'' ?>" href="?tab=social"><i class="fab fa-facebook me-2"></i>Social</a>
                <a class="nav-link <?= $tab==='appearance'?'active':'' ?>" href="?tab=appearance"><i class="fas fa-palette me-2"></i>Appearance</a>
                <a class="nav-link <?= $tab==='advanced'?'active':'' ?>" href="?tab=advanced"><i class="fas fa-cog me-2"></i>Advanced</a>
                <a class="nav-link" href="/admin/email-templates.php"><i class="fas fa-envelope-open-text me-2"></i>Email Templates</a>
            </nav>
        </div>
    </div>

    <!-- Main content -->
    <div class="col-lg-10 col-md-9">

        <!-- ═══════════════════════════ GENERAL ═══════════════════════════ -->
        <?php if ($tab === 'general'): ?>
        <div class="dash-card">
            <div class="settings-section-title"><i class="fas fa-building me-1"></i> General Information</div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="settings_action" value="save_settings">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Hospital / Site Name</label>
                        <input type="text" name="site_name" class="form-control" value="<?= e($s['site_name'] ?? '') ?>" placeholder="e.g. JMedi Hospital">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Tagline</label>
                        <input type="text" name="tagline" class="form-control" value="<?= e($s['tagline'] ?? '') ?>" placeholder="Smart Medical Platform">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Footer Text</label>
                        <input type="text" name="footer_text" class="form-control" value="<?= e($s['footer_text'] ?? '') ?>" placeholder="© 2026 JMedi. All Rights Reserved.">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Meta Description <small class="text-muted">(SEO)</small></label>
                        <input type="text" name="meta_description" class="form-control" value="<?= e($s['meta_description'] ?? '') ?>" placeholder="Short site description for search engines">
                    </div>
                    <div class="col-12 mt-2">
                        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save General</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- ═══════════════════════════ LOGOS ═══════════════════════════ -->
        <?php elseif ($tab === 'logos'): ?>
        <?php
        /* ── Upload pre-flight diagnostics ── */
        $logosDir   = __DIR__ . '/../assets/logos/';
        $dirExists  = is_dir($logosDir);
        $dirWrite   = $dirExists && is_writable($logosDir);
        $phpUpload  = ini_get('upload_max_filesize');
        $phpPost    = ini_get('post_max_size');
        $phpHandler = php_sapi_name();
        if (!$dirExists) @mkdir($logosDir, 0755, true);
        if ($dirExists && !$dirWrite) @chmod($logosDir, 0755);
        $dirWriteAfter = is_dir($logosDir) && is_writable($logosDir);
        $diagOk = $dirWriteAfter;
        ?>
        <?php if (!$diagOk): ?>
        <div class="alert alert-danger mb-3" style="border-radius:10px;border:none;">
            <strong><i class="fas fa-exclamation-triangle me-2"></i>Upload directory not writable</strong>
            <ul class="mb-1 mt-2 small">
                <li>Path: <code><?= htmlspecialchars($logosDir) ?></code></li>
                <li>Directory exists: <?= $dirExists ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>' ?></li>
                <li>Writable: <span class="text-danger">No</span></li>
            </ul>
            <p class="mb-0 small"><strong>Fix on cPanel:</strong> Go to File Manager → <code>assets/logos/</code> → right-click → Permissions → set to <code>755</code> (or <code>775</code>).</p>
        </div>
        <?php else: ?>
        <div class="alert alert-success alert-dismissible fade show mb-3" style="border-radius:10px;border:none;padding:.6rem 1rem;">
            <i class="fas fa-check-circle me-2"></i>
            Upload directory is ready &nbsp;|&nbsp; PHP limit: <strong><?= htmlspecialchars($phpUpload) ?></strong> upload / <strong><?= htmlspecialchars($phpPost) ?></strong> post &nbsp;|&nbsp; Handler: <code><?= htmlspecialchars($phpHandler) ?></code>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <div class="dash-card">
            <div class="settings-section-title"><i class="fas fa-image me-1"></i> Logo Management</div>
            <p class="text-muted small mb-3">Accepted: JPG, PNG, GIF, WEBP &nbsp;|&nbsp; Max 5 MB each &nbsp;|&nbsp; Recommended: transparent PNG</p>
            <div class="row g-3">
                <?php
                $logoMeta = [
                    'frontend_logo' => ['label'=>'Frontend Logo','bg'=>'light','hint'=>'Shown in the website header'],
                    'footer_logo'   => ['label'=>'Footer Logo',  'bg'=>'dark', 'hint'=>'Shown in the website footer. Falls back to Frontend Logo if empty.'],
                    'admin_logo'    => ['label'=>'Admin Logo',   'bg'=>'admin','hint'=>'Shown in the admin sidebar'],
                ];
                foreach ($logoMeta as $field => $meta):
                    $current = $s[$field] ?? '';
                ?>
                <div class="col-md-4">
                    <div class="logo-card">
                        <p class="fw-semibold mb-2" style="font-size:.9rem;"><?= $meta['label'] ?></p>
                        <div class="logo-preview-box <?= $meta['bg'] ?>" id="preview-box-<?= $field ?>">
                            <?php if ($current): ?>
                            <img src="<?= e($current) ?>?v=<?= time() ?>" alt="<?= $meta['label'] ?>" id="preview-img-<?= $field ?>">
                            <?php else: ?>
                            <div class="logo-placeholder" id="preview-img-<?= $field ?>"><i class="fas fa-image fa-2x mb-1 d-block"></i>No logo set</div>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted d-block mb-2"><?= $meta['hint'] ?></small>
                        <!-- Upload -->
                        <form method="POST" action="?tab=logos" enctype="multipart/form-data" class="mb-2">
                            <?= csrfField() ?>
                            <input type="file" name="<?= $field ?>" id="file-<?= $field ?>" class="logo-upload-btn" accept="image/*" onchange="previewLogo(this,'<?= $field ?>')">
                            <label for="file-<?= $field ?>" class="logo-upload-label w-100 text-center mb-2">
                                <i class="fas fa-upload me-1"></i>Choose File
                            </label>
                            <div id="file-name-<?= $field ?>" class="text-muted small mb-2" style="display:none;word-break:break-all;"></div>
                            <button type="submit" class="btn btn-primary btn-sm w-100" id="upload-btn-<?= $field ?>" style="display:none;">
                                <i class="fas fa-cloud-upload-alt me-1"></i>Upload &amp; Save
                            </button>
                        </form>
                        <!-- Remove -->
                        <?php if ($current): ?>
                        <form method="POST" action="?tab=logos">
                            <?= csrfField() ?>
                            <input type="hidden" name="remove_logo" value="<?= $field ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100 btn-remove-logo"
                                    onclick="return confirm('Remove this logo?')">
                                <i class="fas fa-trash-alt me-1"></i>Remove Logo
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Favicon -->
            <div class="mt-4">
                <div class="settings-section-title"><i class="fas fa-star me-1"></i> Favicon</div>
                <div class="row g-3 align-items-center">
                    <div class="col-auto">
                        <?php $fav = $s['favicon'] ?? ''; ?>
                        <?php if ($fav): ?>
                        <img src="<?= e($fav) ?>?v=<?= time() ?>" alt="Favicon" style="width:48px;height:48px;object-fit:contain;border-radius:8px;background:#f5f7fb;padding:6px;border:1px solid #dee2e6;">
                        <?php else: ?>
                        <div style="width:48px;height:48px;border-radius:8px;background:#f5f7fb;border:1px solid #dee2e6;display:flex;align-items:center;justify-content:center;color:#b0bec5;"><i class="fas fa-globe"></i></div>
                        <?php endif; ?>
                    </div>
                    <div class="col">
                        <form method="POST" enctype="multipart/form-data" class="d-flex gap-2 align-items-center flex-wrap">
                            <?= csrfField() ?>
                            <input type="file" name="favicon_file" class="form-control form-control-sm" accept="image/*,.ico" style="max-width:260px;">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-upload me-1"></i>Upload Favicon</button>
                            <?php if ($fav): ?>
                            <form method="POST" style="display:inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="remove_logo" value="favicon">
                                <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Remove favicon?')"><i class="fas fa-trash-alt me-1"></i>Remove</button>
                            </form>
                            <?php endif; ?>
                        </form>
                        <small class="text-muted d-block mt-1">Recommended: 32×32 or 64×64 PNG or ICO</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════ CONTACT ═══════════════════════════ -->
        <?php elseif ($tab === 'contact'): ?>
        <div class="dash-card">
            <div class="settings-section-title"><i class="fas fa-phone me-1"></i> Contact Information</div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Main Phone</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="text" name="phone" class="form-control" value="<?= e($s['phone'] ?? '') ?>" placeholder="+1 (800) 123-4567">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Emergency Phone</label>
                        <div class="input-group">
                            <span class="input-group-text text-danger"><i class="fas fa-ambulance"></i></span>
                            <input type="text" name="emergency_phone" class="form-control" value="<?= e($s['emergency_phone'] ?? '') ?>" placeholder="+1 (800) 911-0000">
                        </div>
                        <small class="text-muted">Shown in the footer emergency section</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control" value="<?= e($s['email'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Appointment / Notification Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-calendar-check"></i></span>
                            <input type="email" name="appointment_email" class="form-control" value="<?= e($s['appointment_email'] ?? '') ?>" placeholder="Receives appointment notifications">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Full Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                            <input type="text" name="address" class="form-control" value="<?= e($s['address'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fab fa-whatsapp text-success me-1"></i>WhatsApp Number</label>
                        <div class="input-group">
                            <span class="input-group-text text-success"><i class="fab fa-whatsapp"></i></span>
                            <input type="text" name="whatsapp_number" class="form-control" value="<?= e($s['whatsapp_number'] ?? '') ?>" placeholder="18001234567">
                        </div>
                        <small class="text-muted">Country code + number, no +, no spaces. e.g. <code>18001234567</code></small>
                    </div>

                    <!-- WhatsApp Pre-filled Message Template -->
                    <div class="col-12">
                        <div class="p-3 rounded-3" style="background:#f0fdf4;border:1.5px solid #86efac;">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                <label class="form-label fw-semibold mb-0" style="color:#15803d;">
                                    <i class="fab fa-whatsapp me-1"></i> WhatsApp Pre-filled Message Template
                                </label>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="resetWAMsg()">
                                    <i class="fas fa-undo me-1"></i>Reset to Default
                                </button>
                            </div>
                            <textarea name="whatsapp_message" id="waMsg" class="form-control" rows="4"
                                placeholder="Hi {site_name}! I would like to request more information about your healthcare services..."
                                oninput="updateWAPreview()"><?= e($s['whatsapp_message'] ?? '') ?></textarea>
                            <div class="mt-2 d-flex gap-2 flex-wrap align-items-center">
                                <small class="text-muted flex-grow-1">
                                    Use <code>{site_name}</code> — it will be replaced with your hospital name automatically.<br>
                                    Keep under 200 characters for best results on mobile WhatsApp.
                                    <span id="waCharCount" class="ms-2 fw-semibold" style="color:#15803d;"></span>
                                </small>
                            </div>
                            <!-- Live preview -->
                            <div class="mt-3">
                                <div class="settings-section-title mb-1" style="font-size:.72rem;">Live Preview — how it appears in WhatsApp</div>
                                <div id="waPreview" style="background:#dcf8c6;border-radius:10px 10px 0 10px;padding:10px 14px;font-size:.88rem;color:#1a1a1a;max-width:360px;box-shadow:0 1px 4px rgba(0,0,0,.12);white-space:pre-wrap;word-break:break-word;font-family:'Segoe UI',sans-serif;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Google Maps Embed URL</label>
                        <input type="text" name="google_maps_embed" class="form-control" value="<?= e($s['google_maps_embed'] ?? '') ?>" placeholder="https://www.google.com/maps/embed?pb=...">
                        <small class="text-muted">Paste the embed URL from Google Maps → Share → Embed a map → Copy link</small>
                    </div>
                    <div class="col-12 mt-2">
                        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Contact Info</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- ═══════════════════════════ SOCIAL ═══════════════════════════ -->
        <?php elseif ($tab === 'social'): ?>
        <div class="dash-card">
            <div class="settings-section-title"><i class="fas fa-share-alt me-1"></i> Social Media Links</div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <div class="row g-3">
                    <?php
                    $socials = [
                        'facebook'  => ['fab fa-facebook-f',  'Facebook',  'https://facebook.com/yourpage'],
                        'twitter'   => ['fab fa-twitter',     'Twitter / X','https://twitter.com/yourhandle'],
                        'instagram' => ['fab fa-instagram',   'Instagram', 'https://instagram.com/yourpage'],
                        'linkedin'  => ['fab fa-linkedin-in', 'LinkedIn',  'https://linkedin.com/company/yourpage'],
                        'youtube'   => ['fab fa-youtube',     'YouTube',   'https://youtube.com/@yourchannel'],
                    ];
                    foreach ($socials as $key => [$icon, $label, $placeholder]):
                    ?>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="<?= $icon ?> me-1"></i><?= $label ?></label>
                        <input type="url" name="<?= $key ?>" class="form-control" value="<?= e($s[$key] ?? '') ?>" placeholder="<?= $placeholder ?>">
                    </div>
                    <?php endforeach; ?>
                    <div class="col-12 mt-2">
                        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Social Links</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- ═══════════════════════════ APPEARANCE ═══════════════════════════ -->
        <?php elseif ($tab === 'appearance'): ?>
        <div class="dash-card">
            <div class="settings-section-title"><i class="fas fa-palette me-1"></i> Appearance / Branding</div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Primary Color</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" name="primary_color" class="form-control form-control-color" value="<?= e($s['primary_color'] ?? '#0D6EFD') ?>" style="height:45px;width:60px;" id="pc">
                            <input type="text" class="form-control" id="pc-hex" value="<?= e($s['primary_color'] ?? '#0D6EFD') ?>" style="font-family:monospace;" oninput="document.getElementById('pc').value=this.value">
                        </div>
                        <small class="text-muted">Buttons, links, accent elements</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Secondary Color</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" name="secondary_color" class="form-control form-control-color" value="<?= e($s['secondary_color'] ?? '#20C997') ?>" style="height:45px;width:60px;" id="sc">
                            <input type="text" class="form-control" id="sc-hex" value="<?= e($s['secondary_color'] ?? '#20C997') ?>" style="font-family:monospace;" oninput="document.getElementById('sc').value=this.value">
                        </div>
                        <small class="text-muted">Highlights, badges, secondary accents</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Preview</label>
                        <div id="color-preview" style="padding:14px;border-radius:12px;background:var(--admin-bg);border:1px solid #dee2e6;">
                            <button type="button" class="btn btn-sm w-100 mb-1" id="prev-btn" style="background:<?= e($s['primary_color'] ?? '#0D6EFD') ?>;color:#fff;border:none;">Primary Button</button>
                            <span class="badge" id="prev-badge" style="background:<?= e($s['secondary_color'] ?? '#20C997') ?>;color:#fff;font-size:.8rem;padding:.4em .8em;">Secondary Badge</span>
                        </div>
                    </div>
                    <div class="col-12 mt-2">
                        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Appearance</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- ═══════════════════════════ ADVANCED ═══════════════════════════ -->
        <?php elseif ($tab === 'advanced'): ?>
        <div class="dash-card">
            <div class="settings-section-title"><i class="fas fa-cog me-1"></i> Advanced Settings</div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Meta Description <small class="text-muted">(SEO)</small></label>
                        <textarea name="meta_description" class="form-control" rows="2" placeholder="Short description shown in Google search results (150-160 chars recommended)"><?= e($s['meta_description'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Google Maps Embed URL</label>
                        <input type="text" name="google_maps_embed" class="form-control" value="<?= e($s['google_maps_embed'] ?? '') ?>" placeholder="https://www.google.com/maps/embed?pb=...">
                        <small class="text-muted">From Google Maps → Share → Embed a map → copy the <code>src</code> URL</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Appointment Notification Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="appointment_email" class="form-control" value="<?= e($s['appointment_email'] ?? '') ?>">
                        </div>
                        <small class="text-muted">Receives new appointment notification emails</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold d-block">Notification Toggles</label>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="notifyOnBooking" name="notify_on_booking" value="1" <?= (($s['notify_on_booking'] ?? '1') === '1') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="notifyOnBooking">Send notification on new booking</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="notifyOnStatus" name="notify_on_status_change" value="1" <?= (($s['notify_on_status_change'] ?? '1') === '1') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="notifyOnStatus">Send patient notification on status change</label>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Mail From Email</label>
                        <input type="email" name="mail_from_email" class="form-control" value="<?= e($s['mail_from_email'] ?? '') ?>" placeholder="booking@yourdomain.com">
                        <small class="text-muted">Used in From/Return-Path when sending notifications.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Mail From Name</label>
                        <input type="text" name="mail_from_name" class="form-control" value="<?= e($s['mail_from_name'] ?? '') ?>" placeholder="JMedi Appointments">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Mail Transport</label>
                        <select name="mail_transport" class="form-select">
                            <option value="php_mail" <?= (($s['mail_transport'] ?? 'php_mail') === 'php_mail') ? 'selected' : '' ?>>PHP mail()</option>
                            <option value="smtp" <?= (($s['mail_transport'] ?? '') === 'smtp') ? 'selected' : '' ?>>SMTP (cPanel mailbox auth)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control" value="<?= e($s['smtp_host'] ?? '') ?>" placeholder="mail.yourdomain.com">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">SMTP Port</label>
                        <input type="number" name="smtp_port" class="form-control" value="<?= e($s['smtp_port'] ?? '587') ?>" placeholder="587">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">SMTP Encryption</label>
                        <select name="smtp_encryption" class="form-select">
                            <option value="tls" <?= (($s['smtp_encryption'] ?? 'tls') === 'tls') ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= (($s['smtp_encryption'] ?? '') === 'ssl') ? 'selected' : '' ?>>SSL</option>
                            <option value="none" <?= (($s['smtp_encryption'] ?? '') === 'none') ? 'selected' : '' ?>>None</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">SMTP Username</label>
                        <input type="text" name="smtp_username" class="form-control" value="<?= e($s['smtp_username'] ?? '') ?>" placeholder="booking@yourdomain.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">SMTP Password</label>
                        <input type="password" name="smtp_password" class="form-control" value="<?= e($s['smtp_password'] ?? '') ?>" autocomplete="new-password">
                    </div>

                    <div class="col-12 mt-3">
                        <div class="p-3 rounded-3" style="border:1px solid #dbe7f5;background:#f8fbff;">
                            <h6 class="fw-bold mb-2"><i class="far fa-paper-plane me-1"></i>Test Email</h6>
                            <p class="text-muted mb-3">Send a test email using your current sender/transport settings to verify delivery.</p>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">Test Email Recipient</label>
                                    <input type="email" name="test_email_recipient" class="form-control" placeholder="Enter email address" value="<?= e($testRecipient) ?>">
                                </div>
                                <div class="col-md-4 d-grid">
                                    <button type="submit" name="settings_action" value="send_test_email" class="btn btn-outline-success"><i class="far fa-envelope me-1"></i>Send Test Email</button>
                                </div>
                                <div class="col-12">
                                    <div class="small text-muted mt-1">
                                        <ul class="mb-0 ps-3">
                                            <li>Save SMTP/Sender settings before sending a test.</li>
                                            <li>For cPanel, host is often <code>mail.yourdomain.com</code>.</li>
                                            <li>Port <code>465</code> usually uses SSL, Port <code>587</code> uses TLS.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upload directory status -->
                    <div class="col-12 mt-3">
                        <div class="settings-section-title"><i class="fas fa-folder-open me-1"></i> Upload Directory Status</div>
                        <?php
                        $checkDirs = [
                            'assets/uploads'       => __DIR__ . '/../assets/uploads',
                            'assets/uploads/logos'  => __DIR__ . '/../assets/uploads/logos',
                            'assets/uploads/doctors'=> __DIR__ . '/../assets/uploads/doctors',
                            'assets/uploads/blog'   => __DIR__ . '/../assets/uploads/blog',
                            'assets/uploads/slides' => __DIR__ . '/../assets/uploads/slides',
                        ];
                        ?>
                        <div class="table-responsive">
                        <table class="table table-sm table-bordered" style="font-size:.85rem;">
                            <thead class="table-light"><tr><th>Directory</th><th>Exists</th><th>Writable</th><th>Files</th></tr></thead>
                            <tbody>
                            <?php foreach ($checkDirs as $label => $path): ?>
                            <?php
                                $exists   = is_dir($path);
                                $writable = $exists && is_writable($path);
                                $count    = $exists ? count(array_filter(scandir($path), fn($f)=>!in_array($f,['.','..','.gitkeep']))) : '-';
                            ?>
                            <tr>
                                <td><code><?= $label ?></code></td>
                                <td><?= $exists ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>' ?></td>
                                <td><?= $writable ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>' ?></td>
                                <td><?= $count ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <small class="text-muted">If directories show "No" for Writable, ask your hosting provider to chmod 755 or 775 on those folders.</small>
                    </div>

                    <div class="col-12 mt-2">
                        <button type="submit" name="settings_action" value="save_settings" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Advanced</button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

    </div><!-- /col -->
</div><!-- /row -->

<script>
/* Live logo preview before upload */
function previewLogo(input, field) {
    const file = input.files[0];
    if (!file) return;
    const nameEl  = document.getElementById('file-name-' + field);
    const btnEl   = document.getElementById('upload-btn-' + field);
    const imgSlot = document.getElementById('preview-img-' + field);
    if (nameEl) { nameEl.textContent = file.name; nameEl.style.display = 'block'; }
    if (btnEl)  { btnEl.style.display = 'block'; }
    const reader = new FileReader();
    reader.onload = e => {
        if (imgSlot) {
            if (imgSlot.tagName === 'IMG') {
                imgSlot.src = e.target.result;
            } else {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.maxHeight = '80px';
                img.style.maxWidth  = '100%';
                img.style.objectFit = 'contain';
                img.id = 'preview-img-' + field;
                imgSlot.replaceWith(img);
            }
        }
    };
    reader.readAsDataURL(file);
}

/* ── WhatsApp message template ── */
const WA_DEFAULT = "Hi {site_name}! 👋\n\nI visited your website and I am interested in your healthcare services. Could you please provide me with more information?\n\nThank you!";
const WA_SITE_NAME = <?= json_encode($s['site_name'] ?? 'JMedi') ?>;

function updateWAPreview() {
    const ta    = document.getElementById('waMsg');
    const prev  = document.getElementById('waPreview');
    const count = document.getElementById('waCharCount');
    if (!ta) return;
    const msg = ta.value || WA_DEFAULT;
    const rendered = msg.replace(/\{site_name\}/g, WA_SITE_NAME);
    if (prev)  prev.textContent  = rendered;
    if (count) {
        const len = rendered.length;
        count.textContent = len + ' chars';
        count.style.color = len > 300 ? '#dc2626' : len > 200 ? '#d97706' : '#15803d';
    }
}
function resetWAMsg() {
    const ta = document.getElementById('waMsg');
    if (ta) { ta.value = WA_DEFAULT; updateWAPreview(); }
}
document.addEventListener('DOMContentLoaded', () => { updateWAPreview(); });

/* Color picker ↔ hex input sync + live preview */
const pcInput  = document.getElementById('pc');
const pcHex    = document.getElementById('pc-hex');
const scInput  = document.getElementById('sc');
const scHex    = document.getElementById('sc-hex');
const prevBtn  = document.getElementById('prev-btn');
const prevBadge= document.getElementById('prev-badge');

function updatePreview() {
    if (prevBtn  && pcInput) prevBtn.style.background  = pcInput.value;
    if (prevBadge && scInput) prevBadge.style.background = scInput.value;
}
if (pcInput)  pcInput.addEventListener('input',  () => { if(pcHex) pcHex.value = pcInput.value; updatePreview(); });
if (scInput)  scInput.addEventListener('input',  () => { if(scHex) scHex.value = scInput.value; updatePreview(); });

</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
