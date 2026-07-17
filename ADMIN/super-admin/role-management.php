<?php
require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');
require_role(['SUPER_ADMIN', 'ICT_ADMIN'], 'ADMIN/login.php');

$pageTitle = 'Role & Duty Management';
$pageSubtitle = 'Assign and configure default role duties and individual user overrides.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
require_once __DIR__ . '/../../ADMIN/admin/includes/db.php';

$users = [];
if (isset($pdo)) {
    try {
        $users = $pdo->query("
            SELECT u.user_id, u.email, u.full_name, r.role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.role_id
            ORDER BY u.full_name, u.email
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}
?>

<section class="page-hero bg-dark text-white p-4 rounded-3 mb-4">
    <div>
        <h1 class="h2 fw-bold text-warning mb-1">Role &amp; Duty Management</h1>
        <p class="text-white-50 mb-0">Define institution-wide permissions for core roles and apply custom user duty overrides.</p>
    </div>
</section>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-4 gap-2" id="roleTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-bold btn-lg" id="roles-directory-tab" data-bs-toggle="tab" data-bs-target="#roles-directory" type="button" role="tab">
            <i class="fas fa-shield-alt me-2 text-warning"></i>Role Duties Directory
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-bold btn-lg" id="user-duties-tab" data-bs-toggle="tab" data-bs-target="#user-duties" type="button" role="tab">
            <i class="fas fa-user-shield me-2 text-warning"></i>User Duties Configuration
        </button>
    </li>
</ul>

<!-- Tabs Content -->
<div class="tab-content" id="roleTabsContent">

    <!-- Tab 1: Role Duties Directory -->
    <div class="tab-pane fade show active" id="roles-directory" role="tabpanel">
        <section class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-list me-2"></i>Active Roles</h5>
                <small class="text-muted">Standard roles defined in the system. Edit their default permission sets.</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="rolesTable">
                        <thead class="table-light">
                            <tr>
                                <th>Role Key</th>
                                <th>Role Name</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    <!-- Tab 2: User Duties Configuration -->
    <div class="tab-pane fade" id="user-duties" role="tabpanel">
        <section class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-users-cog me-2"></i>User Duty Overrides</h5>
                    <small class="text-muted">Grant or deny specific duties for individual users independent of their role.</small>
                </div>
                <div style="max-width: 250px;">
                    <input type="text" id="userSearch" class="form-control form-control-sm" placeholder="Search user...">
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" id="usersTable">
                        <thead class="table-light">
                            <tr>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Assigned Role</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $u): ?>
                                    <tr class="user-row">
                                        <td class="user-name"><strong><?= htmlspecialchars($u['full_name'] ?: 'N/A') ?></strong></td>
                                        <td class="user-email"><?= htmlspecialchars($u['email']) ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($u['role_name'] ?: 'Unassigned') ?></span></td>
                                        <td class="text-end">
                                            <button class="btn btn-outline-primary btn-sm edit-permissions-btn" data-id="<?= $u['user_id'] ?>" data-name="<?= htmlspecialchars($u['full_name'] ?: $u['email']) ?>">
                                                <i class="fas fa-user-lock me-1"></i>Configure Duties
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No users found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

</div>

<!-- Role Duties Modal -->
<div class="modal fade" id="dutiesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dutiesModalLabel">Manage Duties for Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height:65vh;overflow-y:auto;">
                <form id="dutiesForm">
                    <input type="hidden" id="modalRoleKey" name="role_key">
                    <p class="text-muted small mb-3">Check the default permissions this role is allowed to perform.</p>

                    <?php
                    if (function_exists('get_all_permission_groups')) {
                        foreach (get_all_permission_groups() as $groupKey => $group) {
                            $groupId = 'grp_' . $groupKey;
                            echo '<div class="card mb-3 border">';
                            echo '<div class="card-header d-flex align-items-center justify-content-between py-2 px-3 bg-light" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#' . $groupId . '">';
                            echo '<span><i class="' . htmlspecialchars($group['icon'], ENT_QUOTES, 'UTF-8') . ' me-2 text-primary"></i><strong>' . htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8') . '</strong></span>';
                            echo '<span class="d-flex align-items-center gap-2">';
                            echo '<button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 select-all-btn" data-group="' . $groupId . '" onclick="event.stopPropagation();toggleGroupAll(\'' . $groupId . '\',this)">Select All</button>';
                            echo '<i class="fas fa-chevron-down text-muted small"></i>';
                            echo '</span>';
                            echo '</div>';
                            echo '<div class="collapse show" id="' . $groupId . '">';
                            echo '<div class="card-body py-2 px-3">';
                            foreach ($group['permissions'] as $perm) {
                                $permId = 'perm_' . $perm['key'];
                                echo '<div class="form-check mb-2 ps-4">';
                                echo '<input class="form-check-input perm-check" type="checkbox" name="permissions[]" value="' . htmlspecialchars($perm['key'], ENT_QUOTES, 'UTF-8') . '" id="' . $permId . '" data-group="' . $groupId . '">';
                                echo '<label class="form-check-label" for="' . $permId . '">';
                                echo '<strong>' . htmlspecialchars($perm['name'], ENT_QUOTES, 'UTF-8') . '</strong>';
                                echo '<span class="d-block text-muted small">' . htmlspecialchars($perm['description'], ENT_QUOTES, 'UTF-8') . '</span>';
                                echo '</label>';
                                echo '</div>';
                            }
                            echo '</div></div></div>';
                        }
                    }
                    ?>
                </form>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectAllPermissions()">
                    <i class="fas fa-check-double me-1"></i>Select All
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveDutiesBtn">Save Duties</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- User Permissions Override Modal -->
<div class="modal fade" id="userPermissionsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configure Custom Overrides: <span id="permModalTitleName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height: 65vh; overflow-y: auto;">
                <input type="hidden" id="permModalUserId">
                <p class="text-muted small mb-3">
                    Explicitly grant or deny permissions for this specific user. Explicit overrides bypass their role configuration.
                </p>
                <div class="row" id="userPermissionsGrid">
                    <!-- Loaded dynamically via JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveUserPermissionOverrides()">Save Custom Overrides</button>
            </div>
        </div>
    </div>
</div>

<!-- Make sure Bootstrap Bundle is loaded for Modals if not already in header -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
const rolesTableBody = document.querySelector('#rolesTable tbody');
const dutiesModal = new bootstrap.Modal(document.getElementById('dutiesModal'));
const userPermissionsModal = new bootstrap.Modal(document.getElementById('userPermissionsModal'));
const dutiesForm = document.getElementById('dutiesForm');
const modalRoleKeyInput = document.getElementById('modalRoleKey');
const saveDutiesBtn = document.getElementById('saveDutiesBtn');

// Filter users list
document.getElementById('userSearch').addEventListener('input', function() {
    const val = this.value.toLowerCase();
    document.querySelectorAll('.user-row').forEach(row => {
        const name = row.querySelector('.user-name').innerText.toLowerCase();
        const email = row.querySelector('.user-email').innerText.toLowerCase();
        if (name.includes(val) || email.includes(val)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

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
                        <button class="btn btn-outline-primary btn-sm assign-duties-btn" data-key="${role.role_key}" data-name="${role.role_name}">
                            <i class="fas fa-user-tag me-1"></i>Configure Default Duties
                        </button>
                    </td>
                `;
                row.querySelector('.assign-duties-btn').addEventListener('click', () => openDutiesModal(role.role_key, role.role_name));
                rolesTableBody.appendChild(row);
            });
        });
}

function openDutiesModal(roleKey, roleName) {
    document.getElementById('dutiesModalLabel').innerText = `Manage Duties for: ${roleName}`;
    modalRoleKeyInput.value = roleKey;
    
    // Reset checks
    dutiesForm.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
    dutiesForm.querySelectorAll('.select-all-btn').forEach(btn => {
        btn.textContent = 'Select All';
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-outline-primary');
    });
    
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

function toggleGroupAll(groupId, btn) {
    const checkboxes = dutiesForm.querySelectorAll(`.perm-check[data-group="${groupId}"]`);
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
    if (allChecked) {
        btn.textContent = 'Select All';
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-outline-primary');
    } else {
        btn.textContent = 'Deselect All';
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-primary');
    }
}

function selectAllPermissions() {
    dutiesForm.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = true);
    dutiesForm.querySelectorAll('.select-all-btn').forEach(btn => {
        btn.textContent = 'Deselect All';
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-primary');
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
            alert('Default duties updated successfully!');
        } else {
            alert(data.message || 'Error updating duties.');
        }
    });
});

// Setup User Permissions Grid Override
const systemPermissions = [
    <?php
    if (function_exists('get_all_permission_groups')) {
        $flatList = [];
        foreach (get_all_permission_groups() as $group) {
            foreach ($group['permissions'] as $perm) {
                $flatList[] = "{ key: '" . addslashes($perm['key']) . "', label: '" . addslashes($perm['name']) . "' }";
            }
        }
        echo implode(",\n", $flatList);
    }
    ?>
];

document.querySelectorAll('.edit-permissions-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const userId = this.dataset.id;
        const userName = this.dataset.name;
        
        document.getElementById('permModalUserId').value = userId;
        document.getElementById('permModalTitleName').innerText = userName;
        
        // Fetch current overrides
        fetch(`api/user-permissions.php?user_id=${userId}`)
            .then(res => res.json())
            .then(data => {
                const overrides = (data.success && data.data && data.data.overrides) ? data.data.overrides : {};
                renderUserPermissionsGrid(overrides);
            });
    });
});

function renderUserPermissionsGrid(overrides) {
    const container = document.getElementById('userPermissionsGrid');
    container.innerHTML = '';
    
    systemPermissions.forEach(p => {
        const hasOverride = overrides.hasOwnProperty(p.key);
        const overrideVal = hasOverride ? parseInt(overrides[p.key]) : null;
        
        const div = document.createElement('div');
        div.className = 'col-md-6 mb-3 p-2 bg-light rounded border';
        div.innerHTML = `
            <div class="fw-bold mb-1" style="font-size: 0.85rem;">${p.label}</div>
            <div class="d-flex gap-3" style="font-size: 0.75rem;">
                <div class="form-check">
                    <input class="form-check-input perm-radio" type="radio" name="perm_${p.key}" id="inherit_${p.key}" value="inherit" ${!hasOverride ? 'checked' : ''}>
                    <label class="form-check-label text-muted" for="inherit_${p.key}">Inherit</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input perm-radio text-success" type="radio" name="perm_${p.key}" id="grant_${p.key}" value="1" ${overrideVal === 1 ? 'checked' : ''}>
                    <label class="form-check-label text-success fw-semibold" for="grant_${p.key}">Grant</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input perm-radio text-danger" type="radio" name="perm_${p.key}" id="deny_${p.key}" value="0" ${overrideVal === 0 ? 'checked' : ''}>
                    <label class="form-check-label text-danger fw-semibold" for="deny_${p.key}">Deny</label>
                </div>
            </div>
        `;
        container.appendChild(div);
    });
    
    userPermissionsModal.show();
}

async function saveUserPermissionOverrides() {
    const userId = document.getElementById('permModalUserId').value;
    if (!userId) return;

    const data = [];
    document.querySelectorAll('.perm-radio:checked').forEach(radio => {
        const key = radio.name.replace('perm_', '');
        const val = radio.value;
        if (val !== 'inherit') {
            data.push({ permission_key: key, granted: parseInt(val) });
        }
    });

    const form = new FormData();
    form.append('user_id', userId);
    form.append('overrides', JSON.stringify(data));

    const res = await fetch('api/user-permissions.php', { method: 'POST', body: form });
    const result = await res.json();

    if (result.success) {
        alert('User permission overrides saved successfully.');
        userPermissionsModal.hide();
    } else {
        alert(result.message || 'Failed to save overrides.');
    }
}

loadRoles();
</script>

<?php require_once 'includes/footer.php'; ?>
