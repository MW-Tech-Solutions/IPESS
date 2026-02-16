<?php
// forms/step_10.php

// 1. Mock Database Status
$app_status = 'Dept. Review'; 
$date_submitted = "Jan 03, 2026";

// 2. UI Configuration
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
            <button type="button" class="btn btn-success fw-bold px-4 py-2 shadow-sm">
                <i class="bi bi-download me-2"></i> Admission Letter
            </button>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-5">
        <div class="col-md-6">
            <div class="p-3 border rounded-3 bg-white h-100">
                <small class="text-uppercase text-muted fw-bold" style="font-size: 11px;">Application ID</small>
                <div class="fw-bold fs-5">PG-2026-<?php echo str_pad($_SESSION['application_id'] ?? 0, 4, '0', STR_PAD_LEFT); ?></div>
            </div>
        </div>
        <div class="col-md-6">
    <div class="p-3 border rounded-3 bg-white h-100 d-flex justify-content-between align-items-center">
        <div>
            <small class="text-uppercase text-muted fw-bold" style="font-size: 11px;">Application Slip</small>
            <div class="fw-bold fs-5">Ready</div>
        </div>
        <a href="success.php?app_no=PG-2026-<?php echo str_pad($_SESSION['application_id'] ?? 0, 4, '0', STR_PAD_LEFT); ?>" 
           target="_blank" 
           class="btn btn-sm btn-outline-dark rounded-pill">
           Download
        </a>
    </div>
</div>
    </div>

    <h6 class="fw-bold text-muted border-bottom pb-2 mb-4">TRACKING HISTORY</h6>
    <div class="d-flex justify-content-between position-relative">
        <div class="position-absolute top-0 start-0 w-100 bg-light" style="height: 2px; top: 20px; z-index: 0;"></div>

        <?php 
        // --- FIX START ---
        // Renamed $steps to $history_steps so it doesn't break the main menu
        $history_steps = ['Submitted', 'Fees Paid', 'Dept. Review', 'Interview', 'Decision'];
        
        foreach($history_steps as $index => $label): 
        // --- FIX END ---
        
            // Simple Logic for demo: If admitted, all complete. If Review, only first 3.
            $state = 'pending';
            $icon = 'bi-circle';
            
            if($app_status == 'Admitted') {
                $state = 'completed';
                $icon = 'bi-check-lg';
            } elseif ($app_status == 'Dept. Review') {
                if($index <= 2) { $state = ($index == 2) ? 'active' : 'completed'; }
                if($index == 2) $icon = 'bi-search';
                if($index < 2) $icon = 'bi-check-lg';
            }
        ?>
            <div class="timeline-step" style="z-index: 1;">
                <div class="step-dot <?php echo $state; ?>">
                    <i class="bi <?php echo $icon; ?>"></i>
                </div>
                <div class="small fw-bold mt-2 <?php echo ($state == 'active') ? 'text-primary' : 'text-dark'; ?>"><?php echo $label; ?></div>
                <div class="small text-muted" style="font-size: 10px;">
                    <?php echo ($state == 'completed') ? 'Done' : (($state == 'active') ? 'Processing' : 'Pending'); ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>









<!-- <?php
// forms/step_10.php

// 1. Mock Database Status (Replace with real DB logic)
// $app_status = $app_data['status']; 
$app_status = 'Dept. Review'; // Options: 'Dept. Review', 'Admitted', 'Rejected'
$date_submitted = "Jan 03, 2026";

// 2. UI Configuration
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
            <button type="button" class="btn btn-success fw-bold px-4 py-2 shadow-sm">
                <i class="bi bi-download me-2"></i> Admission Letter
            </button>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-5">
        <div class="col-md-6">
            <div class="p-3 border rounded-3 bg-white h-100">
                <small class="text-uppercase text-muted fw-bold" style="font-size: 11px;">Application ID</small>
                <div class="fw-bold fs-5">PG-2026-<?php echo str_pad($_SESSION['application_id'] ?? 0, 4, '0', STR_PAD_LEFT); ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="p-3 border rounded-3 bg-white h-100 d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-uppercase text-muted fw-bold" style="font-size: 11px;">Application Slip</small>
                    <div class="fw-bold fs-5">Ready</div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-dark rounded-pill">Download</button>
            </div>
        </div>
    </div>

    <h6 class="fw-bold text-muted border-bottom pb-2 mb-4">TRACKING HISTORY</h6>
    <div class="d-flex justify-content-between position-relative">
        <div class="position-absolute top-0 start-0 w-100 bg-light" style="height: 2px; top: 20px; z-index: 0;"></div>

        <?php 
        $steps = ['Submitted', 'Fees Paid', 'Dept. Review', 'Interview', 'Decision'];
        foreach($steps as $index => $label): 
            // Simple Logic for demo: If admitted, all complete. If Review, only first 3.
            $state = 'pending';
            $icon = 'bi-circle';
            
            if($app_status == 'Admitted') {
                $state = 'completed';
                $icon = 'bi-check-lg';
            } elseif ($app_status == 'Dept. Review') {
                if($index <= 2) { $state = ($index == 2) ? 'active' : 'completed'; }
                if($index == 2) $icon = 'bi-search';
                if($index < 2) $icon = 'bi-check-lg';
            }
        ?>
            <div class="timeline-step" style="z-index: 1;">
                <div class="step-dot <?php echo $state; ?>">
                    <i class="bi <?php echo $icon; ?>"></i>
                </div>
                <div class="small fw-bold mt-2 <?php echo ($state == 'active') ? 'text-primary' : 'text-dark'; ?>"><?php echo $label; ?></div>
                <div class="small text-muted" style="font-size: 10px;">
                    <?php echo ($state == 'completed') ? 'Done' : (($state == 'active') ? 'Processing' : 'Pending'); ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
 -->
