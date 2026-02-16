<?php
session_start();
require 'db.php';

if (!isset($_GET['app_no'])) {
    header("Location: dashboard.php");
    exit();
}

$appNumber = $_GET['app_no'];

try {
    $stmt = $pdo->prepare("
        SELECT a.*, u.email as user_email, p.*, pc.*, n.*, w.*, r.*
        FROM applications a
        JOIN users u ON a.user_id = u.user_id
        LEFT JOIN personal_details p ON a.application_id = p.application_id
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
        LEFT JOIN nysc_details n ON a.application_id = n.application_id
        LEFT JOIN work_experience w ON a.application_id = w.application_id
        LEFT JOIN research_details r ON a.application_id = r.application_id
        WHERE a.application_number = ? AND a.status = 'Submitted'
    ");
    $stmt->execute([$appNumber]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$app) {
        die("Application not found or not yet submitted.");
    }

    $appId = $app['application_id'];
    
    $stmt = $pdo->prepare("SELECT * FROM olevel_exams WHERE application_id = ? ORDER BY sitting_number ASC");
    $stmt->execute([$appId]);
    $olevel_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM higher_education WHERE application_id = ?");
    $stmt->execute([$appId]);
    $education = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM referees WHERE application_id = ?");
    $stmt->execute([$appId]);
    $referees = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error fetching application details: " . $e->getMessage());
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="/JOSTUM/ADMIN/images/logo.jpeg">
<title>Acknowledgment Slip - <?php echo $appNumber; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root {
            --brand-blue: #1a4388;
            --text-dark: #333;
            --text-muted: #666;
            --border-light: #eee;
        }

        body { background-color: #f1f3f6; font-family: 'Inter', 'Segoe UI', sans-serif; color: var(--text-dark); }
        
        .slip-container { 
            max-width: 950px; 
            margin: 20px auto; 
            background: white; 
            padding: 20px; 
            border-radius: 4px; 
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        /* --- RESPONSIVE HEADER --- */
        .header-top {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .uni-logo { width: 90px; height: auto; }
        .header-titles h2 { color: var(--brand-blue); font-weight: 800; margin: 0; text-transform: uppercase; font-size: 1.2rem; line-height: 1.3; }
        .header-titles p { color: var(--text-muted); margin: 5px 0; font-size: 1rem; font-weight: 600; text-transform: uppercase; }

        .header-ruler { border-top: 3px solid var(--brand-blue); border-bottom: 1px solid var(--brand-blue); height: 6px; margin: 15px 0; }

        .header-meta { display: flex; flex-direction: column; gap: 10px; padding: 5px 0 15px 0; }
        .meta-box span { display: block; font-size: 0.7rem; color: #777; text-transform: uppercase; font-weight: bold; }
        .meta-box strong { font-size: 1rem; color: var(--brand-blue); }

        /* --- SECTION STYLING --- */
        .section-title { 
            background: var(--brand-blue); 
            padding: 6px 12px; 
            font-weight: 700; 
            color: #fff; 
            font-size: 0.8rem; 
            text-transform: uppercase; 
            margin-top: 25px; 
        }
        
        .info-grid { display: grid; grid-template-columns: 1fr; gap: 10px; padding: 10px 0; }
        .info-item { border-bottom: 1px solid #f0f0f0; padding-bottom: 5px; }
        .label { font-size: 0.65rem; color: #6c757d; font-weight: 700; text-transform: uppercase; display: block; }
        .value { font-size: 0.9rem; font-weight: 600; color: #111; }

        /* --- BOXED DATA --- */
        .data-card { border: 1px solid var(--border-light); padding: 12px; border-radius: 4px; background: #fafafa; height: 100%; }

        /* --- DESKTOP & PRINT OVERRIDES --- */
        @media (min-width: 768px) {
            .slip-container { padding: 50px; margin: 30px auto; }
            .header-top { display: grid; grid-template-columns: 120px 1fr 120px; flex-direction: row; }
            .uni-logo { width: 110px; }
            .header-titles h2 { font-size: 1.6rem; }
            .header-meta { flex-direction: row; justify-content: space-between; }
            .header-meta .meta-box.text-md-end { text-align: right !important; }
            .info-grid { grid-template-columns: repeat(2, 1fr); gap: 15px; }
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .slip-container { box-shadow: none; border: none; margin: 0; width: 100%; max-width: 100%; padding: 0px; }
            .header-top { display: grid; grid-template-columns: 120px 1fr 120px; }
            .header-meta { flex-direction: row; justify-content: space-between; }
            .info-grid { grid-template-columns: repeat(2, 1fr); }
            .section-title { -webkit-print-color-adjust: exact; background-color: #1a4388 !important; color: white !important; }
        }
    </style>
</head>
<body>

<div class="container no-print mt-4 text-center">
    <div class="mb-4">
        <button onclick="window.print()" class="btn btn-dark px-4 me-2"><i class="bi bi-printer me-2"></i> Print Slip</button>
        <a href="dashboard.php" class="btn btn-outline-primary">Return to Portal</a>
    </div>
</div>

<div class="slip-container">
    <header>
        <div class="header-top">
            <div class="logo-box">
                <img src="./images/jostum.png" alt="JOSTUM Logo" class="uni-logo">
            </div>
            <div class="header-titles">
                <h2>Joseph Sarwuan Tarka University, Makurdi</h2>
                <p>Postgraduate School</p>
                <div class="fw-bold mt-1 text-dark" style="font-size: 0.85rem;">Official Application Acknowledgment Slip</div>
            </div>
            <div class="d-none d-md-block"></div>
        </div>

        <div class="header-ruler"></div>

        <div class="header-meta">
            <div class="meta-box">
                <span>Application Number</span>
                <strong><?php echo htmlspecialchars($appNumber); ?></strong>
            </div>
            <!-- <div class="meta-box text-md-end">
                <span>Reference Number</span>
                <strong>PG/2025/<?php echo str_pad($appId, 4, '0', STR_PAD_LEFT); ?></strong>
            </div> -->
        </div>
    </header>

    <div class="section-title">Personal Information</div>
    <div class="info-grid">
        <div class="info-item"><span class="label">Full Name</span><span class="value"><?php echo strtoupper($app['surname'] . ' ' . $app['first_name'] . ' ' . $app['other_name']); ?></span></div>
        <div class="info-item"><span class="label">Email Address</span><span class="value"><?php echo $app['user_email']; ?></span></div>
        <div class="info-item"><span class="label">Phone / Gender</span><span class="value"><?php echo $app['phone'] . ' | ' . $app['sex']; ?></span></div>
        <div class="info-item"><span class="label">State / LGA</span><span class="value"><?php echo $app['state_origin'] . ' / ' . $app['lga']; ?></span></div>
    </div>

    <div class="section-title">Programme of Study</div>
    <div class="info-grid">
        <div class="info-item"><span class="label">Degree Applied</span><span class="value"><?php echo $app['degree_type']; ?></span></div>
        <div class="info-item"><span class="label">Mode of Study</span><span class="value"><?php echo $app['mode_of_study']; ?></span></div>
        <div class="info-item" style="grid-column: 1 / -1;"><span class="label">Department / Course</span><span class="value"><?php echo $app['department'] . ' - ' . $app['course']; ?></span></div>
    </div>

    <div class="section-title">Tertiary Education</div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered mt-2" style="font-size: 0.75rem;">
            <thead class="table-light">
                <tr><th>Institution</th><th>Qualification</th><th>Year</th><th>Grade</th></tr>
            </thead>
            <tbody>
                <?php foreach ($education as $edu): ?>
                <tr>
                    <td><?php echo $edu['institution']; ?></td>
                    <td><?php echo $edu['highest_qualification']; ?></td>
                    <td><?php echo $edu['grad_year']; ?></td>
                    <td><?php echo $edu['cgpa']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="section-title">O-Level Examination Results</div>
<div class="row g-3 mt-1">
    <?php if (!empty($olevel_exams)): ?>
        <?php foreach ($olevel_exams as $index => $exam): ?>
            <?php 
                try {
                    $stmt_res = $pdo->prepare("SELECT * FROM olevel_results WHERE exam_id = ?");
                    $stmt_res->execute([$exam['id']]);
                    $exam_results = $stmt_res->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) { $exam_results = []; }
            ?>
            <div class="col-md-6">
                <div class="data-card shadow-sm border">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <span class="badge bg-primary text-uppercase">Sitting <?php echo htmlspecialchars($exam['sitting_number']); ?></span>
                        <span class="value text-primary font-monospace" style="font-size: 0.8rem;"><?php echo htmlspecialchars($exam['exam_number']); ?></span>
                    </div>
                    
                    <div class="row mb-2">
                        <div class="col-6">
                            <span class="label">Exam Type</span>
                            <span class="value"><?php echo strtoupper(htmlspecialchars($exam['exam_type'])); ?></span>
                        </div>
                        <div class="col-6">
                            <span class="label">Year</span>
                            <span class="value"><?php echo htmlspecialchars($exam['exam_year']); ?></span>
                        </div>
                    </div>

                    <div class="mt-2">
                        <span class="label mb-1">Subjects & Grades</span>
                        <div class="rounded border bg-white">
                            <table class="table table-sm table-striped mb-0" style="font-size: 0.75rem;">
                                <tbody>
                                    <?php if (!empty($exam_results)): ?>
                                        <?php foreach ($exam_results as $result): ?>
                                        <tr>
                                            <td class="ps-2 py-1"><?php echo htmlspecialchars($result['subject_name']); ?></td>
                                            <td class="text-end pe-2 py-1 fw-bold"><?php echo htmlspecialchars($result['grade']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="2" class="text-center text-muted small">No results recorded.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12 text-center py-3 text-muted">No O-Level results provided.</div>
    <?php endif; ?>
</div>
    
    <div class="section-title">NYSC Details</div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered mt-2" style="font-size: 0.75rem;">
            <thead class="table-light">
                <tr>
                    <th style="width: 30%;">Exemption/Service Status</th>
                    <th style="width: 40%;">Certificate/Exemption Number</th>
                    <th style="width: 30%;">Year of Completion</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="value py-2"><?php echo htmlspecialchars($app['nysc_status']); ?></td>
                    <td class="value py-2 font-monospace"><?php echo htmlspecialchars($app['certificate_number'] ?: 'N/A'); ?></td>
                    <td class="value py-2"><?php echo !empty($app['nysc_year']) ? $app['nysc_year'] : 'N/A'; ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section-title">Work Experience</div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered mt-2" style="font-size: 0.75rem;">
            <thead class="table-light">
                <tr>
                    <th style="width: 50%;">Current Employer</th>
                    <th style="width: 50%;">Job Title & Experience</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="value py-2"><?php echo htmlspecialchars($app['employer'] ?: 'None / Not Specified'); ?></td>
                    <td class="value py-2">
                        <?php echo htmlspecialchars($app['job_title'] ?: 'N/A'); ?> 
                        <span class="text-muted fw-normal">(<?php echo ($app['years_experience'] ?? 0); ?> Years)</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section-title">Research Proposal / Details</div>
    <div class="p-3 border border-top-0 bg-white" style="min-height: 60px;">
        <span class="label">Proposed Research Title</span>
        <span class="value d-block mt-1" style="font-size: 0.85rem; line-height: 1.4;">
            <?php echo $app['research_area'] ?: 'No title provided'; ?>
        </span>
    </div>

    <div class="section-title">Referees</div>
    <div class="row g-2 mt-1">
        <?php foreach ($referees as $ref): ?>
            <div class="col-md-4">
                <div class="p-2 border rounded" style="background: #fdfdfd;">
                    <div class="value" style="font-size: 0.8rem;"><?php echo $ref['full_name']; ?></div>
                    <div class="text-muted" style="font-size: 0.7rem;"><?php echo $ref['title'] . ' - ' . $ref['organization']; ?></div>
                    <div class="text-muted" style="font-size: 0.8rem;"><?php echo $ref['phone']; ?></div>
                    <div class="text-muted" style="font-size: 0.7rem;"><?php echo $ref['email']; ?></div>

                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-5 pt-4 border-top">
    <div class="row align-items-center">
        <div class="col-md-3 mb-3 mb-md-0 text-center">
            <?php 
                $qrContent = "AppNo:" . $appNumber . " | URL:https://pg.jostum.edu.ng/verify";
                $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($qrContent);
            ?>
            <div style="display: inline-block; padding: 10px; border: 1px solid #eee; background: #fff;">
                <img src="<?php echo $qrUrl; ?>" alt="Verification QR Code" style="width: 110px; height: 110px;">
                <div style="font-size: 0.6rem; color: #999; margin-top: 5px; text-transform: uppercase;">Scan to Verify</div>
            </div>
        </div>

        <div class="col-md-9">
            <div class="row g-0">
                <div class="col-6 border-start ps-3">
                    <span class="label">Application Date</span>
                    <span class="value" style="font-size: 0.85rem;">
                        <?php 
                            echo !empty($app['submitted_at']) 
                                ? date('F d, Y - h:i A', strtotime($app['submitted_at'])) 
                                : 'N/A'; 
                        ?>
                    </span>
                </div>
                <div class="col-6 border-start ps-3">
                    <span class="label">Digital Signature</span>
                    <span class="value" style="font-size: 0.7rem; color: #1a4388; font-family: monospace;">
                        <?php echo strtoupper(md5($appNumber . $appId));?>
                    </span>
                </div>
            </div>
            
            
        </div>
    </div>
</div>
</div>

</body>
</html>