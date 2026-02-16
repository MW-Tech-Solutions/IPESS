<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SUPERVISOR') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'Supervisor Reports';
$pageSubtitle = 'Generate supervision reports and export progress insights.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Supervisor Reports</h1>
        <p class="panel-muted">Export progress summaries and milestone updates.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" onclick="location.reload()"><i class="fas fa-sync me-2"></i>Refresh</button>
        <button class="btn btn-primary" onclick="generateReport()"><i class="fas fa-file-export me-2"></i>Generate Report</button>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Report History</h3>
            <div class="panel-muted">Recent supervision exports.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Report</th>
                        <th>Type</th>
                        <th>Format</th>
                        <th>Generated</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="supervisorReportsTable"></tbody>
            </table>
        </div>
    </div>
</section>

<script>
let supervisorReports = [];

document.addEventListener('DOMContentLoaded', () => loadReports());

async function loadReports() {
    const res = await fetch('api/reports.php?action=list');
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to load reports.');
        return;
    }
    supervisorReports = data.data || [];
    renderReports(supervisorReports);
}

function renderReports(list) {
    const body = document.getElementById('supervisorReportsTable');
    if (!body) return;
    if (list.length === 0) {
        body.innerHTML = '<tr><td colspan="6" class="text-muted">No reports generated yet.</td></tr>';
        return;
    }
    body.innerHTML = list.map(item => `
        <tr>
            <td>${item.report_title}</td>
            <td>${item.report_type}</td>
            <td>${item.format || 'PDF'}</td>
            <td>${new Date(item.created_at).toLocaleDateString()}</td>
            <td><span class="status-chip status-success">${item.status || 'Ready'}</span></td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-primary" onclick="viewReport(${item.report_id})"><i class="fas fa-eye"></i></button>
                <button class="btn btn-sm btn-outline-primary" onclick="downloadReport(${item.report_id})"><i class="fas fa-download"></i></button>
            </td>
        </tr>
    `).join('');
}

async function generateReport() {
    const reportType = (prompt('Enter report type (Weekly, Milestone, Chapter):', 'Weekly') || 'Weekly').trim();
    if (!reportType) return;
    const format = (prompt('Enter format (PDF or CSV):', 'PDF') || 'PDF').toUpperCase();
    const payload = new FormData();
    payload.append('action', 'generate');
    payload.append('report_title', `Supervisor ${reportType} Summary`);
    payload.append('report_type', reportType);
    payload.append('format', format === 'CSV' ? 'CSV' : 'PDF');
    const res = await fetch('api/reports.php', { method: 'POST', body: payload });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to generate report.');
        return;
    }
    loadReports();
}

function viewReport(id) {
    window.open(`report-file.php?id=${id}&mode=view`, '_blank');
}

function downloadReport(id) {
    window.location.href = `report-file.php?id=${id}&mode=download`;
}
</script>

<?php require_once 'includes/footer.php'; ?>
