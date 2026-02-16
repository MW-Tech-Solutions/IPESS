<?php
require_once 'db.php';
require_once 'includes/process_decision_logic.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appNumbers = json_decode($_POST['app_numbers'] ?? '[]');

    if (!empty($appNumbers)) {
        $sentCount = 0;
        $failedCount = 0;

        foreach ($appNumbers as $appNumber) {
            $sql = "SELECT status FROM applications WHERE application_number = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$appNumber]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($application) {
                $decision = strtolower($application['status']) === 'admitted' ? 'approve' : 'reject';
                $result = processAdmissionDecision($pdo, $appNumber, $decision, 'Bulk notification.');
                if ($result['success']) {
                    $sentCount++;
                } else {
                    $failedCount++;
                }
            } else {
                $failedCount++;
            }
        }

        echo json_encode(['success' => true, 'message' => "$sentCount notifications sent successfully. $failedCount failed."]);

    } else {
        echo json_encode(['success' => false, 'message' => 'No application numbers provided.']);
    }
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>