<?php
session_start();
require_once __DIR__ . '/admin/db.php';
require_once __DIR__ . '/includes/mailer.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    email VARCHAR(150) NOT NULL,
                    token_hash VARCHAR(64) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_reset_email (email),
                    INDEX idx_reset_token (token_hash)
                ) ENGINE=InnoDB
            ");

            $stmt = $pdo->prepare("SELECT user_id, full_name FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = 'No account found with that email address.';
            } else {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([(int) $user['user_id']]);
                $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, email, token_hash, expires_at) VALUES (?, ?, ?, ?)");
                $stmt->execute([(int) $user['user_id'], $email, $tokenHash, $expiresAt]);

                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $basePath = rtrim(dirname($_SERVER['REQUEST_URI'] ?? '/'), '/');
                $resetLink = $protocol . '://' . $host . $basePath . '/reset-password.php?token=' . urlencode($token);

                $body = "<p>We received a request to reset your account password.</p>
                         <p>Click the button below to set a new password. This link expires in 1 hour.</p>
                         <p><a href=\"{$resetLink}\" style=\"background:#ffc107;color:#1a252f;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600;\">Reset Password</a></p>";

                $mailResult = portal_send_mail($email, $user['full_name'] ?? 'User', 'Password Reset', $body, "Reset link: {$resetLink}");
                if ($mailResult['success']) {
                    $success = 'Reset link sent to your email.';
                } else {
                    $error = $mailResult['message'];
                }
            }
        } catch (Exception $e) {
            $error = 'An error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="/JOSTUM/ADMIN/images/logo.jpeg">
    <title>Forgot Password - JOSTUM PG SCHOOL</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">

    <style>
        .background-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(2px);
            z-index: 1;
        }

        .login-card {
            position: relative;
            z-index: 2;
            width: 900px;
            min-height: 550px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            display: flex;
            background: #fff;
        }

        .branding-panel {
            flex: 1;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            text-align: center;
        }

        .university-name {
            font-size: 22px;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1.3;
        }

        .postgraduate-badge {
            margin-top: 10px;
            background: #f1f3f5;
            color: #6c757d;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .university-logo {
            max-width: 150px;
            margin: 40px 0;
        }

        .established-text {
            font-size: 13px;
            color: #adb5bd;
        }

        .login-panel {
            flex: 1;
            background: #1f2f46;
            color: #ffffff;
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .welcome-heading {
            font-size: 30px;
            font-weight: 300;
            margin-bottom: 8px;
        }

        .welcome-subtitle {
            color: #adb5bd;
            margin-bottom: 40px;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 14px 15px;
            color: #ffffff;
        }

        .form-control::placeholder {
            color: #ced4da;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #ffc107;
            box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
            color: #ffffff;
        }

        .btn-primary {
            background: #ffc107;
            border: none;
            color: #1f2f46;
            font-weight: 600;
            padding: 14px;
            border-radius: 8px;
        }

        .btn-primary:hover {
            background: #ffca2c;
        }

        .footer-text {
            margin-top: auto;
            text-align: center;
            font-size: 12px;
            color: #adb5bd;
            padding-top: 30px;
        }

        @media (max-width: 768px) {
            .login-card {
                flex-direction: column;
                width: 95%;
            }
        }
    </style>
</head>

<body>

<div style="
    background: url('../APPLICANT/ADMISSIONS/images/jostumgate.png') no-repeat center center / cover;
    height: 100vh;
    position: relative;
">
    <div class="background-overlay"></div>

    <div class="d-flex justify-content-center align-items-center h-100">
        <div class="login-card">

            <div class="branding-panel">
                <div class="university-name">
                    JOSEPH SARWUN TARKA UNIVERSITY MAKURDI
                </div>

                <div class="postgraduate-badge">
                    POSTGRADUATE SCHOOL
                </div>

                <img src="images/logo.jpeg" class="university-logo" alt="University Logo">

                <div class="established-text">
                    Established 1988
                </div>
            </div>

            <div class="login-panel">

                <div>
                    <h1 class="welcome-heading">Forgot Password</h1>
                    <p class="welcome-subtitle">
                        Enter your email to receive a password reset link.
                    </p>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="mb-4">
                            <input
                                type="email"
                                class="form-control"
                                name="email"
                                placeholder="Email Address"
                                required
                            >
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            Send Reset Link
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <a href="login.php" class="text-warning text-decoration-none">
                            Back to Login
                        </a>
                    </div>
                </div>

                <div class="footer-text">
                    &copy; 2026 JOSTUM ICT Directorate
                </div>

            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
