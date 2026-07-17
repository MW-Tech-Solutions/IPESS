<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('session.gc_maxlifetime', '1800');
session_set_cookie_params(['lifetime' => 1800, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once 'admin/db.php';
require_once __DIR__ . '/../config/urls.php';

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    admin_redirect_by_role($role);
}

$error = '';
$email = '';

function admin_redirect_by_role(string $role): void {
    $dashboard = dashboard_for_role($role);
    if ($dashboard !== 'ADMIN/login.php' && $dashboard !== '') {
        redirect_to($dashboard);
    } else {
        redirect_to('ADMIN/index.php');
    }
}

function user_login_query(PDO $pdo, string $email): ?array {
    $passwordColumn = 'password_hash';
    try {
        $hasPasswordHash = false;
        try {
            $pdo->query("SELECT password_hash FROM users LIMIT 0");
            $hasPasswordHash = true;
        } catch (Throwable $e) {}
        
        $passwordColumn = $hasPasswordHash ? 'password_hash' : 'password';
        
        $hasRole = false;
        try {
            $pdo->query("SELECT role FROM users LIMIT 0");
            $hasRole = true;
        } catch (Throwable $e) {}

        if ($hasRole) {
            $stmt = $pdo->prepare("SELECT user_id, {$passwordColumn} AS password_hash, role, full_name FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.{$passwordColumn} AS password_hash, r.role_key AS role, u.full_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.role_id
            WHERE u.email = :email
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email === '' || $password === '') {
        $error = 'Password is required.';
    } else {
        $user = user_login_query($pdo, $email);
        $allowedRoles = ['SUPER_ADMIN', 'ADMIN', 'DEPARTMENT_ADMIN', 'SUPERVISOR', 'REVIEWER'];
        if ($user && password_verify($password, $user['password_hash']) && in_array($user['role'], $allowedRoles, true)) {
            $_SESSION['user_id'] = (int) $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            admin_redirect_by_role($_SESSION['role']);
        } else {
            $error = 'Invalid password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Locked - JOSTUM PG SCHOOL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #f4f7fb; }
        .lock-card { max-width: 460px; margin: 8vh auto; border-radius: 16px; box-shadow: 0 12px 30px rgba(0,0,0,0.08); }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const stored = localStorage.getItem('admin_reauth_email') || '';
            const emailInput = document.getElementById('reloginEmail');
            if (emailInput) {
                if (stored) {
                    emailInput.value = stored;
                    emailInput.setAttribute('readonly', 'readonly');
                } else {
                    emailInput.removeAttribute('readonly');
                }
            }
        });
    </script>
</head>
<body>
    <div class="card lock-card">
        <div class="card-body p-4">
            <div class="text-center mb-3">
                <i class="bi bi-shield-lock-fill fs-2 text-primary"></i>
                <h5 class="mt-2 mb-1">Session Locked</h5>
                <p class="text-muted mb-0">Your session expired due to inactivity. Enter your password to continue.</p>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" id="reloginEmail" name="email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" required>
                </div>
                <button class="btn btn-primary w-100" type="submit">Unlock Session</button>
                <div class="text-center mt-2">
                    <a href="login.php?reset=1" class="text-muted small">Back to full login</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
