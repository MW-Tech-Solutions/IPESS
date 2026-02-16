<?php
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

<script>
const roleForm = document.getElementById('roleForm');
const rolesTableBody = document.querySelector('#rolesTable tbody');
const refreshRolesBtn = document.getElementById('refreshRoles');

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
                    <td>${role.role_key}</td>
                    <td>${role.role_name}</td>
                    <td class="text-end">
                        <button class="btn btn-light btn-sm" data-id="${role.role_id}">Delete</button>
                    </td>
                `;
                row.querySelector('button').addEventListener('click', () => deleteRole(role.role_id));
                rolesTableBody.appendChild(row);
            });
        });
}

function deleteRole(id) {
    if (!confirm('Delete this role?')) return;
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
