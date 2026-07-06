<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Database Connection
require_once 'db.php'; 
require_once __DIR__ . '/../includes/referee_service.php';
require_once __DIR__ . '/../includes/status_engine.php';
require_once __DIR__ . '/../includes/completion_service.php';

// --- UNIFIED POST HANDLER (Notify, Verify, Acknowledge) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $refId = filter_input(INPUT_POST, 'referee_id', FILTER_VALIDATE_INT);
    $currentPageNum = filter_input(INPUT_POST, 'current_page', FILTER_VALIDATE_INT) ?? 1;

    if ($refId) {
        // 1. Verify Action
        if ($action === 'verify') {
            $stmtApp = $pdo->prepare("SELECT application_id FROM referees WHERE referee_id = ?");
            $stmtApp->execute([$refId]);
            $appId = $stmtApp->fetchColumn();

            if ($appId) {
                require_once __DIR__ . '/../classes/ApplicationProgressManager.php';
                $progManager = new ApplicationProgressManager($pdo);
                if (!$progManager->isStageCompleted((int) $appId, ApplicationProgressManager::STAGE_DOC_VERIFY)) {
                    $_SESSION['message'] = "Cannot verify referee reports before Documents Verification is completed.";
                    $_SESSION['msg_type'] = "danger";
                    header("Location: referees.php?page=" . $currentPageNum);
                    exit;
                }

                verify_referee_submission($pdo, $refId, $_SESSION['user_id'] ?? 0, 'Verified', null);
                update_completion($pdo, (int) $appId);

                // Update Application Progress
                $stmtCheck = $pdo->prepare("SELECT progress_id FROM application_progress WHERE application_id = ? AND stage = 'Referee Report'");
                $stmtCheck->execute([$appId]);
                $existingProgress = $stmtCheck->fetch();

                if ($existingProgress) {
                    $stmtProgress = $pdo->prepare("UPDATE application_progress SET stage_status = 'Completed', stage_updated_at = NOW() WHERE application_id = ? AND stage = 'Referee Report'");
                    $stmtProgress->execute([$appId]);
                } else {
                    $stmtProgress = $pdo->prepare("INSERT INTO application_progress (application_id, stage, stage_status, stage_updated_at) VALUES (?, 'Referee Report', 'Completed', NOW())");
                    $stmtProgress->execute([$appId]);
                }

                $_SESSION['message'] = "Referee verified and application progress updated.";
                $_SESSION['msg_type'] = "success";
            }
        }
        // 2. Acknowledge Action
        elseif ($action === 'acknowledge') {
            $stmt = $pdo->prepare("SELECT r.full_name as ref_name, r.email as ref_email, pd.first_name, pd.surname FROM referees r JOIN applications a ON r.application_id = a.application_id JOIN personal_details pd ON a.application_id = pd.application_id WHERE r.referee_id = ?");
            $stmt->execute([$refId]);
            $data = $stmt->fetch();

            if ($data) {
                if (table_exists($pdo, 'referee_uploads')) {
                    $update = $pdo->prepare("UPDATE referee_uploads SET verified_status = 'Received' WHERE referee_id = ?");
                    $update->execute([$refId]);
                }

                require_once 'includes/RefereeMailer.php';
                $appFullName = $data['first_name'] . ' ' . $data['surname'];
                $mailResult = RefereeMailer::sendAcknowledgment($data['ref_email'], $data['ref_name'], $appFullName);

                if ($mailResult['success']) {
                    $_SESSION['message'] = "Status updated and acknowledgment email sent.";
                    $_SESSION['msg_type'] = "success";
                } else {
                    $_SESSION['message'] = "Status updated, but email failed: " . $mailResult['message'];
                    $_SESSION['msg_type'] = "warning";
                }
            }
        }
        // 3. Notify Action
        elseif ($action === 'notify') {
            $stmt = $pdo->prepare("SELECT r.full_name as ref_name, r.email as ref_email, a.application_id, pd.first_name, pd.surname, pd.phone, acc.email as app_email FROM referees r JOIN applications a ON r.application_id = a.application_id JOIN personal_details pd ON a.application_id = pd.application_id JOIN applicant_accounts acc ON a.user_id = acc.user_id WHERE r.referee_id = ?");
            $stmt->execute([$refId]);
            $data = $stmt->fetch();

            if ($data) {
                $applicantName = $data['first_name'] . ' ' . $data['surname'];
                $request = create_referee_request($pdo, $refId, (int) $data['application_id'], $_SESSION['user_id'] ?? null);
                $protocol = function_exists('is_secure_connection') ? (is_secure_connection() ? "https://" : "http://") : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://");
                $vLink = $protocol . $_SERVER['HTTP_HOST'] . "/referee_verify.php?token=" . urlencode($request['token']);

                $sent = send_referee_request_email($pdo, $refId, $vLink);
                $_SESSION['message'] = $sent ? "Notification sent successfully." : "Mail Error: unable to send.";
                $_SESSION['msg_type'] = $sent ? "success" : "danger";
            }
        }
    }
    header("Location: referees.php?page=" . $currentPageNum);
    exit;
}

// --- AJAX HANDLER: FETCH APPLICANT & REFEREES ---
if (isset($_GET['ajax_fetch_applicant'])) {
    $filterStatus = $_GET['status'] ?? 'all';
    $searchTerm   = trim($_GET['search'] ?? '');
    $currentPage  = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?? 1;
    $offset       = ($currentPage - 1); 

    $baseJoins = " FROM applications a JOIN personal_details pd ON a.application_id = pd.application_id JOIN referees r ON a.application_id = r.application_id LEFT JOIN referee_uploads rs ON r.referee_id = rs.referee_id ";

    $whereClauses = [];
    $params = [];

    if ($filterStatus !== 'all') {
        if($filterStatus === 'verified') $whereClauses[] = "rs.submission_status = 'Verified'";
        elseif ($filterStatus === 'pending') $whereClauses[] = "(rs.submission_status != 'Verified' OR rs.submission_status IS NULL)";
    }

    if (!empty($searchTerm)) {
        $whereClauses[] = "(pd.first_name LIKE :searchTerm OR pd.surname LIKE :searchTerm OR a.application_number LIKE :searchTerm OR r.full_name LIKE :searchTerm OR r.email LIKE :searchTerm)";
        $params[':searchTerm'] = '%' . $searchTerm . '%';
    }

    $whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

    $appIdQuery = "SELECT a.application_id $baseJoins $whereSql GROUP BY a.application_id ORDER BY a.application_id DESC LIMIT 1 OFFSET :offset";
    $stmt = $pdo->prepare($appIdQuery);
    foreach ($params as $key => $val) { $stmt->bindValue($key, $val); }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $currentAppId = $stmt->fetchColumn();

    if ($currentAppId) {
        $refQuery = "SELECT r.referee_id, r.full_name, r.title, r.organization, r.email, r.phone, a.application_number, pd.first_name, pd.surname, COALESCE(rs.verified_status, 'Not Submitted') as status, rs.submitted_at as received_at, rs.work_id_path as id_path FROM referees r JOIN applications a ON r.application_id = a.application_id JOIN personal_details pd ON a.application_id = pd.application_id LEFT JOIN referee_uploads rs ON r.referee_id = rs.referee_id WHERE a.application_id = :appId";
        
        $stmt = $pdo->prepare($refQuery);
        $stmt->execute([':appId' => $currentAppId]);
        $referees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $applicantInfo = ['name' => $referees[0]['first_name'] . ' ' . $referees[0]['surname'], 'app_number' => $referees[0]['application_number']];
        $totalRefs = count($referees);
        $verifiedCount = 0;
        foreach($referees as $r) { if($r['status'] === 'Verified') $verifiedCount++; }
        ?>
        <div class="applicant-header bg-light p-3 rounded mb-3 border-start border-4 border-primary shadow-sm">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0 text-primary fw-bold"><?= htmlspecialchars($applicantInfo['name']) ?></h5>
                    <span class="text-muted small">App No: <strong><?= htmlspecialchars($applicantInfo['app_number']) ?></strong></span>
                    <div class="mt-1">
                        <span class="badge bg-info text-dark border border-info">
                            Verified: <?= $verifiedCount ?> / <?= $totalRefs ?>
                        </span>
                    </div>
                </div>
                <div class="text-end text-muted small">Applicant <?= $currentPage ?></div>
            </div>
        </div>

        <div class="document-queue">
            <?php foreach ($referees as $ref): 
                $jsonData = htmlspecialchars(json_encode($ref), ENT_QUOTES, 'UTF-8');
            ?>
                <div class="document-item border rounded p-3 mb-3 bg-white" 
                     onclick='selectReferee(this, <?= $jsonData ?>)'
                     style="cursor: pointer;">
                    
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-user-tie text-primary me-2"></i>
                                <div class="fw-bold"><?= htmlspecialchars($ref['full_name']) ?></div>
                                <?php
                                $status_classes = ['Not Submitted' => 'bg-secondary', 'Submitted' => 'bg-info', 'Received' => 'bg-primary', 'Verified' => 'bg-success'];
                                $status_class = $status_classes[$ref['status']] ?? 'bg-secondary';
                                ?>
                                <span class="badge <?= $status_class ?> ms-auto small"><?= htmlspecialchars($ref['status']) ?></span>
                            </div>
                            <div class="small text-muted"><i class="fas fa-building me-1"></i> <?= htmlspecialchars($ref['organization']) ?></div>
                            <div class="small text-muted"><i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($ref['email']) ?></div>
                        </div>
                        <div class="ms-3">
                             <button type="button" class="btn btn-outline-primary btn-sm" 
                                onclick='openVerificationModal(<?= $jsonData ?>); event.stopPropagation();'>
                                 <i class="fas fa-eye me-1"></i> View &amp; Verify
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    } else {
        echo '<div class="text-center py-5 text-muted"><i class="fas fa-search fa-2x mb-2 opacity-25"></i><p>No applicant data found matching criteria.</p></div>';
    }
    exit; 
}

// --- MAIN PAGE LOAD ---
$stats = ['total'=>0, 'verified'=>0, 'pending'=>0];
try {
    // $statsQuery = "SELECT COUNT(*) as total, SUM(CASE WHEN submission_status = 'Verified' THEN 1 ELSE 0 END) as verified, SUM(CASE WHEN submission_status != 'Verified' OR submission_status IS NULL THEN 1 ELSE 0 END) as pending FROM referees r LEFT JOIN referee_status rs ON r.referee_id = rs.referee_id";
    // $stats = $pdo->query($statsQuery)->fetch(PDO::FETCH_ASSOC);
    $statsQuery = "SELECT 
        COUNT(*) as total, 
        SUM(CASE WHEN rs.verified_status = 'Verified' THEN 1 ELSE 0 END) as verified, 
        SUM(CASE WHEN rs.verified_status = 'Submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN rs.verified_status != 'Verified' OR rs.verified_status IS NULL THEN 1 ELSE 0 END) as pending 
        FROM referees r LEFT JOIN referee_uploads rs ON r.referee_id = rs.referee_id";
    
    $stats = $pdo->query($statsQuery)->fetch(PDO::FETCH_ASSOC);
    $countQuery = "SELECT COUNT(DISTINCT application_id) FROM referees";
    $totalApplicants = $pdo->query($countQuery)->fetchColumn();
    $totalPages = ceil($totalApplicants / 1);
} catch (PDOException $e) { error_log("Stats Error: " . $e->getMessage()); }

// --- INCLUDE TEMPLATES CORRECTLY ---
require_once 'includes/header.php'; // Opens #main-wrapper
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php'; // Opens .main-content
?>

<style>
    /* Local Styles Only */
    .document-item.selected { background-color: #f0f7ff; border-left: 4px solid #0d6efd !important; }
    .document-item:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: all 0.2s; }
</style>

<div class="container-fluid px-4 py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark">Referee Management</h2>
        <div class="d-flex gap-2">
            <button class="btn btn-primary shadow-sm btn-sm" onclick="location.reload()">
                <i class="fas fa-sync me-2"></i> Refresh
            </button>
        </div>
    </div>

    <!-- <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card bg-warning text-white h-100 shadow-sm border-0">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 fw-bold"><?= (int)$stats['pending'] ?></h3>
                        <small class="text-white-50 text-uppercase fw-bold">Pending Verification</small>
                    </div>
                    <i class="fas fa-clock fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white h-100 shadow-sm border-0">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 fw-bold"><?= (int)$stats['verified'] ?></h3>
                        <small class="text-white-50 text-uppercase fw-bold">Verified Referees</small>
                    </div>
                    <i class="fas fa-check-circle fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-primary text-white h-100 shadow-sm border-0">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 fw-bold"><?= (int)$stats['total'] ?></h3>
                        <small class="text-white-50 text-uppercase fw-bold">Total Referees</small>
                    </div>
                    <i class="fas fa-users fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div> -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-warning text-white h-100 shadow-sm border-0">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 fw-bold"><?= (int)$stats['pending'] ?></h3>
                        <small class="text-white-50 text-uppercase fw-bold">Pending Verification</small>
                    </div>
                    <i class="fas fa-clock fa-2x opacity-50"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-info text-white h-100 shadow-sm border-0">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 fw-bold"><?= (int)$stats['submitted'] ?></h3>
                        <small class="text-white-50 text-uppercase fw-bold">Submitted / Review</small>
                    </div>
                    <i class="fas fa-file-upload fa-2x opacity-50"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-success text-white h-100 shadow-sm border-0">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 fw-bold"><?= (int)$stats['verified'] ?></h3>
                        <small class="text-white-50 text-uppercase fw-bold">Verified Referees</small>
                    </div>
                    <i class="fas fa-check-circle fa-2x opacity-50"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-primary text-white h-100 shadow-sm border-0">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 fw-bold"><?= (int)$stats['total'] ?></h3>
                        <small class="text-white-50 text-uppercase fw-bold">Total Referees</small>
                    </div>
                    <i class="fas fa-users fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">Referee List</h5>
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-sm" style="width: 120px;" onchange="filterByStatus(this.value)">
                            <option value="all">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="verified">Verified</option>
                        </select>
                        <div class="input-group input-group-sm" style="width: 200px;">
                            <input type="text" class="form-control" id="searchInput" placeholder="Search...">
                            <button class="btn btn-outline-secondary" onclick="performSearch()"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="applicant-content-wrapper" style="min-height: 300px;">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                        </div>
                    </div>

                    <nav class="mt-4 pt-2 border-top">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item" id="btn-prev">
                                <button class="page-link border-0" onclick="changePage(-1)">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </button>
                            </li>
                            <li class="page-item disabled">
                                <span class="page-link border-0 fw-bold" id="page-indicator">1 / <?= $totalPages ?></span>
                            </li>
                            <li class="page-item" id="btn-next">
                                <button class="page-link border-0" onclick="changePage(1)">
                                    Next <i class="fas fa-chevron-right"></i>
                                </button>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">Referee Details</h5>
                </div>
                <div class="card-body" id="detailViewContainer">
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-user-tie fa-4x mb-3 opacity-25"></i>
                        <p>Select a referee from the list to view full details.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="verificationModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered"> 
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" id="modalVerificationForm">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold"><i class="fas fa-id-card me-2 text-primary"></i>Verification & ID Check</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <div class="col-lg-8 border-end bg-dark">
                            <div id="idViewerContainer" class="d-flex align-items-center justify-content-center" style="height: 500px; background: #333;">
                                <div id="noIdPlaceholder" class="text-center text-white-50">
                                    <i class="fas fa-file-invoice fa-4x mb-3"></i>
                                    <p>No ID card uploaded yet.</p>
                                </div>
                                <iframe id="modal_id_viewer" style="width:100%; height: 100%; border: none; display:none;"></iframe>
                            </div>
                        </div>

                        <div class="col-lg-4 p-4 bg-white">
                            <input type="hidden" name="referee_id" id="modalRefId">
                            <input type="hidden" name="current_page" id="modalPage">
                            
                            <div class="text-center mb-4">
                                <div class="avatar bg-light rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:60px; height:60px;">
                                    <i class="fas fa-user text-primary fa-lg"></i>
                                </div>
                                <h5 id="modalRefName" class="fw-bold mb-0"></h5>
                                <p id="modalRefOrg" class="text-muted small mb-2"></p>
                                <span id="modalRefStatus" class="badge rounded-pill px-3"></span>
                            </div>

                            <hr class="my-4">

                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-2 small">
                                    <span class="text-muted">Email:</span>
                                    <strong id="modalRefEmail" class="text-end text-break"></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2 small">
                                    <span class="text-muted">Phone:</span>
                                    <strong id="modalRefPhone" class="text-end"></strong>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="action" value="acknowledge" id="btnAcknowledge" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i> Acknowledge
                                </button>
                                
                                <button type="submit" name="action" value="verify" id="btnConfirmVerify" class="btn btn-success">
                                    <i class="fas fa-check-double me-2"></i> Final Verify
                                </button>

                                <button type="submit" name="action" value="notify" class="btn btn-outline-secondary btn-sm mt-3">
                                    <i class="fas fa-envelope me-1"></i> Send Reminder
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let currentPage = <?= isset($_GET['page']) ? (int)$_GET['page'] : 1 ?>;
    const totalPages = <?= $totalPages ?>;
    let currentFilter = 'all';
    let currentSearch = '';

    document.addEventListener('DOMContentLoaded', () => {
        loadData(currentPage);
    });

    function loadData(page) {
        const wrapper = document.getElementById('applicant-content-wrapper');
        const prevBtn = document.getElementById('btn-prev');
        const nextBtn = document.getElementById('btn-next');
        
        wrapper.style.opacity = '0.5';

        const url = new URL(window.location.href);
        url.searchParams.set('ajax_fetch_applicant', '1');
        url.searchParams.set('page', page);
        if(currentFilter !== 'all') url.searchParams.set('status', currentFilter);
        if(currentSearch) url.searchParams.set('search', currentSearch);

        fetch(url)
        .then(res => res.text())
        .then(html => {
            wrapper.innerHTML = html;
            wrapper.style.opacity = '1';
            
            document.getElementById('page-indicator').innerText = `${page} / ${totalPages}`;
            
            if (page <= 1) prevBtn.classList.add('disabled'); else prevBtn.classList.remove('disabled');
            if (page >= totalPages) nextBtn.classList.add('disabled'); else nextBtn.classList.remove('disabled');
            
            // Reset detail view if filtering changed context
            if(html.includes('No applicant data')) {
                document.getElementById('detailViewContainer').innerHTML = `<div class="text-center py-5 text-muted"><p>No data</p></div>`;
            }
        });
    }

    function changePage(dir) {
        const newPage = currentPage + dir;
        if (newPage >= 1 && newPage <= totalPages) {
            currentPage = newPage;
            loadData(currentPage);
        }
    }

    function filterByStatus(status) {
        currentFilter = status;
        currentPage = 1;
        loadData(currentPage);
    }

    function performSearch() {
        currentSearch = document.getElementById('searchInput').value;
        currentPage = 1;
        loadData(currentPage);
    }

    function selectReferee(el, data) {
        document.querySelectorAll('.document-item').forEach(i => i.classList.remove('selected'));
        el.classList.add('selected');

        const container = document.getElementById('detailViewContainer');
        const statusBadge = data.status === 'Verified' ? 'bg-success' : (data.status === 'Received' ? 'bg-primary' : 'bg-secondary');
        
        container.innerHTML = `
            <div class="text-center mb-4">
                <div class="bg-light rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                    <span class="fs-2 text-primary fw-bold">${data.full_name.charAt(0)}</span>
                </div>
                <h5 class="fw-bold">${data.full_name}</h5>
                <p class="text-muted small">${data.title}</p>
                <span class="badge ${statusBadge} px-3 py-2 rounded-pill">${data.status}</span>
            </div>
            
            <hr>
            
            <div class="mb-3">
                <label class="small text-muted d-block text-uppercase fw-bold">Organization</label>
                <div><i class="fas fa-building text-primary me-2"></i> ${data.organization}</div>
            </div>
            <div class="mb-3">
                <label class="small text-muted d-block text-uppercase fw-bold">Email</label>
                <div><a href="mailto:${data.email}" class="text-decoration-none text-dark"><i class="fas fa-envelope text-primary me-2"></i> ${data.email}</a></div>
            </div>
            <div class="mb-3">
                <label class="small text-muted d-block text-uppercase fw-bold">Phone</label>
                <div><i class="fas fa-phone text-primary me-2"></i> ${data.phone}</div>
            </div>

             <div class="mt-4 d-grid gap-2">
                <button class="btn btn-primary" onclick='openVerificationModal(${JSON.stringify(data)})'>
                    <i class="fas fa-tasks me-2"></i> Manage Verification
                </button>
            </div>
        `;
    }

    function openVerificationModal(data) {
        document.getElementById('modalRefId').value = data.referee_id;
        document.getElementById('modalPage').value = currentPage;
        document.getElementById('modalRefName').innerText = data.full_name;
        document.getElementById('modalRefOrg').innerText = data.organization;
        document.getElementById('modalRefEmail').innerText = data.email;
        document.getElementById('modalRefPhone').innerText = data.phone;
        
        const statusEl = document.getElementById('modalRefStatus');
        statusEl.innerText = data.status;
        statusEl.className = 'badge rounded-pill px-3 ' + 
            (data.status === 'Verified' ? 'bg-success' : 
             data.status === 'Received' ? 'bg-primary' : 'bg-secondary');

        // ID Viewer
        const iframe = document.getElementById('modal_id_viewer');
        const placeholder = document.getElementById('noIdPlaceholder');
        
        if (data.id_path) {
            iframe.src = '../' + data.id_path; 
            iframe.style.display = 'block';
            placeholder.style.display = 'none';
        } else {
            iframe.style.display = 'none';
            placeholder.style.display = 'block';
        }

        // Button States
        const btnAck = document.getElementById('btnAcknowledge');
        const btnVerify = document.getElementById('btnConfirmVerify');

        btnAck.disabled = (data.status === 'Received' || data.status === 'Verified' || !data.id_path);
        if(data.status === 'Verified') {
             btnVerify.disabled = true;
             btnVerify.innerHTML = '<i class="fas fa-check-circle me-1"></i> Already Verified';
        } else {
             btnVerify.disabled = false;
             btnVerify.innerHTML = '<i class="fas fa-check-double me-1"></i> Final Verification';
        }

        new bootstrap.Modal(document.getElementById('verificationModal')).show();
    }
</script>

<?php 
require_once 'includes/footer.php'; 
?>
