<?php
require 'db.php';

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
    $newPassword = $_POST['password'] ?? '';
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
    <link rel="icon" type="image/jpeg" href="/JOSTUM/ADMIN/images/logo.jpeg">
<title>Set New Password - JOSTUM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #cbd5e0; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; }
        .reset-card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        .brand-icon { font-size: 3rem; color: #2c4474; margin-bottom: 20px; }
        .btn-primary { background: #2c4474; border: none; border-radius: 25px; padding: 12px; font-weight: bold; width: 100%; }
        .form-control { border-radius: 10px; padding: 12px; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="reset-card">
    <div class="brand-icon"><i class="fas fa-lock-open"></i></div>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Password updated successfully!
        </div>
        <p>You can now log in with your new credentials.</p>
        <a href="<?= htmlspecialchars(app_url('APPLICANT/ADMISSIONS/login.php')) ?>" class="btn btn-primary">Go to Login</a>
    <?php else: ?>
        <h3>New Password</h3>
        <p class="text-muted small">Please choose a strong password to secure your account.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger small"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($tokenValid): ?>
            <form method="POST">
                <div class="text-start">
                    <label class="form-label small fw-bold">New Password</label>
                    <input type="password" name="password" class="form-control" placeholder="********" required>
                    
                    <label class="form-label small fw-bold">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="********" required>
                </div>
                
                <button type="submit" class="btn btn-primary">UPDATE PASSWORD</button>
            </form>
        <?php else: ?>
            <a href="<?= htmlspecialchars(app_url('APPLICANT/ADMISSIONS/login.php')) ?>" class="btn btn-primary">Back to Login</a>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>
