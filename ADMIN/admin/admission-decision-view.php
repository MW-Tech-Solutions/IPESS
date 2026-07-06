<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
session_start();
require 'db.php';
require_once __DIR__ . '/../path.php';
require_once __DIR__ . '/includes/upload_path.php';

$isEmbed = isset($_GET['embed']) && $_GET['embed'] === '1';

if (!isset($_GET['app_no'])) {
    header("Location: admission-decisions.php");
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
            COALESCE(f.faculty_name, f2.faculty_name) AS faculty_name,
            COALESCE(d.dept_name, d2.dept_name) AS dept_name,
            dt.degree_name,
            c.course_title,
            sm.mode_name
        FROM applications a
        LEFT JOIN users u ON a.user_id = u.user_id
        LEFT JOIN personal_details p ON a.application_id = p.application_id
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id AND pc.faculty > 0
        LEFT JOIN nysc_details n ON a.application_id = n.application_id
        LEFT JOIN work_experience w ON a.application_id = w.application_id
        LEFT JOIN research_details r ON a.application_id = r.application_id
        LEFT JOIN faculties f ON pc.faculty = f.faculty_id
        LEFT JOIN departments d ON pc.department = d.dept_id
        LEFT JOIN departments d2 ON d2.dept_id = COALESCE(pc.department, a.department_id)
        LEFT JOIN faculties f2 ON f2.faculty_id = d2.faculty_id
        LEFT JOIN degree_types dt ON pc.degree_type = dt.degree_id
        LEFT JOIN courses c ON pc.course = c.course_id
        LEFT JOIN study_modes sm ON pc.mode_of_study = sm.mode_id
        WHERE a.application_number = ?
    ");
    $stmt->execute([$appNumber]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$app) {
        $stmt = $pdo->prepare(str_replace("a.application_number = ?", "a.reference_number = ?", $stmt->queryString));
        $stmt->execute([$appNumber]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$app && ctype_digit((string) $appNumber)) {
        $stmt = $pdo->prepare(str_replace("a.application_number = ?", "a.application_id = ?", $stmt->queryString));
        $stmt->execute([(int) $appNumber]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);
    }

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
        header("Location: admission-decisions.php");
        exit();
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

    $stmt = $pdo->prepare("SELECT * FROM documents WHERE application_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([$appId]);
    $uploaded_documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    function table_exists_local(PDO $pdo, string $table): bool {
        try {
            $sanitizedTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $pdo->query("SELECT 1 FROM `{$sanitizedTable}` LIMIT 0");
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    function documents_verified(PDO $pdo, int $applicationId): bool {
        if (table_exists_local($pdo, 'document_verification')) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE application_id = ?");
            $stmt->execute([$applicationId]);
            $total = (int) $stmt->fetchColumn();
            if ($total === 0) {
                return false;
            }
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM documents d
                JOIN document_verification dv ON d.doc_id = dv.upload_id
                WHERE d.application_id = ? AND dv.verification_status = 'Verified'
            ");
            $stmt->execute([$applicationId]);
            $verified = (int) $stmt->fetchColumn();
            return $verified === $total;
        }

        if (table_exists_local($pdo, 'document_verifications')) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE application_id = ?");
            $stmt->execute([$applicationId]);
            $total = (int) $stmt->fetchColumn();
            if ($total === 0) {
                return false;
            }
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM documents d
                JOIN document_verifications dv ON d.doc_id = dv.doc_id
                WHERE d.application_id = ? AND dv.status = 'Verified'
            ");
            $stmt->execute([$applicationId]);
            $verified = (int) $stmt->fetchColumn();
            return $verified === $total;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE application_id = ?");
        $stmt->execute([$applicationId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    function olevel_completed(PDO $pdo, int $applicationId): bool {
        if (!table_exists_local($pdo, 'olevel_exams')) {
            return false;
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM olevel_exams WHERE application_id = ?");
        $stmt->execute([$applicationId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    function referees_responded(PDO $pdo, int $applicationId): bool {
        if (table_exists_local($pdo, 'referee_uploads')) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM referee_uploads WHERE application_id = ? AND verified_status = 'Verified'");
            $stmt->execute([$applicationId]);
            return (int) $stmt->fetchColumn() > 0;
        }

        if (table_exists_local($pdo, 'referees')) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM referees WHERE application_id = ?");
            $stmt->execute([$applicationId]);
            return (int) $stmt->fetchColumn() > 0;
        }

        return false;
    }

    $docsOk = documents_verified($pdo, $appId);
    $olevelOk = olevel_completed($pdo, $appId);
    $refOk = referees_responded($pdo, $appId);
    $isEligible = true;
    $isFinal = in_array(strtolower($app['status'] ?? ''), ['admitted', 'rejected'], true);
    $isAdmitted = strtolower($app['status'] ?? '') === 'admitted';

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
} catch (PDOException $e) {
    die("Error fetching application details: " . $e->getMessage());
}

$pageTitle = 'Admission Decision - Applicant Details';
$pageSubtitle = 'Full application details for admission decisions.';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

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
            color: var(--text-primary);
            padding-bottom: 80px;
            -webkit-font-smoothing: antialiased;
        }
        <?php if ($isEmbed): ?>
        body {
            padding-bottom: 0;
            background-color: #ffffff;
        }
        .sidebar { display: none !important; }
        .topbar { display: none !important; }
        .content-area { margin-left: 0 !important; }
        .content-body { padding: 16px !important; }
        .admin-shell { grid-template-columns: 1fr !important; }
        .embed-only { display: block !important; }
        .embed-hide { display: none !important; }
        <?php endif; ?>

        .container-xl { max-width: 1200px; }
        .card-custom {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            margin-bottom: 24px;
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

        .profile-header {
            background: white;
            border-bottom: 1px solid var(--border-color);
            padding: 20px 0;
            margin-bottom: 24px;
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

<div class="profile-header">
    <div class="container-xl">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="d-flex flex-column flex-md-row align-items-center text-center text-md-start">
                    <div class="avatar-container mb-3 mb-md-0 me-md-4">
                        <?php 
                        $passportPath = null;
                        foreach ($uploaded_documents as $doc) {
                            $docType = strtolower($doc['document_type'] ?? '');
                            if ($docType === 'passport_profile') {
                                $passportPath = app_url($doc['file_path']);
                                break;
                            }
                            if (!$passportPath && in_array($docType, ['passport', 'passport_photograph'], true)) {
                                $passportPath = app_url($doc['file_path']);
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
                            <span class="d-none d-sm-inline mx-3 text-secondary opacity-50">&bull;</span>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-envelope me-2 text-primary"></i>
                                <span class="small"><?php echo htmlspecialchars($app['user_email']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 text-md-end">
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
            <div class="card-custom">
                <div class="section-header">
                    <span class="section-title"><i class="bi bi-gavel me-2 text-muted"></i> Admission Decision</span>
                </div>
                <div class="card-body-custom d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
                    <div>
                        <div class="label-text">Eligibility Check</div>
                        <div class="value-text">Eligible for decision</div>
                    </div>
                    <div class="d-flex gap-2">
                        <form method="POST" action="includes/process_decision.php" class="decision-form">
                            <input type="hidden" name="app_id" value="<?php echo (int) $appId; ?>">
                            <input type="hidden" name="embed" value="<?php echo $isEmbed ? '1' : '0'; ?>">
                            <input type="hidden" name="decision" value="approve">
                            <button type="submit" class="btn btn-success submit-btn" <?php echo (!$isFinal) ? '' : 'disabled'; ?>>
                                <span class="btn-text"><i class="fas fa-check me-1"></i> Accept Student</span>
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            </button>
                        </form>
                        <form method="POST" action="includes/process_decision.php" class="decision-form">
                            <input type="hidden" name="app_id" value="<?php echo (int) $appId; ?>">
                            <input type="hidden" name="embed" value="<?php echo $isEmbed ? '1' : '0'; ?>">
                            <input type="hidden" name="decision" value="reject">
                            <button type="submit" class="btn btn-outline-danger submit-btn" <?php echo (!$isFinal) ? '' : 'disabled'; ?>>
                                <span class="btn-text"><i class="fas fa-times me-1"></i> Reject</span>
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            </button>
                        </form>
                        <form method="POST" action="includes/process_decision.php" class="decision-form">
                            <input type="hidden" name="app_id" value="<?php echo (int) $appId; ?>">
                            <input type="hidden" name="embed" value="<?php echo $isEmbed ? '1' : '0'; ?>">
                            <input type="hidden" name="decision" value="revoke">
                            <button type="submit" class="btn btn-outline-warning submit-btn" <?php echo $isAdmitted ? '' : 'disabled'; ?>>
                                <span class="btn-text"><i class="fas fa-undo me-1"></i> Revoke Admission</span>
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
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
                    <table class="table table-modern align-middle mb-0">
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
                                    $filePath = htmlspecialchars(app_url($doc['file_path']));
                                ?>

                                <button type="button" class="doc-item text-decoration-none border-0 bg-transparent text-start w-100" data-doc-url="<?php echo $filePath; ?>" data-doc-name="<?php echo htmlspecialchars($docTitle); ?>">
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
                        <?php if (!empty($referees)): ?>
                            <?php foreach ($referees as $ref): ?>
                                <div class="mb-3">
                                    <div class="fw-bold small"><?php echo htmlspecialchars($ref['full_name']); ?></div>
                                    <div class="text-muted small fst-italic"><?php echo htmlspecialchars($ref['organization']); ?></div>
                                    <div class="text-muted small fst-italic"><?php echo htmlspecialchars($ref['phone']); ?></div>
                                    <div class="text-muted small fst-italic"><?php echo htmlspecialchars($ref['email']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-muted small">No referees submitted.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
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

document.querySelectorAll('.decision-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const btn = this.querySelector('.submit-btn');
        const btnText = btn.querySelector('.btn-text');
        const spinner = btn.querySelector('.spinner-border');

        // Disable buttons
        document.querySelectorAll('.submit-btn').forEach(allBtn => {
            allBtn.classList.add('disabled');
            allBtn.style.pointerEvents = 'none';
            allBtn.style.opacity = '0.6';
        });

        btnText.innerHTML = "Processing...";
        if (spinner) spinner.classList.remove('d-none');

        // Build FormData
        const formData = new FormData(this);
        formData.append('ajax', '1');

        fetch(this.getAttribute('action') || 'includes/process_decision.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (spinner) spinner.classList.add('d-none');
            if (data.success) {
                btnText.innerHTML = "Completed";
                // Show success alert
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success mt-3 w-100';
                alertDiv.role = 'alert';
                alertDiv.innerHTML = `<i class="bi bi-check-circle-fill me-2"></i>${data.message}`;
                form.appendChild(alertDiv);

                setTimeout(() => {
                    if (isEmbed && window.parent) {
                        const modalEl = window.parent.document.getElementById('appViewModal');
                        if (modalEl) {
                            const modalInstance = window.parent.bootstrap.Modal.getInstance(modalEl);
                            if (modalInstance) {
                                modalInstance.hide();
                            }
                        }
                        window.parent.location.reload();
                    } else {
                        window.location.reload();
                    }
                }, 1500);
            } else {
                btnText.innerHTML = "Error";
                alert(data.message || 'Operation failed.');
                // Re-enable buttons
                document.querySelectorAll('.submit-btn').forEach(allBtn => {
                    allBtn.classList.remove('disabled');
                    allBtn.style.pointerEvents = 'auto';
                    allBtn.style.opacity = '1';
                });
            }
        })
        .catch(err => {
            console.error(err);
            if (spinner) spinner.classList.add('d-none');
            btnText.innerHTML = "Failed";
            alert("Network error occurred.");
            document.querySelectorAll('.submit-btn').forEach(allBtn => {
                allBtn.classList.remove('disabled');
                allBtn.style.pointerEvents = 'auto';
                allBtn.style.opacity = '1';
            });
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
