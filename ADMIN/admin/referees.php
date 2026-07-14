<?php
session_start();
$pageTitle = 'Referees';
$pageSubtitle = 'Track referee requests, submissions, and verification status.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Referees</h1>
        <p class="panel-muted">Send verification links and review referee submissions.</p>
    </div>
    <div class="hero-actions d-flex gap-2 flex-wrap">
        <button class="btn btn-primary" id="contactAllPendingBtn" onclick="contactAllPendingReferees()">
            <i class="fas fa-paper-plane me-1"></i> Contact All Pending Referees
        </button>
        <button class="btn btn-outline-primary" onclick="loadReferees()">Refresh</button>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Referee Requests</h3>
            <div class="panel-muted" id="refereeMeta">No data loaded.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <select class="form-select form-select-sm" id="filterFaculty" style="min-width: 200px;"></select>
            <select class="form-select form-select-sm" id="filterDepartment" style="min-width: 200px;" disabled></select>
            <select class="form-select form-select-sm" id="filterProgramme" style="min-width: 200px;" disabled></select>
            <select class="form-select form-select-sm" id="filterCourse" style="min-width: 200px;" disabled></select>
            <select class="form-select form-select-sm" id="filterStatus" style="max-width: 200px;">
                <option value="">All Status</option>
                <option value="Pending">Pending</option>
                <option value="Requested">Requested</option>
                <option value="Submitted">Submitted</option>
                <option value="Verified">Verified</option>
                <option value="Rejected">Rejected</option>
            </select>
            <button class="btn btn-sm btn-primary" onclick="loadReferees()">Apply</button>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Programme</th>
                        <th>Referee Status</th>
                        <th>Last Updated</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="refereeTable"></tbody>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="refereeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Referee Submissions</h5>
                    <div class="text-muted small">Applicant: <strong id="modalApplicant">-</strong> • <span id="modalAppNumber">-</span></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="text-uppercase text-muted small">Referee 1</div>
                                    <span class="badge bg-secondary" id="ref1Status">Pending</span>
                                </div>
                                <div class="fw-semibold" id="ref1Name">-</div>
                                <div class="text-muted small mb-2" id="ref1Title">-</div>
                                <div class="small text-muted" id="ref1Org">-</div>
                                <div class="small text-muted" id="ref1Email">-</div>
                                <div class="small text-muted mb-3" id="ref1Phone">-</div>
                                <div class="small text-muted mb-2">Work Email</div>
                                <div class="fw-semibold mb-3" id="ref1WorkEmail">-</div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="text-muted small mb-1">Passport</div>
                                        <div class="ratio ratio-4x5 border rounded bg-light">
                                            <img id="ref1PassportImg" alt="Passport" style="display:none;width:100%;height:100%;object-fit:cover;">
                                            <iframe id="ref1PassportFrame" src="about:blank" class="w-100 h-100 border-0" style="display:none;"></iframe>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-muted small mb-1">Work ID</div>
                                        <div class="ratio ratio-4x5 border rounded bg-light">
                                            <img id="ref1WorkIdImg" alt="Work ID" style="display:none;width:100%;height:100%;object-fit:cover;">
                                            <iframe id="ref1WorkIdFrame" src="about:blank" class="w-100 h-100 border-0" style="display:none;"></iframe>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-3 mb-2" id="ref1DetailsToggleContainer" style="display:none;">
                                    <button class="btn btn-sm btn-outline-primary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#ref1CollapsibleDetails">
                                        <i class="fas fa-list me-1"></i> View Form Evaluation
                                    </button>
                                    <div class="collapse mt-2 border rounded p-3 bg-light text-start" id="ref1CollapsibleDetails">
                                        <div class="small">
                                            <div class="mb-2"><strong>Department:</strong> <span id="ref1SubDept">-</span></div>
                                            <div class="mb-2"><strong>Position:</strong> <span id="ref1SubPos">-</span></div>
                                            <div class="mb-2"><strong>Official Address:</strong> <span id="ref1SubAddress">-</span></div>
                                            <div class="mb-2"><strong>Relationship:</strong> <span id="ref1SubRel">-</span> (<span id="ref1SubYears">-</span> years known)</div>
                                            
                                            <h6 class="fw-bold mt-3 mb-2 border-bottom pb-1 text-primary">Section C: Ratings</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered bg-white mb-2" style="font-size: 0.75rem;">
                                                    <tbody>
                                                        <tr><td>Character & Integrity</td><td id="ref1AssessCharacter" class="fw-semibold text-center"></td></tr>
                                                        <tr><td>Professional Competence</td><td id="ref1AssessCompetence" class="fw-semibold text-center"></td></tr>
                                                        <tr><td>Leadership Ability</td><td id="ref1AssessLeadership" class="fw-semibold text-center"></td></tr>
                                                        <tr><td>Communication Skills</td><td id="ref1AssessCommunication" class="fw-semibold text-center"></td></tr>
                                                        <tr><td>Teamwork</td><td id="ref1AssessTeamwork" class="fw-semibold text-center"></td></tr>
                                                        <tr><td>Reliability</td><td id="ref1AssessReliability" class="fw-semibold text-center"></td></tr>
                                                        <tr><td>Initiative</td><td id="ref1AssessInitiative" class="fw-semibold text-center"></td></tr>
                                                        <tr><td>Emotional Stability</td><td id="ref1AssessStability" class="fw-semibold text-center"></td></tr>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <h6 class="fw-bold mt-3 mb-2 border-bottom pb-1 text-primary">Section D: Comments</h6>
                                            <div class="mb-2"><strong>Strengths:</strong> <p id="ref1Strengths" class="mb-0 text-muted style-italic"></p></div>
                                            <div class="mb-2"><strong>Weaknesses:</strong> <p id="ref1Weaknesses" class="mb-0 text-muted style-italic"></p></div>
                                            <div class="mb-2"><strong>Recommendation:</strong> <span id="ref1Recommendation" class="badge"></span></div>
                                            <div class="mb-2"><strong>Additional Comments:</strong> <p id="ref1AddComments" class="mb-0 text-muted style-italic"></p></div>

                                            <h6 class="fw-bold mt-3 mb-2 border-bottom pb-1 text-success">Section E: Declaration</h6>
                                            <div class="mb-1"><i class="fas fa-check text-success me-1"></i> Certified & Signed by <strong><span id="ref1Signature">-</span></strong></div>
                                            <div class="text-muted" style="font-size: 0.7rem;">Date: <span id="ref1DeclDate">-</span></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 text-end" id="ref1ActionContainer" style="display:none;">
                                    <button class="btn btn-sm btn-success me-1" id="ref1VerifyBtn" onclick="processRefereeAction(1, 'verify')"><i class="fas fa-check me-1"></i>Verify</button>
                                    <button class="btn btn-sm btn-danger" id="ref1RejectBtn" onclick="processRefereeAction(1, 'reject')"><i class="fas fa-times me-1"></i>Reject</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="text-uppercase text-muted small">Referee 2</div>
                                    <span class="badge bg-secondary" id="ref2Status">Pending</span>
                                </div>
                                <div class="fw-semibold" id="ref2Name">-</div>
                                <div class="text-muted small mb-2" id="ref2Title">-</div>
                                <div class="small text-muted" id="ref2Org">-</div>
                                <div class="small text-muted" id="ref2Email">-</div>
                                <div class="small text-muted mb-3" id="ref2Phone">-</div>
                                <div class="small text-muted mb-2">Work Email</div>
                                <div class="fw-semibold mb-3" id="ref2WorkEmail">-</div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="text-muted small mb-1">Passport</div>
                                        <div class="ratio ratio-4x5 border rounded bg-light">
                                            <img id="ref2PassportImg" alt="Passport" style="display:none;width:100%;height:100%;object-fit:cover;">
                                            <iframe id="ref2PassportFrame" src="about:blank" class="w-100 h-100 border-0" style="display:none;"></iframe>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-muted small mb-1">Work ID</div>
                                        <div class="ratio ratio-4x5 border rounded bg-light">
                                            <img id="ref2WorkIdImg" alt="Work ID" style="display:none;width:100%;height:100%;object-fit:cover;">
                                            <iframe id="ref2WorkIdFrame" src="about:blank" class="w-100 h-100 border-0" style="display:none;"></iframe>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-3 mb-2" id="ref2DetailsToggleContainer" style="display:none;">
                                    <button class="btn btn-sm btn-outline-primary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#ref2CollapsibleDetails">
                                        <i class="fas fa-list me-1"></i> View Form Evaluation
                                    </button>
                                    <div class="collapse mt-2 border rounded p-3 bg-light text-start" id="ref2CollapsibleDetails">
                                        <div class="small">
                                            <div class="mb-2"><strong>Department:</strong> <span id="ref2SubDept">-</span></div>
                                            <div class="mb-2"><strong>Position:</strong> <span id="ref2SubPos">-</span></div>
                                            <div class="mb-2"><strong>Official Address:</strong> <span id="ref2SubAddress">-</span></div>
                                            <div class="mb-2"><strong>Relationship:</strong> <span id="ref2SubRel">-</span> (<span id="ref2SubYears">-</span> years known)</div>
                                            
                                            <h6 class="fw-bold mt-3 mb-2 border-bottom pb-1 text-primary">Section C: Ratings</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered bg-white mb-2" style="font-size: 0.75rem;">
                                                    <tbody>
                                                        <tr><td>Character & Integrity</td><td id="ref2AssessCharacter" class="fw-semibold text-center"></td></tr>
                                                        <tr><td>Professional Competence</td><td id="ref2AssessCompetence" class="fw-semibold text-center"></td></tr>
                                                        <tr><td>Leadership Ability</td><td id="ref2AssessLeadership" class="fw-semibold text-center"></td></tr>
                                                        <tr><td>Communication Skills</td><td id="ref2AssessCommunication" class="fw-semibold text-center"></td></tr>
                                                        <tr><td>Teamwork</td><td id="ref2AssessTeamwork" class="fw-semibold text-center"></td></tr>
                                                        <tr><td>Reliability</td><td id="ref2AssessReliability" class="fw-semibold text-center"></td></tr>
                                                        <tr><td>Initiative</td><td id="ref2AssessInitiative" class="fw-semibold text-center"></td></tr>
                                                        <tr><td>Emotional Stability</td><td id="ref2AssessStability" class="fw-semibold text-center"></td></tr>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <h6 class="fw-bold mt-3 mb-2 border-bottom pb-1 text-primary">Section D: Comments</h6>
                                            <div class="mb-2"><strong>Strengths:</strong> <p id="ref2Strengths" class="mb-0 text-muted style-italic"></p></div>
                                            <div class="mb-2"><strong>Weaknesses:</strong> <p id="ref2Weaknesses" class="mb-0 text-muted style-italic"></p></div>
                                            <div class="mb-2"><strong>Recommendation:</strong> <span id="ref2Recommendation" class="badge"></span></div>
                                            <div class="mb-2"><strong>Additional Comments:</strong> <p id="ref2AddComments" class="mb-0 text-muted style-italic"></p></div>

                                            <h6 class="fw-bold mt-3 mb-2 border-bottom pb-1 text-success">Section E: Declaration</h6>
                                            <div class="mb-1"><i class="fas fa-check text-success me-1"></i> Certified & Signed by <strong><span id="ref2Signature">-</span></strong></div>
                                            <div class="text-muted" style="font-size: 0.7rem;">Date: <span id="ref2DeclDate">-</span></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 text-end" id="ref2ActionContainer" style="display:none;">
                                    <button class="btn btn-sm btn-success me-1" id="ref2VerifyBtn" onclick="processRefereeAction(2, 'verify')"><i class="fas fa-check me-1"></i>Verify</button>
                                    <button class="btn btn-sm btn-danger" id="ref2RejectBtn" onclick="processRefereeAction(2, 'reject')"><i class="fas fa-times me-1"></i>Reject</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-end gap-2" data-app-id="">
                <button type="button" class="btn btn-outline-primary" onclick="contactApplicantReferees()">
                    <i class="fas fa-paper-plane me-1"></i> Contact Referees
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bulkRefereeProgressModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sending Referee Requests</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="bulkRefereeProgressCloseBtn" disabled></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="spinner-border spinner-border-sm text-primary" id="bulkRefereeSpinner" role="status" aria-hidden="true"></span>
                    <strong id="bulkRefereeProgressText">Preparing...</strong>
                </div>
                <div class="progress mb-2" style="height: 10px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" id="bulkRefereeProgressBar" style="width: 0%;"></div>
                </div>
                <div class="small text-muted" id="bulkRefereeCurrentLabel">Waiting to start.</div>
                <div class="small mt-2">
                    <span class="me-2">Sent: <strong id="bulkRefereeSentCount">0</strong></span>
                    <span class="me-2">Skipped: <strong id="bulkRefereeSkippedCount">0</strong></span>
                    <span>Failed: <strong id="bulkRefereeFailedCount">0</strong></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" id="bulkRefereeDoneBtn" disabled>Done</button>
            </div>
        </div>
    </div>
</div>

<script>
const refereeTable = document.getElementById('refereeTable');
const refereeMeta = document.getElementById('refereeMeta');
const filterStatus = document.getElementById('filterStatus');
const filterFaculty = document.getElementById('filterFaculty');
const filterDepartment = document.getElementById('filterDepartment');
const filterProgramme = document.getElementById('filterProgramme');
const filterCourse = document.getElementById('filterCourse');

document.addEventListener('DOMContentLoaded', () => {
    initFilters();
});

async function loadReferees() {
    const status = filterStatus.value || '';
    const facultyId = filterFaculty.value || '';
    const departmentId = filterDepartment.value || '';
    const programmeId = filterProgramme.value || '';
    const courseId = filterCourse.value || '';
    if (!facultyId || !departmentId || !programmeId || !courseId) {
        refereeTable.innerHTML = '<tr><td colspan="5" class="text-muted">Select Faculty, Department, Programme, and Course to view applicants.</td></tr>';
        refereeMeta.textContent = 'Waiting for filters.';
        return;
    }
    const data = await fetchJson(`api/referees.php?action=list&status=${encodeURIComponent(status)}&faculty_id=${encodeURIComponent(facultyId)}&department_id=${encodeURIComponent(departmentId)}&programme_id=${encodeURIComponent(programmeId)}&course_id=${encodeURIComponent(courseId)}`);
    if (!data || !data.data) {
        refereeTable.innerHTML = '<tr><td colspan="5" class="text-muted">No data available.</td></tr>';
        return;
    }
    const list = data.data;
    refereeMeta.textContent = `${list.length} applicants loaded`;
    refereeTable.innerHTML = list.map(item => `
        <tr>
            <td>
                <div class="fw-semibold">${escapeHtml(item.applicant_name)}</div>
                <div class="text-muted small">${escapeHtml(item.application_number)}</div>
            </td>
            <td>
                <div class="fw-semibold">${escapeHtml(item.programme || '-')}</div>
                <div class="text-muted small">${escapeHtml([item.faculty, item.department, item.course].filter(Boolean).join(' • ') || '-')}</div>
            </td>
            <td>
                <div class="d-flex flex-wrap gap-1">
                    ${(item.referees || []).map(ref => `<span class="badge ${badgeClass(ref.status)}">${escapeHtml(ref.name)}: ${escapeHtml(ref.status)}</span>`).join('')}
                </div>
            </td>
            <td class="text-muted small">${escapeHtml(item.updated_at || '-')}</td>
            <td class="text-end">
                <button class="btn btn-outline-secondary btn-sm" onclick="viewSubmission(${item.application_id})">
                    <i class="fas fa-eye me-1"></i> View
                </button>
            </td>
        </tr>
    `).join('');
}

function badgeClass(status) {
    switch (status) {
        case 'Verified': return 'bg-success';
        case 'Submitted': return 'bg-primary';
        case 'Requested': return 'bg-warning';
        case 'Rejected': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

async function contactApplicant(appId) {
    const formData = new FormData();
    formData.append('action', 'contact_applicant');
    formData.append('application_id', appId);
    const res = await fetch('api/referees.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Failed to contact referee.');
        return;
    }
    loadReferees();
    alert('Referee email sent successfully.');
}

async function contactAllPendingReferees() {
    const btn = document.getElementById('contactAllPendingBtn');
    if (!btn) return;
    if (!confirm('Send verification requests to all referees not yet contacted across the system?')) {
        return;
    }

    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';

    try {
        const listRes = await fetch('api/referees.php?action=pending_contact_list');
        const listData = await listRes.json();
        if (!listData.success) {
            alert(listData.message || 'Failed to load pending referees.');
            return;
        }

        const pending = Array.isArray(listData.data) ? listData.data : [];
        if (pending.length === 0) {
            alert('No pending referees to contact.');
            return;
        }

        const modalEl = document.getElementById('bulkRefereeProgressModal');
        const modal = new bootstrap.Modal(modalEl);
        const closeBtn = document.getElementById('bulkRefereeProgressCloseBtn');
        const doneBtn = document.getElementById('bulkRefereeDoneBtn');
        const spinner = document.getElementById('bulkRefereeSpinner');
        const progressText = document.getElementById('bulkRefereeProgressText');
        const progressBar = document.getElementById('bulkRefereeProgressBar');
        const currentLabel = document.getElementById('bulkRefereeCurrentLabel');
        const sentEl = document.getElementById('bulkRefereeSentCount');
        const skippedEl = document.getElementById('bulkRefereeSkippedCount');
        const failedEl = document.getElementById('bulkRefereeFailedCount');

        let sent = 0;
        let skipped = 0;
        let failed = 0;
        const total = pending.length;
        spinner.style.display = '';
        progressBar.classList.add('progress-bar-animated');
        progressBar.classList.add('progress-bar-striped');
        progressBar.style.width = '0%';
        progressText.textContent = 'Starting...';
        currentLabel.textContent = 'Preparing queue.';
        sentEl.textContent = '0';
        skippedEl.textContent = '0';
        failedEl.textContent = '0';
        closeBtn.disabled = true;
        doneBtn.disabled = true;
        modal.show();

        for (let i = 0; i < total; i++) {
            const row = pending[i];
            const step = i + 1;
            progressText.textContent = `${step} of ${total} sent`;
            currentLabel.textContent = `Sending to ${row.referee_name || 'Referee'} (${row.referee_email || 'no-email'})`;
            progressBar.style.width = `${Math.round(((step - 1) / total) * 100)}%`;

            const formData = new FormData();
            formData.append('action', 'contact_pending_referee');
            formData.append('referee_id', String(row.referee_id));

            try {
                const itemRes = await fetch('api/referees.php', { method: 'POST', body: formData });
                const itemData = await itemRes.json();
                if (itemData.status === 'sent') {
                    sent++;
                } else if (itemData.status === 'skipped') {
                    skipped++;
                } else {
                    failed++;
                }
            } catch (error) {
                failed++;
            }

            sentEl.textContent = String(sent);
            skippedEl.textContent = String(skipped);
            failedEl.textContent = String(failed);
            progressBar.style.width = `${Math.round((step / total) * 100)}%`;
        }

        progressText.textContent = `Completed ${total} of ${total}`;
        currentLabel.textContent = 'Referee bulk contact process completed.';
        progressBar.classList.remove('progress-bar-animated');
        progressBar.classList.remove('progress-bar-striped');
        spinner.style.display = 'none';
        closeBtn.disabled = false;
        doneBtn.disabled = false;
        loadReferees();
    } catch (e) {
        alert('Failed to send bulk referee requests.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}

async function viewSubmission(appId) {
    const data = await fetchJson(`api/referees.php?action=detail&application_id=${appId}`);
    if (!data || !data.success) {
        alert(data.message || 'Unable to load submission.');
        return;
    }
    document.getElementById('modalApplicant').textContent = data.data.applicant_name || '-';
    document.getElementById('modalAppNumber').textContent = data.data.application_number || '-';

    const footer = document.querySelector('#refereeModal .modal-footer');
    if (footer) {
        footer.setAttribute('data-app-id', appId);
    }

    const refs = data.data.referees || [];
    const ref1 = refs[0] || {};
    const ref2 = refs[1] || {};
    fillReferee(1, ref1);
    fillReferee(2, ref2);

    const contactBtn = document.querySelector('#refereeModal .modal-footer .btn-outline-primary');
    if (contactBtn) {
        const shouldDisable = shouldDisableContact(refs);
        contactBtn.disabled = shouldDisable;
        contactBtn.classList.toggle('disabled', shouldDisable);
    }

    const modal = new bootstrap.Modal(document.getElementById('refereeModal'));
    modal.show();
}

function shouldDisableContact(refs = []) {
    if (!Array.isArray(refs) || refs.length === 0) return true;
    const norm = (s) => String(s || '').toLowerCase();
    const responded = (s) => ['submitted', 'verified', 'rejected'].includes(norm(s));
    if (refs.length === 1) {
        return responded(refs[0].status) || norm(refs[0].status) === 'requested';
    }
    const anyPending = refs.some(r => !responded(r.status));
    if (anyPending) return false;
    return true;
}

async function contactApplicantReferees() {
    const footer = document.querySelector('#refereeModal .modal-footer');
    const appId = footer ? footer.getAttribute('data-app-id') : '';
    if (!appId) return;
    await contactApplicant(appId);
}

function fillReferee(index, ref) {
    const statusEl = document.getElementById(`ref${index}Status`);
    const status = ref.status || 'Pending';
    if (statusEl) {
        statusEl.textContent = status;
        statusEl.className = `badge ${badgeClass(status)}`;
    }
    setText(`ref${index}Name`, ref.name);
    setText(`ref${index}Title`, ref.title);
    setText(`ref${index}Org`, ref.organization);
    setText(`ref${index}Email`, ref.email);
    setText(`ref${index}Phone`, ref.phone);
    setText(`ref${index}WorkEmail`, ref.work_email);
    setPreviewDoc(`ref${index}Passport`, ref.passport_path);
    setPreviewDoc(`ref${index}WorkId`, ref.work_id_path);

    const actionContainer = document.getElementById(`ref${index}ActionContainer`);
    if (actionContainer) {
        if (status === 'Submitted' && ref.referee_id) {
            actionContainer.style.display = 'block';
            actionContainer.dataset.refereeId = ref.referee_id;
        } else {
            actionContainer.style.display = 'none';
        }
    }

    // Populate Evaluation Form details
    const toggleContainer = document.getElementById(`ref${index}DetailsToggleContainer`);
    if (toggleContainer) {
        const hasForm = ['Submitted', 'Verified', 'Rejected'].includes(status) && ref.submitted_name;
        if (hasForm) {
            toggleContainer.style.display = 'block';
            setText(`ref${index}SubDept`, ref.submitted_dept);
            setText(`ref${index}SubPos`, ref.submitted_pos);
            setText(`ref${index}SubAddress`, ref.submitted_address);
            setText(`ref${index}SubRel`, ref.relationship);
            setText(`ref${index}SubYears`, ref.years_known);
            
            setText(`ref${index}AssessCharacter`, ref.assess_character);
            setText(`ref${index}AssessCompetence`, ref.assess_competence);
            setText(`ref${index}AssessLeadership`, ref.assess_leadership);
            setText(`ref${index}AssessCommunication`, ref.assess_communication);
            setText(`ref${index}AssessTeamwork`, ref.assess_teamwork);
            setText(`ref${index}AssessReliability`, ref.assess_reliability);
            setText(`ref${index}AssessInitiative`, ref.assess_initiative);
            setText(`ref${index}AssessStability`, ref.assess_stability);
            
            const strengthsEl = document.getElementById(`ref${index}Strengths`);
            if (strengthsEl) strengthsEl.innerHTML = escapeHtml(ref.strengths || 'None').replace(/\n/g, '<br>');
            
            const weaknessesEl = document.getElementById(`ref${index}Weaknesses`);
            if (weaknessesEl) weaknessesEl.innerHTML = escapeHtml(ref.weaknesses || 'None').replace(/\n/g, '<br>');
            
            const addCommentsEl = document.getElementById(`ref${index}AddComments`);
            if (addCommentsEl) addCommentsEl.innerHTML = escapeHtml(ref.additional_comments || 'None').replace(/\n/g, '<br>');
            
            const recEl = document.getElementById(`ref${index}Recommendation`);
            if (recEl) {
                recEl.textContent = ref.recommendation || 'Not Rated';
                recEl.className = 'badge ' + 
                    (ref.recommendation === 'Strongly Recommend' ? 'bg-success' : 
                     ref.recommendation === 'Recommend' ? 'bg-primary' : 
                     ref.recommendation === 'Recommend with Reservation' ? 'bg-warning text-dark' : 'bg-danger');
            }
            
            setText(`ref${index}Signature`, ref.signature);
            setText(`ref${index}DeclDate`, ref.decl_date);
        } else {
            toggleContainer.style.display = 'none';
        }
    }
}

async function processRefereeAction(index, action) {
    const actionContainer = document.getElementById(`ref${index}ActionContainer`);
    const refId = actionContainer ? actionContainer.dataset.refereeId : '';
    if (!refId) return;

    let remarks = '';
    if (action === 'reject') {
        remarks = prompt('Enter reason for rejection:');
        if (remarks === null) return; // cancelled
        if (!remarks.trim()) {
            alert('Rejection reason is required.');
            return;
        }
    }

    const formData = new FormData();
    formData.append('action', action);
    formData.append('referee_id', refId);
    if (remarks) {
        formData.append('remarks', remarks);
    }

    try {
        const res = await fetch('api/referees.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert(`Referee ${action === 'verify' ? 'verified' : 'rejected'} successfully.`);
            // Refresh modal and table
            const footer = document.querySelector('#refereeModal .modal-footer');
            const appId = footer ? footer.getAttribute('data-app-id') : '';
            if (appId) {
                // Hide modal, refresh list, reopen modal
                const modalEl = document.getElementById('refereeModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
                await loadReferees();
                await viewSubmission(appId);
            } else {
                location.reload();
            }
        } else {
            alert(data.message || 'Action failed.');
        }
    } catch (err) {
        console.error(err);
        alert('An error occurred.');
    }
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value || '-';
}

function setPreviewDoc(prefix, url) {
    const img = document.getElementById(`${prefix}Img`);
    const frame = document.getElementById(`${prefix}Frame`);
    const safeUrl = url || '';
    if (img) { img.style.display = 'none'; img.src = ''; }
    if (frame) { frame.style.display = 'none'; frame.src = 'about:blank'; }
    if (!safeUrl) return;
    const lower = safeUrl.toLowerCase();
    if (lower.endsWith('.jpg') || lower.endsWith('.jpeg') || lower.endsWith('.png') || lower.endsWith('.gif') || lower.endsWith('.webp')) {
        if (img) { img.src = safeUrl; img.style.display = 'block'; }
    } else {
        if (frame) { frame.src = safeUrl; frame.style.display = 'block'; }
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, function(m) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);
    });
}

async function fetchJson(url) {
    const res = await fetch(url);
    return res.json();
}

async function initFilters() {
    await loadFaculties();
    filterFaculty.addEventListener('change', async () => {
        filterDepartment.innerHTML = '<option value="">Select Department</option>';
        filterProgramme.innerHTML = '<option value="">Select Programme</option>';
        filterCourse.innerHTML = '<option value="">Select Course</option>';
        filterDepartment.disabled = true;
        filterProgramme.disabled = true;
        filterCourse.disabled = true;
        if (filterFaculty.value) {
            await loadDepartments(filterFaculty.value);
        }
    });
    filterDepartment.addEventListener('change', async () => {
        filterProgramme.innerHTML = '<option value="">Select Programme</option>';
        filterCourse.innerHTML = '<option value="">Select Course</option>';
        filterProgramme.disabled = true;
        filterCourse.disabled = true;
        if (filterFaculty.value && filterDepartment.value) {
            await loadProgrammes(filterFaculty.value, filterDepartment.value);
        }
    });
    filterProgramme.addEventListener('change', async () => {
        filterCourse.innerHTML = '<option value="">Select Course</option>';
        filterCourse.disabled = true;
        if (filterFaculty.value && filterDepartment.value && filterProgramme.value) {
            await loadCourses(filterFaculty.value, filterDepartment.value, filterProgramme.value);
        }
    });
    filterCourse.addEventListener('change', () => loadReferees());
    loadReferees();
}

async function loadFaculties() {
    const data = await fetchJson('api/referees.php?action=faculties');
    filterFaculty.innerHTML = '<option value="">Select Faculty</option>';
    (data.data || []).forEach(row => {
        const opt = document.createElement('option');
        opt.value = row.id;
        opt.textContent = row.name;
        filterFaculty.appendChild(opt);
    });
    filterDepartment.innerHTML = '<option value="">Select Department</option>';
    filterProgramme.innerHTML = '<option value="">Select Programme</option>';
    filterCourse.innerHTML = '<option value="">Select Course</option>';
}

async function loadDepartments(facultyId) {
    const data = await fetchJson(`api/referees.php?action=departments&faculty_id=${encodeURIComponent(facultyId)}`);
    filterDepartment.innerHTML = '<option value="">Select Department</option>';
    (data.data || []).forEach(row => {
        const opt = document.createElement('option');
        opt.value = row.id;
        opt.textContent = row.name;
        filterDepartment.appendChild(opt);
    });
    filterDepartment.disabled = false;
}

async function loadProgrammes(facultyId, departmentId) {
    const data = await fetchJson(`api/referees.php?action=programmes&faculty_id=${encodeURIComponent(facultyId)}&department_id=${encodeURIComponent(departmentId)}`);
    filterProgramme.innerHTML = '<option value="">Select Programme</option>';
    (data.data || []).forEach(row => {
        const opt = document.createElement('option');
        opt.value = row.id;
        opt.textContent = row.name;
        filterProgramme.appendChild(opt);
    });
    filterProgramme.disabled = false;
}

async function loadCourses(facultyId, departmentId, programmeId) {
    const data = await fetchJson(`api/referees.php?action=courses&faculty_id=${encodeURIComponent(facultyId)}&department_id=${encodeURIComponent(departmentId)}&programme_id=${encodeURIComponent(programmeId)}`);
    filterCourse.innerHTML = '<option value="">Select Course</option>';
    (data.data || []).forEach(row => {
        const opt = document.createElement('option');
        opt.value = row.id;
        opt.textContent = row.name;
        filterCourse.appendChild(opt);
    });
    filterCourse.disabled = false;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
