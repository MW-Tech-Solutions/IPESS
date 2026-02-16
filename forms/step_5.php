<?php $data = $_SESSION['form_data']['step_5'] ?? []; ?>
<h5 class="section-header text-primary mb-4">Employment & Experience</h5>

<div class="mb-4">
    <label class="form-label fw-bold">Employment Status</label>
    <select class="form-select" name="emp_status" id="empStatusSelect" required>
        <option value="" selected disabled>Choose Status...</option>
        <option value="Employed" <?php echo ($data['emp_status'] ?? '') == 'Employed' ? 'selected' : ''; ?>>Employed</option>
        <option value="Unemployed" <?php echo ($data['emp_status'] ?? '') == 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
        <option value="Student" <?php echo ($data['emp_status'] ?? '') == 'Student' ? 'selected' : ''; ?>>Student</option>
    </select>
</div>

<div id="employmentDetails" class="<?php echo ($data['emp_status'] ?? '') == 'Employed' ? '' : 'd-none'; ?>">
    <div class="card p-4 mb-3 border-0 shadow-sm bg-light">
        <h6 class="fw-bold mb-3"><i class="bi bi-briefcase me-2"></i>Most Recent Employment</h6>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Employer / Company Name</label>
                <input type="text" class="form-control work-field" name="employer" value="<?php echo $data['employer'] ?? ''; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Job Title / Role</label>
                <input type="text" class="form-control work-field" name="job_title" value="<?php echo $data['job_title'] ?? ''; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Years of Experience</label>
                <input type="number" 
                       class="form-control work-field" 
                       name="years_experience" 
                       min="0" 
                       max="50" 
                       placeholder="e.g. 5" 
                       value="<?php echo $data['years_experience'] ?? ''; ?>">
                <div class="invalid-feedback">Please enter a valid number of years.</div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const empStatusSelect = document.getElementById("empStatusSelect");
    const employmentDetails = document.getElementById("employmentDetails");
    const workFields = document.querySelectorAll(".work-field");

    function toggleEmploymentFields() {
        const status = empStatusSelect.value;
        
        if (status === "Employed") {
            employmentDetails.classList.remove("d-none");
            workFields.forEach(field => {
                field.setAttribute("required", "required");
            });
        } else {
            employmentDetails.classList.add("d-none");
            workFields.forEach(field => {
                field.value = "";
                field.removeAttribute("required");
                field.classList.remove("is-invalid", "is-valid");
            });
        }
    }

    empStatusSelect.addEventListener("change", toggleEmploymentFields);

    toggleEmploymentFields();
    
    const yearsInput = document.querySelector('input[name="years_experience"]');
    yearsInput.addEventListener("input", function() {
        if (this.value >= 0 && this.value <= 50) {
            this.classList.remove("is-invalid");
            this.classList.add("is-valid");
        } else {
            this.classList.remove("is-valid");
            this.classList.add("is-invalid");
        }
    });
});
</script>