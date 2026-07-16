<?php
ob_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
require 'db.php';

// Load module check helper
$admissions_closed = false;
try {
    if (!function_exists('is_module_active')) {
        function is_module_active_local(string $module_key, $pdo): bool {
            try {
                $stmt = $pdo->prepare("SELECT is_active FROM system_modules WHERE module_key = ?");
                $stmt->execute([$module_key]);
                $val = $stmt->fetchColumn();
                return $val === false || (int)$val === 1;
            } catch (Throwable $e) {
                return true;
            }
        }
        $admissions_closed = !is_module_active_local('admissions', $pdo);
    } else {
        $admissions_closed = !is_module_active('admissions');
    }
} catch (Throwable $e) {
    $admissions_closed = false;
}

$error = '';

$max_attempts = 5;       
$lockout_time = 15;      
$ip_address = $_SERVER['REMOTE_ADDR'];

// Ensure login_attempts table exists with correct AUTO_INCREMENT schema
// (guards against servers where the table was imported without AUTO_INCREMENT)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `ip_address` VARCHAR(45) NOT NULL,
            `attempt_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // Fix existing table if id column lacks AUTO_INCREMENT
    $col = $pdo->query("SHOW COLUMNS FROM `login_attempts` LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
    if ($col && stripos($col['Extra'], 'auto_increment') === false) {
        $pdo->exec("ALTER TABLE `login_attempts` MODIFY `id` INT NOT NULL AUTO_INCREMENT");
    }
} catch (Throwable $e) {
    // Silent: table setup errors should not break the login page
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $stmt = $pdo->prepare("SELECT MIN(attempt_time) FROM login_attempts WHERE ip_address = ? AND attempt_time > (NOW() - INTERVAL ? MINUTE)");
    $stmt->execute([$ip_address, $lockout_time]);
    $first_attempt = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > (NOW() - INTERVAL ? MINUTE)");
    $stmt->execute([$ip_address, $lockout_time]);
    $failed_attempts = $stmt->fetchColumn();

    if ($failed_attempts >= $max_attempts && $first_attempt) {
        $expiry_time = strtotime($first_attempt) + ($lockout_time * 60);
        $remaining_seconds = $expiry_time - time();
        
        if ($remaining_seconds > 0) {
            $lockout_expiry_js = $expiry_time * 1000; 
            $error = "Too many failed attempts. Please wait <span id='countdown'></span> before trying again.";
        }
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT user_id, password_hash, account_status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            
            if ($user['account_status'] === 'Suspended') {
                $error = "Your account has been suspended. Please contact support.";
            } 
            elseif ($user['account_status'] === 'Locked') {
                $error = "Your account is locked due to security reasons. Please reset your password or contact admin.";
            } 
            else {
                $clearStmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
                $clearStmt->execute([$ip_address]);

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_email'] = $email;
                $_SESSION['role'] = 'STUDENT';
                $_SESSION['last_activity'] = time();

                try {
                    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                    $updateStmt->execute([$user['user_id']]);
                } catch (Throwable $e) {
                    // last_login column may not exist on remote server — non-fatal
                }

                if (file_exists(__DIR__ . '/../../helpers/load_application_data.php')) {
                    try {
                        require __DIR__ . '/../../helpers/load_application_data.php';
                    } catch (Throwable $e) {
                        // Non-fatal: session data preloading failed
                    }
                }

                $isAdmitted = false;
                try {
                    $hasCurrentStatus = (bool) $pdo->query("SHOW COLUMNS FROM applications LIKE 'current_status'")->fetch(PDO::FETCH_ASSOC);
                    $statusCols = $hasCurrentStatus ? "status, current_status" : "status";
                    $stmt = $pdo->prepare("SELECT {$statusCols} FROM applications WHERE user_id = ? ORDER BY application_id DESC LIMIT 1");
                    $stmt->execute([$user['user_id']]);
                    $app = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($app) {
                        $status = strtolower((string) ($app['status'] ?? ''));
                        $current = strtolower((string) ($app['current_status'] ?? ''));
                        $isAdmitted = ($status === 'admitted' || $current === 'admission_approved');
                    }
                } catch (Throwable $e) {
                    $isAdmitted = false;
                }

                if ($isAdmitted) {
                    redirect_to('APPLICANT/ACADEMICS/student-portal/index.php#dashboard');
                }

                // Block non-admitted users if admissions module is closed
                if ($admissions_closed) {
                    // Destroy session for non-admitted users so they can't access the dashboard
                    session_destroy();
                    $error = "<strong>Admissions Exercise is Closed.</strong><br>Access to the admissions portal is currently disabled. Please check back later or contact the admissions office.";
                } else {
                    redirect_to('APPLICANT/ADMISSIONS/dashboard.php');
                }
            }
        } else {
            $insertStmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())");
            $insertStmt->execute([$ip_address]);
            
            $remaining = $max_attempts - ($failed_attempts + 1);
            if ($remaining > 0) {
                $error = "Invalid email or password. ($remaining attempts remaining)";
            } else {
                $error = "Invalid email or password. You are now locked out.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Login - IPESS FUAM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <link rel="icon" type="image/png" href="<?= htmlspecialchars(app_url('asset/homepage/ipess_logo.png'), ENT_QUOTES, 'UTF-8'); ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-green: #6EB533;
            --accent-burgundy: #782D32;
            --light-overlay: rgba(255, 255, 255, 0.95);
        }

        body {
            background: linear-gradient(rgba(0, 0, 0, 0.15), rgba(0, 0, 0, 0.15)), 
                        url('./images/auditorium.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 15px;
        }

        .login-card {
            background: var(--light-overlay);
            width: 100%;
            padding: 20px 20px;
            border-radius: 8px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
            color: #333333;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        .logo-container {
            width: 85px;
            height: 85px;
            background-color: white;
            border-radius: 50%;
            margin: 0 auto 15px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border: 2px solid var(--primary-green);
        }

        .uni-logo {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .login-title {
            font-size: 1.45rem;
            font-weight: 700;
            margin-bottom: 25px;
            color: var(--accent-burgundy);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            height: 54px;
            border-radius: 4px 0 0 4px !important;
            font-size: 16px !important; 
            border: 1px solid #dcdcdc;
            border-right: none;
            background-color: #ffffff;
            color: #333;
        }

        .form-control:focus {
            border-color: var(--primary-green);
            box-shadow: none;
        }

        .input-group-text {
            background-color: #ffffff !important;
            border: 1px solid #dcdcdc;
            border-left: none;
            color: #666;
            border-radius: 0 4px 4px 0 !important;
            padding-right: 15px;
            cursor: pointer;
        }

        .password-toggle:hover .input-group-text {
            color: var(--primary-green);
        }

        .btn-signin {
            background-color: var(--primary-green);
            border: none;
            height: 54px;
            font-weight: 600;
            font-size: 1.1rem;
            border-radius: 4px;
            margin-top: 10px;
            color: white;
            transition: all 0.2s ease-in-out;
        }

        .btn-signin:hover {
            background-color: #5c972a;
            color: white;
            transform: translateY(-1px);
        }

        .footer-links {
            margin-top: 20px;
            font-size: 0.95rem;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .footer-links a {
            color: var(--accent-burgundy);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: var(--primary-green);
            text-decoration: underline;
        }

        @media (min-width: 576px) {
            .login-card { max-width: 420px; padding: 40px; }
            .logo-container { width: 95px; height: 95px; }
            .login-title { font-size: 1.6rem; }
            .footer-links { flex-direction: row; justify-content: center; gap: 0; }
            .forgot-pass::after { content: "|"; margin: 0 10px; color: rgba(0,0,0,0.15); }
        }
    </style>
</head>
<body>

<main class="login-card">
    <header>
        <div class="logo-container">
            <img src="<?= htmlspecialchars(app_url('asset/homepage/ipess_logo.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="IPESS FUAM Logo" class="uni-logo">
        </div>
        <h1 class="login-title">IPESS Student Login</h1>
    </header>

    <?php if($error): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="input-group mb-3">
            <input type="email" name="email" class="form-control" placeholder="Email Address" required>
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
        </div>

        <div class="input-group mb-4">
            <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Password" required>
            <span class="input-group-text password-toggle" onclick="togglePassword()">
                <i id="toggleIcon" class="bi bi-eye"></i>
            </span>
        </div>

        <button type="submit" class="btn btn-signin w-100">Sign In</button>
    </form>

    <footer class="footer-links">
        <a href="./includes/forgot_pass.php" class="forgot-pass">Forgot Password?</a>
        <a href="<?= htmlspecialchars(app_absolute_url('APPLICANT/ADMISSIONS/register.php'), ENT_QUOTES, 'UTF-8'); ?>">Create account</a>
    </footer>
</main>
<script>
    function togglePassword() {
        const passwordInput = document.getElementById('passwordInput');
        const toggleIcon = document.getElementById('toggleIcon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('bi-eye');
            toggleIcon.classList.add('bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('bi-eye-slash');
            toggleIcon.classList.add('bi-eye');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const countdownElement = document.getElementById('countdown');
        const expiryTime = <?php echo isset($lockout_expiry_js) ? $lockout_expiry_js : 0; ?>;
        const submitBtn = document.querySelector('.btn-signin');
        const inputs = document.querySelectorAll('.form-control');

        if (countdownElement && expiryTime > 0) {
            if (submitBtn) submitBtn.disabled = true;
            inputs.forEach(input => input.disabled = true);

            const updateTimer = () => {
                const now = new Date().getTime();
                const distance = expiryTime - now;

                if (distance < 0) {
                    if (submitBtn) submitBtn.disabled = false;
                    inputs.forEach(input => input.disabled = false);
                    location.reload(); 
                    return;
                }

                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                countdownElement.innerHTML = minutes + "m " + seconds + "s";
            };

            updateTimer();
            setInterval(updateTimer, 1000);
        }
    });
</script>


</body>
</html>
