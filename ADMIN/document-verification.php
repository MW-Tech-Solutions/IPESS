<?php
session_start();

// Simple RBAC check
// if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
//     header('Location: /login.php'); // Redirect to login
//     exit;
// }

require_once 'includes/db.php';

if (isset($_GET['ajax_fetch_applicant'])) {
    $filterStatus = $_GET['status'] ?? 'all';
    $searchTerm   = trim($_GET['search'] ?? '');
    $currentPage  = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?? 1;
    $offset = ($currentPage - 1); 

    $baseJoins = "
        FROM applications a
        JOIN personal_details pd ON a.application_id = pd.application_id
        JOIN documents d ON a.application_id = d.application_id
        LEFT JOIN document_verification dv ON d.doc_id = dv.upload_id
    ";

    $whereClauses = [];
    $params = [];

    if ($filterStatus !== 'all') {
        $statusMap = ['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Re-upload Required'];
        if($filterStatus === 'pending') {
            $whereClauses[] = "(dv.verification_status = 'Pending' OR dv.verification_status IS NULL)";
        } elseif (isset($statusMap[$filterStatus])) {
            $whereClauses[] = "dv.verification_status = :filterStatus";
            $params[':filterStatus'] = $statusMap[$filterStatus];
        }
    }

    if (!empty($searchTerm)) {
        $whereClauses[] = "(
            d.document_type LIKE :searchTerm 
            OR a.application_number LIKE :searchTerm 
            OR pd.first_name LIKE :searchTerm 
            OR pd.surname LIKE :searchTerm
        )";
        $params[':searchTerm'] = '%' . $searchTerm . '%';
    }

    $whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

    $appIdQuery = "SELECT a.application_id $baseJoins $whereSql GROUP BY a.application_id ORDER BY MAX(d.uploaded_at) DESC LIMIT 1 OFFSET :offset";
    $stmt = $pdo->prepare($appIdQuery);
    foreach ($params as $key => $val) { $stmt->bindValue($key, $val); }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $currentAppId = $stmt->fetchColumn();

    if ($currentAppId) {
        $docsQuery = "
            SELECT 
                d.doc_id, d.document_type, d.file_path, d.uploaded_at, 
                a.application_number, pd.first_name, pd.surname,
                COALESCE(dv.verification_status, 'Pending') as status,
                dv.admin_remark as comments
            $baseJoins
            WHERE a.application_id = :appId
            AND " . ($whereSql ? str_replace('WHERE', '', $whereSql) : "1=1") . "
            ORDER BY d.uploaded_at DESC
        ";
        
        $docParams = $params;
        $docParams[':appId'] = $currentAppId;
        $stmt = $pdo->prepare($docsQuery);
        $stmt->execute($docParams);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $applicantInfo = [
            'name' => $documents[0]['first_name'] . ' ' . $documents[0]['surname'],
            'app_number' => $documents[0]['application_number']
        ];

        $totalDocs = count($documents);
        $verifiedCount = 0;
        foreach($documents as $d) {
            if($d['status'] === 'Verified') $verifiedCount++;
        }

        ?>
        <div class="applicant-header bg-light p-3 rounded mb-3 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0 text-primary fw-bold"><?= htmlspecialchars($applicantInfo['name']) ?></h4>
                    <span class="text-muted">Application No: <strong><?= htmlspecialchars($applicantInfo['app_number']) ?></strong></span>
                    <div class="mt-2">
                        <span class="badge bg-info text-dark border border-info">
                            <i class="fas fa-check-double me-1"></i> Verified: <?= $verifiedCount ?> / <?= $totalDocs ?>
                        </span>
                    </div>
                </div>
                <div class="text-end text-muted small">
                    Showing Applicant <?= $currentPage ?>
                </div>
            </div>
        </div>

        <div class="document-queue">
            <?php foreach ($documents as $doc): ?>
                <div class="document-item border rounded p-3 mb-3" data-doc-id="<?= $doc['doc_id'] ?>" data-file-path="<?= htmlspecialchars($doc['file_path']) ?>" onclick="selectDocument(this)">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-2">
                                <?php 
                                    $fileExtension = pathinfo($doc['file_path'], PATHINFO_EXTENSION);
                                    $fileIconClass = 'fas fa-file-alt'; 
                                    if (in_array(strtolower($fileExtension), ['pdf'])) $fileIconClass = 'fas fa-file-pdf text-danger';
                                    elseif (in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif'])) $fileIconClass = 'fas fa-file-image text-info';
                                ?>
                                <i class="<?= $fileIconClass ?> me-2"></i>
                                <div>
                                    <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $doc['document_type']))) ?></strong>
                                </div>
                                <?php
                                $status_classes = ['Pending' => 'bg-warning', 'Verified' => 'bg-success', 'Re-upload Required' => 'bg-danger'];
                                $status_class = $status_classes[$doc['status']] ?? 'bg-secondary';
                                $display_status = ($doc['status'] === 'Re-upload Required') ? 'Rejected' : $doc['status'];
                                ?>
                                <span class="badge <?= $status_class ?> ms-auto"><?= htmlspecialchars($display_status) ?></span>
                            </div>
                            <small class="text-muted">Uploaded: <?= date('M d, Y, h:i A', strtotime($doc['uploaded_at'])) ?></small>
                            <?php if(!empty($doc['comments'])): ?>
                                <div class="mt-1 text-muted fst-italic text-sm">
                                    <i class="fas fa-comment-alt me-1"></i> <?= htmlspecialchars(substr($doc['comments'], 0, 50)) ?>...
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="ms-3">
                            <button 
                                type="button" 
                                class="btn btn-outline-primary btn-sm"
                                onclick="openVerificationModal('<?= $doc['doc_id'] ?>', '../<?= htmlspecialchars($doc['file_path']) ?>', '<?= htmlspecialchars(ucwords(str_replace('_', ' ', $doc['document_type']))) ?>')"
                            >
                                <i class="fas fa-eye me-1"></i> View &amp; Verify
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    } else {
        echo '<div class="text-center py-5"><p class="text-muted">No applicant data found.</p></div>';
    }
    exit; 
}


require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';

$today_progress = ['total' => 0, 'verified' => 0, 'rejected' => 0, 'percentage' => 0, 'total_uploaded_today' => 0];
// try {
//     $todayStatsQuery = "
//         SELECT 
//             COUNT(verification_id) AS total_processed,
//             SUM(CASE WHEN verification_status = 'Verified' THEN 1 ELSE 0 END) AS verified,
//             SUM(CASE WHEN verification_status = 'Re-upload Required' THEN 1 ELSE 0 END) AS rejected
//         FROM document_verification
//         WHERE DATE(verified_at) = CURDATE()
//     ";
//     $todayStmt = $pdo->query($todayStatsQuery);
//     $todayRow = $todayStmt->fetch(PDO::FETCH_ASSOC);

//     $uploadedTodayQuery = "SELECT COUNT(doc_id) FROM documents WHERE DATE(uploaded_at) = CURDATE()";
//     $totalUploadedToday = $pdo->query($uploadedTodayQuery)->fetchColumn();

//     if ($todayRow) {
//         $today_progress['total'] = (int)$todayRow['total_processed'];
//         $today_progress['verified'] = (int)$todayRow['verified'];
//         $today_progress['rejected'] = (int)$todayRow['rejected'];
//         $today_progress['total_uploaded_today'] = (int)$totalUploadedToday;

//         if ($today_progress['total_uploaded_today'] > 0) {
//             $today_progress['percentage'] = round(($today_progress['total'] / $today_progress['total_uploaded_today']) * 100);
//         } else {
//             $today_progress['percentage'] = $today_progress['total'] > 0 ? 100 : 0;
//         }
//     }
// } catch (PDOException $e) { error_log("Progress Stats Error: " . $e->getMessage()); }

// logic to be integrated into document-verification.php

try {
    // 1. Total Completed Today: Actions performed by admins today
    $completedTodayQuery = "
        SELECT 
            SUM(CASE WHEN verification_status = 'Verified' THEN 1 ELSE 0 END) AS verified,
            SUM(CASE WHEN verification_status = 'Re-upload Required' THEN 1 ELSE 0 END) AS rejected,
            COUNT(verification_id) AS total_actions
        FROM document_verification
        WHERE DATE(verified_at) = CURDATE()
    ";
    $compStmt = $pdo->query($completedTodayQuery);
    $compData = $compStmt->fetch(PDO::FETCH_ASSOC);

    // 2. Total Pending Workload: Documents that are still 'Pending' as of right now
    $pendingQuery = "
        SELECT COUNT(d.doc_id) 
        FROM documents d
        LEFT JOIN document_verification dv ON d.doc_id = dv.upload_id
        WHERE dv.verification_status IS NULL OR dv.verification_status = 'Pending'
    ";
    $totalPending = $pdo->query($pendingQuery)->fetchColumn();

    // 3. Mathematical Progress Calculation
    // Progress % = (Actions Done Today) / (Actions Done Today + Remaining Pending Work)
    $actionsDoneToday = (int)$compData['total_actions'];
    $totalWorkload = $actionsDoneToday + (int)$totalPending;

    $percentage = ($totalWorkload > 0) ? round(($actionsDoneToday / $totalWorkload) * 100) : 0;

    $today_progress = [
        'total' => $actionsDoneToday,
        'verified' => (int)$compData['verified'],
        'rejected' => (int)$compData['rejected'],
        'percentage' => $percentage,
        'remaining_pending' => $totalPending
    ];
} catch (PDOException $e) {
    error_log("Senior Math Progress Error: " . $e->getMessage());
}
$filterStatus = $_GET['status'] ?? 'all';
$searchTerm   = trim($_GET['search'] ?? '');
$currentPage  = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?? 1;

$baseJoins = "
    FROM applications a
    JOIN personal_details pd ON a.application_id = pd.application_id
    JOIN documents d ON a.application_id = d.application_id
    LEFT JOIN document_verification dv ON d.doc_id = dv.upload_id
";
$whereClauses = [];
$params = [];

if ($filterStatus !== 'all') {
    $statusMap = ['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Re-upload Required'];
    if($filterStatus === 'pending') {
        $whereClauses[] = "(dv.verification_status = 'Pending' OR dv.verification_status IS NULL)";
    } elseif (isset($statusMap[$filterStatus])) {
        $whereClauses[] = "dv.verification_status = :filterStatus";
        $params[':filterStatus'] = $statusMap[$filterStatus];
    }
}
if (!empty($searchTerm)) {
    $whereClauses[] = "(d.document_type LIKE :searchTerm OR a.application_number LIKE :searchTerm OR pd.first_name LIKE :searchTerm OR pd.surname LIKE :searchTerm)";
    $params[':searchTerm'] = '%' . $searchTerm . '%';
}
$whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

$totalApplicants = 0;
$stats = ['total'=>0, 'pending'=>0, 'verified'=>0, 'rejected'=>0];

try {
    $statsQuery = "
        SELECT 
            COUNT(d.doc_id) AS total,
            SUM(CASE WHEN dv.verification_status = 'Pending' OR dv.verification_status IS NULL THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN dv.verification_status = 'Verified' THEN 1 ELSE 0 END) AS verified,
            SUM(CASE WHEN dv.verification_status = 'Re-upload Required' THEN 1 ELSE 0 END) AS rejected
        $baseJoins
        $whereSql
    ";
    $stmtStats = $pdo->prepare($statsQuery);
    foreach ($params as $key => $val) { $stmtStats->bindValue($key, $val); }
    $stmtStats->execute();
    $statsResult = $stmtStats->fetch(PDO::FETCH_ASSOC);
    if ($statsResult) {
        $stats['total'] = (int)$statsResult['total'];
        $stats['pending'] = (int)$statsResult['pending'];
        $stats['verified'] = (int)$statsResult['verified'];
        $stats['rejected'] = (int)$statsResult['rejected'];
    }

    $countQuery = "SELECT COUNT(DISTINCT a.application_id) $baseJoins $whereSql";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalApplicants = $stmt->fetchColumn();

} catch (PDOException $e) { error_log("DB Error: " . $e->getMessage()); }

$totalPages = ceil($totalApplicants / 1); 
?>

<div class="content-container">
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-warning text-white h-100 shadow-sm">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 fw-bold"><?= (int)$stats['pending'] ?></h3>
                        <small class="text-uppercase fw-semibold">Pending</small>
                    </div>
                    <i class="fas fa-clock fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-success text-white h-100 shadow-sm">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 fw-bold"><?= (int)$stats['verified'] ?></h3>
                        <small class="text-uppercase fw-semibold">Verified</small>
                    </div>
                    <i class="fas fa-check-circle fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-danger text-white h-100 shadow-sm">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 fw-bold"><?= (int)$stats['rejected'] ?></h3>
                        <small class="text-uppercase fw-semibold">Rejected</small>
                    </div>
                    <i class="fas fa-times-circle fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-info text-white h-100 shadow-sm">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 fw-bold"><?= (int)$stats['total'] ?></h3>
                        <small class="text-uppercase fw-semibold">Total Docs</small>
                    </div>
                    <i class="fas fa-file-alt fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="action-buttons mb-3">
        <button class="btn btn-primary btn-sm" onclick="refreshDocuments()">
            <i class="fas fa-sync"></i> Refresh
        </button>
        <div class="dropdown d-inline-block">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-filter"></i> Filter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item <?= ($filterStatus === 'all') ? 'active' : '' ?>" href="#" onclick="filterByStatus('all')">All Documents</a></li>
                <li><a class="dropdown-item <?= ($filterStatus === 'pending') ? 'active' : '' ?>" href="#" onclick="filterByStatus('pending')">Pending</a></li>
                <li><a class="dropdown-item <?= ($filterStatus === 'verified') ? 'active' : '' ?>" href="#" onclick="filterByStatus('verified')">Verified</a></li>
                <li><a class="dropdown-item <?= ($filterStatus === 'rejected') ? 'active' : '' ?>" href="#" onclick="filterByStatus('rejected')">Rejected</a></li>
            </ul>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Document Verification Queue</h5>
                        <div class="input-group" style="width: 250px;">
                            <input type="text" class="form-control" id="docSearchInput" placeholder="Search..." value="<?= htmlspecialchars($searchTerm) ?>">
                            <button class="btn btn-outline-secondary" type="button" onclick="performSearch()">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    
                    <div id="applicant-content-wrapper">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>

                    <nav aria-label="Applicant pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item disabled" id="btn-prev">
                                <button class="page-link" onclick="changeApplicantPage(-1)">
                                    <i class="fas fa-chevron-left"></i> Previous Applicant
                                </button>
                            </li>
                            
                            <li class="page-item disabled">
                                <span class="page-link" id="page-indicator">
                                    1 / <?= $totalPages ?>
                                </span>
                            </li>

                            <li class="page-item" id="btn-next">
                                <button class="page-link" onclick="changeApplicantPage(1)">
                                    Next Applicant <i class="fas fa-chevron-right"></i>
                                </button>
                            </li>
                        </ul>
                    </nav>
                    
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Document Preview</h5>
                </div>
                <div class="card-body">
                    <div id="documentViewer" class="text-center">
                        <div class="document-placeholder">
                            <i class="fas fa-file-pdf fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Select a document to view</p>
                        </div>
                        <iframe id="doc_viewer_iframe" style="width:100%; height: 500px; border: none; display:none;"></iframe>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
    <div class="card-header">
        <h6 class="mb-0">Today's Progress</h6>
    </div>
    <!-- <div class="card-body">
        <div class="progress mb-2" style="height: 20px;">
            <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                 role="progressbar" 
                 style="width: <?= (int)$today_progress['percentage'] ?>%;" 
                 aria-valuenow="<?= (int)$today_progress['percentage'] ?>" 
                 aria-valuemin="0" 
                 aria-valuemax="100">
                 <?= (int)$today_progress['percentage'] ?>%
            </div>
        </div>
        <small class="text-muted">
            <?= (int)$today_progress['total'] ?> of <?= (int)$today_progress['total_uploaded_today'] ?> documents uploaded today
        </small> -->
        <div class="card-body">
    <div class="progress mb-2" style="height: 20px;">
        <div class="progress-bar bg-success" style="width: <?= $today_progress['percentage'] ?>%">
            <?= $today_progress['percentage'] ?>%
        </div>
    </div>
    <small class="text-muted">
        Completed <strong><?= $today_progress['total'] ?></strong> verifications today. 
        <strong><?= $today_progress['remaining_pending'] ?></strong> documents still require attention.
    </small>
</div>
        <hr>
        <div class="row text-center">
            <div class="col-6">
                <div class="border-end">
                    <h4 class="text-success mb-0"><?= (int)$today_progress['verified'] ?></h4>
                    <small class="text-muted">Verified</small>
                </div>
            </div>
            <div class="col-6">
                <h4 class="text-danger mb-0"><?= (int)$today_progress['rejected'] ?></h4>
                <small class="text-muted">Rejected</small>
            </div>
        </div>
    </div>
</div>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Reason for Rejection</label>
                    <select class="form-select" id="rejectionReason">
                        <option value="">Select a reason...</option>
                        <option value="incomplete">Document incomplete</option>
                        <option value="illegible">Document illegible</option>
                        <option value="expired">Document expired</option>
                        <option value="invalid">Invalid document type</option>
                        <option value="forged">Suspected forgery</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Additional Comments</label>
                    <textarea class="form-control" id="rejectionCommentsModal" rows="3" placeholder="Provide detailed explanation..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmRejection()">Reject Document</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="verificationModal" tabindex="-1" aria-labelledby="verificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered"> 
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="verificationModalLabel">
                    <i class="fas fa-file-contract me-2"></i>Verify Document
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row h-100">
                    <div class="col-lg-9 border-end">
                        <div id="modalDocumentContainer" class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 70vh; overflow: hidden;">
                            <div class="document-placeholder">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <iframe id="modal_doc_viewer" style="width:100%; height: 100%; border: none; display:none;"></iframe>
                        </div>
                    </div>

                    <div class="col-lg-3 d-flex flex-column">
                        <div class="verification-panel flex-grow-1">
                            <h6 class="fw-bold border-bottom pb-2 mb-3">Verification Checklist</h6>
                            
                            <div class="verification-checklist">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="modalCheckAuthenticity">
                                    <label class="form-check-label" for="modalCheckAuthenticity">Document authenticity verified</label>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="modalCheckCompleteness">
                                    <label class="form-check-label" for="modalCheckCompleteness">All required information present</label>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="modalCheckFormat">
                                    <label class="form-check-label" for="modalCheckFormat">Correct format and quality</label>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="modalCheckExpiry">
                                    <label class="form-check-label" for="modalCheckExpiry">Document not expired</label>
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="form-label fw-bold">Comments / Remarks</label>
                                <textarea class="form-control" id="modalVerificationComments" rows="4" placeholder="Add verification notes..."></textarea>
                            </div>
                        </div>

                        <div class="mt-auto pt-3 border-top">
                            <div class="d-grid gap-2">
                                <button class="btn btn-success btn-lg" onclick="submitVerificationFromModal()">
                                    <i class="fas fa-check-circle me-2"></i>Verify Document
                                </button>
                                <button class="btn btn-outline-danger" onclick="openRejectionModalFromVerify()">
                                    <i class="fas fa-times-circle me-2"></i>Reject Document
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script>
    let currentModalDocId = null;
    let selectedDocId = null;
    let currentApplicantPage = <?= $currentPage ?>;
    const totalPages = <?= $totalPages ?>;
    const currentFilterStatus = "<?= htmlspecialchars($filterStatus) ?>";
    const currentSearchTerm = "<?= htmlspecialchars($searchTerm) ?>";

    document.getElementById('sidebar-toggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('collapsed');
        document.getElementById('main-wrapper').classList.toggle('sidebar-collapsed');
    });

    document.addEventListener('DOMContentLoaded', function() {
        loadApplicantData(currentApplicantPage);

        const filterItems = document.querySelectorAll('.dropdown-menu .dropdown-item');
        filterItems.forEach(item => {
            const onclickVal = item.getAttribute('onclick');
            if (onclickVal && onclickVal.includes(`'${currentFilterStatus}'`)) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
    });

    function changeApplicantPage(direction) {
        const newPage = currentApplicantPage + direction;
        if (newPage < 1 || newPage > totalPages) return;
        currentApplicantPage = newPage;
        loadApplicantData(currentApplicantPage);
    }

    function loadApplicantData(page) {
        const wrapper = document.getElementById('applicant-content-wrapper');
        const pageIndicator = document.getElementById('page-indicator');
        const btnPrev = document.getElementById('btn-prev');
        const btnNext = document.getElementById('btn-next');

        wrapper.style.opacity = '0.5';

        const url = new URL(window.location.origin + window.location.pathname);
        url.searchParams.set('ajax_fetch_applicant', '1');
        url.searchParams.set('page', page);
        if (currentFilterStatus !== 'all') url.searchParams.set('status', currentFilterStatus);
        if (currentSearchTerm) url.searchParams.set('search', currentSearchTerm);

        fetch(url)
            .then(response => response.text())
            .then(html => {
                wrapper.innerHTML = html;
                wrapper.style.opacity = '1';
                
                pageIndicator.innerText = `${page} / ${totalPages}`;
                if (page <= 1) btnPrev.classList.add('disabled'); else btnPrev.classList.remove('disabled');
                if (page >= totalPages) btnNext.classList.add('disabled'); else btnNext.classList.remove('disabled');
            })
            .catch(err => {
                console.error('Fetch Error:', err);
                wrapper.innerHTML = '<div class="alert alert-danger">Failed to load applicant data.</div>';
                wrapper.style.opacity = '1';
            });
    }

    function selectDocument(element) {
        selectedDocId = element.dataset.docId;
        const filePath = element.dataset.filePath;

        document.querySelectorAll('.document-item').forEach(item => item.classList.remove('selected'));
        element.classList.add('selected');

        const docViewerIframe = document.getElementById('doc_viewer_iframe');
        const placeholder = document.querySelector('.document-placeholder');

        if (filePath) {
            docViewerIframe.src = `../${filePath}`;
            docViewerIframe.style.display = 'block';
            placeholder.style.display = 'none';
        } else {
            docViewerIframe.style.display = 'none';
            placeholder.style.display = 'block';
        }
    }

    
    function openVerificationModal(docId, filePath, docTitle) {
        currentModalDocId = docId;
        document.getElementById('verificationModalLabel').innerHTML = `<i class="fas fa-file-contract me-2"></i>Verify: ${docTitle}`;

        const iframe = document.getElementById('modal_doc_viewer');
        const placeholder = document.querySelector('#modalDocumentContainer .document-placeholder');
        
        if (iframe) {
            iframe.src = filePath || 'about:blank';
            iframe.style.display = filePath ? 'block' : 'none';
        }
        if (placeholder) {
            placeholder.style.display = 'none';
        }

        document.querySelectorAll('.verification-checklist input').forEach(cb => cb.checked = false);
        document.getElementById('modalVerificationComments').value = '';

        const myModal = new bootstrap.Modal(document.getElementById('verificationModal'));
        myModal.show();
    }

    function submitVerificationFromModal() {
        if (!currentModalDocId) return;

        const checks = document.querySelectorAll('#verificationModal .verification-checklist input:checked');
        if (checks.length < 4) {
            alert('Please complete all verification checks before submitting.');
            return;
        }

        const comments = document.getElementById('modalVerificationComments').value;
        updateDocumentStatus(currentModalDocId, 'Verified', comments, checks.length);
    }

    function openRejectionModalFromVerify() {
        const verifyModalEl = document.getElementById('verificationModal');
        const verifyModal = bootstrap.Modal.getInstance(verifyModalEl);
        verifyModal.hide();

        selectedDocId = currentModalDocId; 
        const rejectModal = new bootstrap.Modal(document.getElementById('rejectionModal'));
        rejectModal.show();
    }

    function confirmRejection() {
        const reason = document.getElementById('rejectionReason').value;
        const comments = document.getElementById('rejectionCommentsModal').value;

        if (!reason) {
            alert('Please select a rejection reason.');
            return;
        }

        let fullComment = `Reason: ${reason}. ${comments}`;
        updateDocumentStatus(selectedDocId, 'Rejected', fullComment, 0); // Score 0 for rejection
        bootstrap.Modal.getInstance(document.getElementById('rejectionModal')).hide();
    }

    // --- API Call ---
    function updateDocumentStatus(docId, status, comments, score) {
        const formData = new FormData();
        formData.append('doc_id', docId);
        formData.append('status', status);
        formData.append('comments', comments);
        formData.append('score', score); // Send Score to PHP

        fetch('api/verify_document.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const verifyModal = bootstrap.Modal.getInstance(document.getElementById('verificationModal'));
                if (verifyModal) verifyModal.hide();

                loadApplicantData(currentApplicantPage);
                
            } else {
                alert('Failed to update: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred.');
        });
    }

    function refreshDocuments() {
        window.location.reload(); 
    }

    function filterByStatus(status) {
        let url = new URL(window.location.origin + window.location.pathname);
        if (status !== 'all') url.searchParams.set('status', status);
        else url.searchParams.delete('status');
        if (currentSearchTerm) url.searchParams.set('search', currentSearchTerm);
        window.location.href = url.toString();
    }

    function performSearch() {
        const newSearchTerm = document.getElementById('docSearchInput').value;
        let url = new URL(window.location.origin + window.location.pathname);
        if (currentFilterStatus && currentFilterStatus !== 'all') url.searchParams.set('status', currentFilterStatus);
        if (newSearchTerm) url.searchParams.set('search', newSearchTerm);
        else url.searchParams.delete('search');
        window.location.href = url.toString();
    }

    document.getElementById('docSearchInput').addEventListener('keypress', function(event) {
        if (event.key === 'Enter') performSearch();
    });

    const style = document.createElement('style');
    style.textContent = `
        .document-item.selected { background-color: #f0f8ff; border-color: #0d6efd !important; }
        .text-sm { font-size: 0.875rem; }
    `;
    document.head.appendChild(style);
</script>
</body>
</html>