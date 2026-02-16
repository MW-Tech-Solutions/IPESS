<?php
$pageTitle = 'Departments';
$pageSubtitle = 'Organize departments and align them to faculties.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Departments</h1>
        <p class="panel-muted">Map departments to faculties for downstream program routing.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-primary" id="refreshDepartments">Refresh</button>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Add Department</h3>
            <div class="panel-muted">Ensure every department has a faculty parent.</div>
        </div>
    </div>
    <div class="panel-body">
        <form id="departmentForm" class="row g-3">
            <div class="col-md-5">
                <label class="form-label">Department Name</label>
                <input type="text" class="form-control" name="dept_name" placeholder="Computer Science" required>
            </div>
            <div class="col-md-5">
                <label class="form-label">Faculty</label>
                <select class="form-select" name="faculty_id" id="facultySelect" required></select>
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
            <h3 class="panel-title">Department Directory</h3>
            <div class="panel-muted">Departments connected to faculties.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="departmentsTable">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Faculty</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</section>

<script>
const departmentForm = document.getElementById('departmentForm');
const departmentsTableBody = document.querySelector('#departmentsTable tbody');
const facultySelect = document.getElementById('facultySelect');
const refreshDepartmentsBtn = document.getElementById('refreshDepartments');

function loadFaculties() {
    fetch('api/manage-entities.php?entity=faculties&action=list')
        .then(response => response.json())
        .then(data => {
            facultySelect.innerHTML = '<option value="">Select Faculty</option>';
            if (!data.success) {
                return;
            }
            data.data.forEach(faculty => {
                const option = document.createElement('option');
                option.value = faculty.faculty_id;
                option.textContent = faculty.faculty_name;
                facultySelect.appendChild(option);
            });
        });
}

function loadDepartments() {
    fetch('api/manage-entities.php?entity=departments&action=list')
        .then(response => response.json())
        .then(data => {
            departmentsTableBody.innerHTML = '';
            if (!data.success || !data.data.length) {
                departmentsTableBody.innerHTML = '<tr><td colspan="3" class="text-muted text-center">No departments found.</td></tr>';
                return;
            }
            data.data.forEach(dept => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${dept.dept_name}</td>
                    <td>${dept.faculty_name || 'Unassigned'}</td>
                    <td class="text-end"><button class="btn btn-light btn-sm" data-id="${dept.dept_id}">Delete</button></td>
                `;
                row.querySelector('button').addEventListener('click', () => deleteDepartment(dept.dept_id));
                departmentsTableBody.appendChild(row);
            });
        });
}

function deleteDepartment(id) {
    if (!confirm('Delete this department?')) return;
    const formData = new FormData();
    formData.append('entity', 'departments');
    formData.append('action', 'delete');
    formData.append('id', id);
    fetch('api/manage-entities.php', { method: 'POST', body: formData })
        .then(() => loadDepartments());
}

departmentForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(departmentForm);
    formData.append('entity', 'departments');
    formData.append('action', 'create');
    fetch('api/manage-entities.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Unable to add department.');
                return;
            }
            departmentForm.reset();
            loadDepartments();
        });
});

refreshDepartmentsBtn.addEventListener('click', loadDepartments);
loadFaculties();
loadDepartments();
</script>

<?php require_once 'includes/footer.php'; ?>
