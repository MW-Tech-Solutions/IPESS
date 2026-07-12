<?php
/**
 * Migration: Add matric tracking columns to admission_processing table
 * Run once: php c:\xampp\htdocs\JOSTUM\ADMIN\super-admin\api\migrate_matric_cols.php
 */
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../admin/includes/db.php';

$columns = [
    "matric_number"                => "VARCHAR(60) DEFAULT NULL",
    "matric_generated_at"          => "DATETIME DEFAULT NULL",
    "matric_generated_by"          => "INT DEFAULT NULL",
    "admission_letter_activated_at"  => "DATETIME DEFAULT NULL",
    "acceptance_letter_activated_at" => "DATETIME DEFAULT NULL",
];

foreach ($columns as $col => $def) {
    try {
        $pdo->exec("ALTER TABLE admission_processing ADD COLUMN IF NOT EXISTS `{$col}` {$def}");
        echo "OK: $col\n";
    } catch (PDOException $e) {
        echo "SKIP/ERR {$col}: " . $e->getMessage() . "\n";
    }
}
echo "Done.\n";
