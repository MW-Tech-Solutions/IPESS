<?php $data = $_SESSION['form_data']['step_2'] ?? []; ?>
<h5 class="mb-4 text-primary">Programme Selection</h5>
<div class="row g-3">
    <div class="col-md-12">
        <label class="form-label">Faculty</label>
        <select class="form-select" name="faculty" required>
            <option value="">Select Faculty...</option>
            <option value="Sciences" <?php echo ($data['faculty'] ?? '') == 'Sciences' ? 'selected' : ''; ?>>Faculty of Sciences</option>
            <option value="Arts" <?php echo ($data['faculty'] ?? '') == 'Arts' ? 'selected' : ''; ?>>Faculty of Arts</option>
        </select>
    </div>

    <div class="col-md-12">
        <label class="form-label">Department</label>
        <select class="form-select" name="department" required>
            <option value="">Select Department...</option>
            <option value="Computer Science" <?php echo ($data['department'] ?? '') == 'Computer Science' ? 'selected' : ''; ?>>Computer Science</option>
            <option value="History" <?php echo ($data['department'] ?? '') == 'History' ? 'selected' : ''; ?>>History</option>
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Degree in View</label>
        <select class="form-select" name="degree_type" required>
            <option value="PGD" <?php echo ($data['degree_type'] ?? '') == 'PGD' ? 'selected' : ''; ?>>PGD</option>
            <option value="MSc" <?php echo ($data['degree_type'] ?? '') == 'MSc' ? 'selected' : ''; ?>>M.Sc (Academic)</option>
            <option value="MBA" <?php echo ($data['degree_type'] ?? '') == 'MBA' ? 'selected' : ''; ?>>MBA (Professional)</option>
            <option value="PhD" <?php echo ($data['degree_type'] ?? '') == 'PhD' ? 'selected' : ''; ?>>Ph.D</option>
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Mode of Study</label>
        <div class="d-flex gap-3 mt-2">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="mode" value="Full Time" checked>
                <label class="form-check-label">Full Time</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="mode" value="Part Time">
                <label class="form-check-label">Part Time</label>
            </div>
        </div>
    </div>
</div>

























<!-- <?php $data = $_SESSION['form_data']['step_2'] ?? []; ?>
<h5 class="mb-4 text-primary">Programme Selection</h5>
<div class="row g-3">
    <div class="col-md-12">
        <label class="form-label">Faculty</label>
        <select class="form-select" name="faculty" id="facultySelect" required>
            <option value="">Select Faculty...</option>
        </select>
    </div>

    <div class="col-md-12">
        <label class="form-label">Department</label>
        <select class="form-select" name="department" id="deptSelect" required disabled>
            <option value="">Select Department...</option>
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Degree in View</label>
        <select class="form-select" name="degree_type" id="degreeSelect" required disabled>
            <option value="">Select Degree Type...</option>
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Course of Study</label>
        <select class="form-select" name="course" id="courseSelect" required disabled>
            <option value="">Select Course...</option>
        </select>
    </div>

    <div class="col-md-12">
        <label class="form-label">Mode of Study</label>
        <div class="d-flex gap-3 mt-2">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="mode" value="Full Time" checked>
                <label class="form-check-label">Full Time</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="mode" value="Part Time">
                <label class="form-check-label">Part Time</label>
            </div>
        </div>
    </div>
</div>

<script src="../asset/pgcourses.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const facultySel = document.getElementById('facultySelect');
    const deptSel = document.getElementById('deptSelect');
    const degreeSel = document.getElementById('degreeSelect');
    const courseSel = document.getElementById('courseSelect');

    // 1. Initialize Faculty Dropdown
    for (let faculty in pgcourses) {
        facultySel.options[facultySel.options.length] = new Option(faculty, faculty);
    }

    // 2. Handle Faculty Selection
    facultySel.onchange = function() {
        deptSel.disabled = (this.value === "");
        deptSel.length = 1; // Reset to "Select..."
        degreeSel.length = 1;
        courseSel.length = 1;
        degreeSel.disabled = true;
        courseSel.disabled = true;
        
        if (this.value !== "") {
            const depts = pgcourses[this.value];
            for (let dept in depts) {
                deptSel.options[deptSel.options.length] = new Option(dept, dept);
            }
        }
    };

    // 3. Handle Department Selection
    deptSel.onchange = function() {
        degreeSel.disabled = (this.value === "");
        degreeSel.length = 1;
        courseSel.length = 1;
        courseSel.disabled = true;

        if (this.value !== "") {
            const degrees = pgcourses[facultySel.value][this.value];
            for (let type in degrees) {
                degreeSel.options[degreeSel.options.length] = new Option(type, type);
            }
        }
    };

    // 4. Handle Degree Selection
    degreeSel.onchange = function() {
        courseSel.disabled = (this.value === "");
        courseSel.length = 1;

        if (this.value !== "") {
            const courses = pgcourses[facultySel.value][deptSel.value][this.value];
            courses.forEach(course => {
                courseSel.options[courseSel.options.length] = new Option(course, course);
            });
        }
    };
});
</script> -->








