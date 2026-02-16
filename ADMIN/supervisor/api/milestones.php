<?php
session_start();
require_once __DIR__ . '/../../admin/includes/db.php';

header('Content-Type: application/json');

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'list') {
    if (!table_exists($pdo, 'supervisor_milestones')) {
        echo json_encode(['success' => false, 'message' => 'Supervisor milestones table not available.']);
        exit;
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $supervisorName = '';
    if ($userId > 0) {
        $info = resolve_supervisor_name($pdo, $userId);
        $supervisorName = $info['name'] ?? '';
    }

    $status = trim((string) ($_GET['status'] ?? ''));
    $query = trim((string) ($_GET['q'] ?? ''));
    $allowedStatuses = ['Upcoming', 'Completed', 'Overdue'];
    if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
        $status = '';
    }

    $filters = [];
    $params = [];

    $useUserId = column_exists($pdo, 'supervisor_milestones', 'supervisor_user_id');
    if ($useUserId && $userId > 0) {
        $filters[] = 'supervisor_user_id = ?';
        $params[] = $userId;
    } elseif ($supervisorName !== '' && table_exists($pdo, 'supervisor_students')) {
        $filters[] = 'student_name IN (SELECT s.full_name FROM supervisor_students s WHERE s.supervisor_name = ?)';
        $params[] = $supervisorName;
    }

    if ($status !== '') {
        $filters[] = 'status = ?';
        $params[] = $status;
    }

    if ($query !== '') {
        $filters[] = '(student_name LIKE ? OR title LIKE ?)';
        $like = '%' . $query . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $where = $filters ? ('WHERE ' . implode(' AND ', $filters)) : '';
    $stmt = $pdo->prepare("SELECT * FROM supervisor_milestones $where ORDER BY (due_date IS NULL), due_date ASC, milestone_id DESC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

if ($action === 'status') {
    $id = (int) ($_POST['milestone_id'] ?? 0);
    $status = $_POST['status'] ?? 'Upcoming';
    if ($id === 0) {
        echo json_encode(['success' => false, 'message' => 'Missing milestone id.']);
        exit;
    }
    $stmt = $pdo->prepare("UPDATE supervisor_milestones SET status = ? WHERE milestone_id = ?");
    $stmt->execute([$status, $id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'create') {
    if (!table_exists($pdo, 'supervisor_milestones')) {
        echo json_encode(['success' => false, 'message' => 'Supervisor milestones table not available.']);
        exit;
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $studentUserId = (int) ($_POST['student_user_id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $dueDate = trim((string) ($_POST['due_date'] ?? ''));
    $note = trim((string) ($_POST['note'] ?? ''));

    if ($studentUserId === 0 || $title === '') {
        echo json_encode(['success' => false, 'message' => 'Student and title are required.']);
        exit;
    }

    $studentName = '';
    $applicationId = null;
    $applicationNumber = null;

    if (table_exists($pdo, 'supervisor_students')) {
        $stmt = $pdo->prepare("SELECT full_name, application_id, application_number FROM supervisor_students WHERE student_user_id = ? LIMIT 1");
        $stmt->execute([$studentUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $studentName = trim((string) ($row['full_name'] ?? ''));
        $applicationId = $row['application_id'] ?? null;
        $applicationNumber = $row['application_number'] ?? null;
    }

    if ($studentName === '' && table_exists($pdo, 'users')) {
        $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1");
        $stmt->execute([$studentUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $studentName = trim((string) ($row['full_name'] ?? ''));
        if ($studentName === '') {
            $studentName = trim((string) ($row['email'] ?? ''));
        }
    }

    if ($studentName === '') {
        echo json_encode(['success' => false, 'message' => 'Unable to resolve student name.']);
        exit;
    }

    $fields = ['student_name', 'title', 'due_date', 'status', 'note'];
    $values = [$studentName, $title, $dueDate !== '' ? $dueDate : null, 'Upcoming', $note !== '' ? $note : null];

    if (column_exists($pdo, 'supervisor_milestones', 'supervisor_user_id')) {
        $fields[] = 'supervisor_user_id';
        $values[] = $userId > 0 ? $userId : null;
    }
    if (column_exists($pdo, 'supervisor_milestones', 'student_user_id')) {
        $fields[] = 'student_user_id';
        $values[] = $studentUserId;
    }
    if (column_exists($pdo, 'supervisor_milestones', 'application_id')) {
        $fields[] = 'application_id';
        $values[] = $applicationId;
    }
    if (column_exists($pdo, 'supervisor_milestones', 'application_number')) {
        $fields[] = 'application_number';
        $values[] = $applicationNumber;
    }

    $placeholders = implode(',', array_fill(0, count($fields), '?'));
    $sql = "INSERT INTO supervisor_milestones (" . implode(',', $fields) . ") VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unsupported action.']);

function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function resolve_supervisor_name(PDO $pdo, int $userId): array {
    $name = '';
    $email = '';
    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $name = trim((string) ($row['full_name'] ?? ''));
    $email = trim((string) ($row['email'] ?? ''));

    if ($name === '' && $email !== '' && table_exists($pdo, 'supervisor_profiles')) {
        $stmt = $pdo->prepare("SELECT full_name FROM supervisor_profiles WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $name = trim((string) ($stmt->fetchColumn() ?: ''));
    }

    return [
        'name' => $name !== '' ? $name : $email,
        'email' => $email
    ];
}
