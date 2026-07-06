<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Updating course spelling...\n";
    $stmt1 = $pdo->prepare("UPDATE courses SET course_title = 'PGD SUSTAINABLE SOCIAL DEVELOPMENT' WHERE course_id = 12");
    $stmt1->execute();
    
    $stmt2 = $pdo->prepare("UPDATE courses SET course_title = 'MSC SUSTAINABLE SOCIAL DEVELOPMENT' WHERE course_id = 13");
    $stmt2->execute();
    
    echo "Updating any programme choices pointing to 0 to correct IDs...\n";
    
    // We can also fix the programme choices that were saved as 0 due to spelling mismatch or other issues.
    // Let's check for any 0s or mismatches:
    // If the department was Social Standards (8), and degree was PGD (4), but course is 0, set course to 12.
    $stmt3 = $pdo->prepare("UPDATE programme_choices SET course = 12 WHERE department = 8 AND degree_type = 4 AND (course = 0 OR course IS NULL)");
    $stmt3->execute();
    
    // If department was Social Standards (8), and degree was MSc (2), set course to 13.
    $stmt4 = $pdo->prepare("UPDATE programme_choices SET course = 13 WHERE department = 8 AND degree_type = 2 AND (course = 0 OR course IS NULL)");
    $stmt4->execute();
    
    echo "Spelling fix completed successfully.\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
