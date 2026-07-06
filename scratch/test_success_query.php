<?php
require_once __DIR__ . '/../config/db.php';

try {
    $appNumber = 'APP/IPESS/2026/0023'; // wait, let's look up by application_id 23 or check existing app number
    
    // Find the actual application number for id 23
    $stmt = $pdo->prepare("SELECT application_number FROM applications WHERE application_id = 23 OR application_number LIKE '%0023%' LIMIT 1");
    $stmt->execute();
    $appNumber = $stmt->fetchColumn() ?: 'APP/IPESS/2026/0003'; // fallback to first one if not found

    echo "Querying for app number: $appNumber\n";

    $stmt = $pdo->prepare("
        SELECT 
            a.*, 
            u.email as user_email, 
            p.*, 
            pc.*, 
            n.*, 
            w.*, 
            r.*,
            f.faculty_name,
            d.dept_name,
            dt.degree_name,
            c.course_title,
            sm.mode_name
        FROM applications a
        JOIN users u ON a.user_id = u.user_id
        LEFT JOIN personal_details p ON a.application_id = p.application_id
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
        LEFT JOIN nysc_details n ON a.application_id = n.application_id
        LEFT JOIN work_experience w ON a.application_id = w.application_id
        LEFT JOIN research_details r ON a.application_id = r.application_id
        LEFT JOIN faculties f ON pc.faculty = f.faculty_id
        LEFT JOIN departments d ON pc.department = d.dept_id
        LEFT JOIN degree_types dt ON pc.degree_type = dt.degree_id
        LEFT JOIN courses c ON pc.course = c.course_id
        LEFT JOIN study_modes sm ON pc.mode_of_study = sm.mode_id
        WHERE a.application_number = ?
    ");
    $stmt->execute([$appNumber]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "\n=== FETCHED APP ROW ===\n";
    print_r($app);

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
