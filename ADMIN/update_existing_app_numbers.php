<?php
/**
 * Database Migration Script
 * Forces all existing applications to use the new application number format:
 * APP/IPESS/{Year}/{Serial}
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../db.php';

try {
    echo "Starting migration of existing application numbers...<br>";

    // Execute the bulk update SQL query
    $sql = "
        UPDATE applications 
        SET application_number = CONCAT(
            'APP/IPESS/', 
            YEAR(COALESCE(submitted_at, NOW())), 
            '/', 
            LPAD(application_id, 4, '0')
        ) 
        WHERE application_number IS NOT NULL AND application_number != ''
    ";
    
    $affected = $pdo->exec($sql);
    echo "Successfully updated {$affected} application numbers to the new format!<br>";
    echo "Migration completed successfully.<br>";

} catch (PDOException $e) {
    die("DB ERROR during migration: " . $e->getMessage());
}
