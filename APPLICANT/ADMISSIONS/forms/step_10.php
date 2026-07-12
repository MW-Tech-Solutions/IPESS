<?php
require_once 'db.php'; 

$application_id = $_SESSION['application_id'] ?? 0;
$app_current_status = '';
$app_status = '';
$db_app_number = '';
$is_admitted = false;

// ─── Define the 6 canonical stages ───────────────────────────────────────────
$all_stages = [
    'Application Submitted',
    'Documents Verification',
    'Referee Report',
    'Departmental Review',
    'PG Review',
    'Final Decisions',
];

// ─── Helper: Normalise old stage labels to new ones ──────────────────────────
function normalise_stage_label(string $label): string {
    $map = [
        'Documents Verified'  => 'Documents Verification',
        'Referee Reports'     => 'Referee Report',
        'Academic Review'     => 'Departmental Review',
        'Final Decision'      => 'Final Decisions',
    ];
    return $map[$label] ?? $label;
}

// ─── Fetch application base data ─────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("SELECT application_number, status, current_status FROM applications WHERE application_id = ?");
    $stmt->execute([$application_id]);
    $app_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($app_data) {
        $db_app_number     = $app_data['application_number'] ?? '';
        $app_status        = $app_data['status'] ?? '';
        $app_current_status = $app_data['current_status'] ?? '';
    }
} catch (PDOException $e) {}

// ─── Fetch admission_processing letter statuses ───────────────────────────────
$admission_letter_active   = false;
$acceptance_letter_active  = false;
$matric_number             = '';
try {
    $stmt_ap = $pdo->prepare("SELECT admission_letter_status, acceptance_letter_status, matric_number FROM admission_processing WHERE application_id = ? LIMIT 1");
    $stmt_ap->execute([$application_id]);
    $ap_row = $stmt_ap->fetch(PDO::FETCH_ASSOC);
    if ($ap_row) {
        $admission_letter_active  = ($ap_row['admission_letter_status']  === 'Active');
        $acceptance_letter_active = ($ap_row['acceptance_letter_status'] === 'Active');
        $matric_number = $ap_row['matric_number'] ?? '';
    }
} catch (PDOException $e) {}

// ─── Fetch progress rows from DB ─────────────────────────────────────────────
$progress_map = [];
try {
    $stmt_prog = $pdo->prepare("SELECT stage, stage_status, stage_updated_at FROM application_progress WHERE application_id = ?");
    $stmt_prog->execute([$application_id]);
    foreach ($stmt_prog->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $normalised_stage = normalise_stage_label($row['stage']);
        $progress_map[$normalised_stage] = [
            'status' => $row['stage_status'],
            'date'   => $row['stage_updated_at'],
        ];
    }
} catch (PDOException $e) {}

// ─── Fallback: calculate statuses from application fields ────────────────────

// "Application Submitted"
$submission_ok = ($app_status !== '' && strtolower($app_status) !== 'draft' && strtolower($app_status) !== 'pending' && $db_app_number !== '');

// "Documents Verification"
$doc_total    = 0;
$doc_verified = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE application_id = ?");
    $stmt->execute([$application_id]);
    $doc_total = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM documents d JOIN document_verification dv ON dv.upload_id = d.doc_id WHERE d.application_id = ? AND dv.verification_status = 'Verified'");
    $stmt->execute([$application_id]);
    $doc_verified = (int) $stmt->fetchColumn();
} catch (PDOException $e) {}
$docs_ok = ($doc_total > 0 && $doc_verified >= $doc_total);

// "Referee Report"
$ref_raw = null;
try {
    $stmt = $pdo->prepare("SELECT verified_status FROM referee_uploads WHERE application_id = ? ORDER BY submitted_at DESC LIMIT 1");
    $stmt->execute([$application_id]);
    $ref_raw = $stmt->fetchColumn();
} catch (PDOException $e) {}
$referee_ok = ($ref_raw === 'Verified');

// Statuses that indicate departmental / PG review stages reached
$dept_statuses = ['UNDER_DEPT_REVIEW', 'DEPT_APPROVED', 'REVIEWER_ASSIGNED', 'UNDER_REVIEWER_REVIEW', 'REVIEWER_APPROVED', 'REVIEWER_REJECTED', 'ADMIN_FINAL_REVIEW', 'ADMISSION_APPROVED', 'ADMISSION_REJECTED', 'SUBMITTED'];
$pg_statuses   = ['REVIEWER_APPROVED', 'ADMIN_FINAL_REVIEW', 'ADMISSION_APPROVED', 'ADMISSION_REJECTED'];
$final_statuses = ['ADMISSION_APPROVED', 'ADMISSION_REJECTED'];

$dept_ok  = in_array($app_current_status, $dept_statuses, true);
$pg_ok    = in_array($app_current_status, $pg_statuses, true);
$final_ok = in_array($app_current_status, $final_statuses, true) || in_array(strtolower($app_status), ['admitted', 'rejected'], true);

// ─── Build final stage progress array ────────────────────────────────────────
// Priority: DB row > calculated fallback
function resolve_stage_status(string $stage, array $progress_map, bool $fallback_done, bool $fallback_approved = false): array {
    if (isset($progress_map[$stage])) {
        $db_status = $progress_map[$stage]['status'];
        // Map DB enum values to display codes
        if ($db_status === 'Completed' || $db_status === 'COMPLETED') return ['code' => 'COMPLETED', 'date' => $progress_map[$stage]['date']];
        if ($db_status === 'In Progress' || $db_status === 'IN_PROGRESS') return ['code' => 'IN PROGRESS', 'date' => $progress_map[$stage]['date']];
        return ['code' => 'PENDING', 'date' => null];
    }
    // Fallback
    if ($fallback_approved) return ['code' => 'APPROVED', 'date' => null];
    if ($fallback_done)     return ['code' => 'COMPLETED', 'date' => null];
    return ['code' => 'PENDING', 'date' => null];
}

$is_admitted_status = (stripos($app_status, 'admit') !== false || $app_current_status === 'ADMISSION_APPROVED');
$is_rejected_status = (stripos($app_status, 'reject') !== false || $app_current_status === 'ADMISSION_REJECTED');
$is_admitted = $is_admitted_status;

// Check if final decision has been overridden in progress_map with Approved/Rejected values
$final_override = 'PENDING';
if (isset($progress_map['Final Decisions'])) {
    $fd = strtoupper($progress_map['Final Decisions']['status']);
    $final_override = in_array($fd, ['APPROVED', 'REJECTED', 'COMPLETED', 'IN PROGRESS']) ? $fd : 'PENDING';
}

$history_stages = [
    'Application Submitted' => resolve_stage_status('Application Submitted', $progress_map, $submission_ok),
    'Documents Verification' => resolve_stage_status('Documents Verification', $progress_map, $docs_ok),
    'Referee Report'         => resolve_stage_status('Referee Report', $progress_map, $referee_ok),
    'Departmental Review'    => resolve_stage_status('Departmental Review', $progress_map, $dept_ok, false),
    'PG Review'              => resolve_stage_status('PG Review', $progress_map, $pg_ok, false),
    'Final Decisions'        => ['code' => $final_override !== 'PENDING' ? $final_override : ($is_admitted_status ? 'APPROVED' : ($is_rejected_status ? 'REJECTED' : 'PENDING')), 'date' => $progress_map['Final Decisions']['date'] ?? null],
];

// ─── UI config based on overall app status ───────────────────────────────────
$ui = ['color' => 'primary', 'bg' => 'primary', 'icon' => 'bi-hourglass-split', 'title' => 'Under Review', 'msg' => 'Your application is currently being processed.'];
if ($is_admitted_status) {
    $ui = ['color' => 'success', 'bg' => 'success', 'icon' => 'bi-award-fill', 'title' => 'Admitted', 'msg' => 'Congratulations! Your admission has been approved.'];
} elseif ($is_rejected_status) {
    $ui = ['color' => 'danger', 'bg' => 'danger', 'icon' => 'bi-x-circle', 'title' => 'Not Successful', 'msg' => 'We regret to inform you that this application was not successful.'];
} elseif (strtolower($app_status) === 'submitted') {
    $ui = ['color' => 'primary', 'bg' => 'primary', 'icon' => 'bi-send-check-fill', 'title' => 'Submitted – Under Review', 'msg' => 'Your application has been submitted and is under review.'];
}
?>

<style>
    .status-header { background: #fff; border: 1px solid #e9ecef; border-radius: 14px; padding: 22px 24px; border-left: 6px solid var(--bs-<?php echo $ui['color']; ?>); box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    
    /* ── Tracking timeline ── */
    .tracking-pipeline { display: flex; align-items: flex-start; justify-content: space-between; position: relative; padding: 0; }
    .tracking-pipeline::before { content: ''; position: absolute; top: 20px; left: 20px; right: 20px; height: 2px; background: #e9ecef; z-index: 0; }
    .pipeline-step { display: flex; flex-direction: column; align-items: center; text-align: center; flex: 1; position: relative; z-index: 1; min-width: 90px; }
    .pipeline-dot { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; transition: all .25s ease; flex-shrink: 0; margin-bottom: 10px; }
    .pipeline-dot.pending     { background: #f8f9fa; color: #ced4da; border: 2px solid #e9ecef; }
    .pipeline-dot.processing  { background: #fff; color: #6EB533; border: 2px solid #6EB533; box-shadow: 0 0 0 4px rgba(110,181,51,.12); }
    .pipeline-dot.completed   { background: #198754; color: #fff; border: 2px solid #198754; }
    .pipeline-dot.approved    { background: #198754; color: #fff; border: 2px solid #198754; }
    .pipeline-dot.rejected    { background: #dc3545; color: #fff; border: 2px solid #dc3545; }
    .pipeline-label { font-size: 11px; font-weight: 700; color: #6c757d; line-height: 1.3; max-width: 90px; }
    .pipeline-label.active  { color: #6EB533; }
    .pipeline-label.done    { color: #198754; }
    .pipeline-label.failed  { color: #dc3545; }
    .pipeline-badge { font-size: 9px; margin-top: 4px; }
    .pipeline-date  { font-size: 9px; color: #adb5bd; margin-top: 2px; }

    @media (max-width: 767px) {
        .tracking-pipeline { flex-direction: column; align-items: flex-start; }
        .tracking-pipeline::before { top: 21px; left: 20px; width: 2px; height: calc(100% - 42px); right: auto; }
        .pipeline-step { flex-direction: row; align-items: flex-start; text-align: left; margin-bottom: 22px; min-width: 0; flex: none; width: 100%; }
        .pipeline-dot  { margin-bottom: 0; margin-right: 14px; flex-shrink: 0; }
        .pipeline-label { max-width: none; }
    }
</style>

<div class="animate__animated animate__fadeIn pb-5">

    <!-- Status Header -->
    <div class="status-header mb-4 d-flex flex-column flex-md-row align-items-center justify-content-between gap-3 text-center text-md-start">
        <div class="d-flex flex-column flex-md-row align-items-center">
            <div class="rounded-circle bg-<?php echo $ui['bg']; ?> bg-opacity-10 p-3 mb-3 mb-md-0 me-md-3 text-<?php echo $ui['color']; ?>">
                <i class="bi <?php echo $ui['icon']; ?> fs-1"></i>
            </div>
            <div>
                <h6 class="text-uppercase text-muted mb-1" style="font-size: 11px; letter-spacing: 1px;">Current Status</h6>
                <h3 class="fw-bold mb-1 text-<?php echo $ui['color']; ?>"><?php echo htmlspecialchars($ui['title']); ?></h3>
                <p class="mb-0 text-muted small"><?php echo $ui['msg']; ?></p>
            </div>
        </div>
        
        <?php if ($is_admitted): ?>
            <div class="d-flex flex-column flex-md-row gap-2 w-100 w-md-auto">
                <?php if ($admission_letter_active): ?>
                <a class="btn btn-success fw-bold px-4 py-3 shadow-sm rounded-pill"
                   href="#"
                   onclick="printSlipBackground('../../helpers/admission-letter.php?app_no=<?php echo urlencode($db_app_number); ?>'); return false;">
                    <i class="bi bi-download me-2"></i> Admission Letter
                </a>
                <?php endif; ?>
                <?php if ($acceptance_letter_active): ?>
                <a class="btn btn-primary fw-bold px-4 py-3 shadow-sm rounded-pill"
                   href="#"
                   onclick="printSlipBackground('../../helpers/acceptance-letter.php?app_no=<?php echo urlencode($db_app_number); ?>'); return false;">
                    <i class="bi bi-file-earmark-check me-2"></i> Acceptance Letter
                </a>
                <?php endif; ?>
                <?php if (!$admission_letter_active && !$acceptance_letter_active): ?>
                <span class="btn btn-outline-secondary fw-bold px-4 py-3 shadow-sm rounded-pill disabled">
                    <i class="bi bi-clock me-2"></i> Letters Not Yet Available
                </span>
                <?php endif; ?>
            </div>
            <iframe id="printFrame" style="display:none;"></iframe>
        <?php endif; ?>
    </div>

    <!-- Application Number & Slip -->
    <div class="row g-3 mb-5">
        <div class="col-12 col-md-6">
            <div class="p-4 border rounded-3 bg-white h-100 shadow-sm d-flex flex-column justify-content-center">
                <small class="text-uppercase text-muted fw-bold" style="font-size: 11px;">Application Number</small>
                <div class="fw-bold fs-3 font-monospace mt-1 text-dark text-break"><?php echo htmlspecialchars($db_app_number ?: 'Generating…'); ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="p-4 border rounded-3 bg-white h-100 shadow-sm d-flex flex-column justify-content-between">
                <div>
                    <small class="text-uppercase text-muted fw-bold" style="font-size: 11px;">Application Document Actions</small>
                    <div class="fw-bold fs-5 mt-1 text-dark">Manage Slip & Form</div>
                    <small class="text-muted" style="font-size: 11px;">View your completed application form or download/print the acknowledgment slip.</small>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <a href="success.php?app_no=<?php echo urlencode(encrypt_app_number($db_app_number)); ?>&view=1" target="_blank" class="btn btn-outline-primary flex-fill py-2 fw-semibold">
                        <i class="bi bi-eye me-1"></i> View Form
                    </a>
                    <a href="success.php?app_no=<?php echo urlencode(encrypt_app_number($db_app_number)); ?>" target="_blank" class="btn btn-primary flex-fill py-2 fw-semibold shadow-sm">
                        <i class="bi bi-download me-1"></i> Download Slip
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 6-Step Tracking History -->
    <h6 class="fw-bold text-muted border-bottom pb-2 mb-4">APPLICATION TRACKING HISTORY</h6>

    <div class="tracking-pipeline mb-5">
    <?php
    $step_num = 0;
    foreach ($history_stages as $stage_name => $stage_info):
        $step_num++;
        $code = strtoupper($stage_info['code']);
        $date = $stage_info['date'] ?? null;

        $dot_class    = 'pending';
        $icon_class   = 'bi-circle';
        $label_class  = '';
        $badge_html   = '<span class="badge bg-light text-secondary border pipeline-badge">PENDING</span>';

        if ($code === 'COMPLETED') {
            $dot_class   = 'completed';
            $icon_class  = 'bi-check-lg';
            $label_class = 'done';
            $badge_html  = '<span class="badge bg-success bg-opacity-10 text-success border border-success pipeline-badge">COMPLETED</span>';
        } elseif ($code === 'APPROVED') {
            $dot_class   = 'approved';
            $icon_class  = 'bi-check-circle-fill';
            $label_class = 'done';
            $badge_html  = '<span class="badge bg-success bg-opacity-10 text-success border border-success pipeline-badge">APPROVED</span>';
        } elseif ($code === 'REJECTED') {
            $dot_class   = 'rejected';
            $icon_class  = 'bi-x-circle-fill';
            $label_class = 'failed';
            $badge_html  = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger pipeline-badge">REJECTED</span>';
        } elseif ($code === 'IN PROGRESS') {
            $dot_class   = 'processing';
            $icon_class  = 'bi-arrow-repeat';
            $label_class = 'active';
            $badge_html  = '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary pipeline-badge">IN PROGRESS</span>';
        }
    ?>
        <div class="pipeline-step">
            <div class="pipeline-dot <?php echo $dot_class; ?>">
                <i class="bi <?php echo $icon_class; ?>"></i>
            </div>
            <div class="pipeline-label <?php echo $label_class; ?>">
                <?php echo htmlspecialchars($stage_name); ?>
                <div><?php echo $badge_html; ?></div>
                <?php if ($date): ?>
                    <div class="pipeline-date"><?php echo date('d M y', strtotime($date)); ?></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

</div>

<!-- PDF Toast -->
<div class="position-fixed bottom-0 start-50 translate-middle-x p-3" style="z-index: 9999; width: 90%; max-width: 400px;">
    <div id="pdfToast" class="toast align-items-center text-white bg-dark border-0 w-100" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                <div class="spinner-border spinner-border-sm me-2 text-primary" role="status"></div>
                Generating document…
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
        const frame = document.getElementById('printFrame') || document.getElementById('printFrameSlip');
        frame.src = url;
        frame.onload = function () {
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
