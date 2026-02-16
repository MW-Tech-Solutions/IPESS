<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SUPERVISOR') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'Milestones';
$pageSubtitle = 'Track key milestones and approvals.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Milestones</h1>
        <p class="panel-muted">Upcoming deadlines and student milestone checkpoints.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-primary" onclick="openCreateMilestone()"><i class="fas fa-plus me-2"></i>Add Milestone</button>
        <button class="btn btn-light" onclick="location.reload()"><i class="fas fa-sync me-2"></i>Refresh</button>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Milestone Tracker</h3>
            <div class="panel-muted">Filter by status or search for a student.</div>
        </div>
        <div class="panel-actions">
            <button class="btn btn-light" onclick="location.reload()"><i class="fas fa-sync me-2"></i>Refresh</button>
        </div>
    </div>
    <div class="panel-body">
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Total</div>
                    <div class="stat-value" id="statTotal">0</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Upcoming</div>
                    <div class="stat-value" id="statUpcoming">0</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Completed</div>
                    <div class="stat-value" id="statCompleted">0</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Overdue</div>
                    <div class="stat-value" id="statOverdue">0</div>
                </div>
            </div>
        </div>

        <div class="row g-3 align-items-end mb-3">
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" id="filterStatus">
                    <option value="">All Status</option>
                    <option value="Upcoming">Upcoming</option>
                    <option value="Completed">Completed</option>
                    <option value="Overdue">Overdue</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Search Student/Title</label>
                <input type="text" class="form-control" id="filterQuery" placeholder="Type student name or title">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" onclick="applyFilters()">Apply</button>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">Reset</button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Title</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Acknowledged</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="milestonesList"></tbody>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="milestoneModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Milestone</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="milestoneId">
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="milestoneStatus">
                        <option value="Upcoming">Upcoming</option>
                        <option value="Completed">Completed</option>
                        <option value="Overdue">Overdue</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="saveMilestone()">Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createMilestoneModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Milestone</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Student</label>
                    <select class="form-select" id="createStudent"></select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Title</label>
                    <input type="text" class="form-control" id="createTitle" placeholder="e.g. Chapter 2 Review">
                </div>
                <div class="mb-3">
                    <label class="form-label">Due Date</label>
                    <input type="date" class="form-control" id="createDueDate">
                </div>
                <div class="mb-3">
                    <label class="form-label">Note</label>
                    <textarea class="form-control" id="createNote" rows="3" placeholder="Optional note to student"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="saveCreateMilestone()">Create</button>
            </div>
        </div>
    </div>
</div>

<script>
let milestones = [];
let studentsCache = [];

document.addEventListener('DOMContentLoaded', () => loadMilestones());

async function loadMilestones() {
    const status = document.getElementById('filterStatus')?.value || '';
    const query = document.getElementById('filterQuery')?.value || '';
    const params = new URLSearchParams({ action: 'list' });
    if (status) params.append('status', status);
    if (query) params.append('q', query);
    const res = await fetch(`api/milestones.php?${params.toString()}`);
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to load milestones.');
        return;
    }
    milestones = data.data || [];
    renderMilestones(milestones);
    renderStats(milestones);
}

function renderMilestones(list) {
    const container = document.getElementById('milestonesList');
    if (!container) return;
    if (list.length === 0) {
        container.innerHTML = '<tr><td colspan="6" class="text-muted">No milestones found.</td></tr>';
        return;
    }
    container.innerHTML = list.map(item => {
        const status = item.status || 'Upcoming';
        const badge = status === 'Completed'
            ? 'badge bg-success'
            : (status === 'Overdue' ? 'badge bg-danger' : 'badge bg-warning text-dark');
        return `
            <tr>
                <td>${item.student_name || '—'}</td>
                <td>${item.title || '—'}</td>
                <td>${item.due_date || '—'}</td>
                <td><span class="${badge}">${status}</span></td>
                <td>${item.acknowledged_at || '—'}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="openMilestone(${item.milestone_id})">Update</button>
                </td>
            </tr>
        `;
    }).join('');
}

function openMilestone(id) {
    const item = milestones.find(milestone => Number(milestone.milestone_id) === Number(id));
    if (!item) return;
    document.getElementById('milestoneId').value = item.milestone_id;
    document.getElementById('milestoneStatus').value = item.status || 'Upcoming';
    new bootstrap.Modal(document.getElementById('milestoneModal')).show();
}

async function saveMilestone() {
    const id = document.getElementById('milestoneId').value;
    const status = document.getElementById('milestoneStatus').value;
    const formData = new FormData();
    formData.append('action', 'status');
    formData.append('milestone_id', id);
    formData.append('status', status);
    const res = await fetch('api/milestones.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to update milestone.');
        return;
    }
    bootstrap.Modal.getInstance(document.getElementById('milestoneModal')).hide();
    loadMilestones();
}

async function openCreateMilestone() {
    await loadStudents();
    new bootstrap.Modal(document.getElementById('createMilestoneModal')).show();
}

async function loadStudents() {
    const select = document.getElementById('createStudent');
    if (!select) return;
    if (studentsCache.length > 0) {
        renderStudents();
        return;
    }
    const res = await fetch('api/students.php?action=list');
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to load students.');
        return;
    }
    studentsCache = data.data || [];
    renderStudents();
}

function renderStudents() {
    const select = document.getElementById('createStudent');
    if (!select) return;
    if (studentsCache.length === 0) {
        select.innerHTML = '<option value="">No students found</option>';
        return;
    }
    select.innerHTML = studentsCache.map(student => {
        const label = student.full_name || student.student_name || student.student_id || 'Student';
        const id = student.student_user_id || student.user_id || '';
        const app = student.application_number ? ` (${student.application_number})` : '';
        return `<option value="${id}">${label}${app}</option>`;
    }).join('');
}

async function saveCreateMilestone() {
    const studentId = document.getElementById('createStudent').value;
    const title = document.getElementById('createTitle').value.trim();
    const dueDate = document.getElementById('createDueDate').value;
    const note = document.getElementById('createNote').value.trim();

    if (!studentId || !title) {
        alert('Student and title are required.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'create');
    formData.append('student_user_id', studentId);
    formData.append('title', title);
    formData.append('due_date', dueDate);
    formData.append('note', note);

    const res = await fetch('api/milestones.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to create milestone.');
        return;
    }
    bootstrap.Modal.getInstance(document.getElementById('createMilestoneModal')).hide();
    document.getElementById('createTitle').value = '';
    document.getElementById('createDueDate').value = '';
    document.getElementById('createNote').value = '';
    loadMilestones();
}

function renderStats(list) {
    const total = list.length;
    const upcoming = list.filter(item => (item.status || 'Upcoming') === 'Upcoming').length;
    const completed = list.filter(item => item.status === 'Completed').length;
    const overdue = list.filter(item => item.status === 'Overdue').length;
    document.getElementById('statTotal').textContent = total;
    document.getElementById('statUpcoming').textContent = upcoming;
    document.getElementById('statCompleted').textContent = completed;
    document.getElementById('statOverdue').textContent = overdue;
}

function applyFilters() {
    loadMilestones();
}

function resetFilters() {
    const status = document.getElementById('filterStatus');
    const query = document.getElementById('filterQuery');
    if (status) status.value = '';
    if (query) query.value = '';
    loadMilestones();
}
</script>

<?php require_once 'includes/footer.php'; ?>
