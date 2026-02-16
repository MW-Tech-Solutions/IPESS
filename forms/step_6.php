<?php $data = $_SESSION['form_data']['step_6'] ?? []; ?>
<h5 class="section-header text-primary mb-4">Statement of Intent</h5>

<div class="row g-3">
    <div class="col-12 mb-3">
        <label class="form-label fw-bold">Proposed Area of Research / Interest</label>
        <input type="text" 
               class="form-control" 
               name="proposed_research_area" 
               placeholder="Enter your specific research focus..." 
               value="<?php echo $data['proposed_research_area'] ?? ''; ?>" 
               required>
    </div>

    <div class="col-12 mb-3">
        <label class="form-label fw-bold">Reason for Choosing this Programme</label>
        <textarea class="form-control word-limit" 
                  name="reason_for_choosing_programme" 
                  rows="4" 
                  data-max="150"
                  required><?php echo $data['reason_for_choosing_programme'] ?? ''; ?></textarea>
        <div class="form-text text-end"><span class="word-count">0</span> / 150 words</div>
    </div>
    <div class="col-12 mb-3">
        <label class="form-label fw-bold">Statement of Purpose</label>
        <textarea class="form-control word-limit" 
                  name="statement_of_purpose" 
                  rows="8" 
                  data-max="500"
                  placeholder="Describe your background and academic goals..." 
                  required><?php echo $data['statement_of_purpose'] ?? ''; ?></textarea>
        <div class="form-text text-end"><span class="word-count">0</span> / 500 words</div>
    </div>

    <div class="col-12 mb-3">
        <label class="form-label fw-bold">Career Objectives After Graduation</label>
        <textarea class="form-control word-limit" 
                  name="career_objectives" 
                  rows="4" 
                  placeholder="Where do you see yourself professionally after completing this degree?" 

                  data-max="200"
                  required><?php echo $data['career_objectives'] ?? ''; ?></textarea>
        <div class="form-text text-end"><span class="word-count">0</span> / 200 words</div>
    </div>

    
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const textareas = document.querySelectorAll(".word-limit");

    textareas.forEach(textarea => {
        const countDisplay = textarea.parentElement.querySelector(".word-count");
        const maxWords = parseInt(textarea.getAttribute("data-max"));

        function updateCount() {
            const text = textarea.value.trim();
            const words = text ? text.split(/\s+/).length : 0;
            countDisplay.innerText = words;

            if (words > maxWords) {
                textarea.classList.add("is-invalid");
                countDisplay.classList.add("text-danger");
            } else {
                textarea.classList.remove("is-invalid");
                countDisplay.classList.remove("text-danger");
            }
        }

        textarea.addEventListener("input", updateCount);
        updateCount();
    });
});
</script>
