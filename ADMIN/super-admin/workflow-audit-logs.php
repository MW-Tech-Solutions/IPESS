<?php
$pageTitle = 'Workflow Audit Trails';
$pageSubtitle = 'Complete granular history of student admission status progressions, evaluations, and registrations.';

require_once 'includes/db.php';

$stats = ['total' => 0, 'verify' => 0, 'approve' => 0, 'reject' => 0];
$logs = [];
$auditAvailable = false;

$filterRole = $_GET['role'] ?? '';
$filterAction = $_GET['action_filter'] ?? '';
$searchTerm = trim($_GET['search'] ?? '');
$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
if ($currentPage < 1) $currentPage = 1;

$limit = 15;
$offset = ($currentPage - 1) * $limit;

if ($pdo) {
    try {
        $auditAvailable = true;
        
        $whereClauses = ["1=1"];
        $params = [];

        if ($filterRole !== '') {
            $whereClauses[] = "wal.role = :role";
            $params[':role'] = $filterRole;
        }

        if ($filterAction !== '') {
            $whereClauses[] = "wal.action LIKE :action_filter";
            $params[':action_filter'] = '%' . $filterAction . '%';
        }

        if ($searchTerm !== '') {
            $whereClauses[] = "(u.email LIKE :search OR u.full_name LIKE :search OR pd.first_name LIKE :search OR pd.surname LIKE :search OR wal.applicant_id LIKE :search OR wal.remarks LIKE :search)";
            $params[':search'] = '%' . $searchTerm . '%';
        }

        $whereSql = implode(' AND ', $whereClauses);

        // Stats
        $stats['total'] = (int) $pdo->query("SELECT COUNT(*) FROM workflow_audit_logs")->fetchColumn();
        $stats['verify'] = (int) $pdo->query("SELECT COUNT(*) FROM workflow_audit_logs WHERE action LIKE '%verify%' OR action LIKE '%document%'")->fetchColumn();
        $stats['approve'] = (int) $pdo->query("SELECT COUNT(*) FROM workflow_audit_logs WHERE action LIKE '%approve%' OR action LIKE '%endorse%'")->fetchColumn();
        $stats['reject'] = (int) $pdo->query("SELECT COUNT(*) FROM workflow_audit_logs WHERE action LIKE '%reject%' OR action LIKE '%decline%' OR action LIKE '%correct%'")->fetchColumn();

        // Count query for pagination
        $countQuery = "
            SELECT COUNT(*)
            FROM workflow_audit_logs wal
            LEFT JOIN users u ON wal.user_id = u.user_id
            LEFT JOIN personal_details pd ON wal.applicant_id = pd.application_id
            WHERE {$whereSql}
        ";
        $stmtCount = $pdo->prepare($countQuery);
        $stmtCount->execute($params);
        $totalLogs = (int) $stmtCount->fetchColumn();

        // Data query
        $logsQuery = "
            SELECT wal.*, u.email AS actor_email, u.full_name AS actor_name,
                   pd.first_name AS app_first_name, pd.surname AS app_surname, a.application_number
            FROM workflow_audit_logs wal
            LEFT JOIN users u ON wal.user_id = u.user_id
            LEFT JOIN applications a ON wal.applicant_id = a.application_id
            LEFT JOIN personal_details pd ON wal.applicant_id = pd.application_id
            WHERE {$whereSql}
            ORDER BY wal.timestamp DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmtData = $pdo->prepare($logsQuery);
        foreach ($params as $key => $val) {
            $stmtData->bindValue($key, $val);
        }
        $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmtData->execute();
        $logs = $stmtData->fetchAll(PDO::FETCH_ASSOC);

        $totalPages = ceil($totalLogs / $limit) ?: 1;

    } catch (PDOException $e) {
        $auditAvailable = false;
        error_log("Workflow Audit logs load error: " . $e->getMessage());
    }
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<div class="d-flex gap-2 mb-4">
    <a href="audit-logs.php" class="btn btn-outline-secondary btn-sm">General Security Logs</a>
    <a href="workflow-audit-logs.php" class="btn btn-primary btn-sm">Admissions Workflow Logs</a>
</div>

<section class="page-hero mt-2">
    <div>
        <h1>Workflow Audit Trails</h1>
        <p class="panel-muted">Complete historical trail of applicant status advancements, verifications, and approvals.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-outline-secondary" href="export-workflow-logs.php?format=csv" target="_blank">
            <i class="fas fa-file-excel me-1"></i> Export Excel
        </a>
    </div>
</section>

<section class="stat-grid mb-4">
    <div class="stat-card">
        <div class="stat-icon text-primary"><i class="fas fa-clipboard-list"></i></div>
        <div>
            <div class="stat-title">Total Logs</div>
            <div class="stat-value"><?= number_format($stats['total']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon text-info"><i class="fas fa-file-signature"></i></div>
        <div>
            <div class="stat-title">Verifications</div>
            <div class="stat-value"><?= number_format($stats['verify']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon text-success"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-title">Endorsements</div>
            <div class="stat-value"><?= number_format($stats['approve']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon text-danger"><i class="fas fa-times-circle"></i></div>
        <div>
            <div class="stat-title">Rejections</div>
            <div class="stat-value"><?= number_format($stats['reject']) ?></div>
        </div>
    </div>
</section>

<!-- Filters -->
<section class="panel mb-4">
    <div class="panel-body">
        <form class="row g-3" method="GET">
            <div class="col-md-3">
                <input type="text" class="form-control" name="search" placeholder="Search by actor, applicant or remarks" value="<?= htmlspecialchars($searchTerm) ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="role">
                    <option value="">All Actor Roles</option>
                    <?php foreach (['SUPER_ADMIN', 'ICTO_STAFF', 'DEPT_ADMIN', 'FACULTY_ADMIN', 'PG_SCHOOL_OFFICER', 'ICT_STAFF'] as $role): ?>
                        <option value="<?= $role ?>" <?= ($filterRole === $role) ? 'selected' : '' ?>><?= $role ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="action_filter">
                    <option value="">All Action Types</option>
                    <option value="verify" <?= ($filterAction === 'verify') ? 'selected' : '' ?>>Verification</option>
                    <option value="endorse" <?= ($filterAction === 'endorse') ? 'selected' : '' ?>>Endorsements</option>
                    <option value="decline" <?= ($filterAction === 'decline') ? 'selected' : '' ?>>Declines & Rejections</option>
                    <option value="matric" <?= ($filterAction === 'matric') ? 'selected' : '' ?>>Matric Generations</option>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100" type="submit"><i class="fas fa-filter me-1"></i> Apply Filters</button>
            </div>
        </form>
    </div>
</section>

<!-- Logs Listing -->
<section class="panel">
    <div class="panel-header">
        <h3 class="panel-title">Audit Trail Records</h3>
        <div class="panel-muted"><?= $totalLogs ?> log entry(ies) recorded.</div>
    </div>
    <div class="panel-body">
        <?php if (!$auditAvailable): ?>
            <div class="text-muted text-center py-4">Workflow audit logs table not found. Run update migrations to activate tracking.</div>
        <?php elseif (empty($logs)): ?>
            <div class="text-muted text-center py-4">No audit trails recorded.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-sm">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Actor / Role</th>
                            <th>Applicant</th>
                            <th>Action</th>
                            <th>Transition Status</th>
                            <th>Remarks</th>
                            <th>Browser/OS/IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $actor = htmlspecialchars($log['actor_name'] ?: $log['actor_email'] ?: 'System');
                            $app = htmlspecialchars(trim($log['app_first_name'] . ' ' . $log['app_surname']) ?: 'N/A');
                            
                            $statusBadge = '<span class="text-muted text-xs font-monospace">' 
                                         . htmlspecialchars($log['old_status'] ?: 'N/A') 
                                         . ' &rarr; ' 
                                         . htmlspecialchars($log['new_status'] ?: 'N/A') 
                                         . '</span>';
                            ?>
                            <tr>
                                <td><?= date('M d, Y H:i:s', strtotime($log['timestamp'])) ?></td>
                                <td>
                                    <div class="fw-bold"><?= $actor ?></div>
                                    <span class="badge bg-light text-dark text-xs"><?= htmlspecialchars($log['role']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-xs"><?= $app ?></div>
                                    <code><?= htmlspecialchars($log['application_number'] ?: $log['applicant_id']) ?></code>
                                </td>
                                <td><?= htmlspecialchars($log['action']) ?></td>
                                <td><?= $statusBadge ?></td>
                                <td class="text-muted text-xs"><?= htmlspecialchars($log['remarks'] ?? '') ?></td>
                                <td class="text-muted text-xs font-monospace">
                                    <?= htmlspecialchars($log['browser']) ?> / <?= htmlspecialchars($log['os']) ?><br>
                                    IP: <?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav class="d-flex justify-content-end mt-4">
                    <ul class="pagination mb-0">
                        <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?role=<?= urlencode($filterRole) ?>&action_filter=<?= urlencode($filterAction) ?>&search=<?= urlencode($searchTerm) ?>&page=<?= $currentPage - 1 ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= ($currentPage == $i) ? 'active' : '' ?>">
                                <a class="page-link" href="?role=<?= urlencode($filterRole) ?>&action_filter=<?= urlencode($filterAction) ?>&search=<?= urlencode($searchTerm) ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?role=<?= urlencode($filterRole) ?>&action_filter=<?= urlencode($filterAction) ?>&search=<?= urlencode($searchTerm) ?>&page=<?= $currentPage + 1 ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
