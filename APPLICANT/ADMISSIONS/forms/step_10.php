<?php
require_once 'db.php'; 

$application_id = $_SESSION['application_id'] ?? 0;
$app_status = 'Dept. Review';
$app_current_status = '';
$submission_status = 'PENDING';
$doc_status = 'IN PROGRESS';
$academic_status = 'PENDING';
$ref_status = 'PENDING';
$final_status = 'PENDING';
$db_app_number = '';

try {
    $stmt = $pdo->prepare("SELECT application_number, status, current_status FROM applications WHERE application_id = ?");
    $stmt->execute([$application_id]);
    $app_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($app_data) {
        $db_app_number = $app_data['application_number'];
        $db_status = $app_data['status'];
        $app_current_status = $app_data['current_status'] ?? '';
        
        $app_status = ($db_status == 'Submitted') ? 'Dept. Review' : $db_status;
    }
} catch (PDOException $e) {
}

$doc_total = 0;
$doc_verified = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE application_id = ?");
    $stmt->execute([$application_id]);
    $doc_total = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM documents d 
        JOIN document_verification dv ON dv.upload_id = d.doc_id
        WHERE d.application_id = ? AND dv.verification_status = 'Verified'
    ");
    $stmt->execute([$application_id]);
    $doc_verified = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
}

if ($doc_total > 0 && $doc_verified >= $doc_total) {
    $doc_status = 'COMPLETED';
}

try {
    $stmt = $pdo->prepare("SELECT verified_status FROM referee_uploads WHERE application_id = ? ORDER BY submitted_at DESC LIMIT 1");
    $stmt->execute([$application_id]);
    $ref_row = $stmt->fetchColumn();
    if ($ref_row) {
        $ref_status = ($ref_row === 'Verified') ? 'COMPLETED' : 'IN PROGRESS';
    }
} catch (PDOException $e) {
}

if (in_array($app_current_status, ['UNDER_DEPT_REVIEW','DEPT_APPROVED','REVIEWER_ASSIGNED','UNDER_REVIEWER_REVIEW','REVIEWER_APPROVED','REVIEWER_REJECTED','ADMIN_FINAL_REVIEW','ADMISSION_APPROVED','ADMISSION_REJECTED','SUBMITTED'], true)) {
    $academic_status = 'IN PROGRESS';
}
if (in_array($app_current_status, ['REVIEWER_APPROVED','ADMIN_FINAL_REVIEW','ADMISSION_APPROVED','ADMISSION_REJECTED','SUBMITTED','DEPT_APPROVED'], true)) {
    $academic_status = 'COMPLETED';
}

if (in_array($app_current_status, ['ADMISSION_APPROVED','ADMISSION_REJECTED'], true) || in_array(strtolower($app_status), ['admitted','rejected'], true)) {
    $final_status = (stripos($app_status, 'reject') !== false || $app_current_status === 'ADMISSION_REJECTED') ? 'REJECTED' : 'APPROVED';
}

$submittedLike = strtolower($app_status);
if ($submittedLike !== 'draft' && $submittedLike !== 'pending' && $db_app_number !== '') {
    $submission_status = 'COMPLETED';
}

$ui_config = [
    'Dept. Review' => ['color' => 'primary', 'bg' => 'primary', 'icon' => 'bi-hourglass-split', 'msg' => 'Your documents are being reviewed.'],
    'Admitted' => ['color' => 'success', 'bg' => 'success', 'icon' => 'bi-award-fill', 'msg' => 'Congratulations! You have been admitted.'],
    'Rejected' => ['color' => 'danger', 'bg' => 'danger', 'icon' => 'bi-x-circle', 'msg' => 'Application not successful.']
];

$ui = $ui_config[$app_status] ?? $ui_config['Dept. Review'];
?>

<style>
    .status-header { background: #f8f9fa; border-radius: 12px; padding: 25px; border-left: 5px solid var(--bs-<?php echo $ui['color']; ?>); }
    .timeline-step { text-align: center; position: relative; flex: 1; }
    .step-dot { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; background: #eee; color: #aaa; }
    .step-dot.active { background: var(--bs-primary); color: #fff; }
    .step-dot.completed { background: #198754; color: #fff; }
    .step-dot.rejected { background: #dc3545; color: #fff; }
</style>

<div class="animate__animated animate__fadeIn">

    <div class="status-header mb-4 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center">
            <div class="rounded-circle bg-<?php echo $ui['bg']; ?> bg-opacity-10 p-3 me-3 text-<?php echo $ui['color']; ?>">
                <i class="bi <?php echo $ui['icon']; ?> fs-2"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-1 text-<?php echo $ui['color']; ?>"><?php echo ($app_status == 'Dept. Review') ? 'Under Review' : $app_status; ?></h4>
                <p class="mb-0 text-muted small"><?php echo $ui['msg']; ?></p>
            </div>
        </div>
        
        <?php if($app_status == 'Admitted'): ?>
            <a class="btn btn-success fw-bold px-4 py-2 shadow-sm"
               href="#"
               onclick="printSlipBackground('../../helpers/admission-letter.php?app_no=<?php echo urlencode($db_app_number); ?>'); return false;">
                <i class="bi bi-download me-2"></i> Download Admission Letter
            </a>
            <iframe id="printFrame" style="display:none;"></iframe>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-5">
        <div class="col-md-6">
            <div class="p-3 border rounded-3 bg-white h-100">
                <small class="text-uppercase text-muted fw-bold" style="font-size: 11px;">Reference ID</small>
                <div class="fw-bold fs-5">PG/2025/<?php echo str_pad($application_id, 4, '0', STR_PAD_LEFT); ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="p-3 border rounded-3 bg-white h-100 d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-uppercase text-muted fw-bold" style="font-size: 11px;">Application Slip</small>
                    <div class="fw-bold fs-5">Ready</div>
                </div>
                <a href="#"
                   onclick="printSlipBackground('success.php?app_no=<?php echo urlencode($db_app_number); ?>'); return false;"
                   class="btn btn-sm btn-outline-dark rounded-pill">
                   Download
                </a>
            </div>
        </div>
    </div>

    <h6 class="fw-bold text-muted border-bottom pb-2 mb-4">TRACKING HISTORY</h6>
    <div class="d-flex justify-content-between position-relative flex-wrap gap-3">
        <div class="position-absolute top-0 start-0 w-100 bg-light d-none d-md-block" style="height: 2px; top: 20px; z-index: 0;"></div>

        <?php 
        $history_steps = [
            ['label' => 'Application Submitted', 'status' => $submission_status],
            ['label' => 'Documents Verified', 'status' => $doc_status],
            ['label' => 'Academic Review', 'status' => $academic_status],
            ['label' => 'Referee Reports', 'status' => $ref_status],
            ['label' => 'Final Decision', 'status' => $final_status],
        ];

        foreach($history_steps as $step): 
            $state = 'pending';
            $icon = 'bi-circle';
            $status_display = 'PENDING';

            if ($step['status'] === 'IN PROGRESS') {
                $state = 'active';
                $icon = 'bi-arrow-repeat';
                $status_display = 'IN PROGRESS';
            } elseif ($step['status'] === 'COMPLETED') {
                $state = 'completed';
                $icon = 'bi-check-lg';
                $status_display = 'COMPLETED';
            } elseif ($step['status'] === 'APPROVED') {
                $state = 'completed';
                $icon = 'bi-check-circle';
                $status_display = 'APPROVED';
            } elseif ($step['status'] === 'REJECTED') {
                $state = 'rejected';
                $icon = 'bi-x-circle';
                $status_display = 'REJECTED';
            }
        ?>
            <div class="timeline-step" style="z-index: 1; min-width: 160px;">
                <div class="step-dot <?php echo $state; ?>">
                    <i class="bi <?php echo $icon; ?>"></i>
                </div>
                <div class="small fw-bold mt-2 <?php echo ($state == 'active') ? 'text-primary' : 'text-dark'; ?>">
                    <?php echo $step['label']; ?>
                </div>
                <div class="small text-muted" style="font-size: 10px;">
                    <?php echo $status_display; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="position-fixed bottom-0 start-50 translate-middle-x p-3" style="z-index: 9999; width: 90%; max-width: 400px;">
    <div id="pdfToast" class="toast align-items-center text-white bg-dark border-0 w-100" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                <div class="spinner-border spinner-border-sm me-2 text-primary" role="status"></div>
                Generating document...
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
function printSlipBackground(url) {
    const toastElement = document.getElementById('pdfToast');
    if (typeof bootstrap !== 'undefined') {
        const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
        toast.show();

        const frame = document.getElementById('printFrame');
        frame.src = url;

        frame.onload = function() {
            try {
                frame.contentWindow.focus();
                frame.contentWindow.print();
                setTimeout(() => toast.hide(), 1000);
            } catch (e) {
                console.error(e);
                window.open(url, '_blank');
                toast.hide();
            }
        };
    } else {
        window.open(url, '_blank');
    }
}
</script>
