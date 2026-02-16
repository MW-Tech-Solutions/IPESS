<?php 
$data = $_SESSION['form_data']['step_2'] ?? []; 
?>

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
            <option value="">Select Faculty First...</option>
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Degree in View</label>
        <select class="form-select" name="degree_type" id="degreeSelect" required disabled>
            <option value="">Select Department First...</option>
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Course of Study</label>
        <select class="form-select" name="course" id="courseSelect" required disabled>
            <option value="">Select Degree First...</option>
        </select>
    </div>

    <div class="col-md-12">
        <label class="form-label">Mode of Study</label>
        <div class="d-flex gap-3 mt-2">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="mode" value="Full Time" 
                    <?php echo ($data['mode'] ?? 'Full Time') == 'Full Time' ? 'checked' : ''; ?>>
                <label class="form-check-label">Full Time</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="mode" value="Part Time"
                    <?php echo ($data['mode'] ?? '') == 'Part Time' ? 'checked' : ''; ?>>
                <label class="form-check-label">Part Time</label>
            </div>
        </div>
    </div>
</div>

<script src="./asset/pgcourses.js"></script> 

<script>
document.addEventListener('DOMContentLoaded', function() {
    const facultySel = document.getElementById('facultySelect');
    const deptSel = document.getElementById('deptSelect');
    const degreeSel = document.getElementById('degreeSelect');
    const courseSel = document.getElementById('courseSelect');

    const savedData = <?php echo json_encode($data); ?>;

    function resetSelect(selectElement, defaultText, disable = true) {
        selectElement.innerHTML = `<option value="">${defaultText}</option>`;
        selectElement.disabled = disable;
    }

    function populateSelect(selectElement, items, selectedValue = null) {
        selectElement.disabled = false;
        items.forEach(item => {
            const option = new Option(item, item);
            if (selectedValue && item === selectedValue) {
                option.selected = true;
            }
            selectElement.add(option);
        });
    }


    if (typeof pgcourses !== 'undefined') {
        const faculties = Object.keys(pgcourses);
        populateSelect(facultySel, faculties, savedData.faculty);

        if (savedData.faculty) {
            loadDepartments(savedData.faculty, savedData.department);
        }
    } else {
        console.error("pgcourses.js not loaded properly.");
    }


    facultySel.addEventListener('change', function() {
        const selectedFaculty = this.value;
        resetSelect(deptSel, "Select Department...");
        resetSelect(degreeSel, "Select Department First...");
        resetSelect(courseSel, "Select Degree First...");
        
        if (selectedFaculty) {
            loadDepartments(selectedFaculty);
        }
    });

    deptSel.addEventListener('change', function() {
        const selectedFaculty = facultySel.value;
        const selectedDept = this.value;
        
        resetSelect(degreeSel, "Select Degree Type...");
        resetSelect(courseSel, "Select Degree First...");

        if (selectedDept) {
            loadDegrees(selectedFaculty, selectedDept);
        }
    });

    degreeSel.addEventListener('change', function() {
        const selectedFaculty = facultySel.value;
        const selectedDept = deptSel.value;
        const selectedDegree = this.value;

        resetSelect(courseSel, "Select Course...");

        if (selectedDegree) {
            loadCourses(selectedFaculty, selectedDept, selectedDegree);
        }
    });


    function loadDepartments(faculty, preselectedDept = null) {
        if (!pgcourses[faculty]) return;
        
        const departments = Object.keys(pgcourses[faculty]);
        populateSelect(deptSel, departments, preselectedDept);

        if (preselectedDept) {
            loadDegrees(faculty, preselectedDept, savedData.degree_type);
        }
    }

    function loadDegrees(faculty, dept, preselectedDegree = null) {
        if (!pgcourses[faculty][dept]) return;

        const degrees = Object.keys(pgcourses[faculty][dept]);
        populateSelect(degreeSel, degrees, preselectedDegree);

        if (preselectedDegree) {
            loadCourses(faculty, dept, preselectedDegree, savedData.course);
        }
    }

    function loadCourses(faculty, dept, degree, preselectedCourse = null) {
        if (!pgcourses[faculty][dept][degree]) return;

        const courses = pgcourses[faculty][dept][degree];
        populateSelect(courseSel, courses, preselectedCourse);
    }
});
</script>