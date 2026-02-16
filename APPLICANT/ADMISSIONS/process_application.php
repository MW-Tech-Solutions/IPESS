<?php
// Database configuration
$host = 'localhost';
$db   = 'jpg';
$user = 'root';
$pass = '997667';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // 1. Insert Applicant
    $stmt = $pdo->prepare("INSERT INTO applicants (first_name, last_name, email) VALUES (?, ?, ?)");
    $stmt->execute([
        $_POST['first_name'] ?? null, 
        $_POST['last_name'] ?? null, 
        $_POST['email'] ?? null
    ]);
    $applicant_id = $pdo->lastInsertId();

    // 2. Insert Higher Education
    $stmt = $pdo->prepare("INSERT INTO higher_education (applicant_id, institution_name, highest_qualification, course_of_study, year_attended, class_of_degree, cgpa, mode_of_study) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $applicant_id, 
        $_POST['uni_name'] ?? null, 
        $_POST['qualification'] ?? null, 
        $_POST['course'] ?? null, 
        $_POST['year'] ?? null, 
        $_POST['class'] ?? null, 
        $_POST['cgpa'] ?? 0, 
        $_POST['mode'] ?? null
    ]);

    // 3. Handle O'Level Sittings
    if (!empty($_POST['sitting']) && is_array($_POST['sitting'])) {
        
        $examStmt = $pdo->prepare("INSERT INTO olevel_exams (applicant_id, sitting_number, exam_type, school_name, exam_year, exam_number) 
                                   VALUES (?, ?, ?, ?, ?, ?)");
                                   
        $resultStmt = $pdo->prepare("INSERT INTO olevel_results (exam_id, subject_name, grade) VALUES (?, ?, ?)");

        foreach ($_POST['sitting'] as $sittingNum => $sittingData) {
            // Only proceed if school name is provided for this sitting
            if (!empty($sittingData['school_name'])) {
                $examStmt->execute([
                    $applicant_id,
                    $sittingNum, 
                    $sittingData['exam_type'],
                    $sittingData['school_name'],
                    $sittingData['year'],
                    $sittingData['exam_no']
                ]);
                
                $examId = $pdo->lastInsertId();

                // Save subjects
                if (!empty($sittingData['subjects']) && is_array($sittingData['subjects'])) {
                    foreach ($sittingData['subjects'] as $subject) {
                        if (!empty($subject['name'])) {
                            $resultStmt->execute([$examId, $subject['name'], $subject['grade']]);
                        }
                    }
                }
            }
        }
    }

    echo "Application submitted successfully! Applicant ID: " . $applicant_id;

} catch (PDOException $e) {
    // Detailed error output for debugging
    exit("Database Error: " . $e->getMessage());
} catch (Exception $e) {
    exit("General Error: " . $e->getMessage());
}