<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../includes/admissions_content.php';
require_once __DIR__ . '/../../includes/portal_page_sections.php';

$admissionsContent = admissions_get_public_content($pdo ?? null);
$admissionPageContent = portal_page_get_content($pdo ?? null, 'admission_landing');

$programmes = [
    ['title' => 'PGD PROCUREMENT MANAGEMENT', 'description' => 'Specialized training in public and corporate procurement systems, bidding processes, and contract management.'],
    ['title' => 'MSc PROCUREMENT MANAGEMENT', 'description' => 'Advanced academic and professional development in sustainable procurement strategies, logistics, and supply chain governance.'],
    ['title' => 'PGD ENVIRONMENTAL SUSTAINABILITY', 'description' => 'Practical entry-level training on environmental impact assessment, standard audits, and sustainable ecosystem management.'],
    ['title' => 'MSc ENVIRONMENTAL SUSTAINABILITY', 'description' => 'Research and development standards for ecological preservation, environmental governance, and resource management.'],
    ['title' => 'PGD SUSTAINABLE SOCIAL DEVELOPMENT', 'description' => 'Skills to design and monitor social safeguards, community development programs, and corporate social policies.'],
    ['title' => 'MSc SUSTAINABLE SOCIAL DEVELOPMENT', 'description' => 'Advanced study in social risk assessment, safeguard standards enhancement, and community welfare strategies.']
];
$requirements = $admissionsContent['requirements'] ?? [];
$importantDates = $admissionsContent['important_dates'] ?? [];
$faqs = $admissionsContent['faqs'] ?? [];
$notice = $admissionsContent['notice'] ?? [];

$footerYear = date('Y');
$topbarContent = $admissionPageContent['topbar'] ?? [
    'meta' => ['+234 704 366 7952', 'admissions@cipessfuam.edu.ng'],
    'social' => ['Facebook', 'Twitter', 'LinkedIn']
];
$navbarContent = $admissionPageContent['navbar'] ?? [];

$heroImages = [
    app_url('asset/homepage/auditorium.jpg'),
    app_url('asset/homepage/tor.png'),
    app_url('asset/homepage/library.png')
];

$heroContent = [
    'title' => 'Center of Excellence in Sustainable Procurement, Environmental & Social Standards',
    'text' => 'Empowering professionals with international standards in Procurement, Environmental, and Social Standards Enhancement (SPESSE) at Federal University of Agriculture Makurdi (FUAM).',
    'primary_label' => 'Start Application',
    'primary_url' => 'register.php',
    'secondary_label' => 'Student Login',
    'secondary_url' => 'login.php',
    'images' => $heroImages,
];

$vcContent = [
    'image' => app_url('asset/homepage/center_leader.jpg'),
    'image_alt' => 'Center Leader',
    'title' => "From the Center Leader's Desk",
    'paragraphs' => [
        "The raison d'etre of any government is the pursuit of the happiness of its citizenry. This way, the government adds value to the quality of life of its people in all strata of society. One of the ways of doing this is via standardization. In recent times, interest in the fields of procurement, environmental management and social safeguards have increased. This has necessitated the need for concomitant capacity building in minimum benchmarks to decrease the negative consequences linked to the absence of standards in the tripartite areas of procurement, environment and social safeguards.",
    ],
    'signature_name' => '',
    'signature_role' => 'Center Leader, IPESS FUAM',
];

$statsContent = [
    ['value' => 'Center of Excellence', 'label' => 'World Bank Assisted'],
    ['value' => '6+', 'label' => 'Academic Courses Offered'],
    ['value' => '30+', 'label' => 'Industry Expert Faculty'],
    ['value' => '12+', 'label' => 'Global Partners'],
];

$processContent = [
    'title' => 'How to Apply',
    'subtitle' => 'Follow these four simple steps to complete your postgraduate admission application.',
    'steps' => [
        ['number' => '01', 'title' => 'Create Account', 'description' => 'Register your profile on the admissions portal using a valid email and phone number.'],
        ['number' => '02', 'title' => 'Select Programme', 'description' => 'Choose your desired postgraduate course (PGD, MSc, or PhD) in your department of choice.'],
        ['number' => '03', 'title' => 'Upload Documents & Referee ID', 'description' => 'Provide academic transcripts, credentials, referee details, and your referee\'s work ID.'],
        ['number' => '04', 'title' => 'Submit & Track', 'description' => 'Submit your application and track the verification flow and admission decision in real-time.']
    ]
];

$officialLogo = app_url('asset/homepage/ipess_logo.png');

$portalLinks = [
    ['label' => 'Login', 'url' => 'login.php', 'variant' => 'primary'],
    ['label' => 'Register', 'url' => 'register.php', 'variant' => 'secondary'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="<?php echo app_url('asset/homepage/ipess_logo.png'); ?>">
    <title>IPESS FUAM Postgraduate Admissions</title>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&family=Source+Sans+3:wght@400;500;600;700&display=swap");
        :root { 
            --green-900: #782D32; 
            --green-700: #6EB533; 
            --gold-500: #d4af37; 
            --slate-900: #0f172a; 
            --slate-700: #334155; 
            --slate-500: #64748b; 
            --slate-200: #e2e8f0; 
            --white: #ffffff; 
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Source Sans 3", "Segoe UI", sans-serif; color: var(--slate-900); background: #ffffff; line-height: 1.6; }
        h1, h2, h3, h4, h5, h6 { font-family: "Merriweather", Georgia, serif; }
        a { color: inherit; text-decoration: none; }
        a:hover { cursor: pointer; }
        img { max-width: 100%; display: block; }
        .container { width: min(1180px, calc(100% - 40px)); margin: 0 auto; }
        .topbar { background: var(--green-900); color: var(--white); font-size: 13px; font-weight: 600; }
        .topbar .content { display: flex; justify-content: space-between; align-items: center; gap: 1rem; min-height: 40px; padding: .35rem 0; flex-wrap: wrap; }
        .topbar .meta, .topbar .social { display: flex; gap: 1.4rem; align-items: center; }
        .header { background: var(--white); position: sticky; top: 0; z-index: 50; box-shadow: 0 7px 24px rgba(0,0,0,.08); }
        .header .content { display: grid; grid-template-columns: 440px minmax(0, 1fr) auto; align-items: center; min-height: 88px; padding: .65rem 0; gap: 1.25rem; }
        .brand { display: flex; align-items: center; gap: .75rem; }
        .brand img { width: auto; max-height: 70px; object-fit: contain; }
        .brand-text { display: flex; flex-direction: column; }
        .brand-name { font-weight: 700; font-size: 1rem; color: var(--green-900); line-height: 1.2; }
        .brand-sub { font-size: 11px; color: var(--slate-500); line-height: 1.25; }
        .nav { display: flex; gap: .85rem; align-items: center; justify-content: flex-end; font-size: .84rem; }
        .nav-item { position: relative; padding: .3rem 0; font-weight: 600; color: var(--slate-700); white-space: nowrap; cursor: pointer; }
        .nav-item:hover { color: var(--green-700); }
        .dropdown { position: absolute; top: 100%; left: 0; min-width: 245px; background: var(--white); border-top: 3px solid var(--gold-500); box-shadow: 0 10px 30px rgba(15,23,42,.12); padding: .75rem; display: none; z-index: 10; }
        .dropdown a { display: block; padding: .55rem 0; font-size: .9rem; color: var(--slate-700); border-bottom: 1px solid #eef2f1; }
        .dropdown a:last-child { border-bottom: none; }
        .nav-item:hover .dropdown { display: block; }
        .portal-links { display: flex; gap: .6rem; align-items: center; }
        .pill { background: var(--green-700); color: var(--white); padding: .72rem .9rem; border-radius: 2px; font-weight: 600; font-size: .82rem; text-transform: uppercase; border: none; cursor: pointer; text-align: center; }
        .pill.secondary { background: var(--gold-500); color: #1c1400; }
        .menu-toggle { display: none; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 10px; border: 1px solid var(--slate-200); background: var(--white); cursor: pointer; position: relative; }
        .menu-toggle span, .menu-toggle::before, .menu-toggle::after { content: ""; position: absolute; left: 50%; width: 20px; height: 2px; background: var(--slate-900); border-radius: 2px; }
        .menu-toggle span { top: 50%; transform: translate(-50%,-50%); }
        .menu-toggle::before { top: calc(50% - 6px); transform: translate(-50%,-50%); }
        .menu-toggle::after { top: calc(50% + 6px); transform: translate(-50%,-50%); }
        .menu-toggle.active span { opacity: 0; }
        .menu-toggle.active::before { top: 50%; transform: translate(-50%,-50%) rotate(45deg); }
        .menu-toggle.active::after { top: 50%; transform: translate(-50%,-50%) rotate(-45deg); }
        
        .hero { position: relative; color: var(--white); min-height: 620px; display: flex; align-items: flex-end; overflow: hidden; background: var(--green-900); }
        .hero::before { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,.15), rgba(0,0,0,.68)); z-index: 1; }
        .hero-bg { position: absolute; inset: 0; background-size: cover; background-position: center; transition: opacity .8s ease; transform: scale(1.01); }
        .hero-bg.fade-out { opacity: 0; }
        .hero-content { position: relative; z-index: 2; text-align: left; padding: 0 0 3.75rem; width: 100%; max-width: 1180px; margin: 0 auto; display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 2rem; align-items: flex-end; }
        .hero h1 { font-size: clamp(2rem, 3.5vw, 2.85rem); font-weight: 800; margin-bottom: 1rem; line-height: 1.2; text-shadow: 0 2px 4px rgba(0,0,0,0.5); }
        .hero p { font-size: 1.05rem; margin-bottom: 2rem; color: #e2f5ea; text-shadow: 0 1px 2px rgba(0,0,0,0.5); }
        .hero .actions { display: flex; gap: 1rem; flex-wrap: wrap; }
        .hero .pill { border-radius: 2px; padding: .8rem 1.25rem; font-size: .88rem; box-shadow: 0 10px 22px rgba(0,0,0,.24); }
        
        .highlight-box { background: rgba(255, 250, 240, 0.95); border-left: 4px solid var(--gold-500); padding: 1.25rem; border-radius: 4px; color: var(--slate-900); font-weight: 600; font-size: 0.95rem; box-shadow: 0 10px 30px rgba(0,0,0,0.25); margin-bottom: 0.5rem; backdrop-filter: blur(10px); }
        .hero-dots { position: absolute; left: 50%; bottom: 1.2rem; transform: translateX(-50%); z-index: 2; display: flex; gap: .55rem; color: #fff; font-weight: 800; font-size: .82rem; }
        .hero-dots span { width: 34px; height: 34px; display: grid; place-items: center; border: 1px solid rgba(255,255,255,.65); background: rgba(15,59,36,.55); cursor: pointer; }
        .hero-dots span.active { background: var(--gold-500); color: #111; border-color: var(--gold-500); }
        
        .section { padding: 4.5rem 0; }
        .section-title { font-family: "Merriweather", Georgia, serif; color: var(--green-900); font-size: clamp(1.8rem, 2.5vw, 2.55rem); font-weight: 700; margin-bottom: 1.5rem; }
        .welcome { display: grid; gap: 3rem; grid-template-columns: minmax(280px, 420px) minmax(0, 1fr); align-items: center; }
        .vc-photo { border-radius: 8px; overflow: hidden; display: flex; justify-content: center; align-items: center; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border: 1px solid #eef2f1; }
        .vc-photo img { width: 100%; height: auto; object-fit: cover; aspect-ratio: 1/1; }
        .welcome-card { padding: 0; }
        .welcome-card p { color: var(--slate-700); margin-bottom: 1.25rem; font-size: 1.05rem; line-height: 1.8; text-align: justify; }
        .signature { font-weight: 800; color: var(--green-700) !important; margin-bottom: .2rem !important; }
        
        .grid-layout { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
        .card-item { background: var(--white); border-radius: 4px; box-shadow: 0 8px 24px rgba(15,23,42,.09); border: 1px solid #edf1ef; padding: 1.75rem; transition: transform 0.25s ease, box-shadow 0.25s ease; }
        .card-item:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(15,23,42,.15); }
        .card-item h5 { font-size: 1.15rem; color: var(--green-900); margin-bottom: 0.75rem; font-weight: 700; }
        .card-item p { color: var(--slate-500); font-size: 0.92rem; line-height: 1.5; }
        
        .glance { background: #f3f7f4; padding: 4.5rem 0; }
        .glance-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; margin-bottom: 1.5rem; }
        .glance-head small { color: var(--gold-500); font-weight: 800; text-transform: uppercase; letter-spacing: .08em; }
        .glance-head h2 { margin: 0; }
        .stats { display: grid; gap: .75rem; grid-template-columns: repeat(4, minmax(0, 1fr)); text-align: center; width: 100%; }
        .stat { padding: 1.4rem .8rem; border-radius: 0; background: var(--white); border: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
        .stat h3 { font-size: 2rem; color: var(--green-700); font-family: "Source Sans 3", "Segoe UI", sans-serif; font-weight: 800; }
        .stat p { color: #10243a; font-weight: 700; font-size: .88rem; margin-top: 0.25rem; }
        
        .process-steps { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1.25rem; margin-top: 2rem; }
        .step-card { background: var(--white); padding: 1.5rem; border-radius: 4px; border: 1px solid var(--slate-200); position: relative; }
        .step-num { width: 36px; height: 36px; border-radius: 4px; background: #e2f5ea; color: var(--green-700); display: grid; place-items: center; font-weight: 800; font-size: 0.95rem; margin-bottom: 1rem; }
        .step-card h5 { color: var(--green-900); font-size: 1.05rem; font-weight: 700; margin-bottom: 0.50rem; }
        .step-card p { color: var(--slate-500); font-size: 0.88rem; line-height: 1.45; }
        
        .deadlines-grid { display: block; margin-top: 1.5rem; }
        .deadlines-table { background: var(--white); border-radius: 4px; border: 1px solid var(--slate-200); padding: 1.5rem; }
        .deadline-row { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid var(--slate-200); }
        .deadline-row:last-child { border-bottom: none; }
        .deadline-row span { font-weight: 600; color: var(--slate-900); }
        .deadline-row strong { color: var(--green-700); font-weight: 700; }
        
        .cta-section { background: var(--green-900); border-radius: 0; padding: 3.25rem; display: grid; grid-template-columns: 1fr auto; align-items: center; gap: 2rem; max-width: 1180px; margin: 0 auto; }
        .cta-section h2 { color: var(--white); font-size: clamp(1.8rem, 2.5vw, 2.6rem); font-weight: 700; line-height: 1.25; margin-bottom: 0.5rem; }
        .cta-section p { color: #d8f3e4; font-size: 1.02rem; }
        .cta-section .pill { background: var(--gold-500); color: #111; padding: .85rem 1.1rem; border-radius: 2px; }
        
        .footer { background: #071d13; color: #e4f3ea; padding: 4rem 0 1.5rem; }
        .footer-grid { display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 2.5rem; }
        .footer h4 { color: var(--white); font-size: 1.08rem; font-weight: 700; margin-bottom: 1rem; }
        .footer ul { list-style: none; }
        .footer li { margin-bottom: .6rem; color: #c8ded2; font-size: .92rem; }
        .footer a:hover, .footer li:hover { color: var(--white); cursor: pointer; }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,.1); margin-top: 3rem; padding-top: 1.2rem; font-size: .84rem; color: #aec4b8; text-align: center; }
        
        .preloader { position: fixed; inset: 0; z-index: 9999; display: grid; place-items: center; background: #fff; transition: opacity .45s ease, visibility .45s ease; }
        .preloader.hidden { opacity: 0; visibility: hidden; }
        .preloader-box { display: grid; place-items: center; gap: 1rem; }
        .preloader-logo { width: min(260px, 70vw); animation: preloader-pulse 1.15s ease-in-out infinite; }
        .preloader-ring { width: 46px; height: 46px; border: 4px solid #d8eadf; border-top-color: #0f6f3f; border-radius: 50%; animation: preloader-spin .85s linear infinite; }
        @keyframes preloader-spin { to { transform: rotate(360deg); } }
        @keyframes preloader-pulse { 50% { transform: scale(1.035); opacity: .82; } }

        @media (max-width: 1200px) {
            .header .content { grid-template-columns: 280px minmax(0, 1fr); }
            .portal-links { display: none; }
        }
        @media (max-width: 900px) {
            .header .content { display: flex; justify-content: space-between; min-height: 76px; }
            .brand img { max-height: 60px; }
            .nav { position: fixed; top: 0; right: 0; bottom: 0; width: min(320px, 86vw); background: var(--white); border-left: 1px solid var(--slate-200); box-shadow: -18px 0 36px rgba(15,23,42,.18); display: flex; flex-direction: column; justify-content: flex-start; align-items: stretch; padding: 5.5rem 1.25rem 1.5rem; gap: .5rem; transform: translateX(100%); transition: transform .25s ease; z-index: 30; overflow-y: auto; }
            .nav.open { transform: translateX(0); }
            .nav-item { width: 100%; padding: .5rem 0; }
            .nav-item .dropdown { position: static; display: block; box-shadow: none; border: none; padding: .35rem 0 .5rem; }
            .menu-toggle { display: inline-flex; z-index: 40; }
            .hero { min-height: 480px; }
            .hero-content { grid-template-columns: 1fr; padding-bottom: 3.2rem; }
            .highlight-box { display: none; }
            .welcome { grid-template-columns: 1fr; gap: 2rem; }
            .vc-photo { order: -1; }
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .process-steps { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .deadlines-grid { grid-template-columns: 1fr; }
            .deadlines-img { display: none; }
            .cta-section { grid-template-columns: 1fr; padding: 2rem 1.5rem; }
            .footer-grid { grid-template-columns: 1fr; gap: 1.5rem; }
        }
        @media (max-width: 500px) {
            .stats { grid-template-columns: 1fr; }
            .process-steps { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="preloader" id="preloader" aria-label="Loading homepage">
        <div class="preloader-box">
            <img class="preloader-logo" src="<?php echo htmlspecialchars($officialLogo); ?>" alt="IPESS FUAM Logo">
            <div class="preloader-ring" aria-hidden="true"></div>
        </div>
    </div>
    
    <div class="topbar">
        <div class="container content">
            <div class="meta">
                <span><i class="fas fa-phone"></i> +234 704 366 7952</span>
                <span><i class="fas fa-envelope"></i> admissions@cipessfuam.edu.ng</span>
            </div>
            <div class="social">
                <span>portal.cipessfuam.edu.ng</span>
            </div>
        </div>
    </div>

    <header class="header">
        <div class="container content">
            <div class="brand">
                <img src="<?php echo htmlspecialchars($officialLogo); ?>" alt="IPESS FUAM Logo">
                <div class="brand-text">
                    <span class="brand-name">IPESS FUAM </span>
                    <span class="brand-sub">Center of Excellence in Sustainable Procurement, Environmental & Social Standards</span>
                </div>
            </div>
            <button class="menu-toggle" aria-label="Toggle navigation"><span></span></button>
            <nav class="nav">
                <div class="nav-item"><a href="#">Home</a></div>
                <div class="nav-item"><a href="#programmes">Programmes</a></div>
                <div class="nav-item"><a href="#requirements">Requirements</a></div>
                <div class="nav-item"><a href="#process">How to Apply</a></div>
                <div class="nav-item"><a href="#deadlines">Important Dates</a></div>
                <div class="nav-item"><a href="#faq">FAQs</a></div>
            </nav>
            <div class="portal-links">
                <?php foreach ($portalLinks as $link): ?>
                    <a class="pill<?php echo $link['variant'] === 'secondary' ? ' secondary' : ''; ?>" href="<?php echo htmlspecialchars($link['url']); ?>"><?php echo htmlspecialchars($link['label']); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </header>

    <section class="hero">
        <div class="hero-bg" id="heroBg" style="background-image: url('<?php echo htmlspecialchars($heroImages[0]); ?>');"></div>
        <div class="hero-content container">
            <div>
                <h1><?php echo htmlspecialchars($heroContent['title']); ?></h1>
                <p><?php echo htmlspecialchars($heroContent['text']); ?></p>
                <div class="actions">
                    <a class="pill" href="<?php echo htmlspecialchars($heroContent['primary_url']); ?>"><?php echo htmlspecialchars($heroContent['primary_label']); ?></a>
                    <a class="pill secondary" href="<?php echo htmlspecialchars($heroContent['secondary_url']); ?>"><?php echo htmlspecialchars($heroContent['secondary_label']); ?></a>
                </div>
            </div>
            <div>
                <?php if (!empty($notice) && !empty($notice['message'])): ?>
                    <div class="highlight-box">
                        <strong>Important Notice:</strong><br>
                        <?php echo nl2br(htmlspecialchars($notice['message'])); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container welcome">
            <div class="vc-photo">
                <img src="<?php echo htmlspecialchars($vcContent['image']); ?>" alt="Center Leader">
            </div>
            <div class="welcome-card">
                <h2 class="section-title"><?php echo htmlspecialchars($vcContent['title']); ?></h2>
                <?php foreach ($vcContent['paragraphs'] as $paragraph): ?>
                    <p><?php echo htmlspecialchars($paragraph); ?></p>
                <?php endforeach; ?>
                <?php if (!empty($vcContent['signature_name'])): ?>
                    <p class="signature"><?php echo htmlspecialchars($vcContent['signature_name']); ?></p>
                <?php endif; ?>
                <?php if (!empty($vcContent['signature_role'])): ?>
                    <p class="signature"><?php echo htmlspecialchars($vcContent['signature_role']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="section bg-light" id="programmes">
        <div class="container">
            <h2 class="section-title">Available Postgraduate Programmes</h2>
            <p class="text-muted">Explore the specialized postgraduate courses currently offered by the Center of Excellence:</p>
            <div class="grid-layout">
                <?php if (!empty($programmes)): ?>
                    <?php foreach ($programmes as $prog): ?>
                        <div class="card-item">
                            <h5><?php echo htmlspecialchars($prog['title']); ?></h5>
                            <p><?php echo htmlspecialchars($prog['description'] ?? 'Specialized course matching World Bank SPESSE requirements for corporate & public sector enhancement.'); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card-item">
                        <h5>Postgraduate Diploma (PGD)</h5>
                        <p>Specialized programs in Sustainable Procurement, Environmental Standards, and Social Standards.</p>
                    </div>
                    <div class="card-item">
                        <h5>Master of Science (M.Sc)</h5>
                        <p>Advanced study in Procurement Management, Environmental Standards, and Social Standards.</p>
                    </div>
                    <div class="card-item">
                        <h5>Doctor of Philosophy (PhD)</h5>
                        <p>Research-level specialization in sustainable development standards and implementation.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="section" id="requirements">
        <div class="container">
            <h2 class="section-title">Admissions Entry Requirements</h2>
            <p class="text-muted">Ensure you satisfy the entry criteria before beginning your registration:</p>
            <div class="grid-layout">
                <?php if (!empty($requirements)): ?>
                    <?php foreach ($requirements as $req): ?>
                        <div class="card-item">
                            <h5><?php echo htmlspecialchars($req['title']); ?></h5>
                            <p><?php echo htmlspecialchars($req['description'] ?? ''); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card-item">
                        <h5>PGD Requirements</h5>
                        <p>A third-class degree or Higher National Diploma (HND) in a related discipline from an approved institution.</p>
                    </div>
                    <div class="card-item">
                        <h5>M.Sc Requirements</h5>
                        <p>A minimum of a Second Class Lower degree in a relevant discipline with transcripts uploaded directly during application.</p>
                    </div>
                    <div class="card-item">
                        <h5>PhD Requirements</h5>
                        <p>An M.Sc degree with an average grade point of B (or 60%) from a recognized university, along with a research proposal outline.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="section bg-light" id="process">
        <div class="container">
            <h2 class="section-title text-center"><?php echo htmlspecialchars($processContent['title']); ?></h2>
            <p class="text-center text-muted mb-4"><?php echo htmlspecialchars($processContent['subtitle']); ?></p>
            <div class="process-steps">
                <?php foreach ($processContent['steps'] as $step): ?>
                    <div class="step-card">
                        <div class="step-num"><?php echo htmlspecialchars($step['number']); ?></div>
                        <h5><?php echo htmlspecialchars($step['title']); ?></h5>
                        <p><?php echo htmlspecialchars($step['description']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section" id="deadlines">
        <div class="container">
            <h2 class="section-title">Important Dates & Milestones</h2>
            <p class="text-muted">Observe key dates for the current admissions and verification calendar:</p>
            <div class="deadlines-grid">
                <div class="deadlines-table">
                    <?php if (!empty($importantDates)): ?>
                        <?php foreach ($importantDates as $dateRow): ?>
                            <div class="deadline-row">
                                <span><?php echo htmlspecialchars($dateRow['title']); ?></span>
                                <strong><?php echo htmlspecialchars(date('F j, Y', strtotime($dateRow['event_date']))); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="deadline-row">
                            <span>Application Deadline</span>
                            <strong>August 31, 2026</strong>
                        </div>
                        <div class="deadline-row">
                            <span>Screening and Verification Flow</span>
                            <strong>September 15, 2026</strong>
                        </div>
                        <div class="deadline-row">
                            <span>Admission List Release</span>
                            <strong>October 1, 2026</strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="section bg-light" id="faq">
        <div class="container">
            <h2 class="section-title text-center">Frequently Asked Questions</h2>
            <div class="grid-layout">
                <?php if (!empty($faqs)): ?>
                    <?php foreach ($faqs as $faq): ?>
                        <div class="card-item">
                            <h5><?php echo htmlspecialchars($faq['question']); ?></h5>
                            <p><?php echo htmlspecialchars($faq['answer']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card-item">
                        <h5>How long does verification take?</h5>
                        <p>Once you submit your application and referee uploads ID card, document verification usually takes 5-7 working days.</p>
                    </div>
                    <div class="card-item">
                        <h5>Can I apply with HND?</h5>
                        <p>Yes! HND candidates are fully eligible to apply for our Postgraduate Diploma (PGD) tracks.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="cta-section">
                <div>
                    <h2>Ready to start your application at IPESS?</h2>
                    <p>Advance your career with international standards in sustainable practices.</p>
                </div>
                <div>
                    <a class="pill" href="register.php">Apply Now</a>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container footer-grid">
            <div>
                <h4>Contact IPESS Admissions</h4>
                <ul>
                    <li><i class="fas fa-phone"></i> +234 704 366 7952</li>
                    <li><i class="fas fa-envelope"></i> admissions@cipessfuam.edu.ng</li>
                    <li><i class="fas fa-map-marker-alt"></i> CIPESS Center, FUAM, Makurdi</li>
                </ul>
            </div>
            <div>
                <h4>Portal Resources</h4>
                <ul>
                    <li><a href="login.php">Candidate Login</a></li>
                    <li><a href="register.php">Create Account</a></li>
                    <li><a href="#process">Admission Steps</a></li>
                </ul>
            </div>
            <div>
                <h4>Useful Links</h4>
                <ul>
                    <li><a href="https://portal.cipessfuam.edu.ng/">CIPESS Portal Home</a></li>
                    <li><a href="http://uam.edu.ng/">FUAM Main Website</a></li>
                    <li><a href="<?php echo rtrim(app_url('ADMIN'), '/'); ?>">Admin Login</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?php echo htmlspecialchars((string) $footerYear); ?> Center for Innovation in Procurement, Environmental and Social Standards (CIPESS), FUAM. Powered by ICT Directorate. All Rights Reserved.
        </div>
    </footer>

    <script>
        const menuToggle = document.querySelector('.menu-toggle');
        const nav = document.querySelector('.nav');
        menuToggle?.addEventListener('click', () => {
            nav.classList.toggle('open');
            menuToggle.classList.toggle('active');
        });
        
        const preloader = document.getElementById('preloader');
        window.addEventListener('load', () => {
            setTimeout(() => preloader?.classList.add('hidden'), 450);
        });
        setTimeout(() => preloader?.classList.add('hidden'), 3000);
    </script>
</body>
</html>

