<?php
$data = $_SESSION['form_data']['step_1'] ?? [];
?>
<h5 class="section-header">Personal Information</h5>
<div class="row g-3">
 
    <div class="col-12 mb-3">
    <div class="d-flex align-items-center justify-content-between p-2 border rounded bg-white">
        <div class="d-flex align-items-center">
            <i class="bi bi-camera text-muted me-3 ms-2"></i>
            <div>
                <span class="fw-bold d-block" style="font-size: 0.9rem;">Passport Photograph</span>
                <span id="fileNameDisplay" class="text-muted small">JPG, PNG (Max 250KB)</span>
            </div>
        </div>

        <div>
            <input type="file" class="d-none" id="passportAjaxInput" name="passport_file" accept=".jpg,.jpeg,.png">
            
            <button type="button" class="btn btn-sm btn-light border" onclick="document.getElementById('passportAjaxInput').click();">
                Choose File
            </button>
            <button type="button" class="btn btn-sm btn-primary d-none" id="uploadBtn">
                Upload
            </button>
            
            <div id="uploadSpinner" class="spinner-border text-primary spinner-border-sm d-none" role="status"></div>
        </div>
    </div>
    <div id="uploadFeedback" class="small mt-1 ms-1"></div>
</div>
    
    <div class="col-md-4">
        <label class="form-label">Surname</label>
        <input type="text" class="form-control" name="surname" value="<?php echo $data['surname'] ?? ''; ?>" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">First Name</label>
        <input type="text" class="form-control" name="firstName" value="<?php echo $data['firstName'] ?? ''; ?>" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Other Name</label>
        <input type="text" class="form-control" name="otherName" value="<?php echo $data['otherName'] ?? ''; ?>">
    </div>

    <div class="col-md-4">
        <label class="form-label">Date of Birth</label>
        <input type="date" class="form-control" name="dob" value="<?php echo $data['dob'] ?? ''; ?>" required>
    </div>
    
    <div class="col-md-4">
        <label class="form-label">Sex</label>
        <select class="form-select" name="sex" required>
            <option value="" selected disabled>Select...</option>
            <option value="Male" <?php echo ($data['sex'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
            <option value="Female" <?php echo ($data['sex'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
            <option value="Other" <?php echo ($data['sex'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label">Nationality</label>
        <select class="form-select" id="nationalitySelect" name="nationality" required>
            <option value="Nigerian" <?php echo ($data['nationality'] ?? 'Nigerian') == 'Nigerian' ? 'selected' : ''; ?>>Nigerian</option>
            <option value="Non-Nigerian" <?php echo ($data['nationality'] ?? '') == 'Non-Nigerian' ? 'selected' : ''; ?>>Non-Nigerian</option>
        </select>
    </div>
    
    <div class="col-md-4">
        <label class="form-label">State of Origin</label>
        <select class="form-select" id="stateSelect" name="state" required>
            <option value="" selected disabled>Select State...</option>
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label">Local Govt. Area (LGA)</label>
        <select class="form-select" id="lgaSelect" name="lga" required>
            <option value="" selected disabled>Select State First</option>
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label">Phone Number</label>
        <input type="tel" class="form-control" id="phoneInput" name="phone" placeholder="e.g. 080..." value="<?php echo $data['phone'] ?? ''; ?>" required>
        <div class="invalid-feedback">Please enter a valid phone number.</div>
    </div>

    <div class="col-md-12">
        <label class="form-label">Email Address</label>
        <input type="email" class="form-control" id="emailInput" name="email" placeholder="name@example.com" value="<?php echo $data['email'] ?? ($_SESSION['user_email'] ?? ''); ?>" readonly required>
    </div>

    <div class="col-12">
        <label class="form-label">Residential Address</label>
        <textarea class="form-control" name="address" rows="2" required><?php echo $data['address'] ?? ''; ?></textarea>
    </div>
</div>

<script src="asset/states.js"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
    
    const passportInput = document.getElementById('passportAjaxInput');
    const uploadBtn = document.getElementById('uploadBtn');
    const spinner = document.getElementById('uploadSpinner');
    const feedback = document.getElementById('uploadFeedback');

    passportInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            uploadBtn.classList.remove('d-none'); 
            feedback.innerHTML = ''; 
        } else {
            uploadBtn.classList.add('d-none');
        }
    });

    uploadBtn.addEventListener('click', function() {
        const file = passportInput.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('passport_file', file);

        
        spinner.classList.remove('d-none');
        uploadBtn.classList.add('d-none');
        passportInput.disabled = true;
        feedback.innerText = 'Uploading...';

        fetch('includes/upload_passport.php', { 
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            
            spinner.classList.add('d-none');
            passportInput.disabled = false;

            if (data.status === 'success') {
                feedback.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Upload Successful!</span>';
                
                
                const newSrc = data.url + '?t=' + new Date().getTime();
                
                const dashboardImages = document.querySelectorAll('.passport-photo');
                dashboardImages.forEach(img => {
                    img.src = newSrc;
                });
                
                passportInput.value = ''; 
            } else {
                feedback.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle-fill"></i> ' + data.message + '</span>';
                uploadBtn.classList.remove('d-none');
            }
        })
        .catch(error => {
            console.error(error);
            spinner.classList.add('d-none');
            passportInput.disabled = false;
            uploadBtn.classList.remove('d-none');
            feedback.innerHTML = '<span class="text-danger">Error connecting to server.</span>';
        });
    });

    const nationalitySelect = document.getElementById("nationalitySelect");
    const stateSelect = document.getElementById("stateSelect");
    const lgaSelect = document.getElementById("lgaSelect");
    const savedState = "<?php echo $data['state'] ?? ''; ?>";
    const savedLga = "<?php echo $data['lga'] ?? ''; ?>";

    function handleNationality() {
        if (nationalitySelect.value === "Non-Nigerian") {
            stateSelect.disabled = true;
            lgaSelect.disabled = true;
            stateSelect.value = "";
            lgaSelect.innerHTML = '<option value="" selected disabled>Not Applicable</option>';
            stateSelect.removeAttribute('required');
            lgaSelect.removeAttribute('required');
        } else {
            stateSelect.disabled = false;
            lgaSelect.disabled = false;
            stateSelect.setAttribute('required', 'required');
            lgaSelect.setAttribute('required', 'required');
            if (stateSelect.options.length <= 1) populateStates();
        }
    }

    function populateStates() {
        if (typeof stateLgas !== 'undefined') {
            stateSelect.innerHTML = '<option value="" selected disabled>Select State...</option>';
            Object.keys(stateLgas).sort().forEach(state => {
                const option = document.createElement("option");
                option.value = state;
                option.textContent = state === "FCT" ? "Abuja (FCT)" : state;
                if(state === savedState) option.selected = true;
                stateSelect.appendChild(option);
            });
            if(savedState) updateLGAs(savedState, savedLga);
        }
    }

    function updateLGAs(selectedState, selectedLga) {
        lgaSelect.innerHTML = '<option value="" selected disabled>Select LGA...</option>';
        if (stateLgas && stateLgas[selectedState]) {
            stateLgas[selectedState].forEach(lga => {
                const option = document.createElement("option");
                option.value = lga;
                option.textContent = lga;
                if(lga === selectedLga) option.selected = true;
                lgaSelect.appendChild(option);
            });
        }
    }

    nationalitySelect.addEventListener("change", handleNationality);
    stateSelect.addEventListener("change", function () {
        updateLGAs(this.value, "");
    });

    // Initialize
    populateStates();
    handleNationality(); 
});
</script>
