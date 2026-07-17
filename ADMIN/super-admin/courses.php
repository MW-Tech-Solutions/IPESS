<?php
$pageTitle = 'Courses';
$pageSubtitle = 'Add courses per faculty, department, and programme type.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Courses</h1>
        <p class="panel-muted">Build out the course catalog by faculty, department, and programme type.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-primary" id="refreshCourses">Refresh</button>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Add Program / Course</h3>
            <div class="panel-muted">Tie new courses to the IPESS Center, a specific Program Area, and a Degree Type.</div>
        </div>
    </div>
    <div class="panel-body">
        <form id="courseForm" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Program / Course Title</label>
                <input type="text" class="form-control" name="course_title" placeholder="e.g. MSc Procurement Management" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Center</label>
                <select class="form-select" id="facultySelect" required></select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Program Area</label>
                <select class="form-select" name="dept_id" id="deptSelect" required></select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Degree Type</label>
                <select class="form-select" name="degree_id" id="programmeSelect" required></select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit">Add</button>
            </div>
        </form>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Program & Course Directory</h3>
            <div class="panel-muted">Programs and courses currently available in the system.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label">Center</label>
                <select class="form-select" id="filterFaculty"></select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Program Area</label>
                <select class="form-select" id="filterDepartment"></select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Degree Type</label>
                <select class="form-select" id="filterProgramme"></select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-outline-primary w-100" id="applyCourseFilters">Apply Filters</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="coursesTable">
                <thead>
                    <tr>
                        <th>Course / Program</th>
                        <th>Program Area</th>
                        <th>Degree Type</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</section>

<script>
const courseForm = document.getElementById('courseForm');
const coursesTableBody = document.querySelector('#coursesTable tbody');
const facultySelect = document.getElementById('facultySelect');
const deptSelect = document.getElementById('deptSelect');
const programmeSelect = document.getElementById('programmeSelect');
const refreshCoursesBtn = document.getElementById('refreshCourses');
const filterFaculty = document.getElementById('filterFaculty');
const filterDepartment = document.getElementById('filterDepartment');
const filterProgramme = document.getElementById('filterProgramme');
const applyCourseFiltersBtn = document.getElementById('applyCourseFilters');

function loadFaculties() {
    fetch('api/manage-entities.php?entity=faculties&action=list')
        .then(response => response.json())
        .then(data => {
            facultySelect.innerHTML = '';
            filterFaculty.innerHTML = '';
            if (!data.success || !data.data.length) return;
            data.data.forEach(faculty => {
                const option = document.createElement('option');
                option.value = faculty.faculty_id;
                option.textContent = faculty.faculty_name;
                facultySelect.appendChild(option);
                const opt2 = document.createElement('option');
                opt2.value = faculty.faculty_id;
                opt2.textContent = faculty.faculty_name;
                filterFaculty.appendChild(opt2);
            });
            // Default to first available faculty and load departments
            const defaultId = data.data[0].faculty_id;
            facultySelect.value = defaultId;
            filterFaculty.value = defaultId;
            loadDepartments(defaultId, deptSelect);
            loadDepartments(defaultId, filterDepartment);
            loadCourses({ faculty_id: defaultId });
        });
}

function loadDepartments(facultyId, target = deptSelect) {
    if (!facultyId) return;
    fetch(`api/manage-entities.php?entity=departments&action=list&faculty_id=${facultyId}`)
        .then(response => response.json())
        .then(data => {
            target.innerHTML = '<option value="">Select Program Area</option>';
            if (!data.success) return;
            data.data.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept.dept_id;
                option.textContent = dept.dept_name;
                target.appendChild(option);
            });
        });
}

function loadProgrammes(target = programmeSelect) {
    fetch('api/manage-entities.php?entity=degree_types&action=list')
        .then(response => response.json())
        .then(data => {
            target.innerHTML = '<option value="">Select Degree Type</option>';
            if (!data.success) return;
            data.data.forEach(programme => {
                const option = document.createElement('option');
                option.value = programme.degree_id;
                option.textContent = programme.degree_name;
                target.appendChild(option);
            });
        });
}

function loadCourses(filters = {}) {
    const params = new URLSearchParams({
        entity: 'courses',
        action: 'list',
        faculty_id: filters.faculty_id || filterFaculty.value || '',
        department_id: filters.department_id || '',
        programme_id: filters.programme_id || ''
    });
    fetch(`api/manage-entities.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            coursesTableBody.innerHTML = '';
            if (!data.success || !data.data.length) {
                coursesTableBody.innerHTML = '<tr><td colspan="4" class="text-muted text-center">No courses found.</td></tr>';
                return;
            }
            data.data.forEach(course => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${course.course_title}</td>
                    <td>${course.dept_name || 'Unassigned'}</td>
                    <td>${course.degree_name || 'Unassigned'}</td>
                    <td class="text-end"><button class="btn btn-light btn-sm" data-id="${course.course_id}">Delete</button></td>
                `;
                row.querySelector('button').addEventListener('click', () => deleteCourse(course.course_id));
                coursesTableBody.appendChild(row);
            });
        });
}

function deleteCourse(id) {
    if (!confirm('Delete this course?')) return;
    const formData = new FormData();
    formData.append('entity', 'courses');
    formData.append('action', 'delete');
    formData.append('id', id);
    fetch('api/manage-entities.php', { method: 'POST', body: formData })
        .then(() => loadCourses());
}

courseForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(courseForm);
    formData.append('entity', 'courses');
    formData.append('action', 'create');
    fetch('api/manage-entities.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Unable to add course.');
                return;
            }
            const savedFaculty = facultySelect.value;
            const savedDept = deptSelect.value;
            const savedProg = programmeSelect.value;
            courseForm.reset();
            facultySelect.value = savedFaculty;
            deptSelect.value = savedDept;
            programmeSelect.value = savedProg;
            loadCourses();
        });
});

facultySelect.addEventListener('change', function() {
    loadDepartments(facultySelect.value, deptSelect);
});

filterFaculty.addEventListener('change', function() {
    loadDepartments(filterFaculty.value, filterDepartment);
    loadCourses({ faculty_id: filterFaculty.value });
});

refreshCoursesBtn.addEventListener('click', () => loadCourses());
loadFaculties();
loadProgrammes(programmeSelect);
loadProgrammes(filterProgramme);

applyCourseFiltersBtn.addEventListener('click', () => {
    loadCourses({
        faculty_id: filterFaculty.value,
        department_id: filterDepartment.value,
        programme_id: filterProgramme.value
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
