<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $identifier = trim($_POST['identifier'] ?? '');
    if (login_limited($identifier)) {
        flash('error', 'Too many failed login attempts. Please wait 15 minutes before trying again.');
        redirect('login.php');
    }
    $stmt = db()->prepare('SELECT u.* FROM users u LEFT JOIN applicants a ON a.user_id = u.id WHERE (u.email = ? OR u.username = ? OR a.jamb_reg_no = ?) AND u.is_active = 1 LIMIT 1');
    $stmt->execute([$identifier, $identifier, normalize_jamb($identifier)]);
    $user = $stmt->fetch();
    if ($user && password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])) {
        record_login_attempt($identifier, true);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['last_seen_at'] = time();
        db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
        audit_log('logged in', 'user', (int) $user['id']);
        redirect($user['role'] === 'applicant' ? 'applicant/dashboard.php' : 'admin/dashboard.php');
    }
    record_login_attempt($identifier, false);
    flash('error', 'Invalid login credentials.');
}

render_header('Login');
?>
<section class="auth-shell gateway-shell">
    <div class="container">
        <div class="split-page">
            <div class="split-info">
                <img src="<?= e(url('images/new_jostum_logo.png')) ?>" alt="JOSTUM logo">
                <p class="eyebrow">Secure Access</p>
                <h1>Continue your JOSTUM screening</h1>
                <p>Applicants can log in with their JAMB registration number or email address. </p>
                <div class="info-list">
                    <span>Protected applicant dashboard</span>
                    <span>Payment and submission tracking</span>
                    <span>Session timeout and login protection</span>
                </div>
            </div>
            <div class="auth-card">
                <div class="auth-brand">
                    <img src="<?= e(url('images/new_jostum_logo.png')) ?>" alt="JOSTUM logo">
                    <div>
                        <p class="eyebrow mb-1">Joseph Sarwuan Tarka University</p>
                        <h1>Portal Login</h1>
                    </div>
                </div>
                <p class="text-muted">Enter your login details to access the portal.</p>
                <form method="post" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label class="form-label">JAMB Registration Number</label>
                        <input name="identifier" class="form-control form-control-lg" required autocomplete="username" placeholder="JAMB Registration number">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control form-control-lg" required autocomplete="current-password" placeholder="Enter your password">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-portal-green btn-lg w-100">Login</button>
                    </div>
                </form>
                <a class="d-inline-block mt-3" href="<?= e(url('forgot-password.php')) ?>">Forgot password?</a>
                <!-- <p class="small text-muted mt-3 mb-0">Default staff password after fresh install: ChangeMe@123. Change it before production use.</p> -->
            </div>
        </div>
    </div>
</section>
<?php render_footer(); ?>
