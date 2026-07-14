<?php
require_once 'includes/db.php';

$columns_to_add = [
    'referee_name' => "VARCHAR(150) DEFAULT NULL",
    'referee_title' => "VARCHAR(50) DEFAULT NULL",
    'referee_organization' => "VARCHAR(150) DEFAULT NULL",
    'referee_department' => "VARCHAR(150) DEFAULT NULL",
    'referee_position' => "VARCHAR(150) DEFAULT NULL",
    'referee_address' => "TEXT DEFAULT NULL",
    'referee_phone' => "VARCHAR(20) DEFAULT NULL",
    'relationship' => "VARCHAR(100) DEFAULT NULL",
    'years_known' => "INT DEFAULT NULL",
    'assessment_character_integrity' => "ENUM('Excellent', 'Very Good', 'Good', 'Fair', 'Poor') DEFAULT NULL",
    'assessment_professional_competence' => "ENUM('Excellent', 'Very Good', 'Good', 'Fair', 'Poor') DEFAULT NULL",
    'assessment_leadership_ability' => "ENUM('Excellent', 'Very Good', 'Good', 'Fair', 'Poor') DEFAULT NULL",
    'assessment_communication_skills' => "ENUM('Excellent', 'Very Good', 'Good', 'Fair', 'Poor') DEFAULT NULL",
    'assessment_teamwork' => "ENUM('Excellent', 'Very Good', 'Good', 'Fair', 'Poor') DEFAULT NULL",
    'assessment_reliability' => "ENUM('Excellent', 'Very Good', 'Good', 'Fair', 'Poor') DEFAULT NULL",
    'assessment_initiative' => "ENUM('Excellent', 'Very Good', 'Good', 'Fair', 'Poor') DEFAULT NULL",
    'assessment_emotional_stability' => "ENUM('Excellent', 'Very Good', 'Good', 'Fair', 'Poor') DEFAULT NULL",
    'major_strengths' => "TEXT DEFAULT NULL",
    'weaknesses' => "TEXT DEFAULT NULL",
    'recommendation' => "ENUM('Strongly Recommend', 'Recommend', 'Recommend with Reservation', 'Do Not Recommend') DEFAULT NULL",
    'additional_comments' => "TEXT DEFAULT NULL",
    'declaration_accepted' => "TINYINT DEFAULT 0",
    'signature' => "VARCHAR(150) DEFAULT NULL",
    'declaration_date' => "DATE DEFAULT NULL"
];

echo "Altering referee_uploads table columns...\n";

foreach ($columns_to_add as $col => $definition) {
    try {
        $pdo->exec("ALTER TABLE `referee_uploads` ADD COLUMN `{$col}` {$definition}");
        echo "Column '{$col}' added successfully.\n";
    } catch (PDOException $e) {
        // If it already exists, it is okay.
        echo "Column '{$col}' already exists or skipped: " . $e->getMessage() . "\n";
    }
}

echo "Done!\n";
?>
