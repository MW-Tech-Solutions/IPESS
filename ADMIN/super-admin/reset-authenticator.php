<?php
/**
 * Reset Authenticator — Admin UI
 * Permission-gated: requires `reset_authenticator` permission.
 * Does NOT call the standard header.php (which enforces SUPER_ADMIN/ICT_ADMIN only).
 * Bootstrap JS is loaded locally to avoid CDN tracking-prevention blocks.
 */
require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');

// Any logged-in user with this permission can access the page (incl. ICT_SUPPORT)
if (!has_permission('reset_authenticator')) {
    http_response_code(403);
    exit('403 Forbidden — You do not have permission to access this page.');
}

$pageTitle = 'Reset Authenticator';


$cssUrl = app_url('ADMIN/super-admin/super-admin.css');
$bsCss  = app_url('asset/vendor/bootstrap/css/bootstrap.min.css');
$bsJs   = app_url('asset/vendor/bootstrap/js/bootstrap.bundle.min.js');
$faUrl  = app_url('asset/vendor/fontawesome/css/all.min.css');
// Fallback to CDN if local vendor files are missing
$bsCssFallback  = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
$bsJsFallback   = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
$faUrlFallback  = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css';

$localBsCss  = defined('JOSTUM_ROOT') && file_exists(JOSTUM_ROOT . '/asset/vendor/bootstrap/css/bootstrap.min.css');
$localBsJs   = defined('JOSTUM_ROOT') && file_exists(JOSTUM_ROOT . '/asset/vendor/bootstrap/js/bootstrap.bundle.min.js');
$localFa     = defined('JOSTUM_ROOT') && file_exists(JOSTUM_ROOT . '/asset/vendor/fontawesome/css/all.min.css');

$bsCssHref  = $localBsCss ? $bsCss  : $bsCssFallback;
$bsJsSrc    = $localBsJs  ? $bsJs   : $bsJsFallback;
$faHref     = $localFa    ? $faUrl  : $faUrlFallback;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> | JOSTUM PG Admin</title>
<link rel="icon" type="image/jpeg" href="/ADMIN/images/logo.jpeg">
<!-- Bootstrap CSS -->
<link href="<?php echo $bsCssHref; ?>" rel="stylesheet">
<!-- Font Awesome -->
<link rel="stylesheet" href="<?php echo $faHref; ?>">
<!-- Super-admin CSS -->
<link rel="stylesheet" href="<?php echo $cssUrl; ?>">
</head>
<body>
<div class="admin-shell" id="admin-shell">

<?php require_once 'includes/sidebar.php'; ?>

<div class="main-content">
<?php require_once 'includes/topbar.php'; ?>
<main class="page-content">

<section class="page-hero">
    <div>
        <h1>Reset Authenticator App</h1>
        <p class="panel-muted">
            Search for a staff or student account and reset their two-factor authentication (TOTP) secret.
            The user will be prompted to set up a new authenticator on their next login.
            This does <strong>not</strong> require the user's password.
        </p>
    </div>
</section>

<!-- Search Panel -->
<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Find Account</h3>
            <div class="panel-muted">Search by name or email address.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-8">
                <label class="form-label fw-semibold">Name or Email</label>
                <input type="text" id="searchInput" class="form-control form-control-lg"
                       placeholder="e.g. Adamu Muhammad or adamu@jostum.edu.ng" autocomplete="off">
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary w-100 py-2" id="searchBtn" onclick="searchUsers()">
                    <i class="fas fa-search me-2"></i> Search
                </button>
            </div>
        </div>

        <div id="searchResults" class="mt-4" style="display:none;">
            <table class="table align-middle" id="resultsTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>2FA Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="resultsBody"></tbody>
            </table>
        </div>
        <div id="searchMsg" class="text-muted mt-3" style="display:none;"></div>
    </div>
</section>

<!-- Confirm Reset Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning-subtle">
                <h5 class="modal-title" id="confirmModalLabel">
                    <i class="fas fa-shield-alt me-2 text-warning"></i>Confirm Authenticator Reset
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>You are about to <strong>clear the 2FA authenticator secret</strong> for:</p>
                <div class="alert alert-warning">
                    <strong id="confirmName"></strong><br>
                    <small id="confirmEmail" class="text-muted"></small>
                </div>
                <p class="mb-0 text-muted small">
                    After reset, the user will need to set up a new authenticator app on their next login.
                    This action will be recorded in the audit log.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirmResetBtn">
                    <i class="fas fa-redo me-2"></i>Reset Authenticator
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Success / Error Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="actionToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="toastMessage">Done.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

</main>
</div><!-- /.main-content -->
</div><!-- /.admin-shell -->

<!-- ── Bootstrap JS must load BEFORE any bootstrap.Modal / Toast calls ── -->
<script src="<?php echo $bsJsSrc; ?>"></script>

<script>
// Bootstrap is now guaranteed loaded above before this runs
let selectedUser = null;
let confirmModal = null;
let actionToast  = null;

// Initialise after DOM ready (Bootstrap already loaded at this point)
document.addEventListener('DOMContentLoaded', function () {
    confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
    actionToast  = new bootstrap.Toast(document.getElementById('actionToast'), { delay: 4000 });

    document.getElementById('searchInput').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') searchUsers();
    });

    document.getElementById('confirmResetBtn').addEventListener('click', function () {
        if (!selectedUser) return;
        const btn = document.getElementById('confirmResetBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Resetting…';

        const fd = new FormData();
        fd.append('action',    'reset');
        fd.append('user_id',   selectedUser.userId);
        fd.append('user_type', selectedUser.role === 'STUDENT' ? 'applicant' : 'staff');

        fetch('api/reset-authenticator.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-redo me-2"></i>Reset Authenticator';
                confirmModal.hide();
                showToast(
                    data.message || (data.success ? 'Done.' : 'Failed.'),
                    data.success ? 'bg-success' : 'bg-danger'
                );
                if (data.success) searchUsers();
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-redo me-2"></i>Reset Authenticator';
                showToast('Network error. Please try again.', 'bg-danger');
            });
    });
});

function searchUsers() {
    const q = document.getElementById('searchInput').value.trim();
    if (q.length < 2) { showMsg('Enter at least 2 characters.'); return; }

    document.getElementById('searchBtn').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Searching…';

    fetch('api/reset-authenticator.php?action=search&q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            document.getElementById('searchBtn').innerHTML = '<i class="fas fa-search me-2"></i>Search';
            if (!data.success) { showMsg(data.message); return; }
            if (!data.data || !data.data.length) { showMsg('No accounts found for that search.'); return; }
            renderResults(data.data);
        })
        .catch(() => {
            document.getElementById('searchBtn').innerHTML = '<i class="fas fa-search me-2"></i>Search';
            showMsg('Request failed. Please try again.');
        });
}

function renderResults(users) {
    const tbody = document.getElementById('resultsBody');
    tbody.innerHTML = '';
    users.forEach(u => {
        const hasTotp   = parseInt(u.has_totp) === 1;
        const totpBadge = hasTotp
            ? '<span class="badge bg-success">Active 2FA</span>'
            : '<span class="badge bg-secondary">No 2FA</span>';
        const resetBtn  = hasTotp
            ? `<button class="btn btn-outline-warning btn-sm"
                   onclick="openConfirm(${Number(u.user_id)},'${esc(u.full_name)}','${esc(u.email)}','${esc(u.role ?? 'staff')}')">
                   <i class="fas fa-redo me-1"></i>Reset
               </button>`
            : '<span class="text-muted small">No 2FA to reset</span>';

        const row = document.createElement('tr');
        row.innerHTML = `
            <td><strong>${esc(u.full_name)}</strong></td>
            <td>${esc(u.email)}</td>
            <td><code>${esc(u.role ?? '—')}</code></td>
            <td>${totpBadge}</td>
            <td class="text-end">${resetBtn}</td>
        `;
        tbody.appendChild(row);
    });
    document.getElementById('searchMsg').style.display   = 'none';
    document.getElementById('searchResults').style.display = 'block';
}

function openConfirm(userId, name, email, role) {
    selectedUser = { userId, name, email, role };
    document.getElementById('confirmName').textContent  = name;
    document.getElementById('confirmEmail').textContent = email;
    if (confirmModal) confirmModal.show();
}

function showMsg(msg) {
    const el = document.getElementById('searchMsg');
    el.textContent = msg;
    el.style.display = 'block';
    document.getElementById('searchResults').style.display = 'none';
}

function showToast(msg, bg) {
    bg = bg || 'bg-success';
    const el = document.getElementById('actionToast');
    el.className = 'toast align-items-center text-white border-0 ' + bg;
    document.getElementById('toastMessage').textContent = msg;
    if (actionToast) actionToast.show();
}

function esc(str) {
    return String(str ?? '').replace(/[&<>"']/g, c =>
        ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])
    );
}
</script>
</body>
</html>
