<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'DEPARTMENT_ADMIN') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'Student Management';
$pageSubtitle = 'Monitor student progress, supervision assignments, and completion status.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Student Management</h1>
        <p class="panel-muted">Track postgraduate students, progress milestones, and supervision activity.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" onclick="refreshStudents()"><i class="fas fa-sync me-2"></i>Refresh</button>
        <button class="btn btn-primary" onclick="addStudent()"><i class="fas fa-plus me-2"></i>Add Student</button>
        <div class="dropdown">
            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-filter me-2"></i>Filter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="filterByProgramme('all')">All Programmes</a></li>
                <li><a class="dropdown-item" href="#" onclick="filterByProgramme('msc')">MSc</a></li>
                <li><a class="dropdown-item" href="#" onclick="filterByProgramme('phd')">PhD</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="filterByStatus('all')">All Status</a></li>
                <li><a class="dropdown-item" href="#" onclick="filterByStatus('active')">Active</a></li>
                <li><a class="dropdown-item" href="#" onclick="filterByStatus('inactive')">Inactive</a></li>
                <li><a class="dropdown-item" href="#" onclick="filterByStatus('graduated')">Graduated</a></li>
            </ul>
        </div>
        <button class="btn btn-info" onclick="bulkActions()"><i class="fas fa-tasks me-2"></i>Bulk Actions</button>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
        <div>
            <div class="stat-title">Total Students</div>
            <div class="stat-value" id="total-students">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        <div>
            <div class="stat-title">Active Students</div>
            <div class="stat-value" id="active-students">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
        <div>
            <div class="stat-title">Graduating</div>
            <div class="stat-value" id="graduating-students">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        <div>
            <div class="stat-title">Completion Rate</div>
            <div class="stat-value" id="completion-rate">0%</div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Computer Science Students</h3>
            <div class="panel-muted">Roster and progress overview for department students.</div>
        </div>
        <div class="input-group" style="max-width: 320px;">
            <input type="text" class="form-control" id="searchInput" placeholder="Search students...">
            <button class="btn btn-outline-primary" type="button"><i class="fas fa-search"></i></button>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="studentsTable">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Programme</th>
                        <th>Supervisor</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Last Activity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="studentsTableBody"></tbody>
            </table>
        </div>

        <nav aria-label="Student pagination" class="mt-3">
            <ul class="pagination justify-content-center">
                <li class="page-item disabled"><span class="page-link">Previous</span></li>
                <li class="page-item active"><span class="page-link">1</span></li>
                <li class="page-item"><a class="page-link" href="#">2</a></li>
                <li class="page-item"><a class="page-link" href="#">3</a></li>
                <li class="page-item"><a class="page-link" href="#">Next</a></li>
            </ul>
        </nav>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Programme & Progress Overview</h3>
            <div class="panel-muted">Distribution across programmes and completion stages.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="border rounded-4 p-3 bg-white">
                    <h6 class="mb-3">Programme Distribution</h6>
                    <canvas id="programmeChart" height="200"></canvas>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="border rounded-4 p-3 bg-white">
                    <h6 class="mb-3">Progress Distribution</h6>
                    <canvas id="progressChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Recent Student Activities</h3>
            <div class="panel-muted">Latest submissions, approvals, and student updates.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="activity-timeline">
            <div class="text-muted">No data yet.</div>
        </div>
    </div>
</section>

<div class="modal fade" id="studentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="studentForm">
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
                            <label class="form-label">Student ID</label>
                            <input type="text" class="form-control" id="studentId" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Programme</label>
                            <select class="form-select" id="programme" required>
                                <option value="">Select programme...</option>
                                <option value="msc_cs">MSc Computer Science</option>
                                <option value="msc_se">MSc Software Engineering</option>
                                <option value="phd_cs">PhD Computer Science</option>
                                <option value="phd_ai">PhD Artificial Intelligence</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Supervisor</label>
                            <select class="form-select" id="supervisor" required>
                                <option value="">No data yet</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="startDate" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expected Completion</label>
                            <input type="date" class="form-control" id="completionDate" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                                <option value="graduated">Graduated</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Research Topic</label>
                            <textarea class="form-control" id="researchTopic" rows="2" placeholder="Student's research topic..."></textarea>
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
                <button type="button" class="btn btn-primary" onclick="saveStudent()">Save Student</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bulkActionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Actions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="selectedCount">0 students selected</p>
                <div class="d-grid gap-2">
                    <button class="btn btn-warning" onclick="bulkChangeSupervisor()"><i class="fas fa-exchange-alt"></i> Change Supervisor</button>
                    <button class="btn btn-info" onclick="bulkSendMessage()"><i class="fas fa-envelope"></i> Send Message</button>
                    <button class="btn btn-success" onclick="bulkUpdateStatus()"><i class="fas fa-edit"></i> Update Status</button>
                    <button class="btn btn-danger" onclick="bulkSuspend()"><i class="fas fa-ban"></i> Suspend Students</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="viewStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Student Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Full Name</div>
                        <div class="fw-bold" id="viewStudentName">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Student ID</div>
                        <div class="fw-bold" id="viewStudentId">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Programme</div>
                        <div class="fw-bold" id="viewStudentProgramme">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Supervisor</div>
                        <div class="fw-bold" id="viewStudentSupervisor">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Email</div>
                        <div class="fw-bold" id="viewStudentEmail">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Status</div>
                        <div class="fw-bold" id="viewStudentStatus">-</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Research Topic</div>
                        <div class="fw-bold" id="viewStudentTopic">-</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Notes</div>
                        <div class="fw-bold" id="viewStudentNotes">-</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="messageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="messageStudentId">
                <div class="mb-3">
                    <label class="form-label">Message</label>
                    <textarea class="form-control" id="messageContent" rows="3" placeholder="Type your message..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="sendStudentMessage()">Send</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="progressModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Student Progress</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between mb-2">
                    <span id="progressLabel">-</span>
                    <span id="progressValue">-</span>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar" id="progressBar" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let students = [];
let programmeChart = null;
let progressChart = null;
const filters = { programme: 'all', status: 'all', search: '' };

document.addEventListener('DOMContentLoaded', () => {
    loadStudents();
    loadSupervisorsForSelect();
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filters.search = this.value.toLowerCase();
            applyFilters();
        });
    }
});

async function loadSupervisorsForSelect() {
    const select = document.getElementById('supervisor');
    if (!select) return;
    const res = await fetch('api/supervisors.php?action=list');
    const data = await res.json();
    const list = data.success ? data.data : [];
    if (list.length === 0) {
        select.innerHTML = '<option value="">No data yet</option>';
        return;
    }
    select.innerHTML = '<option value="">Select supervisor...</option>';
    list.forEach(item => {
        const option = document.createElement('option');
        option.value = item.supervisor_id;
        option.textContent = item.full_name;
        select.appendChild(option);
    });
}

async function loadStudents() {
    const res = await fetch('api/students.php?action=list');
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to load students.');
        return;
    }
    students = data.data || [];
    applyFilters();
}

function applyFilters() {
    let list = [...students];
    if (filters.programme !== 'all') {
        list = list.filter(item => (item.programme || '').toLowerCase().includes(filters.programme));
    }
    if (filters.status !== 'all') {
        list = list.filter(item => (item.status || '').toLowerCase() === filters.status);
    }
    if (filters.search) {
        list = list.filter(item => {
            const text = `${item.student_id} ${item.full_name} ${item.email} ${item.programme}`.toLowerCase();
            return text.includes(filters.search);
        });
    }
    renderStudents(list);
}

function renderStudents(list) {
    const body = document.getElementById('studentsTableBody');
    if (!body) return;
    if (list.length === 0) {
        body.innerHTML = '<tr><td colspan="9" class="text-muted">No data yet.</td></tr>';
    } else {
        body.innerHTML = list.map(renderRow).join('');
    }
    updateStats(list);
    updateCharts(list);
}

function renderRow(item) {
    const statusClass = statusChipClass(item.status);
    const progress = Number(item.progress_pct || 0);
    const progressClass = progress >= 75 ? 'bg-success' : progress >= 50 ? 'bg-info' : progress >= 25 ? 'bg-warning' : 'bg-danger';
    return `
        <tr>
            <td><input type="checkbox" class="student-checkbox" value="${item.student_id}"></td>
            <td>${item.student_id}</td>
            <td>
                <div class="d-flex align-items-center">
                    <div class="student-avatar me-2"><i class="fas fa-user-graduate"></i></div>
                    <div>
                        <div class="fw-bold">${item.full_name}</div>
                        <small class="text-muted">${item.email || '-'}</small>
                    </div>
                </div>
            </td>
            <td><span class="status-chip status-success">${item.programme || '-'}</span></td>
            <td>${item.supervisor_name || '-'}</td>
            <td><span class="status-chip ${statusClass}">${item.status || 'Active'}</span></td>
            <td>
                <div class="progress" style="width: 80px;">
                    <div class="progress-bar ${progressClass}" style="width: ${progress}%"></div>
                </div>
                <small class="text-muted">${progress}%</small>
            </td>
            <td>${item.last_activity || '-'}</td>
            <td>
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-primary" onclick="viewStudent('${item.student_id}')" title="View Profile"><i class="fas fa-eye"></i></button>
                    <button class="btn btn-sm btn-outline-primary" onclick="editStudent('${item.student_id}')" title="Edit"><i class="fas fa-edit"></i></button>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="viewProgress('${item.student_id}')">View Progress</a></li>
                            <li><a class="dropdown-item" href="#" onclick="changeSupervisor('${item.student_id}')">Change Supervisor</a></li>
                            <li><a class="dropdown-item" href="#" onclick="sendMessage('${item.student_id}')">Send Message</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-warning" href="#" onclick="suspendStudent('${item.student_id}')">Suspend</a></li>
                        </ul>
                    </div>
                </div>
            </td>
        </tr>
    `;
}

function statusChipClass(status) {
    const value = (status || '').toLowerCase();
    if (value === 'active') return 'status-success';
    if (value === 'graduated') return 'status-success';
    if (value === 'on hold') return 'status-warning';
    if (value === 'suspended') return 'status-danger';
    return 'status-muted';
}

function updateStats(list) {
    const totalEl = document.getElementById('total-students');
    const activeEl = document.getElementById('active-students');
    const gradEl = document.getElementById('graduating-students');
    const completionEl = document.getElementById('completion-rate');

    const total = list.length;
    const active = list.filter(item => (item.status || '').toLowerCase() === 'active').length;
    const graduated = list.filter(item => (item.status || '').toLowerCase() === 'graduated').length;
    const completion = total > 0 ? Math.round((graduated / total) * 100) : 0;

    if (totalEl) totalEl.textContent = total;
    if (activeEl) activeEl.textContent = active;
    if (gradEl) gradEl.textContent = graduated;
    if (completionEl) completionEl.textContent = `${completion}%`;
}

function updateCharts(list) {
    const programmeMap = {
        'MSc Computer Science': 0,
        'MSc Software Engineering': 0,
        'PhD Computer Science': 0,
        'PhD Artificial Intelligence': 0,
        'Other': 0
    };

    list.forEach(item => {
        const programme = item.programme || 'Other';
        if (programmeMap.hasOwnProperty(programme)) {
            programmeMap[programme] += 1;
        } else {
            programmeMap.Other += 1;
        }
    });

    const programmeLabels = Object.keys(programmeMap);
    const programmeData = Object.values(programmeMap);

    const progressBuckets = [0, 0, 0, 0];
    list.forEach(item => {
        const progress = Number(item.progress_pct || 0);
        if (progress <= 25) progressBuckets[0] += 1;
        else if (progress <= 50) progressBuckets[1] += 1;
        else if (progress <= 75) progressBuckets[2] += 1;
        else progressBuckets[3] += 1;
    });

    const programmeCtx = document.getElementById('programmeChart').getContext('2d');
    if (!programmeChart) {
        programmeChart = new Chart(programmeCtx, {
            type: 'pie',
            data: {
                labels: programmeLabels,
                datasets: [{ data: programmeData, backgroundColor: ['#0d6efd', '#198754', '#dc3545', '#ffc107', '#6c757d'] }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
    } else {
        programmeChart.data.labels = programmeLabels;
        programmeChart.data.datasets[0].data = programmeData;
        programmeChart.update();
    }

    const progressCtx = document.getElementById('progressChart').getContext('2d');
    if (!progressChart) {
        progressChart = new Chart(progressCtx, {
            type: 'bar',
            data: {
                labels: ['0-25%', '26-50%', '51-75%', '76-100%'],
                datasets: [{ label: 'Students', data: progressBuckets, backgroundColor: '#0d6efd' }]
            },
            options: { responsive: true, scales: { y: { beginAtZero: true } } }
        });
    } else {
        progressChart.data.datasets[0].data = progressBuckets;
        progressChart.update();
    }
}

function getStudent(studentId) {
    return students.find(item => item.student_id === studentId);
}

function viewStudent(studentId) {
    const student = getStudent(studentId);
    if (!student) return;
    document.getElementById('viewStudentName').textContent = student.full_name || '-';
    document.getElementById('viewStudentId').textContent = student.student_id || '-';
    document.getElementById('viewStudentProgramme').textContent = student.programme || '-';
    document.getElementById('viewStudentSupervisor').textContent = student.supervisor_name || '-';
    document.getElementById('viewStudentEmail').textContent = student.email || '-';
    document.getElementById('viewStudentStatus').textContent = student.status || '-';
    document.getElementById('viewStudentTopic').textContent = student.research_topic || '-';
    document.getElementById('viewStudentNotes').textContent = student.notes || '-';
    new bootstrap.Modal(document.getElementById('viewStudentModal')).show();
}

function editStudent(studentId) {
    const student = getStudent(studentId);
    if (!student) return;
    document.getElementById('modalTitle').textContent = 'Edit Student';
    document.getElementById('firstName').value = (student.full_name || '').split(' ')[0] || '';
    document.getElementById('lastName').value = (student.full_name || '').split(' ').slice(1).join(' ');
    document.getElementById('studentId').value = student.student_id || '';
    document.getElementById('email').value = student.email || '';
    document.getElementById('programme').value = normalizeProgrammeValue(student.programme);
    setSelectByText('supervisor', student.supervisor_name || '');
    document.getElementById('startDate').value = student.start_date || '';
    document.getElementById('completionDate').value = student.completion_date || '';
    document.getElementById('status').value = (student.status || 'active').toLowerCase();
    document.getElementById('phone').value = student.phone || '';
    document.getElementById('researchTopic').value = student.research_topic || '';
    document.getElementById('notes').value = student.notes || '';
    new bootstrap.Modal(document.getElementById('studentModal')).show();
}

function viewProgress(studentId) {
    const student = getStudent(studentId);
    if (!student) return;
    const progress = Number(student.progress_pct || 0);
    document.getElementById('progressLabel').textContent = `${student.full_name} progress`;
    document.getElementById('progressValue').textContent = `${progress}%`;
    const bar = document.getElementById('progressBar');
    bar.style.width = `${progress}%`;
    bar.className = `progress-bar ${progress >= 75 ? 'bg-success' : progress >= 50 ? 'bg-info' : progress >= 25 ? 'bg-warning' : 'bg-danger'}`;
    new bootstrap.Modal(document.getElementById('progressModal')).show();
}

async function changeSupervisor(studentId) {
    const newSupervisor = prompt('Enter new supervisor name:');
    if (!newSupervisor) return;
    const formData = new FormData();
    formData.append('action', 'bulk');
    formData.append('student_ids[]', studentId);
    formData.append('supervisor_name', newSupervisor);
    const res = await fetch('api/students.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to change supervisor.');
        return;
    }
    loadStudents();
}

function sendMessage(studentId) {
    document.getElementById('messageStudentId').value = studentId;
    document.getElementById('messageContent').value = '';
    new bootstrap.Modal(document.getElementById('messageModal')).show();
}

async function sendStudentMessage() {
    const id = document.getElementById('messageStudentId').value;
    const message = document.getElementById('messageContent').value.trim();
    if (!message) {
        alert('Please enter a message.');
        return;
    }
    const formData = new FormData();
    formData.append('action', 'message');
    formData.append('student_id', id);
    formData.append('message', message);
    const res = await fetch('api/students.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to send message.');
        return;
    }
    bootstrap.Modal.getInstance(document.getElementById('messageModal')).hide();
}

async function suspendStudent(studentId) {
    if (!confirm(`Suspend student ${studentId}?`)) return;
    const formData = new FormData();
    formData.append('action', 'status');
    formData.append('student_id', studentId);
    formData.append('status', 'Suspended');
    const res = await fetch('api/students.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to suspend student.');
        return;
    }
    loadStudents();
}

function addStudent() {
    document.getElementById('modalTitle').textContent = 'Add New Student';
    document.getElementById('studentForm').reset();
    new bootstrap.Modal(document.getElementById('studentModal')).show();
}

async function saveStudent() {
    const form = document.getElementById('studentForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    const fullName = `${document.getElementById('firstName').value.trim()} ${document.getElementById('lastName').value.trim()}`.trim();
    const programmeSelect = document.getElementById('programme');
    const supervisorSelect = document.getElementById('supervisor');
    const statusValue = document.getElementById('status').value;

    const formData = new FormData();
    formData.append('action', 'save');
    formData.append('student_id', document.getElementById('studentId').value.trim());
    formData.append('full_name', fullName);
    formData.append('email', document.getElementById('email').value.trim());
    formData.append('programme', programmeSelect.options[programmeSelect.selectedIndex]?.textContent || '');
    formData.append('supervisor_name', supervisorSelect.options[supervisorSelect.selectedIndex]?.textContent || '');
    formData.append('status', statusValue.charAt(0).toUpperCase() + statusValue.slice(1));
    formData.append('progress_pct', 0);
    formData.append('last_activity', 'just now');
    formData.append('start_date', document.getElementById('startDate').value);
    formData.append('completion_date', document.getElementById('completionDate').value);
    formData.append('phone', document.getElementById('phone').value);
    formData.append('research_topic', document.getElementById('researchTopic').value);
    formData.append('notes', document.getElementById('notes').value);

    const res = await fetch('api/students.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to save student.');
        return;
    }
    bootstrap.Modal.getInstance(document.getElementById('studentModal')).hide();
    loadStudents();
}

function bulkActions() {
    const selectedCount = document.querySelectorAll('.student-checkbox:checked').length;
    if (selectedCount === 0) {
        alert('Please select students first.');
        return;
    }
    document.getElementById('selectedCount').textContent = `${selectedCount} students selected`;
    new bootstrap.Modal(document.getElementById('bulkActionsModal')).show();
}

async function bulkChangeSupervisor() {
    const supervisor = prompt('Enter supervisor name for selected students:');
    if (!supervisor) return;
    await applyBulkAction({ supervisor_name: supervisor });
}

async function bulkSendMessage() {
    const message = prompt('Enter message for selected students:');
    if (!message) return;
    const ids = getSelectedIds();
    for (const id of ids) {
        const formData = new FormData();
        formData.append('action', 'message');
        formData.append('student_id', id);
        formData.append('message', message);
        await fetch('api/students.php', { method: 'POST', body: formData });
    }
    bootstrap.Modal.getInstance(document.getElementById('bulkActionsModal')).hide();
}

async function bulkUpdateStatus() {
    const status = prompt('Enter status (Active, Inactive, Suspended, Graduated):');
    if (!status) return;
    await applyBulkAction({ status });
}

async function bulkSuspend() {
    if (!confirm('Suspend selected students?')) return;
    await applyBulkAction({ status: 'Suspended' });
}

async function applyBulkAction(payload) {
    const ids = getSelectedIds();
    const formData = new FormData();
    formData.append('action', 'bulk');
    ids.forEach(id => formData.append('student_ids[]', id));
    if (payload.status) formData.append('status', payload.status);
    if (payload.supervisor_name) formData.append('supervisor_name', payload.supervisor_name);
    const res = await fetch('api/students.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Bulk action failed.');
        return;
    }
    bootstrap.Modal.getInstance(document.getElementById('bulkActionsModal')).hide();
    loadStudents();
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.student-checkbox:checked')).map(cb => cb.value);
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    document.querySelectorAll('.student-checkbox').forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

function filterByProgramme(programme) {
    filters.programme = programme;
    applyFilters();
}

function filterByStatus(status) {
    filters.status = status;
    applyFilters();
}

function refreshStudents() {
    loadStudents();
}

function normalizeProgrammeValue(programme) {
    const map = {
        'MSc Computer Science': 'msc_cs',
        'MSc Software Engineering': 'msc_se',
        'PhD Computer Science': 'phd_cs',
        'PhD Artificial Intelligence': 'phd_ai'
    };
    return map[programme] || '';
}

function setSelectByText(selectId, text) {
    const select = document.getElementById(selectId);
    if (!select) return;
    const option = Array.from(select.options).find(opt => opt.textContent.trim() === text);
    if (option) {
        select.value = option.value;
    }
}

const style = document.createElement('style');
style.textContent = `
    .student-avatar { width: 40px; height: 40px; border-radius: 50%; background-color: #e9ecef; display: flex; align-items: center; justify-content: center; color: #6c757d; }
    .activity-timeline { position: relative; }
    .activity-item { display: flex; align-items: flex-start; margin-bottom: 1rem; padding-left: 2rem; position: relative; }
    .activity-icon { position: absolute; left: 0; width: 2rem; height: 2rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; }
    .activity-content { flex: 1; }
    .activity-title { font-weight: 500; margin-bottom: 0.25rem; }
    .activity-time { font-size: 0.875rem; color: #6c757d; }
`;
if (!document.querySelector('style[data-app="dept-students"]')) {
    style.setAttribute('data-app', 'dept-students');
    document.head.appendChild(style);
}
</script>

<?php require_once 'includes/footer.php'; ?>
