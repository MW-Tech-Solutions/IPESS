<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../ADMIN/includes/mailer.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$email) {
    echo json_encode(['status' => 'error', 'message' => 'Please provide a valid email address.']);
    exit;
}

function ensure_password_reset_table(PDO $pdo): void {
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
}

try {
    ensure_password_reset_table($pdo);

    $stmt = $pdo->prepare("SELECT user_id, full_name FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'No account found with that email address.']);
        exit;
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([(int) $user['user_id']]);
    $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, email, token_hash, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([(int) $user['user_id'], $email, $tokenHash, $expiresAt]);

    require_once __DIR__ . '/../config/urls.php';
    $resetLink = app_absolute_url('APPLICANT/ADMISSIONS/reset_password.php?token=' . urlencode($token));

    $html = "<p>We received a request to reset your password.</p>
             <p>Click the button below to set a new password. This link expires in 1 hour.</p>
             <p><a href=\"{$resetLink}\" style=\"background:#ffc107;color:#1a252f;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600;\">Reset Password</a></p>";

    $mailResult = portal_send_mail($email, $user['full_name'] ?? 'User', 'Password Reset Request', $html, "Reset link: {$resetLink}");
    if (!$mailResult['success']) {
        echo json_encode(['status' => 'error', 'message' => $mailResult['message']]);
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => 'Recovery link sent to your email.']);
    exit;
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error. Please try again.']);
    exit;
}
?>
