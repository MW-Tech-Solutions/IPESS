<?php
$pageTitle = 'Manage Students';
$pageSubtitle = 'Search and manage student records, biodata, and academic application details.';

require_once 'includes/db.php';

$q = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$allowedStatus = ['Draft', 'Submitted', 'Admitted', 'Rejected'];
if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = '';
}

$stats = [
    'total' => 0,
    'admitted' => 0,
    'submitted' => 0,
    'rejected' => 0,
];
$students = [];

$faculties = [];
$departments = [];
$degrees = [];
$courses = [];
$studyModes = [];

if ($pdo) {
    $statsSql = "
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN a.status = 'Admitted' THEN 1 ELSE 0 END) AS admitted,
            SUM(CASE WHEN a.status = 'Submitted' THEN 1 ELSE 0 END) AS submitted,
            SUM(CASE WHEN a.status = 'Rejected' THEN 1 ELSE 0 END) AS rejected
        FROM applications a
        WHERE NOT EXISTS (
            SELECT 1
            FROM applications nx
            WHERE nx.user_id = a.user_id
              AND nx.application_id > a.application_id
        )
    ";
    $statsStmt = $pdo->query($statsSql);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: $stats;

    $where = [
        "NOT EXISTS (
            SELECT 1
            FROM applications nx
            WHERE nx.user_id = a.user_id
              AND nx.application_id > a.application_id
        )"
    ];
    $params = [];

    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = "(u.email LIKE ?
            OR COALESCE(u.full_name, '') LIKE ?
            OR COALESCE(a.application_number, '') LIKE ?
            OR CAST(a.application_id AS CHAR) LIKE ?
            OR COALESCE(pd.first_name, '') LIKE ?
            OR COALESCE(pd.surname, '') LIKE ?
            OR COALESCE(pd.phone, '') LIKE ?)";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($statusFilter !== '') {
        $where[] = "a.status = ?";
        $params[] = $statusFilter;
    }

    $studentsSql = "
        SELECT
            u.user_id,
            u.email,
            u.full_name,
            u.account_status,
            a.application_id,
            a.application_number,
            a.status AS application_status,
            a.current_status,
            a.department_id AS application_department_id,
            pd.surname,
            pd.first_name,
            pd.other_name,
            pd.phone,
            pd.address,
            pd.sex,
            pd.dob,
            pd.nationality,
            pd.state_origin,
            pd.lga,
            pc.faculty AS pc_faculty_id,
            pc.department AS pc_department_id,
            pc.degree_type AS pc_degree_id,
            pc.mode_of_study AS pc_mode_id,
            pc.course AS pc_course_id,
            d.dept_name,
            f.faculty_name,
            c.course_title,
            dt.degree_name
        FROM applications a
        INNER JOIN users u ON u.user_id = a.user_id
        LEFT JOIN personal_details pd ON pd.application_id = a.application_id
        LEFT JOIN programme_choices pc ON pc.application_id = a.application_id
        LEFT JOIN departments d ON d.dept_id = COALESCE(pc.department, a.department_id)
        LEFT JOIN faculties f ON f.faculty_id = COALESCE(pc.faculty, d.faculty_id)
        LEFT JOIN courses c ON c.course_id = pc.course
        LEFT JOIN degree_types dt ON dt.degree_id = pc.degree_type
        WHERE " . implode(' AND ', $where) . "
        GROUP BY a.application_id
        ORDER BY a.updated_at DESC, a.application_id DESC
    ";

    $studentsStmt = $pdo->prepare($studentsSql);
    $studentsStmt->execute($params);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

    $faculties = $pdo->query("SELECT faculty_id, faculty_name FROM faculties ORDER BY faculty_name")->fetchAll(PDO::FETCH_ASSOC);
    $departments = $pdo->query("SELECT dept_id, dept_name, faculty_id FROM departments ORDER BY dept_name")->fetchAll(PDO::FETCH_ASSOC);
    $degrees = $pdo->query("SELECT degree_id, degree_name FROM degree_types ORDER BY degree_name")->fetchAll(PDO::FETCH_ASSOC);
    $studyModes = $pdo->query("SELECT mode_id, mode_name FROM study_modes ORDER BY mode_name")->fetchAll(PDO::FETCH_ASSOC);
    $courses = $pdo->query("SELECT course_id, course_title, dept_id, degree_id FROM courses ORDER BY course_title")->fetchAll(PDO::FETCH_ASSOC);
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Manage Students</h1>
        <p class="panel-muted">Enterprise student record control for biodata and academic application details.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-outline-primary" onclick="window.location.href='manage-students.php'">Reset Filters</button>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
        <div>
            <div class="stat-title">Total Students</div>
            <div class="stat-value"><?php echo number_format((int) $stats['total']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        <div>
            <div class="stat-title">Admitted</div>
            <div class="stat-value"><?php echo number_format((int) $stats['admitted']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
        <div>
            <div class="stat-title">Submitted</div>
            <div class="stat-value"><?php echo number_format((int) $stats['submitted']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-times"></i></div>
        <div>
            <div class="stat-title">Rejected</div>
            <div class="stat-value"><?php echo number_format((int) $stats['rejected']); ?></div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Student Directory</h3>
            <div class="panel-muted">Search by student name, email, application number, or reference (application ID).</div>
        </div>
    </div>
    <div class="panel-body">
        <form method="get" class="row g-2 mb-3">
            <div class="col-md-8">
                <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search student name, email, application number, or reference ID">
            </div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <?php foreach ($allowedStatus as $statusOption): ?>
                        <option value="<?php echo htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $statusFilter === $statusOption ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-primary" type="submit">Search</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Application No</th>
                        <!-- <th>Reference</th> -->
                        <th>Phone</th>
                        <th>Programme</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($students)): ?>
                        <?php foreach ($students as $student): ?>
                            <?php
                            $studentName = trim((string) ($student['full_name'] ?? ''));
                            if ($studentName === '') {
                                $studentName = trim((string) (($student['surname'] ?? '') . ' ' . ($student['first_name'] ?? '') . ' ' . ($student['other_name'] ?? '')));
                            }
                            $statusClass = 'status-muted';
                            if (($student['application_status'] ?? '') === 'Admitted') {
                                $statusClass = 'status-success';
                            } elseif (($student['application_status'] ?? '') === 'Rejected') {
                                $statusClass = 'status-danger';
                            } elseif (($student['application_status'] ?? '') === 'Submitted') {
                                $statusClass = 'status-warning';
                            }
                            $programmeLabel = trim((string) (($student['degree_name'] ?? '') . ' ' . ($student['course_title'] ?? '')));
                            if ($programmeLabel === '') {
                                $programmeLabel = (string) ($student['dept_name'] ?? 'N/A');
                            }
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($studentName !== '' ? $studentName : 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars((string) ($student['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars((string) ($student['application_number'] ?: 'N/A'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <!-- <td>#<?php echo (int) ($student['application_id'] ?? 0); ?></td> -->
                                <td><?php echo htmlspecialchars((string) ($student['phone'] ?: 'N/A'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($programmeLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><span class="status-chip <?php echo $statusClass; ?>"><?php echo htmlspecialchars((string) ($student['application_status'] ?: 'Unknown'), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-light btn-sm manage-student-btn" data-student-user-id="<?php echo (int) $student['user_id']; ?>">
                                        Manage
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted">No students found for the selected filters.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="manageStudentModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manage Student Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="fw-semibold" id="manageStudentIdentity">Loading...</div>
                    <div class="small text-muted" id="manageStudentMeta"></div>
                </div>

                <ul class="nav nav-tabs mb-3" id="studentManageTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#student-biodata-tab" type="button">Biodata</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#student-academics-tab" type="button">Academics</button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="student-biodata-tab">
                        <form id="studentBiodataForm" class="row g-3">
                            <input type="hidden" name="action" value="save_biodata">
                            <input type="hidden" name="student_user_id" value="">
                            <input type="hidden" name="application_id" value="">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email (Username)</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Surname</label>
                                <input type="text" class="form-control" name="surname">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Other Name</label>
                                <input type="text" class="form-control" name="other_name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" name="phone">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="sex">
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="dob">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nationality</label>
                                <input type="text" class="form-control" name="nationality">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">State of Origin</label>
                                <input type="text" class="form-control" name="state_origin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">LGA</label>
                                <input type="text" class="form-control" name="lga">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" rows="2" name="address"></textarea>
                            </div>
                            <div class="col-12 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">Save Biodata</button>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="student-academics-tab">
                        <form id="studentAcademicsForm" class="row g-3">
                            <input type="hidden" name="action" value="save_academics">
                            <input type="hidden" name="student_user_id" value="">
                            <input type="hidden" name="application_id" value="">
                            <div class="col-md-4">
                                <label class="form-label">Faculty/College</label>
                                <select class="form-select" name="faculty_id" id="studentFacultySelect">
                                    <option value="">Select Faculty</option>
                                    <?php foreach ($faculties as $faculty): ?>
                                        <option value="<?php echo (int) $faculty['faculty_id']; ?>"><?php echo htmlspecialchars((string) $faculty['faculty_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Department</label>
                                <select class="form-select" name="department_id" id="studentDepartmentSelect">
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?php echo (int) $department['dept_id']; ?>" data-faculty-id="<?php echo (int) $department['faculty_id']; ?>"><?php echo htmlspecialchars((string) $department['dept_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Programme (Degree Type)</label>
                                <select class="form-select" name="degree_id" id="studentDegreeSelect">
                                    <option value="">Select Programme</option>
                                    <?php foreach ($degrees as $degree): ?>
                                        <option value="<?php echo (int) $degree['degree_id']; ?>"><?php echo htmlspecialchars((string) $degree['degree_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Course Applied</label>
                                <select class="form-select" name="course_id" id="studentCourseSelect">
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo (int) $course['course_id']; ?>" data-dept-id="<?php echo (int) $course['dept_id']; ?>" data-degree-id="<?php echo (int) $course['degree_id']; ?>">
                                            <?php echo htmlspecialchars((string) $course['course_title'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mode of Study</label>
                                <select class="form-select" name="mode_id">
                                    <option value="">Select Mode</option>
                                    <?php foreach ($studyModes as $mode): ?>
                                        <option value="<?php echo (int) $mode['mode_id']; ?>"><?php echo htmlspecialchars((string) $mode['mode_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Highest Qualification</label>
                                <input type="text" class="form-control" name="highest_qualification">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Course of Study</label>
                                <input type="text" class="form-control" name="course_study">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Institution</label>
                                <input type="text" class="form-control" name="institution">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Graduation Year</label>
                                <input type="number" class="form-control" name="grad_year" min="1950" max="2100">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">CGPA</label>
                                <input type="number" class="form-control" name="cgpa" step="0.01" min="0" max="5">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Research Area</label>
                                <input type="text" class="form-control" name="research_area">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Reason For Choosing</label>
                                <textarea class="form-control" rows="2" name="reason_for_choosing"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Statement of Purpose</label>
                                <textarea class="form-control" rows="2" name="statement_of_purpose"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Career Objectives</label>
                                <textarea class="form-control" rows="2" name="career_objectives"></textarea>
                            </div>
                            <div class="col-12 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">Save Academics</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const manageStudentModalEl = document.getElementById('manageStudentModal');
let manageStudentModal = null;
function getManageStudentModal() {
    if (!manageStudentModalEl || typeof bootstrap === 'undefined') return null;
    manageStudentModal = bootstrap.Modal.getInstance(manageStudentModalEl) || new bootstrap.Modal(manageStudentModalEl);
    return manageStudentModal;
}
const manageStudentIdentity = document.getElementById('manageStudentIdentity');
const manageStudentMeta = document.getElementById('manageStudentMeta');
const biodataForm = document.getElementById('studentBiodataForm');
const academicsForm = document.getElementById('studentAcademicsForm');

const departmentSelect = document.getElementById('studentDepartmentSelect');
const facultySelect = document.getElementById('studentFacultySelect');
const degreeSelect = document.getElementById('studentDegreeSelect');
const courseSelect = document.getElementById('studentCourseSelect');

function filterDepartmentsByFaculty() {
    if (!departmentSelect || !facultySelect) return;
    const facultyId = facultySelect.value;
    Array.from(departmentSelect.options).forEach((option) => {
        if (!option.value) {
            option.hidden = false;
            return;
        }
        const optionFacultyId = option.dataset.facultyId || '';
        option.hidden = facultyId !== '' && optionFacultyId !== facultyId;
    });
}

function filterCourses() {
    if (!courseSelect || !departmentSelect || !degreeSelect) return;
    const departmentId = departmentSelect.value;
    const degreeId = degreeSelect.value;
    Array.from(courseSelect.options).forEach((option) => {
        if (!option.value) {
            option.hidden = false;
            return;
        }
        const optDept = option.dataset.deptId || '';
        const optDegree = option.dataset.degreeId || '';
        const deptMismatch = departmentId !== '' && optDept !== departmentId;
        const degreeMismatch = degreeId !== '' && optDegree !== degreeId;
        option.hidden = deptMismatch || degreeMismatch;
    });
}

if (facultySelect) facultySelect.addEventListener('change', () => { filterDepartmentsByFaculty(); filterCourses(); });
if (departmentSelect) departmentSelect.addEventListener('change', filterCourses);
if (degreeSelect) degreeSelect.addEventListener('change', filterCourses);

function fillFormValue(form, name, value) {
    const field = form.querySelector(`[name="${name}"]`);
    if (field) field.value = value ?? '';
}

function setHiddenIds(studentUserId, applicationId) {
    fillFormValue(biodataForm, 'student_user_id', studentUserId);
    fillFormValue(biodataForm, 'application_id', applicationId);
    fillFormValue(academicsForm, 'student_user_id', studentUserId);
    fillFormValue(academicsForm, 'application_id', applicationId);
}

async function loadStudentProfile(studentUserId) {
    const url = `api/manage-students.php?action=fetch&student_user_id=${encodeURIComponent(studentUserId)}`;
    const response = await fetch(url, { credentials: 'same-origin' });
    const text = await response.text();
    let data = null;
    try {
        data = JSON.parse(text);
    } catch (e) {
        throw new Error(text || 'Invalid server response');
    }
    if (!data.success) {
        throw new Error(data.message || 'Unable to load student record');
    }

    const s = data.student || {};
    const b = data.biodata || {};
    const a = data.academics || {};
    const h = data.higher_education || {};
    const r = data.research || {};

    setHiddenIds(s.student_user_id || '', s.application_id || '');
    manageStudentIdentity.textContent = (s.full_name || `${b.surname || ''} ${b.first_name || ''}` || 'Student').trim();
    manageStudentMeta.textContent = `${s.email || ''} | Application: ${s.application_number || 'N/A'} | Ref: #${s.application_id || 0}`;

    fillFormValue(biodataForm, 'full_name', s.full_name);
    fillFormValue(biodataForm, 'email', s.email);
    fillFormValue(biodataForm, 'surname', b.surname);
    fillFormValue(biodataForm, 'first_name', b.first_name);
    fillFormValue(biodataForm, 'other_name', b.other_name);
    fillFormValue(biodataForm, 'phone', b.phone);
    fillFormValue(biodataForm, 'sex', b.sex);
    fillFormValue(biodataForm, 'dob', b.dob);
    fillFormValue(biodataForm, 'nationality', b.nationality);
    fillFormValue(biodataForm, 'state_origin', b.state_origin);
    fillFormValue(biodataForm, 'lga', b.lga);
    fillFormValue(biodataForm, 'address', b.address);

    fillFormValue(academicsForm, 'faculty_id', a.faculty_id);
    fillFormValue(academicsForm, 'department_id', a.department_id);
    fillFormValue(academicsForm, 'degree_id', a.degree_id);
    fillFormValue(academicsForm, 'mode_id', a.mode_id);
    fillFormValue(academicsForm, 'course_id', a.course_id);
    fillFormValue(academicsForm, 'highest_qualification', h.highest_qualification);
    fillFormValue(academicsForm, 'course_study', h.course_study);
    fillFormValue(academicsForm, 'institution', h.institution);
    fillFormValue(academicsForm, 'grad_year', h.grad_year);
    fillFormValue(academicsForm, 'cgpa', h.cgpa);
    fillFormValue(academicsForm, 'research_area', r.research_area);
    fillFormValue(academicsForm, 'reason_for_choosing', r.reason_for_choosing);
    fillFormValue(academicsForm, 'statement_of_purpose', r.statement_of_purpose);
    fillFormValue(academicsForm, 'career_objectives', r.career_objectives);

    filterDepartmentsByFaculty();
    filterCourses();
}

document.querySelectorAll('.manage-student-btn').forEach((btn) => {
    btn.addEventListener('click', async () => {
        const studentUserId = btn.dataset.studentUserId;
        if (!studentUserId) return;
        try {
            await loadStudentProfile(studentUserId);
            const modal = getManageStudentModal();
            if (!modal) {
                throw new Error('Modal engine unavailable. Refresh the page and try again.');
            }
            modal.show();
        } catch (error) {
            alert(error.message || 'Unable to load student.');
        }
    });
});

async function submitStudentForm(form) {
    const payload = new FormData(form);
    const response = await fetch('api/manage-students.php', {
        method: 'POST',
        body: payload,
        credentials: 'same-origin'
    });
    const text = await response.text();
    let data = null;
    try {
        data = JSON.parse(text);
    } catch (e) {
        throw new Error(text || 'Invalid server response');
    }
    if (!data.success) {
        throw new Error(data.message || 'Save failed');
    }
    return data;
}

if (biodataForm) {
    biodataForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            const data = await submitStudentForm(biodataForm);
            alert(data.message || 'Biodata saved successfully.');
        } catch (error) {
            alert(error.message || 'Unable to save biodata.');
        }
    });
}

if (academicsForm) {
    academicsForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            const data = await submitStudentForm(academicsForm);
            alert(data.message || 'Academic record saved successfully.');
            const userId = academicsForm.querySelector('[name="student_user_id"]').value;
            if (userId) {
                await loadStudentProfile(userId);
            }
        } catch (error) {
            alert(error.message || 'Unable to save academics.');
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
