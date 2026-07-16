<?php
session_start();
// if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
//     header('Location: /login.php');
//     exit;
// }

require_once 'db.php';

// --- 1. GET DASHBOARD SUMMARY STATS (Global Counts) ---
$stats = ['total' => 0, 'pending' => 0, 'admitted' => 0, 'rejected' => 0]; // Default

if (isset($pdo)) {
    try {
        $statsSql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN current_status IN ('REVIEWER_APPROVED','ADMIN_FINAL_REVIEW') THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'Admitted' THEN 1 ELSE 0 END) as admitted,
                SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
            FROM applications
        ";
        $statsStmt = $pdo->query($statsSql);
        $result = $statsStmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $stats = $result;
        }

        // Note: This assumes decision_made_at is a column in the applications table. 
        // If not, this query would need to be adjusted, for example, by using submitted_at for approximation.
        $todayDecisionsSql = "
            SELECT
                SUM(CASE WHEN status = 'Admitted' THEN 1 ELSE 0 END) as approved_today,
                SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_today
            FROM applications
            WHERE DATE(submitted_at) = CURDATE()
        ";
        $todayStmt = $pdo->query($todayDecisionsSql);
        $todayDecisions = $todayStmt->fetch(PDO::FETCH_ASSOC);
        $todayDecisions['pending_today'] = $stats['pending']; // Assuming pending is global not just for today

        $decisionsSql = "
            SELECT a.application_id, a.application_number, p.surname, p.first_name, pc.course, a.status, pc.department, pc.degree_type
            FROM applications a
            LEFT JOIN personal_details p ON a.application_id = p.application_id
            LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
            WHERE a.current_status IN ('REVIEWER_APPROVED','ADMIN_FINAL_REVIEW')
            ORDER BY a.submitted_at DESC
            LIMIT 10
        ";
        $decisionsStmt = $pdo->query($decisionsSql);
        $decisions = $decisionsStmt->fetchAll(PDO::FETCH_ASSOC);

        $recentDecisionsSql = "
            SELECT a.application_number, p.surname, p.first_name, pc.course, a.status, a.submitted_at, 'Admin' as decided_by
            FROM applications a
            LEFT JOIN personal_details p ON a.application_id = p.application_id
            LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
            WHERE a.status IN ('Admitted', 'Rejected')
            ORDER BY a.submitted_at DESC
            LIMIT 10
        ";
        $recentDecisionsStmt = $pdo->query($recentDecisionsSql);
        $recentDecisions = $recentDecisionsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Dummy programme capacity data. In a real application, this would come from a database table.
        $programmeCapacities = [
            'M.Sc Computer Science' => 20,
            'Ph.D Mathematics' => 15,
            'M.Sc Physics' => 18
        ];

        $programmeAdmittedSql = "
            SELECT pc.course, COUNT(a.application_id) as admitted_count
            FROM applications a
            JOIN programme_choices pc ON a.application_id = pc.application_id
            WHERE a.status = 'Admitted'
            GROUP BY pc.course
        ";
        $programmeAdmittedStmt = $pdo->query($programmeAdmittedSql);
        $programmeAdmittedCounts = $programmeAdmittedStmt->fetchAll(PDO::FETCH_KEY_PAIR);


    } catch (PDOException $e) {
        // Silent fail or log error
    }
}
?>
<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

            <!-- Content Container -->
            <div class="content-container">
                <!-- KPI Cards -->
                <section class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                        <div>
                            <div class="stat-title">Pending Decisions</div>
                            <div class="stat-value" id="pending-decisions"><?php echo number_format($stats['pending']); ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div>
                            <div class="stat-title">Admissions Approved</div>
                            <div class="stat-value" id="approved-admissions"><?php echo number_format($stats['admitted']); ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                        <div>
                            <div class="stat-title">Admissions Rejected</div>
                            <div class="stat-value" id="rejected-admissions"><?php echo number_format($stats['rejected']); ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div>
                            <div class="stat-title">Total Capacity</div>
                            <div class="stat-value" id="total-capacity"><?php echo array_sum($programmeCapacities); ?></div>
                        </div>
                    </div>
                </section>

                <!-- Action Buttons Section -->
                <div class="action-buttons">
                    <button class="btn btn-primary btn-sm" onclick="refreshDecisions()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                    <button class="btn btn-success btn-sm" onclick="generateAdmissionLetters()">
                        <i class="fas fa-envelope"></i> Generate Letters
                    </button>
                </div>

                <div class="card mt-3 mb-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Faculty</label>
                                <select class="form-select" id="filterFaculty"></select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Department</label>
                                <select class="form-select" id="filterDepartment" disabled></select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Programme</label>
                                <select class="form-select" id="filterProgramme" disabled></select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Course</label>
                                <select class="form-select" id="filterCourse" disabled></select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Decision Making Interface -->
                <div class="row">
                    <!-- Applications Awaiting Decision -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Applications Awaiting Final Decision</h5>
                                    <div class="input-group" style="width: 250px;">
                                        <input type="text" class="form-control" id="decisionSearchInput" placeholder="Search applications...">
                                        <button class="btn btn-outline-secondary" type="button">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="decision-queue" id="decisionQueue">
                                    <div class="text-center p-3">
                                        <p class="mb-0 text-muted">Select filters to load eligible applicants.</p>
                                    </div>
                                </div>

                                <!-- Pagination -->
                                <nav aria-label="Decisions pagination" class="mt-3">
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
                    </div>

                    <!-- Decision Summary & Actions Panel -->
                    <div class="col-lg-4">
                        <!-- Today's Decisions -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Today's Decisions</h6>
                            </div>
                            <div class="card-body">
                                <div class="decision-summary">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Approved:</span>
                                        <span class="text-success fw-bold"><?php echo number_format($todayDecisions['approved_today'] ?? 0); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Rejected:</span>
                                        <span class="text-danger fw-bold"><?php echo number_format($todayDecisions['rejected_today'] ?? 0); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Pending:</span>
                                        <span class="text-warning fw-bold"><?php echo number_format($todayDecisions['pending_today'] ?? 0); ?></span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <span>Total Processed:</span>
                                        <span class="fw-bold"><?php echo number_format(($todayDecisions['approved_today'] ?? 0) + ($todayDecisions['rejected_today'] ?? 0)); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Programme Capacity -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">Programme Capacity</h6>
                            </div>
                            <div class="card-body">
                                <div class="capacity-list">
                                    <?php foreach ($programmeCapacities as $programme => $capacity): 
                                        $admitted = $programmeAdmittedCounts[$programme] ?? 0;
                                        $percentage = $capacity > 0 ? ($admitted / $capacity) * 100 : 0;
                                        $progressClass = $percentage > 80 ? 'bg-danger' : ($percentage > 60 ? 'bg-warning' : 'bg-success');
                                    ?>
                                        <div class="capacity-item mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small><?php echo htmlspecialchars($programme); ?></small>
                                                <small><?php echo $admitted; ?>/<?php echo $capacity; ?></small>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar <?php echo $progressClass; ?>" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">Quick Actions</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-success btn-sm" onclick="approveAllHighPriority()">
                                        <i class="fas fa-fast-forward"></i> Approve All High Priority
                                    </button>
                                    <button class="btn btn-warning btn-sm" onclick="sendBulkNotifications()">
                                        <i class="fas fa-envelope"></i> Send Bulk Notifications
                                    </button>
                                    <button class="btn btn-info btn-sm" onclick="exportDecisions()">
                                        <i class="fas fa-download"></i> Export Decisions
                                    </button>
                                    <button class="btn btn-secondary btn-sm" onclick="viewDecisionHistory()">
                                        <i class="fas fa-history"></i> View Decision History
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Decisions Table -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Admission Decisions</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <input class="form-check-input" type="checkbox" id="selectAllRecent">
                                        </th>
                                        <th>Application ID</th>
                                        <th>Applicant</th>
                                        <th>Programme</th>
                                        <th>Decision</th>
                                        <th>Decision Date</th>
                                        <th>Decided By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recentDecisions)): ?>
                                        <?php foreach ($recentDecisions as $decision): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($decision['status'] === 'Admitted'): ?>
                                                        <input class="form-check-input recent-checkbox" type="checkbox" value="<?php echo htmlspecialchars($decision['application_number']); ?>">
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($decision['application_number']); ?></td>
                                                <td><?php echo htmlspecialchars($decision['first_name'] . ' ' . $decision['surname']); ?></td>
                                                <td><?php echo htmlspecialchars($decision['course']); ?></td>
                                                <td>
                                                    <?php 
                                                        $statusClass = $decision['status'] === 'Admitted' ? 'bg-success' : 'bg-danger';
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($decision['status']); ?></span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($decision['submitted_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($decision['decided_by']); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-info" onclick="viewDecisionDetails('<?php echo htmlspecialchars($decision['application_number']); ?>')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="revokeDecision('<?php echo htmlspecialchars($decision['application_number']); ?>')">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="8" class="text-center">No recent decisions found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <!-- Decision Confirmation Modal -->
    <div class="modal fade" id="decisionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Admission Decision</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="decisionModalContent">
                        <!-- Content will be populated by JavaScript -->
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="decisionNotes" rows="3" placeholder="Add any additional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmDecisionBtn" onclick="confirmDecision()">Confirm Decision</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Sidebar toggle functionality
    document.getElementById('sidebar-toggle').addEventListener('click', function() {
        const sidebar = document.getElementById('sidebar');
        const mainWrapper = document.getElementById('main-wrapper');

        sidebar.classList.toggle('collapsed');
        mainWrapper.classList.toggle('sidebar-collapsed');
    });


    const filters = {
        faculty: document.getElementById('filterFaculty'),
        department: document.getElementById('filterDepartment'),
        programme: document.getElementById('filterProgramme'),
        course: document.getElementById('filterCourse')
    };
    const decisionQueue = document.getElementById('decisionQueue');

    document.addEventListener('DOMContentLoaded', function() {
        loadFaculties();
        const selectAllRecent = document.getElementById('selectAllRecent');
        const recentCheckboxes = document.querySelectorAll('.recent-checkbox');

        if(selectAllRecent) {
            selectAllRecent.addEventListener('change', function() {
                recentCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        }
    });

    let currentDecision = { appId: null, appNumber: null, type: null };

    // Decision making functions
    function makeDecision(appId, type, appNumber) {
        currentDecision = { appId, appNumber, type };

        const modal = new bootstrap.Modal(document.getElementById('decisionModal'));
        const modalContent = document.getElementById('decisionModalContent');
        const confirmBtn = document.getElementById('confirmDecisionBtn');

        const displayId = appNumber || appId;
        if (type === 'approve') {
            modalContent.innerHTML = `
                <div class="alert alert-success">
                    <h6>Approve Admission for ${displayId}</h6>
                    <p>Are you sure you want to approve this application? The applicant will be offered admission to their chosen programme.</p>
                </div>
            `;
            confirmBtn.className = 'btn btn-success';
            confirmBtn.textContent = 'Approve Admission';
        } else {
            modalContent.innerHTML = `
                <div class="alert alert-danger">
                    <h6>Reject Admission for ${displayId}</h6>
                    <p>Are you sure you want to reject this application? This action cannot be easily reversed.</p>
                </div>
            `;
            confirmBtn.className = 'btn btn-danger';
            confirmBtn.textContent = 'Reject Admission';
        }

        modal.show();
    }

    function confirmDecision() {
        const notes = document.getElementById('decisionNotes').value;

        fetch('includes/process_decision.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `app_id=${encodeURIComponent(currentDecision.appId)}&decision=${encodeURIComponent(currentDecision.type)}&notes=${encodeURIComponent(notes)}&ajax=1`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An unexpected error occurred.');
        });

        bootstrap.Modal.getInstance(document.getElementById('decisionModal')).hide();
    }

    // Other functions
    function viewFullApplication(appId) {
        // Open full application view
        window.open(`/ADMIN/view.php?app_no=${appId}`, '_blank');
    }

    function viewDecisionDetails(appId) {
        // Show decision details modal
        alert(`Viewing decision details for ${appId}`);
    }

    function revokeDecision(appId) {
        if (confirm(`Are you sure you want to revoke the decision for ${appId}?`)) {
            // AJAX call to revoke
            alert(`Decision revoked for ${appId}`);
            location.reload();
        }
    }

    // Bulk actions
    function approveAllHighPriority() {
        if (confirm('Are you sure you want to approve all high-priority applications? This action cannot be undone.')) {
            fetch('approve-high-priority.php', {
                method: 'POST',
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An unexpected error occurred during the bulk approval process.');
            });
        }
    }

    function sendBulkNotifications() {
        const selectedApps = document.querySelectorAll('.recent-checkbox:checked');
        if (selectedApps.length === 0) {
            alert('Please select at least one applicant to send notifications.');
            return;
        }

        if (confirm(`Are you sure you want to send notifications to ${selectedApps.length} selected applicant(s)?`)) {
            const appNumbers = Array.from(selectedApps).map(app => app.value);

            fetch('send-bulk-notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `app_numbers=${JSON.stringify(appNumbers)}`
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An unexpected error occurred during the bulk notification process.');
            });
        }
    }

    function exportDecisions() {
        // Implementation for export
        alert('Decisions exported');
    }

    function viewDecisionHistory() {
        // Implementation for history view
        alert('Opening decision history');
    }

    // Filter functions
    async function loadFaculties() {
        const data = await fetchJson('api/admission-decisions.php?action=faculties');
        setOptions(filters.faculty, data, 'Select Faculty');
        filters.faculty.disabled = false;
        filters.faculty.addEventListener('change', onFacultyChange);
    }

    async function onFacultyChange() {
        disableLower('department');
        if (!filters.faculty.value) return;
        const data = await fetchJson(`api/admission-decisions.php?action=departments&faculty_id=${filters.faculty.value}`);
        setOptions(filters.department, data, 'Select Department');
        filters.department.disabled = false;
        filters.department.addEventListener('change', onDepartmentChange);
    }

    async function onDepartmentChange() {
        disableLower('programme');
        if (!filters.department.value) return;
        const data = await fetchJson(`api/admission-decisions.php?action=programmes&faculty_id=${filters.faculty.value}&department_id=${filters.department.value}`);
        setOptions(filters.programme, data, 'Select Programme');
        filters.programme.disabled = false;
        filters.programme.addEventListener('change', onProgrammeChange);
    }

    async function onProgrammeChange() {
        disableLower('course');
        if (!filters.programme.value) return;
        const data = await fetchJson(`api/admission-decisions.php?action=courses&faculty_id=${filters.faculty.value}&department_id=${filters.department.value}&programme_id=${filters.programme.value}`);
        setOptions(filters.course, data, 'Select Course');
        filters.course.disabled = false;
        filters.course.addEventListener('change', loadEligible);
    }

    async function loadEligible() {
        if (!filters.course.value) return;
        const url = `api/admission-decisions.php?action=eligible_students&faculty_id=${filters.faculty.value}&department_id=${filters.department.value}&programme_id=${filters.programme.value}&course_id=${filters.course.value}`;
        const data = await fetchJson(url);
        renderDecisionQueue(data);
    }

    function renderDecisionQueue(list) {
        if (!Array.isArray(list) || list.length === 0) {
            decisionQueue.innerHTML = '<div class="text-center p-3"><p class="mb-0 text-muted">No eligible applicants found.</p></div>';
            return;
        }
        decisionQueue.innerHTML = list.map(item => `
            <div class="decision-item border rounded p-3 mb-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-2">
                            <div class="avatar-circle me-3"><i class="fas fa-user"></i></div>
                            <div>
                                <h6 class="mb-0">${item.full_name}</h6>
                                <small class="text-muted">${item.application_number}</small>
                            </div>
                        </div>
                        <div class="row g-2 text-sm mb-2">
                            <div class="col-md-6"><strong>Programme:</strong> ${item.programme}</div>
                            <div class="col-md-6"><strong>Course:</strong> ${item.course}</div>
                        </div>
                    </div>
                    <div class="ms-3">
                        <div class="btn-group-vertical">
                            <button class="btn btn-sm btn-success" onclick="makeDecision('${item.application_id}', 'approve', '${item.application_number}')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="makeDecision('${item.application_id}', 'reject', '${item.application_number}')">
                                <i class="fas fa-times"></i> Reject
                            </button>
                            <a href="/ADMIN/view.php?app_no=${encodeURIComponent(item.application_number)}" class="btn btn-sm btn-info" target="_blank">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function setOptions(selectEl, items, placeholder) {
        selectEl.innerHTML = '';
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = placeholder;
        selectEl.appendChild(defaultOption);
        items.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.name;
            selectEl.appendChild(opt);
        });
    }

    function disableLower(level) {
        if (level === 'department') {
            filters.department.disabled = true;
            filters.department.innerHTML = '';
            filters.programme.disabled = true;
            filters.programme.innerHTML = '';
            filters.course.disabled = true;
            filters.course.innerHTML = '';
        }
        if (level === 'programme') {
            filters.programme.disabled = true;
            filters.programme.innerHTML = '';
            filters.course.disabled = true;
            filters.course.innerHTML = '';
        }
        if (level === 'course') {
            filters.course.disabled = true;
            filters.course.innerHTML = '';
        }
        decisionQueue.innerHTML = '<div class="text-center p-3"><p class="mb-0 text-muted">Select filters to load eligible applicants.</p></div>';
    }

    async function fetchJson(url) {
        const res = await fetch(url);
        const data = await res.json();
        return data.data || data;
    }

    const decisionSearchInput = document.getElementById('decisionSearchInput');
    if (decisionSearchInput) {
        decisionSearchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            document.querySelectorAll('#decisionQueue .decision-item').forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(term) ? '' : 'none';
            });
        });
    }

    // Utility functions
    function refreshDecisions() {
        if (filters.course.value) {
            loadEligible();
        } else {
            decisionQueue.innerHTML = '<div class="text-center p-3"><p class="mb-0 text-muted">Select filters to load eligible applicants.</p></div>';
        }
    }

    function generateAdmissionLetters() {
        const selectedApps = document.querySelectorAll('.recent-checkbox:checked');
        if (selectedApps.length === 0) {
            alert('Please select at least one admitted applicant to generate a letter.');
            return;
        }

        selectedApps.forEach(app => {
            const appNo = app.value;
            window.open(`generate-letter.php?app_no=${appNo}`, '_blank');
        });
    }

    // Add custom styling
    const style = document.createElement('style');
    style.textContent = `
        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }
        .text-sm {
            font-size: 0.875rem;
        }
        .reviewer-feedback {
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            margin-top: 8px;
        }
        .capacity-item {
            font-size: 0.875rem;
        }
    `;
    document.head.appendChild(style);
    </script>
<?php require_once 'includes/footer.php'; ?>
