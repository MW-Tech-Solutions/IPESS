<?php $data = $_SESSION['form_data']['step_4'] ?? []; ?>
<h5 class="mb-4 text-primary">NYSC & Professional Qualifications</h5>
<div class="row g-3">
    <div class="col-12">
        <label class="form-label">NYSC Status</label>
        <select class="form-select" name="nysc_status" id="nyscStatus" onchange="toggleNysc()" required>
            <option value="">Select Status...</option>
            <option value="Completed" <?php echo ($data['nysc_status'] ?? '') == 'Completed' ? 'selected' : ''; ?>>Completed</option>
            <option value="Exempted" <?php echo ($data['nysc_status'] ?? '') == 'Exempted' ? 'selected' : ''; ?>>Exempted</option>
            <option value="Serving" <?php echo ($data['nysc_status'] ?? '') == 'Not Yet' ? 'selected' : ''; ?>>Not Yet</option>
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">NYSC Certificate / Exemption No.</label>
        <input type="text" class="form-control" name="nysc_number" placeholder="A0000000" value="<?php echo $data['nysc_number'] ?? ''; ?>">
    </div>
    
    <div class="col-md-6">
        <label class="form-label">Year of Completion/Exemption</label>
        <input type="number" class="form-control" name="nysc_year" value="<?php echo $data['nysc_year'] ?? ''; ?>">
    </div>
    
</div>

<script>
function toggleNysc() {
    const status = document.getElementById('nyscStatus').value;
    const numInput = document.getElementsByName('nysc_number')[0];
    const yearInput = document.getElementsByName('nysc_year')[0];
    
    if (status === 'Completed' || status === 'Exempted') {
        numInput.required = true;
        yearInput.required = true;
        numInput.disabled = false;
        yearInput.disabled = false;
        numInput.closest('.col-md-6').style.display = 'block';
        yearInput.closest('.col-md-6').style.display = 'block';
    } else {
        numInput.required = false;
        yearInput.required = false;
        numInput.disabled = true;
        yearInput.disabled = true;
        numInput.value = '';
        yearInput.value = '';
        numInput.closest('.col-md-6').style.display = 'none';
        yearInput.closest('.col-md-6').style.display = 'none';
    }
}
document.addEventListener('DOMContentLoaded', toggleNysc);
</script>