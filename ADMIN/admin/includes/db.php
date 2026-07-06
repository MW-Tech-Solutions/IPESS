<?php
require_once __DIR__ . '/../../../app/config/database.php';

// Startup Database Migrations & Self-Healing
if (isset($pdo)) {
    try {
        // 1. Migrate Application Numbers to APP/IPESS/YYYY/XXXX format for non-drafts
        $pdo->exec("
            UPDATE applications 
            SET application_number = CONCAT('APP/IPESS/', YEAR(COALESCE(submitted_at, NOW())), '/', LPAD(application_id, 4, '0')) 
            WHERE status <> 'Draft' AND (application_number NOT LIKE 'APP/IPESS/%' OR application_number IS NULL)
        ");

        // 2. Ensure UNIQUE index exists on document_verification.upload_id
        $hasUnique = false;
        $idxStmt = $pdo->query("SHOW INDEX FROM document_verification WHERE Key_name = 'unique_upload_id'");
        if ($idxStmt->fetch()) {
            $hasUnique = true;
        }

        if (!$hasUnique) {
            // Delete duplicate verification entries keeping the latest one
            $pdo->exec("
                DELETE t1 FROM document_verification t1
                INNER JOIN document_verification t2 
                ON t1.upload_id = t2.upload_id AND t1.verification_id < t2.verification_id
            ");
            
            // Add the unique index
            $pdo->exec("ALTER TABLE document_verification ADD UNIQUE KEY unique_upload_id (upload_id)");
        }
    } catch (Throwable $e) {
        error_log("Db Startup Migration Error: " . $e->getMessage());
    }
}
