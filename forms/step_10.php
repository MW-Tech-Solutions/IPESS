<?php
require_once 'db.php'; 

$application_id = $_SESSION['application_id'] ?? 0;

$app_number = '----';
$public_status = 'Pending';
$progress_map = [];
$app_current_status = '';

try {
    $stmtS = $pdo->prepare("SELECT public_status FROM application_status WHERE application_id = ? ORDER BY status_id DESC LIMIT 1");
    $stmtS->execute([$application_id]);
    $public_status = $stmtS->fetchColumn() ?: 'Application Submitted';

    $stmtA = $pdo->prepare("SELECT application_number, current_status, status FROM applications WHERE application_id = ?");
    $stmtA->execute([$application_id]);
    $row = $stmtA->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $app_number = $row['application_number'] ?: 'GENERATING...';
        $app_current_status = $row['current_status'] ?? '';
        $public_status = $row['status'] ?? $public_status;
    }

    $stmt_prog = $pdo->prepare("SELECT stage, stage_status, stage_updated_at FROM application_progress WHERE application_id = ?");
    $stmt_prog->execute([$application_id]);
    $history_rows = $stmt_prog->fetchAll(PDO::FETCH_ASSOC);

    foreach ($history_rows as $row) {
        $progress_map[$row['stage']] = [
            'status' => $row['stage_status'],
            'date'   => $row['stage_updated_at']
        ];
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
}

$ui = [
    'color' => 'primary',
    'bg'    => 'primary',
    'icon'  => 'bi-hourglass-split',
    'title' => $public_status,
    'msg'   => 'Your application is currently being processed.'
];

$status_lower = strtolower($public_status);
$is_admitted = false;

if (strpos($status_lower, 'admit') !== false || strpos($status_lower, 'congrat') !== false || strpos($status_lower, 'success') !== false) {
    $ui['color'] = 'success';
    $ui['bg']    = 'success';
    $ui['icon']  = 'bi-award-fill';
    $ui['msg']   = 'Congratulations! Action is required on your admission offer.';
    $is_admitted = true;
} 
elseif (strpos($status_lower, 'reject') !== false || strpos($status_lower, 'decline') !== false) {
    $ui['color'] = 'danger';
    $ui['bg']    = 'danger';
    $ui['icon']  = 'bi-x-circle';
    $ui['msg']   = 'We regret to inform you that this application was not successful.';
    $is_admitted = false;
} 

$submission_status = 'PENDING';
$doc_status = 'IN PROGRESS';
$ref_status = 'PENDING';
$academic_status = 'PENDING';
$final_status = 'PENDING';

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

if ($doc_total > 0 && $doc_verified >= $doc_total) {
    $doc_status = 'COMPLETED';
}

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

if (in_array($app_current_status, ['ADMISSION_APPROVED','ADMISSION_REJECTED'], true) || in_array(strtolower($public_status), ['admitted','rejected'], true)) {
    $final_status = (stripos($public_status, 'reject') !== false || $app_current_status === 'ADMISSION_REJECTED') ? 'REJECTED' : 'APPROVED';
}

$submittedLike = strtolower($public_status);
if ($submittedLike !== 'draft' && $submittedLike !== 'pending' && $app_number !== 'GENERATING...') {
    $submission_status = 'COMPLETED';
}

$defined_stages = [
    ['label' => 'Application Submitted', 'status' => $submission_status],
    ['label' => 'Documents Verified', 'status' => $doc_status],
    ['label' => 'Academic Review', 'status' => $academic_status],
    ['label' => 'Referee Reports', 'status' => $ref_status],
    ['label' => 'Final Decision', 'status' => $final_status],
];

if (in_array($final_status, ['APPROVED', 'REJECTED'], true)) {
    $submission_status = 'COMPLETED';
    $doc_status = 'COMPLETED';
    $academic_status = 'COMPLETED';
    $ref_status = 'COMPLETED';
    $defined_stages = [
        ['label' => 'Application Submitted', 'status' => $submission_status],
        ['label' => 'Documents Verified', 'status' => $doc_status],
        ['label' => 'Academic Review', 'status' => $academic_status],
        ['label' => 'Referee Reports', 'status' => $ref_status],
        ['label' => 'Final Decision', 'status' => $final_status],
    ];
}
?>

<style>
    .status-header {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 12px;
        padding: 20px;
        border-left: 6px solid var(--bs-<?php echo $ui['color']; ?>);
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    
    .tracking-wrapper {
        position: relative;
        padding: 10px 0;
    }

    .timeline-item {
        display: flex;
        position: relative;
        padding-bottom: 25px; 
    }
    
    .timeline-item::before {
        content: '';
        position: absolute;
        left: 19px; 
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e9ecef;
        z-index: 0;
    }
    
    .timeline-item:last-child::before {
        display: none; 
    }

    
    .step-dot { 
        width: 40px; 
        height: 40px; 
        border-radius: 50%; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        transition: all 0.3s ease; 
        z-index: 1;
        flex-shrink: 0; 
        margin-right: 15px;
        background: white; 
    }

    
    .step-dot.pending { background: #f8f9fa; color: #ced4da; border: 2px solid #e9ecef; }
    .step-dot.processing { background: #fff; color: var(--bs-primary); border: 2px solid var(--bs-primary); box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15); }
    .step-dot.completed { background: #198754; color: #fff; border: 2px solid #198754; }

    
    .step-content {
        padding-top: 5px;
    }

    @media (min-width: 768px) {
        .tracking-wrapper {
            display: flex;
            justify-content: space-between;
            overflow: visible;
        }
        
        .timeline-item {
            flex-direction: column;
            align-items: center;
            text-align: center;
            flex: 1;
            padding-bottom: 0;
        }

        .timeline-item::before {
            width: 100%;
            height: 2px;
            left: 50%; 
            top: 20px; 
            bottom: auto;
        }
        
        .timeline-item:last-child::before {
            display: none;
        }
        
        .step-dot {
            margin-right: 0;
            margin-bottom: 10px;
        }

        .step-content {
            padding-top: 0;
        }
    }
</style>

<div class="animate__animated animate__fadeIn pb-5">

    <div class="status-header mb-4 d-flex flex-column flex-md-row align-items-center justify-content-between gap-3 text-center text-md-start">
        <div class="d-flex flex-column flex-md-row align-items-center">
            <div class="rounded-circle bg-<?php echo $ui['bg']; ?> bg-opacity-10 p-3 mb-3 mb-md-0 me-md-3 text-<?php echo $ui['color']; ?>">
                <i class="bi <?php echo $ui['icon']; ?> fs-1"></i>
            </div>
            <div>
                <h6 class="text-uppercase text-muted mb-1" style="font-size: 11px; letter-spacing: 1px;">Current Status</h6>
                <h3 class="fw-bold mb-1 text-<?php echo $ui['color']; ?>">
                    <?php echo htmlspecialchars($ui['title']); ?>
                </h3>
                <p class="mb-0 text-muted small"><?php echo $ui['msg']; ?></p>
            </div>
        </div>
        
        <?php if($is_admitted): ?>
            <a class="btn btn-success fw-bold w-100 w-md-auto px-4 py-3 shadow-sm rounded-pill"
               href="#"
               onclick="printSlipBackground('helpers/admission-letter.php?app_no=<?php echo urlencode($app_number); ?>'); return false;">
                <i class="bi bi-download me-2"></i> Download Admission Letter
            </a>
            <iframe id="printFrame" style="display:none;"></iframe>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-5">
        <div class="col-12 col-md-6">
            <div class="p-4 border rounded-3 bg-white h-100 shadow-sm position-relative overflow-hidden d-flex flex-column justify-content-center">
                <small class="text-uppercase text-muted fw-bold" style="font-size: 11px;">Application Number</small>
                <div class="fw-bold fs-3 font-monospace mt-1 text-dark text-break">
                    <?php echo htmlspecialchars($app_number); ?>
                </div>
            </div>
        </div>
            
        <div class="col-12 col-md-6">
            
            <div class="p-4 border rounded-3 bg-white h-100 shadow-sm d-flex align-items-center justify-content-between cursor-pointer" 
     onclick="printSlipBackground('helpers/print_slip.php?app_no=<?php echo urlencode($app_number); ?>')">
    <div>
        <small class="text-uppercase text-muted fw-bold" style="font-size: 11px;">Action</small>
        <div class="fw-bold fs-5 mt-1 text-primary">Print Slip</div>
        <small class="text-muted" style="font-size: 11px;">Tap to print document</small>
    </div>
    <div class="btn btn-outline-primary rounded-circle p-3">
        <i class="bi bi-printer fs-5"></i>
    </div>
</div>
            <iframe id="printFrame" style="display:none;"></iframe>
        </div>
    </div>

    <h6 class="fw-bold text-muted border-bottom pb-2 mb-4">APPLICATION PROGRESS</h6>
    
    <div class="tracking-wrapper">
        <?php foreach($defined_stages as $stage): 
            $stage_label = $stage['label'];
            $status_code  = strtoupper($stage['status']);
            $css_class  = 'pending';
            $icon_class = 'bi-circle';
            $text_color = 'text-dark'; 
            $status_display = '<span class="badge bg-light text-secondary border">PENDING</span>'; 

            if ($status_code === 'COMPLETED') {
                $css_class  = 'completed';
                $icon_class = 'bi-check-lg';
                $text_color = 'text-success';
                $status_display = '<div class="text-success fw-bold small">COMPLETED</div>';
            } elseif ($status_code === 'IN PROGRESS') {
                $css_class  = 'processing';
                $icon_class = 'bi-arrow-repeat'; 
                $text_color = 'text-primary';
                $status_display = '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary">IN PROGRESS</span>';
            } elseif ($status_code === 'REJECTED') {
                $css_class  = 'completed';
                $icon_class = 'bi-x-circle';
                $text_color = 'text-danger';
                $status_display = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger">REJECTED</span>';
            } elseif ($status_code === 'APPROVED') {
                $css_class  = 'completed';
                $icon_class = 'bi-check-circle';
                $text_color = 'text-success';
                $status_display = '<span class="badge bg-success bg-opacity-10 text-success border border-success">APPROVED</span>';
            }
        ?>
            <div class="timeline-item">
                <div class="step-dot <?php echo $css_class; ?>">
                    <i class="bi <?php echo $icon_class; ?>"></i>
                </div>
                <div class="step-content">
                    <div class="fw-bold <?php echo $text_color; ?> mb-1">
                        <?php echo htmlspecialchars($stage_label); ?>
                    </div>
                    <div><?php echo $status_display; ?></div>
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
                Generating slip...
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
function printSlipBackground(url) {
    const toastElement = document.getElementById('pdfToast');
    if(typeof bootstrap !== 'undefined') {
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
