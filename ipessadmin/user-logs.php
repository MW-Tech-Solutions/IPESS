<?php
session_start();
require_once 'db.php';
require_once __DIR__ . '/../includes/user_activity_logger.php';

// Access control - only SUPER_ADMIN and DEVELOPER can view all logs
$currentRole = function_exists('normalize_role') ? normalize_role($_SESSION['roleid'] ?? '') : strtoupper(trim($_SESSION['roleid'] ?? ''));
if (!in_array($currentRole, ['SUPER_ADMIN', 'DEVELOPER', 'ICT_ADMIN'], true)) {
    header("Location: index.php");
    exit;
}

// Ensure table exists
ensure_user_logs_tables($pdo);

// ─── Filters ────────────────────────────────────────────────────────────
$filterEvent   = trim($_GET['event']   ?? '');
$filterRole    = trim($_GET['role']    ?? '');
$filterSearch  = trim($_GET['search']  ?? '');
$filterFrom    = trim($_GET['from']    ?? '');
$filterTo      = trim($_GET['to']      ?? '');

$where  = [];
$params = [];

if ($filterEvent)  { $where[] = 'event_type = ?';  $params[] = $filterEvent; }
if ($filterRole)   { $where[] = 'role = ?';         $params[] = strtoupper($filterRole); }
if ($filterSearch) {
    $s = '%' . $filterSearch . '%';
    $where[] = '(full_name LIKE ? OR username LIKE ? OR action LIKE ? OR details LIKE ? OR entity_id LIKE ?)';
    array_push($params, $s, $s, $s, $s, $s);
}
if ($filterFrom) { $where[] = 'created_at >= ?'; $params[] = $filterFrom . ' 00:00:00'; }
if ($filterTo)   { $where[] = 'created_at <= ?'; $params[] = $filterTo   . ' 23:59:59'; }

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ─── Stats ───────────────────────────────────────────────────────────────
$stats = ['total' => 0, 'logins' => 0, 'actions' => 0, 'users' => 0];
try {
    $row = $pdo->query("SELECT COUNT(*) AS total, SUM(event_type='LOGIN') AS logins, SUM(event_type='ACTION') AS actions, COUNT(DISTINCT staff_id) AS users FROM user_login_logs")->fetch(PDO::FETCH_ASSOC);
    if ($row) $stats = $row;
} catch (Throwable $e) {}

// ─── Distinct roles for filter dropdown ──────────────────────────────────
$distinctRoles = [];
try {
    $distinctRoles = $pdo->query("SELECT DISTINCT role FROM user_login_logs WHERE role IS NOT NULL ORDER BY role ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {}

// ─── Pagination ───────────────────────────────────────────────────────────
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM user_login_logs $whereSql");
    $countStmt->execute($params);
    $totalRows = (int)$countStmt->fetchColumn();
} catch (Throwable $e) { $totalRows = 0; }

$totalPages = max(1, (int)ceil($totalRows / $limit));
$offset     = ($page - 1) * $limit;

// ─── Data fetch ──────────────────────────────────────────────────────────
$logs = [];
try {
    $dataStmt = $pdo->prepare("SELECT * FROM user_login_logs $whereSql ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    $dataStmt->execute($params);
    $logs = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$pageTitle = 'User Logs';
$pageSubtitle = 'Track staff login sessions and system activity in real time.';
require_once 'includes/dev_header.php';
require_once 'includes/sidebar.php';
require_once 'includes/dev_topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>User Logs</h1>
        <p class="panel-muted">Login activity, role sessions, and system actions across all staff accounts.</p>
    </div>
    <div class="hero-actions">
        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-light">
            <i class="fas fa-download me-2"></i>Export CSV
        </a>
    </div>
</section>

<?php
// ─── CSV Export ───────────────────────────────────────────────────────────
if (!empty($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="user_logs_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Staff ID','Username','Full Name','Role','Event Type','Action','Details','Entity Type','Entity ID','IP Address','Date & Time']);
    try {
        $allStmt = $pdo->prepare("SELECT * FROM user_login_logs $whereSql ORDER BY created_at DESC");
        $allStmt->execute($params);
        while ($r = $allStmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [$r['id'], $r['staff_id'], $r['username'], $r['full_name'], $r['role'], $r['event_type'], $r['action'], $r['details'], $r['entity_type'], $r['entity_id'], $r['ip_address'], $r['created_at']]);
        }
    } catch (Throwable $e) {}
    fclose($out);
    exit;
}
?>

<!-- Stats -->
<section class="stat-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg,#6366f1,#818cf8);"><i class="fas fa-list-alt"></i></div>
        <div>
            <div class="stat-title">Total Events</div>
            <div class="stat-value"><?php echo number_format((int)$stats['total']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg,#10b981,#34d399);"><i class="fas fa-sign-in-alt"></i></div>
        <div>
            <div class="stat-title">Login Events</div>
            <div class="stat-value"><?php echo number_format((int)$stats['logins']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg,#f59e0b,#fbbf24);"><i class="fas fa-bolt"></i></div>
        <div>
            <div class="stat-title">Actions Logged</div>
            <div class="stat-value"><?php echo number_format((int)$stats['actions']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg,#3b82f6,#60a5fa);"><i class="fas fa-users"></i></div>
        <div>
            <div class="stat-title">Distinct Users</div>
            <div class="stat-value"><?php echo number_format((int)$stats['users']); ?></div>
        </div>
    </div>
</section>

<!-- Filters -->
<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Activity Log</h3>
            <div class="panel-muted">
                Showing <?php echo number_format(count($logs)); ?> of <?php echo number_format($totalRows); ?> records
            </div>
        </div>
    </div>

    <div class="panel-body" style="padding-bottom:0;">
        <form method="GET" class="d-flex flex-wrap gap-2 mb-3 align-items-end">
            <!-- Search -->
            <div style="flex:1;min-width:200px;">
                <label class="form-label small mb-1">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Name, action, details, entity ID…"
                       value="<?php echo htmlspecialchars($filterSearch); ?>">
            </div>
            <!-- Event Type -->
            <div style="min-width:140px;">
                <label class="form-label small mb-1">Event Type</label>
                <select name="event" class="form-select form-select-sm">
                    <option value="">All Events</option>
                    <option value="LOGIN"  <?php echo $filterEvent==='LOGIN'  ? 'selected' : ''; ?>>Login</option>
                    <option value="LOGOUT" <?php echo $filterEvent==='LOGOUT' ? 'selected' : ''; ?>>Logout</option>
                    <option value="ACTION" <?php echo $filterEvent==='ACTION' ? 'selected' : ''; ?>>Action</option>
                </select>
            </div>
            <!-- Role -->
            <div style="min-width:160px;">
                <label class="form-label small mb-1">Role</label>
                <select name="role" class="form-select form-select-sm">
                    <option value="">All Roles</option>
                    <?php foreach ($distinctRoles as $dr): ?>
                        <option value="<?php echo htmlspecialchars($dr); ?>"
                            <?php echo strtoupper($filterRole) === strtoupper($dr) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dr); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Date From -->
            <div style="min-width:140px;">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterFrom); ?>">
            </div>
            <!-- Date To -->
            <div style="min-width:140px;">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterTo); ?>">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="user-logs.php" class="btn btn-light btn-sm">Clear</a>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div style="overflow-x:auto;">
        <table class="table table-hover table-sm align-middle mb-0" style="font-size:0.82rem;">
            <thead style="background:#f8fafc;position:sticky;top:0;z-index:1;">
                <tr>
                    <th style="width:50px;">#</th>
                    <th>Staff ID</th>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Event</th>
                    <th>Action</th>
                    <th style="min-width:280px;">Details</th>
                    <th>Entity</th>
                    <th>IP Address</th>
                    <th>Date &amp; Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="11" class="text-center text-muted py-5">
                            <i class="fas fa-clipboard-list fa-2x mb-2 d-block opacity-40"></i>
                            No log entries found.
                        </td>
                    </tr>
                <?php else: foreach ($logs as $i => $log):
                    $eventType  = strtoupper($log['event_type'] ?? 'ACTION');
                    $eventColor = match($eventType) {
                        'LOGIN'  => '#10b981',
                        'LOGOUT' => '#64748b',
                        'ACTION' => '#6366f1',
                        default  => '#94a3b8',
                    };
                    $eventIcon = match($eventType) {
                        'LOGIN'  => 'fa-sign-in-alt',
                        'LOGOUT' => 'fa-sign-out-alt',
                        'ACTION' => 'fa-bolt',
                        default  => 'fa-circle',
                    };
                    $entityDisplay = '';
                    if ($log['entity_type'] && $log['entity_id']) {
                        $entityDisplay = '<span class="badge bg-light text-dark border" style="font-size:0.7rem;">' .
                            htmlspecialchars($log['entity_type']) . ' #' . htmlspecialchars($log['entity_id']) .
                            '</span>';
                    } elseif ($log['entity_type']) {
                        $entityDisplay = '<span class="text-muted">' . htmlspecialchars($log['entity_type']) . '</span>';
                    }
                ?>
                <tr>
                    <td class="text-muted"><?php echo ($offset + $i + 1); ?></td>
                    <td>
                        <code style="font-size:0.75rem;background:#f1f5f9;padding:2px 5px;border-radius:4px;">
                            <?php echo htmlspecialchars($log['staff_id'] ?? '—'); ?>
                        </code>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($log['full_name'] ?? '—'); ?></strong>
                    </td>
                    <td class="text-muted"><?php echo htmlspecialchars($log['username'] ?? '—'); ?></td>
                    <td>
                        <span class="badge" style="background:rgba(99,102,241,0.1);color:#6366f1;font-size:0.7rem;font-weight:600;letter-spacing:0.04em;">
                            <?php echo htmlspecialchars($log['role'] ?? '—'); ?>
                        </span>
                    </td>
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:5px;font-weight:600;font-size:0.75rem;color:<?php echo $eventColor; ?>">
                            <i class="fas <?php echo $eventIcon; ?>"></i>
                            <?php echo htmlspecialchars($eventType); ?>
                        </span>
                    </td>
                    <td>
                        <span style="font-weight:500;"><?php echo htmlspecialchars($log['action'] ?? '—'); ?></span>
                    </td>
                    <td style="max-width:320px;">
                        <span class="d-block text-truncate" style="max-width:300px;" title="<?php echo htmlspecialchars($log['details'] ?? ''); ?>">
                            <?php echo htmlspecialchars($log['details'] ?? '—'); ?>
                        </span>
                    </td>
                    <td><?php echo $entityDisplay ?: '<span class="text-muted">—</span>'; ?></td>
                    <td>
                        <span style="font-family:monospace;font-size:0.75rem;">
                            <?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?>
                        </span>
                    </td>
                    <td style="white-space:nowrap;font-size:0.75rem;color:#64748b;">
                        <?php
                            $ts = strtotime($log['created_at'] ?? '');
                            echo $ts ? date('M d, Y g:i A', $ts) : '—';
                        ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="panel-body border-top d-flex justify-content-between align-items-center" style="padding:12px 20px;">
        <small class="text-muted">
            Page <?php echo $page; ?> of <?php echo $totalPages; ?> &mdash; <?php echo number_format($totalRows); ?> total records
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php
                $queryBase = array_diff_key($_GET, ['page' => 1]);
                if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($queryBase, ['page' => $page - 1])); ?>">‹ Prev</a>
                    </li>
                <?php endif;
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                for ($p = $start; $p <= $end; $p++): ?>
                    <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($queryBase, ['page' => $p])); ?>">
                            <?php echo $p; ?>
                        </a>
                    </li>
                <?php endfor;
                if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($queryBase, ['page' => $page + 1])); ?>">Next ›</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</section>

<!-- MAC Address Note -->
<div class="alert alert-info mx-3 mb-4" style="font-size:0.82rem;border-radius:8px;">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Note on MAC Address:</strong> MAC addresses are hardware-level identifiers that are <strong>not transmitted over HTTP</strong> and cannot be obtained by a web server — even on a local network. The IP Address column captures the user's real IP (including behind proxies/Cloudflare). For MAC tracking, a desktop agent or network-level ARP logging would be required.
</div>

<?php require_once 'includes/dev_footer.php'; ?>
