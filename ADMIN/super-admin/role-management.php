<?php
require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');
require_role(['SUPER_ADMIN', 'ICT_ADMIN'], 'ADMIN/login.php');

$pageTitle = 'Role Management';
$pageSubtitle = 'Define access levels for administrators and reviewers.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Role Management</h1>
        <p class="panel-muted">Maintain role definitions that control access across the portal.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-primary" id="refreshRoles">Refresh</button>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Create Role</h3>
            <div class="panel-muted">Add a new access profile.</div>
        </div>
    </div>
    <div class="panel-body">
        <form id="roleForm" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Role Key</label>
                <input type="text" class="form-control" name="role_key" placeholder="SUPER_ADMIN" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Role Name</label>
                <input type="text" class="form-control" name="role_name" placeholder="Super Admin" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit">Add</button>
            </div>
        </form>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Roles Directory</h3>
            <div class="panel-muted">Active roles currently defined in the system.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="rolesTable">
                <thead>
                    <tr>
                        <th>Role Key</th>
                        <th>Role Name</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</section>

<!-- Duties Modal -->
<div class="modal fade" id="dutiesModal" tabindex="-1" aria-labelledby="dutiesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dutiesModalLabel">Manage Duties for Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="dutiesForm">
                    <input type="hidden" id="modalRoleKey" name="role_key">
                    
                    <p class="text-muted small mb-3">Check the duties/permissions this role is allowed to perform.</p>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="view_applications" id="permViewApps">
                        <label class="form-check-label" for="permViewApps">
                            <strong>View Applications</strong>
                            <span class="d-block text-muted small">Access dashboard and view list/details of applicant profiles.</span>
                        </label>
                    </div>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="download_applications" id="permDownloadApps">
                        <label class="form-check-label" for="permDownloadApps">
                            <strong>Download Applications PDF</strong>
                            <span class="d-block text-muted small">Download or print full application form summaries.</span>
                        </label>
                    </div>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="download_documents" id="permDownloadDocs">
                        <label class="form-check-label" for="permDownloadDocs">
                            <strong>Download Credentials & Documents</strong>
                            <span class="d-block text-muted small">Download uploaded certificates, transcripts, O-levels, and NYSC docs.</span>
                        </label>
                    </div>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="manage_users" id="permManageUsers">
                        <label class="form-check-label" for="permManageUsers">
                            <strong>Manage Staff Users</strong>
                            <span class="d-block text-muted small">Create and control administrative/review staff accounts.</span>
                        </label>
                    </div>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="manage_roles" id="permManageRoles">
                        <label class="form-check-label" for="permManageRoles">
                            <strong>Manage Roles & System Duties</strong>
                            <span class="d-block text-muted small">Full administrative privileges to modify roles and system duties.</span>
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-content-footer p-3 border-top d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveDutiesBtn">Save Duties</button>
            </div>
        </div>
    </div>
</div>

<!-- Make sure Bootstrap Bundle is loaded for Modals if not already in header -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
const roleForm = document.getElementById('roleForm');
const rolesTableBody = document.querySelector('#rolesTable tbody');
const refreshRolesBtn = document.getElementById('refreshRoles');
const dutiesModal = new bootstrap.Modal(document.getElementById('dutiesModal'));
const dutiesForm = document.getElementById('dutiesForm');
const modalRoleKeyInput = document.getElementById('modalRoleKey');
const saveDutiesBtn = document.getElementById('saveDutiesBtn');

function loadRoles() {
    fetch('api/manage-entities.php?entity=roles&action=list')
        .then(response => response.json())
        .then(data => {
            rolesTableBody.innerHTML = '';
            if (!data.success || !data.data.length) {
                rolesTableBody.innerHTML = '<tr><td colspan="3" class="text-muted text-center">No roles found.</td></tr>';
                return;
            }
            data.data.forEach(role => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><code>${role.role_key}</code></td>
                    <td><strong>${role.role_name}</strong></td>
                    <td class="text-end">
                        <button class="btn btn-outline-primary btn-sm me-1 assign-duties-btn" data-key="${role.role_key}" data-name="${role.role_name}">Assign Duties</button>
                        <button class="btn btn-light btn-sm delete-role-btn" data-id="${role.role_id}">Delete</button>
                    </td>
                `;
                
                row.querySelector('.assign-duties-btn').addEventListener('click', () => openDutiesModal(role.role_key, role.role_name));
                row.querySelector('.delete-role-btn').addEventListener('click', () => deleteRole(role.role_id));
                rolesTableBody.appendChild(row);
            });
        });
}

function openDutiesModal(roleKey, roleName) {
    document.getElementById('dutiesModalLabel').innerText = `Manage Duties for: ${roleName}`;
    modalRoleKeyInput.value = roleKey;
    
    // Reset checkboxes
    dutiesForm.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
    
    // Fetch current permissions
    fetch(`api/manage-entities.php?action=get_permissions&role_key=${encodeURIComponent(roleKey)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                data.data.forEach(perm => {
                    const cb = dutiesForm.querySelector(`input[value="${perm}"]`);
                    if (cb) cb.checked = true;
                });
            }
            dutiesModal.show();
        });
}

saveDutiesBtn.addEventListener('click', () => {
    const formData = new FormData(dutiesForm);
    formData.append('action', 'save_permissions');
    
    fetch('api/manage-entities.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            dutiesModal.hide();
            alert('Duties updated successfully!');
        } else {
            alert(data.message || 'Error updating duties.');
        }
    });
});

function deleteRole(id) {
    if (!confirm('Are you sure you want to delete this role? This may impact users assigned to it.')) return;
    const formData = new FormData();
    formData.append('entity', 'roles');
    formData.append('action', 'delete');
    formData.append('id', id);
    fetch('api/manage-entities.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(() => loadRoles());
}

roleForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(roleForm);
    formData.append('entity', 'roles');
    formData.append('action', 'create');
    fetch('api/manage-entities.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Unable to add role.');
                return;
            }
            roleForm.reset();
            loadRoles();
        });
});

refreshRolesBtn.addEventListener('click', loadRoles);
loadRoles();
</script>

<?php require_once 'includes/footer.php'; ?>
