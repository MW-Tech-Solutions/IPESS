<?php
$pageTitle = 'Programme Types';
$pageSubtitle = 'Define programme levels like PGD, MSc, and PhD.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Programme Types</h1>
        <p class="panel-muted">Manage programme levels used to classify courses.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-primary" id="refreshProgrammes">Refresh</button>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Add Programme Type</h3>
            <div class="panel-muted">Examples: PGD, MSc, PhD.</div>
        </div>
    </div>
    <div class="panel-body">
        <form id="programmeForm" class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Programme Name</label>
                <input type="text" class="form-control" name="degree_name" placeholder="MSc" required>
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
            <h3 class="panel-title">Programme Directory</h3>
            <div class="panel-muted">Available programme levels.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="programmesTable">
                <thead>
                    <tr>
                        <th>Programme</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</section>

<script>
const programmeForm = document.getElementById('programmeForm');
const programmesTableBody = document.querySelector('#programmesTable tbody');
const refreshProgrammesBtn = document.getElementById('refreshProgrammes');

function loadProgrammes() {
    fetch('api/manage-entities.php?entity=degree_types&action=list')
        .then(response => response.json())
        .then(data => {
            programmesTableBody.innerHTML = '';
            if (!data.success || !data.data.length) {
                programmesTableBody.innerHTML = '<tr><td colspan="2" class="text-muted text-center">No programme types found.</td></tr>';
                return;
            }
            data.data.forEach(programme => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${programme.degree_name}</td>
                    <td class="text-end"><button class="btn btn-light btn-sm" data-id="${programme.degree_id}">Delete</button></td>
                `;
                row.querySelector('button').addEventListener('click', () => deleteProgramme(programme.degree_id));
                programmesTableBody.appendChild(row);
            });
        });
}

function deleteProgramme(id) {
    if (!confirm('Delete this programme type?')) return;
    const formData = new FormData();
    formData.append('entity', 'degree_types');
    formData.append('action', 'delete');
    formData.append('id', id);
    fetch('api/manage-entities.php', { method: 'POST', body: formData })
        .then(() => loadProgrammes());
}

programmeForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(programmeForm);
    formData.append('entity', 'degree_types');
    formData.append('action', 'create');
    fetch('api/manage-entities.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Unable to add programme type.');
                return;
            }
            programmeForm.reset();
            loadProgrammes();
        });
});

refreshProgrammesBtn.addEventListener('click', loadProgrammes);
loadProgrammes();
</script>

<?php require_once 'includes/footer.php'; ?>
