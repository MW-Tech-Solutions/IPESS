<?php
require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');
require_role(['SUPER_ADMIN', 'ICT_ADMIN'], 'ADMIN/login.php');

$pageTitle    = 'Activate Admissions';
$pageSubtitle = 'Generate matric numbers and activate admission & acceptance letters for approved applicants.';
$currentPage  = basename(__FILE__); // 'activate-admissions.php'

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<style>
/* ─── Page-specific ─────────────────────────────────────────── */
.badge-active   { background:#16a34a; color:#fff; padding:2px 10px; border-radius:999px; font-size:.78rem; font-weight:600; }
.badge-inactive { background:#6b7280; color:#fff; padding:2px 10px; border-radius:999px; font-size:.78rem; font-weight:600; }
.badge-matric   { background:#1d4ed8; color:#fff; padding:2px 10px; border-radius:999px; font-size:.78rem; font-weight:600; }
.badge-none     { background:#9ca3af; color:#fff; padding:2px 10px; border-radius:999px; font-size:.78rem; font-weight:600; font-style:italic; }

.stat-strip { display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1.5rem; }
.stat-strip .scard { flex:1; min-width:160px; background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:1rem 1.25rem; }
.stat-strip .scard .sval { font-size:1.8rem; font-weight:800; color:#063b29; }
.stat-strip .scard .slbl { font-size:.78rem; color:#6b7280; font-weight:600; text-transform:uppercase; }

.act-bar { display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; margin-bottom:1rem; }
.act-bar select { max-width:180px; }

#admTable thead { background:#063b29; color:#fff; font-size:.82rem; }
#admTable tbody tr:hover { background:#f0fdf4; }
#admTable td, #admTable th { vertical-align:middle; padding:.55rem .75rem; }
.matric-cell { font-family:monospace; font-size:.85rem; }
#toastWrap { position:fixed; bottom:1.5rem; right:1.5rem; z-index:9999; }
</style>

<section class="page-hero">
    <div>
        <h1>Activate Admissions</h1>
        <p class="panel-muted">Generate IPESS matric numbers and toggle admission &amp; acceptance letter availability for admitted applicants.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-success" id="btnBulkGenerate">
            <i class="fas fa-magic me-1"></i> Generate All Missing Matric Numbers
        </button>
    </div>
</section>

<!-- Stat cards -->
<div class="stat-strip" id="statStrip">
    <div class="scard"><div class="sval" id="statTotal">—</div><div class="slbl">Total Admitted</div></div>
    <div class="scard"><div class="sval" id="statWithMatric">—</div><div class="slbl">With Matric Number</div></div>
    <div class="scard"><div class="sval" id="statNoMatric">—</div><div class="slbl">Without Matric Number</div></div>
</div>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Admitted Applicants</h3>
            <div class="panel-muted">Select applicants to activate their admission and/or acceptance letters.</div>
        </div>
    </div>
    <div class="panel-body">
        <!-- Toolbar -->
        <div class="act-bar">
            <select class="form-select form-select-sm" id="filterSelect">
                <option value="all">All Admitted</option>
                <option value="no_matric">Without Matric</option>
                <option value="with_matric">With Matric</option>
            </select>
            <button class="btn btn-sm btn-outline-primary" id="btnSelectAll">Select All</button>
            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-sm btn-primary" id="btnActivateLetters">
                    <i class="fas fa-envelope-open-text me-1"></i> Activate Letters
                </button>
                <button class="btn btn-sm btn-outline-secondary" id="btnDeactivateLetters">
                    <i class="fas fa-ban me-1"></i> Deactivate
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="admTable">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="chkAll"></th>
                        <th>#</th>
                        <th>Applicant</th>
                        <th>App. No.</th>
                        <th>Department</th>
                        <th>Programme</th>
                        <th>Course</th>
                        <th>Matric Number</th>
                        <th>Admission Letter</th>
                        <th>Acceptance Letter</th>
                    </tr>
                </thead>
                <tbody id="admBody">
                    <tr><td colspan="10" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center mt-2" id="paginationArea" style="display:none!important">
            <small class="text-muted" id="pageInfo"></small>
            <div id="pageButtons" class="d-flex gap-1"></div>
        </div>
    </div>
</section>

<!-- Toast -->
<div id="toastWrap">
    <div id="liveToast" class="toast align-items-center text-white border-0" role="alert" style="min-width:280px;">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="toastMsg">Done.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>
const API = '<?php echo app_url("ADMIN/super-admin/api/activate-admissions.php"); ?>';

let currentPage = 1;
let totalPages  = 1;

function toast(msg, type = 'success') {
    const el = document.getElementById('liveToast');
    el.className = `toast align-items-center text-white border-0 bg-${type === 'success' ? 'success' : 'danger'}`;
    document.getElementById('toastMsg').textContent = msg;
    new bootstrap.Toast(el, {delay: 4000}).show();
}

// ── Load table ──────────────────────────────────────────────────────────────
async function loadTable(page = 1) {
    currentPage = page;
    const filter = document.getElementById('filterSelect').value;
    const body   = document.getElementById('admBody');
    body.innerHTML = '<tr><td colspan="10" class="text-center py-4"><div class="spinner-border spinner-border-sm text-success"></div> Loading…</td></tr>';
    document.getElementById('paginationArea').style.display = 'none';

    try {
        const res  = await fetch(`${API}?action=list&filter=${filter}&page=${page}`);
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'API error');

        totalPages = data.pages;
        renderStats(data.total, data.data);
        renderRows(data.data);
        renderPagination(data.total, data.page, data.pages);
    } catch (e) {
        body.innerHTML = `<tr><td colspan="10" class="text-danger text-center">${e.message}</td></tr>`;
    }
}

function renderStats(total, rows) {
    document.getElementById('statTotal').textContent     = total;
    const withM = rows.filter(r => r.matric_number).length;
    const noM   = rows.filter(r => !r.matric_number).length;
    document.getElementById('statWithMatric').textContent = withM;
    document.getElementById('statNoMatric').textContent   = noM;
}

function renderRows(rows) {
    const body = document.getElementById('admBody');
    if (!rows.length) {
        body.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No records found.</td></tr>';
        return;
    }
    body.innerHTML = rows.map((r, i) => {
        const mBadge  = r.matric_number
            ? `<span class="badge-matric">${r.matric_number}</span>`
            : `<span class="badge-none">None</span>`;
        const admBadge  = r.admission_status  === 'Active'
            ? '<span class="badge-active">Active</span>' : '<span class="badge-inactive">Inactive</span>';
        const accBadge  = r.acceptance_status === 'Active'
            ? '<span class="badge-active">Active</span>' : '<span class="badge-inactive">Inactive</span>';

        return `<tr>
            <td><input type="checkbox" class="row-chk" value="${r.application_id}"></td>
            <td>${i + 1}</td>
            <td><strong>${r.full_name || '—'}</strong><br><small class="text-muted">${r.email || ''}</small></td>
            <td><code>${r.application_number}</code></td>
            <td>${r.department || '—'}</td>
            <td>${r.programme  || '—'}</td>
            <td>${r.course     || '—'}</td>
            <td class="matric-cell">${mBadge}</td>
            <td>${admBadge}</td>
            <td>${accBadge}</td>
        </tr>`;
    }).join('');
}

function renderPagination(total, page, pages) {
    const info = document.getElementById('pageInfo');
    const btns = document.getElementById('pageButtons');
    const area = document.getElementById('paginationArea');

    info.textContent = `Showing page ${page} of ${pages} (${total} records)`;
    area.style.display = '';
    let html = '';
    for (let p = 1; p <= pages; p++) {
        html += `<button class="btn btn-sm ${p === page ? 'btn-success' : 'btn-outline-secondary'}" onclick="loadTable(${p})">${p}</button>`;
    }
    btns.innerHTML = html;
}

// ── Bulk generate matric ────────────────────────────────────────────────────
document.getElementById('btnBulkGenerate').addEventListener('click', async () => {
    if (!confirm('Generate IPESS matric numbers for ALL admitted applicants without one?')) return;
    try {
        const fd = new FormData();
        fd.append('action', 'bulk_generate');
        const res  = await fetch(API, {method:'POST', body: fd});
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
        toast(`Generated ${data.generated} matric number(s).`);
        loadTable(1);
    } catch(e) { toast(e.message, 'danger'); }
});

// ── Select all ───────────────────────────────────────────────────────────────
document.getElementById('chkAll').addEventListener('change', function() {
    document.querySelectorAll('.row-chk').forEach(c => c.checked = this.checked);
});
document.getElementById('btnSelectAll').addEventListener('click', () => {
    document.querySelectorAll('.row-chk').forEach(c => c.checked = true);
});

function getSelectedIds() {
    return [...document.querySelectorAll('.row-chk:checked')].map(c => c.value);
}

async function setLetterStatus(admitStat, acceptStat) {
    const ids = getSelectedIds();
    if (!ids.length) { toast('Select at least one applicant.', 'danger'); return; }
    const fd = new FormData();
    fd.append('action',           'set_letter_status');
    fd.append('app_ids',          JSON.stringify(ids));
    fd.append('admission_letter', admitStat);
    fd.append('acceptance_letter', acceptStat);
    try {
        const res  = await fetch(API, {method:'POST', body: fd});
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
        toast(`Updated ${data.updated} record(s).`);
        loadTable(currentPage);
    } catch(e) { toast(e.message, 'danger'); }
}

document.getElementById('btnActivateLetters').addEventListener('click',   () => setLetterStatus('Active',   'Active'));
document.getElementById('btnDeactivateLetters').addEventListener('click', () => setLetterStatus('Inactive', 'Inactive'));
document.getElementById('filterSelect').addEventListener('change', () => loadTable(1));

// ── Initial load ─────────────────────────────────────────────────────────────
loadTable(1);
</script>

<?php require_once 'includes/footer.php'; ?>
