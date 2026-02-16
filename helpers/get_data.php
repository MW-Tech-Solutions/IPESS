<?php

ob_start();

require_once '../config/db.php'; 

header('Content-Type: application/json');

$response = [];

try {
    if (!isset($pdo)) {
        throw new Exception("Database connection failed.");
    }

    $action = $_GET['action'] ?? '';

    if ($action === 'get_depts' && !empty($_GET['faculty_id'])) {
        $stmt = $pdo->prepare("SELECT dept_id, dept_name FROM departments WHERE faculty_id = ? ORDER BY dept_name ASC");
        $stmt->execute([$_GET['faculty_id']]);
        $response = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($action === 'get_degrees' && !empty($_GET['dept_id'])) {
        $query = "SELECT DISTINCT dt.degree_id, dt.degree_name 
                  FROM degree_types dt 
                  JOIN courses c ON dt.degree_id = c.degree_id 
                  WHERE c.dept_id = ? 
                  ORDER BY dt.degree_name ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET['dept_id']]);
        $response = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($action === 'get_courses' && !empty($_GET['dept_id']) && !empty($_GET['degree_id'])) {
        $stmt = $pdo->prepare("SELECT course_id, course_title FROM courses WHERE dept_id = ? AND degree_id = ? ORDER BY course_title ASC");
        $stmt->execute([$_GET['dept_id'], $_GET['degree_id']]);
        $response = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $response = [];
    }

} catch (Exception $e) {
    $response = ['error' => $e->getMessage()];
}

ob_clean(); 
echo json_encode($response);
exit;
?>