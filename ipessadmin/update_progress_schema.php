<?php
/**
 * Database Migration Script
 * Alters application_progress table column types to support new 6-stage tracking pipeline
 * and custom/dynamic workflow stages.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../db.php';

try {
    echo "Starting schema migration...<br>";

    // Alter column `stage` from ENUM to VARCHAR(100)
    $sqlAlterStage = "ALTER TABLE `application_progress` MODIFY COLUMN `stage` VARCHAR(100) NOT NULL";
    $pdo->exec($sqlAlterStage);
    echo "Altered column 'stage' to VARCHAR(100) successfully.<br>";

    // Alter column `stage_status` from ENUM to VARCHAR(20)
    $sqlAlterStatus = "ALTER TABLE `application_progress` MODIFY COLUMN `stage_status` VARCHAR(20) NOT NULL DEFAULT 'Pending'";
    $pdo->exec($sqlAlterStatus);
    echo "Altered column 'stage_status' to VARCHAR(20) successfully.<br>";

    // Optional: Migrate existing records to the new names so they map correctly
    $migrationMap = [
        'Documents Verified' => 'Documents Verification',
        'Referee Reports'    => 'Referee Report',
        'Academic Review'    => 'Departmental Review',
        'Final Decision'     => 'Final Decisions'
    ];

    $updateStmt = $pdo->prepare("UPDATE `application_progress` SET `stage` = ? WHERE `stage` = ?");
    foreach ($migrationMap as $old => $new) {
        $updateStmt->execute([$new, $old]);
        echo "Updated old stage label '{$old}' to '{$new}' where applicable.<br>";
    }

    echo "Migration completed successfully!<br>";

} catch (PDOException $e) {
    die("DB ERROR during migration: " . $e->getMessage());
}
