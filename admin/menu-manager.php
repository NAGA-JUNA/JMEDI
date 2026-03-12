<?php
$pageTitle = 'Menu Manager';
require_once __DIR__ . '/../includes/admin_header.php';
requirePermission('menu_manager');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['menu_name'] ?? '');
        $link = trim($_POST['menu_link'] ?? '');
        $icon = trim($_POST['menu_icon'] ?? '');
        if ($name && $link) {
            $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(menu_order),0) FROM menus")->fetchColumn();
            $stmt = $pdo->prepare("INSERT INTO menus (menu_name, menu_link, menu_icon, menu_order, status) VALUES (:name, :link, :icon, :order, 1)");
            $stmt->execute([':name' => $name, ':link' => $link, ':icon' => $icon, ':order' => $maxOrder + 1]);
            $success = 'Menu item added successfully.';
        } else {
            $error = 'Name and link are required.';
        }
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['menu_name'] ?? '');
        $link = trim($_POST['menu_link'] ?? '');
        $icon = trim($_POST['menu_icon'] ?? '');
        if ($id && $name && $link) {
            $stmt = $pdo->prepare("UPDATE menus SET menu_name=:name, menu_link=:link, menu_icon=:icon WHERE id=:id");
            $stmt->execute([':name' => $name, ':link' => $link, ':icon' => $icon, ':id' => $id]);
            $success = 'Menu item updated.';
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM menus WHERE id=:id")->execute([':id' => $id]);
            $success = 'Menu item deleted.';
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE menus SET status = CASE WHEN status=1 THEN 0 ELSE 1 END WHERE id=:id")->execute([':id' => $id]);
            $success = 'Status updated.';
        }
    }

    if ($action === 'reorder') {
        $order = json_decode($_POST['order'] ?? '[]', true);
        if (is_array($order)) {
            $stmt = $pdo->prepare("UPDATE menus SET menu_order=:ord WHERE id=:id");
            foreach ($order as $i => $id) {
                $stmt->execute([':ord' => $i + 1, ':id' => (int)$id]);
            }
            $success = 'Menu order saved.';
        }
    }
}

$menus = $pdo->query("SELECT * FROM menus ORDER BY menu_order ASC, id ASC")->fetchAll();
?>

<?php if ($success): ?><div class="alert alert-success alert-dismissible fade show" style="border-radius:10px;border:none;"><i class="fas fa-check-circle me-2"></i><?= e($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show" style="border-radius:10px;border:none;"><i class="fas fa-exclamation-circle me-2"></i><?= e($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="dash-card">
            <div class="card-header-row">
                <h6><i class="fas fa-plus-circle me-2" style="color:var(--admin-accent);"></i>Add Menu Item</h6>
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Menu Name</label>
                    <input type="text" name="menu_name" class="form-control" placeholder="e.g. About Us" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Link URL</label>
                    <input type="text" name="menu_link" class="form-control" placeholder="e.g. /public/about.php" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Icon Class <small class="text-muted">(Font Awesome)</small></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fas fa-icons"></i></span>
                        <input type="text" name="menu_icon" class="form-control" placeholder="e.g. fa-info-circle">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100" style="border-radius:10px;"><i class="fas fa-plus me-2"></i>Add Item</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="dash-card">
            <div class="card-header-row">
                <h6><i class="fas fa-bars me-2" style="color:var(--admin-accent);"></i>Menu Items</h6>
                <div class="card-actions">
                    <button class="tab-btn active" id="saveOrderBtn" onclick="saveMenuOrder()" style="display:none;">
                        <i class="fas fa-save me-1"></i>Save Order
                    </button>
                    <span class="badge" style="background:var(--admin-primary);color:#fff;border-radius:8px;padding:0.4em 0.8em;"><?= count($menus) ?> items</span>
                </div>
            </div>
            <p class="text-muted small mb-3"><i class="fas fa-arrows-alt me-1"></i>Drag and drop items to reorder the navigation menu</p>

            <div id="menuList">
                <?php foreach ($menus as $menu): ?>
                <div class="menu-item" data-id="<?= $menu['id'] ?>">
                    <div class="menu-item-inner">
                        <div class="menu-drag-handle"><i class="fas fa-grip-vertical"></i></div>
                        <div class="menu-icon-preview">
                            <i class="fas <?= e($menu['menu_icon'] ?: 'fa-link') ?>"></i>
                        </div>
                        <div class="menu-details">
                            <span class="menu-name"><?= e($menu['menu_name']) ?></span>
                            <span class="menu-link-text"><?= e($menu['menu_link']) ?></span>
                        </div>
                        <div class="menu-actions">
                            <form method="POST" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $menu['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $menu['status'] ? 'menu-status-on' : 'menu-status-off' ?>" title="<?= $menu['status'] ? 'Active' : 'Disabled' ?>">
                                    <i class="fas <?= $menu['status'] ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                                </button>
                            </form>
                            <button type="button" class="btn btn-sm menu-edit-btn" onclick="editMenuItem(<?= $menu['id'] ?>, '<?= e(addslashes($menu['menu_name'])) ?>', '<?= e(addslashes($menu['menu_link'])) ?>', '<?= e(addslashes($menu['menu_icon'])) ?>')" title="Edit">
                                <i class="fas fa-pen"></i>
                            </button>
                            <form method="POST" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $menu['id'] ?>">
                                <button type="button" class="btn btn-sm menu-delete-btn" title="Delete" data-delete-trigger data-delete-label="the '<?= e(addslashes($menu['menu_name'])) ?>' menu item">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (empty($menus)): ?>
            <p class="text-center text-muted py-4">No menu items. Add one using the form.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="editMenuModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius:14px;">
            <div class="modal-header border-0" style="background:var(--admin-primary);color:#fff;border-radius:14px 14px 0 0;">
                <h6 class="modal-title fw-bold"><i class="fas fa-pen me-2"></i>Edit Menu Item</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST" id="editMenuForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="editMenuId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Menu Name</label>
                        <input type="text" name="menu_name" id="editMenuName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Link URL</label>
                        <input type="text" name="menu_link" id="editMenuLink" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Icon Class</label>
                        <input type="text" name="menu_icon" id="editMenuIcon" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary w-100" style="border-radius:10px;">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<form method="POST" id="reorderForm" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="reorder">
    <input type="hidden" name="order" id="reorderData">
</form>

<style>
.menu-item {
    margin-bottom: 8px;
    cursor: grab;
    transition: all 0.2s;
}
.menu-item.dragging {
    opacity: 0.5;
    transform: scale(0.98);
}
.menu-item.drag-over {
    border-top: 2px solid var(--admin-accent);
}
.menu-item-inner {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    transition: all 0.2s;
}
.menu-item-inner:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    border-color: var(--admin-accent);
}
.menu-drag-handle {
    color: var(--admin-text-muted);
    cursor: grab;
    padding: 4px;
    font-size: 1rem;
}
.menu-drag-handle:active { cursor: grabbing; }
.menu-icon-preview {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: rgba(34,197,94,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--admin-accent);
    font-size: 0.9rem;
    flex-shrink: 0;
}
.menu-details {
    flex: 1;
    min-width: 0;
}
.menu-name {
    display: block;
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--admin-text);
}
.menu-link-text {
    display: block;
    font-size: 0.75rem;
    color: var(--admin-text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.menu-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}
.menu-actions .btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    font-size: 0.8rem;
    border: 1px solid var(--admin-border);
    background: var(--admin-card);
    color: var(--admin-text-muted);
    transition: all 0.2s;
}
.menu-status-on { color: var(--admin-accent) !important; border-color: var(--admin-accent) !important; }
.menu-status-off { color: #ef4444 !important; border-color: #ef4444 !important; opacity: 0.6; }
.menu-edit-btn:hover { background: var(--admin-primary) !important; color: #fff !important; border-color: var(--admin-primary) !important; }
.menu-delete-btn:hover { background: #ef4444 !important; color: #fff !important; border-color: #ef4444 !important; }
</style>

<script>
function editMenuItem(id, name, link, icon) {
    document.getElementById('editMenuId').value = id;
    document.getElementById('editMenuName').value = name;
    document.getElementById('editMenuLink').value = link;
    document.getElementById('editMenuIcon').value = icon;
    new bootstrap.Modal(document.getElementById('editMenuModal')).show();
}

function saveMenuOrder() {
    const items = document.querySelectorAll('#menuList .menu-item');
    const order = Array.from(items).map(el => el.dataset.id);
    document.getElementById('reorderData').value = JSON.stringify(order);
    document.getElementById('reorderForm').submit();
}

(function() {
    const list = document.getElementById('menuList');
    if (!list) return;
    let dragItem = null;
    const saveBtn = document.getElementById('saveOrderBtn');

    list.addEventListener('dragstart', function(e) {
        dragItem = e.target.closest('.menu-item');
        if (dragItem) {
            dragItem.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        }
    });

    list.addEventListener('dragend', function() {
        if (dragItem) dragItem.classList.remove('dragging');
        list.querySelectorAll('.menu-item').forEach(el => el.classList.remove('drag-over'));
        dragItem = null;
    });

    list.addEventListener('dragover', function(e) {
        e.preventDefault();
        const target = e.target.closest('.menu-item');
        if (target && target !== dragItem) {
            list.querySelectorAll('.menu-item').forEach(el => el.classList.remove('drag-over'));
            target.classList.add('drag-over');
        }
    });

    list.addEventListener('drop', function(e) {
        e.preventDefault();
        const target = e.target.closest('.menu-item');
        if (target && target !== dragItem && dragItem) {
            const rect = target.getBoundingClientRect();
            const mid = rect.top + rect.height / 2;
            if (e.clientY < mid) {
                list.insertBefore(dragItem, target);
            } else {
                list.insertBefore(dragItem, target.nextSibling);
            }
            saveBtn.style.display = 'inline-block';
        }
        list.querySelectorAll('.menu-item').forEach(el => el.classList.remove('drag-over'));
    });

    list.querySelectorAll('.menu-item').forEach(function(el) {
        el.setAttribute('draggable', 'true');
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
