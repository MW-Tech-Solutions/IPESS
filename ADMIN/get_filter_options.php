<?php
require_once 'db.php';

header('Content-Type: application/json');

$response = [
    'faculties' => [],
    'departments' => []
];

if (isset($pdo)) {
    try {
        // Fetch distinct faculties
        $facultySql = "SELECT DISTINCT faculty FROM programme_choices WHERE faculty IS NOT NULL AND faculty != '' ORDER BY faculty";
        $facultyStmt = $pdo->query($facultySql);
        $response['faculties'] = $facultyStmt->fetchAll(PDO::FETCH_COLUMN);

        // Fetch distinct departments
        $deptSql = "SELECT DISTINCT department FROM programme_choices WHERE department IS NOT NULL AND department != '' ORDER BY department";
        $deptStmt = $pdo->query($deptSql);
        $response['departments'] = $deptStmt->fetchAll(PDO::FETCH_COLUMN);

    } catch (PDOException $e) {
        http_response_code(500);
        $response['error'] = 'Database error: ' . $e->getMessage();
    }
} else {
    http_response_code(500);
    $response['error'] = 'Database connection not available.';
}

echo json_encode($response);
?>