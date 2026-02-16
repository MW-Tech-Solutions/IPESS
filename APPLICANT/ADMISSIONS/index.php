<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="/JOSTUM/ADMIN/images/logo.jpeg">
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
            border-radius: 50%;
            border: 2px solid var(--uam-gold);
            object-fit: cover;
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

        @media (max-width: 768px) {
            .topbar .inner {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="container inner">
            <div class="meta">
                <span>+234 704 366 7952</span>
                <span>admissions@uam.edu.ng</span>
                <span>Mon - Fri: 8:00am - 4:00pm</span>
            </div>
            <div class="meta">
                <span>Prospective Students Portal</span>
            </div>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand" href="/JOSTUM/">
                <img src="images/jostum.jpeg" alt="JOSTUM Logo">
                <div>
                    <div>JOSTUM Admissions</div>
                    <small class="text-muted">Education for Individual and Social Responsibility</small>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="#programmes">Programmes</a></li>
                    <li class="nav-item"><a class="nav-link" href="#requirements">Requirements</a></li>
                    <li class="nav-item"><a class="nav-link" href="#process">How to Apply</a></li>
                    <li class="nav-item"><a class="nav-link" href="#deadlines">Deadlines</a></li>
                    <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                </ul>
                <div class="ms-lg-3 d-flex gap-2">
                    <a class="btn btn-apply" href="register.php">Apply Now</a>
                    <a class="btn btn-outline" href="/JOSTUM/">Main Site</a>
                </div>
            </div>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-bg" style="background-image: url('images/jostumgate-opt.jpg');"></div>
        <div class="container hero-content">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1 class="hero-title">Begin Your Admission Journey</h1>
                    <p class="hero-lead">
                        Explore undergraduate and postgraduate opportunities at Joseph Sarwuan Tarka University, Makurdi.
                        Start your application and track your admission status with ease.
                    </p>
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <a class="btn btn-apply" href="register.php">Start Application</a>
                        <a class="btn btn-outline" href="#process">View Admission Steps</a>
                    </div>
                </div>
                <div class="col-lg-5 mt-4 mt-lg-0">
                    <div class="highlight-box">
                        2025/2026 Admission Portal is now open. Submit your application early to secure your place.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5" id="programmes">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-6">
                    <h2 class="section-title">Postgraduate Programmes</h2>
                    <p class="text-muted">Advance your career with our postgraduate offerings designed for research, leadership, and professional growth.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card-soft">
                                <h5>PGD</h5>
                                <p class="text-muted">Postgraduate Diplomas for students bridging into advanced study.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card-soft">
                                <h5>Master's (MSc)</h5>
                                <p class="text-muted">Research and coursework master's degrees across key disciplines.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card-soft">
                                <h5>MBA</h5>
                                <p class="text-muted">Executive and professional MBA options for business leadership.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card-soft">
                                <h5>Doctorate (PhD)</h5>
                                <p class="text-muted">Advanced doctoral research programmes with expert supervision.</p>
                            </div>
                        </div>
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
                    <h2 class="section-title">Postgraduate Admission Requirements</h2>
                    <p class="text-muted">Review the entry requirements for PGD, MSc, MBA, and PhD programmes before applying.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card-soft">
                                <h6>First Degree (BSc/BA/BEng)</h6>
                                <p class="text-muted">Minimum of Second Class Lower or equivalent from a recognized institution.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card-soft">
                                <h6>PGD Requirements</h6>
                                <p class="text-muted">Third Class or HND with credit may be eligible for PGD programmes.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card-soft">
                                <h6>Master's (MSc/MBA)</h6>
                                <p class="text-muted">Relevant first degree plus transcripts and references where required.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card-soft">
                                <h6>Doctorate (PhD)</h6>
                                <p class="text-muted">Master's degree with strong research record and proposal summary.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5" id="process">
        <div class="container">
            <h2 class="section-title text-center">How to Apply</h2>
            <p class="text-center text-muted mb-4">A simple four-step process to get you started.</p>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card-soft h-100">
                        <div class="process-step">
                            <span>1</span>
                            <div>
                                <h6>Create an Account</h6>
                                <p class="text-muted">Sign up with a valid email to receive verification.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card-soft h-100">
                        <div class="process-step">
                            <span>2</span>
                            <div>
                                <h6>Fill Application</h6>
                                <p class="text-muted">Provide accurate personal and academic details.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card-soft h-100">
                        <div class="process-step">
                            <span>3</span>
                            <div>
                                <h6>Upload Documents</h6>
                                <p class="text-muted">Attach your credentials and required documents.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card-soft h-100">
                        <div class="process-step">
                            <span>4</span>
                            <div>
                                <h6>Submit & Track</h6>
                                <p class="text-muted">Submit your form and monitor your admission status.</p>
                            </div>
                        </div>
                    </div>
                </div>
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
                            <p class="text-muted">Keep track of critical milestones for the 2025/2026 admission cycle.</p>
                        </div>
                        <div class="card-soft deadlines-card">
                            <ul class="list-unstyled mb-0">
                                <li class="d-flex justify-content-between border-bottom py-2">
                                    <span>Applications Open</span>
                                    <strong>January 20, 2026</strong>
                                </li>
                                <li class="d-flex justify-content-between border-bottom py-2">
                                    <span>Early Submission Deadline</span>
                                    <strong>March 15, 2026</strong>
                                </li>
                                <li class="d-flex justify-content-between border-bottom py-2">
                                    <span>Final Submission Deadline</span>
                                    <strong>May 30, 2026</strong>
                                </li>
                                <li class="d-flex justify-content-between py-2">
                                    <span>Admission Decisions</span>
                                    <strong>June 20, 2026</strong>
                                </li>
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
                <div class="col-md-6">
                    <div class="card-soft">
                        <h6>Can I edit my application after submission?</h6>
                        <p class="text-muted">Yes, you can log in and update your application before the final deadline.</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card-soft">
                        <h6>Where do I pay my application fee?</h6>
                        <p class="text-muted">Payment instructions are provided on the application portal after registration.</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card-soft">
                        <h6>Are there scholarship opportunities?</h6>
                        <p class="text-muted">Qualified applicants may apply for merit-based and need-based scholarships.</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card-soft">
                        <h6>How do I contact the admissions office?</h6>
                        <p class="text-muted">Use the email and phone numbers listed in the contact section below.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="cta">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <h2>Ready to apply to JOSTUM?</h2>
                        <p>Start your application today and take the next step in your academic journey.</p>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <a class="btn btn-apply" href="register.php">Apply Now</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="footer-title">Admissions Office</div>
                    <p>Joseph Sarwuan Tarka University, Makurdi</p>
                    <p>Gbajimba Road, Makurdi, Benue State</p>
                </div>
                <div class="col-md-4">
                    <div class="footer-title">Contact</div>
                    <p>+234 704 366 7952</p>
                    <p>admissions@uam.edu.ng</p>
                </div>
                <div class="col-md-4">
                    <div class="footer-title">Quick Links</div>
                    <p><a href="register.php">Admission Application</a></p>
                    <p><a href="/JOSTUM/">Main University Website</a></p>
                </div>
            </div>
            <div class="footer-bottom">
                � 2026 Joseph Sarwuan Tarka University, Makurdi. All rights reserved.
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
