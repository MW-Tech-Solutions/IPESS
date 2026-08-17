<?php
$pageTitle = 'Manage Sidebar Assignments';
$pageSubtitle = 'Remove page/menu assignments from roles to revoke sidebar access.';

require_once 'db.php';

$msg     = '';
$msgType = 'success';

// --- Handle DELETE action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id      = (int)($_POST['record_id'] ?? 0);
    $table   = trim($_POST['record_table'] ?? '');

    // Only allow deletion from these two tables
    $allowedTables = ['right_page_main_menus', 'pesonal_right_page_main_menus'];
    if ($id > 0 && in_array($table, $allowedTables, true) && $pdo) {
        try {
            $pdo->beginTransaction();
            
            // If deleting a role assignment, find matching personal/cached assignments to clean them up too
            if ($table === 'right_page_main_menus') {
                $sel = $pdo->prepare("SELECT pageID, roleID FROM right_page_main_menus WHERE userpageID = ?");
                $sel->execute([$id]);
                $row = $sel->fetch(PDO::FETCH_ASSOC);
                
                if ($row) {
                    // Delete the main role assignment
                    $stmt = $pdo->prepare("DELETE FROM right_page_main_menus WHERE userpageID = ?");
                    $stmt->execute([$id]);
                    
                    // Cascade delete user-specific sidebar caches so changes take effect immediately
                    $stmt2 = $pdo->prepare("DELETE FROM pesonal_right_page_main_menus WHERE pageID = ? AND roleID = ?");
                    $stmt2->execute([$row['pageID'], $row['roleID']]);
                    
                    $msg     = 'Role assignment and all associated user sidebar caches removed successfully.';
                    $msgType = 'success';
                } else {
                    $msg     = 'Record not found or already removed.';
                    $msgType = 'warning';
                }
            } else {
                // Delete user-specific override directly
                $stmt = $pdo->prepare("DELETE FROM pesonal_right_page_main_menus WHERE userpageID = ?");
                $stmt->execute([$id]);
                if ($stmt->rowCount() > 0) {
                    $msg     = 'Personal sidebar override removed successfully.';
                    $msgType = 'success';
                } else {
                    $msg     = 'Record not found or already removed.';
                    $msgType = 'warning';
                }
            }
            
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $msg     = 'Database error: ' . htmlspecialchars($e->getMessage());
            $msgType = 'danger';
        }
    } else {
        $msg     = 'Invalid request.';
        $msgType = 'danger';
    }
}

// --- Filters ---
$filterRole  = trim($_GET['role'] ?? '');
$filterTable = trim($_GET['tbl'] ?? 'right_page_main_menus');
$filterSearch = trim($_GET['q'] ?? '');
if (!in_array($filterTable, ['right_page_main_menus', 'pesonal_right_page_main_menus'], true)) {
    $filterTable = 'right_page_main_menus';
}

// --- Fetch all roles from roles table ---
$roles = [];
if ($pdo) {
    try {
        $roles = $pdo->query("SELECT role_key, role_name FROM roles ORDER BY role_name")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

// --- Fetch assignments ---
$assignments = [];
$totalCount  = 0;
if ($pdo) {
    try {
        if ($filterTable === 'right_page_main_menus') {
            // Role-based assignments
            $where  = [];
            $params = [];
            if ($filterRole)   { $where[] = 'rm.roleID = ?';    $params[] = $filterRole; }
            if ($filterSearch) { $where[] = '(rm.menu_name LIKE ? OR rm.page_url LIKE ? OR rm.roleID LIKE ?)';
                $like = '%' . $filterSearch . '%';
                $params[] = $like; $params[] = $like; $params[] = $like;
            }
            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM right_page_main_menus rm $whereClause");
            $cntStmt->execute($params);
            $totalCount = (int)$cntStmt->fetchColumn();

            $selStmt = $pdo->prepare("
                SELECT rm.userpageID AS id, rm.roleID, rm.menu_name, rm.page_url, rm.page_status, rm.pageType, rm.keep_active,
                       COALESCE(r.role_name, rm.roleID) AS role_label
                FROM right_page_main_menus rm
                LEFT JOIN roles r ON r.role_key = rm.roleID
                $whereClause
                ORDER BY rm.roleID ASC, rm.menu_name ASC
            ");
            $selStmt->execute($params);
            $assignments = $selStmt->fetchAll(PDO::FETCH_ASSOC);

        } else {
            // Personal (user-specific) assignments
            $where  = [];
            $params = [];
            if ($filterSearch) {
                $like = '%' . $filterSearch . '%';
                $where[] = '(pm.menu_name LIKE ? OR pm.page_url LIKE ? OR ua.userName LIKE ? OR pm.userID LIKE ?)';
                $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
            }
            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM pesonal_right_page_main_menus pm
                LEFT JOIN user_access ua ON ua.userName = pm.userID $whereClause");
            $cntStmt->execute($params);
            $totalCount = (int)$cntStmt->fetchColumn();

            $selStmt = $pdo->prepare("
                SELECT pm.userpageID AS id, pm.userID, pm.menu_name, pm.page_url, pm.page_status, pm.pageType, pm.keep_active,
                       COALESCE(ua.userName, pm.userID) AS role_label
                FROM pesonal_right_page_main_menus pm
                LEFT JOIN user_access ua ON ua.userName = pm.userID
                $whereClause
                ORDER BY pm.userID ASC, pm.menu_name ASC
            ");
            $selStmt->execute($params);
            $assignments = $selStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        $msg     = 'Query error: ' . htmlspecialchars($e->getMessage());
        $msgType = 'danger';
    }
}

require_once 'includes/dev_header.php';
require_once 'includes/sidebar.php';
require_once 'includes/dev_topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Manage Sidebar Assignments</h1>
        <p class="panel-muted">Review and remove sidebar page assignments from roles or individual users.</p>
    </div>
    <div class="hero-actions">
        <a href="bind_roles_pages.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Assign Pages</a>
        <a href="manage-sidebar-assignments.php" class="btn btn-outline-secondary"><i class="fas fa-times me-1"></i>Clear Filters</a>
    </div>
</section>

<?php if ($msg): ?>
<div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show mb-3" role="alert">
    <i class="fas fa-<?php echo $msgType === 'success' ? 'check-circle' : ($msgType === 'warning' ? 'exclamation-triangle' : 'times-circle'); ?> me-2"></i>
    <?php echo $msg; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-link"></i></div>
        <div><div class="stat-title">Total Assignments</div><div class="stat-value"><?php echo number_format($totalCount); ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="color:#6366f1"><i class="fas fa-user-shield"></i></div>
        <div><div class="stat-title">Viewing Table</div><div class="stat-value" style="font-size:1rem"><?php echo $filterTable === 'right_page_main_menus' ? 'Role-Based' : 'User-Specific'; ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="color:#f59e0b"><i class="fas fa-sitemap"></i></div>
        <div><div class="stat-title">Roles Available</div><div class="stat-value"><?php echo count($roles); ?></div></div>
    </div>
</section>

<!-- Filter -->
<section class="panel mb-3">
    <div class="panel-header">
        <h3 class="panel-title"><i class="fas fa-filter me-2"></i>Filter Assignments</h3>
    </div>
    <div class="panel-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Search Menu / URL / Role</label>
                <input type="text" name="q" class="form-control" placeholder="menu name, page URL, role..." value="<?php echo htmlspecialchars($filterSearch); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Assignment Table</label>
                <select name="tbl" class="form-select" onchange="this.form.submit()">
                    <option value="right_page_main_menus" <?php echo $filterTable === 'right_page_main_menus' ? 'selected' : ''; ?>>Role-Based Assignments</option>
                    <option value="pesonal_right_page_main_menus" <?php echo $filterTable === 'pesonal_right_page_main_menus' ? 'selected' : ''; ?>>User-Specific Assignments</option>
                </select>
            </div>
            <?php if ($filterTable === 'right_page_main_menus'): ?>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Filter by Role</label>
                <select name="role" class="form-select">
                    <option value="">All Roles</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo htmlspecialchars($r['role_key']); ?>" <?php echo $filterRole === $r['role_key'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r['role_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2 d-grid">
                <label class="form-label small fw-semibold text-muted">&nbsp;</label>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Search</button>
            </div>
        </form>
    </div>
</section>

<!-- Assignments Table -->
<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">
                <?php echo $filterTable === 'right_page_main_menus' ? 'Role-Based Assignments' : 'User-Specific Assignments'; ?>
            </h3>
            <div class="panel-muted">
                <?php echo number_format($totalCount); ?> assignment(s) found.
                <?php if ($filterRole || $filterSearch): ?>&mdash; <span class="text-primary fw-semibold">Filters active</span><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="panel-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo $filterTable === 'right_page_main_menus' ? 'Role' : 'User'; ?></th>
                        <th>Menu Name</th>
                        <th>Page URL</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th class="text-end">Remove</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($assignments)): ?>
                        <?php foreach ($assignments as $i => $row): ?>
                            <tr id="row-<?php echo $row['id']; ?>">
                                <td class="text-muted small"><?php echo $i + 1; ?></td>
                                <td>
                                    <?php
                                        $roleKey   = htmlspecialchars($row['roleID'] ?? $row['userID'] ?? '');
                                        $roleLabel = htmlspecialchars($row['role_label'] ?? $roleKey);
                                        // If label is same as key (JOIN missed), make it human-readable
                                        if ($roleLabel === $roleKey) {
                                            $roleLabel = ucwords(str_replace('_', ' ', strtolower($roleKey)));
                                        }
                                    ?>
                                    <div>
                                        <span class="fw-semibold text-dark d-block"><?php echo $roleLabel; ?></span>
                                        <code class="small text-muted"><?php echo $roleKey; ?></code>
                                    </div>
                                </td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($row['menu_name'] ?? ''); ?></td>
                                <td><code class="small"><?php echo htmlspecialchars($row['page_url'] ?? ''); ?></code></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($row['pageType'] ?? '—'); ?></td>
                                <td>
                                    <span class="status-chip <?php echo ($row['page_status'] ?? 0) ? 'status-success' : 'status-muted'; ?>">
                                        <?php echo ($row['page_status'] ?? 0) ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-danger btn-sm"
                                        onclick="confirmRemove(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($filterTable); ?>', '<?php echo htmlspecialchars(addslashes($row['menu_name'] ?? '')); ?>')">
                                        <i class="fas fa-unlink me-1"></i>Remove
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-unlink fa-2x mb-2 d-block opacity-25"></i>
                                No assignments found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Hidden form for delete action -->
<form id="deleteForm" method="post" style="display:none">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="record_id" id="del_record_id">
    <input type="hidden" name="record_table" id="del_record_table">
    <?php foreach ($_GET as $k => $v): ?>
        <input type="hidden" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo htmlspecialchars($v); ?>">
    <?php endforeach; ?>
</form>

<!-- Confirm Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Removal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p>Are you sure you want to remove the assignment for:</p>
                <p class="fw-semibold" id="confirmMenuName"></p>
                <p class="text-muted small">This will immediately revoke sidebar access to this page for the assigned role/user.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn"><i class="fas fa-unlink me-1"></i>Yes, Remove</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
function confirmRemove(id, table, menuName) {
    document.getElementById('del_record_id').value    = id;
    document.getElementById('del_record_table').value = table;
    document.getElementById('confirmMenuName').textContent = menuName;
    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    modal.show();
    document.getElementById('confirmDeleteBtn').onclick = function() {
        document.getElementById('deleteForm').submit();
    };
}
</script>