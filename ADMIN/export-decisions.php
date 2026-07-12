session_start();
require_once __DIR__ . '/../app/helpers/auth.php';

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

require_once 'db.php';

try {
    $sql = "
        SELECT a.application_number, p.surname, p.first_name, pc.course, a.status, a.submitted_at
        FROM applications a
        LEFT JOIN personal_details p ON a.application_id = p.application_id
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
        WHERE a.status IN ('Admitted', 'Rejected')
        ORDER BY a.submitted_at DESC
    ";
    $stmt = $pdo->query($sql);
    $decisions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="admission_decisions_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Add column headers
    fputcsv($output, ['Application Number', 'Surname', 'First Name', 'Programme', 'Status', 'Decision Date']);

    // Add data
    foreach ($decisions as $decision) {
        fputcsv($output, [
            $decision['application_number'],
            $decision['surname'],
            $decision['first_name'],
            $decision['course'],
            $decision['status'],
            date('Y-m-d', strtotime($decision['submitted_at']))
        ]);
    }

    fclose($output);
    exit();

} catch (PDOException $e) {
    die("Error exporting decisions: " . $e->getMessage());
}
?>