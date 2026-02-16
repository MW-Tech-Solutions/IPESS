<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require 'db.php';

$error = '';

$max_attempts = 5;       
$lockout_time = 15;      
$ip_address = $_SERVER['REMOTE_ADDR'];

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

                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $updateStmt->execute([$user['user_id']]);
                
                require __DIR__ . '/../../helpers/load_application_data.php';

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
                } catch (Exception $e) {
                    $isAdmitted = false;
                }

                if ($isAdmitted) {
                    redirect_to('APPLICANT/ACADEMICS/student-portal/index.php#dashboard');
                }

                redirect_to('dashboard.php');
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
    <title>Student Login - JOSTUM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <link rel="icon" type="image/jpeg" href="/JOSTUM/ADMIN/images/logo.jpeg">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-blue: #21a1f1;
            --navy-overlay: rgba(18, 36, 62, 0.75);
        }

        body {
            background: linear-gradient(rgba(0, 0, 0, 0.2), rgba(0, 0, 0, 0.2)), 
                        url('./images/jostumgate-opt.jpg') no-repeat center center fixed;
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
            background: var(--navy-overlay);
            width: 100%;
            padding: 20px 20px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            color: white;
            text-align: center;
            backdrop-filter: blur(5px);
        }

        .logo-container {
            width: 85px;
            height: 85px;
            background-color: white;
            border-radius: 10%;
            margin: 0 auto 15px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        .uni-logo {
            width: 100%;
            height: 100%;
            object-fit: fill;
        }

        .uni-name {
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 20px;
            opacity: 0.9;
            line-height: 1.4;
        }

        .login-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .form-control {
            height: 54px;
            border-radius: 10px 0 0 10px !important;
            font-size: 16px !important; 
            border: 1px solid transparent;
        }

        .input-group-text {
            background-color: white !important;
            border: none;
            color: #666;
            border-radius: 0 10px 10px 0 !important;
            padding-right: 15px;
        }

        .password-toggle {
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .password-toggle:hover {
            color: var(--primary-blue);
        }

        .btn-signin {
            background-color: var(--primary-blue);
            border: none;
            height: 54px;
            font-weight: 600;
            font-size: 1.1rem;
            border-radius: 10px;
            margin-top: 1px;
        }

        .footer-links {
            margin-top: 20px;
            font-size: 0.95rem;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .footer-links a {
            color: var(--primary-blue);
            text-decoration: none;
        }

        @media (min-width: 576px) {
            .login-card { max-width: 400px; padding: 40px; }
            .logo-container { width: 95px; height: 95px; }
            .login-title { font-size: 1.8rem; }
            .footer-links { flex-direction: row; justify-content: center; gap: 0; }
            .forgot-pass::after { content: "|"; margin: 0 10px; opacity: 0.5; }
        }
    </style>
</head>
<body>

<main class="login-card">
    <header>
        <div class="logo-container">
            <img src="./images/jostum.jpeg" alt="JOSTUM Logo" class="uni-logo">
        </div>
        <h1 class="login-title">Student Login</h1>
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
        <a href="register.php">Create account</a>
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
