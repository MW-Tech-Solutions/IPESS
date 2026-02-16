<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    redirect_to('APPLICANT/ADMISSIONS/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT 
        a.application_id, 
        a.status, 
        a.current_step as db_step, 
        d.file_path as passport 
    FROM applications a 
    LEFT JOIN documents d 
        ON a.application_id = d.application_id 
        AND d.document_type IN ('passport_profile','passport')
    WHERE a.user_id = ? 
    ORDER BY 
        CASE WHEN d.document_type = 'passport_profile' THEN 0 ELSE 1 END,
        a.updated_at DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$app_data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($app_data) {
    $_SESSION['application_id'] = $app_data['application_id'];
    $_SESSION['passport_path'] = $app_data['passport'];

    if ($app_data['status'] === 'Submitted') {
        $current_step = 10;
    } else {
        $current_step = isset($_GET['step']) ? (int)$_GET['step'] : (int)$app_data['db_step'];
    }
} else {
    $current_step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
}

function resolve_passport_path(?string $path): string {
    if (!$path) {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $path = ltrim($path, '/');
    $local = __DIR__ . '/' . $path;
    if (file_exists($local)) {
        return $path;
    }
    $alt = __DIR__ . '/../../' . $path;
    if (file_exists($alt)) {
        return '../' . $path;
    }
    return $path;
}

$passportSrc = resolve_passport_path($_SESSION['passport_path'] ?? '') ?: 'assets/img/default-avatar.png';

if ($current_step > 10) $current_step = 10;

$nav_steps = [
    1 => ['icon' => 'bi-person', 'title' => 'Personal Info'],
    2 => ['icon' => 'bi-mortarboard', 'title' => 'Programme'],
    3 => ['icon' => 'bi-book', 'title' => 'Academic'],
    4 => ['icon' => 'bi-flag', 'title' => 'NYSC'],
    5 => ['icon' => 'bi-briefcase', 'title' => 'Experience'],
    6 => ['icon' => 'bi-lightbulb', 'title' => 'Research'],
    7 => ['icon' => 'bi-people', 'title' => 'Referees'],
    8 => ['icon' => 'bi-upload', 'title' => 'Documents'],
    9 => ['icon' => 'bi-check-circle', 'title' => 'Submit'],
    10 => ['icon' => 'bi-bar-chart-steps', 'title' => 'Admission Status']
];

$step_file = "forms/step_{$current_step}.php";
?>
<?php if (isset($_SESSION['msg'])): ?>
    <div class="alert alert-<?= $_SESSION['msg']['type']; ?> alert-dismissible fade show" role="alert">
        <strong><?= $_SESSION['msg']['type'] == 'danger' ? 'Wait!' : 'Success!'; ?></strong> 
        <?= $_SESSION['msg']['text']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['msg']);  ?>
<?php endif; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root { --primary-blue: #1a4388; --bg-gray: #f8f9fa; }
        body { background-color: var(--bg-gray); font-family: 'Inter', sans-serif; font-size: 14px; }

        .mobile-header {
            background: #fff;
            padding: 10px 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1020;
        }

        .profile-widget {
            display: flex;
            align-items: center;
        }
        .passport-photo {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: fill;
            border: 2px solid #fff;
            background: #ddd;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        @media (max-width: 991.98px) {
            .desktop-sidebar { display: none; }
            .main-content { padding: 15px !important; }
        }

        .nav-link { color: #555; border-radius: 8px; margin-bottom: 5px; padding: 12px; transition: 0.2s; }
        .nav-link.active { background: var(--primary-blue); color: #fff !important; }
        .nav-link.completed { color: #198754; background: #e8f5e9; }

        .form-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .form-control, .form-select { padding: 12px; font-size: 16px; }
    </style>
</head>
<body>

<header class="mobile-header d-lg-none d-flex justify-content-between align-items-center">
    <button class="btn btn-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu">
        <i class="bi bi-list fs-4"></i>
    </button>
    <div class="profile-widget">
            <img src="<?php echo htmlspecialchars($passportSrc); ?>" class="passport-photo">
    </div>
</header>

<div class="container-fluid">
    <div class="row">
        <nav class="col-lg-3 col-xl-2 desktop-sidebar vh-100 sticky-top bg-white p-3 border-end">
            <div class="d-flex align-items-center mb-4 px-3">
                <i class="bi bi-mortarboard-fill fs-3 text-primary me-2"></i>
                <span class="fs-5 fw-bold text-dark">PG</span>
            </div>
            <div class="nav flex-column nav-pills">
                <!-- <?php foreach ($nav_steps as $key => $val): ?>
                    <a href="?step=<?php echo $key; ?>" class="nav-link <?php echo ($key == $current_step) ? 'active' : (($key < $current_step) ? 'completed' : 'disabled'); ?>">
                        <i class="bi <?php echo ($key < $current_step) ? 'bi-check-circle-fill' : $val['icon']; ?> me-2"></i>
                        <?php echo $val['title']; ?>
                    </a>
                <?php endforeach; ?> -->
                <?php foreach ($nav_steps as $key => $val): 
    if ($current_step == 10) {
        $link_class = ($key == 10) ? 'active' : 'disabled';
    } else {
        $link_class = ($key == $current_step) ? 'active' : (($key < $current_step) ? 'completed' : 'disabled');
    }
?>
    <a href="?step=<?php echo $key; ?>" class="nav-link <?php echo $link_class; ?>">
        <i class="bi <?php echo ($key < $current_step) ? 'bi-check-circle-fill' : $val['icon']; ?> me-2"></i>
        <?php echo $val['title']; ?>
    </a>
<?php endforeach; ?>
                <hr>
                <a href="logout.php" class="nav-link text-danger mt-auto">
                    <i class="bi bi-power me-2"></i> Logout
                </a>
            </div>
        </nav>

        <main class="col-lg-9 col-xl-10 main-content p-lg-5 p-3">
            <div class="d-none d-lg-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-0"><?php echo $nav_steps[$current_step]['title']; ?></h2>
                    <small class="text-muted">Step <?php echo $current_step; ?> of 10</small>
                </div>
                <div class="profile-widget">
                        <img src="<?php echo htmlspecialchars($passportSrc); ?>" class="passport-photo">
                </div>
            </div>

            <div class="form-card">
                <form id="stepForm" action="save_progress.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="step_id" value="<?php echo $current_step; ?>">
                    
                    <?php 
                    if(file_exists($step_file)) {
                        include $step_file; 
                    } else {
                        echo "<div class='alert alert-warning'>Section file not found.</div>";
                    }
                    ?>

                    <?php if ($current_step != 10): ?>
                        <div class="d-flex flex-column flex-md-row justify-content-between mt-5 gap-3">
                            <?php if($current_step > 1): ?>
                                <a href="dashboard.php?step=<?php echo $current_step - 1; ?>" class="btn btn-light border p-3 order-2 order-md-1">
                                    <i class="bi bi-chevron-left"></i> Previous
                                </a>
                            <?php else: ?>
                                <div></div>
                            <?php endif; ?>

                            <button type="submit" class="btn btn-primary p-3 px-md-5 fw-bold order-1 order-md-2 shadow-sm">
                                <?php echo ($current_step == 9) ? 'Submit Application' : 'Save & Continue'; ?> 
                                <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </main>
    </div>
</div>

<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileMenu" style="width: 280px;">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold text-primary">Application Steps</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div class="nav flex-column nav-pills">
            <?php foreach ($nav_steps as $key => $val): ?>
                <a href="?step=<?php echo $key; ?>" class="nav-link py-3 <?php echo ($key == $current_step) ? 'active' : (($key < $current_step) ? 'completed' : 'disabled'); ?>">
                    <i class="bi <?php echo ($key < $current_step) ? 'bi-check-circle-fill' : $val['icon']; ?> me-2"></i>
                    <?php echo $val['title']; ?>
                </a>
            <?php endforeach; ?>
            <hr>
            <a href="logout.php" class="nav-link text-danger"><i class="bi bi-power me-2"></i> Logout</a>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmSubmitModal" tabindex="-1" aria-labelledby="confirmSubmitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="confirmSubmitModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Final Confirmation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="fw-bold">Are you sure you want to submit your application?</p>
                <p class="text-muted">Once submitted, you will <strong>not</strong> be able to edit your documents or any other information in this application.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="cancelSubmitBtn">Cancel</button>
                
                <button type="button" class="btn btn-primary" id="finalSubmitBtn">
                    <span id="modalSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    <span id="modalBtnText">Yes, Submit Application</span>
                </button>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
    const stepForm = document.getElementById('stepForm');
    const finalSubmitBtn = document.getElementById('finalSubmitBtn');
    const cancelSubmitBtn = document.getElementById('cancelSubmitBtn');
    const modalSpinner = document.getElementById('modalSpinner');
    const modalBtnText = document.getElementById('modalBtnText');
    const currentStep = <?php echo $current_step; ?>;
    const submitModal = new bootstrap.Modal(document.getElementById('confirmSubmitModal'));

    if (stepForm && currentStep === 9) {
        stepForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitModal.show();
        });

        finalSubmitBtn.addEventListener('click', function() {
            finalSubmitBtn.disabled = true;
            if(cancelSubmitBtn) cancelSubmitBtn.disabled = true;

            modalSpinner.classList.remove('d-none');
            modalBtnText.innerText = " Submitting...";

            stepForm.submit();
        });
    }
});
</script>

</body>
</html>
