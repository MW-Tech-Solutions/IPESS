<?php
/**
 * Max.php - High Impact Testing SPA Dashboard
 * Includes access code protection, user impersonation, submission undo utility, and database inspector.
 */

// Error reporting config for testing stability
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/app/bootstrap.php';
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
                
                $status_breakdown = $pdo->query("
                    SELECT current_status, COUNT(*) as count 
                    FROM applications 
                    GROUP BY current_status
                ")->fetchAll(PDO::FETCH_ASSOC);

                $audit_logs = $pdo->query("
                    SELECT action, details, created_at 
                    FROM audit_logs 
                    ORDER BY log_id DESC 
                    LIMIT 8
                ")->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'users_count' => $users_count,
                    'apps_count' => $apps_count,
                    'submitted_count' => $submitted_count,
                    'draft_count' => $draft_count,
                    'status_breakdown' => $status_breakdown,
                    'audit_logs' => $audit_logs
                ]);
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

                // Revert status to DRAFT using system's status_engine
                $ok = update_application_status($pdo, $app_id, 'DRAFT', [
                    'actor_id' => $actor_id,
                    'actor_role' => $actor_role,
                    'note' => 'Submission undone via max.php testing dashboard'
                ]);

                // Reset submission date columns
                $pdo->prepare("UPDATE applications SET submitted_at = NULL WHERE application_id = ?")->execute([$app_id]);

                // Reset application tracking stages to Pending
                if (table_exists($pdo, 'application_progress')) {
                    $pdo->prepare("UPDATE application_progress SET stage_status = 'Pending', stage_updated_at = NOW() WHERE application_id = ?")->execute([$app_id]);
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Application submission undone successfully. Status set back to Draft and locks removed.'
                ]);
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
                        // Autopopulate blank draft application for immediate testing
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
                    INSERT INTO users (email, full_name, role_id, password_hash, account_status, created_at)
                    VALUES (?, ?, ?, ?, 'Active', NOW())
                ");
                $stmtInsert->execute([$email, $full_name, $role_id, $password_hash]);
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

// Fetch lists for rendering initial view states
$roles = [];
$users = [];
$applications = [];
if ($is_authenticated) {
    try {
        $roles = $pdo->query("SELECT * FROM roles ORDER BY role_id ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        $users = $pdo->query("
            SELECT u.user_id, u.email, u.full_name, u.totp_enabled, r.role_key, r.role_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.role_id 
            ORDER BY u.user_id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $applications = $pdo->query("
            SELECT a.application_id, a.application_number, a.status, a.current_status, a.submitted_at, a.completion_percentage, u.full_name, u.email 
            FROM applications a 
            JOIN users u ON a.user_id = u.user_id 
            ORDER BY a.updated_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // Suppress initial query crashes for clean UI display
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
            --bg-base: #08090c;
            --bg-surface: #0f111a;
            --bg-panel: #151824;
            --color-primary: #3b82f6;
            --color-primary-hover: #2563eb;
            --color-primary-glow: rgba(59, 130, 246, 0.15);
            --color-success: #10b981;
            --color-success-glow: rgba(16, 185, 129, 0.15);
            --color-warning: #f59e0b;
            --color-warning-glow: rgba(245, 158, 11, 0.15);
            --color-danger: #ef4444;
            --color-danger-glow: rgba(239, 68, 68, 0.15);
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --border-color: rgba(255, 255, 255, 0.08);
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
                        radial-gradient(circle at bottom left, rgba(16, 185, 129, 0.08), transparent 40%);
            padding: 20px;
        }

        .auth-card {
            background: rgba(21, 24, 36, 0.65);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            width: 100%;
            max-width: 440px;
            padding: 40px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
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
            background-color: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 15px;
            outline: none;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.2);
            background-color: rgba(255, 255, 255, 0.05);
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
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
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
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5;
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

        .menu-link:hover, .menu-item.active .menu-link {
            color: var(--text-main);
            background-color: rgba(255, 255, 255, 0.04);
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
            background-color: rgba(0, 0, 0, 0.2);
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
            background: linear-gradient(90deg, #1e1b4b, #111827);
            border-bottom: 1px solid rgba(99, 102, 241, 0.2);
            padding: 8px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
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
            background-color: rgba(99, 102, 241, 0.15);
            border: 1px solid rgba(99, 102, 241, 0.3);
            color: #a5b4fc;
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
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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
            background-color: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 13px;
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
            background-color: var(--bg-surface);
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
        }

        .data-table tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.02);
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

        .badge-draft { background-color: var(--color-warning-glow); color: var(--color-warning); border: 1px solid rgba(245, 158, 11, 0.2); }
        .badge-submitted { background-color: var(--color-primary-glow); color: var(--color-primary); border: 1px solid rgba(59, 130, 246, 0.2); }
        .badge-admitted { background-color: var(--color-success-glow); color: var(--color-success); border: 1px solid rgba(16, 185, 129, 0.2); }
        .badge-rejected { background-color: var(--color-danger-glow); color: var(--color-danger); border: 1px solid rgba(239, 68, 68, 0.2); }

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
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .btn-undo:hover {
            background-color: var(--color-warning);
            color: #000;
        }

        .btn-swap {
            background-color: var(--color-primary-glow);
            color: var(--color-primary);
            border: 1px solid rgba(59, 130, 246, 0.3);
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
            transition: transform 0.2s ease, border-color 0.2s ease;
        }

        .user-card:hover {
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.15);
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
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            color: var(--text-muted);
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
            background-color: var(--bg-panel);
            border-left: 4px solid var(--color-primary);
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            width: 320px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: flex-start;
            gap: 12px;
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
            background-color: rgba(255, 255, 255, 0.03);
            color: var(--text-main);
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
            background-color: rgba(255, 255, 255, 0.02);
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
    </style>
</head>
<body>

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
                <li class="menu-item" data-tab="applications">
                    <a class="menu-link">
                        <svg class="menu-icon" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                        Application Manager
                    </a>
                </li>
                <li class="menu-item" data-tab="swapper">
                    <a class="menu-link">
                        <svg class="menu-icon" viewBox="0 0 24 24"><path d="M19 8l-4 4h3c0 3.31-2.69 6-6 6-1.01 0-1.97-.25-2.8-.7l-1.46 1.46C8.97 19.54 10.43 20 12 20c4.42 0 8-3.58 8-8h3l-4-4zM6 12c0-3.31 2.69-6 6-6 1.01 0 1.97.25 2.8.7l1.46-1.46C15.03 4.46 13.57 4 12 4c-4.42 0-8 3.58-8 8H1l4 4 4-4H6z"/></svg>
                        Dashboard Swapper
                    </a>
                </li>
                <li class="menu-item" data-tab="inspector">
                    <a class="menu-link">
                        <svg class="menu-icon" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm-5 14H4v-4h11v4zm0-5H4V9h11v4zm5 5h-4V9h4v9z"/></svg>
                        Database Inspector
                    </a>
                </li>
                <li class="menu-item" data-tab="tools">
                    <a class="menu-link">
                        <svg class="menu-icon" viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                        Test Account Creator
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
                            <a href="APPLICANT/ACADEMICS/student-portal/pages/dashboard.php" target="_blank" class="portal-btn" style="background-color:#6366f1">
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
                            <span class="stat-number" id="stats-users-count">...</span>
                        </div>
                        <div class="stat-card success">
                            <span class="stat-title">Total Applications</span>
                            <span class="stat-number" id="stats-apps-count">...</span>
                        </div>
                        <div class="stat-card warning">
                            <span class="stat-title">Submitted Apps</span>
                            <span class="stat-number" id="stats-submitted-count">...</span>
                        </div>
                        <div class="stat-card danger">
                            <span class="stat-title">Draft Apps</span>
                            <span class="stat-number" id="stats-draft-count">...</span>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:20px;">
                        <!-- Status Chart Map -->
                        <div class="card-panel">
                            <div class="panel-header">
                                <span class="panel-title">Application Status Map</span>
                            </div>
                            <div id="status-breakdown-list" style="display:flex; flex-direction:column; gap:12px;">
                                <!-- Populated dynamically by Javascript -->
                            </div>
                        </div>

                        <!-- System Audit Log Timeline -->
                        <div class="card-panel">
                            <div class="panel-header">
                                <span class="panel-title">System Audit Log Logs</span>
                            </div>
                            <ul class="timeline" id="audit-log-timeline">
                                <!-- Populated dynamically by Javascript -->
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: APPLICATIONS & UNDO SUBMISSION -->
                <div id="tab-applications" class="tab-panel" style="display:none;">
                    <div class="card-panel">
                        <div class="panel-header">
                            <span class="panel-title">Active Database Applications</span>
                        </div>
                        
                        <div class="controls-row">
                            <div class="search-wrapper">
                                <input type="text" id="app-search-input" class="search-input" placeholder="Search by name, email, or application number...">
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
                                        <th>Testing Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Populated dynamically by JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: DASHBOARD SWAPPER / IMPERSONATION -->
                <div id="tab-swapper" class="tab-panel" style="display:none;">
                    <div class="card-panel" style="margin-bottom: 20px;">
                        <span class="panel-title">Available Portal Accounts</span>
                        <p style="color:var(--text-muted); font-size:13px; margin-top:4px;">
                            Click **Swap Context** to immediately log into the PHP application session as the selected user.
                        </p>
                    </div>

                    <div class="controls-row" style="margin-bottom:20px;">
                        <div class="search-wrapper">
                            <input type="text" id="user-search-input" class="search-input" placeholder="Search users by name, email, or role...">
                        </div>
                    </div>

                    <div class="user-grid" id="users-card-grid">
                        <!-- Populated dynamically by Javascript -->
                    </div>
                </div>

                <!-- TAB 4: DATABASE INSPECTOR -->
                <div id="tab-inspector" class="tab-panel" style="display:none;">
                    <div class="db-inspector-container">
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

                <!-- TAB 5: TEST ACCOUNT CREATOR -->
                <div id="tab-tools" class="tab-panel" style="display:none;">
                    <div style="display:grid; grid-template-columns: 1fr 1.2fr; gap:20px;">
                        <!-- Registration Form -->
                        <div class="card-panel">
                            <div class="panel-header">
                                <span class="panel-title">Add New Testing Profile</span>
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
                                    <select name="role_id" class="form-input" style="background-color: var(--bg-surface)" required>
                                        <?php foreach ($roles as $r): ?>
                                            <option value="<?= (int)$r['role_id'] ?>" <?= $r['role_key'] === 'STUDENT' ? 'selected' : '' ?>>
                                                <?= h($r['role_name']) ?> (<?= h($r['role_key']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Authentication Password</label>
                                    <input type="password" name="password" class="form-input" value="password123" required>
                                </div>
                                <button type="submit" class="btn">
                                    Register User & Generate Workspace
                                </button>
                            </form>
                        </div>

                        <!-- MFA TOTP manager -->
                        <div class="card-panel">
                            <div class="panel-header">
                                <span class="panel-title">Multi-Factor Authenticator Bypasses</span>
                            </div>
                            <p style="color:var(--text-muted); font-size:13px; margin-bottom:15px;">
                                Use this list to quickly enable or completely disable/bypass standard MFA TOTP checks for users on the login interface.
                            </p>
                            <div class="table-responsive">
                                <table class="data-table" id="totp-user-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>MFA Enabled</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Loaded dynamically -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- Toast Notifications Grid Container -->
    <div class="toast-container" id="toast-root"></div>
<?php endif; ?>

<!-- Javascript State and Actions Logic -->
<script>
    // Embed PHP data on page load
    const INITIAL_ROLES = <?= json_encode($roles) ?>;
    const INITIAL_USERS = <?= json_encode($users) ?>;
    const INITIAL_APPS = <?= json_encode($applications) ?>;

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
            } else if (targetTab === 'applications') {
                headerTitle.textContent = "Application Manager";
                headerDesc.textContent = "Search, review, and reset postgraduate application workflows.";
                renderApplicationsTable(INITIAL_APPS);
            } else if (targetTab === 'swapper') {
                headerTitle.textContent = "Dashboard Swapper";
                headerDesc.textContent = "Swap active PHP login privileges and view role dashboards.";
                renderUsersSwapperGrid(INITIAL_USERS);
            } else if (targetTab === 'inspector') {
                headerTitle.textContent = "Database Inspector";
                headerDesc.textContent = "Interactive query viewer of physical database tables.";
                loadInspectorTable('users');
            } else if (targetTab === 'tools') {
                headerTitle.textContent = "Testing Account Sandbox";
                headerDesc.textContent = "Register mock accounts and toggle security settings instantly.";
                loadToolsTotpTable();
            }
        });
    });

    // Toast Notification Manager
    function showToast(title, message, type = 'success') {
        const root = document.getElementById('toast-root');
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
        return await res.json();
    }

    // --- TAB 1: OVERVIEW METRICS LOADER ---
    async function loadOverviewStats() {
        try {
            const data = await apiRequest({ action: 'get_stats' });
            if (data.success) {
                document.getElementById('stats-users-count').textContent = data.users_count;
                document.getElementById('stats-apps-count').textContent = data.apps_count;
                document.getElementById('stats-submitted-count').textContent = data.submitted_count;
                document.getElementById('stats-draft-count').textContent = data.draft_count;

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
                                <div style="width:100%; height:8px; background:rgba(255,255,255,0.05); border-radius:4px; overflow:hidden;">
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

    // --- TAB 2: APPLICATION DATA TABLE ---
    function renderApplicationsTable(apps) {
        const tbody = document.querySelector('#apps-data-table tbody');
        tbody.innerHTML = '';
        if (apps.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No applications found.</td></tr>`;
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
                            <div style="width:60px; height:6px; background:rgba(255,255,255,0.05); border-radius:3px; overflow:hidden;">
                                <div style="width:${app.completion_percentage}%; height:100%; background-color:var(--color-success);"></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-${(app.status || 'draft').toLowerCase()}">${app.current_status || 'DRAFT'}</span>
                    </td>
                    <td style="font-size:12px; color:var(--text-muted);">${submittedStr}</td>
                    <td>
                        <div style="display:flex; gap:8px;">
                            ${!isDraft ? `
                                <button onclick="undoApplicationSubmission(${app.application_id})" class="btn-sm btn-undo">
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

    // Filter Applications
    function filterApps() {
        const q = document.getElementById('app-search-input').value.toLowerCase();
        const status = document.getElementById('app-filter-status').value;
        const filtered = INITIAL_APPS.filter(app => {
            const matchesSearch = app.full_name.toLowerCase().includes(q) || 
                                  app.email.toLowerCase().includes(q) || 
                                  (app.application_number && app.application_number.toLowerCase().includes(q));
            const matchesStatus = (status === 'all') || (app.status === status);
            return matchesSearch && matchesStatus;
        });
        renderApplicationsTable(filtered);
    }
    document.getElementById('app-search-input').addEventListener('input', filterApps);
    document.getElementById('app-filter-status').addEventListener('change', filterApps);

    // Revert Submission Action
    async function undoApplicationSubmission(appId) {
        if (!confirm("Are you sure you want to undo this application submission? This sets the status back to Draft so that the applicant can login and edit specific files or fields.")) return;
        try {
            const res = await apiRequest({ action: 'undo_submission', application_id: appId });
            if (res.success) {
                showToast("Submission Undone", res.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast("Action Failed", res.message, 'error');
            }
        } catch (e) {
            showToast("Server Communication Error", e.message, 'error');
        }
    }

    // --- TAB 3: USER SWAPPER / IMPERSONATOR HUB ---
    function renderUsersSwapperGrid(users) {
        const grid = document.getElementById('users-card-grid');
        grid.innerHTML = '';
        if (users.length === 0) {
            grid.innerHTML = `<div style="grid-column: 1/-1; text-align:center; color:var(--text-muted); padding:40px;">No user records found in system.</div>`;
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

    // Filter Swapper Users
    document.getElementById('user-search-input').addEventListener('input', (e) => {
        const q = e.target.value.toLowerCase();
        const filtered = INITIAL_USERS.filter(u => 
            u.full_name.toLowerCase().includes(q) || 
            u.email.toLowerCase().includes(q) || 
            (u.role_key && u.role_key.toLowerCase().includes(q))
        );
        renderUsersSwapperGrid(filtered);
    });

    // Impersonate Context Swapping
    async function impersonateUser(userId) {
        try {
            const res = await apiRequest({ action: 'impersonate_user', user_id: userId });
            if (res.success) {
                showToast("Context Swapped", res.message, 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast("Context Error", res.message, 'error');
            }
        } catch (e) {
            showToast("Server Communication Error", e.message, 'error');
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
            showToast("Server Communication Error", e.message, 'error');
        }
    }

    // --- TAB 4: DATABASE INSPECTOR LOADER ---
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

    // --- TAB 5: ACCOUNT SANDBOX TOOLS ---
    async function createUser(event) {
        event.preventDefault();
        const form = document.getElementById('create-user-form');
        const fd = new FormData(form);
        fd.append('action', 'create_test_user');

        try {
            const res = await fetch('max.php', { method: 'POST', body: fd }).then(r => r.json());
            if (res.success) {
                showToast("Account Created", res.message, 'success');
                form.reset();
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast("Creation Failed", res.message, 'error');
            }
        } catch (e) {
            showToast("Server Communication Error", e.message, 'error');
        }
    }

    function loadToolsTotpTable() {
        const tbody = document.querySelector('#totp-user-table tbody');
        tbody.innerHTML = '';
        if (INITIAL_USERS.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; color:var(--text-muted);">No users found.</td></tr>`;
            return;
        }

        INITIAL_USERS.forEach(u => {
            const isEnabled = (parseInt(u.totp_enabled) === 1);
            tbody.innerHTML += `
                <tr>
                    <td><strong>${u.full_name || '—'}</strong></td>
                    <td>${u.email}</td>
                    <td><span class="user-role-badge">${u.role_key || 'GENERAL'}</span></td>
                    <td>
                        <span class="badge badge-${isEnabled ? 'admitted' : 'draft'}">${isEnabled ? 'Enabled' : 'Bypassed'}</span>
                    </td>
                    <td>
                        <button onclick="toggleUserTotp(${u.user_id}, ${isEnabled ? 0 : 1})" class="btn-sm btn-swap" style="padding:4px 8px; font-size:11px;">
                            ${isEnabled ? 'Bypass OTP' : 'Enable OTP'}
                        </button>
                    </td>
                </tr>
            `;
        });
    }

    async function toggleUserTotp(userId, state) {
        try {
            const res = await apiRequest({ action: 'toggle_totp', user_id: userId, enabled: state });
            if (res.success) {
                showToast("MFA Mode Changed", res.message, 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast("Action Failed", res.message, 'error');
            }
        } catch (e) {
            showToast("Server Communication Error", e.message, 'error');
        }
    }

    // Auto-run startup load on screen render
    window.addEventListener('DOMContentLoaded', () => {
        loadOverviewStats();
    });
</script>
</body>
</html>
