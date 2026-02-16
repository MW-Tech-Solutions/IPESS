<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'REVIEWER') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'Feedback Management';
$pageSubtitle = 'Review submitted feedback and manage follow-ups.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Feedback Management</h1>
        <p class="panel-muted">Track feedback status and required revisions.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" onclick="location.reload()"><i class="fas fa-sync me-2"></i>Refresh</button>
        <button class="btn btn-primary" onclick="createFeedback()"><i class="fas fa-plus me-2"></i>New Feedback</button>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Recent Feedback</h3>
            <div class="panel-muted">Latest feedback submissions awaiting action.</div>
        </div>
        <div class="input-group" style="max-width: 260px;">
            <input type="text" class="form-control" id="searchFeedback" placeholder="Search feedback...">
            <button class="btn btn-outline-primary" type="button"><i class="fas fa-search"></i></button>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Application</th>
                        <th>Reviewer Note</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="feedbackTable"></tbody>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="feedbackModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Feedback</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="feedbackForm">
                    <div class="mb-3">
                        <label class="form-label">Application Code</label>
                        <input type="text" class="form-control" id="feedbackApplication" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Student Name</label>
                        <input type="text" class="form-control" id="feedbackStudent" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Chapter</label>
                        <input type="text" class="form-control" id="feedbackChapter">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Feedback</label>
                        <textarea class="form-control" id="feedbackText" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="feedbackStatus">
                            <option value="Awaiting Response">Awaiting Response</option>
                            <option value="Resolved">Resolved</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="submitFeedback()">Save Feedback</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="viewFeedbackModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Feedback Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-muted small">Application</div>
                <div class="fw-bold mb-2" id="viewFeedbackApp">-</div>
                <div class="text-muted small">Student</div>
                <div class="fw-bold mb-2" id="viewFeedbackStudent">-</div>
                <div class="text-muted small">Chapter</div>
                <div class="fw-bold mb-2" id="viewFeedbackChapter">-</div>
                <div class="text-muted small">Feedback</div>
                <div class="fw-bold" id="viewFeedbackText">-</div>
            </div>
        </div>
    </div>
</div>

<script>
let feedbackItems = [];

document.addEventListener('DOMContentLoaded', () => {
    loadFeedback();
    const search = document.getElementById('searchFeedback');
    if (search) {
        search.addEventListener('input', () => {
            const term = search.value.toLowerCase();
            renderFeedback(feedbackItems.filter(item => {
                const text = `${item.application_code} ${item.student_name} ${item.feedback}`.toLowerCase();
                return text.includes(term);
            }));
        });
    }
});

async function loadFeedback() {
    const res = await fetch('api/feedback.php?action=list');
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to load feedback.');
        return;
    }
    feedbackItems = data.data || [];
    renderFeedback(feedbackItems);
}

function renderFeedback(list) {
    const body = document.getElementById('feedbackTable');
    if (!body) return;
    if (list.length === 0) {
        body.innerHTML = '<tr><td colspan="5" class="text-muted">No data yet.</td></tr>';
        return;
    }
    body.innerHTML = list.map(item => `
        <tr>
            <td>${item.application_code}</td>
            <td>${item.feedback}</td>
            <td><span class="status-chip ${statusChipClass(item.status)}">${item.status}</span></td>
            <td>${new Date(item.updated_at).toLocaleDateString()}</td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-primary" onclick="viewFeedback(${item.feedback_id})"><i class="fas fa-eye"></i></button>
                ${item.status !== 'Resolved' ? `<button class="btn btn-sm btn-outline-primary" onclick="followUp(${item.feedback_id})"><i class="fas fa-envelope"></i></button>` : ''}
            </td>
        </tr>
    `).join('');
}

function statusChipClass(status) {
    return status === 'Resolved' ? 'status-success' : 'status-warning';
}

function createFeedback() {
    document.getElementById('feedbackForm').reset();
    new bootstrap.Modal(document.getElementById('feedbackModal')).show();
}

function viewFeedback(id) {
    const item = feedbackItems.find(feedback => Number(feedback.feedback_id) === Number(id));
    if (!item) return;
    document.getElementById('viewFeedbackApp').textContent = item.application_code;
    document.getElementById('viewFeedbackStudent').textContent = item.student_name;
    document.getElementById('viewFeedbackChapter').textContent = item.chapter || '-';
    document.getElementById('viewFeedbackText').textContent = item.feedback;
    new bootstrap.Modal(document.getElementById('viewFeedbackModal')).show();
}

async function submitFeedback() {
    const form = document.getElementById('feedbackForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    const formData = new FormData();
    formData.append('action', 'create');
    formData.append('application_code', document.getElementById('feedbackApplication').value);
    formData.append('student_name', document.getElementById('feedbackStudent').value);
    formData.append('chapter', document.getElementById('feedbackChapter').value);
    formData.append('feedback', document.getElementById('feedbackText').value);
    formData.append('status', document.getElementById('feedbackStatus').value);
    const res = await fetch('api/feedback.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to save feedback.');
        return;
    }
    bootstrap.Modal.getInstance(document.getElementById('feedbackModal')).hide();
    loadFeedback();
}

async function followUp(id) {
    const formData = new FormData();
    formData.append('action', 'followup');
    formData.append('feedback_id', id);
    const res = await fetch('api/feedback.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to send follow-up.');
        return;
    }
    loadFeedback();
}
</script>

<?php require_once 'includes/footer.php'; ?>
