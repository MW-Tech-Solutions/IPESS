<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../admin/includes/db.php';
require_once __DIR__ . '/../../../app/helpers/auth.php';

// Check auth and role
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$sessionUserId = (int) $_SESSION['user_id'];
$sessionRole = $_SESSION['role'] ?? '';

if (!in_array(normalize_role($sessionRole), ['SUPER_ADMIN', 'ICT_ADMIN'], true)) {
    echo json_encode(['success' => false, 'message' => 'Forbidden. Insufficient permissions.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT permission_key, granted FROM user_permissions WHERE user_id = ?");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $overrides = [];
        foreach ($rows as $row) {
            $overrides[$row['permission_key']] = (int) $row['granted'];
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'overrides' => $overrides
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $overridesJson = $_POST['overrides'] ?? '[]';

    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
        exit;
    }

    $overrides = json_decode($overridesJson, true);
    if (!is_array($overrides)) {
        echo json_encode(['success' => false, 'message' => 'Invalid overrides format.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Clear old overrides
        $delStmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $delStmt->execute([$userId]);

        // 2. Insert new overrides
        if (!empty($overrides)) {
            $insStmt = $pdo->prepare("INSERT INTO user_permissions (user_id, permission_key, granted) VALUES (?, ?, ?)");
            foreach ($overrides as $item) {
                $key = trim($item['permission_key'] ?? '');
                $granted = (int) ($item['granted'] ?? 1);
                if ($key !== '') {
                    $insStmt->execute([$userId, $key, $granted]);
                }
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Overrides updated successfully.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
?>
