<?php
$pageTitle = 'Database Tools';
require_once __DIR__ . '/../includes/admin_header.php';
requireSuperAdmin();

$table = $_GET['table'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$search = $_GET['search'] ?? '';
$perPage = 50;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['db_action'] ?? '';

    if ($action === 'export_csv' && !empty($_POST['export_table'])) {
        $exportTable = $_POST['export_table'];
        $allowedTables = array_column($pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name")->fetchAll(), 'table_name');
        if (!in_array($exportTable, $allowedTables)) {
            die('Invalid table');
        }

        $columns = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($exportTable) . " ORDER BY ordinal_position")->fetchAll(PDO::FETCH_COLUMN);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $exportTable . '_' . date('Y-m-d_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $columns);

        $stmt = $pdo->query("SELECT * FROM `" . $exportTable . "`");
        while ($row = $stmt->fetch()) {
            fputcsv($out, array_values($row));
        }
        fclose($out);
        exit;
    }

    if ($action === 'db_backup') {
        $tmpFile = tempnam(sys_get_temp_dir(), 'dbbackup_') . '.sql';
        $h    = 'localhost';
        $p    = 3306;
        $dbn  = 'svaobtfy_jmedi';
        $u    = 'svaobtfy_jmedi';
        $pass = 'sa1T4HXr@7602626264';

        $cmd = sprintf(
            'mysqldump -h %s -P %s -u %s -p%s %s > %s 2>&1',
            escapeshellarg($h),
            escapeshellarg($p),
            escapeshellarg($u),
            escapeshellarg($pass),
            escapeshellarg($dbn),
            escapeshellarg($tmpFile)
        );
        exec($cmd, $output, $returnCode);

        if ($returnCode === 0 && file_exists($tmpFile) && filesize($tmpFile) > 0) {
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="db_backup_' . date('Y-m-d_His') . '.sql"');
            header('Content-Length: ' . filesize($tmpFile));
            readfile($tmpFile);
            unlink($tmpFile);
            exit;
        } else {
            @unlink($tmpFile);
            $backupError = 'Backup failed. ' . implode("\n", $output);
        }
    }
}

$tables = $pdo->query("
    SELECT table_name AS tablename,
           (SELECT COUNT(*) FROM information_schema.columns c WHERE c.table_schema = DATABASE() AND c.table_name = t.table_name) AS column_count
    FROM information_schema.tables t
    WHERE t.table_schema = DATABASE() AND t.table_type = 'BASE TABLE'
    ORDER BY t.table_name
")->fetchAll();

foreach ($tables as &$t) {
    try {
        $t['row_count'] = (int)$pdo->query("SELECT COUNT(*) FROM `" . $t['tablename'] . "`")->fetchColumn();
    } catch (Exception $e) {
        $t['row_count'] = 0;
    }
}
unset($t);

$tableData = null;
$tableColumns = [];
$totalRows = 0;
$totalPages = 1;

if ($table) {
    $allowedTables = array_column($tables, 'tablename');
    if (in_array($table, $allowedTables)) {
        $tableColumns = $pdo->query("
            SELECT column_name, data_type, is_nullable, column_default, character_maximum_length
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($table) . "
            ORDER BY ordinal_position
        ")->fetchAll();

        $countSql = "SELECT COUNT(*) FROM `$table`";
        $dataSql  = "SELECT * FROM `$table`";
        $params   = [];

        if ($search !== '') {
            $textCols = array_filter($tableColumns, function($c) {
                return in_array($c['data_type'], ['varchar', 'text', 'char', 'tinytext', 'mediumtext', 'longtext']);
            });
            if (!empty($textCols)) {
                $searchClauses = [];
                $i = 0;
                foreach ($textCols as $col) {
                    $paramName = ':search' . $i;
                    $searchClauses[] = "`" . $col['column_name'] . "` LIKE " . $paramName;
                    $params[$paramName] = '%' . $search . '%';
                    $i++;
                }
                $whereClause = " WHERE " . implode(' OR ', $searchClauses);
                $countSql .= $whereClause;
                $dataSql  .= $whereClause;
            }
        }

        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRows  = (int)$countStmt->fetchColumn();
        $totalPages = max(1, ceil($totalRows / $perPage));
        $offset     = ($page - 1) * $perPage;

        $dataSql .= " LIMIT $perPage OFFSET $offset";
        $dataStmt = $pdo->prepare($dataSql);
        $dataStmt->execute($params);
        $tableData = $dataStmt->fetchAll();
    }
}
?>

<?php if (isset($backupError)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?= e($backupError) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-database me-2 text-muted"></i>Database Tools</h4>
        <p class="text-muted mb-0">Browse database tables and manage backups</p>
    </div>
    <form method="POST" style="display:inline;">
        <?= csrfField() ?>
        <input type="hidden" name="db_action" value="db_backup">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-download me-2"></i>Download DB Backup
        </button>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="dash-stat-card">
            <div class="stat-label">Total Tables</div>
            <div class="stat-number"><?= count($tables) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dash-stat-card">
            <div class="stat-label">Total Rows</div>
            <div class="stat-number"><?= number_format(array_sum(array_column($tables, 'row_count'))) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dash-stat-card">
            <div class="stat-label">Total Columns</div>
            <div class="stat-number"><?= array_sum(array_column($tables, 'column_count')) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dash-stat-card">
            <div class="stat-label">Database</div>
            <div class="stat-number" style="font-size:1rem;"><?= e($dbname ?? 'postgres') ?></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-<?= $table ? '3' : '12' ?>">
        <div class="table-card">
            <h5><i class="fas fa-list me-2"></i>Tables</h5>
            <div class="list-group list-group-flush">
                <?php foreach ($tables as $t): ?>
                <a href="?table=<?= urlencode($t['tablename']) ?>"
                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $table === $t['tablename'] ? 'active' : '' ?>"
                   style="border-radius:8px;margin-bottom:2px;font-size:0.88rem;<?= $table === $t['tablename'] ? 'background:var(--admin-primary);color:#fff;border-color:var(--admin-primary);' : '' ?>">
                    <span><i class="fas fa-table me-2 <?= $table === $t['tablename'] ? '' : 'text-muted' ?>"></i><?= e($t['tablename']) ?></span>
                    <span class="badge <?= $table === $t['tablename'] ? 'bg-light text-dark' : 'bg-secondary' ?>"><?= number_format($t['row_count']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if ($table && $tableData !== null): ?>
    <div class="col-lg-9">
        <div class="table-card mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">
                    <i class="fas fa-table me-2"></i><?= e($table) ?>
                    <small class="text-muted fw-normal">(<?= number_format($totalRows) ?> rows)</small>
                </h5>
                <div class="d-flex gap-2">
                    <form method="POST" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="db_action" value="export_csv">
                        <input type="hidden" name="export_table" value="<?= e($table) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-file-csv me-1"></i>Export CSV
                        </button>
                    </form>
                </div>
            </div>

            <div class="mb-3">
                <h6 class="fw-bold mb-2" style="font-size:0.85rem;"><i class="fas fa-columns me-1"></i>Columns (<?= count($tableColumns) ?>)</h6>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($tableColumns as $col): ?>
                    <span class="badge bg-light text-dark border" style="font-size:0.78rem;font-weight:500;" title="<?= e($col['data_type']) ?><?= $col['character_maximum_length'] ? '(' . $col['character_maximum_length'] . ')' : '' ?> | <?= $col['is_nullable'] === 'YES' ? 'nullable' : 'not null' ?><?= $col['column_default'] ? ' | default: ' . $col['column_default'] : '' ?>">
                        <?= e($col['column_name']) ?>
                        <small class="text-muted ms-1"><?= e($col['data_type']) ?><?= $col['character_maximum_length'] ? '(' . $col['character_maximum_length'] . ')' : '' ?></small>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <form method="get" class="mb-3">
                <input type="hidden" name="table" value="<?= e($table) ?>">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search text columns..." value="<?= e($search) ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if ($search): ?>
                    <a href="?table=<?= urlencode($table) ?>" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($search): ?>
            <div class="alert alert-info py-2 px-3 mb-3" style="font-size:0.85rem;">
                <i class="fas fa-filter me-1"></i>Showing results for "<strong><?= e($search) ?></strong>" — <?= number_format($totalRows) ?> match(es)
            </div>
            <?php endif; ?>

            <div class="table-responsive" style="max-height:600px;overflow:auto;">
                <table class="table table-sm table-hover table-bordered mb-0" style="font-size:0.82rem;">
                    <thead class="table-light" style="position:sticky;top:0;z-index:1;">
                        <tr>
                            <th style="width:40px;">#</th>
                            <?php foreach ($tableColumns as $col): ?>
                            <th style="white-space:nowrap;"><?= e($col['column_name']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tableData)): ?>
                        <tr>
                            <td colspan="<?= count($tableColumns) + 1 ?>" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                <?= $search ? 'No matching records found' : 'Table is empty' ?>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($tableData as $idx => $row): ?>
                        <tr>
                            <td class="text-muted"><?= ($page - 1) * $perPage + $idx + 1 ?></td>
                            <?php foreach ($tableColumns as $col): ?>
                            <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e((string)($row[$col['column_name']] ?? '')) ?>">
                                <?php
                                $val = $row[$col['column_name']] ?? null;
                                if ($val === null) {
                                    echo '<span class="text-muted fst-italic">NULL</span>';
                                } elseif (strlen((string)$val) > 100) {
                                    echo e(substr((string)$val, 0, 100)) . '…';
                                } else {
                                    echo e((string)$val);
                                }
                                ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?table=<?= urlencode($table) ?>&page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php
                    $startP = max(1, $page - 3);
                    $endP = min($totalPages, $page + 3);
                    if ($startP > 1): ?>
                    <li class="page-item"><a class="page-link" href="?table=<?= urlencode($table) ?>&page=1&search=<?= urlencode($search) ?>">1</a></li>
                    <?php if ($startP > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <?php endif; ?>
                    <?php for ($p = $startP; $p <= $endP; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?table=<?= urlencode($table) ?>&page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
                    </li>
                    <?php endfor; ?>
                    <?php if ($endP < $totalPages): ?>
                    <?php if ($endP < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <li class="page-item"><a class="page-link" href="?table=<?= urlencode($table) ?>&page=<?= $totalPages ?>&search=<?= urlencode($search) ?>"><?= $totalPages ?></a></li>
                    <?php endif; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?table=<?= urlencode($table) ?>&page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
                <div class="text-center mt-2" style="font-size:0.78rem;color:var(--admin-text-muted);">
                    Page <?= $page ?> of <?= $totalPages ?> — Showing <?= ($page - 1) * $perPage + 1 ?>–<?= min($page * $perPage, $totalRows) ?> of <?= number_format($totalRows) ?> rows
                </div>
            </nav>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
