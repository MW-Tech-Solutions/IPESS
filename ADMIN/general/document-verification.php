<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once 'includes/db.php';

if (!has_permission('verify_applicants')) {
    http_response_code(403);
    exit('403 Forbidden: Document Verification duty not assigned.');
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userDeptId = null;
$userDeptName = '';
if ($userId > 0 && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.department_id, d.dept_name 
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.dept_id
            WHERE u.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $userDeptId = $row['department_id'] ? (int) $row['department_id'] : null;
            $userDeptName = $row['dept_name'] ?? '';
        }
    } catch (PDOException $e) {
    }
}

if (isset($_GET['ajax_fetch_applicant'])) {
    $filterStatus = $_GET['status'] ?? 'all';
    $searchTerm   = trim($_GET['search'] ?? '');
    $currentPage  = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
    if ($currentPage === false || $currentPage === null || $currentPage < 1) {
        $currentPage = 1;
    }
    $offset = ($currentPage - 1); 

    $baseJoins = "
        FROM applications a
        JOIN personal_details pd ON a.application_id = pd.application_id
        JOIN documents d ON a.application_id = d.application_id
        LEFT JOIN document_verification dv ON d.doc_id = dv.upload_id
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
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

    if ($userDeptId !== null) {
        $whereClauses[] = "(pc.department = :userDeptId OR a.department_id = :userDeptId)";
        $params[':userDeptId'] = $userDeptId;
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
                                $status_classes = ['Pending' => 'bg-warning', 'Verified' => 'bg-success', 'Re-upload Required' => 'bg-secondary'];
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
                                onclick="openVerificationModal('<?= $doc['doc_id'] ?>', '../../<?= htmlspecialchars($doc['file_path']) ?>', '<?= htmlspecialchars(ucwords(str_replace('_', ' ', $doc['document_type']))) ?>')"
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

try {
    // 1. Total Completed Today: Actions performed by admins today in their department
    $completedTodayQuery = "
        SELECT 
            SUM(CASE WHEN dv.verification_status = 'Verified' THEN 1 ELSE 0 END) AS verified,
            SUM(CASE WHEN dv.verification_status = 'Re-upload Required' THEN 1 ELSE 0 END) AS rejected,
            COUNT(dv.verification_id) AS total_actions
        FROM document_verification dv
        JOIN documents d ON d.doc_id = dv.upload_id
        JOIN applications a ON a.application_id = d.application_id
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
        WHERE DATE(dv.verified_at) = CURDATE()
    ";
    if ($userDeptId !== null) {
        $completedTodayQuery .= " AND (pc.department = $userDeptId OR a.department_id = $userDeptId)";
    }
    $compStmt = $pdo->query($completedTodayQuery);
    $compData = $compStmt->fetch(PDO::FETCH_ASSOC);

    // 2. Total Pending Workload: Documents that are still 'Pending' as of right now in their department
    $pendingQuery = "
        SELECT COUNT(d.doc_id) 
        FROM documents d
        LEFT JOIN document_verification dv ON d.doc_id = dv.upload_id
        JOIN applications a ON a.application_id = d.application_id
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
        WHERE (dv.verification_status IS NULL OR dv.verification_status = 'Pending')
    ";
    if ($userDeptId !== null) {
        $pendingQuery .= " AND (pc.department = $userDeptId OR a.department_id = $userDeptId)";
    }
    $totalPending = $pdo->query($pendingQuery)->fetchColumn();

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
$currentPage  = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
if ($currentPage === false || $currentPage === null || $currentPage < 1) {
    $currentPage = 1;
}

$baseJoins = "
    FROM applications a
    JOIN personal_details pd ON a.application_id = pd.application_id
    JOIN documents d ON a.application_id = d.application_id
    LEFT JOIN document_verification dv ON d.doc_id = dv.upload_id
    LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
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

if ($userDeptId !== null) {
    $whereClauses[] = "(pc.department = :userDeptId OR a.department_id = :userDeptId)";
    $params[':userDeptId'] = $userDeptId;
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
        $stats = $statsResult;
    }

    $countQuery = "SELECT COUNT(DISTINCT a.application_id) $baseJoins $whereSql";
    $stmtCount = $pdo->prepare($countQuery);
    foreach ($params as $key => $val) { $stmtCount->bindValue($key, $val); }
    $stmtCount->execute();
    $totalApplicants = (int) $stmtCount->fetchColumn();

} catch(PDOException $e) {
    error_log("Db error in document verification setup: " . $e->getMessage());
}
?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="kpi-card bg-white p-3 rounded shadow-sm border-0 d-flex align-items-center">
            <div class="kpi-icon bg-light-primary text-primary p-3 rounded-circle me-3"><i class="fas fa-file-alt fa-lg"></i></div>
            <div>
                <h4 class="mb-0 fw-bold"><?= number_format($stats['total']) ?></h4>
                <span class="text-muted small">Total Docs</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card bg-white p-3 rounded shadow-sm border-0 d-flex align-items-center">
            <div class="kpi-icon bg-light-warning text-warning p-3 rounded-circle me-3"><i class="fas fa-clock fa-lg"></i></div>
            <div>
                <h4 class="mb-0 fw-bold"><?= number_format($stats['pending']) ?></h4>
                <span class="text-muted small">Pending Docs</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card bg-white p-3 rounded shadow-sm border-0 d-flex align-items-center">
            <div class="kpi-icon bg-light-success text-success p-3 rounded-circle me-3"><i class="fas fa-check-circle fa-lg"></i></div>
            <div>
                <h4 class="mb-0 fw-bold"><?= number_format($stats['verified']) ?></h4>
                <span class="text-muted small">Verified Docs</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card bg-white p-3 rounded shadow-sm border-0 d-flex align-items-center">
            <div class="kpi-icon bg-light-danger text-danger p-3 rounded-circle me-3"><i class="fas fa-times-circle fa-lg"></i></div>
            <div>
                <h4 class="mb-0 fw-bold"><?= number_format($stats['rejected']) ?></h4>
                <span class="text-muted small">Rejected Docs</span>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6 col-md-12">
        <div class="panel h-100">
            <div class="panel-header d-flex justify-content-between align-items-center">
                <h3 class="panel-title mb-0">Today's Team Output</h3>
                <span class="badge bg-primary rounded-pill"><?= $today_progress['percentage'] ?>% Progress</span>
            </div>
            <div class="panel-body">
                <div class="progress mb-3" style="height: 12px; border-radius: 6px;">
                    <div class="progress-bar bg-success" style="width: <?= $today_progress['percentage'] ?>%"></div>
                </div>
                <div class="row text-center mt-3 g-2">
                    <div class="col">
                        <h5 class="fw-bold mb-0 text-success"><?= $today_progress['verified'] ?></h5>
                        <small class="text-muted text-xs">Verified Today</small>
                    </div>
                    <div class="col">
                        <h5 class="fw-bold mb-0 text-danger"><?= $today_progress['rejected'] ?></h5>
                        <small class="text-muted text-xs">Rejected Today</small>
                    </div>
                    <div class="col">
                        <h5 class="fw-bold mb-0 text-secondary"><?= $today_progress['remaining_pending'] ?></h5>
                        <small class="text-muted text-xs">Work Queue</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6 col-md-12">
        <div class="panel h-100">
            <div class="panel-header"><h3 class="panel-title mb-0">Search & Filter</h3></div>
            <div class="panel-body">
                <form method="GET" action="document-verification.php" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-sm text-muted">Status</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Documents</option>
                            <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending Verification</option>
                            <option value="verified" <?= $filterStatus === 'verified' ? 'selected' : '' ?>>Verified Documents</option>
                            <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected Documents</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-sm text-muted">Keyword Search</label>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Search applicant, app code..." value="<?= htmlspecialchars($searchTerm) ?>">
                            <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-md-7">
        <div class="panel" style="min-height: 480px;">
            <div class="panel-header d-flex justify-content-between align-items-center">
                <h3 class="panel-title mb-0">Application Documents Queue</h3>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-outline-primary" id="btn-prev" onclick="navigateApplicant(-1)"><i class="fas fa-chevron-left me-1"></i> Prev</button>
                    <span id="page-indicator" class="fw-bold text-sm bg-light px-2 py-1 rounded border">0 / 0</span>
                    <button class="btn btn-sm btn-outline-primary" id="btn-next" onclick="navigateApplicant(1)">Next <i class="fas fa-chevron-right ms-1"></i></button>
                </div>
            </div>
            <div class="panel-body" id="applicant-content-wrapper">
                <div class="text-center py-5 text-muted"><p>Select parameters or wait to load applicant queue...</p></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-5">
        <div class="panel" style="min-height: 480px;">
            <div class="panel-header"><h3 class="panel-title mb-0">Credential Document Sandbox</h3></div>
            <div class="panel-body p-0 d-flex flex-column" style="min-height:430px; position:relative;">
                <iframe id="docViewerIframe" src="about:blank" style="width: 100%; height: 420px; border: 0; border-radius: 0 0 8px 8px;"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Verification Modal -->
<div class="modal fade" id="verificationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="verifyDocTitle">Verify Credential</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="verifyDocId">
                
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted mb-1">Verify Decision</label>
                    <div class="d-grid gap-2">
                        <button class="btn btn-success" type="button" onclick="verifyDocument()"><i class="fas fa-check-circle me-1"></i> Mark Verified (Pass)</button>
                        <button class="btn btn-danger" type="button" onclick="openRejectionModal()"><i class="fas fa-times-circle me-1"></i> Mark Rejected (Fail/Re-upload)</button>
                    </div>
                </div>
                
                <div class="mb-3 border-top pt-3">
                    <label class="form-label fw-bold small text-muted mb-1" for="verifyRemarks">Audit Remark &amp; Notes</label>
                    <textarea class="form-control" id="verifyRemarks" rows="3" placeholder="Enter remarks to notify the applicant..."></textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div class="modal fade" id="rejectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold">Reject Credential</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted mb-1">Rejection Category Reason</label>
                    <select class="form-select" id="rejectionReason">
                        <option value="">Select Reason</option>
                        <option value="Blurry Image / Illegible File">Blurry Image / Illegible File</option>
                        <option value="Incorrect Document Uploaded">Incorrect Document Uploaded</option>
                        <option value="Expired Document / Outdated">Expired Document / Outdated</option>
                        <option value="Tampering Suspected">Tampering Suspected</option>
                        <option value="Name Mismatch relative to Profile">Name Mismatch relative to Profile</option>
                        <option value="Missing Signatures / Stamps">Missing Signatures / Stamps</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted mb-1" for="rejectionCommentsModal">Additional Explanation</label>
                    <textarea class="form-control" id="rejectionCommentsModal" rows="3" placeholder="Explain what the applicant needs to modify..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmRejection()">Submit Rejection</button>
            </div>
        </div>
    </div>
</div>

<script>
    let currentApplicantPage = <?= $currentPage ?>;
    let totalPages = <?= $totalApplicants ?>;
    let currentFilterStatus = '<?= $filterStatus ?>';
    let currentSearchTerm = '<?= urlencode($searchTerm) ?>';
    let selectedDocId = null;

    document.addEventListener('DOMContentLoaded', function() {
        if (totalPages > 0) {
            loadApplicantData(currentApplicantPage);
        } else {
            document.getElementById('applicant-content-wrapper').innerHTML = '<div class="text-center py-5 text-muted"><p>No applicants awaiting verification.</p></div>';
            document.getElementById('page-indicator').innerText = '0 / 0';
        }
    });

    function navigateApplicant(direction) {
        let targetPage = currentApplicantPage + direction;
        if (targetPage < 1 || targetPage > totalPages) return;
        currentApplicantPage = targetPage;
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

        const docViewerIframe = document.getElementById('docViewerIframe');
        if (filePath.endsWith('.pdf')) {
            docViewerIframe.src = `../../${filePath}`;
        } else {
            docViewerIframe.src = `../../${filePath}`;
        }
    }

    function openVerificationModal(docId, fileUrl, docType) {
        selectedDocId = docId;
        document.getElementById('verifyDocId').value = docId;
        document.getElementById('verifyDocTitle').innerText = 'Verify Decision: ' + docType;
        document.getElementById('verifyRemarks').value = '';
        
        new bootstrap.Modal(document.getElementById('verificationModal')).show();
    }

    function verifyDocument() {
        const docId = document.getElementById('verifyDocId').value;
        const comments = document.getElementById('verifyRemarks').value;
        updateDocumentStatus(docId, 'Verified', comments, 100);
        bootstrap.Modal.getInstance(document.getElementById('verificationModal')).hide();
    }

    function openRejectionModal() {
        bootstrap.Modal.getInstance(document.getElementById('verificationModal')).hide();
        document.getElementById('rejectionReason').value = '';
        document.getElementById('rejectionCommentsModal').value = '';
        new bootstrap.Modal(document.getElementById('rejectionModal')).show();
    }

    function confirmRejection() {
        const reason = document.getElementById('rejectionReason').value;
        const comments = document.getElementById('rejectionCommentsModal').value;

        if (!reason) {
            alert('Please select a rejection reason.');
            return;
        }

        let fullComment = `Reason: ${reason}. ${comments}`;
        updateDocumentStatus(selectedDocId, 'Rejected', fullComment, 0);
        bootstrap.Modal.getInstance(document.getElementById('rejectionModal')).hide();
    }

    function updateDocumentStatus(docId, status, comments, score) {
        const formData = new FormData();
        formData.append('doc_id', docId);
        formData.append('status', status);
        formData.append('comments', comments);
        formData.append('score', score);

        fetch('../api/verify_document.php', {
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
</script>

<?php require_once 'includes/footer.php'; ?>
