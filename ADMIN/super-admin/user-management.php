<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SUPER_ADMIN') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'User Management';
$pageSubtitle = 'Manage staff and administrative accounts across the system.';

require_once 'includes/db.php';

$stats = ['total' => 0, 'active' => 0, 'suspended' => 0, 'locked' => 0];
$users = [];
$roles = [];
$departments = [];

if ($pdo) {
    $statsSql = "
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN account_status = 'Active' THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN account_status = 'Suspended' THEN 1 ELSE 0 END) AS suspended,
            SUM(CASE WHEN account_status = 'Locked' THEN 1 ELSE 0 END) AS locked
        FROM users
        WHERE NOT EXISTS (
            SELECT 1
            FROM applications ax
            WHERE ax.user_id = users.user_id
        )
    ";
    $statsStmt = $pdo->query($statsSql);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: $stats;

    $roles = $pdo->query("SELECT role_id, role_key, role_name FROM roles ORDER BY role_name")->fetchAll(PDO::FETCH_ASSOC);
    $departments = $pdo->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name")->fetchAll(PDO::FETCH_ASSOC);

    $usersSql = "
        SELECT u.user_id, u.email, u.account_status, u.last_login, u.created_at,
               u.full_name, u.role_id, u.department_id,
               r.role_name,
               p.first_name, p.surname, a.application_number,
               d_admin.dept_name AS admin_dept_name,
               d_app.dept_name AS app_dept_name
        FROM users u
        LEFT JOIN roles r ON r.role_id = u.role_id
        LEFT JOIN departments d_admin ON d_admin.dept_id = u.department_id
        LEFT JOIN applications a ON a.application_id = (
            SELECT a2.application_id
            FROM applications a2
            WHERE a2.user_id = u.user_id
            ORDER BY a2.submitted_at DESC, a2.application_id DESC
            LIMIT 1
        )
        LEFT JOIN personal_details p ON p.application_id = a.application_id
        LEFT JOIN programme_choices pc ON pc.application_id = a.application_id
        LEFT JOIN departments d_app ON d_app.dept_id = pc.department
        WHERE NOT EXISTS (
            SELECT 1
            FROM applications ax
            WHERE ax.user_id = u.user_id
        )
        ORDER BY u.created_at DESC
    ";
    $users = $pdo->query($usersSql)->fetchAll(PDO::FETCH_ASSOC);
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Users & Accounts</h1>
        <p class="panel-muted">Track staff/admin accounts, monitor account status, and manage onboarding actions.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addUserModal">Add Account</button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exportUsersModal">Export List</button>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div>
            <div class="stat-title">Total Users</div>
            <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        <div>
            <div class="stat-title">Active</div>
            <div class="stat-value"><?php echo number_format($stats['active']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-slash"></i></div>
        <div>
            <div class="stat-title">Suspended</div>
            <div class="stat-value"><?php echo number_format($stats['suspended']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-lock"></i></div>
        <div>
            <div class="stat-title">Locked</div>
            <div class="stat-value"><?php echo number_format($stats['locked']); ?></div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Account Directory</h3>
            <div class="panel-muted">Administrative users only. Student records are managed in Manage Students.</div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm">Bulk Actions</button>
            <button class="btn btn-light btn-sm">Reset Filters</button>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="usersTable">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" id="selectAll">
                        </th>
                        <th>Applicant</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $user): ?>
                            <?php
                            $statusClass = 'status-muted';
                            if ($user['account_status'] === 'Active') {
                                $statusClass = 'status-success';
                            } elseif ($user['account_status'] === 'Suspended') {
                                $statusClass = 'status-warning';
                            } elseif ($user['account_status'] === 'Locked') {
                                $statusClass = 'status-danger';
                            }
                            $name = $user['full_name'] ?: trim(($user['first_name'] ?? '') . ' ' . ($user['surname'] ?? ''));
                            $role = $user['role_name'] ?: ($user['application_number'] ? 'Applicant' : 'Unassigned');
                            $displayDept = $user['admin_dept_name'] ?: ($user['app_dept_name'] ?? 'Unassigned');
                            ?>
                            <tr>
                                <td><input type="checkbox" class="user-checkbox" value="<?php echo (int) $user['user_id']; ?>"></td>
                                <td><?php echo htmlspecialchars($name ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($role); ?></td>
                                <td><?php echo htmlspecialchars($displayDept); ?></td>
                                <td><span class="status-chip <?php echo $statusClass; ?>"><?php echo htmlspecialchars($user['account_status']); ?></span></td>
                                <td><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                <td><?php echo $user['created_at'] ? date('M d, Y', strtotime($user['created_at'])) : 'N/A'; ?></td>
                                <td class="text-end">
                                    <?php if ($user['account_status'] === 'Active'): ?>
                                        <button class="btn btn-warning btn-sm suspend-user" data-id="<?php echo (int) $user['user_id']; ?>">Suspend</button>
                                        <button class="btn btn-danger btn-sm lock-user" data-id="<?php echo (int) $user['user_id']; ?>">Lock</button>
                                    <?php elseif ($user['account_status'] === 'Suspended'): ?>
                                        <button class="btn btn-success btn-sm activate-user" data-id="<?php echo (int) $user['user_id']; ?>">Activate</button>
                                        <button class="btn btn-danger btn-sm lock-user" data-id="<?php echo (int) $user['user_id']; ?>">Lock</button>
                                    <?php else: ?>
                                        <button class="btn btn-success btn-sm activate-user" data-id="<?php echo (int) $user['user_id']; ?>">Activate</button>
                                        <button class="btn btn-warning btn-sm suspend-user" data-id="<?php echo (int) $user['user_id']; ?>">Suspend</button>
                                    <?php endif; ?>
                                    <button class="btn btn-light btn-sm edit-user"
                                        data-id="<?php echo (int) $user['user_id']; ?>"
                                        data-full-name="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"
                                        data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                        data-role-id="<?php echo (int) ($user['role_id'] ?? 0); ?>"
                                        data-department-id="<?php echo (int) ($user['department_id'] ?? 0); ?>"
                                        data-status="<?php echo htmlspecialchars($user['account_status']); ?>">
                                        Edit
                                    </button>
                                    <button class="btn btn-light btn-sm send-reset" data-id="<?php echo (int) $user['user_id']; ?>">Send Reset</button>
                                    <button class="btn btn-light btn-sm delete-user" data-id="<?php echo (int) $user['user_id']; ?>">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">No users found in the system.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>


<div class="modal fade" id="exportUsersModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Export User List</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-4">Choose the format and how you want to receive the report.</p>
                <div class="d-grid gap-2">
                    <a class="btn btn-outline-primary" href="export-users.php?format=pdf&mode=view" target="_blank">View PDF (Print/Save)</a>
                    <a class="btn btn-primary" href="export-users.php?format=csv&mode=download">Download Excel (CSV)</a>
                    <a class="btn btn-outline-secondary" href="export-users.php?format=pdf&mode=download">Download PDF</a>
                </div>
                <div class="text-muted small mt-3">
                    Use "View PDF" to open a printable report and save as PDF from your browser.
                </div>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create User Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addUserForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Status</label>
                            <select class="form-select" name="account_status">
                                <option value="Active">Active</option>
                                <option value="Suspended">Suspended</option>
                                <option value="Locked">Locked</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role_id" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $roleOption): ?>
                                    <option value="<?php echo (int) $roleOption['role_id']; ?>" data-role-key="<?php echo htmlspecialchars($roleOption['role_key']); ?>"><?php echo htmlspecialchars($roleOption['role_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department (for Department Admin)</label>
                            <select class="form-select" name="department_id">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $deptOption): ?>
                                    <option value="<?php echo (int) $deptOption['dept_id']; ?>"><?php echo htmlspecialchars($deptOption['dept_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="specializationField" style="display: none;">
                            <label class="form-label">Area of Specialization</label>
                            <input type="text" class="form-control" name="specialization" placeholder="e.g. Educational Technology">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" rows="3" placeholder="Optional notes for onboarding"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editUserForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" value="">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Status</label>
                            <select class="form-select" name="account_status">
                                <option value="Active">Active</option>
                                <option value="Suspended">Suspended</option>
                                <option value="Locked">Locked</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role_id" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $roleOption): ?>
                                    <option value="<?php echo (int) $roleOption['role_id']; ?>" data-role-key="<?php echo htmlspecialchars($roleOption['role_key']); ?>"><?php echo htmlspecialchars($roleOption['role_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department (for Department Admin)</label>
                            <select class="form-select" name="department_id">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $deptOption): ?>
                                    <option value="<?php echo (int) $deptOption['dept_id']; ?>"><?php echo htmlspecialchars($deptOption['dept_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = this.checked);
});

const roleSelect = document.querySelector('#addUserForm select[name="role_id"]');
const specializationField = document.getElementById('specializationField');

function toggleSpecializationField() {
    if (!roleSelect || !specializationField) return;
    const selectedOption = roleSelect.options[roleSelect.selectedIndex];
    const roleKey = selectedOption ? selectedOption.getAttribute('data-role-key') : '';
    specializationField.style.display = roleKey === 'SUPERVISOR' ? '' : 'none';
}

if (roleSelect) {
    roleSelect.addEventListener('change', toggleSpecializationField);
    toggleSpecializationField();
}

document.getElementById('addUserForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('api/user-management.php', {
        method: 'POST',
        body: formData
    })
    .then(async (response) => {
        const text = await response.text();
        let data = null;
        try {
            data = JSON.parse(text);
        } catch (e) {
            alert('Server response:\n' + text);
            return null;
        }
        return data;
    })
    .then(data => {
                if (!data) return;
        if (!data.success) {
            alert(data.message || 'Unable to create user.');
            return;
        }
        if (data.mail_warning) {
            alert((data.message || 'User created successfully.') + "\n\nEmail warning: " + data.mail_warning);
        } else {
            alert(data.message || 'User created successfully.');
        }
        window.location.reload();
    })
    .catch(() => alert('Unable to create user. Please try again.'));
});

const editUserModal = document.getElementById('editUserModal');
const editUserForm = document.getElementById('editUserForm');

document.querySelectorAll('.edit-user').forEach(button => {
    button.addEventListener('click', function() {
        const userId = this.dataset.id;
        const fullName = this.dataset.fullName || '';
        const email = this.dataset.email || '';
        const roleId = this.dataset.roleId || '';
        const departmentId = this.dataset.departmentId || '';
        const status = this.dataset.status || 'Active';

        editUserForm.querySelector('input[name="user_id"]').value = userId;
        editUserForm.querySelector('input[name="full_name"]').value = fullName;
        editUserForm.querySelector('input[name="email"]').value = email;
        editUserForm.querySelector('select[name="role_id"]').value = roleId;
        editUserForm.querySelector('select[name="department_id"]').value = departmentId;
        editUserForm.querySelector('select[name="account_status"]').value = status;

        const modal = new bootstrap.Modal(editUserModal);
        modal.show();
    });
});

editUserForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('api/user-management.php', {
        method: 'POST',
        body: formData
    })
    .then(async (response) => {
        const text = await response.text();
        let data = null;
        try {
            data = JSON.parse(text);
        } catch (e) {
            alert('Server response:\n' + text);
            return null;
        }
        return data;
    })
    .then(data => {
        if (!data) return;
        if (!data.success) {
            alert(data.message || 'Unable to update user.');
            return;
        }
        window.location.reload();
    })
    .catch(() => alert('Unable to update user. Please try again.'));
});

document.querySelectorAll('.delete-user').forEach(button => {
    button.addEventListener('click', function() {
        const userId = this.dataset.id;
        if (!confirm('Delete this user?')) return;
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('user_id', userId);
        fetch('api/user-actions.php', { method: 'POST', body: formData })
            .then(async (response) => {
        const text = await response.text();
        let data = null;
        try {
            data = JSON.parse(text);
        } catch (e) {
            alert('Server response:\n' + text);
            return null;
        }
        return data;
    })
            .then(data => {
                if (!data) return;
                if (!data.success) {
                    alert(data.message || 'Unable to delete user.');
                    return;
                }
                window.location.reload();
            });
    });
});

document.querySelectorAll('.send-reset').forEach(button => {
    button.addEventListener('click', function() {
        const userId = this.dataset.id;
        const formData = new FormData();
        formData.append('action', 'send_reset');
        formData.append('user_id', userId);
        fetch('api/user-actions.php', { method: 'POST', body: formData })
            .then(async (response) => {
        const text = await response.text();
        let data = null;
        try {
            data = JSON.parse(text);
        } catch (e) {
            alert('Server response:\n' + text);
            return null;
        }
        return data;
    })
            .then(data => {
                if (!data) return;
                if (!data.success) {
                    alert(data.message || 'Unable to send reset link.');
                    return;
                }
                if (data.mail_warning) {
                    alert((data.message || 'Reset link sent.') + "\n\nEmail warning: " + data.mail_warning);
                } else {
                    alert(data.message || 'Reset link sent.');
                }
            });
    });
});

document.querySelectorAll('.activate-user').forEach(button => {
    button.addEventListener('click', function() {
        const userId = this.dataset.id;
        if (!confirm('Activate this user?')) return;
        const formData = new FormData();
        formData.append('action', 'activate');
        formData.append('user_id', userId);
        fetch('api/user-actions.php', { method: 'POST', body: formData })
            .then(async (response) => {
        const text = await response.text();
        let data = null;
        try {
            data = JSON.parse(text);
        } catch (e) {
            alert('Server response:\n' + text);
            return null;
        }
        return data;
    })
            .then(data => {
                if (!data) return;
                if (!data.success) {
                    alert(data.message || 'Unable to activate user.');
                    return;
                }
                window.location.reload();
            });
    });
});

document.querySelectorAll('.suspend-user').forEach(button => {
    button.addEventListener('click', function() {
        const userId = this.dataset.id;
        if (!confirm('Suspend this user?')) return;
        const formData = new FormData();
        formData.append('action', 'suspend');
        formData.append('user_id', userId);
        fetch('api/user-actions.php', { method: 'POST', body: formData })
            .then(async (response) => {
        const text = await response.text();
        let data = null;
        try {
            data = JSON.parse(text);
        } catch (e) {
            alert('Server response:\n' + text);
            return null;
        }
        return data;
    })
            .then(data => {
                if (!data) return;
                if (!data.success) {
                    alert(data.message || 'Unable to suspend user.');
                    return;
                }
                window.location.reload();
            });
    });
});


document.querySelectorAll('.lock-user').forEach(button => {
    button.addEventListener('click', function() {
        const userId = this.dataset.id;
        if (!confirm('Lock this user?')) return;
        const formData = new FormData();
        formData.append('action', 'lock');
        formData.append('user_id', userId);
        fetch('api/user-actions.php', { method: 'POST', body: formData })
            .then(async (response) => {
        const text = await response.text();
        let data = null;
        try {
            data = JSON.parse(text);
        } catch (e) {
            alert('Server response:\n' + text);
            return null;
        }
        return data;
    })
            .then(data => {
                if (!data) return;
                if (!data.success) {
                    alert(data.message || 'Unable to lock user.');
                    return;
                }
                window.location.reload();
            });
    });
});

document.querySelectorAll('.unlock-user').forEach(button => {
    button.addEventListener('click', function() {
        const userId = this.dataset.id;
        if (!confirm('Unlock this user?')) return;
        const formData = new FormData();
        formData.append('action', 'unlock');
        formData.append('user_id', userId);
        fetch('api/user-actions.php', { method: 'POST', body: formData })
            .then(async (response) => {
        const text = await response.text();
        let data = null;
        try {
            data = JSON.parse(text);
        } catch (e) {
            alert('Server response:\n' + text);
            return null;
        }
        return data;
    })
            .then(data => {
                if (!data) return;
                if (!data.success) {
                    alert(data.message || 'Unable to unlock user.');
                    return;
                }
                window.location.reload();
            });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
