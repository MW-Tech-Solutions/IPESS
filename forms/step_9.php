<?php 
$all_data = $_SESSION['form_data'] ?? []; 
$step2 = $all_data['step_2'] ?? [];

$faculty_name = "N/A";
$dept_name = "N/A";
$degree_name = "N/A";
$course_title = "N/A";
$mode_name = "N/A";

if (!empty($step2)) {
    try {
        require 'config/db.php'; 

        if (!empty($step2['faculty'])) {
            $stmt = $pdo->prepare("SELECT faculty_name FROM faculties WHERE faculty_id = ?");
            $stmt->execute([$step2['faculty']]);
            $faculty_name = $stmt->fetchColumn() ?: "N/A";
        }

        if (!empty($step2['department'])) {
            $stmt = $pdo->prepare("SELECT dept_name FROM departments WHERE dept_id = ?");
            $stmt->execute([$step2['department']]);
            $dept_name = $stmt->fetchColumn() ?: "N/A";
        }

        if (!empty($step2['degree_type'])) {
            $stmt = $pdo->prepare("SELECT degree_name FROM degree_types WHERE degree_id = ?");
            $stmt->execute([$step2['degree_type']]);
            $degree_name = $stmt->fetchColumn() ?: "N/A";
        }

        if (!empty($step2['course'])) {
            $stmt = $pdo->prepare("SELECT course_title FROM courses WHERE course_id = ?");
            $stmt->execute([$step2['course']]);
            $course_title = $stmt->fetchColumn() ?: "N/A";
        }

        if (!empty($step2['mode'])) {
            $stmt = $pdo->prepare("SELECT mode_name FROM study_modes WHERE mode_id = ?");
            $stmt->execute([$step2['mode']]);
            $mode_name = $stmt->fetchColumn() ?: "N/A";
        }
    } catch (PDOException $e) {
    }
}

$all_data = $_SESSION['form_data'] ?? []; 
?>
<h5 class="mb-4 text-success">Review Application</h5>
<p>Please review your information carefully before submitting. You cannot edit after submission.</p>

<div class="accordion" id="reviewAccordion">
    
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                Step 1: Personal Information
            </button>
        </h2>
        <div id="collapse1" class="accordion-collapse collapse show" data-bs-parent="#reviewAccordion">
            <div class="accordion-body">
                <table class="table table-sm table-borderless">
                    <tr><td class="text-muted w-25">Full Name:</td><td class="fw-bold"><?php echo ($all_data['step_1']['surname'] ?? '') . ' ' . ($all_data['step_1']['firstName'] ?? '') . ' ' . ($all_data['step_1']['otherName'] ?? ''); ?></td></tr>
                    <tr><td class="text-muted">Email:</td><td class="fw-bold"><?php echo $_SESSION['user_email'] ?? 'Not set'; ?></td></tr>
                    <tr><td class="text-muted">Phone:</td><td class="fw-bold"><?php echo $all_data['step_1']['phone'] ?? ''; ?></td></tr>
                    <tr><td class="text-muted">Address:</td><td class="fw-bold"><?php echo $all_data['step_1']['address'] ?? ''; ?></td></tr>
                    <tr><td class="text-muted">State/LGA:</td><td class="fw-bold"><?php echo ($all_data['step_1']['state'] ?? '') . ' / ' . ($all_data['step_1']['lga'] ?? ''); ?></td></tr>
                </table>
                <a href="?step=1" class="btn btn-sm btn-outline-primary">Edit Step 1</a>
            </div>
        </div>
    </div>

   <div class="accordion-item">
    <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
            Step 2: Programme Details
        </button>
    </h2>
    <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#reviewAccordion">
        <div class="accordion-body">
            <table class="table table-sm table-borderless">
                <tr><td class="text-muted w-25">Faculty:</td><td class="fw-bold"><?php echo htmlspecialchars($faculty_name); ?></td></tr>
                <tr><td class="text-muted">Department:</td><td class="fw-bold"><?php echo htmlspecialchars($dept_name); ?></td></tr>
                <tr><td class="text-muted">Course:</td><td class="fw-bold"><?php echo htmlspecialchars($course_title); ?></td></tr>
                <tr><td class="text-muted">Degree:</td><td class="fw-bold"><?php echo htmlspecialchars($degree_name); ?></td></tr>
                <tr><td class="text-muted">Mode:</td><td class="fw-bold"><?php echo htmlspecialchars($mode_name); ?></td></tr>
            </table>
            <a href="?step=2" class="btn btn-sm btn-outline-primary">Edit Step 2</a>
        </div>
    </div>
</div>

    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                Step 3: Academic History
            </button>
        </h2>
        <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#reviewAccordion">
            <div class="accordion-body">
                <h6 class="text-primary border-bottom pb-1">Higher Education</h6>
                <p><b><?php echo $all_data['step_3']['institution'] ?? ''; ?></b><br>
                <?php echo ($all_data['step_3']['highest_qualification'] ?? '') . ' in ' . ($all_data['step_3']['course_study'] ?? ''); ?> (<?php echo $all_data['step_3']['grad_year'] ?? ''; ?>) - CGPA: <?php echo $all_data['step_3']['cgpa'] ?? ''; ?></p>

                <h6 class="text-primary border-bottom pb-1 mt-3">O-Level Results</h6>
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><b>Sitting 1: <?php echo $all_data['step_3']['ssce1_type'] ?? ''; ?></b> (<?php echo $all_data['step_3']['ssce1_year'] ?? ''; ?>)</p>
                        <ul class="small list-unstyled">
                            <?php 
                            if(isset($all_data['step_3']['ssce1_subjects'])){
                                foreach($all_data['step_3']['ssce1_subjects'] as $idx => $sub){
                                    echo "<li>$sub: <b>{$all_data['step_3']['ssce1_grades'][$idx]}</b></li>";
                                }
                            }
                            ?>
                        </ul>
                    </div>
                    <?php if(!empty($all_data['step_3']['ssce2_school'])): ?>
                    <div class="col-md-6 border-start">
                        <p class="mb-1"><b>Sitting 2: <?php echo $all_data['step_3']['ssce2_type'] ?? ''; ?></b> (<?php echo $all_data['step_3']['ssce2_year'] ?? ''; ?>)</p>
                        <ul class="small list-unstyled">
                            <?php 
                            foreach($all_data['step_3']['ssce2_subjects'] as $idx => $sub){
                                if(!empty($sub)) echo "<li>$sub: <b>{$all_data['step_3']['ssce2_grades'][$idx]}</b></li>";
                            }
                            ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <a href="?step=3" class="btn btn-sm btn-outline-primary">Edit Step 3</a>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4">Step 4: NYSC Info</button></h2>
        <div id="collapse4" class="accordion-collapse collapse" data-bs-parent="#reviewAccordion"><div class="accordion-body"><table class="table table-sm table-borderless"><tr><td class="text-muted w-25">Status:</td><td class="fw-bold"><?php echo $all_data['step_4']['nysc_status'] ?? ''; ?></td></tr><tr><td class="text-muted">Cert No:</td><td class="fw-bold"><?php echo $all_data['step_4']['nysc_number'] ?? 'N/A'; ?></td></tr><tr><td class="text-muted">Year:</td><td class="fw-bold"><?php echo $all_data['step_4']['nysc_year'] ?? 'N/A'; ?></td></tr></table><a href="?step=4" class="btn btn-sm btn-outline-primary">Edit Step 4</a></div></div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse5">Step 5: Work Experience</button></h2>
        <div id="collapse5" class="accordion-collapse collapse" data-bs-parent="#reviewAccordion"><div class="accordion-body"><table class="table table-sm table-borderless"><tr><td class="text-muted w-25">Status:</td><td class="fw-bold"><?php echo $all_data['step_5']['emp_status'] ?? ''; ?></td></tr><?php if(($all_data['step_5']['emp_status'] ?? '') == 'Employed'): ?><tr><td class="text-muted">Employer:</td><td class="fw-bold"><?php echo $all_data['step_5']['employer'] ?? ''; ?></td></tr><tr><td class="text-muted">Role:</td><td class="fw-bold"><?php echo $all_data['step_5']['job_title'] ?? ''; ?></td></tr><tr><td class="text-muted">Years:</td><td class="fw-bold"><?php echo $all_data['step_5']['years_experience'] ?? ''; ?></td></tr><?php endif; ?></table><a href="?step=5" class="btn btn-sm btn-outline-primary">Edit Step 5</a></div></div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse6">Step 6: Research Details</button></h2>
        <div id="collapse6" class="accordion-collapse collapse" data-bs-parent="#reviewAccordion"><div class="accordion-body"><p class="mb-1 text-muted small">Proposed Research Area:</p><p class="fw-bold"><?php echo $all_data['step_6']['proposed_research_area'] ?? ''; ?></p><p class="mb-1 text-muted small">Statement of Purpose:</p><p class="small"><?php echo nl2br(htmlspecialchars($all_data['step_6']['statement_of_purpose'] ?? '')); ?></p><a href="?step=6" class="btn btn-sm btn-outline-primary">Edit Step 6</a></div></div>
    </div>

    <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse7">Step 7: Referees</button></h2>
    <div id="collapse7" class="accordion-collapse collapse" data-bs-parent="#reviewAccordion">
        <div class="accordion-body">
            <div class="row">
                <?php if(isset($all_data['step_7']['ref_name'])): ?>
                    <?php foreach($all_data['step_7']['ref_name'] as $i => $name): ?>
                        <?php if(!empty($name)): ?>
                            <div class="col-md-4">
                                <p class="mb-0 fw-bold"><?php echo $name; ?></p>
                                <p class="small text-muted mb-0"><?php echo $all_data['step_7']['ref_org'][$i]; ?></p>
                                <p class="small mb-0"><?php echo $all_data['step_7']['ref_email'][$i]; ?></p>
                                <p class="small mb-2"><?php echo $all_data['step_7']['ref_phone'][$i]; ?></p>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <a href="?step=7" class="btn btn-sm btn-outline-primary">Edit Step 7</a>
        </div>
    </div>
</div>

    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse8">
                Step 8: Documents
            </button>
        </h2>
        <div id="collapse8" class="accordion-collapse collapse" data-bs-parent="#reviewAccordion">
            <div class="accordion-body">
                <ul class="list-group list-group-flush small">
                    <?php 
                    $docs = $all_data['step_8'] ?? [];
                    
                    $labels = [
                        'olevel_file' => 'O-Level Result (Sitting 1)',
                        'degree_file' => 'Degree Certificate',
                        'transcript_file' => 'Academic Transcript',
                        'nysc_file' => 'NYSC Certificate'
                    ];

                    if (!empty($all_data['step_3']['ssce2_school'])) {
                        $labels['olevel_file_2'] = 'O-Level Result (Sitting 2)';
                    }
                    $degreeType = $all_data['step_2']['degree_type'] ?? '';
                    if ($degreeType === 'PhD') {
                        $labels['proposal_file'] = 'Research Proposal';
                    }

                    foreach($labels as $key => $label){
                        $isUploaded = isset($docs[$key]) && !empty($docs[$key]);
                        $status = $isUploaded 
                            ? '<span class="text-success"><i class="bi bi-check-circle"></i> Uploaded</span>' 
                            : '<span class="text-danger">Missing</span>';
                        
                        echo "<li class='list-group-item d-flex justify-content-between align-items-center'>$label $status</li>";
                    }
                    ?>
                </ul>
                <a href="?step=8" class="btn btn-sm btn-outline-primary mt-2">Edit Step 8</a>
            </div>
        </div>
    </div>

</div>

<div class="card mt-4 border-warning bg-light">
    <div class="card-body">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="declaration" id="declaration" required>
            <label class="form-check-label " for="declaration">
                I, <u><b><?php echo ($all_data['step_1']['surname'] ?? '') . ' ' . ($all_data['step_1']['firstName'] ?? ''); ?></b></u>
                hereby declare that the information provided is true and correct. I understand that any false information may lead to disqualification.
            </label>
        </div>
    </div>
</div> 
<div class="card mt-4 border-warning bg-light">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label small fw-bold text-uppercase text-muted">Security Check</label>
            </div>
            
            <div class="col-md-6">
                <div class="d-inline-flex align-items-center gap-2 p-2 bg-white border rounded shadow-sm">
                    <canvas id="captchaCanvas" width="180" height="50" class="rounded" style="background: #eee; cursor: pointer;" onclick="drawCaptcha()"></canvas>
                    <button type="button" class="btn btn-outline-secondary btn-sm border-0" onclick="drawCaptcha()" title="Refresh">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>

            <div class="col-md-6 mt-2"> 
                <div class="input-group" style="max-width: 300px;"> 
                    <input type="text" id="captchaInput" class="form-control" placeholder="Enter code" maxlength="6" autocomplete="off">
                    <button class="btn btn-primary" type="button" id="verifyCaptchaBtn">
                        Verify
                    </button>
                </div>
                <div id="captchaStatus" class="mt-1 small fw-bold"></div>
                <input type="hidden" name="captcha_verified" id="captcha_verified_hidden" value="0">
            </div>
        </div>
    </div>
</div>

<style>
    .is-invalid-shake {
        animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
        border-color: #dc3545 !important;
    }
    @keyframes shake {
        10%, 90% { transform: translate3d(-1px, 0, 0); }
        20%, 80% { transform: translate3d(2px, 0, 0); }
        30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
        40%, 60% { transform: translate3d(4px, 0, 0); }
    }
</style>

<script>
let captchaCode = "";
let isCaptchaVerified = false;

function drawCaptcha() {
    const canvas = document.getElementById("captchaCanvas");
    if(!canvas) return;
    
    const ctx = canvas.getContext("2d");
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    for (let i = 0; i < 50; i++) {
        ctx.fillStyle = `rgba(${Math.random()*255}, ${Math.random()*255}, ${Math.random()*255}, 0.3)`;
        ctx.beginPath();
        ctx.arc(Math.random() * canvas.width, Math.random() * canvas.height, Math.random() * 2, 0, Math.PI * 2);
        ctx.fill();
    }

    const chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    captchaCode = "";
    for (let i = 0; i < 6; i++) {
        captchaCode += chars.charAt(Math.floor(Math.random() * chars.length));
    }

    for(let i=0; i<5; i++) {
        ctx.strokeStyle = `rgba(${Math.random()*100}, ${Math.random()*100}, ${Math.random()*100}, 0.5)`;
        ctx.lineWidth = 1.5;
        ctx.beginPath();
        ctx.moveTo(0, Math.random() * canvas.height);
        ctx.bezierCurveTo(
            canvas.width / 3, Math.random() * canvas.height,
            (canvas.width / 3) * 2, Math.random() * canvas.height,
            canvas.width, Math.random() * canvas.height
        );
        ctx.stroke();
    }

    let x = 15;
    const fonts = ["Arial", "Verdana", "Courier New", "Georgia", "Times New Roman"];
    
    for(let i=0; i<captchaCode.length; i++) {
        const fontSize = Math.floor(Math.random() * 10) + 22; 
        const fontName = fonts[Math.floor(Math.random() * fonts.length)];
        
        ctx.save();
        ctx.font = `bold ${fontSize}px ${fontName}`;
        ctx.fillStyle = `rgb(${Math.random()*100}, ${Math.random()*100}, ${Math.random()*100})`; 
        
        const angle = (Math.random() - 0.5) * 0.6; 
        const yOffset = (Math.random() - 0.5) * 10;
        
        ctx.translate(x, 25 + yOffset);
        ctx.rotate(angle);
        ctx.fillText(captchaCode[i], 0, 0);
        
        ctx.restore();
        x += 24 + (Math.random() * 4); 
    }
    
    isCaptchaVerified = false;
    document.getElementById("captchaInput").value = "";
    document.getElementById("captchaInput").disabled = false;
    document.getElementById("verifyCaptchaBtn").disabled = false;
    document.getElementById("captchaInput").classList.remove("is-valid", "is-invalid");
    document.getElementById("captchaStatus").innerHTML = "";
}

document.addEventListener("DOMContentLoaded", () => {
    drawCaptcha();
    
    const verifyBtn = document.getElementById("verifyCaptchaBtn");
    const mainForm = document.querySelector('form');
    const hiddenInput = document.getElementById("captcha_verified_hidden");

    if (verifyBtn) {
        verifyBtn.addEventListener("click", function() {
            const input = document.getElementById("captchaInput");
            const status = document.getElementById("captchaStatus");
            const val = input.value.trim();
            
            if(val === captchaCode && captchaCode !== "") {
                isCaptchaVerified = true;
                hiddenInput.value = "1";
                input.classList.remove("is-invalid");
                input.classList.add("is-valid");
                status.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Verified!</span>';
                
                input.disabled = true;
                this.disabled = true;
            } else {
                isCaptchaVerified = false;
                hiddenInput.value = "0";
                input.classList.add("is-invalid-shake"); 
                input.classList.add("is-invalid");
                status.innerHTML = '<span class="text-danger">Incorrect code. Please try again.</span>';
                
                setTimeout(() => input.classList.remove("is-invalid-shake"), 500);
                
                drawCaptcha(); 
                input.value = "";
            }
        });
    }

    if(mainForm) {
        mainForm.addEventListener('submit', function(e) {
            if(!isCaptchaVerified) {
                e.preventDefault();
                alert("Please verify the security captcha before submitting.");
            }
        });
    }
});
</script>