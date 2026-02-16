<?php
require_once __DIR__ . '/includes/db.php';

if (!$pdo) {
    http_response_code(500);
    echo 'Database unavailable.';
    exit;
}

$format = strtolower($_GET['format'] ?? 'pdf');
$mode = strtolower($_GET['mode'] ?? 'view');

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

$logsSql = "
    SELECT l.log_id, l.action, l.entity, l.details, l.ip_address, l.user_agent, l.severity, l.created_at,
           u.email
    FROM audit_logs l
    LEFT JOIN users u ON u.user_id = l.actor_user_id
    $whereSql
    ORDER BY l.created_at DESC
";
$stmt = $pdo->prepare($logsSql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($format === 'csv') {
    $filename = 'jostum-audit-logs-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Timestamp', 'Actor', 'Action', 'Entity', 'Details', 'Severity', 'IP Address', 'User Agent']);
    foreach ($logs as $log) {
        $timestamp = $log['created_at'] ? date('M d, Y H:i', strtotime($log['created_at'])) : 'N/A';
        fputcsv($output, [
            $timestamp,
            $log['email'] ?? 'System',
            $log['action'],
            $log['entity'],
            $log['details'],
            $log['severity'],
            $log['ip_address'] ?? 'N/A',
            $log['user_agent'] ?? 'N/A',
        ]);
    }
    fclose($output);
    exit;
}

$documentTitle = 'JOSTUM PG School - Audit Log Export';
$generatedAt = date('M d, Y H:i');
$download = $mode === 'download';
if ($download) {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename=jostum-audit-logs-' . date('Ymd-His') . '.html');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="/JOSTUM/ADMIN/images/logo.jpeg">
<title><?php echo htmlspecialchars($documentTitle); ?></title>
    <style>
        body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; background: #f5f7fb; margin: 0; padding: 24px; color: #1e293b; }
        .sheet { background: #ffffff; border-radius: 12px; padding: 24px; box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08); }
        .header { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .title { font-size: 20px; font-weight: 700; margin: 0; }
        .meta { color: #64748b; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; color: #475569; }
        .status { font-weight: 600; }
        .status.INFO { color: #10b981; }
        .status.WARNING { color: #f59e0b; }
        .status.CRITICAL { color: #ef4444; }
        .toolbar { margin-top: 16px; display: flex; justify-content: flex-end; }
        .print-btn { background: #2563eb; color: #fff; border: none; padding: 10px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; }
        @media print { body { background: #fff; padding: 0; } .toolbar { display: none; } .sheet { box-shadow: none; border-radius: 0; } }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="header">
            <div>
                <h1 class="title"><?php echo htmlspecialchars($documentTitle); ?></h1>
                <div class="meta">Generated <?php echo htmlspecialchars($generatedAt); ?></div>
            </div>
            <div class="meta">Total Logs: <?php echo number_format(count($logs)); ?></div>
        </div>

        <?php if (!$download): ?>
        <div class="toolbar">
            <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
        </div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>Details</th>
                    <th>Severity</th>
                    <th>IP</th>
                    <th>User Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <?php
                    $timestamp = $log['created_at'] ? date('M d, Y H:i', strtotime($log['created_at'])) : 'N/A';
                    $severity = $log['severity'] ?? 'INFO';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($timestamp); ?></td>
                        <td><?php echo htmlspecialchars($log['email'] ?? 'System'); ?></td>
                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                        <td><?php echo htmlspecialchars($log['entity']); ?></td>
                        <td><?php echo htmlspecialchars($log['details']); ?></td>
                        <td class="status <?php echo htmlspecialchars($severity); ?>"><?php echo htmlspecialchars($severity); ?></td>
                        <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($log['user_agent'] ?? 'N/A'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
