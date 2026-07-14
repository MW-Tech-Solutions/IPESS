<?php
// Standalone page and helper function to create 'admission_processing' table if missing.
// Accessible by both students and staff roles completely.

require_once __DIR__ . '/generate_all_tables.php';

if (!function_exists('check_and_create_admission_processing')) {
    function check_and_create_admission_processing(PDO $pdo): bool {
        return generate_all_missing_tables($pdo);
    }
}

// If accessed directly via HTTP request
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../config/db.php';
    
    if (generate_all_missing_tables($pdo)) {
        echo json_encode(['success' => true, 'message' => "All missing database tables and migrations checked and successfully generated."]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Failed to verify or create database tables."]);
    }
}
