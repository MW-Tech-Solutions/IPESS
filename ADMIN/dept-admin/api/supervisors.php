<?php
session_start();
require_once __DIR__ . '/../../admin/includes/db.php';
require_once __DIR__ . '/../../../includes/status_engine.php';

header('Content-Type: application/json');

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function recalc_supervisor_load(PDO $pdo, int $supervisor_id): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM supervisor_assignments WHERE supervisor_id = ? AND status = 'Assigned'");
    $stmt->execute([$supervisor_id]);
    return (int) $stmt->fetchColumn();
}

if ($action === 'list') {
    $stmt = $pdo->query("SELECT supervisor_id, full_name, title, specialization_keywords, max_capacity, current_students, status, email, phone, research_interests, notes FROM supervisors ORDER BY full_name");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

if ($action === 'save') {
    $id = (int) ($_POST['supervisor_id'] ?? 0);
    $payload = [
        'full_name' => $_POST['full_name'] ?? '',
        'title' => $_POST['title'] ?? null,
        'specialization_keywords' => $_POST['specialization'] ?? null,
        'max_capacity' => (int) ($_POST['max_capacity'] ?? 8),
        'status' => $_POST['status'] ?? 'Active',
        'email' => $_POST['email'] ?? null,
        'phone' => $_POST['phone'] ?? null,
        'research_interests' => $_POST['research_interests'] ?? null,
        'notes' => $_POST['notes'] ?? null,
    ];

    if ($payload['full_name'] === '') {
        echo json_encode(['success' => false, 'message' => 'Missing supervisor details.']);
        exit;
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE supervisors SET full_name = ?, title = ?, specialization_keywords = ?, max_capacity = ?, status = ?, email = ?, phone = ?, research_interests = ?, notes = ? WHERE supervisor_id = ?");
        $stmt->execute([
            $payload['full_name'], $payload['title'], $payload['specialization_keywords'], $payload['max_capacity'], $payload['status'], $payload['email'], $payload['phone'], $payload['research_interests'], $payload['notes'], $id
        ]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO supervisors (full_name, title, specialization_keywords, max_capacity, status, email, phone, research_interests, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $payload['full_name'], $payload['title'], $payload['specialization_keywords'], $payload['max_capacity'], $payload['status'], $payload['email'], $payload['phone'], $payload['research_interests'], $payload['notes']
        ]);
        $id = (int) $pdo->lastInsertId();
    }

    $current = recalc_supervisor_load($pdo, $id);
    $pdo->prepare("UPDATE supervisors SET current_students = ? WHERE supervisor_id = ?")->execute([$current, $id]);

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'status') {
    $id = (int) ($_POST['supervisor_id'] ?? 0);
    $status = $_POST['status'] ?? 'Active';
    if ($id === 0) {
        echo json_encode(['success' => false, 'message' => 'Missing supervisor id.']);
        exit;
    }
    $pdo->prepare("UPDATE supervisors SET status = ? WHERE supervisor_id = ?")->execute([$status, $id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'notify') {
    $id = (int) ($_POST['supervisor_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    if ($id === 0 || $message === '') {
        echo json_encode(['success' => false, 'message' => 'Missing notification message.']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT user_id FROM supervisors WHERE supervisor_id = ?");
    $stmt->execute([$id]);
    $userId = $stmt->fetchColumn();
    if ($userId) {
        notify_user($pdo, (int) $userId, 'Supervisor Notice', $message, 'info');
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'balance') {
    $rows = $pdo->query("SELECT supervisor_id FROM supervisors")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $current = recalc_supervisor_load($pdo, (int) $row['supervisor_id']);
        $pdo->prepare("UPDATE supervisors SET current_students = ? WHERE supervisor_id = ?")->execute([$current, (int) $row['supervisor_id']]);
    }
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unsupported action.']);
