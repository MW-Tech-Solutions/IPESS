<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SUPER_ADMIN') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'Audit Intelligence';
$pageSubtitle = 'Security signals and system activity tied to admin actions.';

require_once 'includes/db.php';

$stats = ['total' => 0, 'info' => 0, 'warning' => 0, 'critical' => 0];
$logs = [];
$auditAvailable = false;

if ($pdo) {
    try {
        $auditAvailable = true;
        $where = [];
        $params = [];

        if (!empty($_GET['severity'])) {
            $where[] = 'l.severity = ?';
            $params[] = $_GET['severity'];
        }
        if (!empty($_GET['action'])) {
            $where[] = 'l.action = ?';
            $params[] = $_GET['action'];
        }
        if (!empty($_GET['search'])) {
            $search = '%' . $_GET['search'] . '%';
            $where[] = '(l.details LIKE ? OR u.email LIKE ?)';
            array_push($params, $search, $search);
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $statsSql = "
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN severity = 'INFO' THEN 1 ELSE 0 END) AS info,
                SUM(CASE WHEN severity = 'WARNING' THEN 1 ELSE 0 END) AS warning,
                SUM(CASE WHEN severity = 'CRITICAL' THEN 1 ELSE 0 END) AS critical
            FROM audit_logs
        ";
        $stats = $pdo->query($statsSql)->fetch(PDO::FETCH_ASSOC) ?: $stats;

        $logsSql = "
            SELECT l.log_id, l.action, l.entity, l.details, l.ip_address, l.user_agent, l.severity, l.created_at,
                   u.email
            FROM audit_logs l
            LEFT JOIN users u ON u.user_id = l.actor_user_id
            $whereSql
            ORDER BY l.created_at DESC
            LIMIT 60
        ";
        $stmt = $pdo->prepare($logsSql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $auditAvailable = false;
    }
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<?php
$exportQuery = $_GET ? ('?' . http_build_query($_GET)) : '';
?>

<section class="page-hero">
    <div>
        <h1>Audit Intelligence</h1>
        <p class="panel-muted">Trace sensitive actions, monitor security flags, and verify system activity.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#exportAuditModal">Export Logs</button>
        <button class="btn btn-primary">Clear Old Logs</button>
    </div>
</section>


<div class="modal fade" id="exportAuditModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Export Audit Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-4">Choose the format and how you want to receive the report.</p>
                <div class="d-grid gap-2">
                    <a class="btn btn-outline-primary" href="export-audit-logs.php<?php echo $exportQuery; ?>&format=pdf&mode=view" target="_blank">View PDF (Print/Save)</a>
                    <a class="btn btn-primary" href="export-audit-logs.php<?php echo $exportQuery; ?>&format=csv&mode=download">Download Excel (CSV)</a>
                    <a class="btn btn-outline-secondary" href="export-audit-logs.php<?php echo $exportQuery; ?>&format=pdf&mode=download">Download PDF</a>
                </div>
                <div class="text-muted small mt-3">Uses your current filters in the export.</div>
            </div>
        </div>
    </div>
</div>


<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
        <div>
            <div class="stat-title">Total Logs</div>
            <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-info-circle"></i></div>
        <div>
            <div class="stat-title">Info Events</div>
            <div class="stat-value"><?php echo number_format($stats['info']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div>
            <div class="stat-title">Warnings</div>
            <div class="stat-value"><?php echo number_format($stats['warning']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-bolt"></i></div>
        <div>
            <div class="stat-title">Critical</div>
            <div class="stat-value"><?php echo number_format($stats['critical']); ?></div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Audit Filters</h3>
            <div class="panel-muted">Refine system logs by severity or action type.</div>
        </div>
    </div>
    <div class="panel-body">
        <form class="row g-3" method="GET">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Search details or email" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="action">
                    <option value="">All Actions</option>
                    <?php foreach (['LOGIN', 'LOGOUT', 'UPDATE', 'CREATE', 'DELETE', 'EXPORT'] as $action): ?>
                        <option value="<?php echo $action; ?>" <?php echo (($_GET['action'] ?? '') === $action) ? 'selected' : ''; ?>><?php echo $action; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="severity">
                    <option value="">All Severity</option>
                    <?php foreach (['INFO', 'WARNING', 'CRITICAL'] as $severity): ?>
                        <option value="<?php echo $severity; ?>" <?php echo (($_GET['severity'] ?? '') === $severity) ? 'selected' : ''; ?>><?php echo $severity; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit">Apply</button>
            </div>
        </form>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">System Audit Log</h3>
            <div class="panel-muted">Most recent activities across admin and system workflows.</div>
        </div>
    </div>
    <div class="panel-body">
        <?php if (!$auditAvailable): ?>
            <div class="text-muted">Audit table not found. Add the audit_logs table to enable tracking.</div>
        <?php elseif (empty($logs)): ?>
            <div class="text-muted">No audit events recorded yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Actor</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>Details</th>
                            <th>Severity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $severityClass = 'status-muted';
                            if ($log['severity'] === 'INFO') {
                                $severityClass = 'status-success';
                            } elseif ($log['severity'] === 'WARNING') {
                                $severityClass = 'status-warning';
                            } elseif ($log['severity'] === 'CRITICAL') {
                                $severityClass = 'status-danger';
                            }
                            ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($log['email'] ?? 'System'); ?></td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><?php echo htmlspecialchars($log['entity']); ?></td>
                                <td><?php echo htmlspecialchars($log['details']); ?></td>
                                <td><span class="status-chip <?php echo $severityClass; ?>"><?php echo htmlspecialchars($log['severity']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
