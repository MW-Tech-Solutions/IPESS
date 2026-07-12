<?php
session_start();
$pageTitle = 'Academic Review';
$pageSubtitle = 'Filter applicants by faculty, department, programme, and course.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Academic Review</h1>
        <p class="panel-muted">Review applicants by academic placement and drill into individual records.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-outline-primary" onclick="refreshReview()">Refresh</button>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Filter Applicants</h3>
            <div class="panel-muted">Select faculty, department, programme, and course.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Faculty</label>
                <select class="form-select" id="filterFaculty"></select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Department</label>
                <select class="form-select" id="filterDepartment" disabled></select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Programme</label>
                <select class="form-select" id="filterProgramme" disabled></select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Course</label>
                <select class="form-select" id="filterCourse" disabled></select>
            </div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Applicants</h3>
            <div class="panel-muted" id="resultsMeta">No results yet.</div>
            <div class="panel-muted" id="docRateMeta">Doc-Verified Rate (My Approvals): 0%</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <select class="form-select form-select-sm" id="bulkAction" style="max-width: 220px;">
                <option value="">Bulk Action</option>
                <option value="mark_reviewed">Mark Reviewed</option>
                <option value="request_docs">Request Documents</option>
                <option value="accept_application">Accept Application</option>
                <option value="reject_application">Reject Application</option>
            </select>
            <button class="btn btn-sm btn-primary" onclick="applyBulk()">Apply</button>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input class="form-check-input" type="checkbox" id="selectAll">
                        </th>
                        <th>Applicant</th>
                        <th>Application No.</th>
                        <th>Programme</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="reviewTable"></tbody>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="studentModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header align-items-start">
                <div>
                    <h5 class="modal-title mb-1">Academic Review — Applicant Profile</h5>
                    <div class="text-muted small">Application No: <strong id="detailRef">-</strong></div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary" id="detailStatus">Pending</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body">
                                <div class="text-uppercase text-muted small">Applicant</div>
                                <div class="h5 mb-1" id="detailName">-</div>
                                <div class="text-muted small mb-3" id="detailEmail">-</div>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge bg-light text-dark border" id="detailProgramme">-</span>
                                    <span class="badge bg-light text-dark border" id="detailCourse">-</span>
                                </div>
                                <div class="mt-3 small text-muted" id="detailFacultyDept">-</div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body">
                                <div class="text-uppercase text-muted small mb-2">Progress</div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted small">Completion</span>
                                    <span class="fw-bold" id="detailCompletion">-</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted small">Documents</span>
                                    <span class="fw-bold" id="detailDocs">-</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted small">Referees</span>
                                    <span class="fw-bold" id="detailReferee">-</span>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body">
                                <div class="text-uppercase text-muted small mb-2">Research Topic</div>
                                <div class="fw-semibold" id="detailTopic">-</div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body">
                                <div class="text-uppercase text-muted small mb-2">CV / Resume</div>
                                <div id="detailCv">-</div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="text-uppercase text-muted small mb-2">Referees</div>
                                <div id="detailReferees">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body">
                                <div class="text-uppercase text-muted small mb-3">O'Level Results</div>
                                <div id="detailOlevel">-</div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body">
                                <div class="text-uppercase text-muted small mb-3">Higher Education</div>
                                <div id="detailDegrees">-</div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body">
                                <div class="text-uppercase text-muted small mb-3">Documents</div>
                                <div id="detailDocuments">-</div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="text-uppercase text-muted small mb-2">Document Preview</div>
                                <div class="ratio ratio-16x9 bg-light rounded border">
                                    <iframe id="detailDocFrame" src="about:blank" class="w-100 h-100 border-0"></iframe>
                                </div>
                                <div class="small text-muted mt-2" id="detailDocHint">Select a document to preview.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-end gap-2" data-app-id="">
                <button type="button" class="btn btn-outline-danger" onclick="modalReject()">
                    <i class="fas fa-times me-1"></i> Reject
                </button>
                <button type="button" class="btn btn-success" onclick="modalAccept()">
                    <i class="fas fa-check me-1"></i> Accept
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const filters = {
    faculty: document.getElementById('filterFaculty'),
    department: document.getElementById('filterDepartment'),
    programme: document.getElementById('filterProgramme'),
    course: document.getElementById('filterCourse')
};

const reviewTable = document.getElementById('reviewTable');
const resultsMeta = document.getElementById('resultsMeta');
const selectAll = document.getElementById('selectAll');

document.addEventListener('DOMContentLoaded', () => {
    loadFaculties();
    loadStats();
    if (selectAll) {
        selectAll.addEventListener('change', () => {
            document.querySelectorAll('.row-check').forEach(el => {
                el.checked = selectAll.checked;
            });
        });
    }
});

function setOptions(selectEl, items, placeholder) {
    selectEl.innerHTML = '';
    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = placeholder;
    selectEl.appendChild(defaultOption);
    items.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = item.name;
        selectEl.appendChild(opt);
    });
}

async function loadFaculties() {
    const data = await fetchJson('api/academic-review.php?action=faculties');
    setOptions(filters.faculty, data, 'Select Faculty');
    filters.faculty.disabled = false;
    filters.faculty.addEventListener('change', onFacultyChange);
}

async function onFacultyChange() {
    filters.department.disabled = true;
    filters.programme.disabled = true;
    filters.course.disabled = true;
    clearTable();

    if (!filters.faculty.value) return;
    const data = await fetchJson(`api/academic-review.php?action=departments&faculty_id=${filters.faculty.value}`);
    setOptions(filters.department, data, 'Select Department');
    filters.department.disabled = false;
    filters.department.addEventListener('change', onDepartmentChange);
    loadStats();
}

async function onDepartmentChange() {
    filters.programme.disabled = true;
    filters.course.disabled = true;
    clearTable();

    if (!filters.department.value) return;
    const data = await fetchJson(`api/academic-review.php?action=programmes&faculty_id=${filters.faculty.value}&department_id=${filters.department.value}`);
    setOptions(filters.programme, data, 'Select Programme');
    filters.programme.disabled = false;
    filters.programme.addEventListener('change', onProgrammeChange);
    loadStats();
}

async function onProgrammeChange() {
    filters.course.disabled = true;
    clearTable();

    if (!filters.programme.value) return;
    const data = await fetchJson(`api/academic-review.php?action=courses&faculty_id=${filters.faculty.value}&department_id=${filters.department.value}&programme_id=${filters.programme.value}`);
    setOptions(filters.course, data, 'Select Course');
    filters.course.disabled = false;
    filters.course.addEventListener('change', loadStudents);
    loadStats();
}

async function loadStudents() {
    clearTable();
    if (!filters.course.value) return;
    const url = `api/academic-review.php?action=students&faculty_id=${filters.faculty.value}&department_id=${filters.department.value}&programme_id=${filters.programme.value}&course_id=${filters.course.value}`;
    const data = await fetchJson(url);
    renderStudents(data);
}

async function loadStats() {
    const url = `api/academic-review.php?action=stats&faculty_id=${filters.faculty.value || ''}&department_id=${filters.department.value || ''}&programme_id=${filters.programme.value || ''}&course_id=${filters.course.value || ''}`;
    const data = await fetchJsonRaw(url);
    if (!data || !data.success || !data.data) return;
    const stats = data.data;
    const meta = document.getElementById('docRateMeta');
    if (meta) {
        meta.textContent = `Doc-Verified Rate (My Approvals): ${stats.rate}% (${stats.verified_applicants}/${stats.total_applicants})`;
    }
}

function renderStudents(list) {
    if (!Array.isArray(list) || list.length === 0) {
        reviewTable.innerHTML = '<tr><td colspan="7" class="text-muted">No applicants found.</td></tr>';
        resultsMeta.textContent = 'No results found.';
        return;
    }
    selectAll.checked = false;
    resultsMeta.textContent = `${list.length} applicant(s) found.`;
    reviewTable.innerHTML = list.map(item => `
        <tr>
            <td><input class="form-check-input row-check" type="checkbox" value="${item.application_id}"></td>
            <td>${item.full_name}</td>
            <td>${item.application_number}</td>
            <td>${item.programme}</td>
            <td>${item.course}</td>
            <td><span class="status-chip ${statusChipClass(item)}">${getStatusLabel(item)}</span></td>
            <td class="text-end">
                ${canAccept(item) ? `<button class="btn btn-sm btn-success me-2" onclick="acceptApplication(${item.application_id})"><i class="fas fa-check"></i></button>` : ''}
                <button class="btn btn-sm btn-outline-primary" onclick="viewStudent(${item.application_id})"><i class="fas fa-eye"></i></button>
            </td>
        </tr>
    `).join('');
}

function getStatusLabel(item) {
    const current = (item.current_status || '').toUpperCase();
    const statusLabels = {
        'DRAFT': 'Draft',
        'SUBMITTED': 'Submitted',
        'ASSIGNED_TO_DEPARTMENT': 'Assigned to Department',
        'UNDER_DEPT_REVIEW': 'Under Dept Review',
        'ACTION_REQUIRED_DOCS': 'Action Required (Docs)',
        'DEPT_APPROVED': 'Department Approved',
        'DEPT_REJECTED': 'Department Rejected',
        'REVIEWER_ASSIGNED': 'Reviewer Assigned',
        'UNDER_REVIEWER_REVIEW': 'Under Reviewer Review',
        'REVIEWER_APPROVED': 'Reviewer Approved',
        'REVIEWER_REJECTED': 'Reviewer Rejected',
        'ADMIN_FINAL_REVIEW': 'Admin Final Review',
        'ADMISSION_APPROVED': 'Admission Approved',
        'ADMISSION_REJECTED': 'Admission Rejected'
    };
    return statusLabels[current] || item.status || 'Draft';
}

function statusChipClass(item) {
    const label = getStatusLabel(item).toLowerCase();
    if (label.includes('approved') || label.includes('admitted')) return 'status-success';
    if (label.includes('pending') || label.includes('review') || label.includes('submitted')) return 'status-warning';
    if (label.includes('rejected')) return 'status-danger';
    return 'status-muted';
}

function canAccept(item) {
    const current = (item.current_status || '').toUpperCase();
    return ['SUBMITTED', 'UNDER_DEPT_REVIEW', 'ACTION_REQUIRED_DOCS'].includes(current);
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = value || '-';
    }
}

function setHtml(id, html) {
    const el = document.getElementById(id);
    if (el) {
        el.innerHTML = html;
    }
}

function setPreview(url, name) {
    const frame = document.getElementById('detailDocFrame');
    const hint = document.getElementById('detailDocHint');
    if (frame) frame.src = url || 'about:blank';
    if (hint) hint.textContent = url ? (name || 'Document preview') : 'Select a document to preview.';
}

function renderOlevel(olevel = []) {
    if (!Array.isArray(olevel) || olevel.length === 0) {
        return '<div class="text-muted">No O\'Level records.</div>';
    }
    return olevel.map(exam => {
        const meta = `${exam.exam_type || ''} ${exam.exam_year || ''} • Sitting ${exam.sitting_number || ''}`.trim();
        const results = Array.isArray(exam.results) && exam.results.length
            ? `<div class="row g-2 mt-2">${
                exam.results.map(r => `<div class="col-6 col-md-4"><span class="badge bg-light text-dark border">${r.subject_name || ''}: ${r.grade || ''}</span></div>`).join('')
              }</div>`
            : '<div class="text-muted small mt-2">No subject results available.</div>';
        return `
            <div class="border rounded p-3 mb-3 bg-light">
                <div class="fw-semibold">${meta}</div>
                <div class="text-muted small">Exam No: ${exam.exam_number || '-'}</div>
                ${results}
            </div>
        `;
    }).join('');
}

function renderHigherEducation(rows = []) {
    if (!Array.isArray(rows) || rows.length === 0) {
        return '<div class="text-muted">No higher education records.</div>';
    }
    const body = rows.map(r => `
        <tr>
            <td>${r.highest_qualification || '-'}</td>
            <td>${r.institution || '-'}</td>
            <td>${r.course_study || '-'}</td>
            <td>${r.grad_year || '-'}</td>
            <td>${r.cgpa || '-'}</td>
        </tr>
    `).join('');
    return `
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Qualification</th>
                        <th>Institution</th>
                        <th>Course</th>
                        <th>Year</th>
                        <th>CGPA</th>
                    </tr>
                </thead>
                <tbody>${body}</tbody>
            </table>
        </div>
    `;
}

function renderDocuments(docs = []) {
    if (!Array.isArray(docs) || docs.length === 0) {
        return '<div class="text-muted">No documents uploaded.</div>';
    }
    return docs.map(doc => {
        const status = doc.status || 'Pending';
        const badgeClass = status === 'Verified' ? 'bg-success' : (status === 'Re-upload Required' ? 'bg-danger' : 'bg-warning');
        const label = doc.document_type ? doc.document_type.replace(/_/g, ' ') : 'Document';
        const url = doc.file_url || '#';
        const safeUrl = String(url).replace(/'/g, "\\'");
        const safeLabel = String(label).replace(/'/g, "\\'");
        return `
            <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                <div>
                    <div class="fw-semibold">${label}</div>
                    <div class="text-muted small">${doc.uploaded_at || ''}</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge ${badgeClass}">${status === 'Re-upload Required' ? 'Rejected' : status}</span>
                    <button class="btn btn-sm btn-outline-primary" type="button" onclick="setPreview('${safeUrl}', '${safeLabel}')">Preview</button>
                </div>
            </div>
        `;
    }).join('');
}

function renderCv(cvDoc) {
    if (!cvDoc || !cvDoc.file_url) {
        return '<span class="text-muted">No CV uploaded.</span>';
    }
    const label = cvDoc.document_type ? cvDoc.document_type.replace(/_/g, ' ') : 'CV';
    const safeUrl = String(cvDoc.file_url).replace(/'/g, "\\'");
    const safeLabel = String(label).replace(/'/g, "\\'");
    return `
        <div class="d-flex justify-content-between align-items-center border rounded p-2">
            <div>
                <div class="fw-semibold">${label}</div>
                <div class="text-muted small">${cvDoc.uploaded_at || ''}</div>
            </div>
            <button class="btn btn-sm btn-outline-primary" type="button" onclick="setPreview('${safeUrl}', '${safeLabel}')">Preview</button>
        </div>
    `;
}

function renderReferees(refs = []) {
    if (!Array.isArray(refs) || refs.length === 0) {
        return '<span class="text-muted">No referees submitted.</span>';
    }
    return refs.map(r => `
        <div class="border rounded p-2 mb-2">
            <div class="fw-semibold">${r.full_name || '-'}</div>
            <div class="text-muted small">${[r.title, r.organization].filter(Boolean).join(' • ')}</div>
            <div class="text-muted small">${r.email || ''} ${r.phone ? ' • ' + r.phone : ''}</div>
        </div>
    `).join('');
}

async function viewStudent(appId) {
    const data = await fetchJson(`api/academic-review.php?action=student_detail&application_id=${appId}`);
    setText('detailName', data.full_name);
    setText('detailRef', data.application_number);
    setText('detailEmail', data.email);
    setText('detailProgramme', data.programme);
    setText('detailCourse', data.course);
    let completionValue = data.completion;
    if ((completionValue === null || completionValue === undefined || Number(completionValue) === 0) && data.document_total && data.document_verified_count) {
        if (Number(data.document_verified_count) >= Number(data.document_total)) {
            completionValue = 100;
        }
    }
    setText('detailCompletion', (completionValue ?? '-') + '%');
    setText('detailDocs', data.document_status);
    setText('detailReferee', data.referee_status);
    setText('detailTopic', data.topic);
    setText('detailFacultyDept', [data.faculty, data.department].filter(Boolean).join(' • ') || '-');
    setHtml('detailDegrees', renderHigherEducation(data.degrees || []));
    setHtml('detailOlevel', renderOlevel(data.olevel || []));
    setHtml('detailDocuments', renderDocuments(data.documents || []));
    setHtml('detailCv', renderCv(data.cv_document));
    setHtml('detailReferees', renderReferees(data.referees || []));
    setPreview('', '');

    const statusBadge = document.getElementById('detailStatus');
    if (statusBadge) {
        const status = (data.current_status || data.status || 'Pending').toString();
        statusBadge.textContent = status.replace(/_/g, ' ');
        statusBadge.className = 'badge';
        if (/reject/i.test(status)) statusBadge.classList.add('bg-danger');
        else if (/approve|admit|submitted/i.test(status)) statusBadge.classList.add('bg-success');
        else statusBadge.classList.add('bg-secondary');
    }

    setModalActions(data, appId);
    new bootstrap.Modal(document.getElementById('studentModal')).show();
}

function setModalActions(data, appId) {
    const footer = document.querySelector('#studentModal .modal-footer');
    if (!footer) return;
    footer.setAttribute('data-app-id', appId);
    const status = (data.status || '').toLowerCase();
    const current = (data.current_status || '').toUpperCase();
    const isFinal = ['admitted', 'rejected', 'department approved', 'department rejected'].includes(status) || ['DEPT_APPROVED', 'DEPT_REJECTED', 'ADMISSION_APPROVED', 'ADMISSION_REJECTED'].includes(current);
    if (isFinal) {
        footer.classList.add('d-none');
        footer.classList.remove('d-flex');
    } else {
        footer.classList.remove('d-none');
        footer.classList.add('d-flex');
    }
}

async function acceptApplication(appId) {
    const formData = new FormData();
    formData.append('action', 'accept');
    formData.append('application_id', appId);
    const res = await fetch('api/academic-review.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to accept application.');
        return;
    }
    loadStudents();
}

async function modalAccept() {
    const footer = document.querySelector('#studentModal .modal-footer');
    const appId = footer ? footer.getAttribute('data-app-id') : '';
    if (!appId) return;
    await acceptApplication(appId);
    const modalEl = document.getElementById('studentModal');
    if (document.activeElement && modalEl.contains(document.activeElement)) {
        document.activeElement.blur();
    }
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) {
        modalInstance.hide();
    } else {
        const closeBtn = modalEl.querySelector('.btn-close') || modalEl.querySelector('[data-bs-dismiss="modal"]');
        if (closeBtn) closeBtn.click();
    }
}

async function modalReject() {
    const footer = document.querySelector('#studentModal .modal-footer');
    const appId = footer ? footer.getAttribute('data-app-id') : '';
    if (!appId) return;
    const formData = new FormData();
    formData.append('action', 'reject');
    formData.append('application_id', appId);
    const res = await fetch('api/academic-review.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to reject application.');
        return;
    }
    loadStudents();
    const modalEl = document.getElementById('studentModal');
    if (document.activeElement && modalEl.contains(document.activeElement)) {
        document.activeElement.blur();
    }
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) {
        modalInstance.hide();
    } else {
        const closeBtn = modalEl.querySelector('.btn-close') || modalEl.querySelector('[data-bs-dismiss="modal"]');
        if (closeBtn) closeBtn.click();
    }
}

async function applyBulk() {
    const action = document.getElementById('bulkAction').value;
    if (!action) return;
    const selected = Array.from(document.querySelectorAll('.row-check:checked')).map(el => el.value);
    if (selected.length === 0) return;

    const formData = new FormData();
    formData.append('action', 'bulk');
    formData.append('bulk_action', action);
    formData.append('ids', JSON.stringify(selected));

    const res = await fetch('api/academic-review.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        loadStudents();
    } else {
        alert(data.message || 'Bulk action failed.');
    }
}

function refreshReview() {
    if (filters.course.value) {
        loadStudents();
    }
}

function clearTable() {
    reviewTable.innerHTML = '<tr><td colspan="7" class="text-muted">Select filters to view applicants.</td></tr>';
    resultsMeta.textContent = 'No results yet.';
}

async function fetchJson(url) {
    const res = await fetch(url);
    const data = await res.json();
    return data.data || data;
}

async function fetchJsonRaw(url) {
    const res = await fetch(url);
    return res.json();
}
</script>

<?php require_once 'includes/footer.php'; ?>
