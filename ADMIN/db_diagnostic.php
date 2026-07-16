<?php
session_start();
require_once __DIR__ . '/admin/db.php';

// Simple protection: only allow access with ?secure_key=jostum_debug_2026
$secure_key = $_GET['secure_key'] ?? $_POST['secure_key'] ?? '';
if ($secure_key !== 'jostum_debug_2026') {
    http_response_code(403);
    echo "Access Denied. Please provide the correct secure_key parameter.";
    exit;
}

// 1. Get Table Schema
$schema = [];
try {
    $stmt = $pdo->query("DESCRIBE users");
    $schema = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $schema_error = $e->getMessage();
}

// 2. Get User Column Flags
$columns = ['role', 'role_id', 'password_hash', 'totp_secret', 'totp_enabled'];
$flags = [];
foreach ($columns as $col) {
    try {
        $pdo->query("SELECT `{$col}` FROM users LIMIT 0");
        $flags[$col] = true;
    } catch (Throwable $e) {
        $flags[$col] = false;
    }
}

// 3. Get Roles List
$roles = [];
try {
    $stmt = $pdo->query("SELECT role_id, role_key, role_name FROM roles ORDER BY role_id");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// 4. Get Users list
$users = [];
try {
    $stmt = $pdo->query("
        SELECT u.user_id, u.email, u.full_name, u.role_id, u.account_status, LENGTH(u.password_hash) as pass_len, u.totp_enabled
        FROM users u
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // If it fails (e.g. no role_id or password_hash), fall back to basic select
    try {
        $stmt = $pdo->query("SELECT user_id, email, account_status FROM users");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $users_error = $e->getMessage();
    }
}

// 5. Test Login Form processing
$test_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $email = trim($_POST['test_email']);
    $password = $_POST['test_password'];

    $passwordColumn = $flags['password_hash'] ? 'password_hash' : 'password';
    $totpSelect = $flags['totp_secret'] ? ', u.totp_secret, u.totp_enabled' : '';

    $test_user = null;
    $query_used = "";

    try {
        if ($flags['role_id']) {
            $query_used = "SELECT u.user_id, u.{$passwordColumn} AS password_hash, r.role_key AS role, u.full_name{$totpSelect} FROM users u LEFT JOIN roles r ON u.role_id = r.role_id WHERE u.email = :email LIMIT 1";
            $stmt = $pdo->prepare($query_used);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $test_user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } elseif ($flags['role']) {
            $query_used = "SELECT user_id, {$passwordColumn} AS password_hash, role, full_name{$totpSelect} FROM users WHERE email = :email LIMIT 1";
            $stmt = $pdo->prepare($query_used);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $test_user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (!$test_user) {
            $test_result = [
                'success' => false,
                'step' => 'User Query',
                'message' => "User NOT found for email: '{$email}' using query: [{$query_used}]"
            ];
        } else {
            $pass_verify = password_verify($password, $test_user['password_hash']);
            
            $allowedRoles = [
                'SUPER_ADMIN', 'ICT_ADMIN', 'PORTAL_ADMIN', 'REGISTRY', 'ADMISSIONS_OFFICER', 
                'BURSARY', 'PG_SCHOOL_OFFICER', 'FACULTY_OFFICER', 'DEPARTMENT_ADMIN', 'HOD', 
                'SUPERVISOR', 'REVIEWER', 'ADMIN', 'ICT_SUPPORT', 'STUDENT_MANAGER', 
                'ACADEMIC_MANAGER', 'SUPERVISOR_MANAGER', 'ICT_STAFF'
            ];

            $rawRole = $test_user['role'] ?? '';
            
            // Normalize role
            $loginRole = strtoupper(trim((string)$rawRole));
            $loginRoleNormalized = str_replace([' ', '-'], '_', $loginRole);
            
            $role_allowed = in_array($loginRoleNormalized, $allowedRoles, true);

            $test_result = [
                'success' => $pass_verify && $role_allowed,
                'step' => 'Validation',
                'user_details' => $test_user,
                'password_match' => $pass_verify ? "MATCH" : "FAIL",
                'role_key' => $rawRole,
                'role_normalized' => $loginRoleNormalized,
                'role_allowed' => $role_allowed ? "ALLOWED" : "BLOCKED",
                'allowed_roles_list' => $allowedRoles
            ];
        }
    } catch (Throwable $e) {
        $test_result = [
            'success' => false,
            'step' => 'Exception',
            'message' => $e->getMessage()
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database & Login Diagnostics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: sans-serif; padding: 30px; }
        .card { margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        pre { background: #1e293b; color: #f8fafc; padding: 15px; border-radius: 8px; font-size: 0.85rem; }
    </style>
</head>
<body>
<div class="container-fluid">
    <h1 class="mb-4">JOSTUM PG System Diagnostics</h1>

    <div class="row">
        <!-- Schema and Flags -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white fw-bold">1. Column Flags (from DESCRIBE/SELECT checks)</div>
                <div class="card-body">
                    <pre><?= json_encode($flags, JSON_PRETTY_PRINT) ?></pre>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-dark text-white fw-bold">2. Users Table Schema Details</div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (isset($schema_error)): ?>
                        <div class="alert alert-danger"><?= $schema_error ?></div>
                    <?php else: ?>
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schema as $s): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($s['Field']) ?></strong></td>
                                        <td><?= htmlspecialchars($s['Type']) ?></td>
                                        <td><?= htmlspecialchars($s['Null']) ?></td>
                                        <td><?= htmlspecialchars($s['Key']) ?></td>
                                        <td><?= htmlspecialchars((string)$s['Default']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Users and Test Login -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white fw-bold">3. Database Users List</div>
                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                    <?php if (isset($users_error)): ?>
                        <div class="alert alert-danger"><?= $users_error ?></div>
                    <?php else: ?>
                        <table class="table table-sm">
                            <thead>
                                <tr><th>ID</th><th>Email</th><th>Role ID</th><th>Status</th><th>Pass Len</th><th>TOTP</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td><?= $u['user_id'] ?></td>
                                        <td><?= htmlspecialchars($u['email']) ?></td>
                                        <td><?= $u['role_id'] ?? 'NULL' ?></td>
                                        <td><?= $u['account_status'] ?? 'NULL' ?></td>
                                        <td><?= $u['pass_len'] ?? 'NULL' ?></td>
                                        <td><?= isset($u['totp_enabled']) ? ($u['totp_enabled'] ? 'ON' : 'OFF') : 'N/A' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-warning text-dark fw-bold">4. Live Login Credentials Test</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="secure_key" value="<?= htmlspecialchars($secure_key) ?>">
                        <div class="mb-3">
                            <label class="form-label">Email to test:</label>
                            <input type="email" name="test_email" class="form-control" placeholder="staff@jostum.edu.ng" required value="<?= htmlspecialchars($_POST['test_email'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password to test:</label>
                            <input type="text" name="test_password" class="form-control" placeholder="Password123!" required value="<?= htmlspecialchars($_POST['test_password'] ?? '') ?>">
                        </div>
                        <button type="submit" class="btn btn-warning w-100 fw-bold">TEST CREDENTIALS</button>
                    </form>

                    <?php if ($test_result): ?>
                        <div class="mt-4">
                            <h5>Test Results:</h5>
                            <?php if ($test_result['success']): ?>
                                <div class="alert alert-success fw-bold">✓ Login Credentials match and role is allowed!</div>
                            <?php else: ?>
                                <div class="alert alert-danger fw-bold">✗ Credentials Verification Failed!</div>
                            <?php endif; ?>
                            <pre><?= json_encode($test_result, JSON_PRETTY_PRINT) ?></pre>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
