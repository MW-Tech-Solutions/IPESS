<?php
/**
 * Cron Mail Queue Processor
 * Can be run via URL or via shell command line cron job:
 * php ipessadmin/api/cron_mail_queue.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/database.php';

set_time_limit(120);

header('Content-Type: text/plain');

try {
    $pdo = db();
} catch (Throwable $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// 1. Ensure email_queue table exists
try {
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'email_queue'")->fetch();
    if (!$tableCheck) {
        die("No email_queue table found. Process skipped.\n");
    }
} catch (Throwable $e) {
    die("Table check failed: " . $e->getMessage() . "\n");
}

// 2. Select a batch of pending emails and lock them using transaction (FOR UPDATE)
$batchSize = 8;
$ids = [];

try {
    $pdo->beginTransaction();
    
    // Select pending email IDs
    $stmt = $pdo->prepare("
        SELECT queue_id 
        FROM email_queue 
        WHERE status = 'Pending' 
        ORDER BY queue_id ASC 
        LIMIT ? 
        FOR UPDATE
    ");
    $stmt->bindValue(1, $batchSize, PDO::PARAM_INT);
    $stmt->execute();
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($ids)) {
        $pdo->commit();
        echo "No pending emails in the queue.\n";
        exit;
    }

    // Immediately mark them as 'Sending' to avoid concurrent executions sending duplicate emails
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $upStmt = $pdo->prepare("UPDATE email_queue SET status = 'Sending' WHERE queue_id IN ($placeholders)");
    $upStmt->execute($ids);
    
    $pdo->commit();
    echo "Marked " . count($ids) . " email(s) as 'Sending'. Beginning delivery...\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Transaction failed: " . $e->getMessage() . "\n");
}

// 3. Process and deliver the marked emails
require_once __DIR__ . '/../../app/helpers/mailer.php';

$successCount = 0;
$failedCount = 0;

foreach ($ids as $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM email_queue WHERE queue_id = ? LIMIT 1");
        $stmt->execute([$id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            continue;
        }

        $result = portal_send_mail(
            $job['recipient_email'],
            $job['recipient_name'],
            $job['subject'],
            $job['body_html'],
            $job['body_text']
        );

        if (!empty($result['success'])) {
            $successCount++;
            $up = $pdo->prepare("UPDATE email_queue SET status = 'Sent', sent_at = NOW() WHERE queue_id = ?");
            $up->execute([$id]);
            echo "[OK] Sent to: " . $job['recipient_email'] . "\n";
        } else {
            $failedCount++;
            $errMessage = $result['message'] ?? 'Unknown mail delivery failure';
            $up = $pdo->prepare("UPDATE email_queue SET status = 'Failed', attempts = attempts + 1, error_message = ? WHERE queue_id = ?");
            $up->execute([$errMessage, $id]);
            echo "[FAIL] Failed to: " . $job['recipient_email'] . " - " . $errMessage . "\n";
        }
    } catch (Throwable $ex) {
        $failedCount++;
        $up = $pdo->prepare("UPDATE email_queue SET status = 'Failed', attempts = attempts + 1, error_message = ? WHERE queue_id = ?");
        $up->execute([$ex->getMessage(), $id]);
        echo "[EX] Fatal error processing queue ID $id: " . $ex->getMessage() . "\n";
    }
}

echo "Execution finished. Success: $successCount, Failed: $failedCount.\n";
