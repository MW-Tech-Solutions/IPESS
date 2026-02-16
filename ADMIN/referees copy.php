<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Database Connection
require_once 'db.php'; 
// --- UNIFIED POST HANDLER ---

// Inside referees.php logic for "Notify"
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $refId = filter_input(INPUT_POST, 'referee_id', FILTER_VALIDATE_INT);
    $currentPageNum = filter_input(INPUT_POST, 'current_page', FILTER_VALIDATE_INT) ?? 1;

    if ($refId) {

        // Inside the if ($refId) { ... } block in referees.php
if ($action === 'verify') {
    // 1. First, get the application_id for this referee
    $stmtApp = $pdo->prepare("SELECT application_id FROM referees WHERE referee_id = ?");
    $stmtApp->execute([$refId]);
    $appId = $stmtApp->fetchColumn();

    if ($appId) {
        // 2. Update the individual Referee Status
        $stmtRef = $pdo->prepare("UPDATE referee_status SET submission_status = 'Verified', received_at = NOW() WHERE referee_id = ?");
        $stmtRef->execute([$refId]);

        // 3. Handle Application Progress (Insert or Update)
        // Check if the 'Referee Reports' stage already exists for this application
        $stmtCheck = $pdo->prepare("SELECT progress_id FROM application_progress WHERE application_id = ? AND stage = 'Referee Reports'");
        $stmtCheck->execute([$appId]);
        $existingProgress = $stmtCheck->fetch();

        if ($existingProgress) {
            // Run Update if row exists
            $stmtProgress = $pdo->prepare("
                UPDATE application_progress 
                SET stage_status = 'Completed', 
                    stage_updated_at = NOW() 
                WHERE application_id = ? AND stage = 'Referee Reports'
            ");
            $stmtProgress->execute([$appId]);
        } else {
            // Run Insert if row does not exist
            $stmtProgress = $pdo->prepare("
                INSERT INTO application_progress (application_id, stage, stage_status, stage_updated_at) 
                VALUES (?, 'Referee Reports', 'Completed', NOW())
            ");
            $stmtProgress->execute([$appId]);
        }

        $_SESSION['message'] = "Referee verified and application progress updated.";
        $_SESSION['msg_type'] = "success";
    }
}
elseif ($action === 'acknowledge') {
    // 1. Fetch data first to get email and names
    $stmt = $pdo->prepare("
        SELECT r.full_name as ref_name, r.email as ref_email, pd.first_name, pd.surname
        FROM referees r
        JOIN applications a ON r.application_id = a.application_id
        JOIN personal_details pd ON a.application_id = pd.application_id
        WHERE r.referee_id = ?
    ");
    $stmt->execute([$refId]);
    $data = $stmt->fetch();

    if ($data) {
        // 2. Update Database Status
        $update = $pdo->prepare("UPDATE referee_status SET submission_status = 'Received' WHERE referee_id = ?");
        $update->execute([$refId]);

        // 3. Send the Acknowledgment Email
        require_once 'includes/RefereeMailer.php';
        $appFullName = $data['first_name'] . ' ' . $data['surname'];
        
        $mailResult = RefereeMailer::sendAcknowledgment(
            $data['ref_email'], 
            $data['ref_name'], 
            $appFullName
        );

        if ($mailResult['success']) {
            $_SESSION['message'] = "Status updated and acknowledgment email sent to " . $data['ref_email'];
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['message'] = "Status updated, but email failed: " . $mailResult['message'];
            $_SESSION['msg_type'] = "warning";
        }
    }
}
        // Handle Email Notification (Notify)
        elseif ($action === 'notify') {
            $stmt = $pdo->prepare("
                SELECT r.full_name as ref_name, r.email as ref_email, pd.first_name, pd.surname, pd.phone, acc.email as app_email
                FROM referees r
                JOIN applications a ON r.application_id = a.application_id
                JOIN personal_details pd ON a.application_id = pd.application_id
                JOIN applicant_accounts acc ON a.user_id = acc.user_id
                WHERE r.referee_id = ?
            ");
            $stmt->execute([$refId]);
            $data = $stmt->fetch();

            if ($data) {
                require_once 'includes/RefereeMailer.php';
                $applicantName = $data['first_name'] . ' ' . $data['surname'];
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                $vLink = $protocol . $_SERVER['HTTP_HOST'] . "/applicants/referee_verify.php?rid=" . $refId . "&auth=" . md5($data['ref_email'] . "JOSTUM_SALT_2024");

                // 1. Send Email to Referee
                $refResult = RefereeMailer::sendVerificationRequest(
                    $data['ref_email'], 
                    $data['ref_name'], 
                    ['name' => $applicantName, 'email' => $data['app_email'], 'phone' => $data['phone']],
                    $vLink
                );

                // 2. If successful, also notify the Applicant
                if ($refResult['success']) {
                    RefereeMailer::notifyApplicantOfRequest(
                        $data['app_email'], // From your JOIN applicant_accounts
                        $applicantName, 
                        $data['ref_name']
                    );
                }

                $_SESSION['message'] = $refResult['success'] ? "Notification sent to referee and applicant notified." : "Mail Error: " . $refResult['message'];
                $_SESSION['msg_type'] = $refResult['success'] ? "success" : "danger";
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

    // Base Joins to link Applications to Personal Details
    // Assuming 'personal_details' exists based on document-verification.php context
    $baseJoins = "
        FROM applications a
        JOIN personal_details pd ON a.application_id = pd.application_id
        JOIN referees r ON a.application_id = r.application_id
        LEFT JOIN referee_status rs ON r.referee_id = rs.referee_id
    ";

    $whereClauses = [];
    $params = [];

    // Filter Logic
    if ($filterStatus !== 'all') {
        if($filterStatus === 'verified') {
            $whereClauses[] = "rs.submission_status = 'Verified'";
        } elseif ($filterStatus === 'pending') {
            $whereClauses[] = "(rs.submission_status != 'Verified' OR rs.submission_status IS NULL)";
        }
    }

    if (!empty($searchTerm)) {
        $whereClauses[] = "(
            pd.first_name LIKE :searchTerm 
            OR pd.surname LIKE :searchTerm 
            OR a.application_number LIKE :searchTerm
            OR r.full_name LIKE :searchTerm
            OR r.email LIKE :searchTerm
        )";
        $params[':searchTerm'] = '%' . $searchTerm . '%';
    }

    $whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

    // 1. Get the Application ID for the current Page/Offset
    $appIdQuery = "SELECT a.application_id $baseJoins $whereSql GROUP BY a.application_id ORDER BY a.application_id DESC LIMIT 1 OFFSET :offset";
    $stmt = $pdo->prepare($appIdQuery);
    foreach ($params as $key => $val) { $stmt->bindValue($key, $val); }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $currentAppId = $stmt->fetchColumn();

    if ($currentAppId) {
        // 2. Fetch Referees for this Applicant
        $refQuery = "
            SELECT 
                r.referee_id, r.full_name, r.title, r.organization, r.email, r.phone,
                a.application_number, pd.first_name, pd.surname,
                COALESCE(rs.submission_status, 'Not Submitted') as status,
                rs.received_at, rs.id_path
            FROM referees r
            JOIN applications a ON r.application_id = a.application_id
            JOIN personal_details pd ON a.application_id = pd.application_id
            LEFT JOIN referee_status rs ON r.referee_id = rs.referee_id
            WHERE a.application_id = :appId
        ";
        
        $stmt = $pdo->prepare($refQuery);
        $stmt->execute([':appId' => $currentAppId]);
        $referees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Applicant Info (from first row)
        $applicantInfo = [
            'name' => $referees[0]['first_name'] . ' ' . $referees[0]['surname'],
            'app_number' => $referees[0]['application_number']
        ];

        $totalRefs = count($referees);
        $verifiedCount = 0;
        foreach($referees as $r) {
            if($r['status'] === 'Verified') $verifiedCount++;
        }

        ?>
        <div class="applicant-header bg-light p-3 rounded mb-3 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0 text-primary fw-bold"><?= htmlspecialchars($applicantInfo['name']) ?></h4>
                    <span class="text-muted">Application No: <strong><?= htmlspecialchars($applicantInfo['app_number']) ?></strong></span>
                    <div class="mt-2">
                        <span class="badge bg-info text-dark border border-info">
                            <i class="fas fa-check-double me-1"></i> Verified: <?= $verifiedCount ?> / <?= $totalRefs ?>
                        </span>
                    </div>
                </div>
                <div class="text-end text-muted small">
                    Showing Applicant <?= $currentPage ?>
                </div>
            </div>
        </div>

        <div class="document-queue">
            <?php foreach ($referees as $ref): ?>
                <?php 
                    // Prepare JSON data for the detail view
                    $jsonData = htmlspecialchars(json_encode($ref), ENT_QUOTES, 'UTF-8');
                ?>
                <div class="document-item border rounded p-3 mb-3" 
                     onclick='selectReferee(this, <?= $jsonData ?>)'
                     style="cursor: pointer; transition: all 0.2s;">
                    
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-user-tie text-primary me-2 fa-lg"></i>
                                <div>
                                    <strong><?= htmlspecialchars($ref['full_name']) ?></strong>
                                    <span class="text-muted small ms-1">(<?= htmlspecialchars($ref['title']) ?>)</span>
                                </div>
                                <?php
                                $status_classes = ['Not Submitted' => 'bg-secondary', 'Submitted' => 'bg-info', 'Received' => 'bg-primary', 'Verified' => 'bg-success'];
                                $status_class = $status_classes[$ref['status']] ?? 'bg-secondary';
                                ?>
                                <span class="badge <?= $status_class ?> ms-auto"><?= htmlspecialchars($ref['status']) ?></span>
                            </div>
                            
                            <div class="small text-muted mb-1">
                                <i class="fas fa-building me-1"></i> <?= htmlspecialchars($ref['organization']) ?>
                            </div>
                            <div class="small text-muted">
                                <i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($ref['email']) ?>
                            </div>
                        </div>
                        
                        <div class="ms-3 d-flex flex-column gap-2">
                            <button 
                                type="button" 
                                class="btn btn-outline-primary btn-sm"
                                onclick='openVerificationModal(<?= $jsonData ?>); event.stopPropagation();'
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
        echo '<div class="text-center py-5"><p class="text-muted">No applicant data found matching criteria.</p></div>';
    }
    exit; 
}

// --- MAIN PAGE LOAD ---

// Handle Form Submission (Verify or Notify)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $refId = filter_input(INPUT_POST, 'referee_id', FILTER_VALIDATE_INT);
    
    if ($refId && $action === 'verify') {
        $stmt = $pdo->prepare("UPDATE referee_status SET submission_status = 'Verified', received_at = NOW() WHERE referee_id = ?");
        $stmt->execute([$refId]);
        // Ideally set a flash message here
    }
    // Refresh page to reflect changes
    header("Location: referees.php?page=" . ($_POST['current_page'] ?? 1));
    exit;
}

// Stats Calculation
$stats = ['total'=>0, 'verified'=>0, 'pending'=>0];
try {
    $statsQuery = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN submission_status = 'Verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN submission_status != 'Verified' OR submission_status IS NULL THEN 1 ELSE 0 END) as pending
        FROM referees r
        LEFT JOIN referee_status rs ON r.referee_id = rs.referee_id
    ";
    $stats = $pdo->query($statsQuery)->fetch(PDO::FETCH_ASSOC);
    
    // Count total applicants for pagination
    $countQuery = "SELECT COUNT(DISTINCT application_id) FROM referees";
    $totalApplicants = $pdo->query($countQuery)->fetchColumn();
    $totalPages = ceil($totalApplicants / 1);
} catch (PDOException $e) { error_log("Stats Error: " . $e->getMessage()); }



require_once 'includes/topbar.php';

require_once 'includes/sidebar.php';
// require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referees Management | JOSTUM PG</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../asset/bootstrap-5.3.8-dist/css/bootstrap.min.css"> 
    <style>
        :root { --primary-color: #0d6efd; --bg-light: #f8f9fa; }
        body { background-color: var(--bg-light); font-family: 'Poppins', sans-serif; }
        .document-item.selected { background-color: #e7f1ff; border-color: #0d6efd !important; }
        .sidebar { min-height: 100vh; width: 250px; position: fixed; background: #fff; z-index: 100; border-right: 1px solid #dee2e6; }
        .content-wrapper { margin-left: 250px; padding: 20px; width: calc(100% - 250px); }
        @media (max-width: 768px) { .content-wrapper { margin-left: 0; width: 100%; } }
        /* Reuse styles from document-verification */
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<div class="d-flex" id="wrapper">
    <div class="content-wrapper">
       
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-warning text-white h-100 shadow-sm">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h3 class="mb-0 fw-bold"><?= (int)$stats['pending'] ?></h3>
                            <small class="text-uppercase fw-semibold">Pending Verification</small>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white h-100 shadow-sm">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h3 class="mb-0 fw-bold"><?= (int)$stats['verified'] ?></h3>
                            <small class="text-uppercase fw-semibold">Verified Referees</small>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white h-100 shadow-sm">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h3 class="mb-0 fw-bold"><?= (int)$stats['total'] ?></h3>
                            <small class="text-uppercase fw-semibold">Total Referees</small>
                        </div>
                        <i class="fas fa-users fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between mb-3">
            <button class="btn btn-primary btn-sm" onclick="location.reload()">
                <i class="fas fa-sync"></i> Refresh
            </button>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-filter"></i> Filter Status
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="filterByStatus('all')">All</a></li>
                    <li><a class="dropdown-item" href="#" onclick="filterByStatus('pending')">Pending</a></li>
                    <li><a class="dropdown-item" href="#" onclick="filterByStatus('verified')">Verified</a></li>
                </ul>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Referee List</h5>
                            <div class="input-group" style="width: 250px;">
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

                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item" id="btn-prev">
                                    <button class="page-link" onclick="changePage(-1)">
                                        <i class="fas fa-chevron-left"></i> Previous Applicant
                                    </button>
                                </li>
                                <li class="page-item disabled">
                                    <span class="page-link" id="page-indicator">1 / <?= $totalPages ?></span>
                                </li>
                                <li class="page-item" id="btn-next">
                                    <button class="page-link" onclick="changePage(1)">
                                        Next Applicant <i class="fas fa-chevron-right"></i>
                                    </button>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Referee Details</h5>
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
</div>
<div class="modal fade" id="verificationModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered"> <div class="modal-content">
            <!-- <form method="POST"> -->
                <form method="POST" id="modalVerificationForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-id-card me-2"></i>Referee Verification & ID Check</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-7 border-end">
                            <h6 class="fw-bold mb-3">Submitted ID Card</h6>
                            <div id="idViewerContainer" class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 450px; overflow: hidden;">
                                <div id="noIdPlaceholder" class="text-center text-muted">
                                    <i class="fas fa-file-invoice fa-4x mb-3 opacity-25"></i>
                                    <p>No ID card has been uploaded yet.</p>
                                </div>
                                <iframe id="modal_id_viewer" style="width:100%; height: 100%; border: none; display:none;"></iframe>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <input type="hidden" name="referee_id" id="modalRefId">
                            <input type="hidden" name="current_page" id="modalPage">
                            
                            <div class="text-center mb-4">
                                <h5 id="modalRefName" class="fw-bold mb-0"></h5>
                                <p id="modalRefOrg" class="text-muted"></p>
                                <span id="modalRefStatus" class="badge rounded-pill px-3"></span>
                            </div>

                            <div class="list-group list-group-flush small mb-4">
                                <div class="list-group-item d-flex justify-content-between">
                                    <span>Email:</span> <strong id="modalRefEmail"></strong>
                                </div>
                                <div class="list-group-item d-flex justify-content-between">
                                    <span>Phone:</span> <strong id="modalRefPhone"></strong>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="action" value="acknowledge" id="btnAcknowledge" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i> Acknowledge Receipt
                                </button>
                                
                                <button type="submit" name="action" value="verify" id="btnConfirmVerify" class="btn btn-success">
                                    <i class="fas fa-check-double me-2"></i> Final Verification
                                </button>

                                <button type="submit" name="action" value="notify" class="btn btn-outline-secondary btn-sm mt-2">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

<script src="../asset/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js.map "></script>
<script>
    let currentPage = <?= isset($_GET['page']) ? (int)$_GET['page'] : 1 ?>;
    const totalPages = <?= $totalPages ?>;
    let currentFilter = 'all';
    let currentSearch = '';

    // Load initial data
    document.addEventListener('DOMContentLoaded', () => {
        loadData(currentPage);
    });

    function loadData(page) {
        const wrapper = document.getElementById('applicant-content-wrapper');
        const prevBtn = document.getElementById('btn-prev');
        const nextBtn = document.getElementById('btn-next');
        
        wrapper.style.opacity = '0.5';

        // Construct URL
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
            
            // Update controls
            document.getElementById('page-indicator').innerText = `${page} / ${totalPages}`;
            
            if (page <= 1) prevBtn.classList.add('disabled'); else prevBtn.classList.remove('disabled');
            if (page >= totalPages) nextBtn.classList.add('disabled'); else nextBtn.classList.remove('disabled');
            
            // Reset right pane
            document.getElementById('detailViewContainer').innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-user-tie fa-4x mb-3 opacity-25"></i>
                    <p>Select a referee from the list to view full details.</p>
                </div>
            `;
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

    // Function to populate Right Column when clicking a list item
    function selectReferee(el, data) {
        // Visual selection
        document.querySelectorAll('.document-item').forEach(i => i.classList.remove('selected'));
        el.classList.add('selected');

        const container = document.getElementById('detailViewContainer');
        const statusBadge = data.status === 'Verified' ? 'bg-success' : 'bg-warning text-dark';
        
        container.innerHTML = `
            <div class="text-center mb-4">
                <div class="bg-light rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                    <span class="fs-1 text-primary fw-bold">${data.full_name.charAt(0)}</span>
                </div>
                <h4 class="fw-bold">${data.full_name}</h4>
                <p class="text-muted">${data.title}</p>
                <span class="badge ${statusBadge} px-3 py-2 rounded-pill">${data.status}</span>
            </div>
            
            <hr>
            
            <h6 class="text-uppercase text-muted small fw-bold mb-3">Contact Information</h6>
            
            <div class="mb-3">
                <label class="small text-muted d-block">Organization</label>
                <strong><i class="fas fa-building text-primary me-2"></i> ${data.organization}</strong>
            </div>
            <div class="mb-3">
                <label class="small text-muted d-block">Email Address</label>
                <strong><a href="mailto:${data.email}" class="text-decoration-none"><i class="fas fa-envelope text-primary me-2"></i> ${data.email}</a></strong>
            </div>
            <div class="mb-3">
                <label class="small text-muted d-block">Phone Number</label>
                <strong><i class="fas fa-phone text-primary me-2"></i> ${data.phone}</strong>
            </div>

             <div class="mt-4 d-grid gap-2">
                <button class="btn btn-primary" onclick='openVerificationModal(${JSON.stringify(data)})'>
                    <i class="fas fa-edit me-2"></i> Manage Verification
                </button>
            </div>
        `;
    }

    // Function to Open Modal
    function openVerificationModal(data) {
        document.getElementById('modalRefId').value = data.referee_id;
        document.getElementById('modalPage').value = currentPage;
        document.getElementById('modalRefName').innerText = data.full_name;
        document.getElementById('modalRefOrg').innerText = data.organization;
        document.getElementById('modalRefEmail').innerText = data.email;
        document.getElementById('modalRefPhone').innerText = data.phone;
        document.getElementById('modalRefStatus').innerText = data.status;

        const btnVerify = document.getElementById('btnConfirmVerify');
        if (data.status === 'Verified') {
            btnVerify.classList.replace('btn-success', 'btn-secondary');
            btnVerify.disabled = true;
            btnVerify.innerText = "Already Verified";
        } else {
            btnVerify.classList.replace('btn-secondary', 'btn-success');
            btnVerify.disabled = false;
            btnVerify.innerHTML = '<i class="fas fa-check me-1"></i> Mark as Verified';
        }

        new bootstrap.Modal(document.getElementById('verificationModal')).show();
    }
    function openVerificationModal(data) {
    document.getElementById('modalRefId').value = data.referee_id;
    document.getElementById('modalPage').value = currentPage;
    document.getElementById('modalRefName').innerText = data.full_name;
    document.getElementById('modalRefOrg').innerText = data.organization;
    document.getElementById('modalRefEmail').innerText = data.email;
    document.getElementById('modalRefPhone').innerText = data.phone;
    
    // Status Badge Logic
    const statusEl = document.getElementById('modalRefStatus');
    statusEl.innerText = data.status;
    statusEl.className = 'badge rounded-pill px-3 ' + 
        (data.status === 'Verified' ? 'bg-success' : 
         data.status === 'Received' ? 'bg-primary' : 'bg-secondary');

    // ID Viewer Logic
    const iframe = document.getElementById('modal_id_viewer');
    const placeholder = document.getElementById('noIdPlaceholder');
    
    // Note: Ensure your AJAX query now includes 'rs.id_path'
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

    // Disable Acknowledge if already Received or Verified
    btnAck.disabled = (data.status === 'Received' || data.status === 'Verified' || !data.id_path);
    
    // Disable Verify if already Verified
    btnVerify.disabled = (data.status === 'Verified');

    new bootstrap.Modal(document.getElementById('verificationModal')).show();
}
document.addEventListener('DOMContentLoaded', function() {
    const verificationForm = document.getElementById('modalVerificationForm');
    
    if (verificationForm) {
        verificationForm.addEventListener('submit', function(e) {
            const submitter = e.submitter; // The button that was actually clicked
            
            // 1. Disable all submit buttons in the modal to prevent any other clicks
            const actionButtons = verificationForm.querySelectorAll('button[type="submit"]');
            actionButtons.forEach(btn => {
                btn.disabled = true;
                btn.classList.add('opacity-75'); 
            });

            // 2. Add the Bootstrap spinner and change text to "Processing..."
            if (submitter) {
                submitter.innerHTML = `
                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    Processing...
                `;
            }

            return true; // Proceed with the form submission
        });
    }
});
</script>

</body>
</html>