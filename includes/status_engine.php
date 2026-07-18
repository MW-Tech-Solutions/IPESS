<?php
// Shared workflow status engine + audit + notifications.

function workflow_status_map(): array {
    return [
        // Admission workflow
        'DRAFT' => ['label' => 'Draft', 'category' => 'admission'],
        'SUBMITTED' => ['label' => 'Submitted', 'category' => 'admission'],
        'ASSIGNED_TO_DEPARTMENT' => ['label' => 'Assigned to Department', 'category' => 'admission'],
        'UNDER_DEPT_REVIEW' => ['label' => 'Under Dept Review', 'category' => 'admission'],
        'ACTION_REQUIRED_DOCS' => ['label' => 'Action Required (Docs)', 'category' => 'admission'],
        'TOPIC_REJECTED' => ['label' => 'Topic Rejected', 'category' => 'admission'],
        'DEPT_APPROVED' => ['label' => 'Department Approved', 'category' => 'admission'],
        'DEPT_REJECTED' => ['label' => 'Department Rejected', 'category' => 'admission'],
        'REVIEWER_ASSIGNED' => ['label' => 'Reviewer Assigned', 'category' => 'admission'],
        'UNDER_REVIEWER_REVIEW' => ['label' => 'Under Reviewer Review', 'category' => 'admission'],
        'REVIEWER_APPROVED' => ['label' => 'Reviewer Approved', 'category' => 'admission'],
        'REVIEWER_REJECTED' => ['label' => 'Reviewer Rejected', 'category' => 'admission'],
        'ACTION_REQUIRED_REVIEW' => ['label' => 'Action Required (Review)', 'category' => 'admission'],
        'ICT_VETTED' => ['label' => 'ICT Vetted', 'category' => 'admission'],
        'HOD_VERIFIED' => ['label' => 'HOD Verified', 'category' => 'admission'],
        'COLLEGE_PENDING' => ['label' => 'College Pending', 'category' => 'admission'],
        'APPROVED_BY_POSTGRADUATE_SCHOOL' => ['label' => 'Approved by PG School', 'category' => 'admission'],
        'REJECTED_BY_POSTGRADUATE_SCHOOL' => ['label' => 'Rejected by PG School', 'category' => 'admission'],
        'ADMIN_FINAL_REVIEW' => ['label' => 'Admin Final Review', 'category' => 'admission'],
        'ADMISSION_APPROVED' => ['label' => 'Admission Approved', 'category' => 'admission'],
        'ADMISSION_REJECTED' => ['label' => 'Admission Rejected', 'category' => 'admission'],

        // Project workflow (post-admission)
        'PROJECT_ACTIVE' => ['label' => 'Project Active', 'category' => 'project'],
        'PROPOSAL_SUBMITTED' => ['label' => 'Proposal Submitted', 'category' => 'project'],
        'PROPOSAL_REJECTED' => ['label' => 'Proposal Rejected', 'category' => 'project'],
        'PROPOSAL_APPROVED' => ['label' => 'Proposal Approved', 'category' => 'project'],
        'REPORT_SUBMITTED' => ['label' => 'Report Submitted', 'category' => 'project'],
        'REPORT_UNDER_REVIEW' => ['label' => 'Report Under Review', 'category' => 'project'],
        'REPORT_REVIEWED' => ['label' => 'Report Reviewed', 'category' => 'project'],
        'PROJECT_COMPLETED' => ['label' => 'Project Completed', 'category' => 'project'],
    ];
}

function table_exists(PDO $pdo, string $table): bool {
    try {
        $sanitizedTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $pdo->query("SELECT 1 FROM `{$sanitizedTable}` LIMIT 0");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function column_exists(PDO $pdo, string $table, string $column): bool {
    try {
        $sanitizedTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $sanitizedColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $pdo->query("SELECT `{$sanitizedColumn}` FROM `{$sanitizedTable}` LIMIT 0");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function log_application_history(PDO $pdo, int $application_id, ?string $from_status, string $to_status, ?int $actor_id, ?string $actor_role, ?string $note = null): void {
    if (!table_exists($pdo, 'application_status_history')) {
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO application_status_history (application_id, from_status, to_status, actor_id, actor_role, note, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$application_id, $from_status, $to_status, $actor_id, $actor_role, $note]);
}

function log_audit(PDO $pdo, string $event, ?int $user_id, string $details): void {
    if (!table_exists($pdo, 'audit_logs')) {
        return;
    }
    try {
        $cols = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'audit_logs'");
        $cols->execute();
        $available = array_map('strtolower', $cols->fetchAll(PDO::FETCH_COLUMN));

        if (in_array('actor_user_id', $available, true)) {
            $stmt = $pdo->prepare("INSERT INTO audit_logs (actor_user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$user_id, $event, $details]);
            return;
        }

        if (in_array('event', $available, true)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO audit_logs (event, user, details, timestamp) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$event, $user_id, $details]);
                return;
            } catch (PDOException $e) {
                // Fall through to legacy insert if schema is different.
            }
        }

        // Fallback for legacy schemas
        if (in_array('action', $available, true)) {
            $stmt = $pdo->prepare("INSERT INTO audit_logs (action, user_id, description, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$event, $user_id, $details]);
        }
    } catch (PDOException $e) {
        return;
    }
}

function notify_user(PDO $pdo, int $user_id, string $title, string $message, string $type = 'info'): void {
    // Primary notifications table
    try {
        if (table_exists($pdo, 'notifications')) {
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $title, $message, $type]);
        }
    } catch (Throwable $e) {
        error_log("Failed to insert primary notification: " . $e->getMessage());
    }

    // Backfill to applicant_notifications for existing UI
    try {
        if (table_exists($pdo, 'applicant_notifications')) {
            $stmt = $pdo->prepare("INSERT INTO applicant_notifications (application_id, notification_title, notification_message, is_read, created_at) VALUES ((SELECT application_id FROM applications WHERE user_id = ? ORDER BY application_id DESC LIMIT 1), ?, ?, 0, NOW())");
            $stmt->execute([$user_id, $title, $message]);
        }
    } catch (Throwable $e) {
        error_log("Failed to insert applicant notification backfill: " . $e->getMessage());
    }
}

function update_application_status(PDO $pdo, int $application_id, string $new_status, array $context = []): bool {
    $map = workflow_status_map();
    if (!isset($map[$new_status])) {
        throw new InvalidArgumentException("Invalid status: {$new_status}");
    }

    $actor_id = $context['actor_id'] ?? null;
    $actor_role = $context['actor_role'] ?? null;
    $note = $context['note'] ?? null;

    $hasCurrentStatus = column_exists($pdo, 'applications', 'current_status');
    $hasStatus = column_exists($pdo, 'applications', 'status');

    $selectCols = [];
    if ($hasCurrentStatus) {
        $selectCols[] = 'current_status';
    }
    if ($hasStatus) {
        $selectCols[] = 'status';
    }
    $selectCols[] = 'user_id';

    $stmt = $pdo->prepare("SELECT " . implode(', ', $selectCols) . " FROM applications WHERE application_id = ?");
    $stmt->execute([$application_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException("Application not found.");
    }

    $prev_status = $hasCurrentStatus ? ($row['current_status'] ?? null) : ($row['status'] ?? null);
    $status_for_ui = $hasStatus ? ($row['status'] ?? 'Draft') : 'Draft';

    if ($new_status === 'SUBMITTED') {
        $status_for_ui = 'Submitted';
    } elseif ($new_status === 'ADMISSION_APPROVED') {
        $status_for_ui = 'Admitted';
    } elseif ($new_status === 'ADMISSION_REJECTED') {
        $status_for_ui = 'Rejected';
    }

    $setParts = [];
    $params = [];
    if ($hasCurrentStatus) {
        $setParts[] = "current_status = ?";
        $params[] = $new_status;
    }
    if ($hasStatus) {
        $setParts[] = "status = ?";
        $params[] = $status_for_ui;
    }
    if (!$setParts) {
        throw new RuntimeException("Applications table missing status columns.");
    }
    $updateSql = "UPDATE applications SET " . implode(', ', $setParts) . ", updated_at = NOW()";

    if ($new_status === 'SUBMITTED') {
        $updateSql .= ", submitted_at = COALESCE(submitted_at, NOW())";
    }
    if ($new_status === 'ADMISSION_APPROVED' && column_exists($pdo, 'applications', 'approved_at')) {
        $updateSql .= ", approved_at = NOW()";
    }
    if (!empty($context['department_id'])) {
        $updateSql .= ", department_id = ?";
        $params[] = (int) $context['department_id'];
    }
    // Support both keys and map to database column reviewer_id
    $revId = $context['reviewer_id'] ?? $context['assigned_reviewer_id'] ?? null;
    if (!empty($revId)) {
        $updateSql .= ", reviewer_id = ?";
        $params[] = (int) $revId;
    }

    $updateSql .= " WHERE application_id = ?";
    $params[] = $application_id;

    $stmt = $pdo->prepare($updateSql);
    $stmt->execute($params);

    log_application_history($pdo, $application_id, $prev_status, $new_status, $actor_id, $actor_role, $note);
    log_audit($pdo, 'Application Status Update', $actor_id, "Application {$application_id}: {$prev_status} -> {$new_status}");

    // Automatically update application_progress tracking table stages
    try {
        if (table_exists($pdo, 'application_progress')) {
            $setStageProgress = function($appId, $stage, $status) use ($pdo) {
                $check = $pdo->prepare("SELECT progress_id FROM application_progress WHERE application_id = ? AND stage = ?");
                $check->execute([$appId, $stage]);
                if ($check->fetch()) {
                    $pdo->prepare("UPDATE application_progress SET stage_status = ?, stage_updated_at = NOW() WHERE application_id = ? AND stage = ?")
                        ->execute([$status, $appId, $stage]);
                } else {
                    $pdo->prepare("INSERT INTO application_progress (application_id, stage, stage_status, stage_updated_at) VALUES (?, ?, ?, NOW())")
                        ->execute([$appId, $stage, $status]);
                }
            };

            // Application Submitted
            if (in_array($new_status, ['SUBMITTED', 'ASSIGNED_TO_DEPARTMENT', 'UNDER_DEPT_REVIEW', 'HOD_VERIFIED', 'DEPT_APPROVED', 'COLLEGE_PENDING', 'APPROVED_BY_POSTGRADUATE_SCHOOL', 'REJECTED_BY_POSTGRADUATE_SCHOOL', 'ADMISSION_APPROVED', 'ADMISSION_REJECTED'], true)) {
                $setStageProgress($application_id, 'Application Submitted', 'Completed');
            }

            // Departmental Review
            $dept_done_statuses = ['DEPT_APPROVED', 'COLLEGE_PENDING', 'REVIEWER_ASSIGNED', 'UNDER_REVIEWER_REVIEW', 'REVIEWER_APPROVED', 'REVIEWER_REJECTED', 'ADMIN_FINAL_REVIEW', 'ADMISSION_APPROVED', 'ADMISSION_REJECTED'];
            if (in_array($new_status, $dept_done_statuses, true)) {
                $setStageProgress($application_id, 'Departmental Review', 'Completed');
            } elseif (in_array($new_status, ['ASSIGNED_TO_DEPARTMENT', 'UNDER_DEPT_REVIEW', 'HOD_VERIFIED'], true)) {
                $setStageProgress($application_id, 'Departmental Review', 'In Progress');
            }

            // PG Review
            $pg_done_statuses = ['APPROVED_BY_POSTGRADUATE_SCHOOL', 'REJECTED_BY_POSTGRADUATE_SCHOOL', 'REVIEWER_APPROVED', 'ADMIN_FINAL_REVIEW', 'ADMISSION_APPROVED', 'ADMISSION_REJECTED'];
            if (in_array($new_status, $pg_done_statuses, true)) {
                $setStageProgress($application_id, 'PG Review', 'Completed');
                $setStageProgress($application_id, 'PG School Review', 'Completed');
            } elseif (in_array($new_status, ['COLLEGE_PENDING', 'REVIEWER_ASSIGNED', 'UNDER_REVIEWER_REVIEW'], true)) {
                $setStageProgress($application_id, 'PG Review', 'In Progress');
                $setStageProgress($application_id, 'PG School Review', 'In Progress');
            }

            // Final Decisions
            if ($new_status === 'ADMISSION_APPROVED') {
                $setStageProgress($application_id, 'Final Decisions', 'Approved');
            } elseif ($new_status === 'ADMISSION_REJECTED') {
                $setStageProgress($application_id, 'Final Decisions', 'Rejected');
            }
        }
    } catch (Throwable $progressEx) {
        error_log('Error updating stage progress: ' . $progressEx->getMessage());
    }

    if (!empty($context['notify_user_id'])) {
        notify_user($pdo, (int) $context['notify_user_id'], $context['notify_title'] ?? 'Application Update', $context['notify_message'] ?? "Your application status is now {$map[$new_status]['label']}.");
    }

    return true;
}

function update_project_status(PDO $pdo, int $project_id, string $new_status, array $context = []): bool {
    $map = workflow_status_map();
    if (!isset($map[$new_status])) {
        throw new InvalidArgumentException("Invalid status: {$new_status}");
    }

    $actor_id = $context['actor_id'] ?? null;
    $actor_role = $context['actor_role'] ?? null;
    $note = $context['note'] ?? null;

    $stmt = $pdo->prepare("SELECT current_stage FROM projects WHERE project_id = ?");
    $stmt->execute([$project_id]);
    $prev_status = $stmt->fetchColumn();

    $stmt = $pdo->prepare("UPDATE projects SET current_stage = ?, updated_at = NOW() WHERE project_id = ?");
    $stmt->execute([$new_status, $project_id]);

    if (table_exists($pdo, 'project_status_history')) {
        $hist = $pdo->prepare("INSERT INTO project_status_history (project_id, from_status, to_status, actor_id, actor_role, note, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $hist->execute([$project_id, $prev_status, $new_status, $actor_id, $actor_role, $note]);
    }

    log_audit($pdo, 'Project Status Update', $actor_id, "Project {$project_id}: {$prev_status} -> {$new_status}");
    return true;
}
