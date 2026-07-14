<?php
/**
 * Reset Authenticator API
 * ─────────────────────────────────────────────────────────────────
 * Allows users with the `reset_authenticator` permission to clear
 * the TOTP secret for any staff or applicant account.
 *
 * Does NOT require knowledge of the target user's password.
 * All actions are logged to audit_logs.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';
header('Content-Type: application/json');

// ── Auth guard ────────────────────────────────────────────────────
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}
if (!has_permission('reset_authenticator')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to reset authenticator apps.']);
    exit;
}

try {
    $pdo = db();
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'search';

// ── SEARCH: find a user by email/username ─────────────────────────
if ($action === 'search') {
    $query = trim($_GET['q'] ?? '');
    if (strlen($query) < 2) {
        echo json_encode(['success' => false, 'message' => 'Enter at least 2 characters to search.']);
        exit;
    }
    try {
        $like = '%' . $query . '%';

        // Search staff users (joining roles table for correct role name)
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.full_name, u.email, r.role_name AS role,
                   CASE WHEN u.totp_secret IS NOT NULL THEN 1 ELSE 0 END AS has_totp
            FROM users u
            LEFT JOIN roles r ON r.role_id = u.role_id
            WHERE u.email LIKE ? OR u.full_name LIKE ?
            LIMIT 20
        ");
        $stmt->execute([$like, $like]);
        $staffRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $staffRows]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ── RESET: clear the totp_secret for a specific user ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reset') {
    $targetUserId  = (int) ($_POST['user_id'] ?? 0);
    $targetType    = $_POST['user_type'] ?? 'staff'; // 'staff' | 'applicant'

    if ($targetUserId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
        exit;
    }

    // Prevent resetting SUPER_ADMIN accounts unless actor is SUPER_ADMIN
    $currentRole = current_user_role();
    if ($currentRole !== 'SUPER_ADMIN') {
        $checkStmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ? LIMIT 1");
        $checkStmt->execute([$targetUserId]);
        $targetRole = normalize_role((string)($checkStmt->fetchColumn() ?: ''));
        if (in_array($targetRole, ['SUPER_ADMIN', 'ICT_ADMIN'], true)) {
            echo json_encode(['success' => false, 'message' => 'You cannot reset the authenticator of a Super Admin or ICT Admin account.']);
            exit;
        }
    }

    try {
        $pdo->beginTransaction();

        $table = ($targetType === 'applicant') ? 'applicant_accounts' : 'users';
        $idCol = 'user_id';

        // Check totp_secret column exists
        $colCheck = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE 'totp_secret'");
        if (!$colCheck || $colCheck->rowCount() === 0) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => "The '{$table}' table does not have a totp_secret column."]);
            exit;
        }

        // Fetch target user info for logging
        $infoStmt = $pdo->prepare("SELECT email FROM `{$table}` WHERE `{$idCol}` = ? LIMIT 1");
        $infoStmt->execute([$targetUserId]);
        $targetEmail = $infoStmt->fetchColumn() ?: 'Unknown';

        // Clear the TOTP secret
        $resetStmt = $pdo->prepare("UPDATE `{$table}` SET totp_secret = NULL WHERE `{$idCol}` = ?");
        $resetStmt->execute([$targetUserId]);

        // Log to audit_logs if the table exists
        try {
            $actorId    = (int) ($_SESSION['user_id'] ?? 0);
            $actorEmail = $_SESSION['email'] ?? 'unknown';
            $logStmt = $pdo->prepare("
                INSERT INTO audit_logs (actor_user_id, action, details, ip_address, created_at)
                VALUES (?, 'RESET_AUTHENTICATOR', ?, ?, NOW())
            ");
            $logStmt->execute([
                $actorId,
                "Authenticator reset for user #{$targetUserId} ({$targetEmail}) by {$actorEmail}",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
        } catch (Throwable $_) {
            // Audit log failure is non-fatal
        }

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => "Authenticator app successfully reset for {$targetEmail}. They can now set up a new one on next login.",
        ]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
