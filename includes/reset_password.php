<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../config/urls.php';

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
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$tokenValid) {
        $error = 'This reset link is invalid or has expired.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $newHash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $stmt->execute([$newHash, (int) $resetRow['user_id']]);

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
    <link rel="icon" type="image/jpeg" href="/JOSTUM/ADMIN/images/logo.jpeg">
    <title>Reset Password - JOSTUM Postgraduate</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    
    <style>
        /* Reusing your consistent styling */
        :root {
            --primary-blue: #2c4474;
            --accent-yellow: #f1b434;
            --overlay-blue: rgba(44, 68, 116, 0.88);
        }

        body, html {
            height: 100%;
            margin: 0;
            font-family: 'Roboto', sans-serif;
            background: #cbd5e0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .recovery-card {
            width: 100%;
            max-width: 450px;
            background: url('../images/jostumgate.jpeg');
            background-size: cover;
            background-position: center;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
        }

        .form-overlay {
            background: var(--overlay-blue);
            backdrop-filter: blur(12px);
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
            min-height: 500px;
        }

        .icon-header {
            font-size: 3rem;
            color: var(--accent-yellow);
            margin-bottom: 20px;
        }

        h2 {
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .form-control-custom {
            border-radius: 25px;
            padding: 12px 25px;
            border: none;
            width: 100%;
            /* Removed bottom margin here to handle it in wrapper */
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .btn-recover {
            background: var(--accent-yellow);
            color: var(--primary-blue);
            border: none;
            border-radius: 25px;
            padding: 12px;
            font-weight: 800;
            width: 100%;
            margin-top: 10px;
            transition: 0.3s;
        }

        .btn-recover:hover {
            background: #e0a320;
            transform: translateY(-2px);
        }

        .back-to-login {
            margin-top: 25px;
            font-size: 0.85rem;
        }
        
        .back-to-login a {
            color: #fff;
            text-decoration: none;
        }

        /* NEW STYLES FOR PASSWORD TOGGLE */
        .password-wrapper {
            position: relative;
            width: 100%;
            margin-bottom: 15px;
        }
        
        .password-wrapper input {
            padding-right: 45px; /* Space for the eye icon */
        }

        .toggle-password {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-blue);
            cursor: pointer;
            z-index: 10;
        }
    </style>
</head>
<body>

<div class="recovery-card">
    <div class="form-overlay">

        <?php if ($success): ?>
            <div class="icon-header" style="color: #2ecc71;">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2>Success!</h2>
            <p class="mb-4">Password reset successfully! You can now login.</p>
            <a href="<?= htmlspecialchars(app_url('APPLICANT/ADMISSIONS/login.php')) ?>" class="btn btn-recover">GO TO LOGIN</a>
        
        <?php elseif ($error && !$tokenValid): ?>
            <div class="icon-header" style="color: #e74c3c;">
                <i class="fas fa-times-circle"></i>
            </div>
            <h2>Error</h2>
            <p class="mb-4"><?php echo $error; ?></p>
            <div class="back-to-login">
                <a href="forgot_pass.php">Request a new link</a>
            </div>

        <?php elseif ($tokenValid): ?>
            <div class="icon-header">
                <i class="fas fa-lock-open"></i>
            </div>
            <h2>New Password</h2>
            <p class="mb-4" style="opacity: 0.9; font-size: 0.9rem;">
                Create a strong password for your account.
            </p>

            <?php if ($error): ?>
                <div class="alert alert-danger w-100 py-2" role="alert" style="font-size: 0.9rem;">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" style="width: 100%;">
                
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" class="form-control-custom" placeholder="New Password" required minlength="6">
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('password', this)"></i>
                </div>

                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control-custom" placeholder="Confirm Password" required minlength="6">
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password', this)"></i>
                </div>
                
                <button type="submit" class="btn btn-recover">RESET PASSWORD</button>
            </form>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    function togglePassword(inputId, icon) {
        const input = document.getElementById(inputId);
        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash");
        } else {
            input.type = "password";
            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
        }
    }
</script>

</body>
</html>
