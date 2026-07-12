<?php
session_start();
require_once __DIR__ . '/../../admin/includes/db.php';
require_once __DIR__ . '/../../../app/helpers/auth.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized access.');
}

$role = $_SESSION['role'] ?? '';
$userId = $_SESSION['user_id'];

if (!has_permission('export_csv', $role, $userId) && !has_permission('export_excel', $role, $userId)) {
    http_response_code(403);
    die('Forbidden. Insufficient permissions to export CSV/Excel.');
}

try {
    $sql = "
        SELECT wal.timestamp, u.email AS actor_email, u.full_name AS actor_name, wal.role,
               pd.first_name, pd.surname, a.application_number,
               wal.action, wal.old_status, wal.new_status, wal.remarks,
               wal.ip_address, wal.browser, wal.os
        FROM workflow_audit_logs wal
        LEFT JOIN users u ON wal.user_id = u.user_id
        LEFT JOIN applications a ON wal.applicant_id = a.application_id
        LEFT JOIN personal_details pd ON wal.applicant_id = pd.application_id
        ORDER BY wal.timestamp DESC
    ";
    $stmt = $pdo->query($sql);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="workflow_audit_logs_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Headers
    fputcsv($output, ['Timestamp', 'Actor Name', 'Actor Email', 'Actor Role', 'Applicant Name', 'Application Number', 'Action', 'Old Status', 'New Status', 'Remarks', 'IP Address', 'Browser', 'OS']);

    // Data rows
    foreach ($logs as $row) {
        $actorName = $row['actor_name'] ?: 'System';
        $appName = trim(($row['first_name'] ?? '') . ' ' . ($row['surname'] ?? '')) ?: 'N/A';
        fputcsv($output, [
            $row['timestamp'],
            $actorName,
            $row['actor_email'],
            $row['role'],
            $appName,
            $row['application_number'],
            $row['action'],
            $row['old_status'],
            $row['new_status'],
            $row['remarks'],
            $row['ip_address'],
            $row['browser'],
            $row['os']
        ]);
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    die("Error exporting workflow audit logs: " . $e->getMessage());
}
?>
