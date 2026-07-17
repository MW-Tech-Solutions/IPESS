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

function recalc_supervisor_load(PDO $pdo, string $supervisor_id): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM supervisor_assignments WHERE supervisor_id = ? AND status = 'Assigned'");
    $stmt->execute([$supervisor_id]);
    return (int) $stmt->fetchColumn();
}

if ($action === 'list') {
    $deptId = null;
    if (isset($_SESSION['department_id']) || isset($_SESSION['dept_id'])) {
        $deptId = $_SESSION['department_id'] ?? $_SESSION['dept_id'];
    } else if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT department_id FROM users WHERE user_id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $dVal = $stmt->fetchColumn();
            if ($dVal !== false && $dVal !== null) {
                $_SESSION['department_id'] = (int) $dVal;
                $deptId = (int) $dVal;
            }
        } catch (Throwable $e) {}
    }

    if ($deptId) {
        $stmt = $pdo->prepare("SELECT supervisor_id, full_name, title, specialization, max_capacity, current_students, status, email, phone, research_interests, notes FROM supervisor_profiles WHERE department_id = ? ORDER BY full_name");
        $stmt->execute([$deptId]);
    } else {
        $stmt = $pdo->query("SELECT supervisor_id, full_name, title, specialization, max_capacity, current_students, status, email, phone, research_interests, notes FROM supervisor_profiles ORDER BY full_name");
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

if ($action === 'save') {
    $id = trim((string) ($_POST['supervisor_id'] ?? ''));
    $payload = [
        'full_name' => $_POST['full_name'] ?? '',
        'title' => $_POST['title'] ?? null,
        'specialization' => $_POST['specialization'] ?? null,
        'max_capacity' => (int) ($_POST['max_capacity'] ?? 8),
        'status' => $_POST['status'] ?? 'Active',
        'email' => $_POST['email'] ?? null,
        'phone' => $_POST['phone'] ?? null,
        'research_interests' => $_POST['research_interests'] ?? null,
        'notes' => $_POST['notes'] ?? null,
        'last_active' => $_POST['last_active'] ?? 'just now',
    ];

    if ($payload['full_name'] === '') {
        echo json_encode(['success' => false, 'message' => 'Missing supervisor details.']);
        exit;
    }

    // Determine department and faculty ID
    $deptId = null;
    if (isset($_SESSION['department_id']) || isset($_SESSION['dept_id'])) {
        $deptId = $_SESSION['department_id'] ?? $_SESSION['dept_id'];
    } else if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT department_id FROM users WHERE user_id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $dVal = $stmt->fetchColumn();
            if ($dVal !== false && $dVal !== null) {
                $_SESSION['department_id'] = (int) $dVal;
                $deptId = (int) $dVal;
            }
        } catch (Throwable $e) {}
    }

    $facultyId = null;
    if ($deptId) {
        $stmtFac = $pdo->prepare("SELECT faculty_id FROM departments WHERE dept_id = ? LIMIT 1");
        $stmtFac->execute([$deptId]);
        $fVal = $stmtFac->fetchColumn();
        if ($fVal !== false) {
            $facultyId = (int) $fVal;
        }
    }

    // Check if it already exists
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM supervisor_profiles WHERE supervisor_id = ?");
    $stmtCheck->execute([$id]);
    $exists = (int) $stmtCheck->fetchColumn();

    if ($exists > 0) {
        $stmt = $pdo->prepare("UPDATE supervisor_profiles SET full_name = ?, title = ?, specialization = ?, max_capacity = ?, status = ?, email = ?, phone = ?, research_interests = ?, notes = ?, last_active = ?, department_id = ?, faculty_id = ? WHERE supervisor_id = ?");
        $stmt->execute([
            $payload['full_name'], $payload['title'], $payload['specialization'], $payload['max_capacity'], $payload['status'], $payload['email'], $payload['phone'], $payload['research_interests'], $payload['notes'], $payload['last_active'], $deptId, $facultyId, $id
        ]);
    } else {
        if ($id === '') {
            $id = 'SUP-' . time() . '-' . rand(100, 999);
        }
        $stmt = $pdo->prepare("INSERT INTO supervisor_profiles (supervisor_id, full_name, title, specialization, max_capacity, status, email, phone, research_interests, notes, last_active, department_id, faculty_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $id, $payload['full_name'], $payload['title'], $payload['specialization'], $payload['max_capacity'], $payload['status'], $payload['email'], $payload['phone'], $payload['research_interests'], $payload['notes'], $payload['last_active'], $deptId, $facultyId
        ]);
    }

    $current = recalc_supervisor_load($pdo, $id);
    $pdo->prepare("UPDATE supervisor_profiles SET current_students = ? WHERE supervisor_id = ?")->execute([$current, $id]);

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'status') {
    $id = trim((string) ($_POST['supervisor_id'] ?? ''));
    $status = $_POST['status'] ?? 'Active';
    if ($id === '') {
        echo json_encode(['success' => false, 'message' => 'Missing supervisor id.']);
        exit;
    }
    $pdo->prepare("UPDATE supervisor_profiles SET status = ? WHERE supervisor_id = ?")->execute([$status, $id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'notify') {
    $id = trim((string) ($_POST['supervisor_id'] ?? ''));
    $message = trim($_POST['message'] ?? '');
    if ($id === '' || $message === '') {
        echo json_encode(['success' => false, 'message' => 'Missing notification message.']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT user_id FROM supervisor_profiles WHERE supervisor_id = ?");
    $stmt->execute([$id]);
    $userId = $stmt->fetchColumn();
    if ($userId) {
        notify_user($pdo, (int) $userId, 'Supervisor Notice', $message, 'info');
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'balance') {
    $rows = $pdo->query("SELECT supervisor_id FROM supervisor_profiles")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $current = recalc_supervisor_load($pdo, $row['supervisor_id']);
        $pdo->prepare("UPDATE supervisor_profiles SET current_students = ? WHERE supervisor_id = ?")->execute([$current, $row['supervisor_id']]);
    }
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unsupported action.']);
