<?php 
$data = $_SESSION['form_data']['step_8'] ?? []; 

function getFileStatus($fieldName, $sessionData) {
    if (!empty($sessionData[$fieldName])) {
        $path = htmlspecialchars($sessionData[$fieldName]);
        $fullUrl = htmlspecialchars(app_url($sessionData[$fieldName]));
        $filename = basename($path);
        return "
        <div class='mt-2 p-2 bg-success-subtle border border-success-subtle rounded d-flex align-items-center justify-content-between'>
            <span class='text-success small'><i class='bi bi-check-circle-fill me-1'></i> Uploaded</span>
            <a href='$fullUrl' target='_blank' class='btn btn-sm btn-outline-success py-0' style='font-size: 0.75rem;'>View</a>
        </div>";
    }
    return '';
}

$rejectedFields = [];
$hasRejections = false;
if (isset($_SESSION['application_id'], $pdo)) {
    $docFieldMap = [
        'passport' => 'passport_file',
        'passport_profile' => 'passport_profile_file',
        'olevel_1' => 'olevel_file',
        'olevel_2' => 'olevel_file_2',
        'degree' => 'degree_file',
        'transcript' => 'transcript_file',
        'nysc' => 'nysc_file',
        'proposal' => 'proposal_file'
    ];
    try {
        $stmt = $pdo->prepare("
            SELECT d.document_type
            FROM documents d
            JOIN document_verification dv ON dv.upload_id = d.doc_id
            WHERE d.application_id = ? AND dv.verification_status = 'Re-upload Required'
        ");
        $stmt->execute([$_SESSION['application_id']]);
        $types = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($types as $type) {
            if (isset($docFieldMap[$type])) {
                $rejectedFields[] = $docFieldMap[$type];
            }
        }
        $hasRejections = count($rejectedFields) > 0;
    } catch (Throwable $e) {
    }
}

function field_attrs(string $fieldName, bool $requiredWhenNoRejections = false): string {
    global $hasRejections, $rejectedFields, $data;
    if ($hasRejections && !in_array($fieldName, $rejectedFields, true)) {
        return 'disabled';
    }
    if ($hasRejections && in_array($fieldName, $rejectedFields, true) && empty($data[$fieldName])) {
        return 'required';
    }
    if (!$hasRejections && $requiredWhenNoRejections && empty($data[$fieldName])) {
        return 'required';
    }
    return '';
}
?>

<h5 class="section-header text-primary mb-4">Document Uploads</h5>

<div class="alert alert-info border-0 shadow-sm mb-4 d-flex align-items-center">
    <i class="bi bi-shield-lock-fill fs-4 me-3"></i>
    <div>
        <small class="d-block"><strong>Allowed Formats:</strong> PDF, JPG, PNG</small>
        <small class="d-block"><strong>Max File Size:</strong> 2MB per document</small>
    </div>
</div>

<?php if ($hasRejections): ?>
    <div class="alert alert-warning border-0 shadow-sm mb-4">
        <strong>Action Required:</strong> Please re-upload only the rejected document(s). Other uploads are temporarily disabled.
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-12">
        <label class="form-label fw-bold">Passport Photograph <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text bg-light"><i class="bi bi-person-bounding-box"></i></span>
            <input type="file" 
                   class="form-control" 
                   name="passport_file" 
                   accept=".jpg,.jpeg,.png" 
                   <?php echo field_attrs('passport_file', true); ?>>
        </div>
        <?php echo getFileStatus('passport_file', $data); ?>
    </div>

    <div class="col-12 mt-4">
        <div class="p-3 border rounded bg-light mb-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="mb-0 fw-bold">O'Level Result(s)</h6>
                    <small class="text-muted">Do you have two sittings?</small>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="twoSittingToggle" 
                           style="transform: scale(1.3);" onchange="toggleOLevelFields()">
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-bold" id="label_sitting1">O'Level Result (Single Sitting) <span class="text-danger">*</span></label>
                <input type="file" class="form-control" name="olevel_file" accept=".pdf,.jpg,.jpeg,.png"
                       <?php echo field_attrs('olevel_file', true); ?>>
                <?php echo getFileStatus('olevel_file', $data); ?>
            </div>

            <div id="olevel_2_col" class="col-md-6 d-none">
                <label class="form-label fw-bold">O'Level Result (Second Sitting) <span class="text-danger">*</span></label>
                <input type="file" class="form-control" id="olevel_file_2" name="olevel_file_2" accept=".pdf,.jpg,.jpeg,.png" <?php echo field_attrs('olevel_file_2'); ?>>
                <?php echo getFileStatus('olevel_file_2', $data); ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold">Degree Certificate <span class="text-danger">*</span></label>
        <input type="file" class="form-control" name="degree_file" accept=".pdf,.jpg,.jpeg,.png"
               <?php echo field_attrs('degree_file', true); ?>>
        <?php echo getFileStatus('degree_file', $data); ?>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold">Academic Transcript <span class="text-danger">*</span></label>
        <input type="file" class="form-control" name="transcript_file" accept=".pdf,.jpg,.jpeg,.png"
               <?php echo field_attrs('transcript_file', true); ?>>
        <?php echo getFileStatus('transcript_file', $data); ?>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold">NYSC Cert. / Exemption (Optional)</label>
        <input type="file" class="form-control" name="nysc_file" accept=".pdf,.jpg,.jpeg,.png" <?php echo field_attrs('nysc_file'); ?>>
        <?php echo getFileStatus('nysc_file', $data); ?>
    </div>

    <div class="col-12 mt-3">
         <div class="form-check">
            <input class="form-check-input" type="checkbox" id="phd_trigger" onchange="togglePhD(this)">
            <label class="form-check-label fw-bold" for="phd_trigger">
                I am applying for a PhD program
            </label>
        </div>
    </div>

    <div class="col-md-6 d-none" id="proposal_container">
        <label class="form-label fw-bold">Research Proposal <span class="text-danger">*</span></label>
        <small class="d-block text-muted mb-1">Upload your detailed research topic and methodology.</small>
        <input type="file" class="form-control" name="proposal_file" id="proposal_file" accept=".pdf">
        <?php echo getFileStatus('proposal_file', $data); ?>
    </div>
</div>

<script>
function toggleOLevelFields() {
    const isTwoSittings = document.getElementById('twoSittingToggle').checked;
    const col2 = document.getElementById('olevel_2_col');
    const input2 = document.getElementById('olevel_file_2');
    const label1 = document.getElementById('label_sitting1');

    if (isTwoSittings) {
        col2.classList.remove('d-none');
        <?php if(empty($data['olevel_file_2'])): ?>
            input2.setAttribute('required', 'required');
        <?php endif; ?>
        label1.innerText = "O'Level Result (First Sitting)";
    } else {
        col2.classList.add('d-none');
        input2.removeAttribute('required');
        input2.value = ""; 
        label1.innerText = "O'Level Result (Single Sitting)";
    }
}

function togglePhD(checkbox) {
    const container = document.getElementById('proposal_container');
    const input = document.getElementById('proposal_file');

    if (checkbox.checked) {
        container.classList.remove('d-none');
        <?php if(empty($data['proposal_file'])): ?>
            input.setAttribute('required', 'required');
        <?php endif; ?>
    } else {
        container.classList.add('d-none');
        input.removeAttribute('required');
        input.value = "";
    }
}

window.addEventListener('DOMContentLoaded', () => {
    <?php if(!empty($data['olevel_file_2'])): ?>
        document.getElementById('twoSittingToggle').checked = true;
        toggleOLevelFields();
    <?php endif; ?>

    <?php if(!empty($data['proposal_file'])): ?>
        document.getElementById('phd_trigger').checked = true;
        togglePhD(document.getElementById('phd_trigger'));
    <?php endif; ?>
});
</script>
