<?php
session_start();
if (file_exists('db.php')) {
    require 'db.php';
}

// --- 1. BUILD FILTER QUERY ---
$whereClauses = [];
$params = [];

// Base SQL
$sql = "SELECT a.application_id, a.application_number, a.status, a.submitted_at, p.surname, p.first_name, pc.faculty, pc.department, pc.degree_type, pc.mode_of_study, pc.course FROM applications a LEFT JOIN personal_details p ON a.application_id = p.application_id LEFT JOIN programme_choices pc ON a.application_id = pc.application_id";

// Filter mapping
$filters = [
    'status' => 'a.status',
    'faculty' => 'pc.faculty',
    'department' => 'pc.department',
    'degree_type' => 'pc.degree_type',
    'mode_of_study' => 'pc.mode_of_study'
];

foreach ($filters as $getVar => $column) {
    if (!empty($_GET[$getVar])) {
        $whereClauses[] = "$column = ?";
        $params[] = $_GET[$getVar];
    }
}

// Search Filter
if (!empty($_GET['search'])) {
    $searchTerm = "%" . $_GET['search'] . "%";
    $whereClauses[] = "(p.first_name LIKE ? OR p.surname LIKE ? OR a.application_number LIKE ?)";
    array_push($params, $searchTerm, $searchTerm, $searchTerm);
}

if (count($whereClauses) > 0) {
    $whereStr = " WHERE " . implode(" AND ", $whereClauses);
    $sql .= $whereStr;
}

// --- 2. PAGINATION LOGIC ---
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 3;
$offset = ($page - 1) * $limit;

if (isset($pdo)) {
    try {
        $sql .= " GROUP BY a.application_id ORDER BY a.submitted_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($applications)) {
            foreach ($applications as $app) {
                $icon = 'fa-file-alt text-primary'; // Default for submitted
                if ($app['status'] === 'Admitted') {
                    $icon = 'fa-check-circle text-success';
                } elseif ($app['status'] === 'Rejected') {
                    $icon = 'fa-times-circle text-danger';
                } elseif ($app['status'] === 'Submitted') {
                    $icon = 'fa-clock text-warning';
                }

                echo '<div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas ' . $icon . '"></i>
                        </div>
                        <div class="activity-content">
                            <p>
                                Application ' . htmlspecialchars($app['status']) . ' for <strong>' . htmlspecialchars($app['first_name'] . ' ' . $app['surname']) . '</strong>
                                (<a href="view.php?app_no=' . urlencode($app['application_number']) . '">' . htmlspecialchars($app['application_number']) . '</a>)
                            </p>
                            <small class="text-muted">' . date('M d, Y, h:i A', strtotime($app['submitted_at'])) . '</small>
                        </div>
                    </div>';
            }
        }
    } catch (PDOException $e) {
        // In a real application, log this error instead of outputting it
        // die("Error: " . $e->getMessage());
    }
}
