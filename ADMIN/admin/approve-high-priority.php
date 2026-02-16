<?php
require_once 'db.php';
require_once 'includes/process_decision_logic.php'; // Reuse decision processing logic

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // High priority definition: CGPA >= 4.0 (on a 5.0 scale or equivalent)
    // This is an assumption and can be adjusted.
    $highPriorityCgpa = 4.0;

    try {
        $pdo->beginTransaction();

        $sql = "
            SELECT a.application_id, a.application_number
            FROM applications a
            JOIN higher_education h ON a.application_id = h.application_id
            WHERE a.status = 'Submitted' AND h.cgpa >= ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$highPriorityCgpa]);
        $highPriorityApps = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $approvedCount = 0;
        foreach ($highPriorityApps as $app) {
            // Use the centralized logic to approve and send email
            $result = processAdmissionDecision($pdo, $app['application_number'], 'approve', 'Auto-approved as high priority.');
            if ($result['success']) {
                $approvedCount++;
            } else {
                // If one fails, roll back all changes to ensure atomicity
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'An error occurred during bulk approval. No applications were approved. Error on: ' . $app['application_number']]);
                exit;
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "$approvedCount high-priority applications have been successfully approved."]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>