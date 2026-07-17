<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../app/bootstrap.php';

if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    unset($_SESSION['pending_admin_login']);
    $show_otp = false;
}

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    $role = normalize_role($_SESSION['role'] ?? '');
    $dashboard = dashboard_for_role($role);
    if ($dashboard !== 'ADMIN/login.php' && $dashboard !== '/ADMIN/login.php' && $dashboard !== '') {
        session_write_close();
        admin_redirect_by_role($role);
    }
}

$error = '';
$otp_error = '';
$show_otp = false;

function admin_redirect_by_role(string $role): void {
    redirect_to(dashboard_for_role($role));
}


function get_user_column_flags(PDO $pdo): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $columns = ['role', 'role_id', 'password_hash', 'totp_secret', 'totp_enabled'];
    $flags = array_fill_keys($columns, false);
    foreach ($columns as $col) {
        try {
            $pdo->query("SELECT `{$col}` FROM users LIMIT 0");
            $flags[$col] = true;
        } catch (Throwable $e) {
            $flags[$col] = false;
        }
    }
    $cache = $flags;
    return $flags;
}

function ensure_totp_columns(PDO $pdo): void {
    try {
        $hasSecret = false;
        try {
            $pdo->query("SELECT totp_secret FROM users LIMIT 0");
            $hasSecret = true;
        } catch (Throwable $e) {}
        if (!$hasSecret) {
            $pdo->exec("ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) NULL");
        }

        $hasEnabled = false;
        try {
            $pdo->query("SELECT totp_enabled FROM users LIMIT 0");
            $hasEnabled = true;
        } catch (Throwable $e) {}
        if (!$hasEnabled) {
            $pdo->exec("ALTER TABLE users ADD COLUMN totp_enabled TINYINT(1) DEFAULT 0");
        }

        $hasVerified = false;
        try {
            $pdo->query("SELECT totp_verified_at FROM users LIMIT 0");
            $hasVerified = true;
        } catch (Throwable $e) {}
        if (!$hasVerified) {
            $pdo->exec("ALTER TABLE users ADD COLUMN totp_verified_at DATETIME DEFAULT NULL");
        }
    } catch (Throwable $e) {
        // Ignore if schema updates are not permitted.
    }
}

function base32_decode_custom(string $input): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $input = strtoupper(preg_replace('/[^A-Z2-7]/', '', $input));
    $bits = '';
    foreach (str_split($input) as $char) {
        $val = strpos($alphabet, $char);
        if ($val === false) {
            continue;
        }
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }
    $output = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $output .= chr(bindec($byte));
        }
    }
    return $output;
}

function generate_base32_secret(int $length = 20): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[random_int(0, 31)];
    }
    return $secret;
}

function generate_totp(string $secret, ?int $timeSlice = null, int $digits = 6): string {
    $timeSlice = $timeSlice ?? (int) floor(time() / 30);
    $secretKey = base32_decode_custom($secret);
    $counter = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $counter, $secretKey, true);
    $offset = ord($hash[19]) & 0x0F;
    $value = (
        ((ord($hash[$offset]) & 0x7f) << 24) |
        ((ord($hash[$offset + 1]) & 0xff) << 16) |
        ((ord($hash[$offset + 2]) & 0xff) << 8) |
        (ord($hash[$offset + 3]) & 0xff)
    );
    $mod = 10 ** $digits;
    return str_pad((string) ($value % $mod), $digits, '0', STR_PAD_LEFT);
}

function verify_totp(string $secret, string $code, int $window = 1): bool {
    $code = preg_replace('/\D/', '', $code ?? '');
    if ($code === '' || strlen($code) < 6) {
        return false;
    }
    $timeSlice = (int) floor(time() / 30);
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(generate_totp($secret, $timeSlice + $i), $code)) {
            return true;
        }
    }
    return false;
}


function user_login_query(PDO $pdo, string $email): ?array {
    $flags = get_user_column_flags($pdo);
    $passwordColumn = $flags['password_hash'] ? 'password_hash' : 'password';
    $totpSelect = $flags['totp_secret'] ? ', u.totp_secret, u.totp_enabled' : '';

    if ($flags['role_id']) {
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.{$passwordColumn} AS password_hash, r.role_key AS role, u.full_name, u.account_status{$totpSelect}
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.role_id
            WHERE u.email = :email
            LIMIT 1
        ");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if ($flags['role']) {
        $stmt = $pdo->prepare("SELECT user_id, {$passwordColumn} AS password_hash, role, full_name, account_status{$totpSelect} FROM users WHERE email = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'check_otp') {
        header('Content-Type: application/json');
        $otp = trim($_POST['otp'] ?? '');
        if (!isset($_SESSION['pending_admin_login'])) {
            echo json_encode(['ok' => false, 'message' => 'TOTP session missing.']);
            exit;
        }
        $pending = $_SESSION['pending_admin_login'];
        if (empty($pending['totp_secret'])) {
            echo json_encode(['ok' => false, 'message' => 'Authenticator not setup.']);
            exit;
        }
        echo json_encode(['ok' => verify_totp($pending['totp_secret'], $otp, 2)]);
        exit;
    }

    if ($action === 'verify_otp') {
        $otp = trim($_POST['otp'] ?? '');
        if (!isset($_SESSION['pending_admin_login'])) {
            $otp_error = 'Authenticator session expired. Please login again.';
        } else {
            $pending = $_SESSION['pending_admin_login'];
            if (empty($pending['totp_secret']) || !verify_totp($pending['totp_secret'], $otp, 2)) {
                $otp_error = 'Invalid authentication code. Please try again.';
            } else {
                try {
                    ensure_totp_columns($pdo);
                    $stmt = $pdo->prepare("UPDATE users SET totp_enabled = 1, totp_verified_at = NOW() WHERE user_id = ?");
                    $stmt->execute([(int) $pending['user_id']]);
                } catch (PDOException $e) {
                }

                $_SESSION['user_id'] = $pending['user_id'];
                $_SESSION['role'] = $pending['role'];
                $_SESSION['last_activity'] = time();

                unset($_SESSION['pending_admin_login']);

                session_write_close();
                admin_redirect_by_role($_SESSION['role']);
            }
        }

        $show_otp = true;
    } else {
        if (isset($_SESSION['pending_admin_login'])) {
            unset($_SESSION['pending_admin_login']);
        }
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Email and password are required.';
        } else {
            try {
                $user = user_login_query($pdo, $email);

                $roleKeys = [];
                try {
                    $roleKeys = $pdo->query("SELECT role_key FROM roles WHERE role_key != 'STUDENT'")->fetchAll(PDO::FETCH_COLUMN);
                } catch (Throwable $e) {}

                if (empty($roleKeys)) {
                    $roleKeys = [
                        'SUPER_ADMIN', 'ICT_ADMIN', 'PORTAL_ADMIN', 'REGISTRY', 'ADMISSIONS_OFFICER', 
                        'BURSARY', 'PG_SCHOOL_OFFICER', 'FACULTY_OFFICER', 'DEPARTMENT_ADMIN', 'HOD', 
                        'SUPERVISOR', 'REVIEWER', 'ADMIN', 'ICT_SUPPORT', 'STUDENT_MANAGER', 
                        'ACADEMIC_MANAGER', 'SUPERVISOR_MANAGER', 'ICT_STAFF'
                    ];
                }

                $loginRole = normalize_role($user['role'] ?? '');
                if ($user && password_verify($password, $user['password_hash']) && in_array($loginRole, array_map('normalize_role', $roleKeys), true)) {
                    // Check if account status is Active
                    $accStatus = $user['account_status'] ?? 'Active';
                    if (strtolower($accStatus) !== 'active') {
                        $error = "This account is currently {$accStatus}. Please contact the Super Admin.";
                    } else {
                        ensure_totp_columns($pdo);

                        $totpSecret = $user['totp_secret'] ?? '';
                        $totpEnabled = (int) ($user['totp_enabled'] ?? 0);

                        if ($totpSecret === '') {
                            $totpSecret = generate_base32_secret();
                            try {
                                $stmt = $pdo->prepare("UPDATE users SET totp_secret = ?, totp_enabled = 0 WHERE user_id = ?");
                                $stmt->execute([$totpSecret, (int) $user['user_id']]);
                                $totpEnabled = 0;
                            } catch (PDOException $e) {
                            }
                        }

                        if ($totpEnabled === 0) {
                            // Bypass OTP and log in immediately
                            $_SESSION['user_id'] = (int) $user['user_id'];
                            $_SESSION['role'] = $loginRole;
                            $_SESSION['last_activity'] = time();
                            session_write_close();
                            admin_redirect_by_role($loginRole);
                        } else {
                            $_SESSION['pending_admin_login'] = [
                                'user_id' => (int) $user['user_id'],
                                'role' => $loginRole,
                                'email' => $email,
                                'name' => $user['full_name'] ?? 'Admin User',
                                'totp_secret' => $totpSecret,
                                'totp_enabled' => $totpEnabled
                            ];
                            $show_otp = true;
                        }
                    }
                } else {
                    $error = 'Invalid credentials provided.';
                }
            } catch (PDOException $e) {
                error_log("Login Error: " . $e->getMessage());
                $error = 'An error occurred. Please try again later. If this persists, check users table schema (role/password_hash).';
            }
        }
    }
}

if (!$show_otp && isset($_SESSION['pending_admin_login'])) {
    $show_otp = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/asset/homepage/ipess_logo.png">
    <title>Login - IPESS FUAM Portal</title>
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
        height: 600px;
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
        margin-bottom: 40px;
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

    .form-options {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 20px 0;
    }

    .checkbox-container {
        display: flex;
        align-items: center;
    }

    .checkbox-container input[type="checkbox"] {
        margin-right: 8px;
    }

    .checkbox-container label {
        margin: 0;
        color: #adb5bd;
        font-size: 14px;
    }

    .forgot-password {
        color: #ffc107;
        text-decoration: none;
        font-size: 14px;
    }

    .forgot-password:hover {
        color: #ffca2c;
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

    .footer-text {
        text-align: center;
        color: #adb5bd;
        font-size: 12px;
        margin-top: auto;
        padding-top: 20px;
    }

    .otp-input {
        max-width: 52px;
        height: 58px;
        margin: 0 4px;
        font-size: 20px;
        font-weight: 700;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: #fff;
    }

    .otp-input:focus {
        border-color: #ffc107;
        box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
    }

    .otp-valid {
        border-color: #28a745 !important;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25) !important;
    }

    .otp-invalid {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
    }

    .auth-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
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

    .totp-key-box {
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.25);
        border-radius: 12px;
        padding: 12px 14px;
        margin-bottom: 16px;
    }

    .totp-key {
        font-weight: 700;
        letter-spacing: 2px;
        font-size: 1rem;
        margin-top: 4px;
        word-break: break-all;
    }

    @media (max-width: 768px) {
        .login-card {
            width: 100%;
            height: auto;
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

<!-- Background -->
<div style="
    background: url('../APPLICANT/ADMISSIONS/images/auditorium.jpg') no-repeat center center/cover;
    height: 100vh;
    position: relative;
">

    <!-- Overlay -->
    <div class="background-overlay"></div>

    <!-- Login Card Wrapper -->
    <div class="d-flex justify-content-center align-items-center h-100">
        <div class="login-card">

            <!-- LEFT BRANDING PANEL -->
            <div class="branding-panel">
                <div class="university-name">
                    FEDERAL UNIVERSITY OF AGRICULTURE MAKURDI
                </div>

                <div class="postgraduate-badge">
                    IPESS FUAM
                </div>

                <div class="logo-container">
                    <img src="images/ipess_logo.png" class="university-logo" alt="University Logo">
                </div>

                <div class="established-text">
                    Center of Excellence
                </div>
            </div>

            <!-- RIGHT LOGIN PANEL -->
            <div class="login-panel">

                <div>
                    <?php if ($show_otp): ?>
                        <?php
                        $pending = $_SESSION['pending_admin_login'] ?? null;
                        $totpSetup = $pending ? empty($pending['totp_enabled']) : false;
                        $totpSecret = $pending['totp_secret'] ?? '';
                        $totpEmail = $pending['email'] ?? '';
                        $qrCodeUrl = '';
                        if ($totpSetup && $totpSecret && $totpEmail) {
                            $company = 'IPESS JOSTUM';
                            $otpauthUrl = 'otpauth://totp/' . rawurlencode($company . ':' . $totpEmail) . '?secret=' . rawurlencode($totpSecret) . '&issuer=' . rawurlencode($company);
                            $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($otpauthUrl);
                        }
                        ?>
                        
                        <div class="auth-header">
                            <a href="login.php?reset=1" class="back-to-login">&larr; Back to login</a>
                        </div>
                        <h1 class="welcome-heading">Authenticator Code</h1>
                        <p class="welcome-subtitle">Enter the 6-digit code from Google Authenticator.</p>

                        <?php if (!empty($otp_error)): ?>
                            <div class="alert alert-danger"><?php echo $otp_error; ?></div>
                        <?php endif; ?>

                        <?php if ($totpSetup && $totpSecret): ?>
                            <div class="alert alert-warning py-2 mb-3" style="font-size: 0.85rem;">
                                <strong>First-time setup:</strong> Scan the QR code below with Google Authenticator, or enter the manual setup key.
                            </div>
                            <?php if ($qrCodeUrl): ?>
                                <div class="text-center mb-3">
                                    <div style="background: white; padding: 12px; border-radius: 8px; display: inline-block; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                                        <img src="<?php echo htmlspecialchars($qrCodeUrl); ?>" alt="Scan QR Code" style="width: 160px; height: 160px; display: block;">
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="totp-key-box">
                                <div class="text-muted" style="font-size: 0.9rem;">Authenticator setup key</div>
                                <div class="totp-key"><?php echo htmlspecialchars($totpSecret); ?></div>
                            </div>
                        <?php endif; ?>


                        <form method="post" id="otpForm">
                            <input type="hidden" name="action" value="verify_otp">
                            <input type="hidden" name="otp" id="otpValue">
                            <div class="d-flex justify-content-between mb-4">
                                <?php for ($i = 0; $i < 6; $i++): ?>
                                    <input type="text" maxlength="1" class="form-control text-center otp-input" inputmode="numeric" pattern="[0-9]*" required>
                                <?php endfor; ?>
                            </div>

                            <button type="submit" class="btn-login">Verify Code</button>
                        </form>
                    <?php else: ?>

                        <h1 class="welcome-heading">Welcome Back</h1>
                        <p class="welcome-subtitle">Access your Portal.</p>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="post">
                            <input type="hidden" name="action" value="login">

                            <div class="form-group">
                                <input
                                    type="email"
                                    class="form-control"
                                    name="email"
                                    placeholder="Email Address"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <div class="password-container">
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="password"
                                        name="password"
                                        placeholder="Secure Password"
                                        required
                                    >
                                    <button type="button" class="password-toggle" id="togglePassword">
                                        <i class="bi bi-eye" id="eyeIcon"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-options">
                                <div class="checkbox-container">
                                    <input type="checkbox" id="remember" name="remember">
                                    <label for="remember">Remember me</label>
                                </div>

                                <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
                            </div>

                            <button type="submit" class="btn-login">
                                Sign In to Portal
                            </button>

                        </form>
                    <?php endif; ?>
                </div>

                <div class="footer-text">
                    © 2026 IPESS FUAM
                </div>

            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    if (togglePassword && passwordInput && eyeIcon) {
        togglePassword.addEventListener('click', function () {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
            eyeIcon.className = type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
        });
    }

    const otpInputs = document.querySelectorAll('.otp-input');
    const otpValue = document.getElementById('otpValue');
    const otpForm = document.getElementById('otpForm');

    if (otpInputs.length && otpValue && otpForm) {
        let otpCheckTimer = null;

        otpInputs.forEach((input, index) => {
            input.addEventListener('input', () => {
                input.value = input.value.replace(/[^0-9]/g, '');
                if (input.value && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
                otpValue.value = Array.from(otpInputs).map(el => el.value).join('');
                otpInputs.forEach(el => el.classList.remove('otp-valid', 'otp-invalid'));

                const otpString = otpValue.value;
                if (otpString.length === 6) {
                    if (otpCheckTimer) {
                        clearTimeout(otpCheckTimer);
                    }
                    otpCheckTimer = setTimeout(() => {
                        const formData = new FormData();
                        formData.append('action', 'check_otp');
                        formData.append('otp', otpString);
                        fetch('login.php', { method: 'POST', body: formData })
                            .then(res => res.json())
                            .then(data => {
                                if (data.ok) {
                                    otpInputs.forEach(el => el.classList.add('otp-valid'));
                                } else {
                                    otpInputs.forEach(el => el.classList.add('otp-invalid'));
                                }
                            })
                            .catch(() => {});
                    }, 250);
                }
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !input.value && index > 0) {
                    otpInputs[index - 1].focus();
                }
            });
        });

        otpForm.addEventListener('submit', (e) => {
            otpValue.value = Array.from(otpInputs).map(el => el.value).join('');
        });
    }
</script>



</body>

</html>
