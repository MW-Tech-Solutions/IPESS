<?php $data = $_SESSION['form_data']['step_7'] ?? []; ?>
<h5 class="section-header text-primary mb-4">Referee Information</h5>
<p class="text-muted">Provide details of at least one (and at most three) academic or professional referees.</p>

<div id="refereeContainer">
    <div class="referee-item mb-4">
        <div class="card shadow-sm border-0 bg-light border-start border-primary border-4">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-badge me-2"></i>Referee 1</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="ref_name[]" value="<?php echo $data['ref_name'][0] ?? ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Position / Title</label>
                        <input type="text" class="form-control" name="ref_title[]" placeholder="e.g. Professor" value="<?php echo $data['ref_title'][0] ?? ''; ?>" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Organization</label>
                        <input type="text" class="form-control" name="ref_org[]" value="<?php echo $data['ref_org'][0] ?? ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control ref-email" name="ref_email[]" value="<?php echo $data['ref_email'][0] ?? ''; ?>" required>
                        <div class="invalid-feedback">Please enter a valid email address (e.g., name@example.com).</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-control ref-phone" name="ref_phone[]" value="<?php echo $data['ref_phone'][0] ?? ''; ?>" required>
                        <div class="invalid-feedback">Enter a valid 10-11 digit phone number.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if(isset($data['ref_name']) && count($data['ref_name']) > 1): ?>
        <?php for($i = 1; $i < min(count($data['ref_name']), 3); $i++): ?>
            <div class="referee-item mb-4">
                <div class="card shadow-sm border-0 bg-light">
                    <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                        <span>Referee <?php echo $i + 1; ?></span>
                        <button type="button" class="btn-close btn-close-white" onclick="removeReferee(this)"></button>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" class="form-control" name="ref_name[]" value="<?php echo $data['ref_name'][$i]; ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Position</label><input type="text" class="form-control" name="ref_title[]" value="<?php echo $data['ref_title'][$i]; ?>" required></div>
                            <div class="col-md-12"><label class="form-label">Organization</label><input type="text" class="form-control" name="ref_org[]" value="<?php echo $data['ref_org'][$i]; ?>" required></div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control ref-email" name="ref_email[]" value="<?php echo $data['ref_email'][$i]; ?>" required>
                                <div class="invalid-feedback">Invalid email address.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control ref-phone" name="ref_phone[]" value="<?php echo $data['ref_phone'][$i]; ?>" required>
                                <div class="invalid-feedback">Invalid phone number.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endfor; ?>
    <?php endif; ?>
</div>

<div class="text-center mt-3">
    <button type="button" id="addRefBtn" class="btn btn-outline-primary" onclick="addReferee()">
        <i class="bi bi-person-plus-fill me-2"></i>Add Another Referee
    </button>
</div>

<script>
const MAX_REFEREES = 3;

function updateAddButtonState() {
    const count = document.getElementsByClassName('referee-item').length;
    const btn = document.getElementById('addRefBtn');
    if (count >= MAX_REFEREES) {
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-slash-circle me-2"></i>Maximum Referees Reached';
        btn.classList.replace('btn-outline-primary', 'btn-outline-secondary');
    } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-person-plus-fill me-2"></i>Add Another Referee';
        btn.classList.replace('btn-outline-secondary', 'btn-outline-primary');
    }
}

function addReferee() {
    const container = document.getElementById('refereeContainer');
    const items = container.getElementsByClassName('referee-item');
    
    if (items.length >= MAX_REFEREES) return;

    const refCount = items.length + 1;
    const newRef = document.createElement('div');
    newRef.className = 'referee-item mb-4';
    newRef.innerHTML = `
        <div class="card shadow-sm border-0 bg-light">
            <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                <span>Referee ${refCount}</span>
                <button type="button" class="btn-close btn-close-white" onclick="removeReferee(this)"></button>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" class="form-control" name="ref_name[]" required></div>
                    <div class="col-md-6"><label class="form-label">Position / Title</label><input type="text" class="form-control" name="ref_title[]" required></div>
                    <div class="col-md-12"><label class="form-label">Organization</label><input type="text" class="form-control" name="ref_org[]" required></div>
                    <div class="col-md-6">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control ref-email" name="ref_email[]" required>
                        <div class="invalid-feedback">Please enter a valid email.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-control ref-phone" name="ref_phone[]" required>
                        <div class="invalid-feedback">Enter a valid 10-11 digit phone number.</div>
                    </div>
                </div>
            </div>
        </div>
    `;
    container.appendChild(newRef);
    attachValidationToRef(newRef);
    updateAddButtonState();
}

function removeReferee(btn) {
    const container = document.getElementById('refereeContainer');
    const items = container.getElementsByClassName('referee-item');
    if (items.length > 1) {
        btn.closest('.referee-item').remove();
        Array.from(items).forEach((item, index) => {
            const header = item.querySelector('.card-header span');
            header.innerHTML = (index === 0) ? '<i class="bi bi-person-badge me-2"></i>Referee 1' : `Referee ${index + 1}`;
        });
    }
    updateAddButtonState();
}

function attachValidationToRef(container) {
    const emailInp = container.querySelector('.ref-email');
    const phoneInp = container.querySelector('.ref-phone');

    emailInp.addEventListener("input", function() {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const isValid = emailRegex.test(this.value.toLowerCase());
        
        if (this.value === "") {
            this.classList.remove("is-valid", "is-invalid");
        } else if (isValid) {
            this.classList.replace("is-invalid", "is-valid") || this.classList.add("is-valid");
        } else {
            this.classList.replace("is-valid", "is-invalid") || this.classList.add("is-invalid");
        }
    });

    phoneInp.addEventListener("input", function() {
        const phoneRegex = /^(\+?\d{1,4})?(\d{10,11})$/;
        const cleanValue = this.value.replace(/\s/g, ''); 
        const isValid = phoneRegex.test(cleanValue);

        if (this.value === "") {
            this.classList.remove("is-valid", "is-invalid");
        } else if (isValid) {
            this.classList.replace("is-invalid", "is-valid") || this.classList.add("is-valid");
        } else {
            this.classList.replace("is-valid", "is-invalid") || this.classList.add("is-invalid");
        }
    });
}

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('.referee-item').forEach(item => attachValidationToRef(item));
    updateAddButtonState();
});
</script>