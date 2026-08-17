<?php
/**
 * Super Admin Application Progress Override API
 * POST /ADMIN/admin/api/update_progress.php
 *
 * Allows a Super Admin to manually set any application stage to any status,
 * or add a brand-new custom stage to the 6-step tracking pipeline.
 *
 * Required POST params:
 *   application_id  int
 *   action          string  set_stage | add_stage | remove_stage
 *   stage           string  (required for set_stage, add_stage, remove_stage)
 *   stage_status    string  (required for set_stage, add_stage): Pending | In Progress | Completed | Approved | Rejected
 *   new_stage_name  string  (required for add_stage — custom label)
 *   note            string  (optional admin note saved to audit log)
 */

session_start();

header('Content-Type: application/json');

// ─── Auth guard ──────────────────────────────────────────────────────────────
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$current_role = strtoupper(trim($_SESSION['role'] ?? ''));
$allowed_roles = ['SUPER_ADMIN', 'ICT_ADMIN'];
if (!in_array($current_role, $allowed_roles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden: Super Admin only']);
    exit;
}

require_once dirname(__DIR__, 3) . '/db.php';
require_once dirname(__DIR__, 3) . '/classes/ApplicationProgressManager.php';

// ─── Input validation ────────────────────────────────────────────────────────
$application_id = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
$action         = trim($_POST['action'] ?? '');
$stage          = trim($_POST['stage'] ?? '');
$stage_status   = trim($_POST['stage_status'] ?? '');
$new_stage_name = trim($_POST['new_stage_name'] ?? '');
$admin_note     = trim($_POST['note'] ?? '');

if (!$application_id || $application_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
    exit;
}
if (!in_array($action, ['set_stage', 'add_stage', 'remove_stage'], true)) {
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

$valid_statuses = ['Pending', 'In Progress', 'Completed', 'Approved', 'Rejected'];

function table_exists_local(PDO $pdo, string $table): bool {
    try {
        $sanitizedTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $pdo->query("SELECT 1 FROM `{$sanitizedTable}` LIMIT 0");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function log_audit(PDO $pdo, int $admin_id, int $app_id, string $action, string $detail): void {
    try {
        if (table_exists_local($pdo, 'admin_audit_log')) {
            $pdo->prepare("INSERT INTO admin_audit_log (admin_id, application_id, action, detail, created_at) VALUES (?, ?, ?, ?, NOW())")
                ->execute([$admin_id, $app_id, $action, $detail]);
        }
    } catch (Exception $e) {
        error_log('Audit log error: ' . $e->getMessage());
    }
}

$admin_id = (int) ($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);

// ─── Execute action ──────────────────────────────────────────────────────────
try {
    if ($action === 'set_stage') {
        // ─── Set a stage's status ─────────────────────────────────────────────
        if (empty($stage) || empty($stage_status)) {
            echo json_encode(['success' => false, 'message' => 'stage and stage_status are required']);
            exit;
        }
        if (!in_array($stage_status, $valid_statuses, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid stage_status value']);
            exit;
        }

        if (!table_exists_local($pdo, 'application_progress')) {
            echo json_encode(['success' => false, 'message' => 'application_progress table does not exist']);
            exit;
        }

        // Check if row exists
        $check = $pdo->prepare("SELECT progress_id FROM application_progress WHERE application_id = ? AND stage = ?");
        $check->execute([$application_id, $stage]);

        if ($check->fetch()) {
            $pdo->prepare("UPDATE application_progress SET stage_status = ?, stage_updated_at = NOW() WHERE application_id = ? AND stage = ?")
                ->execute([$stage_status, $application_id, $stage]);
        } else {
            $pdo->prepare("INSERT INTO application_progress (application_id, stage, stage_status, stage_updated_at) VALUES (?, ?, ?, NOW())")
                ->execute([$application_id, $stage, $stage_status]);
        }

        log_audit($pdo, $admin_id, $application_id, 'set_stage',
            "Stage '{$stage}' set to '{$stage_status}'" . ($admin_note ? " — {$admin_note}" : ''));

        echo json_encode(['success' => true, 'message' => "Stage '{$stage}' updated to '{$stage_status}'"]);

    } elseif ($action === 'add_stage') {
        // ─── Add a custom extra stage ─────────────────────────────────────────
        if (empty($new_stage_name) || empty($stage_status)) {
            echo json_encode(['success' => false, 'message' => 'new_stage_name and stage_status are required']);
            exit;
        }
        if (!in_array($stage_status, $valid_statuses, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid stage_status value']);
            exit;
        }
        if (strlen($new_stage_name) > 100) {
            echo json_encode(['success' => false, 'message' => 'Stage name too long (max 100 chars)']);
            exit;
        }

        if (!table_exists_local($pdo, 'application_progress')) {
            echo json_encode(['success' => false, 'message' => 'application_progress table does not exist']);
            exit;
        }

        $check = $pdo->prepare("SELECT progress_id FROM application_progress WHERE application_id = ? AND stage = ?");
        $check->execute([$application_id, $new_stage_name]);

        if ($check->fetch()) {
            $pdo->prepare("UPDATE application_progress SET stage_status = ?, stage_updated_at = NOW() WHERE application_id = ? AND stage = ?")
                ->execute([$stage_status, $application_id, $new_stage_name]);
        } else {
            $pdo->prepare("INSERT INTO application_progress (application_id, stage, stage_status, stage_updated_at) VALUES (?, ?, ?, NOW())")
                ->execute([$application_id, $new_stage_name, $stage_status]);
        }

        log_audit($pdo, $admin_id, $application_id, 'add_stage',
            "Custom stage '{$new_stage_name}' added/updated with status '{$stage_status}'" . ($admin_note ? " — {$admin_note}" : ''));

        echo json_encode(['success' => true, 'message' => "Custom stage '{$new_stage_name}' added/updated"]);

    } elseif ($action === 'remove_stage') {
        // ─── Remove a custom extra stage ──────────────────────────────────────
        if (empty($stage)) {
            echo json_encode(['success' => false, 'message' => 'stage is required']);
            exit;
        }
        // Prevent removing core stages
        $core_stages = ApplicationProgressManager::ALL_STAGES;
        if (in_array($stage, $core_stages, true)) {
            echo json_encode(['success' => false, 'message' => "Cannot remove core stage '{$stage}'."]);
            exit;
        }

        $del = $pdo->prepare("DELETE FROM application_progress WHERE application_id = ? AND stage = ?");
        $del->execute([$application_id, $stage]);

        log_audit($pdo, $admin_id, $application_id, 'remove_stage',
            "Custom stage '{$stage}' removed" . ($admin_note ? " — {$admin_note}" : ''));

        echo json_encode(['success' => true, 'message' => "Stage '{$stage}' removed"]);
    }

} catch (PDOException $e) {
    error_log('update_progress API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
