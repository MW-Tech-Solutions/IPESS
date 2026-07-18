<?php
session_start();
require_once __DIR__ . '/../app/bootstrap.php';

// 1. Strict Role Authorization check (SUPER_ADMIN and PORTAL_ADMIN only)
$userRole = normalize_role($_SESSION['role'] ?? '');
if ($userRole !== 'SUPER_ADMIN' && $userRole !== 'PORTAL_ADMIN') {
    http_response_code(403);
    die("<div style='font-family:sans-serif;padding:50px;text-align:center;'>
            <h1 style='color:#dc3545;'>Access Denied</h1>
            <p>Only Super Administrators and Portal Admins are allowed to access this maintenance tool.</p>
            <a href='dashboard.php' style='color:#0d6efd;text-decoration:none;'>Return to Dashboard</a>
         </div>");
}

$pageTitle = 'System Reset Tool';
$pageSubtitle = 'Perform administrative table wipes and database maintenance safely.';

// 2. Handle AJAX Reset Execution Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $confirmText = trim($_POST['confirm_text'] ?? '');
    
    if ($confirmText !== 'CONFIRM RESET') {
        echo json_encode(['success' => false, 'message' => 'Please type "CONFIRM RESET" exactly to proceed.']);
        exit;
    }

    try {
        $pdo = db();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Turn off foreign key constraints
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        if ($action === 'applicants') {
            // Delete all applicant-related details
            $pdo->exec("TRUNCATE TABLE olevel_results");
            $pdo->exec("TRUNCATE TABLE olevel_exams");
            $pdo->exec("TRUNCATE TABLE higher_education");
            $pdo->exec("TRUNCATE TABLE research_details");
            $pdo->exec("TRUNCATE TABLE documents");
            $pdo->exec("TRUNCATE TABLE document_verification");
            $pdo->exec("TRUNCATE TABLE referee_uploads");
            $pdo->exec("TRUNCATE TABLE referee_requests");
            $pdo->exec("TRUNCATE TABLE referees");
            $pdo->exec("TRUNCATE TABLE programme_choices");
            $pdo->exec("TRUNCATE TABLE personal_details");
            $pdo->exec("TRUNCATE TABLE application_status_history");
            $pdo->exec("TRUNCATE TABLE admission_processing");
            $pdo->exec("TRUNCATE TABLE supervisor_assignments");
            $pdo->exec("TRUNCATE TABLE student_profiles");
            $pdo->exec("TRUNCATE TABLE reviewer_feedback");
            $pdo->exec("TRUNCATE TABLE reviewer_history");
            $pdo->exec("TRUNCATE TABLE applications");

            // Purge student users
            $pdo->exec("DELETE FROM user_permissions WHERE user_id IN (SELECT user_id FROM users WHERE role_id = (SELECT role_id FROM roles WHERE role_key = 'STUDENT' LIMIT 1) OR role = 'STUDENT')");
            $pdo->exec("DELETE FROM password_resets WHERE user_id IN (SELECT user_id FROM users WHERE role_id = (SELECT role_id FROM roles WHERE role_key = 'STUDENT' LIMIT 1) OR role = 'STUDENT')");
            $pdo->exec("DELETE FROM users WHERE role_id = (SELECT role_id FROM roles WHERE role_key = 'STUDENT' LIMIT 1) OR role = 'STUDENT'");
            
            // Cleanup any admin accounts that were created but have no real matching admin roles
            $pdo->exec("DELETE FROM users WHERE role NOT IN ('SUPER_ADMIN', 'ICT_ADMIN', 'PORTAL_ADMIN', 'PG_SCHOOL_OFFICER', 'ADMISSIONS_OFFICER', 'DEPT_ADMIN', 'FACULTY_ADMIN', 'REVIEWER', 'SUPERVISOR', 'CENTER_LEADER') AND role_id NOT IN (SELECT role_id FROM roles WHERE role_key IN ('SUPER_ADMIN', 'ICT_ADMIN', 'PORTAL_ADMIN', 'PG_SCHOOL_OFFICER', 'ADMISSIONS_OFFICER', 'DEPT_ADMIN', 'FACULTY_ADMIN', 'REVIEWER', 'SUPERVISOR', 'CENTER_LEADER'))");

            $msg = 'All applicants, user credentials of student role, o-level forms, recommendations, higher education, research, documents, and active PG applications have been purged successfully.';

        } elseif ($action === 'verification') {
            // Delete verification logs only
            $pdo->exec("TRUNCATE TABLE document_verification");
            $pdo->exec("TRUNCATE TABLE referee_requests");
            $pdo->exec("TRUNCATE TABLE referee_uploads");
            $pdo->exec("TRUNCATE TABLE application_status_history");
            
            // Revert all application flows to step 2 Draft status
            $pdo->exec("UPDATE applications SET current_step = 2, status = 'Draft'");

            $msg = 'All verification feedback logs, referee uploads, status history logs, and supervisor routing metadata have been cleared. Applications reset to draft state.';

        } elseif ($action === 'catalog') {
            // Clear courses, departments, faculties, degree types catalog
            $pdo->exec("TRUNCATE TABLE courses");
            $pdo->exec("TRUNCATE TABLE departments");
            $pdo->exec("TRUNCATE TABLE faculties");
            $pdo->exec("TRUNCATE TABLE degree_types");

            $msg = 'Academic catalog wiped successfully. Faculty, Department, Courses, and Degree types list cleared.';
        } else {
            throw new Exception("Invalid action requested.");
        }

        // Restore foreign key constraints
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        echo json_encode(['success' => true, 'message' => $msg]);
        exit;
    } catch (Throwable $e) {
        // Safe lock: ensure foreign key check is restored
        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        } catch (Throwable $_) {}

        echo json_encode(['success' => false, 'message' => 'Reset database error: ' . $e->getMessage()]);
        exit;
    }
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Database Maintenance & Reset Tool</h1>
        <p class="panel-muted text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1"></i> Warning: Wiping actions are destructive and cannot be undone.</p>
    </div>
</section>

<div class="container-fluid mt-3">
    <div class="row g-4">
        <!-- Purge Applicants Card -->
        <div class="col-lg-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-danger text-white py-3">
                    <h5 class="mb-0"><i class="fas fa-user-slash me-2"></i>Purge Applicants & Users</h5>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <p class="text-muted small">
                            Use this to clear out all postgraduate applicants records from the portal:
                        </p>
                        <ul class="small text-muted ps-3">
                            <li>All Applicant Users and credentials</li>
                            <li>Personal details, dob, phone logs</li>
                            <li>O-Level exam cards & results</li>
                            <li>Degrees, choices & courses selection</li>
                            <li>Higher Education & Research files</li>
                            <li>Referees, verification tokens & requests</li>
                            <li>Uploaded documents (passports, certificates)</li>
                        </ul>
                    </div>
                    <div class="mt-3 pt-3 border-top">
                        <form onsubmit="handleResetSubmit(event, 'applicants')">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="check_applicants" required>
                                <label class="form-check-label small" for="check_applicants">
                                    I verify that I want to purge all applicant users and applications.
                                </label>
                            </div>
                            <div class="mb-3">
                                <input type="text" class="form-control form-control-sm" placeholder="Type: CONFIRM RESET" required>
                            </div>
                            <button type="submit" class="btn btn-danger btn-sm w-100">
                                <i class="fas fa-trash-alt me-1"></i> Purge Applicants
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Purge Verification Card -->
        <div class="col-lg-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-warning text-dark py-3">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Reset Verification Logs Only</h5>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <p class="text-muted small">
                            Preserves registered applicants, but resets their verification logs and moves them back to step 2 Draft status:
                        </p>
                        <ul class="small text-muted ps-3">
                            <li>Document Verification results logs</li>
                            <li>Referee review/uploads & recommendation logs</li>
                            <li>All historical status update logs</li>
                            <li>Resets application current step to 2</li>
                        </ul>
                    </div>
                    <div class="mt-3 pt-3 border-top">
                        <form onsubmit="handleResetSubmit(event, 'verification')">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="check_verification" required>
                                <label class="form-check-label small" for="check_verification">
                                    I verify that I want to reset verification status.
                                </label>
                            </div>
                            <div class="mb-3">
                                <input type="text" class="form-control form-control-sm" placeholder="Type: CONFIRM RESET" required>
                            </div>
                            <button type="submit" class="btn btn-warning btn-sm w-100 text-dark">
                                <i class="fas fa-sync me-1"></i> Reset Verifications
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Purge Academic Catalog Card -->
        <div class="col-lg-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="mb-0"><i class="fas fa-book me-2"></i>Wipe Academic Catalog</h5>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <p class="text-muted small">
                            Purges the courses catalog framework completely. Use this if you need to re-seed or migrate academic entities:
                        </p>
                        <ul class="small text-muted ps-3">
                            <li>Degree Types list (MSc, PhD, PGD)</li>
                            <li>Faculties and departments list</li>
                            <li>All Courses database records</li>
                        </ul>
                        <p class="text-danger small mt-2">
                            <i class="fas fa-exclamation-triangle me-1"></i> Warning: Wiping the catalog while applicants exist will break applicant choices.
                        </p>
                    </div>
                    <div class="mt-3 pt-3 border-top">
                        <form onsubmit="handleResetSubmit(event, 'catalog')">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="check_catalog" required>
                                <label class="form-check-label small" for="check_catalog">
                                    I verify that I want to delete the courses catalog.
                                </label>
                            </div>
                            <div class="mb-3">
                                <input type="text" class="form-control form-control-sm" placeholder="Type: CONFIRM RESET" required>
                            </div>
                            <button type="submit" class="btn btn-outline-dark btn-sm w-100">
                                <i class="fas fa-folder-minus me-1"></i> Wipe Catalog
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function handleResetSubmit(event, action) {
    event.preventDefault();
    const form = event.target;
    const checkbox = form.querySelector('input[type="checkbox"]');
    const input = form.querySelector('input[type="text"]');
    const btn = form.querySelector('button[type="submit"]');

    if (!checkbox.checked) {
        alert("Please check the verification checkbox.");
        return;
    }

    if (input.value.trim() !== 'CONFIRM RESET') {
        alert('Please type "CONFIRM RESET" in the text box to execute this action.');
        return;
    }

    if (!confirm("Are you absolutely sure you want to proceed? This database write operation is completely permanent.")) {
        return;
    }

    btn.disabled = true;
    const oldBtnText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Wiping...';

    try {
        const response = await fetch(`reset.php?action=${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `confirm_text=${encodeURIComponent(input.value.trim())}`
        });
        const data = await response.json();
        if (data.success) {
            alert("Success: " + data.message);
            // Reset input values
            input.value = '';
            checkbox.checked = false;
        } else {
            alert("Error: " + data.message);
        }
    } catch (e) {
        alert("Network error: " + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = oldBtnText;
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
