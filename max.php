<?php
/**
 * Max.php - High Impact Testing SPA Dashboard
 * Includes access code protection, user impersonation, submission undo utility, database inspector,
 * and comprehensive Super Admin system controls (User CRUD, Session settings, Notices, and log cleanser).
 */

// Error reporting config for testing stability
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
$pdo = db();

// Access Code Authentication Logic
$auth_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_access_code'])) {
    $entered = trim($_POST['login_access_code']);
    $hash_val = '1859970737';
    // Check direct equality OR crc32 hash code match
    if ($entered === $hash_val || sprintf('%u', crc32($entered)) === $hash_val) {
        $_SESSION['max_authenticated'] = true;
        header("Location: max.php");
        exit;
    } else {
        $auth_error = 'Access code invalid. Try again.';
    }
}

// Session cleaning actions
if (isset($_GET['logout_max'])) {
    unset($_SESSION['max_authenticated']);
    header("Location: max.php");
    exit;
}

$is_authenticated = !empty($_SESSION['max_authenticated']);

// Helper to check if a table exists
if (!function_exists('max_table_exists')) {
    function max_table_exists(PDO $pdo, string $table): bool {
        try {
            $pdo->query("SELECT 1 FROM `{$table}` LIMIT 0");
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

// API Processing Endpoints
if ($is_authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    try {
        switch ($action) {
            case 'get_stats':
                $users_count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                $apps_count = (int)$pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
                $submitted_count = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'Submitted'")->fetchColumn();
                $draft_count = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'Draft'")->fetchColumn();
                
                $active_sessions = 0;
                if (max_table_exists($pdo, 'admission_sessions')) {
                    $active_sessions = (int)$pdo->query("SELECT COUNT(*) FROM admission_sessions WHERE is_active = 1")->fetchColumn();
                }
                
                $status_breakdown = $pdo->query("
                    SELECT current_status, COUNT(*) as count 
                    FROM applications 
                    GROUP BY current_status
                ")->fetchAll(PDO::FETCH_ASSOC);

                $audit_logs = [];
                if (max_table_exists($pdo, 'audit_logs')) {
                    $audit_logs = $pdo->query("
                        SELECT action, details, created_at 
                        FROM audit_logs 
                        ORDER BY log_id DESC 
                        LIMIT 8
                    ")->fetchAll(PDO::FETCH_ASSOC);
                }

                echo json_encode([
                    'success' => true,
                    'users_count' => $users_count,
                    'apps_count' => $apps_count,
                    'submitted_count' => $submitted_count,
                    'draft_count' => $draft_count,
                    'active_sessions' => $active_sessions,
                    'status_breakdown' => $status_breakdown,
                    'audit_logs' => $audit_logs
                ]);
                exit;

            case 'search_users':
                $q = '%' . trim($_POST['query'] ?? '') . '%';
                $stmt = $pdo->prepare("
                    SELECT u.user_id, u.email, u.full_name, u.totp_enabled, u.department_id, u.account_status, r.role_id, r.role_key, r.role_name 
                    FROM users u 
                    LEFT JOIN roles r ON u.role_id = r.role_id 
                    WHERE u.full_name LIKE ? OR u.email LIKE ? OR r.role_key LIKE ? OR r.role_name LIKE ?
                    ORDER BY u.user_id DESC
                    LIMIT 50
                ");
                $stmt->execute([$q, $q, $q, $q]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $results]);
                exit;

            case 'search_applications':
                $q = '%' . trim($_POST['query'] ?? '') . '%';
                $status = $_POST['status'] ?? 'all';
                
                $sql = "
                    SELECT a.application_id, a.application_number, a.status, a.current_status, a.submitted_at, a.completion_percentage, u.full_name, u.email 
                    FROM applications a 
                    JOIN users u ON a.user_id = u.user_id 
                    WHERE (a.application_number LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)
                ";
                $params = [$q, $q, $q];
                if ($status !== 'all') {
                    $sql .= " AND a.status = ?";
                    $params[] = $status;
                }
                $sql .= " ORDER BY a.updated_at DESC LIMIT 50";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $results]);
                exit;

            case 'edit_user':
                $user_id = (int)$_POST['user_id'];
                $full_name = trim($_POST['full_name']);
                $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
                $role_id = (int)$_POST['role_id'];
                $dept_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
                $status = $_POST['account_status'];

                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?, role_id = ?, department_id = ?, account_status = ? 
                    WHERE user_id = ?
                ");
                $stmt->execute([$full_name, $email, $role_id, $dept_id, $status, $user_id]);

                echo json_encode(['success' => true, 'message' => 'User account updated successfully.']);
                exit;

            case 'delete_user':
                $user_id = (int)$_POST['user_id'];
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
                exit;

            case 'change_app_status':
                $app_id = (int)$_POST['application_id'];
                $new_status = $_POST['new_status'];

                require_once __DIR__ . '/includes/status_engine.php';
                $actor_id = $_SESSION['user_id'] ?? null;
                $actor_role = $_SESSION['role'] ?? 'SUPER_ADMIN';

                $ok = update_application_status($pdo, $app_id, $new_status, [
                    'actor_id' => $actor_id,
                    'actor_role' => $actor_role,
                    'note' => 'Status manually overridden via max.php testing control panel'
                ]);

                echo json_encode(['success' => true, 'message' => "Application status updated to {$new_status}."]);
                exit;

            case 'undo_submission':
                $app_id = (int)$_POST['application_id'];

                $stmt = $pdo->prepare("SELECT user_id, current_status, status FROM applications WHERE application_id = ?");
                $stmt->execute([$app_id]);
                $app = $stmt->fetch();
                if (!$app) {
                    echo json_encode(['success' => false, 'message' => 'Application record not found.']);
                    exit;
                }

                require_once __DIR__ . '/includes/status_engine.php';
                $actor_id = $_SESSION['user_id'] ?? null;
                $actor_role = $_SESSION['role'] ?? 'SUPER_ADMIN';

                // Revert status to DRAFT
                $ok = update_application_status($pdo, $app_id, 'DRAFT', [
                    'actor_id' => $actor_id,
                    'actor_role' => $actor_role,
                    'note' => 'Submission undone via max.php testing dashboard'
                ]);

                $pdo->prepare("UPDATE applications SET submitted_at = NULL WHERE application_id = ?")->execute([$app_id]);

                if (max_table_exists($pdo, 'application_progress')) {
                    $pdo->prepare("UPDATE application_progress SET stage_status = 'Pending', stage_updated_at = NOW() WHERE application_id = ?")->execute([$app_id]);
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Application submission undone successfully. Status set back to Draft.'
                ]);
                exit;

            case 'update_session':
                if (!max_table_exists($pdo, 'admission_sessions')) {
                    echo json_encode(['success' => false, 'message' => 'Admission Sessions table does not exist in this database schema configuration.']);
                    exit;
                }
                $session_id = (int)$_POST['session_id'];
                $is_open = (int)$_POST['is_open'];
                $is_active = (int)$_POST['is_active'];
                $fee = (float)$_POST['application_fee'];
                $opens = !empty($_POST['opens_at']) ? $_POST['opens_at'] : null;
                $closes = !empty($_POST['closes_at']) ? $_POST['closes_at'] : null;

                $stmt = $pdo->prepare("
                    UPDATE admission_sessions 
                    SET is_open = ?, is_active = ?, application_fee = ?, opens_at = ?, closes_at = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$is_open, $is_active, $fee, $opens, $closes, $session_id]);
                echo json_encode(['success' => true, 'message' => 'Admission session updated successfully.']);
                exit;

            case 'add_notice':
                $title = trim($_POST['title']);
                $body = trim($_POST['body']);
                $published = (int)$_POST['is_published'];

                $table = max_table_exists($pdo, 'admissions_notices') ? 'admissions_notices' : 'notices';
                $stmt = $pdo->prepare("INSERT INTO `{$table}` (title, body, is_published) VALUES (?, ?, ?)");
                $stmt->execute([$title, $body, $published]);

                echo json_encode(['success' => true, 'message' => 'Notice added successfully.']);
                exit;

            case 'update_settings':
                $name = trim($_POST['institution_name']);
                $timeout = (int)$_POST['session_timeout'];

                $stmt = $pdo->prepare("UPDATE system_settings SET institution_name = ?, session_timeout_seconds = ?");
                $stmt->execute([$name, $timeout]);
                echo json_encode(['success' => true, 'message' => 'Settings updated successfully.']);
                exit;

            case 'clear_logs':
                if (max_table_exists($pdo, 'audit_logs')) {
                    $pdo->exec("TRUNCATE TABLE audit_logs");
                }
                if (max_table_exists($pdo, 'application_status_history')) {
                    $pdo->exec("TRUNCATE TABLE application_status_history");
                }
                if (max_table_exists($pdo, 'workflow_audit_logs')) {
                    $pdo->exec("TRUNCATE TABLE workflow_audit_logs");
                }
                echo json_encode(['success' => true, 'message' => 'System audit trails and status logs cleared successfully.']);
                exit;

            case 'impersonate_user':
                $user_id = (int)$_POST['user_id'];
                $stmt = $pdo->prepare("
                    SELECT u.user_id, u.email, u.full_name, r.role_key 
                    FROM users u 
                    LEFT JOIN roles r ON u.role_id = r.role_id 
                    WHERE u.user_id = ? 
                    LIMIT 1
                ");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                if (!$user) {
                    echo json_encode(['success' => false, 'message' => 'Target user details not found.']);
                    exit;
                }

                // Authenticate session as the selected user
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $user['role_key'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['last_activity'] = time();

                // If user is applicant, retrieve or initialize application row
                if ($user['role_key'] === 'STUDENT') {
                    $stmtApp = $pdo->prepare("
                        SELECT a.application_id, d.file_path as passport 
                        FROM applications a 
                        LEFT JOIN documents d 
                            ON a.application_id = d.application_id 
                            AND d.document_type IN ('passport_profile','passport')
                        WHERE a.user_id = ? 
                        ORDER BY 
                            CASE WHEN d.document_type = 'passport_profile' THEN 0 ELSE 1 END,
                            a.updated_at DESC
                        LIMIT 1
                    ");
                    $stmtApp->execute([$user['user_id']]);
                    $app_data = $stmtApp->fetch();
                    if ($app_data) {
                        $_SESSION['application_id'] = $app_data['application_id'];
                        $_SESSION['passport_path'] = $app_data['passport'];
                    } else {
                        $pdo->prepare("
                            INSERT INTO applications (user_id, status, current_step, completion_percentage, current_status) 
                            VALUES (?, 'Draft', 1, 0.00, 'DRAFT')
                        ")->execute([$user['user_id']]);
                        $new_app_id = $pdo->lastInsertId();
                        $_SESSION['application_id'] = $new_app_id;
                        $_SESSION['passport_path'] = null;

                        require_once __DIR__ . '/classes/ApplicationProgressManager.php';
                        $progManager = new ApplicationProgressManager($pdo);
                        $progManager->initializeApplication((int)$new_app_id);
                    }
                }

                echo json_encode([
                    'success' => true,
                    'message' => "Logged in as {$user['full_name']} ({$user['role_key']}). System session activated."
                ]);
                exit;

            case 'clear_impersonation':
                unset(
                    $_SESSION['user_id'], 
                    $_SESSION['role'], 
                    $_SESSION['user_email'], 
                    $_SESSION['email'], 
                    $_SESSION['full_name'], 
                    $_SESSION['application_id'], 
                    $_SESSION['passport_path']
                );
                echo json_encode(['success' => true, 'message' => 'Swapped role session cleared. Back to base state.']);
                exit;

            case 'create_test_user':
                $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
                $full_name = trim($_POST['full_name'] ?? '');
                $role_id = (int)$_POST['role_id'];
                $dept_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
                $password = trim($_POST['password'] ?? 'password123');

                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'message' => 'Valid email address required.']);
                    exit;
                }

                $stmtCheck = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmtCheck->execute([$email]);
                if ($stmtCheck->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Email address is already in use.']);
                    exit;
                }

                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $stmtInsert = $pdo->prepare("
                    INSERT INTO users (email, full_name, role_id, department_id, password_hash, account_status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'Active', NOW())
                ");
                $stmtInsert->execute([$email, $full_name, $role_id, $dept_id, $password_hash]);
                $new_uid = $pdo->lastInsertId();

                $role_stmt = $pdo->prepare("SELECT role_key FROM roles WHERE role_id = ?");
                $role_stmt->execute([$role_id]);
                $role_key = $role_stmt->fetchColumn();

                if ($role_key === 'STUDENT') {
                    $pdo->prepare("
                        INSERT INTO applications (user_id, status, current_step, completion_percentage, current_status) 
                        VALUES (?, 'Draft', 1, 0.00, 'DRAFT')
                    ")->execute([$new_uid]);
                    $app_id = $pdo->lastInsertId();

                    require_once __DIR__ . '/classes/ApplicationProgressManager.php';
                    $progManager = new ApplicationProgressManager($pdo);
                    $progManager->initializeApplication((int)$app_id);
                }

                echo json_encode([
                    'success' => true,
                    'message' => "Successfully created {$full_name} as {$role_key}!"
                ]);
                exit;

            case 'toggle_totp':
                $user_id = (int)$_POST['user_id'];
                $enabled = (int)$_POST['enabled'];

                $stmt = $pdo->prepare("UPDATE users SET totp_enabled = ? WHERE user_id = ?");
                $stmt->execute([$enabled, $user_id]);

                $status = $enabled ? 'enabled' : 'disabled';
                echo json_encode(['success' => true, 'message' => "MFA security has been {$status} for user ID {$user_id}."]);
                exit;

            case 'get_table_data':
                $table = $_POST['table_name'] ?? 'users';
                $allowed = ['users', 'applications', 'application_status_history', 'audit_logs', 'supervisors', 'referee_requests'];
                if (!in_array($table, $allowed, true)) {
                    echo json_encode(['success' => false, 'message' => 'Table access prohibited.']);
                    exit;
                }

                $rows = $pdo->query("SELECT * FROM `{$table}` ORDER BY 1 DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $rows]);
                exit;
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'API Error: ' . $e->getMessage()]);
        exit;
    }
}

// Fetch first 50 records for initial view states to keep DOM size tiny
$roles = [];
$users = [];
$applications = [];
$departments = [];
$supervisors = [];
$sessions_list = [];
$system_settings = [];
$overview_stats = [
    'users' => 0,
    'apps' => 0,
    'submitted' => 0,
    'draft' => 0,
    'active_sessions' => 0
];
$status_breakdown = [];
$audit_logs = [];

if ($is_authenticated) {
    try {
        $roles = $pdo->query("SELECT * FROM roles ORDER BY role_id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate counts directly from database to show immediately in HTML
        $overview_stats['users'] = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $overview_stats['apps'] = (int)$pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
        $overview_stats['submitted'] = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'Submitted'")->fetchColumn();
        $overview_stats['draft'] = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'Draft'")->fetchColumn();

        if (max_table_exists($pdo, 'admission_sessions')) {
            $overview_stats['active_sessions'] = (int)$pdo->query("SELECT COUNT(*) FROM admission_sessions WHERE is_active = 1")->fetchColumn();
            $sessions_list = $pdo->query("SELECT * FROM admission_sessions ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        }

        $status_breakdown = $pdo->query("
            SELECT current_status, COUNT(*) as count 
            FROM applications 
            GROUP BY current_status
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (max_table_exists($pdo, 'audit_logs')) {
            $audit_logs = $pdo->query("
                SELECT action, details, created_at 
                FROM audit_logs 
                ORDER BY log_id DESC 
                LIMIT 8
            ")->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Paginate users (first 50)
        $users = $pdo->query("
            SELECT u.user_id, u.email, u.full_name, u.totp_enabled, u.department_id, u.account_status, r.role_id, r.role_key, r.role_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.role_id 
            ORDER BY u.user_id DESC
            LIMIT 50
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Paginate applications (first 50)
        $applications = $pdo->query("
            SELECT a.application_id, a.application_number, a.status, a.current_status, a.submitted_at, a.completion_percentage, u.full_name, u.email 
            FROM applications a 
            JOIN users u ON a.user_id = u.user_id 
            ORDER BY a.updated_at DESC
            LIMIT 50
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (max_table_exists($pdo, 'supervisor_profiles')) {
            $supervisors = $pdo->query("SELECT sp.*, u.full_name FROM supervisor_profiles sp LEFT JOIN users u ON sp.email = u.email")->fetchAll(PDO::FETCH_ASSOC);
        }
        
        if (max_table_exists($pdo, 'system_settings')) {
            $system_settings = $pdo->query("SELECT * FROM system_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $e) {
        // Suppress initial query crashes
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Max Control Center & Testing Suite</title>
    <!-- Modern typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-base: #f1f5f9;
            --bg-surface: #ffffff;
            --bg-panel: #ffffff;
            --color-primary: #2563eb;
            --color-primary-hover: #1d4ed8;
            --color-primary-glow: rgba(37, 99, 235, 0.06);
            --color-success: #059669;
            --color-success-glow: rgba(5, 150, 105, 0.08);
            --color-warning: #d97706;
            --color-warning-glow: rgba(217, 119, 6, 0.08);
            --color-danger: #dc2626;
            --color-danger-glow: rgba(220, 38, 38, 0.08);
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: rgba(0, 0, 0, 0.08);
            --font-sans: 'Plus Jakarta Sans', -apple-system, sans-serif;
            --font-code: 'Space Grotesk', monospace;
            --sidebar-width: 260px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-base);
            color: var(--text-main);
            font-family: var(--font-sans);
            font-size: 14px;
            line-height: 1.5;
            overflow-x: hidden;
            height: 100vh;
        }

        input, select, button, textarea {
            font-family: inherit;
            color: inherit;
        }

        /* --- AUTHENTICATION LOGIN CARD LAYOUT --- */
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at top right, var(--color-primary-glow), transparent 45%),
                        radial-gradient(circle at bottom left, rgba(5, 150, 105, 0.05), transparent 40%);
            padding: 20px;
        }

        .auth-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            width: 100%;
            max-width: 440px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            animation: slideIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            text-align: center;
        }

        .auth-logo {
            font-family: var(--font-code);
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--color-primary), #60a5fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .auth-subtitle {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-label {
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-muted);
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-input {
            width: 100%;
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            color: #0f172a;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 15px;
            outline: none;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 10px rgba(37, 99, 235, 0.15);
            background-color: #ffffff;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            background-color: var(--color-primary);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .btn:hover {
            background-color: var(--color-primary-hover);
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(1px);
        }

        .alert-error {
            background-color: var(--color-danger-glow);
            border: 1px solid rgba(220, 38, 38, 0.2);
            color: #b91c1c;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            animation: shake 0.4s ease;
        }

        /* --- DASHBOARD WRAPPER STRUCTURE --- */
        .app-layout {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--bg-surface);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }

        .sidebar-brand {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-logo {
            font-family: var(--font-code);
            font-size: 22px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--color-primary), #60a5fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .sidebar-menu {
            list-style: none;
            padding: 16px;
            flex-grow: 1;
            overflow-y: auto;
        }

        .menu-item {
            margin-bottom: 4px;
        }

        .menu-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .menu-link:hover {
            color: var(--color-primary);
            background-color: var(--color-primary-glow);
        }

        .menu-item.active .menu-link {
            background-color: var(--color-primary-glow);
            color: var(--color-primary);
            border-left: 3px solid var(--color-primary);
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .menu-icon {
            width: 18px;
            height: 18px;
            fill: currentColor;
            opacity: 0.8;
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--border-color);
            background-color: #f8fafc;
            text-align: center;
        }

        .logout-link {
            color: var(--color-danger);
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .logout-link:hover {
            opacity: 0.8;
        }

        .main-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background-color: var(--bg-base);
        }

        /* --- IMPERSONATION TOP NOTIFIER PANEL --- */
        .session-banner {
            background: linear-gradient(90deg, #eff6ff, #f8fafc);
            border-bottom: 1px solid #bfdbfe;
            padding: 8px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
            color: #1e3a8a;
        }

        .session-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            background-color: var(--color-success);
            border-radius: 50%;
            box-shadow: 0 0 8px var(--color-success);
            animation: pulse 1.5s infinite;
        }

        .session-badge {
            background-color: #dbeafe;
            border: 1px solid #bfdbfe;
            color: #1e40af;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .session-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .portal-btn {
            background-color: var(--color-success);
            color: white;
            text-decoration: none;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            transition: opacity 0.2s;
        }

        .portal-btn:hover {
            opacity: 0.9;
        }

        .clear-impersonation-btn {
            background: none;
            border: 1px solid var(--color-danger);
            color: var(--color-danger);
            cursor: pointer;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .clear-impersonation-btn:hover {
            background-color: var(--color-danger-glow);
        }

        .main-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            background-color: var(--bg-surface);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .main-title h1 {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .main-title p {
            color: var(--text-muted);
            font-size: 13px;
            margin-top: 2px;
        }

        .content-body {
            flex-grow: 1;
            padding: 24px;
            overflow-y: auto;
        }

        /* --- DASHBOARD CARDS & METRICS --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background-color: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02), 0 1px 2px rgba(0, 0, 0, 0.01);
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background-color: var(--color-primary);
        }

        .stat-card.success::after { background-color: var(--color-success); }
        .stat-card.warning::after { background-color: var(--color-warning); }
        .stat-card.danger::after { background-color: var(--color-danger); }

        .stat-title {
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            margin: 10px 0;
            color: var(--text-main);
            font-family: var(--font-code);
        }

        /* --- TABLES & SEARCH FILTERS --- */
        .card-panel {
            background-color: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02), 0 1px 2px rgba(0, 0, 0, 0.01);
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .panel-title {
            font-size: 16px;
            font-weight: 600;
        }

        .controls-row {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search-wrapper {
            position: relative;
            flex-grow: 1;
            min-width: 250px;
        }

        .search-input {
            width: 100%;
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 13px;
            color: #0f172a;
            outline: none;
        }

        .search-input:focus {
            border-color: var(--color-primary);
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .data-table th {
            background-color: #f8fafc;
            padding: 12px 16px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-color);
        }

        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            color: var(--text-main);
        }

        .data-table tbody tr:hover {
            background-color: #f8fafc;
        }

        /* --- BADGES & ACTIONS --- */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-draft { background-color: #fef3c7; color: #b45309; border: 1px solid rgba(217, 119, 6, 0.2); }
        .badge-submitted { background-color: #dbeafe; color: #1d4ed8; border: 1px solid rgba(37, 99, 235, 0.2); }
        .badge-admitted { background-color: #d1fae5; color: #047857; border: 1px solid rgba(5, 150, 105, 0.2); }
        .badge-rejected { background-color: #fee2e2; color: #b91c1c; border: 1px solid rgba(220, 38, 38, 0.2); }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
            cursor: pointer;
            border: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-undo {
            background-color: var(--color-warning-glow);
            color: var(--color-warning);
            border: 1px solid rgba(217, 119, 6, 0.3);
        }

        .btn-undo:hover {
            background-color: var(--color-warning);
            color: #ffffff;
        }

        .btn-swap {
            background-color: var(--color-primary-glow);
            color: var(--color-primary);
            border: 1px solid rgba(37, 99, 235, 0.3);
        }

        .btn-swap:hover {
            background-color: var(--color-primary);
            color: white;
        }

        /* --- IMPERSONATOR GRID CARDS --- */
        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .user-card {
            background-color: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }

        .user-card:hover {
            transform: translateY(-2px);
            border-color: #cbd5e1;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04);
        }

        .user-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--color-primary), var(--color-success));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            color: white;
        }

        .user-meta h4 {
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }

        .user-meta p {
            color: var(--text-muted);
            font-size: 12px;
            word-break: break-all;
        }

        .user-role-badge {
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            color: #475569;
            display: inline-block;
            margin-top: 4px;
        }

        .user-card-footer {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }

        /* --- TOAST NOTIFICATIONS --- */
        .toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            background-color: #ffffff;
            border-left: 4px solid var(--color-primary);
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            width: 320px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            color: var(--text-main);
            transform: translateX(120%);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success { border-left-color: var(--color-success); }
        .toast.error { border-left-color: var(--color-danger); }
        .toast.warning { border-left-color: var(--color-warning); }

        .toast-content {
            flex-grow: 1;
        }

        .toast-title {
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 2px;
        }

        .toast-msg {
            color: var(--text-muted);
            font-size: 12px;
        }

        /* --- ANIMATIONS --- */
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-6px); }
            40%, 80% { transform: translateX(6px); }
        }

        @keyframes pulse {
            0% { transform: scale(0.95); opacity: 0.5; }
            50% { transform: scale(1.05); opacity: 1; }
            100% { transform: scale(0.95); opacity: 0.5; }
        }

        /* --- DB INSPECTOR GRID TABLE VIEW --- */
        .db-inspector-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .inspector-row {
            display: flex;
            gap: 20px;
        }

        .inspector-sidebar {
            width: 220px;
            flex-shrink: 0;
            background-color: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 16px;
        }

        .inspector-list {
            list-style: none;
        }

        .inspector-item {
            margin-bottom: 6px;
        }

        .inspector-link {
            display: block;
            padding: 8px 12px;
            border-radius: 6px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
        }

        .inspector-link:hover, .inspector-item.active .inspector-link {
            background-color: var(--color-primary-glow);
            color: var(--color-primary);
        }

        .inspector-item.active .inspector-link {
            background-color: var(--color-primary-glow);
            color: var(--color-primary);
            font-weight: 600;
        }

        .inspector-table-wrapper {
            flex-grow: 1;
            background-color: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Timeline styling for overview panel */
        .timeline {
            list-style: none;
            position: relative;
            padding-left: 20px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 5px;
            top: 5px;
            width: 2px;
            height: calc(100% - 10px);
            background-color: var(--border-color);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }

        .timeline-dot {
            position: absolute;
            left: -19px;
            top: 4px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: var(--color-primary);
            border: 2px solid var(--bg-panel);
        }

        .timeline-content {
            background-color: #f8fafc;
            border: 1px solid var(--border-color);
            padding: 12px 16px;
            border-radius: 8px;
        }

        .timeline-time {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .timeline-title {
            font-weight: 600;
            font-size: 13px;
        }

        .timeline-desc {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Preloader Styles */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<?php if ($is_authenticated): ?>
    <!-- Beautiful glassmorphism preloader -->
    <div id="max-preloader" style="
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: radial-gradient(circle at center, rgba(248, 250, 252, 0.98), rgba(241, 245, 249, 1));
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 99999;
        transition: opacity 0.4s ease, visibility 0.4s ease;
    ">
        <div style="text-align: center;">
            <div class="preloader-spinner" style="
                width: 50px;
                height: 50px;
                border: 4px solid var(--border-color);
                border-top: 4px solid var(--color-primary);
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 0 auto 20px;
            "></div>
            <div style="
                font-family: var(--font-code);
                font-size: 20px;
                font-weight: 700;
                background: linear-gradient(135deg, var(--color-primary), #60a5fa);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                margin-bottom: 8px;
                letter-spacing: -0.5px;
            ">MAX TESTING CONSOLE</div>
            <div style="color: var(--text-muted); font-size: 13px; letter-spacing: 0.5px;">Loading live database statistics...</div>
        </div>
    </div>
<?php endif; ?>

<?php if (!$is_authenticated): ?>
    <!-- Access Code Login Screen -->
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo">MAX TESTING SPA</div>
            <p class="auth-subtitle">Verify Identity to Launch Testing Console</p>

            <?php if ($auth_error): ?>
                <div class="alert-error"><?= h($auth_error) ?></div>
            <?php endif; ?>

            <form action="max.php" method="POST">
                <div class="form-group">
                    <label class="form-label" for="login_access_code">Security Access Code</label>
                    <input class="form-input" type="password" id="login_access_code" name="login_access_code" placeholder="••••••••••••" required autofocus>
                </div>
                <button type="submit" class="btn">
                    Launch Console
                </button>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- SPA Dashboard Layout -->
    <div class="app-layout">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-logo">MAX WORKZONE</div>
            </div>
            
            <ul class="sidebar-menu">
                <li class="menu-item active" data-tab="overview">
                    <a class="menu-link">
                        <svg class="menu-icon" viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                        Overview & Metrics
                    </a>
                </li>
                <li class="menu-item" data-tab="users">
                    <a class="menu-link">
                        <svg class="menu-icon" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        User Accounts CRUD
                    </a>
                </li>
                <li class="menu-item" data-tab="applications">
                    <a class="menu-link">
                        <svg class="menu-icon" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                        Application Workflows
                    </a>
                </li>
                <li class="menu-item" data-tab="swapper">
                    <a class="menu-link">
                        <svg class="menu-icon" viewBox="0 0 24 24"><path d="M19 8l-4 4h3c0 3.31-2.69 6-6 6-1.01 0-1.97-.25-2.8-.7l-1.46 1.46C8.97 19.54 10.43 20 12 20c4.42 0 8-3.58 8-8h3l-4-4zM6 12c0-3.31 2.69-6 6-6 1.01 0 1.97.25 2.8.7l1.46-1.46C15.03 4.46 13.57 4 12 4c-4.42 0-8 3.58-8 8H1l4 4 4-4H6z"/></svg>
                        Dashboard Swapper
                    </a>
                </li>
                <li class="menu-item" data-tab="settings">
                    <a class="menu-link">
                        <svg class="menu-icon" viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                        System & Sessions
                    </a>
                </li>
                <li class="menu-item" data-tab="inspector">
                    <a class="menu-link">
                        <svg class="menu-icon" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm-5 14H4v-4h11v4zm0-5H4V9h11v4zm5 5h-4V9h4v9z"/></svg>
                        Database Inspector
                    </a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <a href="max.php?logout_max=1" class="logout-link">
                    <svg style="width:16px;height:16px;fill:currentColor" viewBox="0 0 24 24"><path d="M10.09 15.59L11.5 17l5-5-5-5-1.41 1.41L12.67 11H3v2h9.67l-2.58 2.59zM19 3H5c-1.11 0-2 .9-2 2v4h2V5h14v14H5v-4H3v4c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
                    Lock Console
                </a>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="main-wrapper">
            <!-- Impersonation Active Banner -->
            <?php if (isset($_SESSION['user_id'])): 
                $activeRole = $_SESSION['role'] ?? 'STUDENT';
                $dashboardUrl = dashboard_for_role($activeRole);
                $isStudent = ($activeRole === 'STUDENT');
            ?>
                <div class="session-banner" id="session-active-banner">
                    <div class="session-info">
                        <span class="pulse-dot"></span>
                        <span>Active Session: <strong><?= h($_SESSION['full_name'] ?? $_SESSION['user_email']) ?></strong></span>
                        <span class="session-badge"><?= h($activeRole) ?></span>
                    </div>
                    <div class="session-actions">
                        <a href="<?= h($dashboardUrl) ?>" target="_blank" class="portal-btn">
                            Open Dashboard Workspace &rarr;
                        </a>
                        <?php if ($isStudent): ?>
                            <a href="APPLICANT/ACADEMICS/student-portal/pages/dashboard.php" target="_blank" class="portal-btn" style="background-color:#2563eb">
                                Open Student Portal
                            </a>
                        <?php endif; ?>
                        <button onclick="clearImpersonation()" class="clear-impersonation-btn">
                            Exit Impersonation
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <header class="main-header">
                <div class="main-title">
                    <h1 id="page-title">Overview & Metrics</h1>
                    <p id="page-desc">General metrics and real-time activity indicators of the JOSTUM IPESS platform.</p>
                </div>
            </header>

            <!-- Main Content Panels -->
            <main class="content-body">
                
                <!-- TAB 1: OVERVIEW -->
                <div id="tab-overview" class="tab-panel">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <span class="stat-title">Total Users</span>
                            <span class="stat-number" id="stats-users-count"><?= number_format($overview_stats['users']) ?></span>
                        </div>
                        <div class="stat-card success">
                            <span class="stat-title">Total Applications</span>
                            <span class="stat-number" id="stats-apps-count"><?= number_format($overview_stats['apps']) ?></span>
                        </div>
                        <div class="stat-card warning">
                            <span class="stat-title">Submitted Apps</span>
                            <span class="stat-number" id="stats-submitted-count"><?= number_format($overview_stats['submitted']) ?></span>
                        </div>
                        <div class="stat-card danger">
                            <span class="stat-title">Active Sessions</span>
                            <span class="stat-number" id="stats-sessions-count"><?= number_format($overview_stats['active_sessions']) ?></span>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:20px;">
                        <!-- Status Chart Map -->
                        <div class="card-panel">
                            <div class="panel-header">
                                <span class="panel-title">Application Status Map</span>
                            </div>
                            <div id="status-breakdown-list" style="display:flex; flex-direction:column; gap:12px;">
                                <?php if (!empty($status_breakdown)): ?>
                                    <?php foreach ($status_breakdown as $row): 
                                        $pct = $overview_stats['apps'] > 0 ? round(($row['count'] / $overview_stats['apps']) * 100) : 0;
                                    ?>
                                        <div>
                                            <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px;">
                                                <span><strong><?= h($row['current_status'] ?: 'UNKNOWN') ?></strong></span>
                                                <span><?= $row['count'] ?> (<?= $pct ?>%)</span>
                                            </div>
                                            <div style="width:100%; height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden;">
                                                <div style="width:<?= $pct ?>%; height:100%; background-color:var(--color-primary); border-radius:4px;"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="color:var(--text-muted); font-size:13px;">No applications found in database.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- System Audit Log Timeline -->
                        <div class="card-panel">
                            <div class="panel-header">
                                <span class="panel-title">System Audit Log Logs</span>
                            </div>
                            <ul class="timeline" id="audit-log-timeline">
                                <?php if (!empty($audit_logs)): ?>
                                    <?php foreach ($audit_logs as $log): ?>
                                        <li class="timeline-item">
                                            <span class="timeline-dot"></span>
                                            <div class="timeline-content">
                                                <div class="timeline-time"><?= h($log['created_at']) ?></div>
                                                <div class="timeline-title"><?= h($log['action']) ?></div>
                                                <div class="timeline-desc"><?= h($log['details']) ?></div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="color:var(--text-muted); font-size:13px;">No logs logged.</div>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: USER ACCOUNTS CRUD -->
                <div id="tab-users" class="tab-panel" style="display:none;">
                    <div class="card-panel">
                        <div class="panel-header">
                            <span class="panel-title">System Users Database</span>
                            <button class="btn-sm btn-swap" onclick="openAddUserModal()">+ Add New User</button>
                        </div>
                        <div class="controls-row">
                            <div class="search-wrapper">
                                <input type="text" id="crud-user-search" class="search-input" placeholder="Type user name, email, or role to search...">
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table" id="users-crud-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Populated dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: APPLICATIONS & WORKFLOW CONTROLS -->
                <div id="tab-applications" class="tab-panel" style="display:none;">
                    <div class="card-panel">
                        <div class="panel-header">
                            <span class="panel-title">Admissions Applications Manager</span>
                        </div>
                        
                        <div class="controls-row">
                            <div class="search-wrapper">
                                <input type="text" id="app-search-input" class="search-input" placeholder="Type name, email, or app number to search...">
                            </div>
                            <select id="app-filter-status" class="search-input" style="width:200px;">
                                <option value="all">All Statuses</option>
                                <option value="Draft">Draft</option>
                                <option value="Submitted">Submitted</option>
                                <option value="Admitted">Admitted</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table" id="apps-data-table">
                                <thead>
                                    <tr>
                                        <th>App Number</th>
                                        <th>Applicant Name</th>
                                        <th>Email</th>
                                        <th>Progress</th>
                                        <th>Status</th>
                                        <th>Submitted At</th>
                                        <th>Workflow Operations</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Populated dynamically by JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TAB 4: DASHBOARD SWAPPER / IMPERSONATION -->
                <div id="tab-swapper" class="tab-panel" style="display:none;">
                    <div class="card-panel" style="margin-bottom: 20px;">
                        <span class="panel-title">Active Swapper Accounts</span>
                        <p style="color:var(--text-muted); font-size:13px; margin-top:4px;">
                            Swap active session privilege and view role dashboards instantly.
                        </p>
                    </div>

                    <div class="controls-row" style="margin-bottom:20px;">
                        <div class="search-wrapper">
                            <input type="text" id="user-search-input" class="search-input" placeholder="Type user name, email, or role to search...">
                        </div>
                    </div>

                    <div class="user-grid" id="users-card-grid">
                        <!-- Populated dynamically by Javascript -->
                    </div>
                </div>

                <!-- TAB 5: ADMISSIONS SESSIONS & SETTINGS -->
                <div id="tab-settings" class="tab-panel" style="display:none;">
                    <div style="display:grid; grid-template-columns: 1.2fr 1fr; gap:20px;">
                        <!-- Sessions manager -->
                        <div class="card-panel">
                            <div class="panel-header">
                                <span class="panel-title">Admissions Sessions</span>
                            </div>
                            <div id="sessions-config-container">
                                <!-- Populated dynamically -->
                            </div>
                        </div>

                        <!-- General config details -->
                        <div style="display:flex; flex-direction:column; gap:20px;">
                            <div class="card-panel">
                                <div class="panel-header">
                                    <span class="panel-title">System Properties</span>
                                </div>
                                <form id="settings-general-form" onsubmit="submitGeneralSettings(event)">
                                    <div class="form-group">
                                        <label class="form-label">Institution Name</label>
                                        <input type="text" name="institution_name" id="set-inst-name" class="form-input" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Session Timeout (seconds)</label>
                                        <input type="number" name="session_timeout" id="set-timeout" class="form-input" required>
                                    </div>
                                    <button type="submit" class="btn">Save Configuration Settings</button>
                                </form>
                            </div>

                            <div class="card-panel">
                                <div class="panel-header">
                                    <span class="panel-title">Add System Notice</span>
                                </div>
                                <form id="add-notice-form" onsubmit="submitNotice(event)">
                                    <div class="form-group">
                                        <label class="form-label">Notice Title</label>
                                        <input type="text" name="title" class="form-input" placeholder="e.g. Admission Deadline Extended" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Notice Body</label>
                                        <textarea name="body" class="form-input" style="height:100px; resize:none;" placeholder="Write notice content details..." required></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Status</label>
                                        <select name="is_published" class="form-input">
                                            <option value="1">Publish Instantly</option>
                                            <option value="0">Save as Draft</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn">Publish Notice Entry</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 6: DATABASE INSPECTOR & TOOLS -->
                <div id="tab-inspector" class="tab-panel" style="display:none;">
                    <div class="db-inspector-container">
                        <div class="card-panel" style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span class="panel-title">Testing Tools & Maintenance</span>
                                <p style="color:var(--text-muted); font-size:12px; margin-top:2px;">Purge logs and diagnostic parameters for fresh testing runs.</p>
                            </div>
                            <button onclick="purgeSystemLogs()" class="btn-sm btn-undo" style="background-color:var(--color-danger-glow); color:var(--color-danger); border-color:rgba(220,38,38,0.2)">
                                Purge Testing Logs & Audit Trails
                            </button>
                        </div>

                        <div class="inspector-row">
                            <!-- Tables Sidebar -->
                            <div class="inspector-sidebar">
                                <h4 style="font-size:12px; text-transform:uppercase; letter-spacing:0.8px; color:var(--text-muted); margin-bottom:12px;">Choose Table</h4>
                                <ul class="inspector-list">
                                    <li class="inspector-item active" data-table-target="users"><a class="inspector-link">users</a></li>
                                    <li class="inspector-item" data-table-target="applications"><a class="inspector-link">applications</a></li>
                                    <li class="inspector-item" data-table-target="application_status_history"><a class="inspector-link">status_history</a></li>
                                    <li class="inspector-item" data-table-target="audit_logs"><a class="inspector-link">audit_logs</a></li>
                                    <li class="inspector-item" data-table-target="supervisors"><a class="inspector-link">supervisors</a></li>
                                    <li class="inspector-item" data-table-target="referee_requests"><a class="inspector-link">referee_requests</a></li>
                                </ul>
                            </div>

                            <!-- Data Display Panel -->
                            <div class="inspector-table-wrapper">
                                <div class="panel-header">
                                    <span class="panel-title" id="inspector-table-title">Table: users (Last 100 rows)</span>
                                </div>
                                <div class="table-responsive" style="max-height: 500px;">
                                    <table class="data-table" id="inspector-data-table">
                                        <thead>
                                            <!-- Headers loaded dynamically -->
                                        </thead>
                                        <tbody>
                                            <!-- Rows loaded dynamically -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- Edit User Modal popup -->
    <div id="edit-user-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); z-index:9999; align-items:center; justify-content:center;">
        <div class="card-panel" style="width:100%; max-width:500px; margin:20px; background:white; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
            <div class="panel-header" style="border-bottom: 1px solid var(--border-color); padding-bottom:12px; margin-bottom:15px;">
                <span class="panel-title">Edit User Account</span>
                <button onclick="closeEditUserModal()" style="background:none; border:none; font-size:20px; cursor:pointer; color:var(--text-muted)">&times;</button>
            </div>
            <form id="edit-user-form" onsubmit="submitEditUser(event)">
                <input type="hidden" name="user_id" id="edit-u-id">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" id="edit-u-name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" id="edit-u-email" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">User Role</label>
                    <select name="role_id" id="edit-u-role" class="form-input" required></select>
                </div>
                <div class="form-group">
                    <label class="form-label">Department Assignment</label>
                    <select name="department_id" id="edit-u-dept" class="form-input">
                        <option value="">No Department Mapping</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Account Lock Status</label>
                    <select name="account_status" id="edit-u-status" class="form-input" required>
                        <option value="Active">Active</option>
                        <option value="Suspended">Suspended</option>
                        <option value="Locked">Locked</option>
                    </select>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:10px;">
                    <button type="button" class="btn-sm btn-undo" onclick="closeEditUserModal()">Cancel</button>
                    <button type="submit" class="btn-sm btn-swap" style="background-color:var(--color-primary); color:white;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create User Modal popup -->
    <div id="add-user-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); z-index:9999; align-items:center; justify-content:center;">
        <div class="card-panel" style="width:100%; max-width:500px; margin:20px; background:white; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
            <div class="panel-header" style="border-bottom: 1px solid var(--border-color); padding-bottom:12px; margin-bottom:15px;">
                <span class="panel-title">Add New Testing Profile</span>
                <button onclick="closeAddUserModal()" style="background:none; border:none; font-size:20px; cursor:pointer; color:var(--text-muted)">&times;</button>
            </div>
            <form id="create-user-form" onsubmit="createUser(event)">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-input" placeholder="e.g. John Doe" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input" placeholder="e.g. testuser@uam.edu.ng" required>
                </div>
                <div class="form-group">
                    <label class="form-label">User Role</label>
                    <select name="role_id" id="add-u-role" class="form-input" required></select>
                </div>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <select name="department_id" id="add-u-dept" class="form-input">
                        <option value="">No Department Mapping</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Authentication Password</label>
                    <input type="password" name="password" class="form-input" value="password123" required>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:10px;">
                    <button type="button" class="btn-sm btn-undo" onclick="closeAddUserModal()">Cancel</button>
                    <button type="submit" class="btn-sm btn-swap" style="background-color:var(--color-primary); color:white;">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notifications Grid Container -->
    <div class="toast-container" id="toast-root"></div>
<?php endif; ?>

<!-- Javascript State and Actions Logic -->
<script>
    // Embed PHP data on page load (limited to first 50 records to prevent overwhelming browser memory)
    const INITIAL_ROLES = <?= json_encode($roles) ?>;
    let INITIAL_USERS = <?= json_encode($users) ?>;
    let INITIAL_APPS = <?= json_encode($applications) ?>;
    const INITIAL_DEPTS = <?= json_encode($departments) ?>;
    const INITIAL_SESSIONS = <?= json_encode($sessions_list) ?>;
    const INITIAL_SETTINGS = <?= json_encode($system_settings) ?>;

    // SPA View Router Configuration
    document.querySelectorAll('.sidebar-menu .menu-item').forEach(item => {
        item.addEventListener('click', () => {
            document.querySelectorAll('.sidebar-menu .menu-item').forEach(el => el.classList.remove('active'));
            item.classList.add('active');

            const targetTab = item.getAttribute('data-tab');
            document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
            document.getElementById('tab-' + targetTab).style.display = 'block';

            // Custom Page Headers
            const headerTitle = document.getElementById('page-title');
            const headerDesc = document.getElementById('page-desc');
            if (targetTab === 'overview') {
                headerTitle.textContent = "Overview & Metrics";
                headerDesc.textContent = "General metrics and real-time activity indicators of the JOSTUM IPESS platform.";
                loadOverviewStats();
            } else if (targetTab === 'users') {
                headerTitle.textContent = "User Accounts CRUD";
                headerDesc.textContent = "List, create, update and delete database users.";
                renderUsersCRUDTable(INITIAL_USERS);
            } else if (targetTab === 'applications') {
                headerTitle.textContent = "Admissions Applications Manager";
                headerDesc.textContent = "Review workflows and reset postgraduate application states.";
                renderApplicationsTable(INITIAL_APPS);
            } else if (targetTab === 'swapper') {
                headerTitle.textContent = "Dashboard Swapper";
                headerDesc.textContent = "Swap active PHP login privileges and view role dashboards.";
                renderUsersSwapperGrid(INITIAL_USERS);
            } else if (targetTab === 'settings') {
                headerTitle.textContent = "System & Sessions Settings";
                headerDesc.textContent = "Open admission sessions, add notice updates, and customize parameters.";
                renderSessionsManager(INITIAL_SESSIONS);
                loadSettingsGeneralForm(INITIAL_SETTINGS);
            } else if (targetTab === 'inspector') {
                headerTitle.textContent = "Database Inspector & Maintenance";
                headerDesc.textContent = "Interactive query viewer of physical database tables.";
                loadInspectorTable('users');
            }
        });
    });

    // Toast Notification Manager
    function showToast(title, message, type = 'success') {
        const root = document.getElementById('toast-root');
        if (!root) return;
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-msg">${message}</div>
            </div>
        `;
        root.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // API Post Payload Helper
    async function apiRequest(payload) {
        const formData = new FormData();
        for (const k in payload) {
            formData.append(k, payload[k]);
        }
        const res = await fetch('max.php', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();
        if (!json.success) {
            showToast("Database Error", json.message || "An unexpected database exception occurred.", "error");
        }
        return json;
    }

    // --- TAB 1: OVERVIEW METRICS LOADER ---
    async function loadOverviewStats() {
        try {
            const data = await apiRequest({ action: 'get_stats' });
            if (data.success) {
                document.getElementById('stats-users-count').textContent = Number(data.users_count).toLocaleString();
                document.getElementById('stats-apps-count').textContent = Number(data.apps_count).toLocaleString();
                document.getElementById('stats-submitted-count').textContent = Number(data.submitted_count).toLocaleString();
                document.getElementById('stats-sessions-count').textContent = Number(data.active_sessions).toLocaleString();

                // Render status chart bars
                const container = document.getElementById('status-breakdown-list');
                container.innerHTML = '';
                if (data.status_breakdown && data.status_breakdown.length > 0) {
                    data.status_breakdown.forEach(row => {
                        const pct = data.apps_count > 0 ? Math.round((row.count / data.apps_count) * 100) : 0;
                        container.innerHTML += `
                            <div>
                                <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px;">
                                    <span><strong>${row.current_status || 'UNKNOWN'}</strong></span>
                                    <span>${row.count} (${pct}%)</span>
                                </div>
                                <div style="width:100%; height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden;">
                                    <div style="width:${pct}%; height:100%; background-color:var(--color-primary); border-radius:4px;"></div>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    container.innerHTML = '<div style="color:var(--text-muted); font-size:13px;">No applications found in database.</div>';
                }

                // Render audit logs
                const auditContainer = document.getElementById('audit-log-timeline');
                auditContainer.innerHTML = '';
                if (data.audit_logs && data.audit_logs.length > 0) {
                    data.audit_logs.forEach(log => {
                        auditContainer.innerHTML += `
                            <li class="timeline-item">
                                <span class="timeline-dot"></span>
                                <div class="timeline-content">
                                    <div class="timeline-time">${log.created_at}</div>
                                    <div class="timeline-title">${log.action}</div>
                                    <div class="timeline-desc">${log.details || ''}</div>
                                </div>
                            </li>
                        `;
                    });
                } else {
                    auditContainer.innerHTML = '<div style="color:var(--text-muted); font-size:13px;">No logs logged.</div>';
                }
            }
        } catch (e) {
            console.error(e);
        }
    }

    // --- TAB 2: USERS CRUD TABLE ---
    function renderUsersCRUDTable(users) {
        const tbody = document.querySelector('#users-crud-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (users.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No users found matching search query.</td></tr>`;
            return;
        }

        users.forEach(u => {
            const isEnabled = (parseInt(u.totp_enabled) === 1);
            const deptName = u.department_id ? (INITIAL_DEPTS.find(d => d.department_id == u.department_id)?.name || 'Dept #' + u.department_id) : '—';
            tbody.innerHTML += `
                <tr>
                    <td><strong>${u.full_name || '—'}</strong></td>
                    <td style="font-family:var(--font-code);">${u.email}</td>
                    <td><span class="user-role-badge">${u.role_key || 'GENERAL'}</span></td>
                    <td style="color:var(--text-muted);">${deptName}</td>
                    <td>
                        <span class="badge badge-${u.account_status == 'Active' ? 'admitted' : 'rejected'}">${u.account_status}</span>
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <button onclick='openEditUserModal(${JSON.stringify(u)})' class="btn-sm btn-swap" style="padding:4px 8px; font-size:11px;">Edit</button>
                            <button onclick="toggleUserTotp(${u.user_id}, ${isEnabled ? 0 : 1})" class="btn-sm btn-undo" style="padding:4px 8px; font-size:11px; color:#1e3a8a; background:#eff6ff; border-color:#bfdbfe;">
                                ${isEnabled ? 'Bypass MFA' : 'Enable MFA'}
                            </button>
                            <button onclick="deleteUser(${u.user_id})" class="btn-sm btn-undo" style="padding:4px 8px; font-size:11px; background-color:var(--color-danger-glow); color:var(--color-danger); border-color:rgba(220,38,38,0.2)">Delete</button>
                        </div>
                    </td>
                </tr>
            `;
        });
    }

    // Filter CRUD Users with Server-Side AJAX Search (avoids overwhelming browser memory)
    let userSearchTimeout;
    document.getElementById('crud-user-search').addEventListener('input', (e) => {
        clearTimeout(userSearchTimeout);
        const query = e.target.value;
        userSearchTimeout = setTimeout(async () => {
            const res = await apiRequest({ action: 'search_users', query: query });
            if (res.success) {
                renderUsersCRUDTable(res.data);
            }
        }, 300);
    });

    // Edit User Modal Handlers
    function openEditUserModal(userObj) {
        document.getElementById('edit-u-id').value = userObj.user_id;
        document.getElementById('edit-u-name').value = userObj.full_name;
        document.getElementById('edit-u-email').value = userObj.email;
        document.getElementById('edit-u-status').value = userObj.account_status;

        const roleSelect = document.getElementById('edit-u-role');
        roleSelect.innerHTML = '';
        INITIAL_ROLES.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.role_id;
            opt.textContent = `${r.role_name} (${r.role_key})`;
            if (r.role_id == userObj.role_id) opt.selected = true;
            roleSelect.appendChild(opt);
        });

        const deptSelect = document.getElementById('edit-u-dept');
        deptSelect.innerHTML = '<option value="">No Department Mapping</option>';
        INITIAL_DEPTS.forEach(d => {
            const opt = document.createElement('option');
            opt.value = d.department_id;
            opt.textContent = d.name;
            if (d.department_id == userObj.department_id) opt.selected = true;
            deptSelect.appendChild(opt);
        });

        document.getElementById('edit-user-modal').style.display = 'flex';
    }

    function closeEditUserModal() {
        document.getElementById('edit-user-modal').style.display = 'none';
    }

    async function submitEditUser(event) {
        event.preventDefault();
        const form = document.getElementById('edit-user-form');
        const fd = new FormData(form);
        fd.append('action', 'edit_user');

        try {
            const res = await fetch('max.php', { method: 'POST', body: fd }).then(r => r.json());
            if (res.success) {
                showToast("User Updated", res.message, 'success');
                closeEditUserModal();
                setTimeout(() => location.reload(), 1500);
            }
        } catch (e) {
            showToast("Server Error", e.message, 'error');
        }
    }

    async function deleteUser(userId) {
        if (!confirm("Are you sure you want to physically delete this user? All their child applications and logs will be permanently deleted due to cascading constraints.")) return;
        try {
            const res = await apiRequest({ action: 'delete_user', user_id: userId });
            if (res.success) {
                showToast("User Deleted", res.message, 'success');
                setTimeout(() => location.reload(), 1500);
            }
        } catch (e) {
            showToast("Server Error", e.message, 'error');
        }
    }

    // Add User Modal Handlers
    function openAddUserModal() {
        const roleSelect = document.getElementById('add-u-role');
        roleSelect.innerHTML = '';
        INITIAL_ROLES.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.role_id;
            opt.textContent = `${r.role_name} (${r.role_key})`;
            if (r.role_key === 'STUDENT') opt.selected = true;
            roleSelect.appendChild(opt);
        });

        const deptSelect = document.getElementById('add-u-dept');
        deptSelect.innerHTML = '<option value="">No Department Mapping</option>';
        INITIAL_DEPTS.forEach(d => {
            const opt = document.createElement('option');
            opt.value = d.department_id;
            opt.textContent = d.name;
            deptSelect.appendChild(opt);
        });

        document.getElementById('add-user-modal').style.display = 'flex';
    }

    function closeAddUserModal() {
        document.getElementById('add-user-modal').style.display = 'none';
    }

    async function createUser(event) {
        event.preventDefault();
        const form = document.getElementById('create-user-form');
        const fd = new FormData(form);
        fd.append('action', 'create_test_user');

        try {
            const res = await fetch('max.php', { method: 'POST', body: fd }).then(r => r.json());
            if (res.success) {
                showToast("Account Created", res.message, 'success');
                closeAddUserModal();
                setTimeout(() => location.reload(), 1500);
            }
        } catch (e) {
            showToast("Server Error", e.message, 'error');
        }
    }

    // --- TAB 3: APPLICATIONS MANAGER ---
    function renderApplicationsTable(apps) {
        const tbody = document.querySelector('#apps-data-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (apps.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No applications found matching search filter.</td></tr>`;
            return;
        }

        apps.forEach(app => {
            const isDraft = (app.status === 'Draft' || app.current_status === 'DRAFT');
            const submittedStr = app.submitted_at ? app.submitted_at : '—';
            tbody.innerHTML += `
                <tr>
                    <td style="font-family:var(--font-code); font-weight:600; color:var(--color-primary);">
                        ${app.application_number || 'DRAFT-TEMP'}
                    </td>
                    <td><strong>${app.full_name}</strong></td>
                    <td style="color:var(--text-muted);">${app.email}</td>
                    <td>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="font-family:var(--font-code); font-size:11px;">${Math.round(app.completion_percentage)}%</span>
                            <div style="width:60px; height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden;">
                                <div style="width:${app.completion_percentage}%; height:100%; background-color:var(--color-success);"></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-${(app.status || 'draft').toLowerCase()}">${app.current_status || 'DRAFT'}</span>
                    </td>
                    <td style="font-size:12px; color:var(--text-muted);">${submittedStr}</td>
                    <td>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <select onchange="overrideApplicationStatus(${app.application_id}, this.value)" class="search-input" style="width:160px; padding:4px 8px; font-size:11px; height:auto;">
                                <option value="">Override Status...</option>
                                <option value="DRAFT">Draft</option>
                                <option value="SUBMITTED">Submitted</option>
                                <option value="ASSIGNED_TO_DEPARTMENT">Assigned to Dept</option>
                                <option value="UNDER_DEPT_REVIEW">Under Dept Review</option>
                                <option value="DEPT_APPROVED">Dept Approved</option>
                                <option value="COLLEGE_PENDING">College Pending</option>
                                <option value="APPROVED_BY_POSTGRADUATE_SCHOOL">Approved by PG School</option>
                                <option value="ADMISSION_APPROVED">Admission Approved</option>
                                <option value="ADMISSION_REJECTED">Admission Rejected</option>
                            </select>
                            
                            ${!isDraft ? `
                                <button onclick="undoApplicationSubmission(${app.application_id})" class="btn-sm btn-undo" style="padding:4px 8px; font-size:11px;">
                                    Undo Submit
                                </button>
                            ` : `
                                <span style="font-size:11px; color:var(--text-muted); font-style:italic;">Draft (Editable)</span>
                            `}
                        </div>
                    </td>
                </tr>
            `;
        });
    }

    // Filter Applications with Server-Side AJAX Search (avoids overwhelming browser memory)
    let appSearchTimeout;
    function triggerAppSearch() {
        clearTimeout(appSearchTimeout);
        appSearchTimeout = setTimeout(async () => {
            const query = document.getElementById('app-search-input').value;
            const status = document.getElementById('app-filter-status').value;
            const res = await apiRequest({ action: 'search_applications', query: query, status: status });
            if (res.success) {
                renderApplicationsTable(res.data);
            }
        }, 300);
    }
    document.getElementById('app-search-input').addEventListener('input', triggerAppSearch);
    document.getElementById('app-filter-status').addEventListener('change', triggerAppSearch);

    async function overrideApplicationStatus(appId, newStatus) {
        if (!newStatus) return;
        if (!confirm(`Are you sure you want to force change this application status to ${newStatus}?`)) return;
        try {
            const res = await apiRequest({ action: 'change_app_status', application_id: appId, new_status: newStatus });
            if (res.success) {
                showToast("Status Changed", res.message, 'success');
                triggerAppSearch();
            }
        } catch (e) {
            showToast("Server Error", e.message, 'error');
        }
    }

    async function undoApplicationSubmission(appId) {
        if (!confirm("Are you sure you want to undo this application submission? This sets the status back to Draft so that the applicant can login and edit specific files or fields.")) return;
        try {
            const res = await apiRequest({ action: 'undo_submission', application_id: appId });
            if (res.success) {
                showToast("Submission Undone", res.message, 'success');
                triggerAppSearch();
            }
        } catch (e) {
            showToast("Server Error", e.message, 'error');
        }
    }

    // --- TAB 4: SWAPPER GRID ---
    function renderUsersSwapperGrid(users) {
        const grid = document.getElementById('users-card-grid');
        if (!grid) return;
        grid.innerHTML = '';
        if (users.length === 0) {
            grid.innerHTML = `<div style="grid-column: 1/-1; text-align:center; color:var(--text-muted); padding:40px;">No user records found matching search.</div>`;
            return;
        }

        users.forEach(user => {
            const initials = user.full_name ? user.full_name.split(' ').map(n => n[0]).join('').substring(0,2).toUpperCase() : 'U';
            grid.innerHTML += `
                <div class="user-card">
                    <div>
                        <div class="user-card-header">
                            <div class="user-avatar">${initials}</div>
                            <div class="user-meta">
                                <h4>${user.full_name || 'No Name'}</h4>
                                <p>${user.email}</p>
                                <span class="user-role-badge">${user.role_key || 'GENERAL'}</span>
                            </div>
                        </div>
                    </div>
                    <div class="user-card-footer">
                        <button onclick="impersonateUser(${user.user_id})" class="btn-sm btn-swap" style="width:100%;">
                            Swap Context &rarr;
                        </button>
                    </div>
                </div>
            `;
        });
    }

    // Filter Swapper Users with Server-Side AJAX Search
    let swapperSearchTimeout;
    document.getElementById('user-search-input').addEventListener('input', (e) => {
        clearTimeout(swapperSearchTimeout);
        const query = e.target.value;
        swapperSearchTimeout = setTimeout(async () => {
            const res = await apiRequest({ action: 'search_users', query: query });
            if (res.success) {
                renderUsersSwapperGrid(res.data);
            }
        }, 300);
    });

    async function impersonateUser(userId) {
        try {
            const res = await apiRequest({ action: 'impersonate_user', user_id: userId });
            if (res.success) {
                showToast("Context Swapped", res.message, 'success');
                setTimeout(() => location.reload(), 1200);
            }
        } catch (e) {
            showToast("Server Error", e.message, 'error');
        }
    }

    async function clearImpersonation() {
        try {
            const res = await apiRequest({ action: 'clear_impersonation' });
            if (res.success) {
                showToast("Session Restored", res.message, 'success');
                setTimeout(() => location.reload(), 1200);
            }
        } catch (e) {
            showToast("Server Error", e.message, 'error');
        }
    }

    // --- TAB 5: SESSIONS & GENERAL SETTINGS ---
    function renderSessionsManager(sessions) {
        const container = document.getElementById('sessions-config-container');
        if (!container) return;
        container.innerHTML = '';
        if (sessions.length === 0) {
            container.innerHTML = `<div style="color:var(--text-muted); font-size:13px; padding:15px; border:1px dashed var(--border-color); text-align:center; border-radius:8px;">No admission sessions registered.</div>`;
            return;
        }

        sessions.forEach(s => {
            container.innerHTML += `
                <form onsubmit="submitUpdateSession(event)" class="card-panel" style="background:#f8fafc; border:1px solid var(--border-color); margin-bottom:15px; padding:15px;">
                    <input type="hidden" name="session_id" value="${s.id}">
                    <div style="font-weight:700; font-size:14px; margin-bottom:12px; color:var(--color-primary)">
                        Session Year: ${s.year_label}
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Opens At</label>
                            <input type="date" name="opens_at" class="form-input" value="${s.opens_at || ''}">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Closes At</label>
                            <input type="date" name="closes_at" class="form-input" value="${s.closes_at || ''}">
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1.2fr 1fr 1fr; gap:10px; margin-bottom:12px; align-items:end;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Application Fee (₦)</label>
                            <input type="number" name="application_fee" class="form-input" step="0.01" value="${s.application_fee}">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Opens State</label>
                            <select name="is_open" class="form-input">
                                <option value="1" ${s.is_open == 1 ? 'selected' : ''}>Open</option>
                                <option value="0" ${s.is_open == 0 ? 'selected' : ''}>Closed</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Active State</label>
                            <select name="is_open" class="form-input">
                                <option value="1" ${s.is_active == 1 ? 'selected' : ''}>Active</option>
                                <option value="0" ${s.is_active == 0 ? 'selected' : ''}>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn-sm btn-swap" style="width:100%; display:block;">Update Session Setup</button>
                </form>
            `;
        });
    }

    async function submitUpdateSession(event) {
        event.preventDefault();
        const form = event.target;
        const fd = new FormData(form);
        fd.append('action', 'update_session');

        try {
            const res = await fetch('max.php', { method: 'POST', body: fd }).then(r => r.json());
            if (res.success) {
                showToast("Session Saved", res.message, 'success');
            }
        } catch (e) {
            showToast("Server Error", e.message, 'error');
        }
    }

    function loadSettingsGeneralForm(set) {
        const instInput = document.getElementById('set-inst-name');
        const timeoutInput = document.getElementById('set-timeout');
        if (instInput) instInput.value = set.institution_name || '';
        if (timeoutInput) timeoutInput.value = set.session_timeout_seconds || 900;
    }

    async function submitGeneralSettings(event) {
        event.preventDefault();
        const form = document.getElementById('settings-general-form');
        const fd = new FormData(form);
        fd.append('action', 'update_settings');

        try {
            const res = await fetch('max.php', { method: 'POST', body: fd }).then(r => r.json());
            if (res.success) {
                showToast("Settings Updated", res.message, 'success');
            }
        } catch (e) {
            showToast("Server Error", e.message, 'error');
        }
    }

    async function submitNotice(event) {
        event.preventDefault();
        const form = document.getElementById('add-notice-form');
        const fd = new FormData(form);
        fd.append('action', 'add_notice');

        try {
            const res = await fetch('max.php', { method: 'POST', body: fd }).then(r => r.json());
            if (res.success) {
                showToast("Notice Published", res.message, 'success');
                form.reset();
            }
        } catch (e) {
            showToast("Server Error", e.message, 'error');
        }
    }

    // --- TAB 6: DATABASE INSPECTOR LOADER ---
    document.querySelectorAll('.inspector-list .inspector-item').forEach(item => {
        item.addEventListener('click', () => {
            document.querySelectorAll('.inspector-list .inspector-item').forEach(el => el.classList.remove('active'));
            item.classList.add('active');
            const targetTable = item.getAttribute('data-table-target');
            loadInspectorTable(targetTable);
        });
    });

    async function loadInspectorTable(tableName) {
        const title = document.getElementById('inspector-table-title');
        title.textContent = `Table: ${tableName} (Loading last 100 rows...)`;

        const thead = document.querySelector('#inspector-data-table thead');
        const tbody = document.querySelector('#inspector-data-table tbody');
        if (!thead || !tbody) return;
        thead.innerHTML = '';
        tbody.innerHTML = '';

        try {
            const res = await apiRequest({ action: 'get_table_data', table_name: tableName });
            if (res.success && res.data.length > 0) {
                title.textContent = `Table: ${tableName} (Last ${res.data.length} rows)`;
                
                // Build headers dynamically
                const cols = Object.keys(res.data[0]);
                let headTr = '<tr>';
                cols.forEach(col => { headTr += `<th>${col}</th>`; });
                headTr += '</tr>';
                thead.innerHTML = headTr;

                // Build rows dynamically
                res.data.forEach(row => {
                    let tr = '<tr>';
                    cols.forEach(col => {
                        let val = row[col];
                        if (val === null) val = '<span style="color:var(--text-muted); font-style:italic;">null</span>';
                        else if (val && val.length > 50) val = val.substring(0, 50) + '...';
                        tr += `<td style="font-family:var(--font-code); font-size:12px;">${val}</td>`;
                    });
                    tr += '</tr>';
                    tbody.innerHTML += tr;
                });
            } else {
                title.textContent = `Table: ${tableName} (0 rows found)`;
                tbody.innerHTML = `<tr><td style="text-align:center; color:var(--text-muted);">Empty table / no records.</td></tr>`;
            }
        } catch (e) {
            title.textContent = `Table: ${tableName} (Error loading data)`;
            tbody.innerHTML = `<tr><td style="text-align:center; color:var(--color-danger);">Failed to query database.</td></tr>`;
        }
    }

    async function toggleUserTotp(userId, state) {
        try {
            const res = await apiRequest({ action: 'toggle_totp', user_id: userId, enabled: state });
            if (res.success) {
                showToast("MFA Mode Changed", res.message, 'success');
                setTimeout(() => location.reload(), 1200);
            }
        } catch (e) {
            showToast("Server Error", e.message, 'error');
        }
    }

    async function purgeSystemLogs() {
        if (!confirm("Warning: This will physically delete/truncate all entries inside audit_logs, workflow_audit_logs, and application_status_history tables. This is intended to clean the workspace for a fresh testing session. Proceed?")) return;
        try {
            const res = await apiRequest({ action: 'clear_logs' });
            if (res.success) {
                showToast("Logs Purged", res.message, 'success');
                setTimeout(() => location.reload(), 1500);
            }
        } catch (e) {
            showToast("Server Error", e.message, 'error');
        }
    }

    // Auto-run startup load on screen render
    window.addEventListener('DOMContentLoaded', async () => {
        // Render initial tables
        if (document.getElementById('users-crud-table')) {
            renderUsersCRUDTable(INITIAL_USERS);
        }
        if (document.getElementById('apps-data-table')) {
            renderApplicationsTable(INITIAL_APPS);
        }
        if (document.getElementById('users-card-grid')) {
            renderUsersSwapperGrid(INITIAL_USERS);
        }

        // Fetch latest live stats
        try {
            await loadOverviewStats();
        } catch (e) {
            console.error(e);
        } finally {
            // Hide the preloader smoothly
            const preloader = document.getElementById('max-preloader');
            if (preloader) {
                preloader.style.opacity = '0';
                preloader.style.visibility = 'hidden';
                setTimeout(() => preloader.remove(), 400);
            }
        }
    });
</script>
</body>
</html>
