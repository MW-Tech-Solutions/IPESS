<?php
require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');
require_role(['SUPER_ADMIN', 'ICT_ADMIN'], 'ADMIN/login.php');

$pageTitle = 'IPESS Reports';
$pageSubtitle = 'Generate, download, and manage IPESS institutional reports.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>IPESS Reports & Exports</h1>
        <p class="panel-muted">Generate PDF or Excel reports for IPESS and track their history.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" id="refreshReports">Refresh</button>
        <button class="btn btn-primary" type="submit" form="reportForm">Generate Report</button>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Generate Report</h3>
            <div class="panel-muted">Choose a report type and export format.</div>
        </div>
    </div>
    <div class="panel-body">
        <form id="reportForm" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Report Type</label>
                <select class="form-select" name="report_type" required>
                    <option value="Admissions Summary">Admissions Summary</option>
                    <option value="Faculty Breakdown">Faculty Breakdown</option>
                    <option value="Programme Capacity">Programme Capacity</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Format</label>
                <select class="form-select" name="format" required>
                    <option value="PDF">PDF</option>
                    <option value="EXCEL">Excel</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Delivery</label>
                <select class="form-select" name="delivery" required>
                    <option value="view">View in browser</option>
                    <option value="download">Download</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit">Generate</button>
            </div>
        </form>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Report History</h3>
            <div class="panel-muted">Recently generated exports.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="reportsTable">
                <thead>
                    <tr>
                        <th>Report</th>
                        <th>Type</th>
                        <th>Format</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</section>

<script>
const reportForm = document.getElementById('reportForm');
const reportsTableBody = document.querySelector('#reportsTable tbody');
const refreshReportsBtn = document.getElementById('refreshReports');

function loadReports() {
    fetch('api/reports.php?action=list')
        .then(response => response.json())
        .then(data => {
            reportsTableBody.innerHTML = '';
            if (!data.success || !data.data.length) {
                reportsTableBody.innerHTML = '<tr><td colspan="5" class="text-muted text-center">No reports generated yet.</td></tr>';
                return;
            }
            data.data.forEach(report => {
                const created = report.created_at ? new Date(report.created_at).toLocaleString() : '';
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${report.report_name}</td>
                    <td>${report.report_type}</td>
                    <td>${report.format}</td>
                    <td>${created}</td>
                    <td class="text-end">
                        <a class="btn btn-outline-primary btn-sm" href="${report.view_url}" target="_blank">View</a>
                        <a class="btn btn-light btn-sm" href="${report.download_url}">Download</a>
                        <button class="btn btn-light btn-sm" data-id="${report.report_id}">Delete</button>
                    </td>
                `;
                row.querySelector('button').addEventListener('click', () => deleteReport(report.report_id));
                reportsTableBody.appendChild(row);
            });
        });
}

function deleteReport(id) {
    if (!confirm('Delete this report?')) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    fetch('api/reports.php', { method: 'POST', body: formData })
        .then(() => loadReports());
}

reportForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(reportForm);
    formData.append('action', 'generate');
    fetch('api/reports.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Report generation failed.');
                return;
            }
            loadReports();
            const delivery = (formData.get('delivery') || 'view').toLowerCase();
            if (delivery === 'download' && data.download_url) {
                window.location.href = data.download_url;
            } else if (data.view_url) {
                window.open(data.view_url, '_blank');
            }
        });
});

refreshReportsBtn.addEventListener('click', loadReports);
loadReports();
</script>

<?php require_once 'includes/footer.php'; ?>
