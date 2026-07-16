<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');

if (!has_permission('manage_users')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied: Requires manage_users permission.']);
    exit;
}

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


function normalize_base32_secret(string $secret): string {
    $secret = strtoupper($secret);
    return preg_replace('/[^A-Z2-7]/', '', $secret);
}

function build_totp_uri(string $account, string $secret, string $issuer = 'JOSTUM PG'): string {
    $account = trim($account);
    $issuer = trim($issuer) !== '' ? trim($issuer) : 'JOSTUM PG';
    $secret = normalize_base32_secret($secret);

    $label = rawurlencode($issuer . ':' . $account);
    $issuerEnc = rawurlencode($issuer);

    return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuerEnc}&algorithm=SHA1&digits=6&period=30";
}

function generate_qr_png_data(string $uri, int $size = 320, string $ecc = 'M'): string {
    $size = max(200, min(480, $size));
    $ecc = strtoupper(trim($ecc));
    if (!in_array($ecc, ['L', 'M', 'Q', 'H'], true)) {
        $ecc = 'M';
    }

    $autoload = __DIR__ . '/../../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    if (class_exists('QRCode')) {
        try {
            return QRCode::png($uri, 10, 2, $ecc);
        } catch (Throwable $e) {
            error_log('QR generation failed (QRCode): ' . $e->getMessage());
        }
    }

    if (class_exists(\BaconQrCode\Writer::class)) {
        try {
            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle($size, 2),
                new \BaconQrCode\Renderer\GDLibRenderer()
            );
            $writer = new \BaconQrCode\Writer($renderer);
            $ecLevel = match ($ecc) {
                'H' => \BaconQrCode\Common\ErrorCorrectionLevel::H(),
                'Q' => \BaconQrCode\Common\ErrorCorrectionLevel::Q(),
                'M' => \BaconQrCode\Common\ErrorCorrectionLevel::M(),
                default => \BaconQrCode\Common\ErrorCorrectionLevel::L(),
            };
            return $writer->writeString($uri, 'UTF-8', $ecLevel);
        } catch (Throwable $e) {
            error_log('QR generation failed: ' . $e->getMessage());
            return '';
        }
    }

    error_log('QR generation failed: BaconQrCode not available.');
    return '';
}


function generate_base32_secret(int $length = 20): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[random_int(0, 31)];
    }
    return $secret;
}

function sync_user_duties(PDO $pdo, int $userId, int $dutyViewRecords, int $dutyApproveApps, int $dutyVerifyDocs): void {
    $perms = [];
    if ($dutyViewRecords) {
        $perms['view_applications'] = 1;
        $perms['view_applicants'] = 1;
        $perms['view_dashboard'] = 1;
    }
    if ($dutyApproveApps) {
        $perms['department_review'] = 1;
        $perms['view_dashboard'] = 1;
    }
    if ($dutyVerifyDocs) {
        $perms['verify_applicants'] = 1;
        $perms['view_dashboard'] = 1;
    }

    $keysToDelete = ['view_applications', 'view_applicants', 'view_dashboard', 'department_review', 'verify_applicants'];
    $inClause = implode(',', array_fill(0, count($keysToDelete), '?'));
    $delStmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ? AND permission_key IN ($inClause)");
    $delStmt->execute(array_merge([$userId], $keysToDelete));

    if (!empty($perms)) {
        $insStmt = $pdo->prepare("INSERT INTO user_permissions (user_id, permission_key, granted) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE granted = 1");
        foreach ($perms as $key => $granted) {
            $insStmt->execute([$userId, $key]);
        }
    }
}

try {
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$action = strtolower(trim($_POST['action'] ?? 'create'));
$accountStatus = $_POST['account_status'] ?? 'Active';

$validStatuses = ['Active', 'Suspended', 'Locked'];
if (!in_array($accountStatus, $validStatuses, true)) {
    $accountStatus = 'Active';
}

if ($action === 'update') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $roleId = !empty($_POST['role_id']) ? (int) $_POST['role_id'] : null;
    $departmentId = !empty($_POST['department_id']) ? (int) $_POST['department_id'] : null;

    if ($userId === 0 || $fullName === '' || $email === '') {
        echo json_encode(['success' => false, 'message' => 'User, name, and email are required.']);
        exit;
    }

    $roleKey = null;
    if ($roleId !== null && $roleId > 0) {
        $roleStmt = $pdo->prepare("SELECT role_key FROM roles WHERE role_id = ?");
        $roleStmt->execute([$roleId]);
        $roleKey = $roleStmt->fetchColumn();
        if (!$roleKey) {
            echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
            exit;
        }
    }

    if ($roleKey === 'DEPARTMENT_ADMIN' && empty($departmentId)) {
        echo json_encode(['success' => false, 'message' => 'Department is required for Department Admin.']);
        exit;
    }

    $existing = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1");
    $existing->execute([$email, $userId]);
    if ($existing->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Email is already in use.']);
        exit;
    }

    $updateSql = "UPDATE users SET full_name = ?, email = ?, role_id = ?, department_id = ?, account_status = ? WHERE user_id = ?";
    $stmt = $pdo->prepare($updateSql);
    $stmt->execute([$fullName, $email, $roleId, $departmentId, $accountStatus, $userId]);

    $dutyViewRecords = !empty($_POST['duty_view_records']) ? 1 : 0;
    $dutyApproveApps = !empty($_POST['duty_approve_apps']) ? 1 : 0;
    $dutyVerifyDocs = !empty($_POST['duty_verify_docs']) ? 1 : 0;
    sync_user_duties($pdo, $userId, $dutyViewRecords, $dutyApproveApps, $dutyVerifyDocs);

    echo json_encode(['success' => true, 'message' => 'User updated successfully.']);
    exit;
}

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$fullName = trim($firstName . ' ' . $lastName);
$email = trim($_POST['email'] ?? '');
$roleId = !empty($_POST['role_id']) ? (int) $_POST['role_id'] : null;
$departmentId = !empty($_POST['department_id']) ? (int) $_POST['department_id'] : null;

if ($fullName === '' || $email === '') {
    echo json_encode(['success' => false, 'message' => 'Name and email are required.']);
    exit;
}

$roleKey = null;
if ($roleId !== null && $roleId > 0) {
    $roleStmt = $pdo->prepare("SELECT role_key FROM roles WHERE role_id = ?");
    $roleStmt->execute([$roleId]);
    $roleKey = $roleStmt->fetchColumn();

    if (!$roleKey) {
        echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
        exit;
    }
}

if ($roleKey === 'DEPARTMENT_ADMIN' && empty($departmentId)) {
    echo json_encode(['success' => false, 'message' => 'Department is required for Department Admin.']);
    exit;
}

$existing = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
$existing->execute([$email]);
if ($existing->fetchColumn()) {
    echo json_encode(['success' => false, 'message' => 'Email is already in use.']);
    exit;
}

$tempPassword = bin2hex(random_bytes(4));
$passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);
$token = bin2hex(random_bytes(16));
$expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

ensure_totp_columns($pdo);
ensure_password_resets_table($pdo);
$totpSecret = generate_base32_secret();

$insertSql = "
    INSERT INTO users (email, full_name, role_id, department_id, account_status, password_hash, reset_token, reset_expires, totp_secret, totp_enabled, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
";
$stmt = $pdo->prepare($insertSql);
$stmt->execute([$email, $fullName, $roleId, $departmentId, $accountStatus, $passwordHash, $token, $expires, $totpSecret, 0]);

$insertedUserId = (int) $pdo->lastInsertId();
$tokenHash = hash('sha256', $token);
$resetStmt = $pdo->prepare("INSERT INTO password_resets (user_id, email, token_hash, expires_at) VALUES (?, ?, ?, ?)");
$resetStmt->execute([$insertedUserId, $email, $tokenHash, $expires]);

$dutyViewRecords = !empty($_POST['duty_view_records']) ? 1 : 0;
$dutyApproveApps = !empty($_POST['duty_approve_apps']) ? 1 : 0;
$dutyVerifyDocs = !empty($_POST['duty_verify_docs']) ? 1 : 0;
sync_user_duties($pdo, $insertedUserId, $dutyViewRecords, $dutyApproveApps, $dutyVerifyDocs);

$settings = $pdo->query("SELECT * FROM system_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$issuer = trim($settings['institution_name'] ?? 'JOSTUM PG');
$resetLink = app_absolute_url('ADMIN/reset-password.php?token=' . urlencode($token));
$loginLink = app_absolute_url('ADMIN/login.php');

$subject = 'JOSTUM PG School - Account Created';
$body = "Hello {$fullName},<br><br>";
$body .= "Your admin account has been created with email: <strong>{$email}</strong>.";
$body .= "<br><br>Use the link below to set your password and complete setup:";
$body .= "<br><a href=\"{$resetLink}\">{$resetLink}</a><br><br>";
$body .= "Login page:";
$body .= "<br><a href=\"{$loginLink}\">{$loginLink}</a><br><br>";
$body .= "This link expires in 24 hours.";
$body .= "<br><br><strong>Authenticator setup key:</strong><br>";
$body .= "<strong>{$totpSecret}</strong><br><br>";

$qrUri = build_totp_uri($email, $totpSecret, $issuer);
error_log("TOTP QR URI for {$email}: {$qrUri}");

$qrPng = generate_qr_png_data($qrUri, 340, 'M');
$qrCid = 'qr_' . bin2hex(random_bytes(6));
$body .= "<strong>Authenticator QR Code:</strong><br>";
if ($qrPng !== '') {
    $body .= "<img src=\"cid:{$qrCid}\" alt=\"Authenticator QR\" style=\"width:220px;height:220px;border:1px solid #e2e8f0;border-radius:8px;display:block;margin-top:8px;\"><br>";
} else {
    $body .= "(QR image unavailable. Use the setup key above.)<br><br>";
}
$body .= "If you already enabled Authenticator, you can ignore the setup key.";

$mailResult = sendMail(
    $settings,
    $email,
    $fullName,
    $subject,
    $body,
    $resetLink,
    $qrPng !== '' ? [
        ['cid' => $qrCid, 'data' => base64_encode($qrPng), 'name' => 'auth-qr.png', 'mime' => 'image/png']
    ] : []
);

if (!$mailResult['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'User created, but email failed to send.',
        'mail_warning' => $mailResult['message']
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'User created and email sent.'
]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
}

function sendMail(?array $settings, string $to, string $toName, string $subject, string $body, string $resetLink, array $qrEmbeds = []): array {
    $institution = $settings['institution_name'] ?? 'JOSTUM PG School';
    $content = $body . "<br><br><strong>Institution:</strong> {$institution}";

    return portal_send_mail(
        $to,
        $toName,
        $subject,
        $content,
        '',
        [
            'preheader' => 'Account created. Use the reset link and authenticator key.',
            'cta_label' => 'Set Password',
            'cta_url' => $resetLink,
            'embed_images' => $qrEmbeds,
        ]
    );
}
