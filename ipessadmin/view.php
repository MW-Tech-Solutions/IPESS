<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require 'db.php';
require_once __DIR__ . '/../path.php';
require_once __DIR__ . '/../config/urls.php';
require_once __DIR__ . '/includes/upload_path.php';
require_once __DIR__ . '/../includes/status_engine.php';
require_once __DIR__ . '/../includes/permissions.php';

$isEmbed = isset($_GET['embed']) && $_GET['embed'] === '1';

$role = $_SESSION['role'] ?? $_SESSION['roleid'] ?? '';
$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['userid'] ?? 0);

if (!$userId || !$role || normalize_role($role) === 'STUDENT') {
    redirect_to('ipessadmin/login.php');
}

$userDeptId = null;
if ($userId > 0 && isset($pdo)) {
    try {
        $stmtDept = $pdo->prepare("SELECT department_id FROM users WHERE user_id = ? LIMIT 1");
        $stmtDept->execute([$userId]);
        $userDeptId = $stmtDept->fetchColumn();
        if ($userDeptId) {
            $userDeptId = (int) $userDeptId;
        } else {
            $userDeptId = null;
        }
    } catch (Throwable $e) {}
}

function get_back_link($role) {
    $normRole = normalize_role($role);
    switch ($normRole) {
        case 'SUPER_ADMIN':
        case 'ICT_ADMIN':
            return app_url('ipessadmin/records.php');
        case 'ICTO':
            return app_url('ipessadmin/document-verification.php');
        case 'HOD':
        case 'DEPARTMENT_ADMIN':
            return app_url('ipessadmin/department-applications.php');
        case 'COLLEGE_ADMIN':
            return app_url('ipessadmin/faculty-applications.php');
        case 'PG_ADMIN':
        case 'PG_SCHOOL_OFFICER':
            return app_url('ipessadmin/pg-applications.php');
        default:
            return app_url('ipessadmin/records.php');
    }
}
$backLink = get_back_link($role);

if (!isset($_GET['app_no'])) {
    header("Location: " . $backLink);
    exit();
}

$rawAppNo = $_GET['app_no'];
$decrypted = decrypt_app_number($rawAppNo);
if ($decrypted !== '' && str_contains($decrypted, 'IPESS')) {
    $appNumber = $decrypted;
} else {
    $appNumber = $rawAppNo;
}

function resolveDocUrl($filePath) {
    if (empty($filePath)) return '';
    if (preg_match('#^https?://#i', $filePath)) return $filePath;
    $clean = ltrim($filePath, '/');
    return app_url($clean);
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            a.*, 
            u.email as user_email, 
            p.*, 
            pc.*, 
            n.*, 
            w.*, 
            r.*,
            f.faculty_name,
            d.dept_name,
            dt.degree_name,
            c.course_title,
            sm.mode_name
        FROM applications a
        LEFT JOIN users u ON a.user_id = u.user_id
        LEFT JOIN personal_details p ON a.application_id = p.application_id
        LEFT JOIN programme_choices pc ON pc.application_id = a.application_id
        LEFT JOIN nysc_details n ON a.application_id = n.application_id
        LEFT JOIN work_experience w ON a.application_id = w.application_id
        LEFT JOIN research_details r ON a.application_id = r.application_id
        LEFT JOIN faculties f ON pc.faculty = f.faculty_id
        LEFT JOIN departments d ON pc.department = d.dept_id
        LEFT JOIN degree_types dt ON pc.degree_type = dt.degree_id
        LEFT JOIN courses c ON pc.course = c.course_id
        LEFT JOIN study_modes sm ON pc.mode_of_study = sm.mode_id
        WHERE a.application_number = ?
    ");
    $stmt->execute([$appNumber]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$app && ctype_digit((string) $appNumber)) {
        $stmt = $pdo->prepare(str_replace("a.application_number = ?", "a.application_id = ?", $stmt->queryString));
        $stmt->execute([(int) $appNumber]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$app) {
        $_SESSION['error'] = "Application not found or not submitted yet.";
        header("Location: " . $backLink);
        exit();
    }

    $appId = $app['application_id'];

    // Passport Photo
    $stmt_pass = $pdo->prepare("
        SELECT file_path 
        FROM documents 
        WHERE application_id = ? 
          AND document_type IN ('passport_profile','passport') 
        ORDER BY CASE WHEN document_type = 'passport_profile' THEN 0 ELSE 1 END
        LIMIT 1
    ");
    $stmt_pass->execute([$appId]);
    $passport = $stmt_pass->fetch(PDO::FETCH_ASSOC);
    $passportUrl = (!empty($passport['file_path'])) ? resolveDocUrl($passport['file_path']) : app_url('asset/img/default-avatar.png');

    // Academic / O-Level Data
    $stmt = $pdo->prepare("SELECT * FROM olevel_exams WHERE application_id = ? ORDER BY sitting_number ASC");
    $stmt->execute([$appId]);
    $olevel_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM higher_education WHERE application_id = ?");
    $stmt->execute([$appId]);
    $education = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM referees WHERE application_id = ?");
    $stmt->execute([$appId]);
    $referees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Attached Documents
    $stmt = $pdo->prepare("
        SELECT d.*, COALESCE(dv.verification_status, 'Pending') as verification_status, dv.admin_remark 
        FROM documents d 
        LEFT JOIN document_verification dv ON d.doc_id = dv.upload_id 
        WHERE d.application_id = ? 
          AND d.document_type NOT IN ('passport_profile', 'passport')
        ORDER BY d.doc_id ASC
    ");
    $stmt->execute([$appId]);
    $uploaded_documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $deptList = [];
    try {
        $deptList = $pdo->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}

} catch (Throwable $e) {
    die("Error fetching application details: " . $e->getMessage());
}

$candidateFullName = trim(($app['title'] ?? '') . ' ' . ($app['first_name'] ?? '') . ' ' . ($app['other_name'] ?? '') . ' ' . ($app['surname'] ?? ''));
if (empty($candidateFullName)) {
    $candidateFullName = $app['full_name'] ?? 'Applicant';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Dossier - <?= htmlspecialchars($app['application_number'] ?: 'Slip') ?> - <?= htmlspecialchars($candidateFullName) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Cinzel:wght@600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <style>
        :root {
            --brand-navy: #0f172a;
            --brand-accent: #0284c7;
            --brand-gold: #b45309;
            --surface-bg: #f8fafc;
            --border-light: #cbd5e1;
        }

        body {
            background-color: var(--surface-bg);
            font-family: 'Inter', -apple-system, sans-serif;
            color: #1e293b;
            font-size: 13.5px;
            line-height: 1.5;
        }

        .pdf-canvas-container canvas {
            max-width: 100% !important;
            height: auto !important;
            margin: 10px auto;
            display: block;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #ffffff;
        }

        /* Top Action Bar */
        .dossier-topbar {
            background: #ffffff;
            border-bottom: 1px solid var(--border-light);
            padding: 12px 24px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        /* Paper Dossier Canvas */
        .dossier-container {
            max-width: 960px;
            margin: 25px auto 50px auto;
        }

        .dossier-paper {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            margin-bottom: 30px;
        }

        /* Slip Header */
        .slip-header-logo {
            max-height: 75px;
            width: auto;
        }

        .slip-uni-title {
            font-family: 'Cinzel', serif;
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: 0.5px;
            margin: 0;
            text-transform: uppercase;
        }

        .slip-center-title {
            font-size: 12.5px;
            font-weight: 700;
            color: #0369a1;
            margin: 3px 0 0 0;
        }

        .slip-doc-name {
            font-size: 15px;
            font-weight: 800;
            background: #f1f5f9;
            color: #0f172a;
            display: inline-block;
            padding: 5px 20px;
            border-radius: 20px;
            border: 1px solid #cbd5e1;
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .applicant-passport-frame {
            width: 130px;
            height: 150px;
            border: 2px solid #0f172a;
            border-radius: 6px;
            padding: 3px;
            background: #ffffff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            object-fit: cover;
        }

        /* Table & Section Headers */
        .dossier-section-title {
            font-size: 12px;
            font-weight: 700;
            background: #0f172a;
            color: #ffffff;
            padding: 6px 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 20px;
            margin-bottom: 0;
            border-radius: 4px 4px 0 0;
        }

        .table-dossier {
            width: 100%;
            margin-bottom: 0;
            border: 1px solid #cbd5e1;
            font-size: 12.5px;
        }

        .table-dossier th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            padding: 6px 10px;
            border: 1px solid #cbd5e1;
            width: 25%;
        }

        .table-dossier td {
            padding: 6px 10px;
            border: 1px solid #cbd5e1;
            color: #0f172a;
        }

        /* Attached Document Page Presentation */
        .attached-doc-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 24px;
            margin-top: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        }

        .attached-doc-banner {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .attached-doc-img {
            max-width: 100%;
            max-height: 850px;
            height: auto;
            display: block;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .attached-doc-embed {
            width: 100%;
            height: 750px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
        }

        /* ================= PRINT STYLING (ONE CONTINUOUS MULTI-PAGE STAPLE-READY DOSSIER) ================= */
        @media print {
            .no-print {
                display: none !important;
            }
            @page {
                size: A4 portrait;
                margin: 10mm 10mm 10mm 10mm;
            }
            body {
                background: #ffffff !important;
                color: #000000 !important;
                font-size: 10pt !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .dossier-container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .dossier-paper {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .attached-doc-card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                page-break-before: always !important;
                break-before: page !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            .attached-doc-banner {
                padding-bottom: 4px !important;
                margin-bottom: 8px !important;
                border-bottom: 2px solid #000000 !important;
                page-break-after: avoid !important;
                break-after: avoid !important;
            }
            .attached-doc-img, 
            .pdf-canvas-container canvas {
                max-width: 100% !important;
                max-height: 760px !important;
                width: auto !important;
                height: auto !important;
                object-fit: contain !important;
                display: block !important;
                margin: 0 auto !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            a {
                text-decoration: none !important;
                color: inherit !important;
            }
        }
    </style>
</head>
<body>

    <!-- Top Action Bar (Screen Only) -->
    <div class="dossier-topbar no-print d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <a href="<?= htmlspecialchars($backLink) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Records
            </a>
            <div>
                <strong class="text-dark fs-6"><?= htmlspecialchars($candidateFullName) ?></strong>
                <span class="text-muted ms-2">(<code><?= htmlspecialchars($app['application_number'] ?: 'N/A') ?></code>)</span>
            </div>
            <span class="badge bg-primary px-3 py-2">
                Status: <?= htmlspecialchars($app['status'] ?: 'Submitted') ?>
            </span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="#attached-docs-section" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-paperclip me-1"></i> Jump to Attachments (<?= count($uploaded_documents) ?>)
            </a>
            <button type="button" class="btn btn-success btn-sm fw-bold px-3 py-2 shadow-sm btn-dossier-print" id="btnPrintTop" style="cursor:pointer;">
                <i class="bi bi-printer-fill me-1"></i> Print Complete Dossier (Slip + Attachments)
            </button>
        </div>
    </div>

    <div class="container dossier-container">

        <!-- ================= PAGE 1 & 2: OFFICIAL APPLICATION SLIP ================= -->
        <div class="dossier-paper" id="slip-section">
            
            <!-- Slip Header -->
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?= app_url('asset/homepage/ipess_logo.png') ?>" alt="Logo" class="slip-header-logo">
                    <div>
                        <h2 class="slip-uni-title">Joseph Sarwuan Tarka University, Makurdi</h2>
                        <h4 class="slip-center-title">Center of Excellence in Sustainable Procurement, Environmental & Social Standards (CIPESS)</h4>
                        <div class="text-muted small">Postgraduate Admissions Portal &bull; Official Student Application Dossier</div>
                    </div>
                </div>
                <div class="text-end">
                    <img src="<?= htmlspecialchars($passportUrl) ?>" alt="Passport Photo" class="applicant-passport-frame">
                </div>
            </div>

            <!-- Application Meta Bar -->
            <div class="bg-light p-3 rounded border mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <span class="text-muted small text-uppercase">Application Number:</span>
                    <div class="fs-5 fw-bold text-primary font-monospace"><?= htmlspecialchars($app['application_number'] ?: 'APP/IPESS/2026/----') ?></div>
                </div>
                <div>
                    <span class="text-muted small text-uppercase">Submission Date:</span>
                    <div class="fw-semibold text-dark"><?= $app['submitted_at'] ? date('F d, Y \a\t h:i A', strtotime($app['submitted_at'])) : 'Draft / Pending' ?></div>
                </div>
                <div>
                    <span class="text-muted small text-uppercase">Admission Status:</span>
                    <div><span class="badge bg-dark px-3 py-1"><?= htmlspecialchars($app['status'] ?: 'Submitted') ?></span></div>
                </div>
            </div>

            <!-- Section 1: Programme Details -->
            <div class="dossier-section-title">1. Programme Applied For</div>
            <table class="table table-dossier table-bordered">
                <tbody>
                    <tr>
                        <th>Degree Applied:</th>
                        <td><strong class="text-primary"><?= htmlspecialchars($app['degree_name'] ?? 'Postgraduate') ?></strong></td>
                        <th>Mode of Study:</th>
                        <td><?= htmlspecialchars($app['mode_name'] ?? 'Full-Time') ?></td>
                    </tr>
                    <tr>
                        <th>Faculty / School:</th>
                        <td><?= htmlspecialchars($app['faculty_name'] ?? 'Postgraduate School') ?></td>
                        <th>Department:</th>
                        <td><?= htmlspecialchars($app['dept_name'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Course / Specialization:</th>
                        <td colspan="3"><strong class="text-dark"><?= htmlspecialchars($app['course_title'] ?? 'N/A') ?></strong></td>
                    </tr>
                </tbody>
            </table>

            <!-- Section 2: Personal Information -->
            <div class="dossier-section-title">2. Personal & Contact Information</div>
            <table class="table table-dossier table-bordered">
                <tbody>
                    <tr>
                        <th>Full Name:</th>
                        <td><strong><?= htmlspecialchars($candidateFullName) ?></strong></td>
                        <th>Gender:</th>
                        <td><?= htmlspecialchars($app['sex'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Email Address:</th>
                        <td><?= htmlspecialchars($app['user_email'] ?? $app['email'] ?? 'N/A') ?></td>
                        <th>Phone Number:</th>
                        <td><?= htmlspecialchars($app['phone'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Date of Birth:</th>
                        <td><?= !empty($app['dob']) ? date('M d, Y', strtotime($app['dob'])) : 'N/A' ?></td>
                        <th>Marital Status:</th>
                        <td><?= htmlspecialchars($app['marital_status'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>State of Origin:</th>
                        <td><?= htmlspecialchars($app['state_origin'] ?? 'N/A') ?></td>
                        <th>LGA:</th>
                        <td><?= htmlspecialchars($app['lga'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Residential Address:</th>
                        <td colspan="3"><?= htmlspecialchars($app['contact_address'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Next of Kin Name:</th>
                        <td><?= htmlspecialchars($app['next_of_kin_name'] ?? 'N/A') ?></td>
                        <th>Next of Kin Phone:</th>
                        <td><?= htmlspecialchars($app['next_of_kin_phone'] ?? 'N/A') ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Section 3: Academic Qualifications -->
            <div class="dossier-section-title">3. Higher Academic Qualifications</div>
            <table class="table table-dossier table-bordered">
                <thead>
                    <tr class="table-light">
                        <th style="width:35%">Institution</th>
                        <th style="width:30%">Qualification / Discipline</th>
                        <th style="width:15%">Class / Grade</th>
                        <th style="width:20%">Year Awarded</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($education)): ?>
                        <?php foreach ($education as $edu): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($edu['institution']) ?></strong></td>
                                <td><?= htmlspecialchars($edu['highest_qualification']) ?> (<?= htmlspecialchars($edu['discipline'] ?? '') ?>)</td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($edu['class_of_degree'] ?? $edu['cgpa'] ?? 'Passed') ?></span></td>
                                <td><?= htmlspecialchars($edu['grad_year']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center text-muted py-2">No higher education qualifications recorded.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Section 4: O'Level Examination Breakdown -->
            <div class="dossier-section-title">4. O'Level Examination Breakdown</div>
            <?php if (!empty($olevel_exams)): ?>
                <div class="row g-2 mt-1">
                    <?php foreach ($olevel_exams as $exam): 
                        try {
                            $stmt_res = $pdo->prepare("SELECT * FROM olevel_results WHERE exam_id = ?");
                            $stmt_res->execute([$exam['id']]);
                            $exam_results = $stmt_res->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Throwable $e) { $exam_results = []; }
                    ?>
                        <div class="col-md-6">
                            <table class="table table-dossier table-bordered mb-0">
                                <thead>
                                    <tr class="table-light">
                                        <th colspan="2" class="text-dark fw-bold">
                                            <?= htmlspecialchars($exam['exam_type']) ?> (<?= htmlspecialchars($exam['exam_year']) ?> - Sitting <?= $exam['sitting_number'] ?>)
                                            <span class="badge bg-dark float-end"><?= htmlspecialchars($exam['exam_number']) ?></span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exam_results as $res): ?>
                                        <tr>
                                            <td style="width:70%"><?= htmlspecialchars($res['subject_name']) ?></td>
                                            <td style="width:30%" class="fw-bold text-center"><?= htmlspecialchars($res['grade']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <table class="table table-dossier table-bordered">
                    <tr><td class="text-center text-muted py-2">No O'Level results captured.</td></tr>
                </table>
            <?php endif; ?>

            <!-- Section 5: NYSC & Employment -->
            <div class="dossier-section-title">5. NYSC Details & Employment History</div>
            <table class="table table-dossier table-bordered">
                <tbody>
                    <tr>
                        <th>NYSC Status:</th>
                        <td><?= htmlspecialchars($app['nysc_status'] ?? 'N/A') ?></td>
                        <th>Certificate No:</th>
                        <td><code><?= htmlspecialchars($app['certificate_number'] ?? 'N/A') ?></code></td>
                    </tr>
                    <tr>
                        <th>Current Employer:</th>
                        <td><?= htmlspecialchars($app['employer'] ?? 'Not Specified') ?></td>
                        <th>Position / Role:</th>
                        <td><?= htmlspecialchars($app['job_title'] ?? 'N/A') ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Section 6: Referees -->
            <div class="dossier-section-title">6. Referees Information</div>
            <table class="table table-dossier table-bordered">
                <thead>
                    <tr class="table-light">
                        <th style="width:35%">Referee Name</th>
                        <th style="width:35%">Designation / Organization</th>
                        <th style="width:30%">Contact Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($referees)): ?>
                        <?php foreach ($referees as $ref): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($ref['full_name']) ?></strong></td>
                                <td><?= htmlspecialchars($ref['organization'] ?? 'Academic/Professional') ?></td>
                                <td>
                                    <div><i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($ref['email'] ?? '') ?></div>
                                    <div><i class="bi bi-phone me-1"></i> <?= htmlspecialchars($ref['phone'] ?? '') ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="text-center text-muted py-2">No referees listed.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Section 7: Candidate Declaration -->
            <div class="dossier-section-title">7. Candidate Declaration</div>
            <div class="p-3 border border-top-0 rounded-bottom mb-3" style="font-size:11.5px; line-height: 1.6; background: #fff;">
                <p class="mb-2">
                    I hereby declare that the particulars given above and attached in this application are correct and accurate to the best of my knowledge and belief. I understand that any false statement or omission of relevant information will automatically disqualify my application or lead to revocation of admission.
                </p>
                <div class="d-flex justify-content-between align-items-end mt-4 pt-3 border-top">
                    <div>
                        <span class="text-muted small">Date Generated / Submitted:</span>
                        <div class="fw-bold"><?= date('F d, Y') ?></div>
                    </div>
                    <div class="text-center" style="width: 200px;">
                        <div class="border-bottom border-dark pb-1" style="height: 30px;"></div>
                        <span class="text-muted small">Candidate Signature</span>
                    </div>
                </div>
            </div>

        </div>


        <!-- ================= SECTION 2: ATTACHED CREDENTIALS & DOCUMENTS BINDER ================= -->
        <div id="attached-docs-section">
            
            <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                <h4 class="fw-bold text-dark mb-0">
                    <i class="bi bi-paperclip me-2 text-primary"></i> Attached Credentials & Certificates (<?= count($uploaded_documents) ?>)
                </h4>
                <span class="text-muted small">These documents will be automatically printed right after the application slip on subsequent pages.</span>
            </div>

            <?php if (!empty($uploaded_documents)): ?>
                <?php 
                $docIndex = 0;
                foreach ($uploaded_documents as $doc): 
                    $docIndex++;
                    $docTypeRaw = $doc['document_type'];
                    $docTitle = ucwords(str_replace(['_', '-'], ' ', $docTypeRaw));
                    $docUrl = resolveDocUrl($doc['file_path']);
                    $ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
                    $isPdf = ($ext === 'pdf');
                    $verificationStatus = $doc['verification_status'] ?? 'Pending';
                    $statusBadge = ($verificationStatus === 'Verified') ? 'bg-success' : (($verificationStatus === 'Rejected') ? 'bg-danger' : 'bg-warning text-dark');
                ?>

                    <!-- Each document page starts with page-break for clean single stapled binder printout -->
                    <div class="attached-doc-card page-break mb-4" style="page-break-inside: avoid !important; break-inside: avoid !important;">
                        
                        <!-- Header Banner for Attached Document -->
                        <div class="attached-doc-banner d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <span class="badge bg-dark text-uppercase mb-1">Attachment #<?= $docIndex ?></span>
                                <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($docTitle) ?></h5>
                                <div class="text-muted small" style="font-size: 11px;">
                                    File: <code><?= htmlspecialchars(basename($doc['file_path'])) ?></code> &bull; 
                                    Uploaded: <?= date('M d, Y H:i', strtotime($doc['uploaded_at'])) ?>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge <?= $statusBadge ?> px-3 py-1">
                                    <i class="bi bi-shield-check me-1"></i> <?= htmlspecialchars($verificationStatus) ?>
                                </span>
                                <a href="<?= htmlspecialchars($docUrl) ?>" target="_blank" class="btn btn-outline-secondary btn-sm no-print">
                                    <i class="bi bi-box-arrow-up-right me-1"></i> Open Original
                                </a>
                            </div>
                        </div>

                        <!-- Render Document Content (Directly grouped with banner) -->
                        <div class="text-center py-1">
                            <?php if ($isImage): ?>
                                <img src="<?= htmlspecialchars($docUrl) ?>" alt="<?= htmlspecialchars($docTitle) ?>" class="attached-doc-img">
                            <?php elseif ($isPdf): ?>
                                <!-- Inline High-Resolution PDF.js Page Canvas Renderer (Screen + Print) -->
                                <div class="pdf-canvas-container" data-pdf-url="<?= htmlspecialchars($docUrl) ?>"></div>

                                <!-- Native PDF Viewer (Fallback / Screen Interactive) -->
                                <div class="pdf-native-fallback no-print mt-2">
                                    <iframe src="<?= htmlspecialchars($docUrl) ?>" style="width:100%; height:750px; border:1px solid #cbd5e1; border-radius:6px; background:#fff;"></iframe>
                                </div>
                            <?php else: ?>
                                <div class="p-4 bg-light border rounded text-center">
                                    <i class="bi bi-file-earmark-text text-secondary fs-1 d-block mb-2"></i>
                                    <h6 class="fw-bold"><?= htmlspecialchars($docTitle) ?></h6>
                                    <p class="text-muted small mb-3">File format: .<?= htmlspecialchars($ext) ?></p>
                                    <a href="<?= htmlspecialchars($docUrl) ?>" target="_blank" class="btn btn-primary btn-sm px-4">
                                        <i class="bi bi-download me-1"></i> Download Attachment
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>

                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-light border p-4 text-center text-muted">
                    <i class="bi bi-folder-x fs-1 d-block mb-2"></i>
                    No additional supporting documents uploaded for this applicant.
                </div>
            <?php endif; ?>

        </div>

    </div>

    <!-- Floating Print Button for Fast Access -->
    <div class="position-fixed bottom-0 end-0 p-4 no-print" style="z-index: 1050;">
        <button type="button" class="btn btn-success btn-lg shadow-lg fw-bold rounded-pill px-4 py-3 btn-dossier-print" style="cursor:pointer;">
            <i class="bi bi-printer-fill me-2 fs-5"></i> Print Complete Dossier (Slip + Attachments)
        </button>
    </div>

    <script>
    // PDF.js worker setup
    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        document.querySelectorAll('.pdf-canvas-container').forEach(function(container) {
            const url = container.dataset.pdfUrl;
            if (!url) return;

            const fallback = container.parentElement.querySelector('.pdf-native-fallback');

            pdfjsLib.getDocument(url).promise.then(function(pdf) {
                // When PDF.js successfully renders pages, hide the iframe fallback so there's no duplicate
                if (fallback) {
                    fallback.style.display = 'none';
                }

                for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                    pdf.getPage(pageNum).then(function(page) {
                        const scale = 1.8; // High-resolution scale for crisp reading & printing
                        const viewport = page.getViewport({ scale: scale });
                        const canvas = document.createElement('canvas');
                        canvas.className = 'attached-doc-img pdf-page-canvas mb-3';
                        const context = canvas.getContext('2d');
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;

                        const renderContext = {
                            canvasContext: context,
                            viewport: viewport
                        };
                        page.render(renderContext);
                        container.appendChild(canvas);
                    });
                }
            }).catch(function(err) {
                console.warn('PDF.js inline rendering fallback to iframe:', err);
                if (fallback) {
                    fallback.style.display = 'block';
                }
            });
        });
    }

    function triggerPrintDossier() {
        window.focus();
        setTimeout(function() {
            try {
                window.print();
            } catch (err) {
                console.error("Print error:", err);
                document.execCommand('print', false, null);
            }
        }, 150);
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.btn-dossier-print').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                triggerPrintDossier();
            });
        });
    });
    </script>
</body>
</html>
