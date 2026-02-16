<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SUPERVISOR') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'Progress Tracking';
$pageSubtitle = 'Monitor chapter progress and supervisor actions.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Progress Tracking</h1>
        <p class="panel-muted">Follow each student?s chapter submissions and approvals.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" onclick="location.reload()"><i class="fas fa-sync me-2"></i>Refresh</button>
        <button class="btn btn-primary" onclick="exportProgress()"><i class="fas fa-download me-2"></i>Export</button>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Chapter Progress</h3>
            <div class="panel-muted">Latest updates by student and chapter.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Chapter</th>
                        <th>Status</th>
                        <th>Supervisor Note</th>
                        <th>Updated</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="progressTable"></tbody>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Progress</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="progressId">
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="progressStatus">
                        <option value="Pending Review">Pending Review</option>
                        <option value="Awaiting Revision">Awaiting Revision</option>
                        <option value="Approved">Approved</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Supervisor Note</label>
                    <textarea class="form-control" id="progressNote" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="saveProgress()">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
let progressItems = [];

document.addEventListener('DOMContentLoaded', () => loadProgress());

async function loadProgress() {
    const res = await fetch('api/progress.php?action=list');
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to load progress.');
        return;
    }
    progressItems = data.data || [];
    renderProgress(progressItems);
}

function renderProgress(list) {
    const body = document.getElementById('progressTable');
    if (!body) return;
    if (list.length === 0) {
        body.innerHTML = '<tr><td colspan="6" class="text-muted">No data yet.</td></tr>';
        return;
    }
    body.innerHTML = list.map(item => `
        <tr>
            <td>${item.student_name}</td>
            <td>${item.chapter || '-'}</td>
            <td><span class="status-chip ${statusChipClass(item.status)}">${item.status}</span></td>
            <td>${item.supervisor_note || '-'}</td>
            <td>${new Date(item.updated_at).toLocaleDateString()}</td>
            <td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="openReview('${item.student_id}')"><i class="fas fa-eye"></i></button></td>
        </tr>
    `).join('');
}

function statusChipClass(status) {
    const value = (status || '').toLowerCase();
    if (value === 'approved') return 'status-success';
    if (value === 'awaiting revision') return 'status-muted';
    return 'status-warning';
}

function openReview(id) {
    const item = progressItems.find(progress => String(progress.student_id) === String(id));
    if (!item) return;
    document.getElementById('progressId').value = item.student_id;
    document.getElementById('progressStatus').value = item.status || 'Pending Review';
    document.getElementById('progressNote').value = item.supervisor_note || '';
    new bootstrap.Modal(document.getElementById('reviewModal')).show();
}

async function saveProgress() {
    const id = document.getElementById('progressId').value;
    const status = document.getElementById('progressStatus').value;
    const note = document.getElementById('progressNote').value;
    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('student_id', id);
    formData.append('status', status);
    formData.append('supervisor_note', note);
    const res = await fetch('api/progress.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to update progress.');
        return;
    }
    bootstrap.Modal.getInstance(document.getElementById('reviewModal')).hide();
    loadProgress();
}

function exportProgress() {
    window.location.href = 'api/progress.php?action=export';
}
</script>

<?php require_once 'includes/footer.php'; ?>
