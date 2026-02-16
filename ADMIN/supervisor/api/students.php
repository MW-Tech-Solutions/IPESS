<?php
session_start();
require_once __DIR__ . '/../../admin/includes/db.php';

header('Content-Type: application/json');

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

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

if ($action === 'list') {
    $supervisorUserId = $_SESSION['user_id'] ?? null;
    if (!$supervisorUserId) {
        echo json_encode(['success' => false, 'message' => 'Supervisor session missing.']);
        exit;
    }

    if (!table_exists($pdo, 'supervisor_students')) {
        echo json_encode(['success' => false, 'message' => 'Supervisor students table not available.']);
        exit;
    }

    $useUserId = column_exists($pdo, 'supervisor_students', 'supervisor_user_id');
    $supervisorInfo = resolve_supervisor_name($pdo, (int) $supervisorUserId);
    $supervisorName = $supervisorInfo['name'] ?? '';

    if (!$useUserId && $supervisorName === '') {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    if ($useUserId) {
        $stmt = $pdo->prepare("
            SELECT student_id, full_name, programme, current_chapter, status, last_submission,
                   email, progress_pct, supervisor_name, notes, updated_at, student_user_id
            FROM supervisor_students
            WHERE supervisor_user_id = ?
            ORDER BY full_name ASC
        ");
        $stmt->execute([(int) $supervisorUserId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT student_id, full_name, programme, current_chapter, status, last_submission,
                   email, progress_pct, supervisor_name, notes, updated_at, student_user_id
            FROM supervisor_students
            WHERE supervisor_name = ?
            ORDER BY full_name ASC
        ");
        $stmt->execute([$supervisorName]);
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($row) {
        return [
            'student_id' => $row['student_id'],
            'student_user_id' => $row['student_user_id'] ?? null,
            'full_name' => $row['full_name'],
            'programme' => $row['programme'],
            'current_chapter' => $row['current_chapter'] ?? '',
            'status' => $row['status'] ?? 'Pending Review',
            'last_submission' => $row['last_submission'] ?: ($row['updated_at'] ? date('Y-m-d', strtotime($row['updated_at'])) : null),
            'email' => $row['email'] ?? '',
            'progress_pct' => $row['progress_pct'] ?? null,
            'supervisor_name' => $row['supervisor_name'] ?? '',
            'notes' => $row['notes'] ?? ''
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

if ($action === 'status') {
    $studentId = trim($_POST['student_id'] ?? '');
    $status = $_POST['status'] ?? 'Pending Review';
    $notes = $_POST['notes'] ?? null;

    if ($studentId === '') {
        echo json_encode(['success' => false, 'message' => 'Missing student id.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE supervisor_students SET status = ?, notes = ?, updated_at = NOW() WHERE student_id = ?");
    $stmt->execute([$status, $notes, $studentId]);

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'message') {
    $studentId = $_POST['student_id'] ?? '';
    $message = trim($_POST['message'] ?? '');
    if ($studentId === '' || $message === '') {
        echo json_encode(['success' => false, 'message' => 'Missing message details.']);
        exit;
    }

    if (!table_exists($pdo, 'supervisor_messages')) {
        echo json_encode(['success' => false, 'message' => 'Supervisor messages table not available.']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO supervisor_messages (student_id, message, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$studentId, $message]);

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unsupported action.']);
