<?php
$pageTitle = 'Programme Capacity';
$pageSubtitle = 'Set admission limits per course or programme.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Programme Capacity</h1>
        <p class="panel-muted">Define intake limits for each programme course.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-primary" id="refreshCapacities">Refresh</button>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Set Capacity</h3>
            <div class="panel-muted">Assign or update intake limits for a course.</div>
        </div>
    </div>
    <div class="panel-body">
        <form id="capacityForm" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Course</label>
                <select class="form-select" name="course_id" id="capacityCourseSelect" required></select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Capacity</label>
                <input type="number" class="form-control" name="capacity" min="0" value="0" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="is_active">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit">Save</button>
            </div>
        </form>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Capacity Directory</h3>
            <div class="panel-muted">Current capacity definitions across courses.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="capacitiesTable">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Capacity</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</section>

<script>
const capacityForm = document.getElementById('capacityForm');
const capacitiesTableBody = document.querySelector('#capacitiesTable tbody');
const capacityCourseSelect = document.getElementById('capacityCourseSelect');
const refreshCapacitiesBtn = document.getElementById('refreshCapacities');

function loadCourseOptions() {
    fetch('api/manage-entities.php?entity=courses&action=list')
        .then(response => response.json())
        .then(data => {
            capacityCourseSelect.innerHTML = '<option value=\"\">Select Course</option>';
            if (!data.success) return;
            data.data.forEach(course => {
                const option = document.createElement('option');
                option.value = course.course_id;
                option.textContent = course.course_title;
                capacityCourseSelect.appendChild(option);
            });
        });
}

function loadCapacities() {
    fetch('api/manage-entities.php?entity=capacities&action=list')
        .then(response => response.json())
        .then(data => {
            capacitiesTableBody.innerHTML = '';
            if (!data.success || !data.data.length) {
                capacitiesTableBody.innerHTML = '<tr><td colspan=\"4\" class=\"text-muted text-center\">No capacities found.</td></tr>';
                return;
            }
            data.data.forEach(capacity => {
                const status = capacity.is_active == 1 ? 'Active' : 'Inactive';
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${capacity.course_title || 'Unassigned'}</td>
                    <td>${capacity.capacity}</td>
                    <td>${status}</td>
                    <td class=\"text-end\"><button class=\"btn btn-light btn-sm\" data-id=\"${capacity.capacity_id}\">Delete</button></td>
                `;
                row.querySelector('button').addEventListener('click', () => deleteCapacity(capacity.capacity_id));
                capacitiesTableBody.appendChild(row);
            });
        });
}

function deleteCapacity(id) {
    if (!confirm('Delete this capacity record?')) return;
    const formData = new FormData();
    formData.append('entity', 'capacities');
    formData.append('action', 'delete');
    formData.append('id', id);
    fetch('api/manage-entities.php', { method: 'POST', body: formData })
        .then(() => loadCapacities());
}

capacityForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(capacityForm);
    formData.append('entity', 'capacities');
    formData.append('action', 'create');
    fetch('api/manage-entities.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Unable to save capacity.');
                return;
            }
            capacityForm.reset();
            loadCapacities();
        });
});

refreshCapacitiesBtn.addEventListener('click', loadCapacities);
loadCourseOptions();
loadCapacities();
</script>

<?php require_once 'includes/footer.php'; ?>
