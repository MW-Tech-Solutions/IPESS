<?php
$pageTitle = 'Faculties';
$pageSubtitle = 'Maintain faculty structure for postgraduate admissions.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Faculties</h1>
        <p class="panel-muted">Add or retire faculties used in application routing.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-primary" id="refreshFaculties">Refresh</button>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Add Faculty</h3>
            <div class="panel-muted">Keep faculty names consistent for analytics.</div>
        </div>
    </div>
    <div class="panel-body">
        <form id="facultyForm" class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Faculty Name</label>
                <input type="text" class="form-control" name="faculty_name" placeholder="Sciences" required>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit">Add</button>
            </div>
        </form>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Faculty Directory</h3>
            <div class="panel-muted">Currently registered faculties.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="facultiesTable">
                <thead>
                    <tr>
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
const facultyForm = document.getElementById('facultyForm');
const facultiesTableBody = document.querySelector('#facultiesTable tbody');
const refreshFacultiesBtn = document.getElementById('refreshFaculties');

function loadFaculties() {
    fetch('api/manage-entities.php?entity=faculties&action=list')
        .then(response => response.json())
        .then(data => {
            facultiesTableBody.innerHTML = '';
            if (!data.success || !data.data.length) {
                facultiesTableBody.innerHTML = '<tr><td colspan="2" class="text-muted text-center">No faculties found.</td></tr>';
                return;
            }
            data.data.forEach(faculty => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${faculty.faculty_name}</td>
                    <td class="text-end"><button class="btn btn-light btn-sm" data-id="${faculty.faculty_id}">Delete</button></td>
                `;
                row.querySelector('button').addEventListener('click', () => deleteFaculty(faculty.faculty_id));
                facultiesTableBody.appendChild(row);
            });
        });
}

function deleteFaculty(id) {
    if (!confirm('Delete this faculty?')) return;
    const formData = new FormData();
    formData.append('entity', 'faculties');
    formData.append('action', 'delete');
    formData.append('id', id);
    fetch('api/manage-entities.php', { method: 'POST', body: formData })
        .then(() => loadFaculties());
}

facultyForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(facultyForm);
    formData.append('entity', 'faculties');
    formData.append('action', 'create');
    fetch('api/manage-entities.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Unable to add faculty.');
                return;
            }
            facultyForm.reset();
            loadFaculties();
        });
});

refreshFacultiesBtn.addEventListener('click', loadFaculties);
loadFaculties();
</script>

<?php require_once 'includes/footer.php'; ?>
