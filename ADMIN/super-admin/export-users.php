<?php
require_once __DIR__ . '/includes/db.php';

if (!$pdo) {
    http_response_code(500);
    echo 'Database unavailable.';
    exit;
}

$format = strtolower($_GET['format'] ?? 'pdf');
$mode = strtolower($_GET['mode'] ?? 'view');

$usersSql = "
    SELECT u.user_id, u.email, u.account_status, u.last_login, u.created_at,
           u.full_name, r.role_name,
           p.first_name, p.surname, a.application_number,
           d.dept_name
    FROM users u
    LEFT JOIN roles r ON r.role_id = u.role_id
    LEFT JOIN applications a ON a.application_id = (
        SELECT a2.application_id
        FROM applications a2
        WHERE a2.user_id = u.user_id
        ORDER BY a2.submitted_at DESC, a2.application_id DESC
        LIMIT 1
    )
    LEFT JOIN personal_details p ON p.application_id = a.application_id
    LEFT JOIN programme_choices pc ON pc.application_id = a.application_id
    LEFT JOIN departments d ON d.dept_id = pc.department
    ORDER BY u.created_at DESC
";
$users = $pdo->query($usersSql)->fetchAll(PDO::FETCH_ASSOC);

if ($format === 'csv') {
    $filename = 'jostum-users-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Full Name', 'Email', 'Role', 'Department', 'Status', 'Last Login', 'Created']);
    foreach ($users as $user) {
        $name = $user['full_name'] ?: trim(($user['first_name'] ?? '') . ' ' . ($user['surname'] ?? ''));
        $role = $user['role_name'] ?: ($user['application_number'] ? 'Applicant' : 'Unassigned');
        $dept = $user['dept_name'] ?? 'Unassigned';
        $lastLogin = $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never';
        $created = $user['created_at'] ? date('M d, Y', strtotime($user['created_at'])) : 'N/A';
        fputcsv($output, [$name, $user['email'], $role, $dept, $user['account_status'], $lastLogin, $created]);
    }
    fclose($output);
    exit;
}

$documentTitle = 'JOSTUM PG School - User Export';
$generatedAt = date('M d, Y H:i');

$download = $mode === 'download';
if ($download) {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename=jostum-users-' . date('Ymd-His') . '.html');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="/ADMIN/images/logo.jpeg">
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
        .status.Active { color: #10b981; }
        .status.Suspended { color: #f59e0b; }
        .status.Locked { color: #ef4444; }
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
            <div class="meta">Total Users: <?php echo number_format(count($users)); ?></div>
        </div>

        <?php if (!$download): ?>
        <div class="toolbar">
            <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
        </div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php
                    $name = $user['full_name'] ?: trim(($user['first_name'] ?? '') . ' ' . ($user['surname'] ?? ''));
                    $role = $user['role_name'] ?: ($user['application_number'] ? 'Applicant' : 'Unassigned');
                    $dept = $user['dept_name'] ?? 'Unassigned';
                    $lastLogin = $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never';
                    $created = $user['created_at'] ? date('M d, Y', strtotime($user['created_at'])) : 'N/A';
                    $status = $user['account_status'] ?? 'Unknown';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($name ?: 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($role); ?></td>
                        <td><?php echo htmlspecialchars($dept); ?></td>
                        <td class="status <?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status); ?></td>
                        <td><?php echo htmlspecialchars($lastLogin); ?></td>
                        <td><?php echo htmlspecialchars($created); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
