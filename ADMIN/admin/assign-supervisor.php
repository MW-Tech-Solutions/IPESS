<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'Assign Supervisors';
$pageSubtitle = 'Match admitted students to supervisors by department.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Assign Supervisors</h1>
        <p class="panel-muted">Select filters to assign supervisors to admitted students (Status: Admitted or Current Status: Admission Approved).</p>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Filters</h3>
            <div class="panel-muted">Select faculty, department, and programme to load supervisors and admitted students.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Faculty/College</label>
                <select id="facultySelect" class="form-select">
                    <option value="">Select Faculty</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Department</label>
                <select id="departmentSelect" class="form-select" disabled>
                    <option value="">Select Department</option>
                </select>
            </div>
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <button class="btn btn-primary" id="loadAssignmentsBtn">Load Assignments</button>
                    <span id="statusMessage" class="text-muted">Select filters to begin.</span>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Admitted Students</h3>
            <div class="panel-muted">Assign a supervisor for each student in the selected department.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="studentsTable">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Application No.</th>
                        <th>Department</th>
                        <th>Supervisor</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="6" class="text-center text-muted">Select filters to load students.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
const apiBase = 'api/supervisor-assign.php';
const facultySelect = document.getElementById('facultySelect');
const departmentSelect = document.getElementById('departmentSelect');
const studentsTableBody = document.querySelector('#studentsTable tbody');
const statusMessage = document.getElementById('statusMessage');

function setStatus(message, isError = false) {
    statusMessage.textContent = message || '';
    statusMessage.classList.toggle('text-danger', !!isError);
    statusMessage.classList.toggle('text-muted', !isError);
}

async function loadFaculties() {
    const res = await fetch(`${apiBase}?action=faculties`);
    const data = await res.json();
    facultySelect.innerHTML = '<option value="">Select Faculty</option>';
    if (!data.success) {
        setStatus(data.message || 'Failed to load faculties.', true);
        return;
    }
    data.data.forEach(row => {
        const opt = document.createElement('option');
        opt.value = row.id;
        opt.textContent = row.name;
        facultySelect.appendChild(opt);
    });
}

async function loadDepartments(facultyId) {
    const res = await fetch(`${apiBase}?action=departments&faculty_id=${encodeURIComponent(facultyId)}`);
    const data = await res.json();
    departmentSelect.innerHTML = '<option value="">Select Department</option>';
    departmentSelect.disabled = true;
    if (!data.success) {
        setStatus(data.message || 'Failed to load departments.', true);
        return;
    }
    data.data.forEach(row => {
        const opt = document.createElement('option');
        opt.value = row.id;
        opt.textContent = row.name;
        departmentSelect.appendChild(opt);
    });
    departmentSelect.disabled = false;
}

async function loadSupervisors(deptId) {
    const res = await fetch(`${apiBase}?action=supervisors&department_id=${encodeURIComponent(deptId)}`);
    const data = await res.json();
    return data.success ? data.data : [];
}

async function loadStudents(filters) {
    const params = new URLSearchParams(filters);
    const res = await fetch(`${apiBase}?action=students&${params.toString()}`);
    const data = await res.json();
    return data.success ? data.data : [];
}

function buildSupervisorSelect(supervisors, selectedName) {
    const select = document.createElement('select');
    select.className = 'form-select form-select-sm';
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = 'Select Supervisor';
    select.appendChild(placeholder);
    supervisors.forEach(sup => {
        const opt = document.createElement('option');
        opt.value = sup.id;
        opt.textContent = sup.name || sup.email || `Supervisor #${sup.id}`;
        if (selectedName && opt.textContent.trim().toLowerCase() === String(selectedName).trim().toLowerCase()) {
            opt.selected = true;
        }
        select.appendChild(opt);
    });
    return select;
}

async function renderAssignments() {
    const facultyId = facultySelect.value;
    const deptId = departmentSelect.value;
    if (!facultyId || !deptId) {
        setStatus('Select faculty and department first.', true);
        return;
    }
    setStatus('Loading...');
    const [supervisors, students] = await Promise.all([
        loadSupervisors(deptId),
        loadStudents({ faculty_id: facultyId, department_id: deptId })
    ]);
    studentsTableBody.innerHTML = '';

    if (!students.length) {
        studentsTableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No admitted students found.</td></tr>';
        setStatus('');
        return;
    }

    students.forEach(student => {
        const row = document.createElement('tr');

        const name = [student.first_name, student.surname].filter(Boolean).join(' ') || 'Student';
        const deptName = student.dept_name || student.department || '';
        const programme = [student.degree_name, student.course_title].filter(Boolean).join(' ');
        const supervisorSelect = buildSupervisorSelect(supervisors, student.assigned_supervisor_name);

        row.innerHTML = `
            <td>${name}</td>
            <td>${student.email || ''}</td>
            <td>${student.application_number || ''}</td>
            <td>${deptName}</td>
            <td></td>
            <td class="text-end"></td>
        `;

        row.children[4].appendChild(supervisorSelect);

        const actionBtn = document.createElement('button');
        actionBtn.className = 'btn btn-sm btn-primary';
        actionBtn.textContent = 'Assign';
        actionBtn.addEventListener('click', async () => {
            const supervisorId = supervisorSelect.value;
            if (!supervisorId) {
                setStatus('Select a supervisor first.', true);
                return;
            }
            setStatus('Assigning...');
            const form = new FormData();
            form.append('action', 'assign');
            form.append('student_id', student.student_id || '');
            form.append('application_id', student.application_id || '');
            form.append('application_number', student.application_number || '');
            form.append('student_name', name);
            form.append('student_email', student.email || '');
            form.append('programme', programme);
            form.append('department_id', deptId);
            form.append('supervisor_id', supervisorId);
            const res = await fetch(apiBase, { method: 'POST', body: form });
            const data = await res.json();
            if (!data.success) {
                setStatus(data.message || 'Assignment failed.', true);
                return;
            }
            setStatus('Supervisor assigned.');
        });
        row.children[5].appendChild(actionBtn);

        studentsTableBody.appendChild(row);
    });
    setStatus('');
}

document.getElementById('loadAssignmentsBtn').addEventListener('click', renderAssignments);
facultySelect.addEventListener('change', () => {
    const facultyId = facultySelect.value;
    departmentSelect.innerHTML = '<option value="">Select Department</option>';
    departmentSelect.disabled = true;
    if (facultyId) {
        loadDepartments(facultyId);
    }
});

departmentSelect.addEventListener('change', async () => {
});

document.addEventListener('DOMContentLoaded', loadFaculties);
</script>

<?php require_once 'includes/footer.php'; ?>
