<?php
require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');
require_role(['SUPER_ADMIN', 'ICT_ADMIN'], 'ADMIN/login.php');

$faculties = [];
$departments = [];
$degrees = [];
$courses = [];
$states = [];
$roles = [];

if (isset($pdo)) {
    try {
        $faculties = $pdo->query("SELECT faculty_id, faculty_name FROM faculties ORDER BY faculty_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $departments = $pdo->query("SELECT dept_id, dept_name, faculty_id FROM departments ORDER BY dept_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $degrees = $pdo->query("SELECT degree_id, degree_name FROM degree_types ORDER BY degree_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $courses = $pdo->query("
            SELECT c.course_id, c.course_title, c.dept_id, d.faculty_id 
            FROM courses c
            LEFT JOIN departments d ON c.dept_id = d.dept_id
            ORDER BY c.course_title ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        $states = $pdo->query("SELECT DISTINCT state_origin FROM personal_details WHERE state_origin IS NOT NULL AND state_origin <> '' ORDER BY state_origin ASC")->fetchAll(PDO::FETCH_COLUMN);
        $roles = $pdo->query("SELECT role_id, role_key, role_name FROM roles ORDER BY role_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fallback
    }
}

$pageTitle = 'IPESS Reports';
$pageSubtitle = 'Generate, download, and manage IPESS institutional reports.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>IPESS Reports & Exports</h1>
        <p class="panel-muted">Generate PDF or Excel reports for IPESS and track their history.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" id="refreshReports">Refresh History</button>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Demographic & System Reports</h3>
            <div class="panel-muted">Choose a category, refine with filters, and export.</div>
        </div>
    </div>
    <div class="panel-body">
        <form id="reportForm" class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-bold">Report Category</label>
                <select class="form-select" id="reportCategorySelect" name="report_type" required>
                    <option value="Admissions Summary">Summary: Admissions Overview</option>
                    <option value="Faculty Breakdown">Summary: Faculty Breakdown</option>
                    <option value="Programme Capacity">Summary: Programme Capacity</option>
                    <option value="Student Admissions">Detailed: Student Admissions</option>
                    <option value="Staff Records">Detailed: Staff Records</option>
                </select>
            </div>
            
            <div class="col-md-4">
                <label class="form-label fw-bold">Format</label>
                <select class="form-select" name="format" required>
                    <option value="PDF">PDF (Landscape for lists)</option>
                    <option value="EXCEL">Excel / CSV</option>
                    <option value="DOSSIERS_ZIP">ZIP of Student Dossier PDFs</option>
                </select>
            </div>
            
            <div class="col-md-4">
                <label class="form-label fw-bold">Delivery Mode</label>
                <select class="form-select" name="delivery" required>
                    <option value="view">View in Browser</option>
                    <option value="download">Download File</option>
                </select>
            </div>

            <!-- Student Filters -->
            <div class="row g-3 d-none mt-2 mx-0 px-0 w-100" id="studentFiltersRow">
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-muted small">Faculty / College</label>
                    <select class="form-select" name="college_id" id="studentFacultySelect">
                        <option value="">All Colleges</option>
                        <?php foreach ($faculties as $f): ?>
                            <option value="<?php echo $f['faculty_id']; ?>"><?php echo htmlspecialchars($f['faculty_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-muted small">Department</label>
                    <select class="form-select" name="department_id" id="studentDeptSelect">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo $d['dept_id']; ?>" data-faculty-id="<?php echo $d['faculty_id']; ?>"><?php echo htmlspecialchars($d['dept_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold text-muted small">Academic Programme</label>
                    <select class="form-select" name="course_id" id="studentCourseSelect">
                        <option value="">All Programmes</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo $c['course_id']; ?>" data-dept-id="<?php echo $c['dept_id']; ?>" data-faculty-id="<?php echo $c['faculty_id']; ?>"><?php echo htmlspecialchars($c['course_title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold text-muted small">Degree Type</label>
                    <select class="form-select" name="degree_id">
                        <option value="">All Degree Types</option>
                        <?php foreach ($degrees as $dg): ?>
                            <option value="<?php echo $dg['degree_id']; ?>"><?php echo htmlspecialchars($dg['degree_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold text-muted small">Application Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Statuses</option>
                        <option value="Draft">Draft</option>
                        <option value="Submitted">Submitted</option>
                        <option value="Admitted">Admitted</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold text-muted small">State of Origin</label>
                    <select class="form-select" name="state">
                        <option value="">All States</option>
                        <?php foreach ($states as $st): ?>
                            <option value="<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($st); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold text-muted small">Local Government Area (LGA)</label>
                    <input type="text" class="form-control" name="lga" placeholder="e.g. Makurdi">
                </div>
            </div>

            <!-- Staff Filters -->
            <div class="row g-3 d-none mt-2 mx-0 px-0 w-100" id="staffFiltersRow">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted small">Department</label>
                    <select class="form-select" name="staff_department_id">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['dept_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted small">Administrative Role</label>
                    <select class="form-select" name="role_id">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo $r['role_id']; ?>"><?php echo htmlspecialchars($r['role_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="col-12 d-flex justify-content-end mt-4">
                <button class="btn btn-primary" type="submit"><i class="fas fa-file-invoice me-1"></i> Generate Report</button>
            </div>
        </form>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Individual Applicant Package Generator</h3>
            <div class="panel-muted">Select an applicant and download all slips, letters, and attachments in one packaged ZIP download.</div>
        </div>
    </div>
    <div class="panel-body">
        <form id="packageForm" method="GET" action="api/reports.php" target="_blank" class="row g-3">
            <input type="hidden" name="action" value="download_individual_package">
            
            <div class="col-md-12 position-relative">
                <label class="form-label fw-bold">Search Applicant</label>
                <input type="text" class="form-control" id="applicantSearchInput" placeholder="Type applicant name or application number to search..." autocomplete="off">
                <input type="hidden" name="app_id" id="selectedAppId" required>
                
                <ul class="dropdown-menu w-100 mt-1" id="applicantSuggestionsList" style="display:none; max-height:220px; overflow-y:auto; z-index:9999;">
                    <!-- populated dynamically -->
                </ul>
            </div>
            
            <div class="col-md-12">
                <label class="form-label fw-bold mb-2">Package Contents</label>
                <div class="row g-2">
                    <div class="col-md-3">
                        <div class="form-check p-3 border rounded">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="include_slip" value="1" id="chkSlip" checked>
                            <label class="form-check-label fw-semibold" for="chkSlip">Application Form Slip</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check p-3 border rounded">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="include_admission" value="1" id="chkAdmission" checked>
                            <label class="form-check-label fw-semibold" for="chkAdmission">Admission Letter</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check p-3 border rounded">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="include_acceptance" value="1" id="chkAcceptance" checked>
                            <label class="form-check-label fw-semibold" for="chkAcceptance">Acceptance Letter</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check p-3 border rounded">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="include_docs" value="1" id="chkDocs" checked>
                            <label class="form-check-label fw-semibold" for="chkDocs">Uploaded Document files</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-12 d-flex justify-content-end mt-4">
                <button class="btn btn-success fw-bold" type="submit" id="btnDownloadPackage" disabled>
                    <i class="fas fa-file-pdf me-1"></i> Download Dossier (.PDF)
                </button>
            </div>
        </form>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Report History</h3>
            <div class="panel-muted">Recently generated exports.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="reportsTable">
                <thead>
                    <tr>
                        <th>Report</th>
                        <th>Type</th>
                        <th>Format</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</section>

<script>
const reportForm = document.getElementById('reportForm');
const reportsTableBody = document.querySelector('#reportsTable tbody');
const refreshReportsBtn = document.getElementById('refreshReports');

const reportCategorySelect = document.getElementById('reportCategorySelect');
const studentFiltersRow = document.getElementById('studentFiltersRow');
const staffFiltersRow = document.getElementById('staffFiltersRow');

const studentFacultySelect = document.getElementById('studentFacultySelect');
const studentDeptSelect = document.getElementById('studentDeptSelect');
const studentCourseSelect = document.getElementById('studentCourseSelect');

const searchInput = document.getElementById('applicantSearchInput');
const suggestionsList = document.getElementById('applicantSuggestionsList');
const selectedAppId = document.getElementById('selectedAppId');
const btnDownloadPackage = document.getElementById('btnDownloadPackage');

let searchDebounceTimeout = null;

// Handle dynamic departments selection
function filterStudentDepts() {
    if (!studentFacultySelect || !studentDeptSelect) return;
    const facultyId = studentFacultySelect.value;
    Array.from(studentDeptSelect.options).forEach(opt => {
        if (!opt.value) return; 
        const optFacultyId = opt.dataset.facultyId || '';
        opt.hidden = (facultyId !== '' && optFacultyId !== facultyId);
        opt.style.display = (facultyId !== '' && optFacultyId !== facultyId) ? 'none' : 'block';
    });
}

// Handle dynamic academic programmes selection
function filterStudentCourses() {
    if (!studentFacultySelect || !studentDeptSelect || !studentCourseSelect) return;
    const facultyId = studentFacultySelect.value;
    const deptId = studentDeptSelect.value;
    
    Array.from(studentCourseSelect.options).forEach(opt => {
        if (!opt.value) return;
        const optFacultyId = opt.dataset.facultyId || '';
        const optDeptId = opt.dataset.deptId || '';
        
        let show = true;
        if (facultyId !== '' && optFacultyId !== facultyId) {
            show = false;
        }
        if (deptId !== '' && optDeptId !== deptId) {
            show = false;
        }
        
        opt.hidden = !show;
        opt.style.display = show ? 'block' : 'none';
    });
}

studentFacultySelect.addEventListener('change', () => {
    studentDeptSelect.value = '';
    studentCourseSelect.value = '';
    filterStudentDepts();
    filterStudentCourses();
});

studentDeptSelect.addEventListener('change', () => {
    studentCourseSelect.value = '';
    filterStudentCourses();
});

// Toggle Filter Rows
reportCategorySelect.addEventListener('change', () => {
    const val = reportCategorySelect.value;
    if (val === 'Student Admissions') {
        studentFiltersRow.classList.remove('d-none');
        staffFiltersRow.classList.add('d-none');
    } else if (val === 'Staff Records') {
        studentFiltersRow.classList.add('d-none');
        staffFiltersRow.classList.remove('d-none');
    } else {
        studentFiltersRow.classList.add('d-none');
        staffFiltersRow.classList.add('d-none');
    }
});

// Autocomplete logic
searchInput.addEventListener('input', () => {
    clearTimeout(searchDebounceTimeout);
    const query = searchInput.value.trim();
    
    selectedAppId.value = '';
    btnDownloadPackage.disabled = true;
    
    if (query.length < 2) {
        suggestionsList.style.display = 'none';
        return;
    }
    
    searchDebounceTimeout = setTimeout(() => {
        fetch(`api/reports.php?action=search_students&query=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(resData => {
                suggestionsList.innerHTML = '';
                if (!resData.success || !resData.data || resData.data.length === 0) {
                    const li = document.createElement('li');
                    li.className = 'dropdown-item text-muted';
                    li.textContent = 'No applicants found';
                    suggestionsList.appendChild(li);
                } else {
                    resData.data.forEach(item => {
                        const li = document.createElement('li');
                        li.className = 'dropdown-item';
                        li.style.cursor = 'pointer';
                        li.innerHTML = `<strong>${item.full_name}</strong> <span class="badge bg-light text-dark font-monospace float-end">${item.application_number}</span>`;
                        li.addEventListener('click', () => {
                            searchInput.value = `${item.full_name} (${item.application_number})`;
                            selectedAppId.value = item.application_id;
                            btnDownloadPackage.disabled = false;
                            suggestionsList.style.display = 'none';
                        });
                        suggestionsList.appendChild(li);
                    });
                }
                suggestionsList.style.display = 'block';
            });
    }, 250);
});

document.addEventListener('click', (e) => {
    if (e.target !== searchInput && e.target !== suggestionsList) {
        suggestionsList.style.display = 'none';
    }
});

function loadReports() {
    fetch('api/reports.php?action=list')
        .then(response => response.json())
        .then(data => {
            reportsTableBody.innerHTML = '';
            if (!data.success || !data.data.length) {
                reportsTableBody.innerHTML = '<tr><td colspan="5" class="text-muted text-center">No reports generated yet.</td></tr>';
                return;
            }
            data.data.forEach(report => {
                const created = report.created_at ? new Date(report.created_at).toLocaleString() : '';
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${report.report_name}</td>
                    <td>${report.report_type}</td>
                    <td>${report.format}</td>
                    <td>${created}</td>
                    <td class="text-end">
                        <a class="btn btn-outline-primary btn-sm" href="${report.view_url}" target="_blank">View</a>
                        <a class="btn btn-light btn-sm" href="${report.download_url}">Download</a>
                        <button class="btn btn-light btn-sm" data-id="${report.report_id}">Delete</button>
                    </td>
                `;
                row.querySelector('button').addEventListener('click', () => deleteReport(report.report_id));
                reportsTableBody.appendChild(row);
            });
        });
}

function deleteReport(id) {
    if (!confirm('Delete this report?')) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    fetch('api/reports.php', { method: 'POST', body: formData })
        .then(() => loadReports());
}

reportForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(reportForm);
    formData.append('action', 'generate');
    fetch('api/reports.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Report generation failed.');
                return;
            }
            loadReports();
            const delivery = (formData.get('delivery') || 'view').toLowerCase();
            if (delivery === 'download' && data.download_url) {
                window.location.href = data.download_url;
            } else if (data.view_url) {
                window.open(data.view_url, '_blank');
            }
        });
});

refreshReportsBtn.addEventListener('click', loadReports);
loadReports();
</script>

<?php require_once 'includes/footer.php'; ?>
