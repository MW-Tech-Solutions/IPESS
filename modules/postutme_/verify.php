<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/layout.php';

$candidate = null;
$missingJamb = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'complaint') {
        $stmt = db()->prepare('INSERT INTO support_requests (jamb_reg_no, name, email, phone, message) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([normalize_jamb($_POST['missing_jamb_reg_no'] ?? ''), trim($_POST['name'] ?? ''), trim($_POST['email'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['message'] ?? '')]);
        flash('success', 'Your manual review request has been submitted. The admissions helpdesk will review it.');
        redirect('verify.php');
    }

    if (($_POST['action'] ?? '') === 'confirm') {
        $candidateId = (int) ($_POST['candidate_id'] ?? 0);
        if (empty($_POST['details_confirmed'])) {
            flash('error', 'Please confirm that the displayed JAMB details are yours.');
        } else {
            $_SESSION['verified_jamb_candidate_id'] = $candidateId;
            db()->prepare('UPDATE jamb_candidates SET verified_at = NOW() WHERE id = ?')->execute([$candidateId]);
            redirect('create-profile.php');
        }
    }

    $jamb = normalize_jamb($_POST['jamb_reg_no'] ?? '');
    $session = active_session();
    if ($jamb !== '') {
        $stmt = db()->prepare('SELECT jc.* FROM jamb_candidates jc JOIN admission_sessions s ON s.id = jc.admission_session_id WHERE jc.jamb_reg_no = ? AND s.year_label = ? LIMIT 1');
        $stmt->execute([$jamb, $session['year_label']]);
        $candidate = $stmt->fetch();
        if ($candidate) {
            flash('success', 'JAMB record found. Please preview and confirm the details before profile creation.');
        } else {
            $missingJamb = $jamb;
            flash('error', 'Your JAMB record was not found in the current JOSTUM candidate list.');
        }
    }
}

render_header('Verify JAMB', 'home');
?>
<section class="section-pad gateway-shell">
    <div class="container">
        <div class="split-page">
            <div class="split-info">
                <img src="<?= e(url('images/new_jostum_logo.png')) ?>" alt="JOSTUM logo">
                <p class="eyebrow">JAMB Verification</p>
                <h1>Confirm your eligibility before profile creation</h1>
                <p>The portal checks your JAMB registration number against the current JOSTUM candidate list uploaded by ICT. Profile creation is only available when a matching record is found.</p>
                <div class="info-list">
                    <span>Preview your JAMB details before continuing</span>
                    <span>JAMB-supplied fields cannot be edited</span>
                    <span>Manual review is available only when enabled</span>
                </div>
            </div>
            <div class="auth-card">
                <div class="auth-brand">
                    <img src="<?= e(url('images/new_jostum_logo.png')) ?>" alt="JOSTUM logo">
                    <div>
                        <p class="eyebrow mb-1">Start Application</p>
                        <h1>Verify JAMB</h1>
                    </div>
                </div>
                <p class="text-muted">Enter the same JAMB registration number used for the current admission year.</p>
                <form method="post" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label class="form-label">JAMB Registration Number</label>
                        <input name="jamb_reg_no" class="form-control form-control-lg" required autocomplete="off" placeholder="Example: 202512345678AB">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-portal-green btn-lg w-100">Verify Candidate</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($candidate): ?>
            <div class="portal-card mt-4 verified-card">
                <h2>Readonly JAMB Preview</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Full Name</dt><dd class="col-sm-8"><?= e(candidate_full_name($candidate)) ?></dd>
                    <dt class="col-sm-4">JAMB Registration Number</dt><dd class="col-sm-8"><?= e($candidate['jamb_reg_no']) ?></dd>
                    <dt class="col-sm-4">Gender</dt><dd class="col-sm-8"><?= e($candidate['gender'] ?? '') ?></dd>
                    <dt class="col-sm-4">State</dt><dd class="col-sm-8"><?= e($candidate['state_origin']) ?></dd>
                    <dt class="col-sm-4">LGA</dt><dd class="col-sm-8"><?= e($candidate['lga']) ?></dd>
                    <dt class="col-sm-4">JAMB Score</dt><dd class="col-sm-8"><?= e(candidate_score($candidate)) ?></dd>
                    <dt class="col-sm-4">Applied Course</dt><dd class="col-sm-8"><?= e(candidate_course($candidate)) ?></dd>
                    <dt class="col-sm-4">UTME Subjects</dt><dd class="col-sm-8"><?= e(implode(', ', array_filter([$candidate['utme_subject_1'] ?? '', $candidate['utme_subject_2'] ?? '', $candidate['utme_subject_3'] ?? '', $candidate['utme_subject_4'] ?? '']))) ?></dd>
                </dl>
                <form method="post" class="mt-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="confirm">
                    <input type="hidden" name="candidate_id" value="<?= e((string) $candidate['id']) ?>">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="details_confirmed" id="details_confirmed" required>
                        <label class="form-check-label" for="details_confirmed">These are my details</label>
                    </div>
                    <button class="btn btn-gold">Create Profile</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($missingJamb): ?>
            <div class="portal-card mt-4">
                <h2>Your JAMB record was not found in the current JOSTUM candidate list.</h2>
                <ul class="friendly-list">
                    <li>Check the spelling of your JAMB registration number.</li>
                    <li>Try again using the exact number on your JAMB slip.</li>
                    <li>Submit a complaint/manual review request if you believe your record should be on the list.</li>
                </ul>
                <?php if (setting_bool('manual_review_enabled')): ?>
                    <form method="post" class="row g-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="complaint">
                        <input type="hidden" name="missing_jamb_reg_no" value="<?= e($missingJamb) ?>">
                        <div class="col-md-6"><label class="form-label">Full Name</label><input name="name" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                        <div class="col-12"><label class="form-label">Complaint</label><textarea name="message" class="form-control" rows="3" required>I cannot find my JAMB record on the JOSTUM candidate list.</textarea></div>
                        <div class="col-12"><button class="btn btn-portal-blue">Submit Manual Review Request</button></div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info mb-0">Manual review is currently disabled by the university. Profile creation is not allowed until your JAMB record is imported.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php render_footer(); ?>
