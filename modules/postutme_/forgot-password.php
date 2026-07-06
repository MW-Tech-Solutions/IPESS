<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $identifier = trim($_POST['identifier'] ?? '');
    $stmt = db()->prepare('SELECT u.* FROM users u LEFT JOIN applicants a ON a.user_id = u.id WHERE u.email = ? OR a.jamb_reg_no = ? LIMIT 1');
    $stmt->execute([$identifier, normalize_jamb($identifier)]);
    $user = $stmt->fetch();
    if ($user) {
        $token = bin2hex(random_bytes(32));
        $reset = db()->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))');
        $reset->execute([$user['id'], password_hash($token, PASSWORD_DEFAULT)]);
        notify_user((int) $user['id'], 'Password reset requested', 'A password reset was requested for your JOSTUM portal account. Contact ICT/admin to complete reset in this local build.');
    }
    flash('success', 'If the account exists, password reset instructions have been queued.');
    redirect('forgot-password.php');
}

render_header('Forgot Password');
?>
<section class="auth-shell gateway-shell">
    <div class="container">
        <div class="split-page">
            <div class="split-info">
                <img src="<?= e(url('images/new_jostum_logo.png')) ?>" alt="JOSTUM logo">
                <p class="eyebrow">Account Recovery</p>
                <h1>Recover access to your portal account</h1>
                <p>Use your JAMB registration number. If an account exists, reset instructions will be queued for the configured support channel.</p>
                <div class="info-list">
                    <span>Works for new applicants and returning applicants</span>
                    <span>Reset requests are logged</span>
                    <span>Use your correct JAMB number</span>
                </div>
            </div>
            <div class="auth-card">
                <div class="auth-brand">
                    <img src="<?= e(url('images/new_jostum_logo.png')) ?>" alt="JOSTUM logo">
                    <div>
                        <p class="eyebrow mb-1">Joseph Sarwuan Tarka University</p>
                        <h1>Forgot Password</h1>
                    </div>
                </div>
                <p class="text-muted">Enter your JAMB registration number</p>
                <form method="post" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label class="form-label">JAMB Registration Number</label>
                        <input name="identifier" class="form-control form-control-lg" required placeholder="JAMB number">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-portal-green btn-lg w-100">Request Reset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<?php render_footer(); ?>
