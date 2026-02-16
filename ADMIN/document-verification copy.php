<?php
session_start();

// Simple RBAC check
// if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
//     header('Location: /login.php'); // Redirect to login
//     exit;
// }

require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';

// Get filter and search parameters
$filterStatus = $_GET['status'] ?? 'all';
$searchTerm = trim($_GET['search'] ?? '');
$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?? 1;
$limit = 10; // Number of documents per page
$offset = ($currentPage - 1) * $limit;

// Build WHERE clause for filtering and searching
$whereClauses = [];
$params = [];

if ($filterStatus !== 'all') {
    $whereClauses[] = "d.status = :filterStatus";
    $params[':filterStatus'] = ucfirst($filterStatus);
}

if (!empty($searchTerm)) {
    $searchTermWildcard = '%' . $searchTerm . '%';
    $whereClauses[] = "(d.document_type LIKE :searchTerm OR a.application_number LIKE :searchTerm OR pd.first_name LIKE :searchTerm OR pd.surname LIKE :searchTerm)";
    $params[':searchTerm'] = $searchTermWildcard;
}

$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = ' WHERE ' . implode(' AND ', $whereClauses);
}

// Initialize stats
$stats = [
    'pending' => 0,
    'verified' => 0,
    'rejected' => 0,
    'total' => 0
];

// Fetch document stats dynamically
try {
    $statsQuery = "
        SELECT 
            SUM(CASE WHEN d.status = 'Pending' OR d.status IS NULL THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN d.status = 'Verified' THEN 1 ELSE 0 END) AS verified,
            SUM(CASE WHEN d.status = 'Rejected' THEN 1 ELSE 0 END) AS rejected,
            COUNT(d.doc_id) AS total
        FROM documents d
        JOIN applications a ON d.application_id = a.application_id
        JOIN personal_details pd ON a.application_id = pd.application_id
        {$whereSql}
    ";
    $stmt = $pdo->prepare($statsQuery);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats = $result;
    }
} catch (Exception $e) {
    error_log("Error fetching document stats: " . $e->getMessage());
}

// Fetch all documents with their verification status
$documents = [];
$totalDocuments = 0;
try {
    $countQuery = "
        SELECT COUNT(d.doc_id)
        FROM documents d
        JOIN applications a ON d.application_id = a.application_id
        JOIN personal_details pd ON a.application_id = pd.application_id
        {$whereSql}
    ";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalDocuments = $countStmt->fetchColumn();

    $documentsQuery = "
        SELECT 
            d.doc_id, 
            d.document_type, 
            d.file_path, 
            d.uploaded_at, 
            a.application_number,
            pd.first_name,
            pd.surname,
            COALESCE(d.status, 'Pending') as status,
            d.comments
        FROM documents d
        JOIN applications a ON d.application_id = a.application_id
        JOIN personal_details pd ON a.application_id = pd.application_id
        {$whereSql}
        ORDER BY d.uploaded_at DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($documentsQuery);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching documents: " . $e->getMessage());
}

// Calculate total pages for pagination
$totalPages = ceil($totalDocuments / $limit);

// Fetch today's progress
$today_progress = [
    'verified' => 0,
    'rejected' => 0,
    'total' => 0,
    'percentage' => 0
];
try {
    $todayWhereSql = " WHERE DATE(d.uploaded_at) = CURDATE()";
    $todayParams = [];

    // Add filter and search to today's progress if applicable
    if ($filterStatus !== 'all') {
        $todayWhereSql .= " AND d.status = :filterStatus";
        $todayParams[':filterStatus'] = ucfirst($filterStatus);
    }
    if (!empty($searchTerm)) {
        $todayWhereSql .= " AND (d.document_type LIKE :searchTerm OR a.application_number LIKE :searchTerm OR pd.first_name LIKE :searchTerm OR pd.surname LIKE :searchTerm)";
        $todayParams[':searchTerm'] = '%' . $searchTerm . '%';
    }

    $todayQuery = "
        SELECT
            SUM(CASE WHEN d.status = 'Verified' THEN 1 ELSE 0 END) AS verified,
            SUM(CASE WHEN d.status = 'Rejected' THEN 1 ELSE 0 END) AS rejected
        FROM documents d
        JOIN applications a ON d.application_id = a.application_id
        JOIN personal_details pd ON a.application_id = pd.application_id
        {$todayWhereSql}
    ";
    $stmt = $pdo->prepare($todayQuery);
    $stmt->execute($todayParams);
    $today_result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($today_result) {
        $today_progress['verified'] = $today_result['verified'] ?? 0;
        $today_progress['rejected'] = $today_result['rejected'] ?? 0;
        $today_progress['total'] = $today_progress['verified'] + $today_progress['rejected'];
        
        $total_docs_uploaded_today_query = "
            SELECT COUNT(d.doc_id) 
            FROM documents d
            JOIN applications a ON d.application_id = a.application_id
            JOIN personal_details pd ON a.application_id = pd.application_id
            WHERE DATE(d.uploaded_at) = CURDATE()
        ";
        $total_docs_uploaded_today_stmt = $pdo->prepare($total_docs_uploaded_today_query);
        $total_docs_uploaded_today_stmt->execute();
        $total_docs_uploaded_today = $total_docs_uploaded_today_stmt->fetchColumn();

        if($total_docs_uploaded_today > 0) {
            $today_progress['percentage'] = round(($today_progress['total'] / $total_docs_uploaded_today) * 100);
        }
    }
} catch (Exception $e) {
    error_log("Error fetching today's progress: " . $e->getMessage());
}

?>

            <!-- Content Container -->
            <div class="content-container">
                <!-- KPI Cards -->
                <div class="kpi-cards">
                    <div class="kpi-card">
                        <div class="kpi-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="kpi-content">
                            <h3><?= (int)$stats['pending'] ?></h3>
                            <p>Pending Verification</p>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="kpi-content">
                            <h3><?= (int)$stats['verified'] ?></h3>
                            <p>Verified</p>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon danger">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="kpi-content">
                            <h3><?= (int)$stats['rejected'] ?></h3>
                            <p>Rejected</p>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon info">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="kpi-content">
                            <h3><?= (int)$stats['total'] ?></h3>
                            <p>Total Documents</p>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons Section -->
                <div class="action-buttons">
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

                <!-- Document Verification Interface -->
                <div class="row">
                    <!-- Document Queue -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Document Verification Queue</h5>
                                    <div class="input-group" style="width: 250px;">
                                        <input type="text" class="form-control" id="docSearchInput" placeholder="Search documents..." value="<?= htmlspecialchars($searchTerm) ?>">
                                        <button class="btn btn-outline-secondary" type="button" onclick="performSearch()">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="document-queue">
                                    <?php if (!empty($documents)): ?>
                                        <?php foreach ($documents as $doc): ?>
                                            <div class="document-item border rounded p-3 mb-3" data-doc-id="<?= $doc['doc_id'] ?>" data-file-path="<?= htmlspecialchars($doc['file_path']) ?>" onclick="selectDocument(this)">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex align-items-center mb-2">
                                                            <?php 
                                                                $fileExtension = pathinfo($doc['file_path'], PATHINFO_EXTENSION);
                                                                $fileIconClass = 'fas fa-file-alt'; // Default icon
                                                                if (in_array($fileExtension, ['pdf'])) {
                                                                    $fileIconClass = 'fas fa-file-pdf text-danger';
                                                                } elseif (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
                                                                    $fileIconClass = 'fas fa-file-image text-info';
                                                                }
                                                            ?>
                                                            <i class="<?= $fileIconClass ?> me-2"></i>
                                                            <div>
                                                                <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $doc['document_type']))) ?></strong>
                                                                <small class="text-muted d-block"><?= htmlspecialchars($doc['surname'] . ' ' . $doc['first_name']) ?> - <?= htmlspecialchars($doc['application_number']) ?></small>
                                                            </div>
                                                            <?php
                                                            $status_classes = ['Pending' => 'bg-warning', 'Verified' => 'bg-success', 'Rejected' => 'bg-danger'];
                                                            $status_class = $status_classes[$doc['status']] ?? 'bg-secondary';
                                                            ?>
                                                            <span class="badge <?= $status_class ?> ms-auto"><?= htmlspecialchars($doc['status']) ?></span>
                                                        </div>
                                                        <small class="text-muted">Uploaded: <?= date('M d, Y, h:i A', strtotime($doc['uploaded_at'])) ?></small>
                                                    </div>
                                                    <div class="ms-3">
                                                        <a href="../<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">View</a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-center">No documents found.</p>
                                    <?php endif; ?>
                                </div>

                                <!-- Pagination -->
                                <nav aria-label="Documents pagination" class="mt-3">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?status=<?= htmlspecialchars($filterStatus) ?>&search=<?= htmlspecialchars($searchTerm) ?>&page=<?= $currentPage - 1 ?>" tabindex="-1">Previous</a>
                                        </li>
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?= ($currentPage == $i) ? 'active' : '' ?>">
                                                <a class="page-link" href="?status=<?= htmlspecialchars($filterStatus) ?>&search=<?= htmlspecialchars($searchTerm) ?>&page=<?= $i ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?status=<?= htmlspecialchars($filterStatus) ?>&search=<?= htmlspecialchars($searchTerm) ?>&page=<?= $currentPage + 1 ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>

                    <!-- Document Viewer/Verification Panel -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Document Viewer</h5>
                            </div>
                            <div class="card-body">
                                <div id="documentViewer" class="text-center">
                                    <div class="document-placeholder">
                                        <i class="fas fa-file-pdf fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Select a document to view</p>
                                    </div>
                                    <iframe id="doc_viewer_iframe" style="width:100%; height: 500px; border: none; display:none;"></iframe>
                                </div>

                                <div id="verificationPanel" style="display: none;">
                                    <h6>Verification Checklist</h6>
                                    <div class="verification-checklist">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="checkAuthenticity">
                                            <label class="form-check-label" for="checkAuthenticity">
                                                Document authenticity verified
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="checkCompleteness">
                                            <label class="form-check-label" for="checkCompleteness">
                                                All required information present
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="checkFormat">
                                            <label class="form-check-label" for="checkFormat">
                                                Correct format and quality
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="checkExpiry">
                                            <label class="form-check-label" for="checkExpiry">
                                                Document not expired
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <label class="form-label">Comments</label>
                                        <textarea class="form-control" id="verificationComments" rows="3" placeholder="Add verification notes..."></textarea>
                                    </div>

                                    <div class="d-grid gap-2 mt-3">
                                        <button class="btn btn-success" onclick="submitVerification()">
                                            <i class="fas fa-check"></i> Verify Document
                                        </button>
                                        <button class="btn btn-danger" onclick="submitRejection()">
                                            <i class="fas fa-times"></i> Reject Document
                                        </button>
                                        <button class="btn btn-warning" onclick="requestResubmission()">
                                            <i class="fas fa-redo"></i> Request Resubmission
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">Today's Progress</h6>
                            </div>
                            <div class="card-body">
                                <div class="progress mb-2">
                                    <div class="progress-bar bg-success" style="width: <?= $today_progress['percentage'] ?>%"><?= $today_progress['percentage'] ?>%</div>
                                </div>
                                <small class="text-muted"><?= $today_progress['total'] ?> of <?= $total_docs_uploaded_today ?? 0 ?> documents uploaded today</small>

                                <hr>

                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="border-end">
                                            <h4 class="text-success mb-0"><?= $today_progress['verified'] ?></h4>
                                            <small class="text-muted">Verified</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-danger mb-0"><?= $today_progress['rejected'] ?></h4>
                                        <small class="text-muted">Rejected</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Rejection Modal -->
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
    document.getElementById('sidebar-toggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('collapsed');
        document.getElementById('main-wrapper').classList.toggle('sidebar-collapsed');
    });

    let selectedDocId = null;
    const currentFilterStatus = "<?= htmlspecialchars($filterStatus) ?>";
    const currentSearchTerm = "<?= htmlspecialchars($searchTerm) ?>";
    const currentPage = <?= $currentPage ?>;

    function selectDocument(element) {
        selectedDocId = element.dataset.docId;
        const filePath = element.dataset.filePath;

        document.querySelectorAll('.document-item').forEach(item => {
            item.classList.remove('selected');
        });
        element.classList.add('selected');

        const documentViewer = document.getElementById('documentViewer');
        const verificationPanel = document.getElementById('verificationPanel');
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
        
        verificationPanel.style.display = 'block';

        document.querySelectorAll('.verification-checklist input').forEach(cb => cb.checked = false);
        document.getElementById('verificationComments').value = '';
    }

    function submitVerification() {
        if (!selectedDocId) {
            alert('Please select a document first.');
            return;
        }

        const checks = document.querySelectorAll('.verification-checklist input:checked');
        if (checks.length < 4) {
            alert('Please complete all verification checks before submitting.');
            return;
        }

        const comments = document.getElementById('verificationComments').value;
        updateDocumentStatus(selectedDocId, 'Verified', comments);
    }

    function submitRejection() {
        if (!selectedDocId) {
            alert('Please select a document first.');
            return;
        }
        const modal = new bootstrap.Modal(document.getElementById('rejectionModal'));
        modal.show();
    }
    
    function confirmRejection() {
        const reason = document.getElementById('rejectionReason').value;
        const comments = document.getElementById('rejectionCommentsModal').value;

        if (!reason) {
            alert('Please select a rejection reason.');
            return;
        }

        let fullComment = `Reason: ${reason}. ${comments}`;
        updateDocumentStatus(selectedDocId, 'Rejected', fullComment);
        bootstrap.Modal.getInstance(document.getElementById('rejectionModal')).hide();
    }

    function updateDocumentStatus(docId, status, comments) {
        const formData = new FormData();
        formData.append('doc_id', docId);
        formData.append('status', status);
        formData.append('comments', comments);

        fetch('api/verify_document.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Document ${status} successfully!`);
                location.reload(); // Reload to reflect changes and apply filters
            } else {
                alert('Failed to update document status: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the document status.');
        });
    }

    function refreshDocuments() {
        // Construct the URL with current filters and search term
        let url = new URL(window.location.origin + window.location.pathname);
        if (currentFilterStatus && currentFilterStatus !== 'all') {
            url.searchParams.set('status', currentFilterStatus);
        }
        if (currentSearchTerm) {
            url.searchParams.set('search', currentSearchTerm);
        }
        // Retain current page after refresh
        url.searchParams.set('page', currentPage); 
        window.location.href = url.toString();
    }

    function filterByStatus(status) {
        let url = new URL(window.location.origin + window.location.pathname);
        if (status !== 'all') {
            url.searchParams.set('status', status);
        } else {
            url.searchParams.delete('status'); // Remove status param if 'all'
        }
        if (currentSearchTerm) {
            url.searchParams.set('search', currentSearchTerm);
        }
        // Reset to page 1 when filtering
        url.searchParams.set('page', 1); 
        window.location.href = url.toString();
    }

    function performSearch() {
        const newSearchTerm = document.getElementById('docSearchInput').value;
        let url = new URL(window.location.origin + window.location.pathname);
        if (currentFilterStatus && currentFilterStatus !== 'all') {
            url.searchParams.set('status', currentFilterStatus);
        }
        if (newSearchTerm) {
            url.searchParams.set('search', newSearchTerm);
        } else {
            url.searchParams.delete('search'); // Remove search param if empty
        }
        // Reset to page 1 when searching
        url.searchParams.set('page', 1); 
        window.location.href = url.toString();
    }

    document.getElementById('docSearchInput').addEventListener('keypress', function(event) {
        if (event.key === 'Enter') {
            performSearch();
        }
    });

    // Highlight active filter dropdown item
    document.addEventListener('DOMContentLoaded', function() {
        const filterItems = document.querySelectorAll('.dropdown-menu .dropdown-item');
        filterItems.forEach(item => {
            const statusMatch = item.getAttribute('onclick').match(/'(all|pending|verified|rejected)'/);
            if (statusMatch && statusMatch[1] === currentFilterStatus) {
                item.classList.add('active');
            } else {
                item.classList.remove('active'); // Ensure only one is active
            }
        });
    });

    const style = document.createElement('style');
    style.textContent = `
        .document-item.selected {
            background-color: #f0f8ff;
            border-color: #0d6efd;
        }
        .text-sm {
            font-size: 0.875rem;
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>