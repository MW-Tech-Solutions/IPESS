<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$action = $_GET['action'] ?? '';
$response = ['status' => 'error', 'data' => []];

try {
    if (!$conn) {
        throw new Exception("Database connection failed.");
    }

    switch ($action) {
        case 'get_faculties':
            $stmt = $conn->prepare("SELECT faculty_id, faculty_name FROM faculties ORDER BY faculty_name ASC");
            $stmt->execute();
            $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['status'] = 'success';
            break;

        case 'get_departments':
            $faculty_id = $_GET['faculty_id'] ?? 0;
            $stmt = $conn->prepare("SELECT dept_id, dept_name FROM departments WHERE faculty_id = ? ORDER BY dept_name ASC");
            $stmt->execute([$faculty_id]);
            $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['status'] = 'success';
            break;

        case 'get_degrees':
            $dept_id = $_GET['dept_id'] ?? 0;
            $stmt = $conn->prepare("
                SELECT DISTINCT dt.degree_id, dt.degree_name 
                FROM degree_types dt
                JOIN courses c ON dt.degree_id = c.degree_id
                WHERE c.dept_id = ?
                ORDER BY dt.degree_name ASC
            ");
            $stmt->execute([$dept_id]);
            $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['status'] = 'success';
            break;

        case 'get_courses':
            $dept_id = $_GET['dept_id'] ?? 0;
            $degree_id = $_GET['degree_id'] ?? 0;
            $stmt = $conn->prepare("
                SELECT course_id, course_title 
                FROM courses 
                WHERE dept_id = ? AND degree_id = ? 
                ORDER BY course_title ASC
            ");
            $stmt->execute([$dept_id, $degree_id]);
            $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['status'] = 'success';
            break;

        default:
            $response['message'] = 'Invalid action';
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>