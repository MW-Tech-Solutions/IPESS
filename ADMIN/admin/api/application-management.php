<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

$action = $_GET['action'] ?? '';

try {
    if (!$pdo) {
        echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
        exit;
    }

    if ($action === 'faculties') {
        if (table_exists($pdo, 'faculties')) {
            $rows = $pdo->query("SELECT faculty_id AS id, faculty_name AS name FROM faculties ORDER BY faculty_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $rows = $pdo->query("SELECT DISTINCT faculty AS id, faculty AS name FROM programme_choices ORDER BY faculty ASC")->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    if ($action === 'departments') {
        $facultyId = $_GET['faculty_id'] ?? '';
        if (table_exists($pdo, 'departments')) {
            if ($facultyId !== '') {
                $stmt = $pdo->prepare("SELECT dept_id AS id, dept_name AS name FROM departments WHERE faculty_id = ? ORDER BY dept_name ASC");
                $stmt->execute([$facultyId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $rows = $pdo->query("SELECT dept_id AS id, dept_name AS name FROM departments ORDER BY dept_name ASC")->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            $rows = $pdo->query("SELECT DISTINCT department AS id, department AS name FROM programme_choices ORDER BY department ASC")->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    if ($action === 'modes') {
        if (table_exists($pdo, 'study_modes')) {
            $rows = $pdo->query("SELECT mode_id AS id, mode_name AS name FROM study_modes ORDER BY mode_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $rows = $pdo->query("SELECT DISTINCT mode_of_study AS id, mode_of_study AS name FROM programme_choices ORDER BY mode_of_study ASC")->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    if ($action === 'degrees') {
        if (table_exists($pdo, 'degree_types')) {
            $rows = $pdo->query("SELECT degree_id AS id, degree_name AS name FROM degree_types ORDER BY degree_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $rows = $pdo->query("SELECT DISTINCT degree_type AS id, degree_type AS name FROM programme_choices ORDER BY degree_type ASC")->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
