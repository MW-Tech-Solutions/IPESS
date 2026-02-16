<?php
$data = $_SESSION['form_data']['step_1'] ?? [];
?>
<h5 class="section-header">Personal Information</h5>
<div class="row g-3">
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
        <input type="tel" 
               class="form-control" 
               id="phoneInput"
               name="phone" 
               placeholder="e.g. 080... or +234..." 
               value="<?php echo $data['phone'] ?? ''; ?>" 
               required>
        <div class="invalid-feedback">Please enter a valid phone number (e.g., 090... or +234...).</div>
    </div>

    <div class="col-md-12">
        <label class="form-label">Email Address</label>
        <input type="email" 
               class="form-control" 
               id="emailInput"
               name="email" 
               placeholder="name@example.com"
               value="<?php echo $data['email'] ?? ''; ?>" 
               required>
    </div>

    <div class="col-12">
        <label class="form-label">Residential Address</label>
        <textarea class="form-control" name="address" rows="2" required><?php echo $data['address'] ?? ''; ?></textarea>
    </div>
</div>

<script src="asset/states.js"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
    const nationalitySelect = document.getElementById("nationalitySelect");
    const stateSelect = document.getElementById("stateSelect");
    const lgaSelect = document.getElementById("lgaSelect");
    const emailInput = document.getElementById("emailInput");
    const phoneInput = document.getElementById("phoneInput");
    
    const savedState = "<?php echo $data['state'] ?? ''; ?>";
    const savedLga = "<?php echo $data['lga'] ?? ''; ?>";

    function validatePhone(phone) {
        const phoneRe = /^(\+?\d{1,4})?(\d{10,11})$/;
        return phoneRe.test(phone.replace(/\s/g, '')); 
    }

    function validateEmail(email) {
        const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRe.test(email.toLowerCase());
    }

    phoneInput.addEventListener("input", function() {
        if (validatePhone(this.value)) {
            this.classList.remove("is-invalid");
            this.classList.add("is-valid");
        } else {
            this.classList.remove("is-valid");
            this.classList.add("is-invalid");
        }
    });

    emailInput.addEventListener("input", function() {
        if (validateEmail(this.value)) {
            this.classList.remove("is-invalid");
            this.classList.add("is-valid");
        } else {
            this.classList.remove("is-valid");
            this.classList.add("is-invalid");
        }
    });


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

    populateStates();
    handleNationality(); 
});
</script>

