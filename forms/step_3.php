<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$data = $_SESSION['form_data']['step_3'] ?? []; 
?>

<div class="tab-pane active">
    <h5 class="section-header text-primary mb-4">Higher Education</h5>
    <div class="row g-3 mb-5">
        <div class="col-md-4">
            <label class="form-label">Highest Qualification</label>
            <select class="form-select" name="highest_qualification" required>
                <option value="" selected disabled>Choose...</option>
                <option value="BSc" <?php echo ($data['highest_qualification'] ?? '') == 'BSc' ? 'selected' : ''; ?>>B.Sc / B.A</option>
                <option value="MSc" <?php echo ($data['highest_qualification'] ?? '') == 'MSc' ? 'selected' : ''; ?>>M.Sc / M.A</option>
                <option value="HND" <?php echo ($data['highest_qualification'] ?? '') == 'HND' ? 'selected' : ''; ?>>HND</option>
                <option value="PhD" <?php echo ($data['highest_qualification'] ?? '') == 'PhD' ? 'selected' : ''; ?>>PhD</option>
            </select>
        </div>
        <div class="col-md-8">
            <label class="form-label">Course of Study</label>
            <input type="text" class="form-control" name="course_study" value="<?php echo $data['course_study'] ?? ''; ?>" required>
        </div>
        <div class="col-md-8">
            <label class="form-label">Institution Attended</label>
            <input type="text" class="form-control" name="institution" value="<?php echo $data['institution'] ?? ''; ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Year of Graduation</label>
            <input type="number" 
                   class="form-control year-input" 
                   name="grad_year" 
                   min="1950" 
                   max="<?php echo date('Y'); ?>" 
                   placeholder="YYYY" 
                   value="<?php echo $data['grad_year'] ?? ''; ?>" 
                   required>
            <div class="invalid-feedback">Enter a valid year (1950 - <?php echo date('Y'); ?>).</div>
        </div>
        
        <div class="col-md-4">
            <label class="form-label">Grade Scale</label>
            <select class="form-select" name="grade_scale" id="gradeScaleSelect" required>
                <option value="" selected disabled>Select Scale...</option>
                <option value="5.0" <?php echo ($data['grade_scale'] ?? '') == '5.0' ? 'selected' : ''; ?>>5.0</option>
                <option value="4.0" <?php echo ($data['grade_scale'] ?? '') == '4.0' ? 'selected' : ''; ?>>4.0</option>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Class of Degree / CGPA</label>
            <input type="number" 
                   step="0.01"
                   class="form-control" 
                   id="cgpaInput" 
                   name="cgpa" 
                   placeholder="e.g. 4.50" 
                   value="<?php echo $data['cgpa'] ?? ''; ?>" 
                   required>
            <div class="form-text" id="cgpaHelper">Max 5.00</div>
            <div class="invalid-feedback" id="cgpaFeedback">Invalid CGPA.</div>
        </div>
        
        <div class="col-md-4">
            <label class="form-label">Mode of Study</label>
            <select class="form-select" name="mode_study" required>
                <option value="" selected disabled>Select Mode...</option>
                <option value="FT" <?php echo ($data['mode_study'] ?? '') == 'FT' ? 'selected' : ''; ?>>Full Time (FT)</option>
                <option value="PT" <?php echo ($data['mode_study'] ?? '') == 'PT' ? 'selected' : ''; ?>>Part Time (PT)</option>
            </select>
        </div>
    </div>

    <h5 class="section-header text-primary mb-3">SSCE 1 O'Level Results</h5>
    <div class="p-3 border rounded bg-light mb-4">
        <div class="row g-3 mb-3">
            <div class="col-md-12">
                <label class="form-label">Name of School</label>
                <input type="text" class="form-control" name="ssce1_school" value="<?php echo $data['ssce1_school'] ?? ''; ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Exam Number</label>
                <input type="text" class="form-control" name="ssce1_exam_number" value="<?php echo $data['ssce1_exam_number'] ?? ''; ?>" placeholder="e.g. 4123456789" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Exam Year</label>
                <input type="number" class="form-control year-input" name="ssce1_year" min="1950" max="<?php echo date('Y'); ?>" placeholder="YYYY" value="<?php echo $data['ssce1_year'] ?? ''; ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Result Type</label>
                <select class="form-select" name="ssce1_type" id="ssce1_type" onchange="toggleOtherInput(this, 'otherType1')" required>
                    <option value="">Select...</option>
                    <option value="WAEC" <?php echo ($data['ssce1_type'] ?? '') == 'WAEC' ? 'selected' : ''; ?>>WAEC</option>
                    <option value="NECO" <?php echo ($data['ssce1_type'] ?? '') == 'NECO' ? 'selected' : ''; ?>>NECO</option>
                    <option value="NABTEB" <?php echo ($data['ssce1_type'] ?? '') == 'NABTEB' ? 'selected' : ''; ?>>NABTEB</option>
                    <option value="GCE" <?php echo ($data['ssce1_type'] ?? '') == 'GCE' ? 'selected' : ''; ?>>GCE</option>
                    <option value="Others" <?php echo ($data['ssce1_type'] ?? '') == 'Others' ? 'selected' : ''; ?>>Others (Specify)</option>
                </select>
                <input type="text" id="otherType1" name="ssce1_type_other" class="form-control mt-2 <?php echo ($data['ssce1_type'] ?? '') == 'Others' ? '' : 'd-none'; ?>" value="<?php echo $data['ssce1_type_other'] ?? ''; ?>" placeholder="Specify Result Type">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-sm" id="olevelTable1">
                <thead class="table-secondary">
                    <tr>
                        <th style="width: 50px;">S/N</th>
                        <th>Subject</th>
                        <th style="width: 150px;">Grade</th>
                        <th style="width: 50px;"></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center">
            <button type="button" class="btn btn-sm btn-primary" onclick="addOLevelRow('olevelTable1')"><i class="bi bi-plus-circle"></i> Add Subject</button>
            <small class="text-muted" id="olevelTable1_count">0/9 Subjects</small>
        </div>
    </div>

    <div class="form-check form-switch my-4 ms-2">
        <input class="form-check-input" type="checkbox" id="ssce2Toggle" onclick="toggleSSCE2()" <?php echo !empty($data['ssce2_school']) ? 'checked' : ''; ?>>
        <label class="form-check-label fw-bold text-secondary" for="ssce2Toggle"> Combine a 2nd Sitting? (Optional)</label>
    </div>

    <div id="ssce2Container" class="<?php echo !empty($data['ssce2_school']) ? '' : 'd-none'; ?> p-3 border rounded bg-white">
        <h5 class="section-header text-secondary mb-3">SSCE 2 O'Level Results</h5>
        <div class="row g-3 mb-3">
            <div class="col-md-12"><label class="form-label">Name of School</label><input type="text" class="form-control ssce2-field" name="ssce2_school" value="<?php echo $data['ssce2_school'] ?? ''; ?>"></div>
            <div class="col-md-3">
                <label class="form-label">Exam Number</label>
                <input type="text" class="form-control ssce2-field" name="ssce2_exam_number" value="<?php echo $data['ssce2_exam_number'] ?? ''; ?>" placeholder="e.g. 4123456789">
            </div>
            <div class="col-md-3"><label class="form-label">Exam Year</label><input type="number" class="form-control ssce2-field year-input" name="ssce2_year" value="<?php echo $data['ssce2_year'] ?? ''; ?>" placeholder="YYYY"></div>
            <div class="col-md-6">
                <label class="form-label">Result Type</label>
                <select class="form-select ssce2-field" name="ssce2_type" onchange="toggleOtherInput(this, 'otherType2')">
                    <option value="">Select...</option>
                    <option value="WAEC" <?php echo ($data['ssce2_type'] ?? '') == 'WAEC' ? 'selected' : ''; ?>>WAEC</option>
                    <option value="NECO" <?php echo ($data['ssce2_type'] ?? '') == 'NECO' ? 'selected' : ''; ?>>NECO</option>
                    <option value="NABTEB" <?php echo ($data['ssce2_type'] ?? '') == 'NABTEB' ? 'selected' : ''; ?>>NABTEB</option>
                    <option value="GCE" <?php echo ($data['ssce2_type'] ?? '') == 'GCE' ? 'selected' : ''; ?>>GCE</option>
                    <option value="Others" <?php echo ($data['ssce2_type'] ?? '') == 'Others' ? 'selected' : ''; ?>>Others</option>
                </select>
                <input type="text" id="otherType2" name="ssce2_type_other" class="form-control mt-2 <?php echo ($data['ssce2_type'] ?? '') == 'Others' ? '' : 'd-none'; ?>" value="<?php echo $data['ssce2_type_other'] ?? ''; ?>">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-sm" id="olevelTable2">
                <thead class="table-secondary"><tr><th>S/N</th><th>Subject</th><th>Grade</th><th></th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center">
            <button type="button" class="btn btn-sm btn-secondary" onclick="addOLevelRow('olevelTable2')">Add Subject</button>
            <small class="text-muted" id="olevelTable2_count">0/9 Subjects</small>
        </div>
    </div>
</div>

<script src="asset/subjects.js"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
    // --- LOAD SAVED DATA ---
    const sessionData = <?php echo json_encode($data); ?>;

    if (sessionData.ssce1_subjects && sessionData.ssce1_subjects.length > 0) {
        rehydrateTable('olevelTable1', 'ssce1', sessionData.ssce1_subjects, sessionData.ssce1_grades);
    } else {
        addOLevelRow('olevelTable1'); 
    }

    if (sessionData.ssce2_subjects && sessionData.ssce2_subjects.length > 0) {
        rehydrateTable('olevelTable2', 'ssce2', sessionData.ssce2_subjects, sessionData.ssce2_grades);
        toggleSSCE2(); 
    } else {
        addOLevelRow('olevelTable2');
    }

    initValidations();
    updateCounter('olevelTable1');
    updateCounter('olevelTable2');
    
    checkCGPALimit(); 
});

const scaleSelect = document.getElementById('gradeScaleSelect');
const cgpaInput = document.getElementById('cgpaInput');
const cgpaFeedback = document.getElementById('cgpaFeedback');
const cgpaHelper = document.getElementById('cgpaHelper');

function checkCGPALimit() {
    const scale = parseFloat(scaleSelect.value) || 5.0; 
    const cgpa = parseFloat(cgpaInput.value);
    cgpaHelper.innerText = `Max ${scale.toFixed(2)}`;
    
    if (!isNaN(cgpa) && cgpa > scale) {
        cgpaInput.setCustomValidity(`CGPA cannot exceed ${scale}`);
        cgpaInput.classList.add('is-invalid');
        cgpaFeedback.innerText = `CGPA cannot be greater than the selected Grade Scale (${scale}).`;
    } else {
        cgpaInput.setCustomValidity("");
        cgpaInput.classList.remove('is-invalid');
    }
}

if(scaleSelect && cgpaInput) {
    scaleSelect.addEventListener('change', checkCGPALimit);
    cgpaInput.addEventListener('input', checkCGPALimit);
}


function rehydrateTable(tableId, prefix, subjectsArray, gradesArray) {
    const tableBody = document.getElementById(tableId).querySelector('tbody');
    tableBody.innerHTML = '';

    subjectsArray.forEach((subject, index) => {
        addOLevelRow(tableId);
        const rows = tableBody.querySelectorAll('tr');
        const lastRow = rows[rows.length - 1];
        const subjectSelect = lastRow.querySelector(`select[name="${prefix}_subjects[]"]`);
        const otherInput = lastRow.querySelector(`input[name="${prefix}_subject_others[]"]`);
        const isCustomSubject = typeof subjects === 'undefined' || !subjects.includes(subject);

        subjectSelect.value = isCustomSubject ? 'Others' : subject;
        if (otherInput) {
            otherInput.value = isCustomSubject ? subject : '';
        }
        syncOtherSubjectField(lastRow);
        lastRow.querySelector(`select[name="${prefix}_grades[]"]`).value = gradesArray[index];
    });
}

function addOLevelRow(tableId) {
    const tableBody = document.getElementById(tableId).getElementsByTagName('tbody')[0];
    const rowCount = tableBody.rows.length;

    if (rowCount >= 9) {
        alert("You cannot add more than 9 subjects.");
        return;
    }

    const newRow = tableBody.insertRow();
    const prefix = (tableId === 'olevelTable1') ? 'ssce1' : 'ssce2';
    const isRequired = (tableId === 'olevelTable1' || document.getElementById("ssce2Toggle").checked) ? 'required' : '';

    let subjectOptions = '<option value="">Subject...</option>';
    if (typeof subjects !== 'undefined') {
        subjects.forEach(s => { subjectOptions += `<option value="${s}">${s}</option>`; });
    }
    subjectOptions += '<option value="Others">Others (Specify)</option>';

    newRow.innerHTML = `
        <td class="sn text-center align-middle">${rowCount + 1}</td>
        <td>
            <select class="form-select form-select-sm subject-select"
                    name="${prefix}_subjects[]"
                    onchange="handleSubjectChange(this, '${tableId}')"
                    ${isRequired}>
                ${subjectOptions}
            </select>
            <input type="text"
                   class="form-control form-control-sm mt-2 subject-other-input d-none"
                   name="${prefix}_subject_others[]"
                   placeholder="Type subject name"
                   onblur="checkDuplicateSubjects(this, '${tableId}')">
        </td>
        <td>
            <select class="form-select form-select-sm" name="${prefix}_grades[]" ${isRequired}>
                <option value="">Grade...</option>
                ${['A1','B2','B3','C4','C5','C6','D7','E8','F9'].map(g => `<option value="${g}">${g}</option>`).join('')}
            </select>
        </td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this, '${tableId}')"><i class="bi bi-trash"></i></button></td>
    `;

    updateCounter(tableId);
}

function removeRow(btn, tableId) {
    const tableBody = document.getElementById(tableId).getElementsByTagName('tbody')[0];
    if (tableBody.rows.length > 1) {
        btn.closest('tr').remove();
        Array.from(tableBody.rows).forEach((row, index) => { row.querySelector('.sn').innerText = index + 1; });
        updateCounter(tableId);
    }
}

function getResolvedSubjectValue(row) {
    if (!row) return '';

    const select = row.querySelector('.subject-select');
    if (!select) return '';

    if (select.value === 'Others') {
        const otherInput = row.querySelector('.subject-other-input');
        return (otherInput?.value || '').trim();
    }

    return select.value.trim();
}

function syncOtherSubjectField(row) {
    const select = row.querySelector('.subject-select');
    const otherInput = row.querySelector('.subject-other-input');
    if (!select || !otherInput) return;

    const isOther = select.value === 'Others';
    otherInput.classList.toggle('d-none', !isOther);
    otherInput.required = isOther && select.hasAttribute('required');

    if (!isOther) {
        otherInput.value = '';
    }
}

function checkDuplicateSubjects(control, tableId) {
    const row = control.closest('tr');
    const selectedValue = getResolvedSubjectValue(row);
    if (!selectedValue) return;

    const tableRows = document.querySelectorAll(`#${tableId} tbody tr`);
    let isDuplicate = false;

    tableRows.forEach(otherRow => {
        if (otherRow !== row && getResolvedSubjectValue(otherRow).toLowerCase() === selectedValue.toLowerCase()) {
            isDuplicate = true;
        }
    });

    if (isDuplicate) {
        alert(`Warning: The subject "${selectedValue}" has already been selected for this sitting.`);
        control.value = '';
        syncOtherSubjectField(row);
    }
}

function handleSubjectChange(selectElement, tableId) {
    const row = selectElement.closest('tr');
    syncOtherSubjectField(row);
    checkDuplicateSubjects(selectElement, tableId);
}

function updateCounter(tableId) {
    const count = document.getElementById(tableId).getElementsByTagName('tbody')[0].rows.length;
    const counterEl = document.getElementById(`${tableId}_count`);
    if(counterEl) counterEl.innerText = `${count}/9 Subjects`;
}

function toggleSSCE2() {
    const isChecked = document.getElementById("ssce2Toggle").checked;
    const container = document.getElementById("ssce2Container");
    container.classList.toggle("d-none", !isChecked);
    const fields = container.querySelectorAll(".ssce2-field, .subject-select");
    fields.forEach(f => {
        if (isChecked) f.setAttribute("required", "required");
        else f.removeAttribute("required");
    });

    container.querySelectorAll('tbody tr').forEach(row => syncOtherSubjectField(row));
}

function toggleOtherInput(selectEl, inputId) {
    const otherInput = document.getElementById(inputId);
    if (selectEl.value === 'Others') {
        otherInput.classList.remove('d-none');
        otherInput.setAttribute('required', 'required');
    } else {
        otherInput.classList.add('d-none');
        otherInput.removeAttribute('required');
    }
}

function initValidations() {
    const currentYear = new Date().getFullYear();
    document.querySelectorAll(".year-input").forEach(input => {
        input.addEventListener("input", function() {
            const year = parseInt(this.value);
            const isValid = this.value.length === 4 && year >= 1950 && year <= currentYear;
            this.classList.toggle("is-invalid", !isValid);
            this.classList.toggle("is-valid", isValid);
        });
    });
}
</script>
