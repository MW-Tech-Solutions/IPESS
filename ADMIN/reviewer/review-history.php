<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'REVIEWER') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'Review History';
$pageSubtitle = 'Archive of completed reviews and outcomes.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Review History</h1>
        <p class="panel-muted">Previously completed reviews and recommendations.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" onclick="location.reload()"><i class="fas fa-sync me-2"></i>Refresh</button>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Completed Reviews</h3>
            <div class="panel-muted">Search and reference previous review decisions.</div>
        </div>
        <div class="input-group" style="max-width: 260px;">
            <input type="text" class="form-control" id="searchHistory" placeholder="Search...">
            <button class="btn btn-outline-primary" type="button"><i class="fas fa-search"></i></button>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Application</th>
                        <th>Applicant</th>
                        <th>Programme</th>
                        <th>Decision</th>
                        <th>Completed</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="historyTable"></tbody>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Review Summary</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-muted small">Application</div>
                <div class="fw-bold mb-2" id="historyApp">-</div>
                <div class="text-muted small">Decision</div>
                <div class="fw-bold mb-2" id="historyDecision">-</div>
                <div class="text-muted small">Comment</div>
                <div class="fw-bold" id="historyComment">-</div>
            </div>
        </div>
    </div>
</div>

<script>
let historyItems = [];

document.addEventListener('DOMContentLoaded', () => {
    loadHistory();
    const search = document.getElementById('searchHistory');
    if (search) {
        search.addEventListener('input', () => {
            const term = search.value.toLowerCase();
            renderHistory(historyItems.filter(item => {
                const text = `${item.application_code} ${item.applicant_name} ${item.decision}`.toLowerCase();
                return text.includes(term);
            }));
        });
    }
});

async function loadHistory() {
    const res = await fetch('api/history.php?action=list');
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to load history.');
        return;
    }
    historyItems = data.data || [];
    renderHistory(historyItems);
}

function renderHistory(list) {
    const body = document.getElementById('historyTable');
    if (!body) return;
    if (list.length === 0) {
        body.innerHTML = '<tr><td colspan="6" class="text-muted">No reviews yet.</td></tr>';
        return;
    }
    body.innerHTML = list.map(item => `
        <tr>
            <td>${item.application_code}</td>
            <td>${item.applicant_name}</td>
            <td>${item.programme || '-'}</td>
            <td><span class="status-chip ${decisionClass(item.decision)}">${item.decision}</span></td>
            <td>${new Date(item.decided_at).toLocaleDateString()}</td>
            <td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="viewSummary(${item.history_id})"><i class="fas fa-eye"></i></button></td>
        </tr>
    `).join('');
}

function decisionClass(decision) {
    const value = (decision || '').toLowerCase();
    if (value.includes('approved') || value.includes('complete')) return 'status-success';
    if (value.includes('rejected')) return 'status-danger';
    return 'status-warning';
}

function viewSummary(id) {
    const item = historyItems.find(history => Number(history.history_id) === Number(id));
    if (!item) return;
    document.getElementById('historyApp').textContent = item.application_code;
    document.getElementById('historyDecision').textContent = item.decision || '-';
    document.getElementById('historyComment').textContent = item.comment || item.remarks || '-';
    new bootstrap.Modal(document.getElementById('historyModal')).show();
}
</script>

<?php require_once 'includes/footer.php'; ?>
