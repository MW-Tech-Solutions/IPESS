<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SUPER_ADMIN') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'System Settings';
$pageSubtitle = 'Institution profile, access controls, and operational defaults.';

require_once 'includes/db.php';

$defaultSettings = [
    'institution_name' => 'JOSTUM PG School',
    'support_email' => 'admin@jostum.edu.ng',
    'phone' => '+234 123 456 7890',
    'website_url' => 'https://www.jostum.edu.ng',
    'address' => 'Makurdi, Benue State, Nigeria',
    'password_min_length' => 8,
    'lockout_attempts' => 5,
    'session_timeout' => 60,
    'two_factor_policy' => 'REQUIRED',
    'audit_level' => 'STANDARD',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_encryption' => 'TLS',
    'system_email' => 'no-reply@jostum.edu.ng',
    'reply_to_email' => 'support@jostum.edu.ng',
    'student_verification_active' => 1
];

$settings = $defaultSettings;
$saved = false;

if ($pdo) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = [
            'institution_name' => trim($_POST['institution_name'] ?? $defaultSettings['institution_name']),
            'support_email' => trim($_POST['support_email'] ?? $defaultSettings['support_email']),
            'phone' => trim($_POST['phone'] ?? $defaultSettings['phone']),
            'website_url' => trim($_POST['website_url'] ?? $defaultSettings['website_url']),
            'address' => trim($_POST['address'] ?? $defaultSettings['address']),
            'password_min_length' => (int) ($_POST['password_min_length'] ?? $defaultSettings['password_min_length']),
            'lockout_attempts' => (int) ($_POST['lockout_attempts'] ?? $defaultSettings['lockout_attempts']),
            'session_timeout' => (int) ($_POST['session_timeout'] ?? $defaultSettings['session_timeout']),
            'two_factor_policy' => $_POST['two_factor_policy'] ?? $defaultSettings['two_factor_policy'],
            'audit_level' => $_POST['audit_level'] ?? $defaultSettings['audit_level'],
            'smtp_host' => trim($_POST['smtp_host'] ?? $defaultSettings['smtp_host']),
            'smtp_port' => (int) ($_POST['smtp_port'] ?? $defaultSettings['smtp_port']),
            'smtp_encryption' => $_POST['smtp_encryption'] ?? $defaultSettings['smtp_encryption'],
            'system_email' => trim($_POST['system_email'] ?? $defaultSettings['system_email']),
            'reply_to_email' => trim($_POST['reply_to_email'] ?? $defaultSettings['reply_to_email']),
            'student_verification_active' => isset($_POST['student_verification_active']) ? 1 : 0
        ];

        $existingId = $pdo->query("SELECT settings_id FROM system_settings LIMIT 1")->fetchColumn();
        if ($existingId) {
            $updateSql = "
                UPDATE system_settings
                SET institution_name = ?, support_email = ?, phone = ?, website_url = ?, address = ?,
                    password_min_length = ?, lockout_attempts = ?, session_timeout = ?, two_factor_policy = ?,
                    audit_level = ?, smtp_host = ?, smtp_port = ?, smtp_encryption = ?, system_email = ?, reply_to_email = ?,
                    student_verification_active = ?
                WHERE settings_id = ?
            ";
            $stmt = $pdo->prepare($updateSql);
            $stmt->execute([
                $payload['institution_name'],
                $payload['support_email'],
                $payload['phone'],
                $payload['website_url'],
                $payload['address'],
                $payload['password_min_length'],
                $payload['lockout_attempts'],
                $payload['session_timeout'],
                $payload['two_factor_policy'],
                $payload['audit_level'],
                $payload['smtp_host'],
                $payload['smtp_port'],
                $payload['smtp_encryption'],
                $payload['system_email'],
                $payload['reply_to_email'],
                $payload['student_verification_active'],
                $existingId
            ]);
        } else {
            $insertSql = "
                INSERT INTO system_settings
                (institution_name, support_email, phone, website_url, address, password_min_length, lockout_attempts,
                 session_timeout, two_factor_policy, audit_level, smtp_host, smtp_port, smtp_encryption, system_email, reply_to_email,
                 student_verification_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            $stmt = $pdo->prepare($insertSql);
            $stmt->execute([
                $payload['institution_name'],
                $payload['support_email'],
                $payload['phone'],
                $payload['website_url'],
                $payload['address'],
                $payload['password_min_length'],
                $payload['lockout_attempts'],
                $payload['session_timeout'],
                $payload['two_factor_policy'],
                $payload['audit_level'],
                $payload['smtp_host'],
                $payload['smtp_port'],
                $payload['smtp_encryption'],
                $payload['system_email'],
                $payload['reply_to_email'],
                $payload['student_verification_active']
            ]);
        }
        $saved = true;
    }

    $settingsRow = $pdo->query("SELECT * FROM system_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($settingsRow) {
        $settings = array_merge($settings, $settingsRow);
    }
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>System Settings</h1>
        <p class="panel-muted">Configure institution identity, security, and notification defaults.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" type="reset" form="settingsForm">Reset</button>
        <button class="btn btn-primary" type="submit" form="settingsForm">Save Changes</button>
    </div>
</section>

<?php if ($saved): ?>
    <div class="alert alert-success">Settings updated successfully.</div>
<?php endif; ?>

<form id="settingsForm" method="POST">
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3 class="panel-title">Institution Identity</h3>
                <div class="panel-muted">Update contact points and public-facing metadata.</div>
            </div>
        </div>
        <div class="panel-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Institution Name</label>
                    <input type="text" class="form-control" name="institution_name" value="<?php echo htmlspecialchars($settings['institution_name']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Support Email</label>
                    <input type="email" class="form-control" name="support_email" value="<?php echo htmlspecialchars($settings['support_email']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone Number</label>
                    <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($settings['phone']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Website URL</label>
                    <input type="url" class="form-control" name="website_url" value="<?php echo htmlspecialchars($settings['website_url']); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Campus Address</label>
                    <textarea class="form-control" rows="3" name="address"><?php echo htmlspecialchars($settings['address']); ?></textarea>
                </div>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3 class="panel-title">Access & Security</h3>
                <div class="panel-muted">Set lockout thresholds and authentication safeguards.</div>
            </div>
        </div>
        <div class="panel-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Minimum Password Length</label>
                    <input type="number" class="form-control" name="password_min_length" value="<?php echo (int) $settings['password_min_length']; ?>" min="6" max="32">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Lockout Attempts</label>
                    <input type="number" class="form-control" name="lockout_attempts" value="<?php echo (int) $settings['lockout_attempts']; ?>" min="3" max="10">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Session Timeout (mins)</label>
                    <input type="number" class="form-control" name="session_timeout" value="<?php echo (int) $settings['session_timeout']; ?>" min="15" max="240">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Two-Factor Policy</label>
                    <select class="form-select" name="two_factor_policy">
                        <?php foreach (['REQUIRED' => 'Required for administrators', 'OPTIONAL' => 'Optional', 'DISABLED' => 'Disabled'] as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo ($settings['two_factor_policy'] === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Audit Logging Level</label>
                    <select class="form-select" name="audit_level">
                        <?php foreach (['STANDARD' => 'Standard', 'VERBOSE' => 'Verbose', 'CRITICAL' => 'Critical only'] as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo ($settings['audit_level'] === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 mt-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="student_verification_active" name="student_verification_active" value="1" <?php echo (!empty($settings['student_verification_active'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold text-danger" for="student_verification_active">Mandatory Applicant Account Verification</label>
                        <div class="form-text">If enabled, students must verify their email/phone via OTP after registration before they can log in and apply. If disabled, they can log in and apply immediately.</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3 class="panel-title">Communication Channels</h3>
                <div class="panel-muted">SMTP and notification defaults for applicants and admins.</div>
            </div>
        </div>
        <div class="panel-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">SMTP Host</label>
                    <input type="text" class="form-control" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host']); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">SMTP Port</label>
                    <input type="number" class="form-control" name="smtp_port" value="<?php echo (int) $settings['smtp_port']; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Encryption</label>
                    <select class="form-select" name="smtp_encryption">
                        <?php foreach (['TLS', 'SSL', 'NONE'] as $enc): ?>
                            <option value="<?php echo $enc; ?>" <?php echo ($settings['smtp_encryption'] === $enc) ? 'selected' : ''; ?>><?php echo $enc; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">System Email</label>
                    <input type="email" class="form-control" name="system_email" value="<?php echo htmlspecialchars($settings['system_email']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Reply-To Email</label>
                    <input type="email" class="form-control" name="reply_to_email" value="<?php echo htmlspecialchars($settings['reply_to_email']); ?>">
                </div>
            </div>
        </div>
    </section>
</form>

<?php require_once 'includes/footer.php'; ?>
