<?php
$stats = [
    'colleges' => 0,
    'departments' => 0,
    'staff' => 0,
    'awards' => 0
];

try {
    require_once __DIR__ . '/config/db.php';

    if (!function_exists('jostum_table_exists')) {
        function jostum_table_exists(PDO $pdo, string $table): bool {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        }
    }

    if (!function_exists('jostum_count_table')) {
        function jostum_count_table(PDO $pdo, string $table): int {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
            return (int) $stmt->fetchColumn();
        }
    }

    if (isset($pdo)) {
        if (jostum_table_exists($pdo, 'faculties')) {
            $stats['colleges'] = jostum_count_table($pdo, 'faculties');
        } elseif (jostum_table_exists($pdo, 'colleges')) {
            $stats['colleges'] = jostum_count_table($pdo, 'colleges');
        }

        if (jostum_table_exists($pdo, 'departments')) {
            $stats['departments'] = jostum_count_table($pdo, 'departments');
        }

        if (jostum_table_exists($pdo, 'supervisors')) {
            $stats['staff'] = jostum_count_table($pdo, 'supervisors');
        } elseif (jostum_table_exists($pdo, 'users') && jostum_table_exists($pdo, 'roles')) {
            $stmt = $pdo->query("                SELECT COUNT(*)
                FROM users u
                LEFT JOIN roles r ON r.role_id = u.role_id
                WHERE UPPER(COALESCE(r.role_key, r.role_name, '')) IN (
                    'SUPERVISOR',
                    'LECTURER',
                    'TEACHING_STAFF',
                    'STAFF'
                )
            ");
            $stats['staff'] = (int) $stmt->fetchColumn();
        } elseif (jostum_table_exists($pdo, 'staff')) {
            $stats['staff'] = jostum_count_table($pdo, 'staff');
        }

        $award_tables = ['awards', 'award_wins', 'honours', 'honors', 'achievements'];
        foreach ($award_tables as $table) {
            if (jostum_table_exists($pdo, $table)) {
                $stats['awards'] = jostum_count_table($pdo, $table);
                break;
            }
        }
    }
} catch (Throwable $e) {
    // Fall back to zeros if database is unavailable.
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="/JOSTUM/ADMIN/images/logo.jpeg">
    <title>Joseph Sarwuan Tarka University, Makurdi</title>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&family=Source+Sans+3:wght@400;500;600;700&display=swap");

        :root {
            --green-900: #0f3b24;
            --green-700: #0f6f3f;
            --green-600: #118a4e;
            --gold-500: #d6a847;
            --slate-900: #0f172a;
            --slate-700: #334155;
            --slate-500: #64748b;
            --slate-200: #e2e8f0;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Source Sans 3", "Segoe UI", sans-serif;
            color: var(--slate-900);
            background: #f7f9fb;
            line-height: 1.6;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: "Merriweather", Georgia, serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        img {
            max-width: 100%;
            display: block;
        }

        .container {
            width: min(1200px, 92%);
            margin: 0 auto;
        }

        .topbar {
            background: var(--green-900);
            color: var(--white);
            font-size: 0.88rem;
        }

        .topbar .content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 0.5rem 0;
            flex-wrap: wrap;
        }

        .topbar .meta {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .topbar .social {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .header {
            background: var(--white);
            border-bottom: 1px solid var(--slate-200);
            position: sticky;
            top: 0;
            z-index: 20;
        }

        .header .content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.75rem 0;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .brand img {
            width: 56px;
            height: 56px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid var(--green-700);
        }

        .brand .name {
            font-weight: 700;
            font-size: 1.05rem;
        }

        .brand .sub {
            font-size: 0.78rem;
            color: var(--slate-500);
        }

        .nav {
            display: flex;
            gap: 1.1rem;
            align-items: center;
            flex-wrap: wrap;
            font-size: 0.95rem;
        }

        .nav-item {
            position: relative;
            padding: 0.3rem 0;
            font-weight: 600;
            color: var(--slate-700);
        }

        .nav-item:hover {
            color: var(--green-700);
        }

        .dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            min-width: 210px;
            background: var(--white);
            border: 1px solid var(--slate-200);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
            padding: 0.75rem;
            display: none;
            z-index: 10;
        }

        .dropdown a {
            display: block;
            padding: 0.45rem 0;
            font-size: 0.9rem;
            color: var(--slate-700);
        }

        .nav-item:hover .dropdown {
            display: block;
        }

        .portal-links {
            display: flex;
            gap: 0.6rem;
        }

        .pill {
            background: var(--green-700);
            color: var(--white);
            padding: 0.5rem 0.9rem;
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .pill.secondary {
            background: var(--gold-500);
            color: #2b1c00;
        }

        .hero {
            position: relative;
            color: var(--white);
            min-height: 70vh;
            display: grid;
            place-items: center;
            overflow: hidden;
        }

        .hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(15, 59, 36, 0.9), rgba(17, 138, 78, 0.65));
            z-index: 1;
        }

        .hero-bg {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            transition: opacity 0.8s ease;
        }

        .hero-bg.fade-out {
            opacity: 0;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
            padding: 5rem 0;
            max-width: 820px;
        }

        .hero h1 {
            font-size: clamp(2rem, 4vw, 3.3rem);
            font-weight: 800;
            margin-bottom: 1rem;
        }

        .hero p {
            font-size: 1.05rem;
            margin-bottom: 2rem;
            color: #e2f5ea;
        }

        .hero .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .section {
            padding: 4rem 0;
        }

        .section-title {
            font-size: clamp(1.6rem, 2.5vw, 2.2rem);
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--green-900);
        }

        .welcome {
            display: grid;
            gap: 2rem;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            align-items: stretch;
        }

        .welcome-card {
            background: var(--white);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
            height: 100%;
        }

        .vc-photo {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
            height: 100%;
        }

        .vc-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .welcome-card p {
            color: var(--slate-700);
            margin-bottom: 1rem;
        }

        .signature {
            font-weight: 700;
            color: var(--green-700);
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
        }

        .event-card {
            background: var(--white);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
            display: flex;
            flex-direction: column;
        }

        .event-card img {
            height: 180px;
            object-fit: cover;
        }

        .event-card .content {
            padding: 1.25rem;
            flex: 1;
        }

        .event-card small {
            color: var(--slate-500);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
        }

        .event-card h3 {
            font-size: 1rem;
            margin: 0.6rem 0 0.75rem;
            color: var(--slate-900);
        }

        .stats {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            text-align: center;
        }

        .stat {
            background: var(--white);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--slate-200);
        }

        .stat h3 {
            font-size: 2rem;
            color: var(--green-700);
        }

        .cta {
            background: linear-gradient(120deg, var(--green-900), var(--green-700));
            color: var(--white);
            border-radius: 24px;
            padding: 3rem;
            display: grid;
            gap: 2rem;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            align-items: center;
        }

        .cta p {
            color: #d8f3e4;
        }

        .footer {
            background: #0a1f15;
            color: #e4f3ea;
            padding: 3rem 0 2rem;
        }

        .footer-grid {
            display: grid;
            gap: 2rem;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }

        .footer h4 {
            margin-bottom: 0.75rem;
            color: #e7f6ee;
        }

        .footer ul {
            list-style: none;
        }

        .footer li {
            margin-bottom: 0.5rem;
            color: #c8ded2;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            margin-top: 2rem;
            padding-top: 1rem;
            font-size: 0.85rem;
            color: #b6cabb;
            text-align: center;
        }

        @media (max-width: 920px) {
            .nav {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="container content">
            <div class="meta">
                <span>+234 704 366 7952</span>
                <span>info@uam.edu.ng</span>
                <span>Mon - Fri: 8:00 - 16:00hrs</span>
            </div>
            <div class="social">
                <span>Facebook</span>
                <span>Instagram</span>
                <span>Telegram</span>
                <span>Youtube</span>
            </div>
        </div>
    </div>

    <header class="header">
        <div class="container content">
            <div class="brand">
                <img src="APPLICANT/ADMISSIONS/images/jostum.jpeg" alt="JOSTUM Logo">
                <div>
                    <div class="name">Joseph Sarwuan Tarka University, Makurdi</div>
                    <div class="sub">Education for Individual and Social Responsibility</div>
                </div>
            </div>
            <nav class="nav">
                <div class="nav-item">
                    Home
                </div>
                <div class="nav-item">
                    About Us
                    <div class="dropdown">
                        <a href="#">History</a>
                        <a href="#">Vision</a>
                        <a href="#">Mission</a>
                    </div>
                </div>
                <div class="nav-item">
                    Leadership
                    <div class="dropdown">
                        <a href="#">Visitors</a>
                        <a href="#">Principal Officers</a>
                        <a href="#">Deans of Colleges</a>
                        <a href="#">Heads of Departments</a>
                        <a href="#">Directors</a>
                    </div>
                </div>
                <div class="nav-item">
                    Administration
                    <div class="dropdown">
                        <a href="#">Office of the VC</a>
                        <a href="#">Registry</a>
                        <a href="#">Bursary</a>
                    </div>
                </div>
                <div class="nav-item">
                    Admissions
                    <div class="dropdown">
                        <a href="#">Undergraduate Requirement</a>
                        <a href="#">Sandwich Requirement</a>
                        <a href="#">Post-graduate Requirement</a>
                        <a href="#">JUPEB Requirement</a>
                        <a href="#">Remedial Requirement</a>
                        <a href="#">Check Admission</a>
                    </div>
                </div>
                <div class="nav-item">
                    Academics
                    <div class="dropdown">
                        <a href="#">Colleges/Departments</a>
                        <a href="#">Post-graduate School</a>
                        <a href="#">Directorates</a>
                        <a href="#">Academic Calendar</a>
                    </div>
                </div>
                <div class="nav-item">News & Events</div>
                <div class="nav-item">Contact Us</div>
            </nav>
            <div class="portal-links">
                <!-- <a class="pill secondary" href="ADMIN/index.php">ADMIN</a> -->
                <a class="pill" href="APPLICANT/ADMISSIONS/index.php">APPLICANT</a>
            </div>
        </div>
    </header>

    <section class="hero">
        <div class="hero-bg" id="heroBg" style="background-image: url('APPLICANT/ADMISSIONS/images/jostumgate.png');"></div>
        <div class="hero-content container">
            <h1>Welcome to Joseph Sarwuan Tarka University, Makurdi</h1>
            <p>
                A vibrant academic community committed to excellence in teaching, research, and service.
                Discover programs that shape innovators and leaders for tomorrow.
            </p>
            <div class="actions">
                <a class="pill" href="APPLICANT/ADMISSIONS/login.php">Apply Now</a>
                <!-- <a class="pill secondary" href="ADMIN/index.php">Portal Access</a> -->
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container welcome">
            <div class="vc-photo">
                <img src="APPLICANT/ADMISSIONS/images/vc.png" alt="JOSTUM Students">
            </div>
            <div class="welcome-card">
                <h2 class="section-title">Message from the Vice Chancellor</h2>
                <p>
                    Dear students, staff, and visitors, welcome to Joseph Sarwuan Tarka University, Makurdi.
                    We are proud of our culture of scholarship, innovation, and community impact.
                </p>
                <p>
                    Our institution offers a wide range of undergraduate and postgraduate programs
                    supported by dedicated faculty and modern learning resources.
                </p>
                <p class="signature">Professor Isaac Nathaniel Itodo</p>
                <p class="signature">Vice Chancellor</p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <h2 class="section-title">Latest Campus Events</h2>
            <div class="events-grid">
                <article class="event-card">
                    <img src="APPLICANT/ADMISSIONS/images/jostumhall.png" alt="IPESS">
                    <div class="content">
                        <small>IPESS • October 3, 2025</small>
                        <h3>Stronger country systems: How Nigeria is building a skilled workforce for the future</h3>
                        <p>Highlights from the IPESS capacity development initiative and its impact.</p>
                    </div>
                </article>
                <article class="event-card">
                    <img src="APPLICANT/ADMISSIONS/images/jostumgate.png" alt="IPESS Graduates">
                    <div class="content">
                        <small>IPESS • September 21, 2025</small>
                        <h3>IPESS JOSTUM graduates 140 trainees in Minna and Lokoja</h3>
                        <p>Valedictory sessions celebrate new professionals trained across two states.</p>
                    </div>
                </article>
                <article class="event-card">
                    <img src="APPLICANT/ADMISSIONS/images/jostumstudents.png" alt="Engineering Homecoming">
                    <div class="content">
                        <small>College News • September 9, 2025</small>
                        <h3>Homecoming: Engineering family in JOSTUM receive Engr. Prof. Itodo</h3>
                        <p>A warm reception honoring leadership and service to the university.</p>
                    </div>
                </article>
                <article class="event-card">
                    <img src="APPLICANT/ADMISSIONS/images/jostumm.jpeg" alt="Campus">
                    <div class="content">
                        <small>IPESS • August 6, 2025</small>
                        <h3>SPESSE Project: IPESS – FUAM breaking new grounds in human capacity</h3>
                        <p>Strategic programs expand professional training opportunities.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container stats">
            <div class="stat">
                <h3><?php echo number_format($stats['colleges']); ?></h3>
                <p>Total Colleges</p>
            </div>
            <div class="stat">
                <h3><?php echo number_format($stats['departments']); ?></h3>
                <p>Total Departments</p>
            </div>
            <div class="stat">
                <h3><?php echo number_format($stats['staff']); ?></h3>
                <p>Teaching Staff</p>
            </div>
            <div class="stat">
                <h3><?php echo number_format($stats['awards']); ?></h3>
                <p>Award Winning</p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container cta">
            <div>
                <h2>Are you ready to start your career journey with us?</h2>
                <p>Education for Individual and Social Responsibility.</p>
            </div>
            <div class="actions">
                <a class="pill" href="APPLICANT/ADMISSIONS/index.php">Start Application</a>
                <a class="pill secondary" href="ADMIN/index.php">Administrator Access</a>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container footer-grid">
            <div>
                <h4>Contact Us</h4>
                <ul>
                    <li>+234 704 366 7952</li>
                    <li>info@uam.edu.ng</li>
                    <li>JOSTUM P.M.B. 2373</li>
                    <li>Gbajimba Road, Makurdi Nigeria</li>
                </ul>
            </div>
            <div>
                <h4>Connect With Us</h4>
                <ul>
                    <li>Facebook</li>
                    <li>Instagram</li>
                    <li>Telegram</li>
                    <li>Youtube</li>
                </ul>
            </div>
            <div>
                <h4>Academics</h4>
                <ul>
                    <li>Admissions</li>
                    <li>Colleges</li>
                    <li>Departments</li>
                    <li>Courses</li>
                    <li>Calendar</li>
                    <li>Library</li>
                </ul>
            </div>
            <div>
                <h4>Useful Links</h4>
                <ul>
                    <li><a href="ADMIN/index.php">ADMIN</a></li>
                    <li><a href="APPLICANT/ADMISSIONS/index.php">APPLICANT</a></li>
                    <li>Directorates</li>
                    <li>Resources</li>
                    <li>Fees</li>
                    <li>FAQs</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            © Copyright 2025 – Joseph Sarwuan Tarka University, Makurdi. Powered by Directorate of ICT.
        </div>
    </footer>

    <script>
        const heroImages = [
            "APPLICANT/ADMISSIONS/images/jostumgate.png",
            "APPLICANT/ADMISSIONS/images/jostumhall.png",
            "APPLICANT/ADMISSIONS/images/jostumstudents.png"
        ];

        let heroIndex = 0;
        const heroBg = document.getElementById("heroBg");

        setInterval(() => {
            heroBg.classList.add("fade-out");
            setTimeout(() => {
                heroIndex = (heroIndex + 1) % heroImages.length;
                heroBg.style.backgroundImage = `url('${heroImages[heroIndex]}')`;
                heroBg.classList.remove("fade-out");
            }, 600);
        }, 6000);
    </script>
</body>
</html>
