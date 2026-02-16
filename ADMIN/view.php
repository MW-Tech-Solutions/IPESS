<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
session_start();
require 'db.php';
require_once __DIR__ . '/../path.php';
require_once __DIR__ . '/includes/upload_path.php';
require_once __DIR__ . '/../includes/status_engine.php';
require_once __DIR__ . '/../includes/permissions.php';

$isEmbed = isset($_GET['embed']) && $_GET['embed'] === '1';

if (!isset($_SESSION['role']) || !is_admin_role($_SESSION['role'])) {
    redirect_to('ADMIN/login.php');
}

// Authentication check (uncomment for production)
// if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }

if (!isset($_GET['app_no'])) {
    header("Location: application-management.php");
    exit();
}

$appNumber = $_GET['app_no'];

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
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
        LEFT JOIN nysc_details n ON a.application_id = n.application_id
        LEFT JOIN work_experience w ON a.application_id = w.application_id
        LEFT JOIN research_details r ON a.application_id = r.application_id
        -- Foreign Key Joins Added Here
        LEFT JOIN faculties f ON pc.faculty = f.faculty_id
        LEFT JOIN departments d ON pc.department = d.dept_id
        LEFT JOIN degree_types dt ON pc.degree_type = dt.degree_id
        LEFT JOIN courses c ON pc.course = c.course_id
        LEFT JOIN study_modes sm ON pc.mode_of_study = sm.mode_id
        WHERE a.application_number = ?
    ");
    $stmt->execute([$appNumber]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    $hasReferenceNumber = false;
    try {
        $hasReferenceNumber = (bool) $pdo->query("SHOW COLUMNS FROM applications LIKE 'reference_number'")->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $hasReferenceNumber = false;
    }

    if (!$app && $hasReferenceNumber) {
        $stmt = $pdo->prepare(str_replace("a.application_number = ?", "a.reference_number = ?", $stmt->queryString));
        $stmt->execute([$appNumber]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$app && ctype_digit((string) $appNumber)) {
        $stmt = $pdo->prepare(str_replace("a.application_number = ?", "a.application_id = ?", $stmt->queryString));
        $stmt->execute([(int) $appNumber]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$app) {
        $_SESSION['error'] = "Application not submitted yet.";
        header("Location: application-management.php");
        exit();
    }

    $appId = $app['application_id'];
    
    // 2. Fetch Related Data
    $stmt = $pdo->prepare("SELECT * FROM olevel_exams WHERE application_id = ? ORDER BY sitting_number ASC");
    $stmt->execute([$appId]);
    $olevel_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM higher_education WHERE application_id = ?");
    $stmt->execute([$appId]);
    $education = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM referees WHERE application_id = ?");
    $stmt->execute([$appId]);
    $referees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $deptList = [];
    try {
        $deptList = $pdo->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
    }

    // 3. Fetch Documents
    $stmt = $pdo->prepare("SELECT * FROM documents WHERE application_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([$appId]);
    $uploaded_documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Helper function for document icons
    function getFileIcon($filename) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'pdf': return 'bi-file-earmark-pdf text-danger';
            case 'jpg': 
            case 'jpeg':
            case 'png': return 'bi-file-earmark-image text-primary';
            case 'doc':
            case 'docx': return 'bi-file-earmark-word text-primary';
            default: return 'bi-file-earmark-text text-secondary';
        }
    }

    function getDocumentPath($filePath) {
        $basePath = ''; 
        if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $filePath)) {
            return $basePath . ltrim($filePath, '/');
        }
        return $filePath;
    }

} catch (PDOException $e) {
    die("Error fetching application details: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Review | <?php echo htmlspecialchars($appNumber); ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        :root {
            --bg-body: #f1f5f9; 
            --surface-card: #ffffff;
            --text-primary: #1e293b; 
            --text-secondary: #64748b; 
            --border-color: #e2e8f0; 
            --brand-primary: #4f46e5; 
            --brand-success: #10b981; 
            --brand-danger: #ef4444; 
        }

        body {
            background-color: var(--bg-body);
            font-family: 'Inter', sans-serif;
            color: var(--text-primary);
            padding-bottom: 80px;
            -webkit-font-smoothing: antialiased;
        }
        <?php if ($isEmbed): ?>
        body {
            padding-bottom: 0;
            background-color: #ffffff;
        }
        .embed-only { display: block !important; }
        .embed-hide { display: none !important; }
        <?php endif; ?>

        /* --- Layout & Cards --- */
        .container-xl { max-width: 1200px; }
        
        .card-custom {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            margin-bottom: 24px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .section-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .card-body-custom { padding: 24px; }
        /* --- Navigation Styling --- */
.back-link {
    transition: all 0.2s ease;
    font-size: 0.9rem;
}

.back-link:hover {
    color: var(--brand-primary) !important;
    transform: translateX(-4px);
}

/* Enhancing the Profile Header for better alignment with the back button */
.profile-header {
    background: white;
    border-top: 1px solid var(--border-color); /* Added top border */
    border-bottom: 1px solid var(--border-color);
    padding: 25px 0;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
/* Optimized Header & Avatar */
.profile-header {
    background: white;
    border-bottom: 1px solid var(--border-color);
    padding: 20px 0; /* Reduced padding for mobile */
    margin-bottom: 24px;
} 

.avatar-container {
    flex-shrink: 0;
}

.avatar-circle, .avatar-img {
    width: 80px;
    height: 80px;
    border-radius: 12px;
    object-fit: cover;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.8rem;
    border: 3px solid #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.avatar-circle {
    background: linear-gradient(135deg, #4f46e5 0%, #818cf8 100%);
    color: white;
}

/* Mobile specific fixes */
@media (max-width: 767.98px) {
    .profile-header { text-align: center; padding: 25px 0; }
    
    .avatar-circle, .avatar-img {
        width: 100px; /* Slightly larger on mobile for focus */
        height: 100px;
        margin: 0 auto 15px auto; /* Center avatar */
    }

    .status-container {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px dashed var(--border-color);
        width: 100%;
        text-align: center !important;
    }
    
    .status-container .label-text {
        text-align: center !important;
    }
}
        /* --- Typography Helpers --- */
        .label-text {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            font-weight: 600;
            margin-bottom: 4px;
            display: block;
        }
        
        .value-text {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-primary);
            word-wrap: break-word;
        }

        /* --- Header Profile Section --- */
        .profile-header {
            background: white;
            border-bottom: 1px solid var(--border-color);
            padding: 30px 0;
            margin-bottom: 30px;
        }
        
        
    /* Desktop adjustment for Avatar */
   
        .avatar-circle {
            width: 70px;
            height: 70px;
            font-size: 1.5rem;
    
    }
    .submit-btn {
        transition: all 0.3s ease;
        position: relative;
        min-width: 140px; /* Prevents button from shrinking when text changes */
    }

    .submit-btn .spinner-border {
        margin-left: 8px;
        vertical-align: middle;
    }

    /* Visual cue that the button is "working" */
    .submit-btn.disabled {
        cursor: not-allowed;
    }

    /* Ensure text doesn't break layout on very small screens */
    .text-truncate {
        display: inline-block;
        vertical-align: bottom;
    }
    .card-modern {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }


        /* --- Action Bar --- */
        .action-bar {
            background: #fff;
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            border-left: 5px solid var(--brand-primary);
        }
        /* Sticky Action Bar Enhancement */
.sticky-top .action-bar {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px); /* Frosted effect */
    border: 1px solid rgba(226, 232, 240, 0.8);
}

/* Ensure the button hovers nicely */
.action-bar .btn-light:hover {
    background-color: #fff;
    border-color: var(--brand-primary) !important;
    color: var(--brand-primary) !important;
}
.action-bar .btn-light:hover i {
    color: var(--brand-primary) !important;
}

        /* --- Tables --- */
        .table-modern { margin: 0; }
        .table-modern thead th {
            background-color: #f8fafc;
            color: var(--text-secondary);
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 600;
            border-bottom: 1px solid var(--border-color);
            padding: 12px 16px;
        }
        .table-modern tbody td {
            padding: 16px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        .table-modern tbody tr:last-child td { border-bottom: none; }

        /* --- Documents Grid --- */
        .doc-item {
            display: flex;
            align-items: center;
            padding: 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: #fff;
            transition: all 0.2s;
            text-decoration: none;
            color: inherit;
        }
        .doc-item:hover {
            border-color: var(--brand-primary);
            background: #fdfdff;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .doc-icon { font-size: 1.75rem; margin-right: 15px; }
        .doc-info h6 { margin: 0; font-size: 0.9rem; font-weight: 600; color: var(--text-primary); }
        .doc-info small { color: var(--text-secondary); font-size: 0.75rem; }
    </style>
</head>
<body>
<div class="container-xl mt-3">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
</div>
<body>
<div class="container-xl mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <?php if (!$isEmbed): ?>
            <a href="application-management.php" class="btn btn-white border-0 ps-0 text-secondary fw-medium d-inline-flex align-items-center back-link">
                <i class="bi bi-arrow-left me-2"></i>
                Back to Dashboard
            </a>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    </div>
<div class="profile-header">
    <div class="container-xl">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="d-flex flex-column flex-md-row align-items-center text-center text-md-start">
                    
                    <div class="avatar-container mb-3 mb-md-0 me-md-4">
                        <?php 
                        $passportPath = null;
                        foreach ($uploaded_documents as $doc) {
                            if (strtolower($doc['document_type']) === 'passport' || strtolower($doc['document_type']) === 'passport_photograph') {
                                // $passportPath = $doc['file_path'];
                                $passportPath = getPublicFilePath($doc['file_path']);
                                break;
                            }
                        }

                        if ($passportPath): ?>
                            <img src="<?php echo htmlspecialchars($passportPath); ?>" alt="Passport" class="avatar-img shadow-sm">
                        <?php else: ?>
                            <div class="avatar-circle shadow-sm">
                                <?php echo substr($app['first_name'], 0, 1) . substr($app['surname'], 0, 1); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex-grow-1">
                        <h2 class="h3 fw-bold mb-1 text-dark text-uppercase">
                            <?php echo htmlspecialchars($app['surname'] . ' ' . $app['first_name']); ?>
                        </h2>
                        
                        <div class="d-flex flex-column flex-sm-row align-items-center justify-content-center justify-content-md-start text-muted">
                            <div class="d-flex align-items-center mb-1 mb-sm-0">
                                <i class="bi bi-person-badge me-2 text-primary"></i>
                                <span class="small fw-medium"><?php echo htmlspecialchars($appNumber); ?></span>
                            </div>
                            
                            <span class="d-none d-sm-inline mx-3 text-secondary opacity-50">•</span>
                            
                            <div class="d-flex align-items-center">
                                <i class="bi bi-envelope me-2 text-primary"></i>
                                <span class="small"><?php echo htmlspecialchars($app['user_email']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 text-md-end status-container">
                <span class="label-text">Application Status</span>
                <?php 
                    $statusColor = match(strtolower($app['status'])) {
                        'submitted' => 'bg-success',
                        'admitted' => 'bg-primary',
                        'rejected' => 'bg-danger',
                        default => 'bg-secondary'
                    };
                ?>
                <span class="badge rounded-pill <?php echo $statusColor; ?> px-3 py-2 mt-1" style="font-weight: 600; font-size: 0.85rem;">
                    <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem; vertical-align: middle;"></i>
                    <?php echo ucfirst($app['status'] ?? 'Pending'); ?>
                </span>
                <div class="mt-2 text-muted small">
                    Applied: <?php echo !empty($app['submitted_at']) ? date('M d, Y', strtotime($app['submitted_at'])) : 'N/A'; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="container-xl">
    
    <div class="row mb-4">
        <div class="col-12">
            <div class="row mb-4 sticky-top" style="top: 20px; z-index: 1020;">
    <div class="col-12">
        <div class="action-bar d-flex flex-column flex-md-row justify-content-between align-items-center shadow-sm">
            <div class="d-flex align-items-center">
                <a href="application-management.php" class="btn btn-light border me-3 d-none d-md-flex align-items-center justify-content-center" style="width: 42px; height: 42px; border-radius: 10px;" title="Back to Dashboard">
                    <i class="bi bi-arrow-left text-secondary"></i>
                </a>
                
                <div>
                    <h5 class="fw-bold mb-1">Administrative Decision</h5>
                    <p class="mb-0 text-muted small">Review all documents before taking action.</p>
                </div>
            </div>
            
            <div class="d-flex gap-2 mt-3 mt-md-0">
                <button type="button" class="btn btn-outline-primary px-4" data-bs-toggle="modal" data-bs-target="#assignDeptModal">
                    <i class="bi bi-diagram-3 me-2"></i>Assign to Department
                </button>
                <form method="POST" action="./includes/process_decision.php" class="decision-form">
                    <input type="hidden" name="app_id" value="<?php echo $appId; ?>">
                    <input type="hidden" name="decision" value="reject">
                    <button type="submit" class="btn btn-outline-danger px-4 submit-btn">
                        <span class="btn-text"><i class="bi bi-x-circle me-2"></i>Reject</span>
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </form>

                <form method="POST" action="./includes/process_decision.php" class="decision-form">
                    <input type="hidden" name="app_id" value="<?php echo $appId; ?>">
                    <input type="hidden" name="decision" value="admit">
                    <button type="submit" class="btn btn-primary px-4 submit-btn" style="background-color: var(--brand-primary); border: none;">
                        <span class="btn-text"><i class="bi bi-check-circle me-2"></i>Admit Applicant</span>
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            
            <!-- <div class="card-custom">
                <div class="section-header">
                    <span class="section-title"><i class="bi bi-person-lines-fill me-2 text-muted"></i> Personal & Programme Details</span>
                </div>
                <div class="card-body-custom">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <span class="label-text">Applied Degree</span>
                            <div class="value-text fs-5"><?php echo htmlspecialchars($app['degree_type']); ?></div>
                            <div class="text-muted small mt-1"><?php echo htmlspecialchars($app['course']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <span class="label-text">Mode of Study</span>
                            <div class="value-text"><?php echo htmlspecialchars($app['mode_of_study']); ?></div>
                        </div>
                        <div class="col-12"><hr class="text-muted opacity-25"></div>
                        <div class="col-md-4">
                            <span class="label-text">Gender</span>
                            <div class="value-text"><?php echo htmlspecialchars($app['sex']); ?></div>
                        </div>
                        <div class="col-md-4">
                            <span class="label-text">Phone Number</span>
                            <div class="value-text"><?php echo htmlspecialchars($app['phone']); ?></div>
                        </div>
                        <div class="col-md-4">
                            <span class="label-text">Origin</span>
                            <div class="value-text"><?php echo htmlspecialchars($app['state_origin']); ?></div>
                        </div>
                    </div>
                </div>
            </div> -->
            <div class="card-custom">
                <div class="section-header">
                    <span class="section-title"><i class="bi bi-person-lines-fill me-2 text-muted"></i> Personal & Programme Details</span>
                </div>
                <div class="card-body-custom">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <span class="label-text">Applied Degree</span>
                            <div class="value-text fs-5"><?php echo htmlspecialchars($app['degree_name'] ?? 'N/A'); ?></div>
                            <div class="text-muted small mt-1">
                                <?php echo htmlspecialchars($app['dept_name'] ?? 'Unknown Dept'); ?> - 
                                <?php echo htmlspecialchars($app['course_title'] ?? 'Unknown Course'); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <span class="label-text">Mode of Study</span>
                            <div class="value-text"><?php echo htmlspecialchars($app['mode_name'] ?? 'N/A'); ?></div>
                            
                            <div class="mt-3">
                                <span class="label-text">Faculty</span>
                                <div class="value-text"><?php echo htmlspecialchars($app['faculty_name'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                        <div class="col-12"><hr class="text-muted opacity-25"></div>
                        <div class="col-md-4">
                            <span class="label-text">Gender</span>
                            <div class="value-text"><?php echo htmlspecialchars($app['sex']); ?></div>
                        </div>
                        <div class="col-md-4">
                            <span class="label-text">Phone Number</span>
                            <div class="value-text"><?php echo htmlspecialchars($app['phone']); ?></div>
                        </div>
                        <div class="col-md-4">
                            <span class="label-text">Origin</span>
                            <div class="value-text"><?php echo htmlspecialchars($app['state_origin']); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-custom">
                <div class="section-header">
                    <span class="section-title"><i class="bi bi-mortarboard me-2 text-muted"></i> Academic History</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-modern align-middle mb-0 ">
                        <thead>
                            <tr>
                                <th>Institution</th>
                                <th>Qualification</th>
                                <th>Year</th>
                                <th class="text-end">CGPA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($education) > 0): ?>
                                <?php foreach ($education as $edu): ?>
                                <tr>
                                    <td class="fw-medium"><?php echo htmlspecialchars($edu['institution']); ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($edu['highest_qualification']); ?></span></td>
                                    <td class="text-muted"><?php echo htmlspecialchars($edu['grad_year']); ?></td>
                                    <td class="text-end fw-bold"><?php echo htmlspecialchars($edu['cgpa']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No education history recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-custom">
                <div class="section-header">
                    <span class="section-title"><i class="bi bi-journal-text me-2 text-muted"></i> O-Level Results</span>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <?php foreach ($olevel_exams as $exam): 
                             // Fetch results logic kept inline for simplicity
                             try {
                                $stmt_res = $pdo->prepare("SELECT * FROM olevel_results WHERE exam_id = ?");
                                $stmt_res->execute([$exam['id']]);
                                $exam_results = $stmt_res->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Exception $e) { $exam_results = []; }
                        ?>
                        <div class="col-md-6">
                            <div class="border rounded p-3 bg-light h-100">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($exam['exam_type']); ?></div>
                                        <div class="small text-muted"><?php echo $exam['exam_year']; ?> &bull; Sitting <?php echo $exam['sitting_number']; ?></div>
                                    </div>
                                    <span class="badge bg-dark"><?php echo htmlspecialchars($exam['exam_number']); ?></span>
                                </div>
                                <hr class="my-2">
                                <table class="table table-sm table-borderless mb-0 w-100">
                                    <?php foreach ($exam_results as $res): ?>
                                    <tr>
                                        <td class="ps-0 py-1 small"><?php echo htmlspecialchars($res['subject_name']); ?></td>
                                        <td class="pe-0 py-1 text-end fw-bold small"><?php echo htmlspecialchars($res['grade']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

             <div class="card-custom">
                <div class="section-header">
                    <span class="section-title"><i class="bi bi-lightbulb me-2 text-muted"></i> Research Proposal</span>
                </div>
                <div class="card-body-custom">
                    <span class="label-text">Proposed Topic</span>
                    <p class="mb-0 mt-2" style="line-height: 1.6; color: var(--text-primary);">
                        <?php echo htmlspecialchars($app['research_area'] ?: 'No research topic provided.'); ?>
                    </p>
                </div>
            </div>

        </div>

        <div class="col-lg-4">
            
            <div class="card-custom border-primary border-opacity-25" style="border-top-width: 4px;">
                <div class="section-header bg-white">
                    <span class="section-title text-primary"><i class="bi bi-folder2-open me-2"></i> Verification Documents</span>
                </div>
                <div class="card-body-custom p-3 bg-light">
<?php if (!empty($uploaded_documents)): ?>
    <div class="d-flex flex-column gap-2">

        <?php foreach ($uploaded_documents as $doc): ?>
            <?php
                $docTitle   = ucwords(str_replace('_', ' ', $doc['document_type']));
                $filename   = basename($doc['file_path']);
                $fileIcon   = getFileIcon($filename);
                $uploadDate = date('M d', strtotime($doc['uploaded_at']));

                // FIX: resolve file from one level up
                $filePath = htmlspecialchars(getPublicFilePath($doc['file_path']));
            ?>

              <button
                  type="button"
                  class="doc-item text-decoration-none border-0 bg-transparent text-start w-100"
                  data-doc-url="<?php echo $filePath; ?>"
                  data-doc-name="<?php echo htmlspecialchars($docTitle); ?>"
              >
                  <i class="<?php echo $fileIcon; ?> doc-icon"></i>

                <div class="doc-info w-100">
                    <div class="d-flex justify-content-between">
                        <h6 class="mb-0"><?php echo htmlspecialchars($docTitle); ?></h6>
                    </div>

                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-truncate" style="max-width:150px;">
                            <?php echo htmlspecialchars($filename); ?>
                        </small>
                        <small class="text-primary">View File</small>
                    </div>
                </div>
              </button>

        <?php endforeach; ?>

    </div>
<?php else: ?>
    <div class="text-center py-4 text-muted">
        <i class="bi bi-folder-x fs-1 mb-2 d-block"></i>
        No documents uploaded.
    </div>
<?php endif; ?>
</div>

            </div>
            <?php if ($isEmbed): ?>
                <div class="card-custom border-0 shadow-sm embed-only" id="docInlinePreview" style="display:none;">
                    <div class="section-header">
                        <span class="section-title">Document Preview</span>
                    </div>
                    <div class="card-body-custom p-0" style="min-height:60vh;">
                        <img id="docInlineImage" src="" alt="Document preview" style="display:none; width:100%; height:auto;">
                        <iframe id="docInlineFrame" src="about:blank" style="display:none; width:100%; height:60vh; border:0;"></iframe>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card-custom">
                <div class="section-header">
                    <span class="section-title">Additional Context</span>
                </div>
                <div class="card-body-custom">
                    
                    <div class="mb-4">
                        <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">NYSC Status</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Status</span>
                            <span class="fw-medium text-end"><?php echo htmlspecialchars($app['nysc_status']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Cert No.</span>
                            <span class="fw-medium text-end font-monospace small bg-light px-2 rounded"><?php echo htmlspecialchars($app['certificate_number'] ?: 'N/A'); ?></span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Experience</h6>
                        <span class="label-text">Current Employer</span>
                        <div class="value-text mb-2"><?php echo htmlspecialchars($app['employer'] ?: 'Not Specified'); ?></div>
                        
                        <span class="label-text">Role</span>
                        <div class="value-text"><?php echo htmlspecialchars($app['job_title'] ?: '-'); ?></div>
                    </div>

                    <div>
                        <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Referees</h6>
                        <?php foreach ($referees as $ref): ?>
                            <div class="mb-3">
                                <div class="fw-bold small"><?php echo htmlspecialchars($ref['full_name']); ?></div>
                                <div class="text-muted small fst-italic"><?php echo htmlspecialchars($ref['organization']); ?></div>
                                <!-- <a href="tel:<?php echo htmlspecialchars($ref['phone']); ?>" class="small text-decoration-none me-2"><i class="bi bi-telephone"></i> Call</a>
                                <a href="mailto:<?php echo htmlspecialchars($ref['email']); ?>" class="small text-decoration-none"><i class="bi bi-envelope"></i> Email</a> -->
                               <div class="text-muted small fst-italic"><?php echo htmlspecialchars($ref['phone']); ?></div>
                               <div class="text-muted small fst-italic"><?php echo htmlspecialchars($ref['email']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

     </div>
</div>
<script>
document.querySelectorAll('.decision-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        // Find the button inside the current form
        const btn = this.querySelector('.submit-btn');
        const btnText = btn.querySelector('.btn-text');
        const spinner = btn.querySelector('.spinner-border');

        // 1. Disable all buttons in the action bar to prevent double clicks
        document.querySelectorAll('.submit-btn').forEach(allBtn => {
            allBtn.classList.add('disabled');
            allBtn.style.pointerEvents = 'none';
            allBtn.style.opacity = '0.7';
        });

        // 2. Change the clicked button state
        btnText.innerHTML = "Processing..."; // Optional: Change text
        spinner.classList.remove('d-none'); // Show the loading spinner
        
        // Form continues to process_decision.php automatically
    });
});
</script>

<div class="modal fade" id="assignDeptModal" tabindex="-1" aria-labelledby="assignDeptModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="./includes/assign_department.php">
            <div class="modal-header">
                <h5 class="modal-title" id="assignDeptModalLabel">Assign Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="application_id" value="<?php echo (int) $appId; ?>">
                <input type="hidden" name="app_no" value="<?php echo htmlspecialchars($appNumber); ?>">
                <div class="mb-3">
                    <label class="form-label">Select Department</label>
                    <select class="form-select" name="department_id" required>
                        <option value="">Select Department</option>
                        <?php foreach ($deptList as $dept): ?>
                            <option value="<?php echo (int) $dept['dept_id']; ?>" <?php echo ((int) $dept['dept_id'] === (int) ($app['department_id'] ?? 0)) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['dept_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Note (optional)</label>
                    <textarea class="form-control" name="note" rows="3" placeholder="Add a note for the department."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Assign Department</button>
            </div>
        </form>
    </div>
</div>
<?php if (!$isEmbed): ?>
<div class="modal fade" id="docPreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="docPreviewTitle">Document Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0" style="min-height:70vh;">
        <img id="docPreviewImage" src="" alt="Document preview" style="display:none; width:100%; height:auto;">
        <iframe id="docPreviewFrame" src="about:blank" style="display:none; width:100%; height:70vh; border:0;"></iframe>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!$isEmbed): ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>
<script>
const isEmbed = <?php echo $isEmbed ? 'true' : 'false'; ?>;
document.querySelectorAll('[data-doc-url]').forEach(btn => {
    btn.addEventListener('click', () => {
        const url = btn.dataset.docUrl || '';
        const name = btn.dataset.docName || 'Document Preview';
        const img = isEmbed ? document.getElementById('docInlineImage') : document.getElementById('docPreviewImage');
        const frame = isEmbed ? document.getElementById('docInlineFrame') : document.getElementById('docPreviewFrame');

        if (!isEmbed) {
            const title = document.getElementById('docPreviewTitle');
            title.textContent = name;
        }

        if (img) { img.style.display = 'none'; img.src = ''; }
        if (frame) { frame.style.display = 'none'; frame.src = 'about:blank'; }

        const lower = url.toLowerCase();
        if (lower.endsWith('.jpg') || lower.endsWith('.jpeg') || lower.endsWith('.png') || lower.endsWith('.gif') || lower.endsWith('.webp')) {
            if (img) { img.src = url; img.style.display = 'block'; }
        } else {
            if (frame) { frame.src = url; frame.style.display = 'block'; }
        }

        if (isEmbed) {
            const panel = document.getElementById('docInlinePreview');
            if (panel) {
                panel.style.display = 'block';
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        } else if (typeof bootstrap !== 'undefined') {
            const modal = new bootstrap.Modal(document.getElementById('docPreviewModal'));
            modal.show();
        }
    });
});
</script>
</body>
</html>
