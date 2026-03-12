<?php
$pageTitle = 'Email Templates';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
requirePermission('settings');
require_once __DIR__ . '/../includes/admin_header.php';

$success = '';
$error = '';
$previewHtml = '';

$templates = getEmailTemplates($pdo);
$editId = (int)($_GET['edit'] ?? ($_POST['template_id'] ?? 0));
$editing = null;
foreach ($templates as $t) {
    if ((int)$t['id'] === $editId) {
        $editing = $t;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['template_action'] ?? '';

    if ($action === 'save_template') {
        $id = (int)($_POST['template_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $variables = trim($_POST['variables'] ?? '');
        $status = isset($_POST['status']) ? 1 : 0;

        if ($id && $subject !== '' && $body !== '') {
            $stmt = $pdo->prepare("UPDATE email_templates SET subject=:subject, body=:body, variables=:vars, status=:status, updated_at=CURRENT_TIMESTAMP WHERE id=:id");
            $stmt->execute([':subject' => $subject, ':body' => $body, ':vars' => $variables, ':status' => $status, ':id' => $id]);
            $success = 'Template updated successfully.';
            $templates = getEmailTemplates($pdo);
            foreach ($templates as $t) {
                if ((int)$t['id'] === $id) {
                    $editing = $t;
                    break;
                }
            }
        } else {
            $error = 'Template subject and body are required.';
            if ($editing) {
                $editing['subject'] = $subject;
                $editing['body'] = $body;
                $editing['variables'] = $variables;
                $editing['status'] = $status;
            }
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)($_POST['template_id'] ?? 0);
        $newStatus = ($_POST['status_value'] ?? '0') === '1' ? 1 : 0;
        if ($id) {
            $stmt = $pdo->prepare("UPDATE email_templates SET status=:status, updated_at=CURRENT_TIMESTAMP WHERE id=:id");
            $stmt->execute([':status' => $newStatus, ':id' => $id]);
            $success = $newStatus === 1 ? 'Template enabled.' : 'Template disabled.';
            $templates = getEmailTemplates($pdo);
            foreach ($templates as $t) {
                if ((int)$t['id'] === $id) {
                    $editing = $t;
                    break;
                }
            }
        }
    } elseif ($action === 'preview_template') {
        $id = (int)($_POST['template_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $siteName = getSetting($pdo, 'site_name', 'JMedi');
        $siteLogo = getSetting($pdo, 'frontend_logo', '');

        if ($id && $editing) {
            $editing['subject'] = $subject;
            $editing['body'] = $body;
            $editing['variables'] = trim($_POST['variables'] ?? '');
            $editing['status'] = isset($_POST['status']) ? 1 : 0;
        }

        $sampleJson = trim($_POST['preview_variables'] ?? '');
        $vars = [
            'patient_name' => 'John Doe',
            'doctor_name' => 'Dr Andrew Thompson',
            'appointment_date' => date('Y-m-d'),
            'appointment_time' => date('H:i'),
            'clinic_name' => $siteName,
            'clinic_logo' => $siteLogo,
            'year' => date('Y'),
        ];
        if ($sampleJson !== '') {
            $decoded = json_decode($sampleJson, true);
            if (is_array($decoded)) {
                $vars = array_merge($vars, $decoded);
            }
        }

        $renderedBody = renderTemplateVariables($body, $vars);
        $renderedSubject = renderTemplateVariables($subject, $vars);
        $isFullHtml = stripos($renderedBody, '<html') !== false;
        $previewHtmlBody = $isFullHtml
            ? $renderedBody
            : buildEmailLayout($renderedBody, (string)($vars['clinic_name'] ?? $siteName));

        $previewHtml = '<h6 class="mb-2">Subject: ' . e($renderedSubject) . '</h6>' . $previewHtmlBody;
        $success = 'Preview generated.';
    } elseif ($action === 'apply_default_template' && $editing) {
        $defaultTemplate = getDefaultEmailTemplate($editing['template_name'] ?? '');
        if ($defaultTemplate) {
            $editing['subject'] = $defaultTemplate['subject'] ?? $editing['subject'];
            $editing['body'] = $defaultTemplate['body'] ?? $editing['body'];
            $editing['variables'] = $defaultTemplate['variables'] ?? ($editing['variables'] ?? '');
            $success = 'Default template loaded in editor. Click Save Changes to apply.';
        } else {
            $error = 'No default template available for this email.';
        }
    }
}
?>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="dash-card">
            <h5 class="mb-3"><i class="fas fa-envelope-open-text me-2"></i>Email Templates</h5>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Name</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($templates as $tmp): ?>
                        <tr>
                            <td><code><?= e($tmp['template_name']) ?></code></td>
                            <td><?= (int)$tmp['status'] === 1 ? '<span class="badge bg-success">Enabled</span>' : '<span class="badge bg-secondary">Disabled</span>' ?></td>
                            <td class="text-end">
                                <form method="POST" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="template_id" value="<?= (int)$tmp['id'] ?>">
                                    <input type="hidden" name="status_value" value="<?= (int)$tmp['status'] === 1 ? '0' : '1' ?>">
                                    <button class="btn btn-sm <?= (int)$tmp['status'] === 1 ? 'btn-outline-secondary' : 'btn-outline-success' ?>" type="submit" name="template_action" value="toggle_status">
                                        <?= (int)$tmp['status'] === 1 ? 'Disable' : 'Enable' ?>
                                    </button>
                                </form>
                                <a class="btn btn-sm btn-outline-primary ms-1" href="/admin/email-templates.php?edit=<?= (int)$tmp['id'] ?>">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="dash-card">
            <h5 class="mb-3"><i class="fas fa-pen me-2"></i><?= $editing ? 'Edit Template' : 'Select template to edit' ?></h5>
            <?php if ($editing): ?>
            <?php $defaultTemplate = getDefaultEmailTemplate($editing['template_name'] ?? ''); ?>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="template_id" value="<?= (int)$editing['id'] ?>">

                <div class="mb-2">
                    <label class="form-label fw-semibold">Template Name</label>
                    <input class="form-control" value="<?= e($editing['template_name']) ?>" disabled>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold">Subject</label>
                    <input class="form-control" name="subject" value="<?= e($editing['subject']) ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold">Variables (comma separated)</label>
                    <input class="form-control" name="variables" value="<?= e($editing['variables']) ?>" placeholder="patient_name,doctor_name,appointment_date,appointment_time,clinic_name,clinic_logo,year">
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold">Body (HTML) <small class="text-muted">(pure HTML, inline CSS, table-based)</small></label>
                    <textarea class="form-control" name="body" rows="16" style="font-family:monospace;" required><?= e($editing['body']) ?></textarea>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="status" value="1" <?= (int)$editing['status'] === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label">Enable Template</label>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-primary" type="submit" name="template_action" value="save_template"><i class="fas fa-save me-1"></i>Save Changes</button>
                    <button class="btn btn-outline-secondary" type="submit" name="template_action" value="preview_template"><i class="fas fa-eye me-1"></i>Preview Email</button>
                    <?php if ($defaultTemplate): ?>
                    <button class="btn btn-outline-info" type="submit" name="template_action" value="apply_default_template"><i class="fas fa-magic me-1"></i>Load Default Template</button>
                    <?php endif; ?>
                </div>

                <div class="mt-3">
                    <label class="form-label">Preview test values JSON (optional)</label>
                    <textarea class="form-control" name="preview_variables" rows="3" placeholder='{"patient_name":"Alice","doctor_name":"Dr Andrew","appointment_date":"2026-03-12","appointment_time":"10:30 AM","clinic_name":"JMedi","clinic_logo":"https://example.com/logo.png"}'></textarea>
                </div>
            </form>
            <?php endif; ?>
        </div>

        <?php if ($previewHtml): ?>
        <div class="dash-card mt-3">
            <div class="preview-wrapper"><?= $previewHtml ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
