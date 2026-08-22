<?php
/**
 * Send Reminders Page — Admin UI
 * Queues bulk email reminders to applicants who are yet to finish/submit their applications.
 * Emails are processed in the background via Cron Job or processed online via the client agent.
 */
require_once __DIR__ . '/../app/bootstrap.php';
enforce_session_timeout(900, 'login.php');

$normRole = normalize_role(current_user_role());
$hasAccess = false;
if ($normRole === 'SUPER_ADMIN' || $normRole === 'DEVELOPER') {
    $hasAccess = true;
} else {
    $currentFile = 'send-reminders.php';
    $userId = $_SESSION['user_id'] ?? $_SESSION['userid'] ?? '';
    $roleId = $_SESSION['role'] ?? $_SESSION['roleid'] ?? '';
    $userRoles = array_unique(array_filter([$roleId, $normRole, current_user_role()]));
    try {
        require_once __DIR__ . '/../app/config/database.php';
        $pdo = db();
        if ($userId) {
            $stmtStaffRole = $pdo->prepare("SELECT userRoleID FROM user_access WHERE userName = ? OR staffIDs = ? OR EmailAddress = ? LIMIT 1");
            $stmtStaffRole->execute([$userId, $userId, $userId]);
            $uRoleId = (int)$stmtStaffRole->fetchColumn();
            if ($uRoleId > 0) {
                $userRoles[] = (string)$uRoleId;
                $stmtModern = $pdo->prepare("SELECT role_key, role_name FROM roles WHERE role_id = ? LIMIT 1");
                $stmtModern->execute([$uRoleId]);
                $modRow = $stmtModern->fetch(PDO::FETCH_ASSOC);
                if ($modRow) {
                    if (!empty($modRow['role_key'])) $userRoles[] = $modRow['role_key'];
                    if (!empty($modRow['role_name'])) $userRoles[] = $modRow['role_name'];
                }
            }
        }
        $userRoles = array_values(array_unique(array_filter($userRoles)));
        if (!empty($userRoles)) {
            $placeholders = implode(',', array_fill(0, count($userRoles), '?'));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM right_page_main_menus WHERE (page_url LIKE ? OR page_url LIKE ?) AND roleID IN ($placeholders) AND page_status = '1'");
            $stmt->execute(array_merge(['%' . $currentFile, $currentFile], $userRoles));
            if ((int)$stmt->fetchColumn() > 0) {
                $hasAccess = true;
            }
        }
        if (!$hasAccess && $userId) {
            $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM pesonal_right_page_main_menus WHERE (page_url LIKE ? OR page_url LIKE ?) AND userID = ? AND page_status = '1'");
            $stmt2->execute(['%' . $currentFile, $currentFile, $userId]);
            if ((int)$stmt2->fetchColumn() > 0) {
                $hasAccess = true;
            }
        }
    } catch (Throwable $e) {}
}

if (!$hasAccess) {
    http_response_code(403);
    exit('403 Forbidden — You do not have permission to access this page.');
}

require_once 'db.php';

// Self-healing: Ensure email_queue table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `email_queue` (
            `queue_id` INT NOT NULL AUTO_INCREMENT,
            `user_id` INT NOT NULL,
            `campaign` VARCHAR(100) NOT NULL,
            `recipient_email` VARCHAR(191) NOT NULL,
            `recipient_name` VARCHAR(191) NOT NULL,
            `subject` VARCHAR(255) NOT NULL,
            `body_html` TEXT NOT NULL,
            `body_text` TEXT NOT NULL,
            `status` ENUM('Pending', 'Sending', 'Sent', 'Failed') NOT NULL DEFAULT 'Pending',
            `attempts` INT NOT NULL DEFAULT 0,
            `error_message` TEXT DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `sent_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`queue_id`),
            UNIQUE KEY `uniq_user_campaign` (`user_id`, `campaign`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Throwable $e) {
    error_log("Failed to create email_queue table: " . $e->getMessage());
}

// --- AJAX Endpoints ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'get_queue_stats') {
        $closingDate = trim($_POST['closing_date'] ?? '');
        $campaign = 'closing_reminder_' . $closingDate;

        try {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN status = 'Sending' THEN 1 ELSE 0 END) AS sending,
                    SUM(CASE WHEN status = 'Sent' THEN 1 ELSE 0 END) AS sent,
                    SUM(CASE WHEN status = 'Failed' THEN 1 ELSE 0 END) AS failed
                FROM email_queue
                WHERE campaign = ?
            ");
            $stmt->execute([$campaign]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Fetch current draft applicant count
            $draftCount = (int)$pdo->query("
                SELECT COUNT(DISTINCT u.user_id) 
                FROM applications a 
                JOIN users u ON a.user_id = u.user_id 
                WHERE a.status = 'Draft'
            ")->fetchColumn();

            echo json_encode([
                'success' => true,
                'draft_count' => $draftCount,
                'total' => (int)($stats['total'] ?? 0),
                'pending' => (int)($stats['pending'] ?? 0),
                'sending' => (int)($stats['sending'] ?? 0),
                'sent' => (int)($stats['sent'] ?? 0),
                'failed' => (int)($stats['failed'] ?? 0)
            ]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'queue_reminders') {
        $closingDate = trim($_POST['closing_date'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $bodyTemplate = trim($_POST['body_template'] ?? '');

        if (empty($closingDate) || empty($subject) || empty($bodyTemplate)) {
            echo json_encode(['success' => false, 'message' => 'All configuration form fields are required.']);
            exit;
        }

        $campaign = 'closing_reminder_' . $closingDate;
        $formattedDate = date('d F Y', strtotime($closingDate));

        try {
            // Find all draft applicants who have not already been queued for this specific campaign
            $stmt = $pdo->prepare("
                SELECT DISTINCT u.user_id, u.email, u.full_name 
                FROM applications a
                JOIN users u ON a.user_id = u.user_id
                WHERE a.status = 'Draft'
                  AND u.user_id NOT IN (
                      SELECT user_id FROM email_queue WHERE campaign = ?
                  )
            ");
            $stmt->execute([$campaign]);
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($candidates)) {
                echo json_encode(['success' => true, 'queued' => 0, 'message' => 'All eligible draft applicants are already queued.']);
                exit;
            }

            $queuedCount = 0;
            foreach ($candidates as $cand) {
                $name = $cand['full_name'];
                $email = $cand['email'];

                $personalizedBody = str_replace(
                    ['[Name]', '[Closing Date]'],
                    [$name, $formattedDate],
                    $bodyTemplate
                );

                $emailContent = '
                <div style="font-family: sans-serif; padding: 20px; color: #333; max-width: 600px; margin: auto; border: 1px solid #eee; border-radius: 5px;">
                    <h2 style="color: #6EB533; border-bottom: 2px solid #6EB533; padding-bottom: 10px;">IPESS JOSTUM</h2>
                    ' . nl2br($personalizedBody) . '
                    <div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #eee; font-size: 11px; color: #777;">
                        This is an automated administrative notification. Please do not reply directly to this email.
                    </div>
                </div>';

                $textAlternative = strip_tags(str_replace('<br>', "\n", $personalizedBody));

                $ins = $pdo->prepare("
                    INSERT IGNORE INTO email_queue 
                    (user_id, campaign, recipient_email, recipient_name, subject, body_html, body_text, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
                ");
                $ins->execute([
                    $cand['user_id'],
                    $campaign,
                    $email,
                    $name,
                    $subject,
                    $emailContent,
                    $textAlternative
                ]);

                if ($ins->rowCount() > 0) {
                    $queuedCount++;
                }
            }

            echo json_encode(['success' => true, 'queued' => $queuedCount]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Queue insertion failed: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'run_delivery_agent') {
        // Triggers the background queue processor file and returns its output
        try {
            ob_start();
            require_once __DIR__ . '/api/cron_mail_queue.php';
            $output = ob_get_clean();
            echo json_encode(['success' => true, 'output' => trim($output)]);
        } catch (Throwable $e) {
            ob_get_clean();
            echo json_encode(['success' => false, 'message' => 'Agent execution error: ' . $e->getMessage()]);
        }
        exit;
    }
}

// Initial counts of draft applications
$draftCount = 0;
try {
    $draftCount = (int)$pdo->query("
        SELECT COUNT(DISTINCT u.user_id) 
        FROM applications a 
        JOIN users u ON a.user_id = u.user_id 
        WHERE a.status = 'Draft'
    ")->fetchColumn();
} catch (Throwable $e) {}

$pageTitle = 'Application Reminders Queue';
$pageSubtitle = 'Queue and process email notifications for applicants with unsubmitted (Draft) applications.';

require_once 'includes/dev_header.php';
require_once 'includes/sidebar.php';
require_once 'includes/dev_topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Application Reminders Queue</h1>
        <p class="panel-muted">Prepare and dispatch reminder notifications. Mails can run via server-side cron or via the online processing agent.</p>
    </div>
</section>

<div class="row g-4">
    <!-- Configuration Form -->
    <div class="col-lg-6">
        <section class="panel">
            <div class="panel-header border-bottom">
                <h3 class="panel-title"><i class="fas fa-tasks me-2 text-primary"></i>Campaign Setup</h3>
            </div>
            <div class="panel-body py-4">
                <form id="campaignForm" onsubmit="queueEmails(event)">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Eligible Recipients</label>
                        <div class="form-control-plaintext fw-bold text-dark fs-5">
                            <i class="fas fa-users text-primary me-2"></i><span id="draftCount"><?= number_format($draftCount) ?></span> Applicants in Draft
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="closing_date" class="form-label small fw-semibold text-muted">Specific Closing Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="closing_date" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" onchange="loadStats()">
                    </div>

                    <div class="mb-3">
                        <label for="subject" class="form-label small fw-semibold text-muted">Email Subject</label>
                        <input type="text" class="form-control" id="subject" value="IPESS JOSTUM - Postgraduate Application Deadline Reminder" required>
                    </div>

                    <div class="mb-3">
                        <label for="body_template" class="form-label small fw-semibold text-muted">Email Body Template</label>
                        <textarea class="form-control" id="body_template" rows="8" required>Dear [Name],

We noticed that you have started but not yet completed/submitted your postgraduate application on the IPESS portal.

Please be informed that the application exercise will officially close on [Closing Date].

If you wish to be considered for admission for this academic session, please ensure you log in to the portal and submit your application before the closing date.

To complete your application, log in here:
<?= app_absolute_url('APPLICANT/ADMISSIONS/login.php') ?>

Best regards,
Admissions Office,
IPESS JOSTUM.</textarea>
                        <div class="form-text small text-muted">You can use <code>[Name]</code> and <code>[Closing Date]</code> tags. They will be personalized dynamically.</div>
                    </div>

                    <div class="mt-4 pt-2">
                        <button type="submit" id="queueBtn" class="btn btn-primary px-4">
                            <i class="fas fa-list-ol me-2"></i>Queue Reminder Emails
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <!-- Queue status and delivery -->
    <div class="col-lg-6">
        <section class="panel mb-4">
            <div class="panel-header border-bottom d-flex justify-content-between align-items-center">
                <h3 class="panel-title"><i class="fas fa-shipping-fast me-2 text-warning"></i>Queue & Delivery Status</h3>
                <span class="badge bg-secondary py-2 px-3 small text-white" id="campaignBadge">Campaign: Load stats...</span>
            </div>
            <div class="panel-body py-4">
                <div class="row text-center g-3 mb-4">
                    <div class="col-3">
                        <div class="text-muted small">Queued</div>
                        <h2 class="fw-bold mb-0 text-dark" id="statTotal">0</h2>
                    </div>
                    <div class="col-3">
                        <div class="text-primary small">Pending</div>
                        <h2 class="fw-bold mb-0 text-primary" id="statPending">0</h2>
                    </div>
                    <div class="col-3">
                        <div class="text-success small">Delivered</div>
                        <h2 class="fw-bold mb-0 text-success" id="statSent">0</h2>
                    </div>
                    <div class="col-3">
                        <div class="text-danger small">Failed</div>
                        <h2 class="fw-bold mb-0 text-danger" id="statFailed">0</h2>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-semibold text-muted" id="progressPctText">Progress: 0%</span>
                        <span class="small text-muted" id="progressRatioText">0 / 0</span>
                    </div>
                    <div class="progress" style="height: 12px; border-radius: 50px;">
                        <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" id="progressBar" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>

                <!-- Background Processing Alert Box -->
                <div class="alert alert-info py-3 border-0 d-flex align-items-start mb-4" style="border-radius: 8px;">
                    <i class="fas fa-info-circle fs-4 me-3 text-info mt-1"></i>
                    <div>
                        <h6 class="fw-bold mb-1 text-info">Background Cron Delivery Active</h6>
                        <p class="mb-0 small text-muted-dark">Mails are processed in the background on the server. You can safely close this page and go offline. The cron job will continue delivery.</p>
                    </div>
                </div>

                <div class="d-flex mb-3 align-items-center">
                    <label class="form-label small fw-semibold text-muted mb-0 me-auto">Delivery Logs</label>
                    <button type="button" id="startAgentBtn" class="btn btn-outline-warning btn-sm px-3" onclick="toggleDeliveryAgent()">
                        <i class="fas fa-cog fa-spin me-2"></i>Run Client Delivery Agent
                    </button>
                </div>
                <div class="border rounded bg-light p-3" id="logBox" style="height: 200px; overflow-y: scroll; font-family: monospace; font-size: 0.8rem; line-height: 1.4;">
                    <span class="text-muted">Agent idle. Start client agent to process queue in browser.</span>
                </div>
            </div>
        </section>
    </div>
</div>

<?php require_once 'includes/dev_footer.php'; ?>

<script>
let totalQueued = 0;
let pendingCount = 0;
let isAgentRunning = false;
let pollingInterval = null;

document.addEventListener('DOMContentLoaded', () => {
    loadStats();
    // Poll stats every 5 seconds to show background cron progress in real-time
    pollingInterval = setInterval(loadStats, 5000);
});

function loadStats() {
    const closingDate = document.getElementById('closing_date').value;
    document.getElementById('campaignBadge').textContent = 'Campaign: closing_reminder_' + closingDate;

    const fd = new FormData();
    fd.append('action', 'get_queue_stats');
    fd.append('closing_date', closingDate);

    fetch('send-reminders.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;

        document.getElementById('draftCount').textContent = data.draft_count.toLocaleString();
        document.getElementById('statTotal').textContent = data.total;
        document.getElementById('statPending').textContent = data.pending;
        document.getElementById('statSent').textContent = data.sent;
        document.getElementById('statFailed').textContent = data.failed;

        totalQueued = data.total;
        pendingCount = data.pending;

        // Progress calculation
        const processed = data.sent + data.failed;
        const pct = totalQueued > 0 ? Math.min(100, Math.round((processed / totalQueued) * 100)) : 0;
        document.getElementById('progressBar').style.width = pct + '%';
        document.getElementById('progressPctText').textContent = `Progress: ${pct}%`;
        document.getElementById('progressRatioText').textContent = `${processed} / ${totalQueued}`;

        // Disable queue button if everyone is already queued
        document.getElementById('queueBtn').disabled = (data.draft_count === 0);

        // Turn off agent if queue is completely processed
        if (isAgentRunning && pendingCount === 0) {
            stopDeliveryAgent();
            const logBox = document.getElementById('logBox');
            logBox.innerHTML += `<span class="text-success fw-bold">[AGENT] Queue is empty. Client delivery agent stopped.</span><br>`;
            logBox.scrollTop = logBox.scrollHeight;
        }
    });
}

function queueEmails(e) {
    e.preventDefault();

    const queueBtn = document.getElementById('queueBtn');
    queueBtn.disabled = true;
    queueBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Queueing...';

    const closingDate = document.getElementById('closing_date').value;
    const subject = document.getElementById('subject').value;
    const bodyTemplate = document.getElementById('body_template').value;

    const fd = new FormData();
    fd.append('action', 'queue_reminders');
    fd.append('closing_date', closingDate);
    fd.append('subject', subject);
    fd.append('body_template', bodyTemplate);

    fetch('send-reminders.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        queueBtn.disabled = false;
        queueBtn.innerHTML = '<i class="fas fa-list-ol me-2"></i>Queue Reminder Emails';

        const logBox = document.getElementById('logBox');
        if (data.success) {
            logBox.innerHTML = `<span class="text-primary fw-bold">[SYSTEM] Successfully queued ${data.queued} email(s) for Campaign: closing_reminder_${closingDate}.</span><br>`;
            loadStats();
        } else {
            logBox.innerHTML += `<span class="text-danger fw-bold">[ERROR] Failed to queue: ${data.message}</span><br>`;
        }
        logBox.scrollTop = logBox.scrollHeight;
    })
    .catch(() => {
        queueBtn.disabled = false;
        queueBtn.innerHTML = '<i class="fas fa-list-ol me-2"></i>Queue Reminder Emails';
    });
}

function toggleDeliveryAgent() {
    if (isAgentRunning) {
        stopDeliveryAgent();
    } else {
        startDeliveryAgent();
    }
}

function startDeliveryAgent() {
    isAgentRunning = true;
    const btn = document.getElementById('startAgentBtn');
    btn.innerHTML = '<i class="fas fa-pause me-2"></i>Stop Client Delivery Agent';
    btn.className = 'btn btn-danger btn-sm px-3';

    const logBox = document.getElementById('logBox');
    logBox.innerHTML += `<span class="text-primary font-weight-bold">[AGENT] Client delivery agent started. Processing batches...</span><br>`;
    logBox.scrollTop = logBox.scrollHeight;

    runBatchDelivery();
}

function stopDeliveryAgent() {
    isAgentRunning = false;
    const btn = document.getElementById('startAgentBtn');
    btn.innerHTML = '<i class="fas fa-cog fa-spin me-2"></i>Run Client Delivery Agent';
    btn.className = 'btn btn-outline-warning btn-sm px-3';

    const logBox = document.getElementById('logBox');
    logBox.innerHTML += `<span class="text-warning fw-bold">[AGENT] Client delivery agent stopped.</span><br>`;
    logBox.scrollTop = logBox.scrollHeight;
}

let consecutiveErrors = 0;

function runBatchDelivery() {
    if (!isAgentRunning) return;

    const fd = new FormData();
    fd.append('action', 'run_delivery_agent');

    fetch('send-reminders.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (!isAgentRunning) return;

        const logBox = document.getElementById('logBox');
        if (data.success) {
            consecutiveErrors = 0; // Reset errors

            // Print cron text output to logs
            const lines = data.output.split('\n');
            lines.forEach(line => {
                if (line.trim() !== '') {
                    if (line.includes('[OK]')) {
                        logBox.innerHTML += `<span class="text-success">${line}</span><br>`;
                    } else if (line.includes('[FAIL]') || line.includes('[ERROR]') || line.includes('[EX]')) {
                        logBox.innerHTML += `<span class="text-danger">${line}</span><br>`;
                    } else {
                        logBox.innerHTML += `<span class="text-muted">${line}</span><br>`;
                    }
                }
            });
            logBox.scrollTop = logBox.scrollHeight;
            loadStats();

            if (data.output.includes("No pending emails")) {
                // Done
                stopDeliveryAgent();
            } else {
                // Deliver next batch after 2 seconds
                setTimeout(runBatchDelivery, 2000);
            }
        } else {
            consecutiveErrors++;
            if (consecutiveErrors < 5) {
                logBox.innerHTML += `<span class="text-warning fw-bold">[AGENT WARNING] ${data.message}. Retrying (Attempt ${consecutiveErrors}/5) in 5s...</span><br>`;
                logBox.scrollTop = logBox.scrollHeight;
                setTimeout(runBatchDelivery, 5000);
            } else {
                logBox.innerHTML += `<span class="text-danger fw-bold">[AGENT ERROR] ${data.message}. Stopping agent.</span><br>`;
                logBox.scrollTop = logBox.scrollHeight;
                stopDeliveryAgent();
            }
        }
    })
    .catch(() => {
        if (!isAgentRunning) return;
        consecutiveErrors++;
        const logBox = document.getElementById('logBox');
        if (consecutiveErrors < 5) {
            logBox.innerHTML += `<span class="text-warning fw-bold">[AGENT WARNING] Connection failed. Retrying (Attempt ${consecutiveErrors}/5) in 5s...</span><br>`;
            logBox.scrollTop = logBox.scrollHeight;
            setTimeout(runBatchDelivery, 5000);
        } else {
            logBox.innerHTML += `<span class="text-danger fw-bold">[AGENT ERROR] Connection failed. Stopping agent.</span><br>`;
            logBox.scrollTop = logBox.scrollHeight;
            stopDeliveryAgent();
        }
    });
}
</script>
