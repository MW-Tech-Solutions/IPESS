<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/_admin.php';

$user = require_admin(['ict_admin', 'super_admin']);
$keys = ['payment_provider', 'paystack_public_key', 'paystack_secret_key', 'remita_merchant_id', 'remita_service_type_id', 'remita_api_key', 'support_email', 'support_phone', 'manual_review_enabled', 'allow_form_without_payment', 'allow_change_of_course', 'allow_edit_after_submission', 'upload_max_mb', 'allowed_file_types', 'profile_requires_payment', 'maintenance_mode', 'session_timeout_minutes', 'jamb_weight', 'olevel_weight'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($keys as $key) {
        save_setting($key, trim($_POST[$key] ?? ''));
    }
    audit_log('updated portal settings', 'settings');
    flash('success', 'Settings saved.');
}

render_header('Portal Settings');
?>
<?php render_workspace_start('admin', $user, 'Settings'); ?>
        <div class="portal-card">
            <h1>Payment & Portal Settings</h1>
            <form method="post" class="row g-3">
                <?= csrf_field() ?>
                <div class="col-md-4">
                    <label class="form-label">Payment Provider</label>
                    <select name="payment_provider" class="form-select">
                        <option value="paystack" <?= setting('payment_provider') === 'paystack' ? 'selected' : '' ?>>Paystack</option>
                        <option value="remita" <?= setting('payment_provider') === 'remita' ? 'selected' : '' ?>>Remita</option>
                    </select>
                </div>
                <?php foreach (array_slice($keys, 1) as $key): ?>
                    <div class="col-md-6">
                        <label class="form-label"><?= e(ucwords(str_replace('_', ' ', $key))) ?></label>
                        <input name="<?= e($key) ?>" class="form-control" value="<?= e(setting($key, '')) ?>">
                    </div>
                <?php endforeach; ?>
                <div class="col-12"><button class="btn btn-portal-green btn-lg">Save Settings</button></div>
            </form>
        </div>
<?php render_workspace_end(); ?>
<?php render_footer(); ?>
