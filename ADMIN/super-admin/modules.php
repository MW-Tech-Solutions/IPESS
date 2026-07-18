<?php
/**
 * System Modules Control UI
 * Gatekeeping: Only Super Admins can access.
 */
require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');

if (normalize_role(current_user_role()) !== 'SUPER_ADMIN') {
    http_response_code(403);
    exit('403 Forbidden — Only Super Admins can access this page.');
}

$pageTitle = 'Module Settings';

$cssUrl = app_url('ADMIN/super-admin/super-admin.css');
$bsCss  = app_url('asset/vendor/bootstrap/css/bootstrap.min.css');
$bsJs   = app_url('asset/vendor/bootstrap/js/bootstrap.bundle.min.js');
$faUrl  = app_url('asset/vendor/fontawesome/css/all.min.css');

$bsCssFallback  = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
$bsJsFallback   = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
$faUrlFallback  = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css';

$localBsCss  = defined('JOSTUM_ROOT') && file_exists(JOSTUM_ROOT . '/asset/vendor/bootstrap/css/bootstrap.min.css');
$localBsJs   = defined('JOSTUM_ROOT') && file_exists(JOSTUM_ROOT . '/asset/vendor/bootstrap/js/bootstrap.bundle.min.js');
$localFa     = defined('JOSTUM_ROOT') && file_exists(JOSTUM_ROOT . '/asset/vendor/fontawesome/css/all.min.css');

$bsCssHref  = $localBsCss ? $bsCss  : $bsCssFallback;
$bsJsSrc    = $localBsJs  ? $bsJs   : $bsJsFallback;
$faHref     = $localFa    ? $faUrl  : $faUrlFallback;

$pdo = db();

// Self-healing: Ensure student_verification module is in the system_modules table
try {
    $checkMod = $pdo->prepare("SELECT COUNT(*) FROM system_modules WHERE module_key = 'student_verification'");
    $checkMod->execute();
    if ((int)$checkMod->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO system_modules (module_key, module_name, is_active) VALUES ('student_verification', 'Applicant Account Verification', 1)")->execute();
    }
} catch (Throwable $e) {
    error_log("Failed to insert student_verification module: " . $e->getMessage());
}

$modules = $pdo->query("SELECT * FROM system_modules ORDER BY module_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> | JOSTUM PG Admin</title>
<link rel="icon" type="image/jpeg" href="/ADMIN/images/logo.jpeg">
<!-- Bootstrap CSS -->
<link href="<?php echo $bsCssHref; ?>" rel="stylesheet">
<!-- Font Awesome -->
<link rel="stylesheet" href="<?php echo $faHref; ?>">
<!-- Super-admin CSS -->
<link rel="stylesheet" href="<?php echo $cssUrl; ?>">
<style>
/* Sleek custom iOS switch look */
.form-switch .form-check-input {
    width: 2.8em;
    height: 1.5em;
    cursor: pointer;
    background-color: rgba(0,0,0,0.1);
    border: none;
    transition: background-color 0.3s ease, background-position 0.15s ease-in-out;
}
.form-switch .form-check-input:focus {
    box-shadow: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='rgba%280, 0, 0, 0.25%29'/%3e%3c/svg%3e");
}
.form-switch .form-check-input:checked {
    background-color: var(--bs-success, #28a745);
}
.status-badge {
    padding: 0.35em 0.65em;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 50rem;
}
.module-card {
    transition: all 0.25s ease-in-out;
    border: 1px solid rgba(0, 0, 0, 0.08);
}
.module-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important;
}
</style>
</head>
<body>
<div class="admin-shell" id="admin-shell">

<?php require_once 'includes/sidebar.php'; ?>

<div class="main-content">
<?php require_once 'includes/topbar.php'; ?>
<main class="page-content">

<section class="page-hero">
    <div>
        <h1>System Module Settings</h1>
        <p class="panel-muted">
            Manage system-wide accessibility of major modules (e.g. Admissions, Academics). 
            Deactivating a module restricts it to Super Admins and updates redirection/login flows for student and applicant accounts.
        </p>
    </div>
</section>

<div class="row g-4">
    <div class="col-lg-8">
        <section class="panel mb-4">
            <div class="panel-header d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="panel-title">Active Modules</h3>
                    <div class="panel-muted">Toggle accessibility of components.</div>
                </div>
            </div>
            <div class="panel-body">
                <?php if (empty($modules)): ?>
                    <div class="alert alert-info mb-0">No configurable system modules found.</div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($modules as $mod): ?>
                            <div class="col-md-6">
                                <div class="card module-card shadow-sm h-100">
                                    <div class="card-body d-flex flex-column justify-content-between p-4">
                                        <div>
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($mod['module_name']); ?></h5>
                                                <span id="badge-<?php echo $mod['module_key']; ?>" class="status-badge <?php echo $mod['is_active'] ? 'bg-success text-white' : 'bg-danger text-white'; ?>">
                                                    <?php echo $mod['is_active'] ? 'Active' : 'Disabled'; ?>
                                                </span>
                                            </div>
                                            <p class="text-muted small mb-4">
                                                Key: <code><?php echo htmlspecialchars($mod['module_key']); ?></code>
                                            </p>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="small text-muted">
                                                Last updated: <?php echo date('M d, Y H:i', strtotime($mod['updated_at'])); ?>
                                            </span>
                                            <div class="form-check form-switch ps-0">
                                                <input class="form-check-input ms-0" type="checkbox" role="switch" 
                                                       id="switch-<?php echo $mod['module_key']; ?>"
                                                       <?php echo $mod['is_active'] ? 'checked' : ''; ?>
                                                       onchange="toggleModule('<?php echo $mod['module_key']; ?>', this)">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="col-lg-4">
        <section class="panel h-100">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title"><i class="fas fa-history me-2 text-primary"></i>Recent Module Audit Logs</h3>
                    <div class="panel-muted">History of module configuration updates.</div>
                </div>
            </div>
            <div class="panel-body">
                <?php
                $logs = [];
                try {
                    $stmtLog = $pdo->prepare("
                        SELECT a.*, COALESCE(CONCAT(u.first_name, ' ', u.surname), 'System') AS actor_name 
                        FROM audit_logs a
                        LEFT JOIN users u ON a.actor_user_id = u.user_id
                        WHERE a.action = 'Toggle Module' 
                        ORDER BY a.log_id DESC 
                        LIMIT 6
                    ");
                    $stmtLog->execute();
                    $logs = $stmtLog->fetchAll(PDO::FETCH_ASSOC);
                } catch (Throwable $_) {}
                ?>
                <?php if (empty($logs)): ?>
                    <p class="text-muted small mb-0">No module configuration audit logs found.</p>
                <?php else: ?>
                    <div class="timeline" style="border-left: 2px solid rgba(0,0,0,0.08); padding-left: 20px; position: relative;">
                        <?php foreach ($logs as $log): ?>
                            <div class="mb-3 position-relative">
                                <div class="bg-primary rounded-circle position-absolute" style="width: 10px; height: 10px; left: -26px; top: 5px; border: 2px solid white;"></div>
                                <div class="fw-semibold small text-dark"><?php echo htmlspecialchars($log['actor_name']); ?></div>
                                <div class="text-muted" style="font-size: 0.8rem;"><?php echo htmlspecialchars($log['details']); ?></div>
                                <div class="text-muted small" style="font-size: 0.7rem;"><?php echo date('M d, H:i', strtotime($log['timestamp'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

</main>
</div>
</div>

<!-- Bootstrap Bundle JS -->
<script src="<?php echo $bsJsSrc; ?>"></script>

<script>
async function toggleModule(moduleKey, switchEl) {
    const isChecked = switchEl.checked ? 1 : 0;
    const badge = document.getElementById(`badge-${moduleKey}`);
    
    // Disable switch during API request
    switchEl.disabled = true;

    try {
        const formData = new FormData();
        formData.append('module_key', moduleKey);
        formData.append('is_active', isChecked);
        
        const response = await fetch('api/modules.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data && data.success) {
            if (isChecked) {
                badge.textContent = 'Active';
                badge.className = 'status-badge bg-success text-white';
            } else {
                badge.textContent = 'Disabled';
                badge.className = 'status-badge bg-danger text-white';
            }
        } else {
            alert(data.message || 'Failed to update module status.');
            switchEl.checked = !isChecked; // Revert switch state
        }
    } catch (err) {
        console.error(err);
        alert('An error occurred while calling the server.');
        switchEl.checked = !isChecked; // Revert switch state
    } finally {
        switchEl.disabled = false;
    }
}
</script>
</body>
</html>
