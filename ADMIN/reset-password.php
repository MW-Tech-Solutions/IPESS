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
    <link rel="icon" type="image/jpeg" href="/JOSTUM/ADMIN/images/logo.jpeg">
    <title>Reset Password - JOSTUM PG SCHOOL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Reset Password</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                        <div class="alert alert-success">Password updated successfully.</div>
                        <?php endif; ?>
                        <?php if (!$success && $tokenValid): ?>
                        <form method="post">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                        </form>
                        <?php elseif ($success): ?>
                        <a href="login.php" class="btn btn-primary w-100">Back to Login</a>
                        <?php else: ?>
                        <a href="login.php" class="btn btn-primary w-100">Back to Login</a>
                        <?php endif; ?>
                        <div class="text-center mt-3">
                            <a href="login.php">Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
