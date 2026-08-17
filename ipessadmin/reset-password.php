<?php
session_start();
require_once __DIR__ . '/admin/db.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = false;
$tokenValid = false;
$resetRow = null;

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

if ($token !== '') {
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token_hash = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$tokenHash]);
    $resetRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($resetRow) {
        $tokenValid = true;
    } else {
        $error = 'This reset link is invalid or has expired.';
    }
} else {
    $error = 'Invalid reset link.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$tokenValid) {
        $error = 'This reset link is invalid or has expired.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $stmt->execute([$hashed, (int) $resetRow['user_id']]);

        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE id = ?");
        $stmt->execute([(int) $resetRow['id']]);

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="/ADMIN/images/logo.jpeg">
    <title>Reset Password - JOSTUM PG SCHOOL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .background-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(2px);
            z-index: 1;
        }

        .login-card {
            position: relative;
            z-index: 2;
            width: 900px;
            min-height: 600px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            display: flex;
        }

        .branding-panel {
            flex: 1;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            text-align: center;
        }

        .university-name {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
            line-height: 1.2;
        }

        .postgraduate-badge {
            background: #f8f9fa;
            color: #6c757d;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 40px;
        }

        .logo-container {
            margin: 40px 0;
        }

        .university-logo {
            max-width: 150px;
            height: auto;
        }

        .established-text {
            color: #adb5bd;
            font-size: 14px;
            margin-top: 40px;
        }

        .login-panel {
            flex: 1;
            background: #1a252f;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 40px;
            overflow-y: auto;
        }

        .welcome-heading {
            font-size: 32px;
            font-weight: 300;
            margin-bottom: 10px;
        }

        .welcome-subtitle {
            color: #adb5bd;
            margin-bottom: 32px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            padding: 12px 15px;
            font-size: 16px;
        }

        .form-control::placeholder {
            color: #adb5bd;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #ffc107;
            box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
            color: white;
        }

        .password-container {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #adb5bd;
            cursor: pointer;
            padding: 0;
        }

        .btn-login {
            background: #ffc107;
            border: none;
            border-radius: 8px;
            padding: 15px;
            font-size: 16px;
            font-weight: 600;
            color: #1a252f;
            width: 100%;
            margin-top: 20px;
            transition: background-color 0.3s;
        }

        .btn-login:hover {
            background: #ffca2c;
        }

        .back-to-login {
            color: #ffc107;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            margin-bottom: 12px;
        }

        .back-to-login:hover {
            color: #ffca2c;
        }

        .footer-text {
            text-align: center;
            color: #adb5bd;
            font-size: 12px;
            margin-top: auto;
            padding-top: 20px;
        }

        @media (max-width: 768px) {
            .login-card {
                width: 100%;
                min-height: auto;
                flex-direction: column;
                margin: 20px;
            }

            .branding-panel,
            .login-panel {
                flex: none;
                padding: 30px 20px;
            }

            .welcome-heading {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
<div style="
    background: url('../APPLICANT/ADMISSIONS/images/jostumgate.png') no-repeat center center/cover;
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

                <div class="logo-container">
                    <img src="images/logo.jpeg" class="university-logo" alt="University Logo">
                </div>

                <div class="established-text">
                    Established 1988
                </div>
            </div>

            <div class="login-panel">
                <div>
                    <a href="login.php" class="back-to-login">&larr; Back to login</a>
                    <h1 class="welcome-heading">Reset Password</h1>
                    <p class="welcome-subtitle">Create a new secure password for your portal account.</p>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">Password updated successfully.</div>
                        <a href="login.php" class="btn btn-login">Back to Login</a>
                    <?php elseif ($tokenValid): ?>
                        <form method="post">
                            <div class="form-group">
                                <div class="password-container">
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="new_password"
                                        name="new_password"
                                        placeholder="New Password"
                                        required
                                    >
                                    <button type="button" class="password-toggle" data-target="new_password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="password-container">
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="confirm_password"
                                        name="confirm_password"
                                        placeholder="Confirm Password"
                                        required
                                    >
                                    <button type="button" class="password-toggle" data-target="confirm_password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn-login">Reset Password</button>
                        </form>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-login">Back to Login</a>
                    <?php endif; ?>
                </div>

                <div class="footer-text">
                    © 2026 JOSTUM ICT Directorate
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('.password-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            if (!input || !icon) {
                return;
            }
            const nextType = input.type === 'password' ? 'text' : 'password';
            input.type = nextType;
            icon.className = nextType === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
        });
    });
</script>
</body>
</html>
