<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/phpqrcode.php';

header('Content-Type: application/json');

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});


function ensure_totp_columns(PDO $pdo): void {
    try {
        $hasSecret = $pdo->query("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'totp_secret'")->fetchColumn();
        if (!$hasSecret) {
            $pdo->exec("ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) NULL");
        }
        $hasEnabled = $pdo->query("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'totp_enabled'")->fetchColumn();
        if (!$hasEnabled) {
            $pdo->exec("ALTER TABLE users ADD COLUMN totp_enabled TINYINT(1) DEFAULT 0");
        }
        $hasVerified = $pdo->query("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'totp_verified_at'")->fetchColumn();
        if (!$hasVerified) {
            $pdo->exec("ALTER TABLE users ADD COLUMN totp_verified_at DATETIME DEFAULT NULL");
        }
    } catch (PDOException $e) {
    }
}

function ensure_password_resets_table(PDO $pdo): void {
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


function build_totp_uri(string $email, string $secret, string $issuer = 'JOSTUM PG'): string {
    $label = rawurlencode($issuer . ':' . $email);
    $issuerEnc = rawurlencode($issuer);
    return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuerEnc}";
}


function build_qr_url(string $uri): string {
    return 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . urlencode($uri);
}

function build_qr_data_uri(string $uri, int $size = 220): string {
    if (!class_exists('QRCode')) {
        return '';
    }

function build_qr_png(string $uri): string {
    if (!class_exists('QRCode')) {
        return '';
    }

    try {
        return QRCode::png($uri, 8, 2, 'M');
    } catch (Throwable $e) {
        return '';
    }
}

    try {
        $png = QRCode::png($uri, 8, 2, 'M');
    } catch (Throwable $e) {
        return '';
    }
    return 'data:image/png;base64,' . base64_encode($png);
}


function generate_base32_secret(int $length = 20): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[random_int(0, 31)];
    }
    return $secret;
}

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
if ($action === 'delete') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    if ($userId === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user id.']);
        exit;
    }
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'send_reset') {
    $qrPath = '';
    $qrCid = '';
    ensure_totp_columns($pdo);
    ensure_password_resets_table($pdo);
    $userId = (int) ($_POST['user_id'] ?? 0);
    if ($userId === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user id.']);
        exit;
    }

    $userStmt = $pdo->prepare("SELECT email, full_name, totp_secret, totp_enabled FROM users WHERE user_id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }


    $totpSecret = $user['totp_secret'] ?? '';
    $totpEnabled = (int) ($user['totp_enabled'] ?? 0);
    if ($totpSecret === '') {
        $totpSecret = generate_base32_secret();
        $updateSecret = $pdo->prepare("UPDATE users SET totp_secret = ?, totp_enabled = 0 WHERE user_id = ?");
        $updateSecret->execute([$totpSecret, $userId]);
        $totpEnabled = 0;
    }

    $token = bin2hex(random_bytes(16));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE user_id = ?");
    $update->execute([$token, $expires, $userId]);

    $tokenHash = hash('sha256', $token);
    $resetStmt = $pdo->prepare("INSERT INTO password_resets (user_id, email, token_hash, expires_at) VALUES (?, ?, ?, ?)");
    $resetStmt->execute([$userId, $user['email'], $tokenHash, $expires]);

    $settings = $pdo->query("SELECT * FROM system_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $baseUrl = getenv('APP_BASE_URL')
        ?: ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '13.60.198.99'));
    $resetLink = rtrim($baseUrl, '/') . '/ADMIN/reset-password.php?token=' . urlencode($token);

    $subject = 'JOSTUM PG School - Password Reset';
    $body = "Hello " . ($user['full_name'] ?: $user['email']) . ",<br><br>";
    $body .= "Use the link below to reset your password:<br><a href=\"{$resetLink}\">{$resetLink}</a><br><br>";
    if ($totpEnabled === 0 && $totpSecret !== '') {
        $body .= "<strong>Authenticator setup key:</strong><br><strong>{$totpSecret}</strong><br><br>";
        $qrUri = build_totp_uri($user['email'], $totpSecret, 'JOSTUM PG');
        $qrPath = '';
        if (class_exists('QRCode')) {
            try {
                $dir = sys_get_temp_dir();
                if ($dir !== '' && is_dir($dir)) {
                    $filename = 'auth_qr_' . bin2hex(random_bytes(6)) . '.png';
                    $qrPath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
                    QRCode::pngToFile($qrUri, $qrPath, 8, 2, 'M');
                }
            } catch (Throwable $e) {
                $qrPath = '';
            }
        }
        $qrCid = 'qr_' . bin2hex(random_bytes(6));
        $body .= "<strong>Authenticator QR Code:</strong><br>";
        if ($qrPath !== '') {
            $body .= "<img src=\"cid:{$qrCid}\" alt=\"Authenticator QR\" style=\"width:200px;height:200px;border:1px solid #e2e8f0;border-radius:8px;display:block;margin-top:8px;\"><br>";
        } else {
            $body .= "(QR image unavailable. Use the setup key above.)<br><br>";
        }
    } else {
        $body .= "Authenticator is already enabled on your account.<br><br>";
    }
    $body .= "This link expires in 24 hours.<br><br>Regards,<br>JOSTUM PG School";

    $mailResult = sendMail($settings, $user['email'], $user['full_name'], $subject, $body, $resetLink, $qrPath, $qrCid);

    if ($qrPath !== '' && file_exists($qrPath)) {
    @unlink($qrPath);
}

if (!$mailResult['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Reset link created, but email failed to send.',
            'mail_warning' => $mailResult['message']
        ]);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Reset link sent successfully.']);
if ($qrPath !== '' && file_exists($qrPath)) {
    @unlink($qrPath);
}
    exit;
}

if (in_array($action, ['lock', 'unlock', 'suspend', 'activate'], true)) {
    $userId = (int) ($_POST['user_id'] ?? 0);
    if ($userId == 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user id.']);
        exit;
    }

    $statusMap = [
        'lock' => 'Locked',
        'unlock' => 'Active',
        'activate' => 'Active',
        'suspend' => 'Suspended',
    ];
    $newStatus = $statusMap[$action] ?? 'Active';
    $stmt = $pdo->prepare("UPDATE users SET account_status = ? WHERE user_id = ?");
    $stmt->execute([$newStatus, $userId]);

    echo json_encode(['success' => true, 'message' => "User status updated to {$newStatus}."]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
}

function sendMail(?array $settings, string $to, string $toName, string $subject, string $body, string $resetLink = "", string $qrPath = '', string $qrCid = ''): array {
    $institution = $settings['institution_name'] ?? 'JOSTUM PG School';
    $content = $body . "<br><br><strong>Institution:</strong> {$institution}";

    return portal_send_mail(
        $to,
        $toName,
        $subject,
        $content,
        '',
        [
            'preheader' => 'A notification from the JOSTUM PG School portal.',
            'cta_label' => $resetLink ? 'Reset Password' : '',
            'cta_url' => $resetLink ? $resetLink : '',
            'embed_files' => $qrPath !== '' ? [['cid' => $qrCid, 'path' => $qrPath, 'name' => 'auth-qr.png', 'mime' => 'image/png']] : [],
            'attachments' => $qrPath !== '' ? [['path' => $qrPath, 'name' => 'auth-qr.png']] : [],
        ]
    );
}
