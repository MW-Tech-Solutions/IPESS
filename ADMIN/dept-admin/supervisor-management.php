<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'DEPARTMENT_ADMIN') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'Supervisor Management';
$pageSubtitle = 'Manage supervisor assignments, workload balance, and activity.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Supervisor Management</h1>
        <p class="panel-muted">Track supervisor capacity, specializations, and workload distribution.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" onclick="refreshSupervisors()"><i class="fas fa-sync me-2"></i>Refresh</button>
        <button class="btn btn-primary" onclick="addSupervisor()"><i class="fas fa-plus me-2"></i>Add Supervisor</button>
        <div class="dropdown">
            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-filter me-2"></i>Filter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="filterByStatus('all')">All Supervisors</a></li>
                <li><a class="dropdown-item" href="#" onclick="filterByStatus('active')">Active</a></li>
                <li><a class="dropdown-item" href="#" onclick="filterByStatus('inactive')">Inactive</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="filterByWorkload('all')">All Workloads</a></li>
                <li><a class="dropdown-item" href="#" onclick="filterByWorkload('available')">Available</a></li>
                <li><a class="dropdown-item" href="#" onclick="filterByWorkload('busy')">Busy</a></li>
            </ul>
        </div>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div>
            <div class="stat-title">Total Supervisors</div>
            <div class="stat-value" id="total-supervisors">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        <div>
            <div class="stat-title">Active Supervisors</div>
            <div class="stat-value" id="active-supervisors">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
        <div>
            <div class="stat-title">Available Capacity</div>
            <div class="stat-value" id="available-supervisors">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        <div>
            <div class="stat-title">Avg Students</div>
            <div class="stat-value" id="avg-students">0.0</div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Computer Science Supervisors</h3>
            <div class="panel-muted">Manage assignments and view workload by supervisor.</div>
        </div>
        <div class="input-group" style="max-width: 280px;">
            <input type="text" class="form-control" id="searchInput" placeholder="Search supervisors...">
            <button class="btn btn-outline-primary" type="button"><i class="fas fa-search"></i></button>
        </div>
    </div>
    <div class="panel-body">
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="supervisor-grid" id="supervisorGrid"></div>
            </div>
            <div class="col-lg-4">
                <div class="panel mb-3">
                    <div class="panel-header">
                        <div>
                            <h3 class="panel-title">Workload Distribution</h3>
                            <div class="panel-muted">Current supervisor capacity split.</div>
                        </div>
                    </div>
                    <div class="panel-body">
                        <canvas id="workloadChart" height="200"></canvas>
                        <div class="mt-3 small">
                            <div class="d-flex justify-content-between mb-1"><span><i class="fas fa-circle text-success me-1"></i>Available (0-3)</span><span id="legend-available">0 supervisors</span></div>
                            <div class="d-flex justify-content-between mb-1"><span><i class="fas fa-circle text-info me-1"></i>Moderate (4-6)</span><span id="legend-moderate">0 supervisors</span></div>
                            <div class="d-flex justify-content-between mb-1"><span><i class="fas fa-circle text-warning me-1"></i>Busy (7-8)</span><span id="legend-busy">0 supervisors</span></div>
                            <div class="d-flex justify-content-between mb-1"><span><i class="fas fa-circle text-danger me-1"></i>Overloaded (9+)</span><span id="legend-overloaded">0 supervisors</span></div>
                        </div>
                    </div>
                </div>

                <div class="panel mb-3">
                    <div class="panel-header">
                        <div>
                            <h3 class="panel-title">Quick Actions</h3>
                            <div class="panel-muted">Tools for supervisor workload management.</div>
                        </div>
                    </div>
                    <div class="panel-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-success btn-sm" onclick="balanceWorkload()"><i class="fas fa-balance-scale"></i> Balance Workload</button>
                            <button class="btn btn-info btn-sm" onclick="sendBulkNotification()"><i class="fas fa-envelope"></i> Bulk Notification</button>
                            <button class="btn btn-warning btn-sm" onclick="exportSupervisorList()"><i class="fas fa-download"></i> Export List</button>
                            <button class="btn btn-secondary btn-sm" onclick="viewSupervisorReports()"><i class="fas fa-chart-bar"></i> Supervisor Reports</button>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h3 class="panel-title">Recent Activities</h3>
                            <div class="panel-muted">Latest supervisor updates.</div>
                        </div>
                    </div>
                    <div class="panel-body">
                        <div class="activity-list">
                            <div class="text-muted">No data yet.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Supervisor Details</h3>
            <div class="panel-muted">Roster overview and capacity status.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Title</th>
                        <th>Specialization</th>
                        <th>Current Students</th>
                        <th>Max Capacity</th>
                        <th>Status</th>
                        <th>Last Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="supervisorTable"></tbody>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="supervisorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Supervisor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="supervisorForm">
                    <input type="hidden" id="supervisorId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Academic Title</label>
                            <select class="form-select" id="title" required>
                                <option value="">Select title...</option>
                                <option value="professor">Professor</option>
                                <option value="associate_professor">Associate Professor</option>
                                <option value="senior_lecturer">Senior Lecturer</option>
                                <option value="lecturer">Lecturer</option>
                                <option value="assistant_lecturer">Assistant Lecturer</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Specialization</label>
                            <input type="text" class="form-control" id="specialization" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Max Student Capacity</label>
                            <input type="number" class="form-control" id="maxCapacity" value="8" min="1" max="15" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Current Students</label>
                            <input type="number" class="form-control" id="currentStudents" value="0" min="0" max="30" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Research Interests</label>
                            <textarea class="form-control" id="researchInterests" rows="3" placeholder="List research interests and expertise areas..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" rows="2" placeholder="Any additional information..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveSupervisor()">Save Supervisor</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="viewSupervisorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Supervisor Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Full Name</div>
                        <div class="fw-bold" id="viewFullName">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Title</div>
                        <div class="fw-bold" id="viewTitle">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Specialization</div>
                        <div class="fw-bold" id="viewSpecialization">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Status</div>
                        <div class="fw-bold" id="viewStatus">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Email</div>
                        <div class="fw-bold" id="viewEmail">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Phone</div>
                        <div class="fw-bold" id="viewPhone">-</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Research Interests</div>
                        <div class="fw-bold" id="viewResearch">-</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Notes</div>
                        <div class="fw-bold" id="viewNotes">-</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="workloadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Supervisor Workload</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between mb-2">
                    <span id="workloadLabel">-</span>
                    <span id="workloadValue">-</span>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar" id="workloadBar" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="notificationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Notification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="notifySupervisorId">
                <div class="mb-3">
                    <label class="form-label">Message</label>
                    <textarea class="form-control" id="notifyMessage" rows="3" placeholder="Compose a quick update..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="sendSupervisorNotification()">Send</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let supervisors = [];
let workloadChart = null;
const filters = { status: 'all', workload: 'all', search: '' };

document.addEventListener('DOMContentLoaded', () => {
    loadSupervisors();
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filters.search = this.value.toLowerCase();
            applyFilters();
        });
    }
});

async function loadSupervisors() {
    const res = await fetch('api/supervisors.php?action=list');
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to load supervisors.');
        return;
    }
    supervisors = data.data || [];
    applyFilters();
}

function applyFilters() {
    let list = [...supervisors];
    if (filters.status !== 'all') {
        list = list.filter(item => item.status && item.status.toLowerCase() === filters.status);
    }
    if (filters.workload !== 'all') {
        list = list.filter(item => {
            const current = Number(item.current_students || 0);
            const max = Number(item.max_capacity || 0);
            const ratio = max > 0 ? current / max : 0;
            if (filters.workload === 'available') return ratio < 0.5;
            if (filters.workload === 'busy') return ratio >= 0.8;
            return true;
        });
    }
    if (filters.search) {
        list = list.filter(item => {
            const text = `${item.full_name} ${item.title} ${item.specialization} ${item.email}`.toLowerCase();
            return text.includes(filters.search);
        });
    }
    renderSupervisors(list);
}

function renderSupervisors(list) {
    const grid = document.getElementById('supervisorGrid');
    const table = document.getElementById('supervisorTable');
    if (!grid || !table) return;

    if (list.length === 0) {
        grid.innerHTML = '<div class="text-muted">No data yet.</div>';
        table.innerHTML = '<tr><td colspan="8" class="text-muted">No data yet.</td></tr>';
    } else {
        grid.innerHTML = list.map(renderCard).join('');
        table.innerHTML = list.map(renderTableRow).join('');
    }
    updateStats(list);
    updateChart(list);
}

function renderCard(item) {
    const statusClass = statusChipClass(item.status);
    const current = Number(item.current_students || 0);
    const max = Number(item.max_capacity || 0);
    const percent = max > 0 ? Math.min((current / max) * 100, 100) : 0;
    const progressClass = percent >= 80 ? 'bg-danger' : percent >= 60 ? 'bg-warning' : 'bg-success';
    const actionLabel = (item.status || '').toLowerCase() === 'inactive' ? 'Activate' : 'Deactivate';
    const actionStatus = (item.status || '').toLowerCase() === 'inactive' ? 'Active' : 'Inactive';

    return `
        <div class="supervisor-card border rounded-4 p-3 mb-3 bg-white">
            <div class="d-flex justify-content-between align-items-start">
                <div class="d-flex align-items-center flex-grow-1">
                    <div class="supervisor-avatar me-3"><i class="fas fa-user-tie"></i></div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">${item.full_name}</h6>
                        <p class="mb-1 small text-muted">${item.title || 'Supervisor'} | ${item.specialization || 'General'}</p>
                        <div class="supervisor-stats">
                            <span class="status-chip ${statusClass} me-1">${item.status || 'Active'}</span>
                            <small class="text-muted">${current} students</small>
                        </div>
                    </div>
                </div>
                <div class="supervisor-actions">
                    <div class="btn-group-vertical">
                        <button class="btn btn-sm btn-outline-primary" onclick="viewSupervisor('${item.supervisor_id}')" title="View Profile"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-sm btn-outline-primary" onclick="editSupervisor('${item.supervisor_id}')" title="Edit"><i class="fas fa-edit"></i></button>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="assignStudents('${item.supervisor_id}')">Assign Students</a></li>
                                <li><a class="dropdown-item" href="#" onclick="viewWorkload('${item.supervisor_id}')">View Workload</a></li>
                                <li><a class="dropdown-item" href="#" onclick="sendNotification('${item.supervisor_id}')">Send Notification</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-warning" href="#" onclick="setSupervisorStatus('${item.supervisor_id}', '${actionStatus}')">${actionLabel}</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="supervisor-workload mt-2">
                <div class="d-flex justify-content-between small mb-1"><span>Workload</span><span>${current}/${max} students</span></div>
                <div class="progress" style="height: 6px;"><div class="progress-bar ${progressClass}" style="width: ${percent}%"></div></div>
            </div>
        </div>
    `;
}

function renderTableRow(item) {
    const statusClass = statusChipClass(item.status);
    const current = Number(item.current_students || 0);
    const max = Number(item.max_capacity || 0);
    return `
        <tr>
            <td>${item.full_name}</td>
            <td>${item.title || '-'}</td>
            <td>${item.specialization || '-'}</td>
            <td>${current}</td>
            <td>${max}</td>
            <td><span class="status-chip ${statusClass}">${item.status || 'Active'}</span></td>
            <td>${item.last_active || 'recently'}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewSupervisor('${item.supervisor_id}')"><i class="fas fa-eye"></i></button>
                <button class="btn btn-sm btn-outline-primary" onclick="editSupervisor('${item.supervisor_id}')"><i class="fas fa-edit"></i></button>
            </td>
        </tr>
    `;
}

function statusChipClass(status) {
    const value = (status || '').toLowerCase();
    if (value === 'inactive') return 'status-warning';
    if (value === 'active') return 'status-success';
    return 'status-muted';
}

function updateStats(list) {
    const total = list.length;
    const active = list.filter(item => (item.status || '').toLowerCase() === 'active').length;
    const available = list.filter(item => Number(item.current_students || 0) < Number(item.max_capacity || 0)).length;
    const avg = total > 0 ? (list.reduce((sum, item) => sum + Number(item.current_students || 0), 0) / total).toFixed(1) : '0.0';

    const totalEl = document.getElementById('total-supervisors');
    const activeEl = document.getElementById('active-supervisors');
    const availableEl = document.getElementById('available-supervisors');
    const avgEl = document.getElementById('avg-students');
    if (totalEl) totalEl.textContent = total;
    if (activeEl) activeEl.textContent = active;
    if (availableEl) availableEl.textContent = available;
    if (avgEl) avgEl.textContent = avg;
}

function updateChart(list) {
    const buckets = [0, 0, 0, 0];
    list.forEach(item => {
        const count = Number(item.current_students || 0);
        if (count <= 3) buckets[0] += 1;
        else if (count <= 6) buckets[1] += 1;
        else if (count <= 8) buckets[2] += 1;
        else buckets[3] += 1;
    });

    const availableEl = document.getElementById('legend-available');
    const moderateEl = document.getElementById('legend-moderate');
    const busyEl = document.getElementById('legend-busy');
    const overloadedEl = document.getElementById('legend-overloaded');
    if (availableEl) availableEl.textContent = `${buckets[0]} supervisors`;
    if (moderateEl) moderateEl.textContent = `${buckets[1]} supervisors`;
    if (busyEl) busyEl.textContent = `${buckets[2]} supervisors`;
    if (overloadedEl) overloadedEl.textContent = `${buckets[3]} supervisors`;

    const ctx = document.getElementById('workloadChart').getContext('2d');
    if (!workloadChart) {
        workloadChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Available', 'Moderate', 'Busy', 'Overloaded'],
                datasets: [{
                    data: buckets,
                    backgroundColor: ['#198754', '#0dcaf0', '#ffc107', '#dc3545']
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
    } else {
        workloadChart.data.datasets[0].data = buckets;
        workloadChart.update();
    }
}

function getSupervisor(id) {
    return supervisors.find(item => item.supervisor_id === id);
}

function viewSupervisor(supId) {
    const item = getSupervisor(supId);
    if (!item) return;
    document.getElementById('viewFullName').textContent = item.full_name || '-';
    document.getElementById('viewTitle').textContent = item.title || '-';
    document.getElementById('viewSpecialization').textContent = item.specialization || '-';
    document.getElementById('viewStatus').textContent = item.status || '-';
    document.getElementById('viewEmail').textContent = item.email || '-';
    document.getElementById('viewPhone').textContent = item.phone || '-';
    document.getElementById('viewResearch').textContent = item.research_interests || '-';
    document.getElementById('viewNotes').textContent = item.notes || '-';
    new bootstrap.Modal(document.getElementById('viewSupervisorModal')).show();
}

function editSupervisor(supId) {
    const item = getSupervisor(supId);
    if (!item) return;
    document.getElementById('modalTitle').textContent = 'Edit Supervisor';
    document.getElementById('supervisorId').value = item.supervisor_id || '';
    document.getElementById('firstName').value = (item.full_name || '').split(' ')[0] || '';
    document.getElementById('lastName').value = (item.full_name || '').split(' ').slice(1).join(' ');
    document.getElementById('email').value = item.email || '';
    document.getElementById('phone').value = item.phone || '';
    document.getElementById('title').value = (item.title || '').toLowerCase().replace(/\s+/g, '_');
    document.getElementById('specialization').value = item.specialization || '';
    document.getElementById('maxCapacity').value = item.max_capacity || 8;
    document.getElementById('currentStudents').value = item.current_students || 0;
    document.getElementById('status').value = (item.status || 'Active').toLowerCase();
    document.getElementById('researchInterests').value = item.research_interests || '';
    document.getElementById('notes').value = item.notes || '';
    new bootstrap.Modal(document.getElementById('supervisorModal')).show();
}

function assignStudents(supId) {
    editSupervisor(supId);
}

function viewWorkload(supId) {
    const item = getSupervisor(supId);
    if (!item) return;
    const current = Number(item.current_students || 0);
    const max = Number(item.max_capacity || 0);
    const percent = max > 0 ? Math.min((current / max) * 100, 100) : 0;
    document.getElementById('workloadLabel').textContent = item.full_name;
    document.getElementById('workloadValue').textContent = `${current}/${max} students`;
    const bar = document.getElementById('workloadBar');
    bar.style.width = `${percent}%`;
    bar.className = `progress-bar ${percent >= 80 ? 'bg-danger' : percent >= 60 ? 'bg-warning' : 'bg-success'}`;
    new bootstrap.Modal(document.getElementById('workloadModal')).show();
}

function sendNotification(supId) {
    document.getElementById('notifySupervisorId').value = supId;
    document.getElementById('notifyMessage').value = '';
    new bootstrap.Modal(document.getElementById('notificationModal')).show();
}

async function sendSupervisorNotification() {
    const id = document.getElementById('notifySupervisorId').value;
    const message = document.getElementById('notifyMessage').value.trim();
    if (!message) {
        alert('Please enter a message.');
        return;
    }
    const formData = new FormData();
    formData.append('action', 'notify');
    formData.append('supervisor_id', id);
    formData.append('message', message);
    const res = await fetch('api/supervisors.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to send notification.');
        return;
    }
    bootstrap.Modal.getInstance(document.getElementById('notificationModal')).hide();
}

async function setSupervisorStatus(supId, status) {
    const formData = new FormData();
    formData.append('action', 'status');
    formData.append('supervisor_id', supId);
    formData.append('status', status);
    const res = await fetch('api/supervisors.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to update status.');
        return;
    }
    loadSupervisors();
}

function deactivateSupervisor(supId) {
    setSupervisorStatus(supId, 'Inactive');
}

function addSupervisor() {
    document.getElementById('modalTitle').textContent = 'Add New Supervisor';
    document.getElementById('supervisorForm').reset();
    document.getElementById('supervisorId').value = '';
    new bootstrap.Modal(document.getElementById('supervisorModal')).show();
}

async function saveSupervisor() {
    const form = document.getElementById('supervisorForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    const supervisorId = document.getElementById('supervisorId').value || `SUP-${Date.now()}`;
    const fullName = `${document.getElementById('firstName').value.trim()} ${document.getElementById('lastName').value.trim()}`.trim();

    const formData = new FormData();
    formData.append('action', 'save');
    formData.append('supervisor_id', supervisorId);
    formData.append('full_name', fullName);
    formData.append('title', document.getElementById('title').selectedOptions[0]?.textContent || '');
    formData.append('specialization', document.getElementById('specialization').value);
    formData.append('max_capacity', document.getElementById('maxCapacity').value);
    formData.append('current_students', document.getElementById('currentStudents').value);
    formData.append('status', document.getElementById('status').value === 'inactive' ? 'Inactive' : 'Active');
    formData.append('email', document.getElementById('email').value);
    formData.append('phone', document.getElementById('phone').value);
    formData.append('research_interests', document.getElementById('researchInterests').value);
    formData.append('notes', document.getElementById('notes').value);
    formData.append('last_active', 'just now');

    const res = await fetch('api/supervisors.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to save supervisor.');
        return;
    }
    bootstrap.Modal.getInstance(document.getElementById('supervisorModal')).hide();
    loadSupervisors();
}

async function balanceWorkload() {
    const res = await fetch('api/supervisors.php', { method: 'POST', body: new URLSearchParams({ action: 'balance' }) });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to balance workload.');
        return;
    }
    loadSupervisors();
}

function sendBulkNotification() {
    if (supervisors.length === 0) {
        alert('No supervisors available.');
        return;
    }
    sendNotification(supervisors[0].supervisor_id);
}

function exportSupervisorList() {
    window.location.href = 'api/supervisors.php?action=export';
}

function viewSupervisorReports() {
    window.location.href = 'department-reports.php';
}

function filterByStatus(status) {
    filters.status = status;
    applyFilters();
}

function filterByWorkload(workload) {
    filters.workload = workload;
    applyFilters();
}

function refreshSupervisors() {
    loadSupervisors();
}

const style = document.createElement('style');
style.textContent = `
    .supervisor-avatar { width: 50px; height: 50px; border-radius: 50%; background-color: #e9ecef; display: flex; align-items: center; justify-content: center; color: #6c757d; }
    .supervisor-workload { margin-top: 10px; }
    .activity-item { padding: 4px 0; border-left: 2px solid #e9ecef; padding-left: 8px; margin-left: 8px; }
`;
if (!document.querySelector('style[data-app="dept-supervisors"]')) {
    style.setAttribute('data-app', 'dept-supervisors');
    document.head.appendChild(style);
}
</script>

<?php require_once 'includes/footer.php'; ?>
