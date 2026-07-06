<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/admissions_content.php';
require_once __DIR__ . '/../../includes/portal_page_sections.php';

$admissionsContent = admissions_get_public_content($pdo ?? null);
$admissionPageContent = portal_page_get_content($pdo ?? null, 'admission_landing');
$programmes = $admissionsContent['programmes'];
$requirements = $admissionsContent['requirements'];
$importantDates = $admissionsContent['important_dates'];
$faqs = $admissionsContent['faqs'];
$notice = $admissionsContent['notice'];
$footerYear = date('Y');
$topbarContent = $admissionPageContent['topbar'] ?? [];
$navbarContent = $admissionPageContent['navbar'] ?? [];
$heroContent = $admissionPageContent['hero'] ?? [];
$processContent = $admissionPageContent['process'] ?? [];
$ctaContent = $admissionPageContent['cta'] ?? [];
$footerContent = $admissionPageContent['footer'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="/ADMIN/images/logo.jpeg">
<title>Admissions | Joseph Sarwuan Tarka University, Makurdi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&family=Source+Sans+3:wght@400;500;600;700&display=swap");

        :root {
            --uam-green: #0b5b3f;
            --uam-green-dark: #063b29;
            --uam-green-soft: #147a57;
            --uam-gold: #d4af37;
            --uam-cream: #f6f2e7;
            --uam-sand: #efe6d3;
            --uam-text: #1f2937;
            --uam-muted: #6b7280;
            --uam-border: #e2e0d9;
            --uam-card: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "Source Sans 3", "Segoe UI", sans-serif;
            background: radial-gradient(circle at top left, #ffffff 0%, #f5f1e6 45%, #e9f3ee 100%);
            color: var(--uam-text);
            margin: 0;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: "Merriweather", Georgia, serif;
            color: var(--uam-green-dark);
        }

        .topbar {
            background: var(--uam-green-dark);
            color: #fefbf4;
            font-size: 0.9rem;
        }

        .topbar .inner {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
            padding: 0.6rem 0;
        }

        .topbar .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
        }

        .navbar-brand img {
            width: 56px;
            height: 56px;
            border-radius: 0;
            border: none;
            object-fit: contain;
        }

        .brand-name {
            white-space: nowrap;
            display: inline-flex;
            gap: 0.2rem;
            font-size: 1.02rem;
        }

        .brand-sub {
            display: block;
            font-size: 0.82rem;
            color: var(--uam-muted);
            white-space: nowrap;
        }

        .navbar {
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .navbar .container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: nowrap;
        }

        .custom-toggle {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            border: 1px solid var(--uam-border);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            position: relative;
            background: #fff;
            margin-left: auto;
        }

        .navbar-toggler-icon { display: none; }

        .custom-toggle:focus {
            box-shadow: 0 0 0 0.15rem rgba(13, 110, 253, 0.15);
        }

        .custom-toggle .hamburger,
        .custom-toggle .hamburger::before,
        .custom-toggle .hamburger::after {
            content: "";
            position: absolute;
            left: 50%;
            width: 20px;
            height: 2px;
            background: var(--uam-text);
            border-radius: 2px;
            transform: translate(-50%, -50%);
            transition: transform 0.2s ease, top 0.2s ease, opacity 0.15s ease;
        }

        .custom-toggle .hamburger { top: 50%; }
        .custom-toggle .hamburger::before { top: calc(50% - 6px); }
        .custom-toggle .hamburger::after { top: calc(50% + 6px); }

        .custom-toggle[aria-expanded="true"] .hamburger { opacity: 0; }
        .custom-toggle[aria-expanded="true"] .hamburger::before {
            top: 50%;
            transform: translate(-50%, -50%) rotate(45deg);
            opacity: 1;
        }
        .custom-toggle[aria-expanded="true"] .hamburger::after {
            top: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            opacity: 1;
        }

        .hero {
            position: relative;
            overflow: hidden;
            color: #fefbf4;
            padding: 5rem 0;
            min-height: 65vh;
        }

        .hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(6, 59, 41, 0.92), rgba(20, 122, 87, 0.7));
            z-index: 1;
        }

        .hero-bg {
            position: absolute;
            inset: 0;
            background-position: center;
            background-size: cover;
            z-index: 0;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: clamp(2.2rem, 4vw, 3.6rem);
            font-weight: 800;
            color: #fff;
        }

        .hero-lead {
            font-size: 1.1rem;
            max-width: 650px;
            color: #dff3e7;
        }

        .btn-apply {
            background: var(--uam-gold);
            border: none;
            color: #2a1c00;
            font-weight: 700;
            padding: 0.7rem 1.6rem;
            border-radius: 999px;
        }

        .btn-outline {
            border: 1px solid #fff;
            color: #fff;
            padding: 0.7rem 1.6rem;
            border-radius: 999px;
        }

        .section-title {
            font-size: clamp(1.6rem, 2.6vw, 2.4rem);
            margin-bottom: 1rem;
        }

        .card-soft {
            background: var(--uam-card);
            border: 1px solid var(--uam-border);
            border-radius: 18px;
            padding: 1.5rem;
            box-shadow: 0 14px 30px rgba(12, 24, 18, 0.08);
            height: 100%;
        }

        .programme-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .programme-card:last-child:nth-child(odd) {
            grid-column: 1 / -1;
        }

        .programme-card:last-child:nth-child(odd) .card-soft {
            display: grid;
            grid-template-columns: minmax(150px, 0.32fr) 1fr;
            align-items: center;
            gap: 1rem;
            padding-block: 1.25rem;
        }

        .programme-card:last-child:nth-child(odd) h5,
        .programme-card:last-child:nth-child(odd) p {
            margin: 0;
        }

        .stat {
            text-align: center;
            padding: 1.4rem 1rem;
            border-radius: 18px;
            background: #fff;
            border: 1px solid var(--uam-border);
        }

        .stat h3 {
            color: var(--uam-green);
            font-size: 2rem;
        }

        .process-step {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }

        .process-step span {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(11, 91, 63, 0.15);
            color: var(--uam-green);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .cta {
            background: linear-gradient(120deg, var(--uam-green-dark), var(--uam-green));
            color: #fff;
            border-radius: 24px;
            padding: 2.5rem;
        }

        .cta h2 {
            color: #fff;
        }

        .footer {
            background: #0a1f15;
            color: #dfeee6;
            padding: 3rem 0 2rem;
        }

        .footer a {
            color: inherit;
            text-decoration: none;
        }

        .footer-title {
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: #f3fbf7;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            margin-top: 2rem;
            padding-top: 1rem;
            font-size: 0.85rem;
            text-align: center;
        }

        .highlight-box {
            background: #fffaf0;
            border-left: 4px solid var(--uam-gold);
            padding: 1rem 1.25rem;
            border-radius: 12px;
            font-weight: 600;
        }

        .image-frame {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 12px 30px rgba(12, 24, 18, 0.12);
        }

        #programmes .image-frame img {
            width: 100%;
            min-height: 430px;
            object-fit: cover;
        }

        .deadlines-card,
        .deadlines-image {
            height: 100%;
        }

        .deadlines-content {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .deadlines-card {
            flex: 1;
        }

        .deadlines-media {
            display: flex;
            height: 100%;
        }

        .deadlines-media .image-frame {
            flex: 1;
        }

        .deadlines-image img {
            height: 100%;
            object-fit: cover;
        }

        @media (max-width: 992px) {
            .topbar .inner {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .topbar .meta {
                width: 100%;
                flex-wrap: nowrap;
                gap: 0.8rem;
                white-space: nowrap;
                overflow-x: auto;
            }

            .navbar-brand {
                align-items: flex-start;
                flex: 1;
                min-width: 0;
            }

            .brand-name {
                white-space: normal;
                display: block;
                line-height: 1.2;
            }

            .name-line { display: block; }

            .brand-sub {
                white-space: normal;
                margin-top: 0.15rem;
            }

            .navbar-collapse {
                padding: 0.75rem 0;
            }

            .navbar-collapse .ms-lg-3 {
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
                margin-top: 0.75rem;
            }

            .navbar-collapse .btn {
                width: 100%;
                text-align: center;
            }
        }

        @media (max-width: 576px) {
            .programme-grid {
                grid-template-columns: 1fr;
            }

            .programme-card:last-child:nth-child(odd) {
                grid-column: auto;
            }

            .programme-card:last-child:nth-child(odd) .card-soft {
                display: block;
            }

            #programmes .image-frame img {
                min-height: 260px;
            }
        }
    </style>
</head>
<body>
    <?php if (!empty($notice) && !empty($notice['message'])): ?>
    <div class="modal fade" id="quickNoticeModal" tabindex="-1" aria-labelledby="quickNoticeLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="quickNoticeLabel"><?php echo htmlspecialchars($notice['title'] ?: 'Important Notice'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($notice['message'])); ?></p>
                </div>
                <?php if (!empty($notice['button_label']) && !empty($notice['button_url'])): ?>
                <div class="modal-footer">
                    <a class="btn btn-success" href="<?php echo htmlspecialchars($notice['button_url']); ?>"><?php echo htmlspecialchars($notice['button_label']); ?></a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="topbar">
        <div class="container inner">
            <div class="meta">
                <?php foreach (($topbarContent['meta'] ?? []) as $item): ?>
                    <span><?php echo htmlspecialchars($item); ?></span>
                <?php endforeach; ?>
            </div>
            <div class="meta">
                <span><?php echo htmlspecialchars($topbarContent['right_text'] ?? 'Prospective Students Portal'); ?></span>
            </div>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand" href="/">
                <img src="<?php echo htmlspecialchars($navbarContent['logo'] ?? 'images/jostum.jpeg'); ?>" alt="JOSTUM Logo">
                <div>
                    <div class="brand-name">
                        <?php foreach (($navbarContent['name_lines'] ?? []) as $line): ?>
                            <span class="name-line"><?php echo htmlspecialchars($line); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <small class="brand-sub"><?php echo htmlspecialchars($navbarContent['sub'] ?? 'Education for Individual and Social Responsibility'); ?></small>
                </div>
            </a>
            <button class="navbar-toggler custom-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Toggle navigation">
                <span class="hamburger"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <?php foreach (($navbarContent['nav_items'] ?? []) as $item): ?>
                        <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars($item['url'] ?? '#'); ?>"><?php echo htmlspecialchars($item['label'] ?? ''); ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <div class="ms-lg-3 d-flex gap-2">
                    <?php foreach (($navbarContent['actions'] ?? []) as $action): ?>
                        <a class="btn <?php echo ($action['variant'] ?? 'primary') === 'outline' ? 'btn-outline' : 'btn-apply'; ?>" href="<?php echo htmlspecialchars($action['url'] ?? '#'); ?>"><?php echo htmlspecialchars($action['label'] ?? ''); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-bg" style="background-image: url('<?php echo htmlspecialchars($heroContent['background_image'] ?? 'images/jostumgate-opt.jpg'); ?>');"></div>
        <div class="container hero-content">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1 class="hero-title"><?php echo htmlspecialchars($heroContent['title'] ?? 'Begin Your Admission Journey'); ?></h1>
                    <p class="hero-lead"><?php echo htmlspecialchars($heroContent['text'] ?? ''); ?></p>
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <a class="btn btn-apply" href="<?php echo htmlspecialchars($heroContent['primary_url'] ?? 'register.php'); ?>"><?php echo htmlspecialchars($heroContent['primary_label'] ?? 'Start Application'); ?></a>
                        <a class="btn btn-outline" href="<?php echo htmlspecialchars($heroContent['secondary_url'] ?? '#process'); ?>"><?php echo htmlspecialchars($heroContent['secondary_label'] ?? 'View Admission Steps'); ?></a>
                    </div>
                </div>
                <div class="col-lg-5 mt-4 mt-lg-0">
                    <div class="highlight-box">
                        <?php echo htmlspecialchars($notice['message'] ?? 'Admissions updates, deadlines, and urgent notices will appear here once published by the portal administration team.'); ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5" id="programmes">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-6">
                    <h2 class="section-title">Programmes</h2>
                    <p class="text-muted">Explore the available admissions programmes currently published by the portal administration team.</p>
                    <div class="programme-grid">
                        <?php foreach ($programmes as $programme): ?>
                        <div class="programme-card">
                            <div class="card-soft">
                                <h5><?php echo htmlspecialchars($programme['title']); ?></h5>
                                <p class="text-muted"><?php echo htmlspecialchars($programme['description'] ?? ''); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="image-frame">
                        <img src="images/jostumstudents.png" alt="JOSTUM Students" class="img-fluid">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5" id="requirements">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-5">
                    <div class="image-frame">
                        <img src="images/jostumhall.png" alt="JOSTUM Hall" class="img-fluid">
                    </div>
                </div>
                <div class="col-lg-7">
                    <h2 class="section-title">Admission Requirements</h2>
                    <p class="text-muted">Review the published entry requirements for your intended programme before applying.</p>
                    <div class="row g-3">
                        <?php foreach ($requirements as $requirement): ?>
                        <div class="col-md-6">
                            <div class="card-soft">
                                <h6><?php echo htmlspecialchars($requirement['title']); ?></h6>
                                <p class="text-muted"><?php echo htmlspecialchars($requirement['description'] ?? ''); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5" id="process">
        <div class="container">
            <h2 class="section-title text-center"><?php echo htmlspecialchars($processContent['title'] ?? 'How to Apply'); ?></h2>
            <p class="text-center text-muted mb-4"><?php echo htmlspecialchars($processContent['subtitle'] ?? 'A simple four-step process to get you started.'); ?></p>
            <div class="row g-4">
                <?php foreach (($processContent['steps'] ?? []) as $step): ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="card-soft h-100">
                            <div class="process-step">
                                <span><?php echo htmlspecialchars($step['number'] ?? ''); ?></span>
                                <div>
                                    <h6><?php echo htmlspecialchars($step['title'] ?? ''); ?></h6>
                                    <p class="text-muted"><?php echo htmlspecialchars($step['description'] ?? ''); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="py-5" id="deadlines">
        <div class="container">
            <div class="row g-4 align-items-stretch">
                <div class="col-lg-7">
                    <div class="deadlines-content">
                        <div>
                            <h2 class="section-title">Important Dates</h2>
                            <p class="text-muted">Keep track of critical milestones for the current admission cycle.</p>
                        </div>
                        <div class="card-soft deadlines-card">
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($importantDates as $index => $dateRow): ?>
                                <li class="d-flex justify-content-between <?php echo $index < count($importantDates) - 1 ? 'border-bottom' : ''; py-2">
                                    <span><?php echo htmlspecialchars($dateRow['title']); ?></span>
                                    <strong><?php echo htmlspecialchars(date('F j, Y', strtotime($dateRow['event_date']))); ?></strong>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="deadlines-media">
                        <div class="image-frame deadlines-image">
                            <img src="images/jostumm.jpeg" alt="Campus" class="img-fluid">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5" id="faq">
        <div class="container">
            <h2 class="section-title text-center">Frequently Asked Questions</h2>
            <div class="row g-4 mt-1">
                <?php foreach ($faqs as $faq): ?>
                <div class="col-md-6">
                    <div class="card-soft">
                        <h6><?php echo htmlspecialchars($faq['question']); ?></h6>
                        <p class="text-muted"><?php echo htmlspecialchars($faq['answer']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="cta">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <h2><?php echo htmlspecialchars($ctaContent['title'] ?? 'Ready to apply to JOSTUM?'); ?></h2>
                        <p><?php echo htmlspecialchars($ctaContent['text'] ?? ''); ?></p>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <a class="btn btn-apply" href="<?php echo htmlspecialchars($ctaContent['button_url'] ?? 'register.php'); ?>"><?php echo htmlspecialchars($ctaContent['button_label'] ?? 'Apply Now'); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <?php foreach (($footerContent['columns'] ?? []) as $column): ?>
                    <div class="col-md-4">
                        <div class="footer-title"><?php echo htmlspecialchars($column['title'] ?? ''); ?></div>
                        <?php foreach (($column['items'] ?? []) as $item): ?>
                            <?php if (is_string($item) && strpos($item, '|') !== false): ?>
                                <?php [$label, $url] = array_pad(explode('|', $item, 2), 2, '#'); ?>
                                <p><a href="<?php echo htmlspecialchars($url); ?>"><?php echo htmlspecialchars($label); ?></a></p>
                            <?php else: ?>
                                <p><?php echo htmlspecialchars((string) $item); ?></p>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="footer-bottom">
                &copy; <?php echo htmlspecialchars((string) $footerYear); ?> <?php echo htmlspecialchars($footerContent['bottom_text'] ?? 'Joseph Sarwuan Tarka University, Makurdi. All rights reserved.'); ?>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (!empty($notice) && !empty($notice['message'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalElement = document.getElementById('quickNoticeModal');
            if (!modalElement || typeof bootstrap === 'undefined') {
                return;
            }
            const noticeModal = new bootstrap.Modal(modalElement);
            noticeModal.show();
        });
    </script>
    <?php endif; ?>
</body>
</html>
