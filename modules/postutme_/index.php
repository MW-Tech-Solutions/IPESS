<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/layout.php';

$session = active_session();
$notices = db()->query('SELECT * FROM notices WHERE is_published = 1 ORDER BY created_at DESC LIMIT 4')->fetchAll();
$deadlines = db()->query('SELECT * FROM deadlines WHERE is_active = 1 ORDER BY deadline_date ASC LIMIT 4')->fetchAll();

render_header('POST-UTME Online Screening Portal', 'home');
?>
<section class="landing-shell">
    <div class="container">
        <div class="landing-panel">
            <aside class="programme-rail">
                <a class="rail-item active" href="<?= e(url('verify.php')) ?>">
                    <span>Undergraduate</span>
                    <small>POST-UTME Screening</small>
                </a>
                <a class="rail-item" href="<?= e(url('status.php')) ?>">
                    <span>Application Status</span>
                    <small>Track submitted forms</small>
                </a>
                <a class="rail-item" href="<?= e(url('login.php')) ?>">
                    <span>Applicant Login</span>
                    <small>Continue application</small>
                </a>
            </aside>

            <div class="portal-main">
                <div class="portal-title-row">
                    <div>
                        <p class="eyebrow mb-1">Joseph Sarwuan Tarka University, Makurdi</p>
                        <h1>POST-UTME Online Screening Portal</h1>
                        <p class="hero-copy">Select an action below to begin or continue your screening for the <?= e($session['year_label']) ?> admission year.</p>
                    </div>
                    <img src="<?= e(url('images/new_jostum_logo.png')) ?>" alt="JOSTUM logo" class="landing-logo">
                </div>

                <div class="screening-box">
                    <div class="box-heading">
                        <h2>Post UTME</h2>
                        <span>Admission Year: <?= e($session['year_label']) ?></span>
                    </div>
                    <p>Please select a link applicable to you from the list below.</p>
                    <div class="portal-action-list">
                        <a href="<?= e(url('verify.php')) ?>">Start Application</a>
                        <a href="<?= e(url('login.php')) ?>">Continue Application</a>
                        <a href="<?= e(url('status.php')) ?>">Check Status</a>
                    </div>
                </div>

                <div class="light-grid">
                    <section>
                        <div class="section-title">
                            <h2>Notice Board</h2>
                            <p>Official screening announcements and applicant guidance.</p>
                        </div>
                        <div class="notice-list">
                            <?php foreach ($notices as $notice): ?>
                                <article class="light-card">
                                    <h3><?= e($notice['title']) ?></h3>
                                    <p><?= e($notice['body']) ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <aside>
                        <div class="light-card">
                            <h2>Important Deadlines</h2>
                            <div class="deadline-list">
                                <?php foreach ($deadlines as $deadline): ?>
                                    <div>
                                        <span><?= e($deadline['label']) ?></span>
                                        <strong><?= e(date('M j, Y', strtotime($deadline['deadline_date']))) ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="light-card mt-3">
                            <h2>Help & Support</h2>
                            <p>For payment or application issues, contact the admissions help desk with your JAMB registration number.</p>
                            <div><strong>Email:</strong> <?= e(setting('support_email', 'admissions@jostum.edu.ng')) ?></div>
                            <div><strong>Phone:</strong> <?= e(setting('support_phone', '08000000000')) ?></div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>
</section>
<?php render_footer(); ?>
