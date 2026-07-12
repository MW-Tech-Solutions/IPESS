<?php
$pageTitle = 'ICT Admissions processing';
$pageSubtitle = 'Issue student matric numbers, register incoming candidates, and manage letters activation.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
require_once __DIR__ . '/../../ADMIN/admin/includes/db.php';

$stats = ['approved' => 0, 'matric_generated' => 0, 'letters_active' => 0, 'completed' => 0];
$recentLogs = [];

if (isset($pdo)) {
    try {
        // 1. Total Approved by PG School but not yet matric generated
        $stats['approved'] = (int) $pdo->query("
            SELECT COUNT(*) 
            FROM applications a
            LEFT JOIN admission_processing ap ON a.application_id = ap.application_id
            WHERE a.current_status = 'APPROVED_BY_POSTGRADUATE_SCHOOL' AND (ap.matric_number IS NULL OR ap.matric_number = '')
        ")->fetchColumn();

        // 2. Matric Generated
        $stats['matric_generated'] = (int) $pdo->query("
            SELECT COUNT(*) 
            FROM admission_processing 
            WHERE matric_number IS NOT NULL AND matric_number != ''
        ")->fetchColumn();

        // 3. Letters Activated
        $stats['letters_active'] = (int) $pdo->query("
            SELECT COUNT(*) 
            FROM admission_processing 
            WHERE acceptance_letter_status = 'Active' OR admission_letter_status = 'Active'
        ")->fetchColumn();

        // 4. Completed (Both letters active and status = 'Admitted'/'ADMISSION_APPROVED')
        $stats['completed'] = (int) $pdo->query("
            SELECT COUNT(*) 
            FROM applications a
            JOIN admission_processing ap ON a.application_id = ap.application_id
            WHERE a.status = 'Admitted' OR a.current_status = 'ADMISSION_APPROVED'
        ")->fetchColumn();

        // 5. Recent Logs
        $logQuery = "
            SELECT wal.*, pd.first_name, pd.surname, u.full_name AS actor_name
            FROM workflow_audit_logs wal
            LEFT JOIN personal_details pd ON wal.applicant_id = pd.application_id
            LEFT JOIN users u ON wal.user_id = u.user_id
            WHERE wal.action LIKE '%Matric%' OR wal.action LIKE '%Letter%' OR wal.action LIKE '%ICT%'
            ORDER BY wal.timestamp DESC
            LIMIT 8
        ";
        $recentLogs = $pdo->query($logQuery)->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("ICT Staff Dashboard Error: " . $e->getMessage());
    }
}
?>

<section class="page-hero">
    <div>
        <h1>ICT Admissions Processing Desk</h1>
        <p class="panel-muted">Generate matriculation/student numbers, activate admission letters, and finalise applicant admission processes.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-primary" href="admissions.php">
            <i class="fas fa-id-card me-2"></i>Process Candidate Admissions
        </a>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon" style="color: #6EB533;"><i class="fas fa-hourglass-half"></i></div>
        <div>
            <div class="stat-title">Awaiting Processing</div>
            <div class="stat-value"><?= number_format($stats['approved']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon text-warning"><i class="fas fa-barcode"></i></div>
        <div>
            <div class="stat-title">Matric Generated</div>
            <div class="stat-value"><?= number_format($stats['matric_generated']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon text-info"><i class="fas fa-envelope-open-text"></i></div>
        <div>
            <div class="stat-title">Letters Activated</div>
            <div class="stat-value"><?= number_format($stats['letters_active']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon text-success"><i class="fas fa-user-check"></i></div>
        <div>
            <div class="stat-title">Admissions Completed</div>
            <div class="stat-value"><?= number_format($stats['completed']) ?></div>
        </div>
    </div>
</section>

<div class="row g-4 mt-1">
    <div class="col-lg-12">
        <section class="panel">
            <div class="panel-header border-bottom-0 pb-0">
                <h3 class="panel-title">Recent ICT Processing Logs</h3>
                <div class="panel-muted">Audit trail of student number generations and letter activations.</div>
            </div>
            <div class="panel-body">
                <?php if (!empty($recentLogs)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-sm">
                            <thead>
                                <tr>
                                    <th>Officer</th>
                                    <th>Applicant</th>
                                    <th>Action Perform</th>
                                    <th>Status Value</th>
                                    <th>Log Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentLogs as $log): ?>
                                    <?php
                                    $actor = htmlspecialchars($log['actor_name'] ?: $log['role'] ?: 'ICT Officer');
                                    $applicant = htmlspecialchars(trim(($log['first_name'] ?? '') . ' ' . ($log['surname'] ?? '')) ?: 'N/A');
                                    ?>
                                    <tr>
                                        <td><strong><?= $actor ?></strong></td>
                                        <td><?= $applicant ?></td>
                                        <td><?= htmlspecialchars($log['action']) ?></td>
                                        <td><span class="badge bg-success"><?= htmlspecialchars($log['new_status'] ?: 'Success') ?></span></td>
                                        <td class="text-muted"><?= date('M d, Y H:i', strtotime($log['timestamp'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-4">No recent ICT processing activities recorded.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
