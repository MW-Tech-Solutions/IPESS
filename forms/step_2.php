<?php 

require_once 'db.php'; 

$data = $_SESSION['form_data']['step_2'] ?? [];

try {
    $stmt = $pdo->query("SELECT faculty_id, faculty_name FROM faculties ORDER BY faculty_name ASC");
    $faculties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmtMode = $pdo->query("SELECT mode_id, mode_name FROM study_modes ORDER BY mode_name ASC");
    $study_modes = $stmtMode->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $faculties = [];
    $study_modes = [];
}
?>

<h5 class="mb-4 text-primary">Programme Selection</h5>

<div class="row g-3">
    <div class="col-md-12">
        <label class="form-label">Faculty</label>
        <select class="form-select" name="faculty" id="facultySelect" required>
            <option value="">Select Faculty...</option>
            <?php foreach ($faculties as $faculty): ?>
                <option value="<?php echo $faculty['faculty_id']; ?>" 
                    <?php echo (isset($data['faculty']) && $data['faculty'] == $faculty['faculty_id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($faculty['faculty_name']); ?>
                </option>
            <?php endforeach; ?>
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
        <?php if (!empty($study_modes)): ?>
            <?php foreach ($study_modes as $m): ?>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="mode" 
                           value="<?php echo $m['mode_id']; ?>" 
                           id="mode_<?php echo $m['mode_id']; ?>"
                        <?php 
                            // Compare against saved session data
                            if (isset($data['mode']) && $data['mode'] == $m['mode_id']) {
                                echo 'checked';
                            } elseif (!isset($data['mode']) && $m['mode_name'] == 'Full Time') {
                                echo 'checked'; // Default selection
                            }
                        ?>>
                    <label class="form-check-label" for="mode_<?php echo $m['mode_id']; ?>">
                        <?php echo htmlspecialchars($m['mode_name']); ?>
                    </label>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-danger small">Error: No study modes found in database.</p>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const facultySel = document.getElementById('facultySelect');
    const deptSel = document.getElementById('deptSelect');
    const degreeSel = document.getElementById('degreeSelect');
    const courseSel = document.getElementById('courseSelect');
    const savedData = <?php echo json_encode($data); ?>;

    // Helper: Reset a dropdown
    function resetSelect(selectElement, defaultText) {
        selectElement.innerHTML = `<option value="">${defaultText}</option>`;
        selectElement.disabled = true;
    }

    async function fetchData(params) {
        
        const url = `helpers/get_data.php?${new URLSearchParams(params)}`;

        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error(`Server returned ${response.status}`);
            
            const result = await response.json();
            
            if (result.error) {
                console.error('API Error:', result.error);
                return [];
            }
            return result;
        } catch (error) {
            console.error('Fetch error:', error);
            return [];
        }
    }

   
    function populateSelect(selectElement, items, idKey, nameKey, selectedValue = null) {
        const defaultText = selectElement.options[0].text;
        selectElement.innerHTML = `<option value="">${defaultText}</option>`;

        if (items && items.length > 0) {
            selectElement.disabled = false;
            items.forEach(item => {
                const option = new Option(item[nameKey], item[idKey]);
                if (selectedValue && item[idKey] == selectedValue) {
                    option.selected = true;
                }
                selectElement.add(option);
            });
        } else {
            selectElement.innerHTML = `<option value="">No items available</option>`;
            selectElement.disabled = true;
        }
    }

    facultySel.addEventListener('change', async function() {
        const facultyId = this.value;
        
        resetSelect(deptSel, "Select Department...");
        resetSelect(degreeSel, "Select Department First...");
        resetSelect(courseSel, "Select Degree First...");
        
        if (facultyId) {
            deptSel.innerHTML = '<option>Loading...</option>';
            const depts = await fetchData({ action: 'get_depts', faculty_id: facultyId });
            resetSelect(deptSel, "Select Department...");
            populateSelect(deptSel, depts, 'dept_id', 'dept_name');
        }
    });

    deptSel.addEventListener('change', async function() {
        const deptId = this.value;
        
        resetSelect(degreeSel, "Select Degree...");
        resetSelect(courseSel, "Select Degree First...");
        
        if (deptId) {
            degreeSel.innerHTML = '<option>Loading...</option>';
            const degrees = await fetchData({ action: 'get_degrees', dept_id: deptId });
            resetSelect(degreeSel, "Select Degree...");
            populateSelect(degreeSel, degrees, 'degree_id', 'degree_name');
        }
    });

    degreeSel.addEventListener('change', async function() {
        const degreeId = this.value;
        const deptId = deptSel.value;
        
        resetSelect(courseSel, "Select Course...");
        
        if (degreeId && deptId) {
            courseSel.innerHTML = '<option>Loading...</option>';
            const courses = await fetchData({ 
                action: 'get_courses', 
                dept_id: deptId, 
                degree_id: degreeId 
            });
            resetSelect(courseSel, "Select Course...");
            populateSelect(courseSel, courses, 'course_id', 'course_title');
        }
    });

    async function initSavedData() {
        if (savedData.faculty) {
            const depts = await fetchData({ action: 'get_depts', faculty_id: savedData.faculty });
            populateSelect(deptSel, depts, 'dept_id', 'dept_name', savedData.department);
            deptSel.disabled = false;

            if (savedData.department) {
                const degrees = await fetchData({ action: 'get_degrees', dept_id: savedData.department });
                populateSelect(degreeSel, degrees, 'degree_id', 'degree_name', savedData.degree_type);
                degreeSel.disabled = false;

                if (savedData.degree_type) {
                    const courses = await fetchData({ 
                        action: 'get_courses', 
                        dept_id: savedData.department, 
                        degree_id: savedData.degree_type 
                    });
                    populateSelect(courseSel, courses, 'course_id', 'course_title', savedData.course);
                    courseSel.disabled = false;
                }
            }
        }
    }
    
    initSavedData();
});
</script>
