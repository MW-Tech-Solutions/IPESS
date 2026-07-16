<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once '../includes/db.php';
require_once __DIR__ . '/../../../config/urls.php';
require_once __DIR__ . '/../../../includes/permissions.php';

header('Content-Type: application/json');

if (!is_logged_in() || normalize_role(current_user_role()) === 'STUDENT') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.', 'data' => []]);
    exit;
}

if (!has_permission('view_applications')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: View Records duty not assigned.', 'data' => []]);
    exit;
}

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable.', 'data' => []]);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userDeptId = null;
if ($userId > 0) {
    try {
        $stmtDept = $pdo->prepare("SELECT department_id FROM users WHERE user_id = ? LIMIT 1");
        $stmtDept->execute([$userId]);
        $userDeptId = $stmtDept->fetchColumn();
        if ($userDeptId) {
            $userDeptId = (int) $userDeptId;
        } else {
            $userDeptId = null;
        }
    } catch (Throwable $e) {}
}

$q = trim((string) ($_GET['q'] ?? ''));
if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

$like = '%' . $q . '%';
$results = [];

try {
    // 1. Search Applications
    $hasPersonal = false;
    try {
        $hasPersonal = (bool) $pdo->query("SELECT 1 FROM personal_details LIMIT 1");
    } catch (Throwable $e) {}

    $nameSelect = $hasPersonal
        ? "COALESCE(CONCAT(p.first_name, ' ', p.surname), u.full_name, u.email) AS applicant_name"
        : "COALESCE(u.full_name, u.email) AS applicant_name";
    $nameJoin = $hasPersonal ? "LEFT JOIN personal_details p ON p.application_id = a.application_id" : "";

    $sql = "
        SELECT a.application_id, a.application_number, a.status, u.email, {$nameSelect}
        FROM applications a
        JOIN users u ON u.user_id = a.user_id
        {$nameJoin}
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
        WHERE (a.application_number LIKE ? OR u.email LIKE ? OR COALESCE(u.full_name, '') LIKE ? " . 
        ($hasPersonal ? "OR COALESCE(p.first_name, '') LIKE ? OR COALESCE(p.surname, '') LIKE ?" : "") . ")
    ";
    
    $params = [$like, $like, $like];
    if ($hasPersonal) {
        $params[] = $like;
        $params[] = $like;
    }

    if ($userDeptId !== null) {
        $sql .= " AND (pc.department = ? OR a.department_id = ?)";
        $params[] = $userDeptId;
        $params[] = $userDeptId;
    }

    $sql .= " GROUP BY a.application_id ORDER BY a.application_id DESC LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'type' => 'Application',
            'label' => ($row['application_number'] ?: ('App #' . (int) $row['application_id'])) . ' - ' . ($row['applicant_name'] ?: $row['email']),
            'meta' => 'Status: ' . ($row['status'] ?: 'Draft'),
            'url' => '/ADMIN/view.php?app_no=' . urlencode($row['application_number'])
        ];
    }

    // 2. Search Referees
    $refSql = "
        SELECT r.referee_id, r.full_name, r.email, a.application_id, a.application_number
        FROM referees r
        JOIN applications a ON a.application_id = r.application_id
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
        WHERE (COALESCE(r.full_name, '') LIKE ? OR COALESCE(r.email, '') LIKE ?)
    ";
    
    $refParams = [$like, $like];
    if ($userDeptId !== null) {
        $refSql .= " AND (pc.department = ? OR a.department_id = ?)";
        $refParams[] = $userDeptId;
        $refParams[] = $userDeptId;
    }
    
    $refSql .= " ORDER BY r.referee_id DESC LIMIT 8";
    
    $stmt = $pdo->prepare($refSql);
    $stmt->execute($refParams);
    
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'type' => 'Referee',
            'label' => ($row['full_name'] ?: 'Referee') . ' (' . ($row['email'] ?: 'No email') . ')',
            'meta' => 'Application: ' . ($row['application_number'] ?: ('#' . (int) $row['application_id'])),
            'url' => '/ADMIN/view.php?app_no=' . urlencode($row['application_number'])
        ];
    }

    // Deduplicate
    $seen = [];
    $deduped = [];
    foreach ($results as $item) {
        $key = $item['type'] . '|' . $item['label'] . '|' . $item['url'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $deduped[] = $item;
        if (count($deduped) >= 15) {
            break;
        }
    }

    echo json_encode(['success' => true, 'data' => $deduped]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Search failed.', 'data' => []]);
}
