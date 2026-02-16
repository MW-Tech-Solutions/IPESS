<?php 
// Initialize session if not already started
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
        <div class="col-md-6">
            <label class="form-label">Class of Degree / CGPA</label>
            <input type="text" 
                   class="form-control" 
                   id="cgpaInput" 
                   name="cgpa" 
                   placeholder="e.g. 4.50" 
                   value="<?php echo $data['cgpa'] ?? ''; ?>" 
                   required>
            <div class="form-text">Max 5.00 (e.g., 3.5 or 4.25).</div>
            <div class="invalid-feedback">Invalid CGPA. Use 0.00 to 5.00 format (max 2 decimals).</div>
        </div>
        <div class="col-md-6">
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
                <tbody>
                    </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-sm btn-primary" onclick="addOLevelRow('olevelTable1')"><i class="bi bi-plus-circle"></i> Add Subject</button>
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
                <tbody>
                    </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-sm btn-secondary" onclick="addOLevelRow('olevelTable2')">Add Subject</button>
    </div>
</div>

<script src="asset/subjects.js"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
    const sessionData = <?php echo json_encode($data); ?>;

    // 1. Rehydrate Sitting 1
    if (sessionData.ssce1_subjects && sessionData.ssce1_subjects.length > 0) {
        rehydrateTable('olevelTable1', 'ssce1', sessionData.ssce1_subjects, sessionData.ssce1_grades);
    } else {
        addOLevelRow('olevelTable1'); // Default first row
    }

    // 2. Rehydrate Sitting 2
    if (sessionData.ssce2_subjects && sessionData.ssce2_subjects.length > 0) {
        rehydrateTable('olevelTable2', 'ssce2', sessionData.ssce2_subjects, sessionData.ssce2_grades);
        toggleSSCE2(); // Ensure fields are correctly required if data exists
    } else {
        addOLevelRow('olevelTable2'); // Default first row
    }

    // 3. Validation Logic
    initValidations();
});

function rehydrateTable(tableId, prefix, subjectsArray, gradesArray) {
    const tableBody = document.getElementById(tableId).querySelector('tbody');
    tableBody.innerHTML = ''; // Clear defaults

    subjectsArray.forEach((subject, index) => {
        addOLevelRow(tableId);
        const rows = tableBody.querySelectorAll('tr');
        const lastRow = rows[rows.length - 1];
        lastRow.querySelector(`select[name="${prefix}_subjects[]"]`).value = subject;
        lastRow.querySelector(`select[name="${prefix}_grades[]"]`).value = gradesArray[index];
    });
}

function addOLevelRow(tableId) {
    const tableBody = document.getElementById(tableId).getElementsByTagName('tbody')[0];
    const newRow = tableBody.insertRow();
    const rowCount = tableBody.rows.length;
    const prefix = (tableId === 'olevelTable1') ? 'ssce1' : 'ssce2';
    const isRequired = (tableId === 'olevelTable1' || document.getElementById("ssce2Toggle").checked) ? 'required' : '';

    let subjectOptions = '<option value="">Subject...</option>';
    if (typeof subjects !== 'undefined') {
        subjects.forEach(s => { subjectOptions += `<option value="${s}">${s}</option>`; });
    }

    newRow.innerHTML = `
        <td class="sn text-center align-middle">${rowCount}</td>
        <td><select class="form-select form-select-sm subject-select" name="${prefix}_subjects[]" ${isRequired}>${subjectOptions}</select></td>
        <td>
            <select class="form-select form-select-sm" name="${prefix}_grades[]" ${isRequired}>
                <option value="">Grade...</option>
                ${['A1','B2','B3','C4','C5','C6','D7','E8','F9'].map(g => `<option value="${g}">${g}</option>`).join('')}
            </select>
        </td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this, '${tableId}')"><i class="bi bi-trash"></i></button></td>
    `;
}

function removeRow(btn, tableId) {
    const tableBody = document.getElementById(tableId).getElementsByTagName('tbody')[0];
    if (tableBody.rows.length > 1) {
        btn.closest('tr').remove();
        Array.from(tableBody.rows).forEach((row, index) => { row.querySelector('.sn').innerText = index + 1; });
    }
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




<!-- <?php 
// Initialize session if not already started
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
        <div class="col-md-6">
            <label class="form-label">Class of Degree / CGPA</label>
            <input type="text" 
                   class="form-control" 
                   id="cgpaInput" 
                   name="cgpa" 
                   placeholder="e.g. 4.50" 
                   value="<?php echo $data['cgpa'] ?? ''; ?>" 
                   required>
            <div class="form-text">Max 5.00 (e.g., 3.5 or 4.25).</div>
            <div class="invalid-feedback">Invalid CGPA. Use 0.00 to 5.00 format (max 2 decimals).</div>
        </div>
        <div class="col-md-6">
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
                <input type="text" class="form-control" name="ssce1_exam_number" placeholder="e.g. 4123456789" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Exam Year</label>
                <input type="number" class="form-control year-input" name="ssce1_year" min="1950" max="<?php echo date('Y'); ?>" placeholder="YYYY" value="<?php echo $data['ssce1_year'] ?? ''; ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Result Type</label>
                <select class="form-select" name="ssce1_type" id="ssce1_type" onchange="toggleOtherInput(this, 'otherType1')" required>
                    <option value="">Select...</option>
                    <option value="WAEC" <?php echo ($data['ssce1_type'] ?? '') == 'WAEC' ? 'selected' : ''; ?>>WAEC</option>
                    <option value="NECO" <?php echo ($data['ssce1_type'] ?? '') == 'NECO' ? 'selected' : ''; ?>>NECO</option>
                    <option value="NABTEB" <?php echo ($data['ssce1_type'] ?? '') == 'NABTEB' ? 'selected' : ''; ?>>NABTEB</option>
                    <option value="Others" <?php echo ($data['ssce1_type'] ?? '') == 'Others' ? 'selected' : ''; ?>>Others (Specify)</option>
                </select>
                <input type="text" id="otherType1" name="ssce1_type_other" class="form-control mt-2 d-none" placeholder="Specify Result Type">
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
                <tbody>
                    <tr>
                        <td class="sn text-center align-middle">1</td>
                        <td><select class="form-select form-select-sm subject-select" name="ssce1_subjects[]" required><option value="">Subject...</option></select></td>
                        <td>
                            <select class="form-select form-select-sm" name="ssce1_grades[]" required>
                                <option value="">Grade...</option>
                                <option value="A1">A1</option><option value="B2">B2</option><option value="B3">B3</option>
                                <option value="C4">C4</option><option value="C5">C5</option><option value="C6">C6</option>
                                <option value="D7">D7</option><option value="E8">E8</option><option value="F9">F9</option>
                            </select>
                        </td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this, 'olevelTable1')"><i class="bi bi-trash"></i></button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-sm btn-primary" onclick="addOLevelRow('olevelTable1')"><i class="bi bi-plus-circle"></i> Add Subject</button>
    </div>

    <div class="form-check form-switch my-4 ms-2">
        <input class="form-check-input" type="checkbox" id="ssce2Toggle" onclick="toggleSSCE2()">
        <label class="form-check-label fw-bold text-secondary" for="ssce2Toggle"> Combine a 2nd Sitting? (Optional)</label>
    </div>

    <div id="ssce2Container" class="d-none p-3 border rounded bg-white">
        <h5 class="section-header text-secondary mb-3">SSCE 2 O'Level Results</h5>
        <div class="row g-3 mb-3">
            <div class="col-md-12"><label class="form-label">Name of School</label><input type="text" class="form-control ssce2-field" name="ssce2_school"></div>
            <div class="col-md-3">
    <label class="form-label">Exam Number</label>
    <input type="text" class="form-control" name="ssce2_exam_number" placeholder="e.g. 4123456789" required>
</div>
            <div class="col-md-3"><label class="form-label">Exam Year</label><input type="number" class="form-control ssce2-field year-input" name="ssce2_year" placeholder="YYYY"></div>
            <div class="col-md-3">
                <label class="form-label">Result Type</label>
                <select class="form-select ssce2-field" name="ssce2_type" onchange="toggleOtherInput(this, 'otherType2')">
                    <option value="">Select...</option><option value="WAEC">WAEC</option><option value="NECO">NECO</option><option value="Others">Others</option>
                </select>
                <input type="text" id="otherType2" name="ssce2_type_other" class="form-control mt-2 d-none">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-sm" id="olevelTable2">
                <thead class="table-secondary"><tr><th>S/N</th><th>Subject</th><th>Grade</th><th></th></tr></thead>
                <tbody>
                    <tr>
                        <td class="sn text-center align-middle">1</td>
                        <td><select class="form-select form-select-sm subject-select ssce2-field" name="ssce2_subjects[]"><option value="">Subject...</option></select></td>
                        <td><select class="form-select form-select-sm ssce2-field" name="ssce2_grades[]"><option value="">Grade...</option><option value="A1">A1</option><option value="B2">B2</option><option value="B3">B3</option><option value="C4">C4</option><option value="C5">C5</option><option value="C6">C6</option></select></td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this, 'olevelTable2')"><i class="bi bi-trash"></i></button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-sm btn-secondary" onclick="addOLevelRow('olevelTable2')">Add Subject</button>
    </div>
</div>

<script src="asset/subjects.js"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
    // 1. Initial Subject Population
    populateSubjects();

    // 2. CGPA Validation (Regex: 0-5.00, 1 or 2 decimals)
    const cgpaInput = document.getElementById("cgpaInput");
    if (cgpaInput) {
        cgpaInput.addEventListener("input", function() {
            const val = this.value;
            const formatValid = /^\d(\.\d{1,2})?$/.test(val);
            const numVal = parseFloat(val);

            if (formatValid && numVal >= 0 && numVal <= 5.00) {
                this.classList.remove("is-invalid");
                this.classList.add("is-valid");
            } else {
                this.classList.remove("is-valid");
                this.classList.add("is-invalid");
            }
        });
    }

    // 3. Year Validation (1950 - Current Year)
    const currentYear = new Date().getFullYear();
    document.querySelectorAll(".year-input").forEach(input => {
        input.addEventListener("input", function() {
            const year = parseInt(this.value);
            if (this.value.length === 4 && year >= 1950 && year <= currentYear) {
                this.classList.remove("is-invalid");
                this.classList.add("is-valid");
            } else {
                this.classList.remove("is-valid");
                this.classList.add("is-invalid");
            }
        });
    });
});

/**
 * Populates all subject dropdowns using the 'subjects' array from asset/subjects.js
 */
function populateSubjects() {
    const selects = document.querySelectorAll('.subject-select');
    if (typeof subjects !== 'undefined') {
        selects.forEach(select => {
            // Only populate if not already populated
            if (select.options.length <= 1) {
                subjects.forEach(subjectName => {
                    const opt = document.createElement("option");
                    opt.value = subjectName;
                    opt.textContent = subjectName;
                    select.appendChild(opt);
                });
            }
        });
    }
}

/**
 * Adds a new row to the O'Level table
 */
function addOLevelRow(tableId) {
    const tableBody = document.getElementById(tableId).getElementsByTagName('tbody')[0];
    const newRow = tableBody.insertRow();
    const rowCount = tableBody.rows.length;
    
    const prefix = (tableId === 'olevelTable1') ? 'ssce1' : 'ssce2';
    const isRequired = (tableId === 'olevelTable1' || document.getElementById("ssce2Toggle").checked) ? 'required' : '';

    // Generate Subject Options
    let subjectOptions = '<option value="">Subject...</option>';
    if (typeof subjects !== 'undefined') {
        subjects.forEach(s => { subjectOptions += `<option value="${s}">${s}</option>`; });
    }

    newRow.innerHTML = `
        <td class="sn text-center align-middle">${rowCount}</td>
        <td><select class="form-select form-select-sm subject-select" name="${prefix}_subjects[]" ${isRequired}>${subjectOptions}</select></td>
        <td>
            <select class="form-select form-select-sm" name="${prefix}_grades[]" ${isRequired}>
                <option value="">Grade...</option>
                <option value="A1">A1</option><option value="B2">B2</option><option value="B3">B3</option>
                <option value="C4">C4</option><option value="C5">C5</option><option value="C6">C6</option>
                <option value="D7">D7</option><option value="E8">E8</option><option value="F9">F9</option>
            </select>
        </td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this, '${tableId}')"><i class="bi bi-trash"></i></button></td>
    `;
}

/**
 * Removes a row and re-indexes the serial numbers
 */
function removeRow(btn, tableId) {
    const tableBody = document.getElementById(tableId).getElementsByTagName('tbody')[0];
    if (tableBody.rows.length > 1) {
        btn.closest('tr').remove();
        // Re-index S/N
        Array.from(tableBody.rows).forEach((row, index) => {
            row.querySelector('.sn').innerText = index + 1;
        });
    } else {
        alert("At least one subject is required.");
    }
}

/**
 * Toggles the visibility and 'required' state of the second sitting section
 */
function toggleSSCE2() {
    const isChecked = document.getElementById("ssce2Toggle").checked;
    const container = document.getElementById("ssce2Container");
    container.classList.toggle("d-none", !isChecked);
    
    // Toggle required attribute for fields inside
    const fields = container.querySelectorAll(".ssce2-field");
    fields.forEach(f => {
        if (isChecked) f.setAttribute("required", "required");
        else f.removeAttribute("required");
    });
}

/**
 * Shows/Hides text input for 'Others' option in Result Type
 */
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
</script> -->