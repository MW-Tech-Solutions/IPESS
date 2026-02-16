<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SUPERVISOR') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'My Students';
$pageSubtitle = 'Track supervision load and recent submissions.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>My Students</h1>
        <p class="panel-muted">Review each student?s progress and deliver feedback.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" onclick="location.reload()"><i class="fas fa-sync me-2"></i>Refresh</button>
        <button class="btn btn-primary" onclick="sendBulkMessage()"><i class="fas fa-envelope me-2"></i>Bulk Message</button>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div>
            <div class="stat-title">Total Students</div>
            <div class="stat-value">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
        <div>
            <div class="stat-title">Pending Reviews</div>
            <div class="stat-value">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-title">Approved Chapters</div>
            <div class="stat-value">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        <div>
            <div class="stat-title">Upcoming Deadlines</div>
            <div class="stat-value">0</div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Student Roster</h3>
            <div class="panel-muted">Current supervision list and status.</div>
        </div>
        <div class="input-group" style="max-width: 260px;">
            <input type="text" class="form-control" id="searchStudents" placeholder="Search...">
            <button class="btn btn-outline-primary" type="button"><i class="fas fa-search"></i></button>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Programme</th>
                        <th>Current Chapter</th>
                        <th>Status</th>
                        <th>Last Submission</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="studentsTable"></tbody>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="studentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Student Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Student Name</div>
                        <div class="fw-bold" id="viewStudentName">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Programme</div>
                        <div class="fw-bold" id="viewStudentProgramme">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Current Chapter</div>
                        <div class="fw-bold" id="viewStudentChapter">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Status</div>
                        <div class="fw-bold" id="viewStudentStatus">-</div>
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

<div class="modal fade" id="feedbackModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Feedback</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="feedbackStudentId">
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="feedbackStatus">
                        <option value="Pending Review">Pending Review</option>
                        <option value="Awaiting Revision">Awaiting Revision</option>
                        <option value="Approved">Approved</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Feedback Notes</label>
                    <textarea class="form-control" id="feedbackNotes" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="submitFeedback()">Send</button>
            </div>
        </div>
    </div>
</div>

<script>
let supervisorStudents = [];

document.addEventListener('DOMContentLoaded', () => {
    loadStudents();
    const search = document.getElementById('searchStudents');
    if (search) {
        search.addEventListener('input', () => {
            const term = search.value.toLowerCase();
            renderStudents(supervisorStudents.filter(item => {
                const text = `${item.full_name} ${item.programme} ${item.current_chapter}`.toLowerCase();
                return text.includes(term);
            }));
        });
    }
});

async function loadStudents() {
    const res = await fetch('api/students.php?action=list');
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to load students.');
        return;
    }
    supervisorStudents = data.data || [];
    renderStudents(supervisorStudents);
    updateStats(supervisorStudents);
}

function renderStudents(list) {
    const body = document.getElementById('studentsTable');
    if (!body) return;
    if (list.length === 0) {
        body.innerHTML = '<tr><td colspan="6" class="text-muted">No data yet.</td></tr>';
        return;
    }
    body.innerHTML = list.map(item => `
        <tr>
            <td>${item.full_name}</td>
            <td>${item.programme || '-'}</td>
            <td>${item.current_chapter || '-'}</td>
            <td><span class="status-chip ${statusChipClass(item.status)}">${item.status}</span></td>
            <td>${item.last_submission || '-'}</td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-primary" onclick="viewStudent('${item.student_id}')"><i class="fas fa-eye"></i></button>
                <button class="btn btn-sm btn-outline-primary" onclick="sendFeedback('${item.student_id}')"><i class="fas fa-comment"></i></button>
            </td>
        </tr>
    `).join('');
}

function updateStats(list) {
    const stats = document.querySelectorAll('.stat-value');
    if (stats.length < 4) return;
    stats[0].textContent = list.length;
    stats[1].textContent = list.filter(item => item.status === 'Pending Review').length;
    stats[2].textContent = list.filter(item => item.status === 'Approved').length;
    stats[3].textContent = list.filter(item => item.status === 'Awaiting Revision').length;
}

function statusChipClass(status) {
    const value = (status || '').toLowerCase();
    if (value === 'approved') return 'status-success';
    if (value === 'awaiting revision') return 'status-muted';
    return 'status-warning';
}

function getStudent(id) {
    return supervisorStudents.find(item => item.student_id === id);
}

function viewStudent(id) {
    const student = getStudent(id);
    if (!student) return;
    document.getElementById('viewStudentName').textContent = student.full_name;
    document.getElementById('viewStudentProgramme').textContent = student.programme || '-';
    document.getElementById('viewStudentChapter').textContent = student.current_chapter || '-';
    document.getElementById('viewStudentStatus').textContent = student.status || '-';
    document.getElementById('viewStudentNotes').textContent = student.notes || '-';
    new bootstrap.Modal(document.getElementById('studentModal')).show();
}

function sendFeedback(id) {
    document.getElementById('feedbackStudentId').value = id;
    document.getElementById('feedbackStatus').value = 'Pending Review';
    document.getElementById('feedbackNotes').value = '';
    new bootstrap.Modal(document.getElementById('feedbackModal')).show();
}

async function submitFeedback() {
    const studentId = document.getElementById('feedbackStudentId').value;
    const status = document.getElementById('feedbackStatus').value;
    const notes = document.getElementById('feedbackNotes').value;
    const formData = new FormData();
    formData.append('action', 'status');
    formData.append('student_id', studentId);
    formData.append('status', status);
    formData.append('notes', notes);
    const res = await fetch('api/students.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to send feedback.');
        return;
    }
    bootstrap.Modal.getInstance(document.getElementById('feedbackModal')).hide();
    loadStudents();
}

async function sendBulkMessage() {
    const message = prompt('Enter message for all students:');
    if (!message) return;
    for (const student of supervisorStudents) {
        const formData = new FormData();
        formData.append('action', 'message');
        formData.append('student_id', student.student_id);
        formData.append('message', message);
        await fetch('api/students.php', { method: 'POST', body: formData });
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
