<?php
$pageTitle = 'Site Backup';
require_once __DIR__ . '/../includes/admin_header.php';
requireSuperAdmin();

$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$message = '';
$messageType = '';

$db_url = getenv('DATABASE_URL');
if ($db_url) {
    $parsed = parse_url($db_url);
    $pgHost = $parsed['host'] ?? 'localhost';
    $pgPort = $parsed['port'] ?? 5432;
    $pgDb = ltrim($parsed['path'] ?? '', '/');
    $pgUser = $parsed['user'] ?? '';
    $pgPass = $parsed['pass'] ?? '';
} else {
    $pgHost = getenv('PGHOST') ?: 'localhost';
    $pgPort = getenv('PGPORT') ?: 5432;
    $pgDb = getenv('PGDATABASE') ?: 'postgres';
    $pgUser = getenv('PGUSER') ?: 'postgres';
    $pgPass = getenv('PGPASSWORD') ?: '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'db_backup') {
            $filename = 'db_backup_' . date('Y-m-d_His') . '.sql';
            $filepath = $backupDir . '/' . $filename;

            putenv("PGPASSWORD=$pgPass");
            $cmd = sprintf(
                'pg_dump -h %s -p %s -U %s -d %s -F p > %s 2>&1',
                escapeshellarg($pgHost),
                escapeshellarg($pgPort),
                escapeshellarg($pgUser),
                escapeshellarg($pgDb),
                escapeshellarg($filepath)
            );
            exec($cmd, $output, $returnCode);

            if ($returnCode === 0 && file_exists($filepath) && filesize($filepath) > 0) {
                header('Content-Type: application/sql');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($filepath));
                readfile($filepath);
                unlink($filepath);
                exit;
            } else {
                $message = 'Database backup failed. Error: ' . implode("\n", $output);
                $messageType = 'danger';
            }

        } elseif ($action === 'full_backup') {
            $filename = 'full_backup_' . date('Y-m-d_His') . '.zip';
            $filepath = $backupDir . '/' . $filename;
            $rootDir = realpath(__DIR__ . '/..');

            $sqlFile = $backupDir . '/db_dump_' . date('Y-m-d_His') . '.sql';
            putenv("PGPASSWORD=$pgPass");
            $cmd = sprintf(
                'pg_dump -h %s -p %s -U %s -d %s -F p > %s 2>&1',
                escapeshellarg($pgHost),
                escapeshellarg($pgPort),
                escapeshellarg($pgUser),
                escapeshellarg($pgDb),
                escapeshellarg($sqlFile)
            );
            exec($cmd, $output, $returnCode);

            $dbOk = ($returnCode === 0 && file_exists($sqlFile) && filesize($sqlFile) > 0);

            $zip = new ZipArchive();
            if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $dirs = ['public', 'assets', 'includes', 'admin', 'database'];
                foreach ($dirs as $dir) {
                    $dirPath = $rootDir . '/' . $dir;
                    if (is_dir($dirPath)) {
                        $files = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
                            RecursiveIteratorIterator::LEAVES_ONLY
                        );
                        foreach ($files as $file) {
                            if (!$file->isDir()) {
                                $filePath = $file->getRealPath();
                                $relativePath = $dir . '/' . substr($filePath, strlen($dirPath) + 1);
                                $zip->addFile($filePath, $relativePath);
                            }
                        }
                    }
                }

                $rootFiles = ['router.php', 'main.py', 'pyproject.toml', 'package.json'];
                foreach ($rootFiles as $rf) {
                    $rfPath = $rootDir . '/' . $rf;
                    if (file_exists($rfPath)) {
                        $zip->addFile($rfPath, $rf);
                    }
                }

                if ($dbOk) {
                    $zip->addFile($sqlFile, 'database_dump.sql');
                }

                $zip->close();

                if ($dbOk && file_exists($sqlFile)) {
                    unlink($sqlFile);
                }

                if (file_exists($filepath)) {
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    header('Content-Length: ' . filesize($filepath));
                    readfile($filepath);
                    unlink($filepath);
                    exit;
                } else {
                    $message = 'Failed to create ZIP archive.';
                    $messageType = 'danger';
                }
            } else {
                $message = 'Failed to create ZIP archive.';
                $messageType = 'danger';
                if ($dbOk && file_exists($sqlFile)) {
                    unlink($sqlFile);
                }
            }

        } elseif ($action === 'cleanup') {
            $cleaned = 0;
            $backupFiles = glob($backupDir . '/*');
            foreach ($backupFiles as $bf) {
                if (is_file($bf)) {
                    unlink($bf);
                    $cleaned++;
                }
            }
            $message = "Cleaned up $cleaned backup file(s).";
            $messageType = 'success';
        }
    }
}

$existingBackups = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/*.{sql,zip}', GLOB_BRACE);
    if ($files) {
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        foreach ($files as $f) {
            $existingBackups[] = [
                'name' => basename($f),
                'size' => filesize($f),
                'date' => date('Y-m-d H:i:s', filemtime($f)),
                'type' => pathinfo($f, PATHINFO_EXTENSION) === 'zip' ? 'Full Backup' : 'DB Backup',
            ];
        }
    }
}

function formatFileSize(int $bytes): string {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
?>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
    <?= e($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Site Backup</h4>
        <p class="text-muted mb-0" style="font-size:0.9rem;">Download database and full site backups</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="form-card h-100">
            <div class="text-center mb-3">
                <div style="width:64px;height:64px;border-radius:16px;background:rgba(13,148,136,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                    <i class="fas fa-database" style="font-size:1.5rem;color:#0d9488;"></i>
                </div>
                <h5 class="fw-bold mb-1">Database Backup</h5>
                <p class="text-muted" style="font-size:0.85rem;">Download a PostgreSQL dump of the entire database as a .sql file</p>
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="db_backup">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-download me-2"></i> Download DB Backup (.sql)
                </button>
            </form>
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-card h-100">
            <div class="text-center mb-3">
                <div style="width:64px;height:64px;border-radius:16px;background:rgba(59,130,246,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                    <i class="fas fa-file-archive" style="font-size:1.5rem;color:#3b82f6;"></i>
                </div>
                <h5 class="fw-bold mb-1">Full Site Backup</h5>
                <p class="text-muted" style="font-size:0.85rem;">Download a ZIP archive containing all site files + database dump</p>
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="full_backup">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-file-archive me-2"></i> Download Full Backup (.zip)
                </button>
            </form>
            <div class="mt-2 text-center">
                <small class="text-muted">Includes: public/, assets/, includes/, admin/, database/ + DB dump</small>
            </div>
        </div>
    </div>
</div>

<div class="form-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">Backup History</h5>
        <?php if (!empty($existingBackups)): ?>
        <form method="POST" class="d-inline">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="cleanup">
            <button type="button" class="btn btn-outline-danger btn-sm" data-delete-trigger data-delete-label="all stored backup files">
                <i class="fas fa-trash me-1"></i> Clean Up
            </button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (empty($existingBackups)): ?>
    <div class="text-center py-4">
        <i class="fas fa-inbox text-muted" style="font-size:2rem;"></i>
        <p class="text-muted mt-2 mb-0">No backup files found on server. Backups are downloaded directly to your browser.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Filename</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($existingBackups as $bk): ?>
                <tr>
                    <td>
                        <i class="fas <?= $bk['type'] === 'Full Backup' ? 'fa-file-archive text-primary' : 'fa-database text-success' ?> me-2"></i>
                        <?= e($bk['name']) ?>
                    </td>
                    <td><span class="badge <?= $bk['type'] === 'Full Backup' ? 'bg-primary' : 'bg-success' ?>"><?= $bk['type'] ?></span></td>
                    <td><?= formatFileSize($bk['size']) ?></td>
                    <td><?= e($bk['date']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
