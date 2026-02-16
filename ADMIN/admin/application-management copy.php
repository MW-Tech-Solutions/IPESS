<?php
/*
session_start();

// Simple RBAC check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    header('Location: /login.php'); // Redirect to login
    exit;
}

require_once 'controllers/AdminApplicationController.php';

global $pdo;
$controller = new AdminApplicationController($pdo);
$applications = $controller->getAllApplications();
$stats = $controller->getApplicationStats();
*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JOSTUM PG SCHOOL - Application Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="wrapper" id="main-wrapper">
        <!-- Fixed Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="#" class="sidebar-logo">
                    <i class="fas fa-graduation-cap"></i>
                    <span>JOSTUM PG</span>
                </a>
                <button class="sidebar-toggle" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            <div class="sidebar-nav">
                <ul class="nav flex-column">
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link active" href="application-management.php">
                            <i class="fas fa-tasks"></i>
                            <span>Application Management</span>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="document-verification.php">
                            <i class="fas fa-check-circle"></i>
                            <span>Document Verification</span>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="admission-decisions.php">
                            <i class="fas fa-gavel"></i>
                            <span>Admission Decisions</span>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navbar -->
            <nav class="navbar">
                <div class="navbar-brand">
                    Application Management
                </div>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <!-- <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="filterByStatus('all')">All Applications</a></li>
                                <li><a class="dropdown-item" href="#" onclick="filterByStatus('pending')">Pending</a></li>
                                <li><a class="dropdown-item" href="#" onclick="filterByStatus('under_review')">Under Review</a></li>
                                <li><a class="dropdown-item" href="#" onclick="filterByStatus('approved')">Approved</a></li>
                                <li><a class="dropdown-item" href="#" onclick="filterByStatus('rejected')">Rejected</a></li>
                            </ul>
                        </div> -->
                    </li>
                    <li class="nav-item dropdown">
                        <button class="dropdown-toggle notification-btn" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge">3</span>
                        </button>
                        <ul class="dropdown-menu notification-dropdown">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user-plus"></i> New user registered</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-file-alt"></i> Application submitted</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-exclamation-triangle"></i> System alert</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="#">View all notifications</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown user-dropdown">
                        <button class="dropdown-toggle" data-bs-toggle="dropdown">
                            <div class="user-avatar">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <span>Admin</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </nav>

            <!-- Content Container -->
            <div class="content-container">
                <!-- KPI Cards -->
                <div class="kpi-cards">
                    <div class="kpi-card">
                        <div class="kpi-icon primary">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="kpi-content">
                            <h3 id="total-apps">247</h3>
                            <p>Total Applications</p>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="kpi-content">
                            <h3 id="pending-apps">89</h3>
                            <p>Pending Review</p>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="kpi-content">
                            <h3 id="approved-apps">156</h3>
                            <p>Approved</p>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon danger">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="kpi-content">
                            <h3 id="rejected-apps">12</h3>
                            <p>Rejected</p>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons Section -->
                <div class="action-buttons">
                    <form method="post" class="d-inline">
                        <button type="submit" name="action" value="refresh" class="btn btn-primary btn-sm" onclick="refreshApplications()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </form>
                    <form method="post" class="d-inline">
                        <button type="submit" name="action" value="export" class="btn btn-success btn-sm" onclick="exportApplications()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </form>
                    <form method="post" class="d-inline">
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="filterByStatus('all')">All Applications</a></li>
                                <li><a class="dropdown-item" href="#" onclick="filterByStatus('pending')">Pending</a></li>
                                <li><a class="dropdown-item" href="#" onclick="filterByStatus('under_review')">Under Review</a></li>
                                <li><a class="dropdown-item" href="#" onclick="filterByStatus('approved')">Approved</a></li>
                                <li><a class="dropdown-item" href="#" onclick="filterByStatus('rejected')">Rejected</a></li>
                            </ul>
                        </div>
                    </form>
                </div>

                <!-- Applications Table -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">All Applications</h5>
                            <div class="input-group" style="width: 300px;">
                                <input type="text" class="form-control" id="searchInput" placeholder="Search applications...">
                                <button class="btn btn-outline-secondary" type="button">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="applicationsTable">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="selectAll"></th>
                                        <th>Application ID</th>
                                        <th>Applicant Name</th>
                                        <th>Programme</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Submitted Date</th>
                                        <th>Priority</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="checkbox" class="app-checkbox"></td>
                                        <td>APP-2024-001</td>
                                        <td>Alice Johnson</td>
                                        <td>M.Sc Computer Science</td>
                                        <td>Computer Science</td>
                                        <td><span class="badge bg-warning">Under Review</span></td>
                                        <td>2024-01-10</td>
                                        <td><span class="badge bg-success">High</span></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-info" onclick="viewApplication('APP-2024-001')" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary" onclick="editApplication('APP-2024-001')" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-h"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="#" onclick="approveApplication('APP-2024-001')">Approve</a></li>
                                                        <li><a class="dropdown-item" href="#" onclick="rejectApplication('APP-2024-001')">Reject</a></li>
                                                        <li><a class="dropdown-item" href="#" onclick="assignReviewer('APP-2024-001')">Assign Reviewer</a></li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteApplication('APP-2024-001')">Delete</a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><input type="checkbox" class="app-checkbox"></td>
                                        <td>APP-2024-002</td>
                                        <td>Ibrahim Ahmed</td>
                                        <td>Ph.D Mathematics</td>
                                        <td>Mathematics</td>
                                        <td><span class="badge bg-success">Approved</span></td>
                                        <td>2024-01-08</td>
                                        <td><span class="badge bg-secondary">Normal</span></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-info" onclick="viewApplication('APP-2024-002')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary" onclick="editApplication('APP-2024-002')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-h"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="#" onclick="revokeApproval('APP-2024-002')">Revoke Approval</a></li>
                                                        <li><a class="dropdown-item" href="#" onclick="sendNotification('APP-2024-002')">Send Notification</a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><input type="checkbox" class="app-checkbox"></td>
                                        <td>APP-2024-003</td>
                                        <td>Carol Davis</td>
                                        <td>M.Sc Physics</td>
                                        <td>Physics</td>
                                        <td><span class="badge bg-danger">Rejected</span></td>
                                        <td>2024-01-05</td>
                                        <td><span class="badge bg-secondary">Normal</span></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-info" onclick="viewApplication('APP-2024-003')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary" onclick="editApplication('APP-2024-003')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-h"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="#" onclick="reconsiderApplication('APP-2024-003')">Reconsider</a></li>
                                                        <li><a class="dropdown-item" href="#" onclick="sendRejectionNotice('APP-2024-003')">Send Rejection Notice</a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <!-- More application rows would be populated here -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <nav aria-label="Applications pagination">
                            <ul class="pagination justify-content-center">
                                <li class="page-item disabled">
                                    <a class="page-link" href="#" tabindex="-1">Previous</a>
                                </li>
                                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                <li class="page-item"><a class="page-link" href="#">2</a></li>
                                <li class="page-item"><a class="page-link" href="#">3</a></li>
                                <li class="page-item">
                                    <a class="page-link" href="#">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>

                <!-- Bulk Actions -->
                <div class="card" id="bulkActionsCard" style="display: none;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <span id="selectedCount">0 applications selected</span>
                            <div class="btn-group">
                                <form method="post" class="d-inline">
                                    <button type="submit" name="action" value="bulk_approve" class="btn btn-success btn-sm" onclick="bulkApprove()">
                                        <i class="fas fa-check"></i> Bulk Approve
                                    </button>
                                </form>
                                <form method="post" class="d-inline">
                                    <button type="submit" name="action" value="bulk_reject" class="btn btn-warning btn-sm" onclick="bulkReject()">
                                        <i class="fas fa-times"></i> Bulk Reject
                                    </button>
                                </form>
                                <form method="post" class="d-inline">
                                    <button type="submit" name="action" value="bulk_assign" class="btn btn-info btn-sm" onclick="bulkAssign()">
                                        <i class="fas fa-user-plus"></i> Bulk Assign
                                    </button>
                                </form>
                                <form method="post" class="d-inline">
                                    <button type="submit" name="action" value="bulk_delete" class="btn btn-danger btn-sm" onclick="bulkDelete()">
                                        <i class="fas fa-trash"></i> Bulk Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Application Details Modal -->
    <div class="modal fade" id="applicationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Application Details - <span id="modalAppId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6>Applicant Information</h6>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <strong>Name:</strong> <span id="modalName"></span>
                                </div>
                                <div class="col-md-6">
                                    <strong>Email:</strong> <span id="modalEmail"></span>
                                </div>
                                <div class="col-md-6">
                                    <strong>Phone:</strong> <span id="modalPhone"></span>
                                </div>
                                <div class="col-md-6">
                                    <strong>Programme:</strong> <span id="modalProgramme"></span>
                                </div>
                            </div>

                            <h6>Application Status</h6>
                            <div class="mb-3">
                                <span class="badge bg-warning" id="modalStatus">Under Review</span>
                                <small class="text-muted ms-2">Last updated: 2024-01-15</small>
                            </div>

                            <h6>Documents</h6>
                            <div class="list-group mb-3">
                                <a href="#" class="list-group-item list-group-item-action">
                                    <i class="fas fa-file-pdf me-2"></i>CV/Resume.pdf
                                    <span class="badge bg-success ms-2">Verified</span>
                                </a>
                                <a href="#" class="list-group-item list-group-item-action">
                                    <i class="fas fa-file-pdf me-2"></i>Academic Transcript.pdf
                                    <span class="badge bg-warning ms-2">Pending</span>
                                </a>
                                <a href="#" class="list-group-item list-group-item-action">
                                    <i class="fas fa-file-pdf me-2"></i>Recommendation Letter 1.pdf
                                    <span class="badge bg-success ms-2">Verified</span>
                                </a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h6>Quick Actions</h6>
                            <div class="d-grid gap-2">
                                <button class="btn btn-success" onclick="approveApplicationModal()">
                                    <i class="fas fa-check"></i> Approve Application
                                </button>
                                <button class="btn btn-danger" onclick="rejectApplicationModal()">
                                    <i class="fas fa-times"></i> Reject Application
                                </button>
                                <button class="btn btn-info" onclick="assignReviewerModal()">
                                    <i class="fas fa-user-plus"></i> Assign Reviewer
                                </button>
                                <button class="btn btn-warning" onclick="requestMoreInfo()">
                                    <i class="fas fa-question-circle"></i> Request More Info
                                </button>
                            </div>

                            <h6 class="mt-3">Comments</h6>
                            <div class="mb-3">
                                <textarea class="form-control" rows="3" placeholder="Add a comment..."></textarea>
                                <button class="btn btn-sm btn-outline-primary mt-2">Add Comment</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="saveChanges()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
    // Sidebar toggle functionality
    document.getElementById('sidebar-toggle').addEventListener('click', function() {
        const sidebar = document.getElementById('sidebar');
        const mainWrapper = document.getElementById('main-wrapper');

        sidebar.classList.toggle('collapsed');
        mainWrapper.classList.toggle('sidebar-collapsed');
    });

    // Select all functionality
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.app-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateBulkActions();
    });

    // Individual checkbox change
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('app-checkbox')) {
            updateBulkActions();
            updateSelectAllState();
        }
    });

    function updateBulkActions() {
        const checkedBoxes = document.querySelectorAll('.app-checkbox:checked');
        const bulkActionsCard = document.getElementById('bulkActionsCard');
        const selectedCount = document.getElementById('selectedCount');

        if (checkedBoxes.length > 0) {
            bulkActionsCard.style.display = 'block';
            selectedCount.textContent = `${checkedBoxes.length} application${checkedBoxes.length > 1 ? 's' : ''} selected`;
        } else {
            bulkActionsCard.style.display = 'none';
        }
    }

    function updateSelectAllState() {
        const allCheckboxes = document.querySelectorAll('.app-checkbox');
        const checkedBoxes = document.querySelectorAll('.app-checkbox:checked');
        const selectAll = document.getElementById('selectAll');

        selectAll.checked = allCheckboxes.length === checkedBoxes.length && allCheckboxes.length > 0;
        selectAll.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < allCheckboxes.length;
    }

    // Application actions
    function viewApplication(appId) {
        // Populate modal with application data
        document.getElementById('modalAppId').textContent = appId;
        // In real implementation, fetch data via AJAX
        const modal = new bootstrap.Modal(document.getElementById('applicationModal'));
        modal.show();
    }

    function editApplication(appId) {
        // Redirect to edit page or open edit modal
        alert(`Edit application: ${appId}`);
    }

    function approveApplication(appId) {
        if (confirm(`Are you sure you want to approve application ${appId}?`)) {
            // AJAX call to approve
            alert(`Application ${appId} approved successfully!`);
            location.reload();
        }
    }

    function rejectApplication(appId) {
        const reason = prompt('Please provide a reason for rejection:');
        if (reason) {
            // AJAX call to reject
            alert(`Application ${appId} rejected.`);
            location.reload();
        }
    }

    function deleteApplication(appId) {
        if (confirm(`Are you sure you want to permanently delete application ${appId}? This action cannot be undone.`)) {
            // AJAX call to delete
            alert(`Application ${appId} deleted.`);
            location.reload();
        }
    }

    // Bulk actions
    function bulkApprove() {
        const selectedApps = Array.from(document.querySelectorAll('.app-checkbox:checked')).map(cb => cb.closest('tr').querySelector('td:nth-child(2)').textContent);
        if (confirm(`Approve ${selectedApps.length} applications?`)) {
            // AJAX bulk approve
            alert(`${selectedApps.length} applications approved successfully!`);
            location.reload();
        }
    }

    function bulkReject() {
        const selectedApps = Array.from(document.querySelectorAll('.app-checkbox:checked')).map(cb => cb.closest('tr').querySelector('td:nth-child(2)').textContent);
        const reason = prompt('Please provide a reason for bulk rejection:');
        if (reason) {
            // AJAX bulk reject
            alert(`${selectedApps.length} applications rejected.`);
            location.reload();
        }
    }

    function bulkAssign() {
        // Open assign reviewer modal
        alert('Bulk assign functionality would open a reviewer selection modal');
    }

    function bulkDelete() {
        const selectedApps = Array.from(document.querySelectorAll('.app-checkbox:checked')).map(cb => cb.closest('tr').querySelector('td:nth-child(2)').textContent);
        if (confirm(`Permanently delete ${selectedApps.length} applications? This action cannot be undone.`)) {
            // AJAX bulk delete
            alert(`${selectedApps.length} applications deleted.`);
            location.reload();
        }
    }

    // Filter functions
    function filterByStatus(status) {
        // Implement filtering logic
        alert(`Filtering by status: ${status}`);
    }

    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('#applicationsTable tbody tr');

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });

    // Other utility functions
    function refreshApplications() {
        location.reload();
    }

    function exportApplications() {
        alert('Exporting applications to CSV...');
        // Implement export functionality
    }

    // Modal action functions
    function approveApplicationModal() {
        approveApplication(document.getElementById('modalAppId').textContent);
        bootstrap.Modal.getInstance(document.getElementById('applicationModal')).hide();
    }

    function rejectApplicationModal() {
        rejectApplication(document.getElementById('modalAppId').textContent);
        bootstrap.Modal.getInstance(document.getElementById('applicationModal')).hide();
    }

    function assignReviewerModal() {
        alert('Assign reviewer modal would open here');
    }

    function requestMoreInfo() {
        alert('Request more information functionality');
    }

    function saveChanges() {
        alert('Changes saved successfully!');
        bootstrap.Modal.getInstance(document.getElementById('applicationModal')).hide();
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateBulkActions();
    });
    </script>
</body>
</html>