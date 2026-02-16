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
    if (!table_exists($pdo, 'supervisor_students')) {
        echo json_encode(['success' => false, 'message' => 'Supervisor students table not available.']);
        exit;
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId === 0) {
        echo json_encode(['success' => false, 'message' => 'Supervisor session missing.']);
        exit;
    }

    $useUserId = column_exists($pdo, 'supervisor_students', 'supervisor_user_id');
    $nameInfo = resolve_supervisor_name($pdo, $userId);
    $supervisorName = $nameInfo['name'] ?? '';

    if (!$useUserId && $supervisorName === '') {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    if ($useUserId) {
        $stmt = $pdo->prepare("
            SELECT student_id, full_name AS student_name, current_chapter AS chapter,
                   status, notes AS supervisor_note, updated_at, student_user_id
            FROM supervisor_students
            WHERE supervisor_user_id = ?
            ORDER BY updated_at DESC
        ");
        $stmt->execute([$userId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT student_id, full_name AS student_name, current_chapter AS chapter,
                   status, notes AS supervisor_note, updated_at, student_user_id
            FROM supervisor_students
            WHERE supervisor_name = ?
            ORDER BY updated_at DESC
        ");
        $stmt->execute([$supervisorName]);
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

if ($action === 'update') {
    $studentId = trim($_POST['student_id'] ?? '');
    $status = $_POST['status'] ?? 'Pending Review';
    $note = $_POST['supervisor_note'] ?? null;
    if ($studentId === '') {
        echo json_encode(['success' => false, 'message' => 'Missing student id.']);
        exit;
    }
    $useStudentUserId = column_exists($pdo, 'supervisor_students', 'student_user_id');
    $keyCol = $useStudentUserId ? 'student_user_id' : 'student_id';
    $stmt = $pdo->prepare("UPDATE supervisor_students SET status = ?, notes = ?, updated_at = NOW() WHERE {$keyCol} = ?");
    $stmt->execute([$status, $note, $studentId]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'export') {
    if (!table_exists($pdo, 'supervisor_students')) {
        echo json_encode(['success' => false, 'message' => 'Supervisor students table not available.']);
        exit;
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $nameInfo = resolve_supervisor_name($pdo, $userId);
    $supervisorName = $nameInfo['name'] ?? '';

    $stmt = $pdo->prepare("
        SELECT full_name AS student_name, current_chapter AS chapter, status, notes AS supervisor_note, updated_at
        FROM supervisor_students
        WHERE supervisor_name = ?
        ORDER BY updated_at DESC
    ");
    $stmt->execute([$supervisorName]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=supervisor-progress-' . date('Ymd-His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Student', 'Chapter', 'Status', 'Supervisor Note', 'Updated']);
    foreach ($rows as $row) {
        fputcsv($output, [$row['student_name'], $row['chapter'], $row['status'], $row['supervisor_note'], $row['updated_at']]);
    }
    fclose($output);
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
