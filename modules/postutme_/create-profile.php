<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/layout.php';

$candidateId = (int) ($_SESSION['verified_jamb_candidate_id'] ?? 0);
if (!$candidateId) {
    flash('info', 'Please verify your JAMB registration number first.');
    redirect('verify.php');
}
$stmt = db()->prepare('SELECT * FROM jamb_candidates WHERE id = ?');
$stmt->execute([$candidateId]);
$candidate = $stmt->fetch();
if (!$candidate) {
    redirect('verify.php');
}
$existing = db()->prepare('SELECT id FROM applicants WHERE jamb_candidate_id = ? OR jamb_reg_no = ? LIMIT 1');
$existing->execute([$candidate['id'], $candidate['jamb_reg_no']]);
if ($existing->fetch()) {
    flash('info', 'A profile already exists for this JAMB registration number. Please log in to continue.');
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Provide a valid email address.');
    } elseif (!valid_ng_phone($phone)) {
        flash('error', 'Enter a valid Nigerian phone number, for example 08031234567 or +2348031234567.');
    } elseif (strlen($password) < 8 || $password !== $confirmPassword) {
        flash('error', 'Password must be at least 8 characters and match the confirmation password.');
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $name = trim($candidate['surname'] . ' ' . $candidate['first_name'] . ' ' . $candidate['other_names']);
            $userStmt = $pdo->prepare('INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, "applicant")');
            $userStmt->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int) $pdo->lastInsertId();

            $applicationNumber = generate_application_number();
            $appStmt = $pdo->prepare('INSERT INTO applicants (application_number, user_id, jamb_candidate_id, jamb_reg_no, surname, first_name, other_names, email, phone, gender, contact_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $appStmt->execute([$applicationNumber, $userId, $candidate['id'], $candidate['jamb_reg_no'], $candidate['surname'], $candidate['first_name'], $candidate['other_names'], $email, $phone, $candidate['gender'] ?? null, '']);
            $applicantId = (int) $pdo->lastInsertId();
            $profile = $pdo->prepare('INSERT INTO applicant_profiles (applicant_id, state_origin, lga, nationality) VALUES (?, ?, ?, "Nigerian")');
            $profile->execute([$applicantId, $candidate['state_origin'] ?? '', $candidate['lga'] ?? '']);
            $pdo->prepare('UPDATE jamb_candidates SET is_registered = 1 WHERE id = ?')->execute([$candidate['id']]);
            $pdo->commit();
            $_SESSION['user_id'] = $userId;
            notify_user($userId, 'JOSTUM profile created', 'Your POST-UTME screening profile has been created successfully.');
            audit_log('created applicant profile', 'applicant', $applicantId);
            redirect('applicant/dashboard.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'Profile could not be created. The email or JAMB record may already be registered.');
        }
    }
}

render_header('Create Profile');
?>
<section class="section-pad gateway-shell">
    <div class="container">
        <div class="split-page">
            <div class="split-info">
                <img src="<?= e(url('images/new_jostum_logo.png')) ?>" alt="JOSTUM logo">
                <p class="eyebrow">Profile Registration</p>
                <h1>Create your applicant access</h1>
                <p>Your JAMB record has been verified. JAMB-supplied details are locked while you provide contact details and a secure password.</p>
                <div class="verified-summary compact-summary">
                    <strong><?= e($candidate['surname'] . ' ' . $candidate['first_name'] . ' ' . $candidate['other_names']) ?></strong>
                    <span><?= e($candidate['jamb_reg_no']) ?></span>
                    <span><?= e(candidate_course($candidate)) ?></span>
                    <span><?= e($candidate['gender'] ?? '') ?> · <?= e($candidate['state_origin']) ?> · <?= e($candidate['lga']) ?></span>
                </div>
            </div>
            <div class="auth-card">
                <div class="auth-brand">
                    <img src="<?= e(url('images/new_jostum_logo.png')) ?>" alt="JOSTUM logo">
                    <div>
                        <p class="eyebrow mb-1">Applicant Registration</p>
                        <h1>Create Profile</h1>
                    </div>
                </div>
                <form method="post" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-md-7">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" required value="<?= e($candidate['email']) ?>" placeholder="name@example.com">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Phone Number</label>
                        <input name="phone" class="form-control" required value="<?= e($candidate['phone']) ?>" placeholder="08031234567">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required minlength="8" placeholder="Minimum 8 characters">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="8" placeholder="Repeat password">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-portal-green btn-lg w-100">Create Profile & Continue</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<?php render_footer(); ?>
