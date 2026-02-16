<?php
session_start();
header('Content-Type: application/json');

// RBAC check and DB connection
// if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN' || !isset($_SESSION['user_id'])) {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized']);
//     exit;
// }

require_once '../includes/db.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     $doc_id = filter_input(INPUT_POST, 'doc_id', FILTER_VALIDATE_INT);
//     $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
//     $comments = filter_input(INPUT_POST, 'comments', FILTER_SANITIZE_STRING);
//     $user_id = $_SESSION['user_id'];

//     if (!$doc_id || !$status) {
//         $response['message'] = 'Invalid input.';
//     } else {
//         if ($status === 'Rejected') {
//     $status = 'Re-upload Required';
// }
//         try {
   
//     $query = "
//         INSERT INTO document_verification 
//             (upload_id, verification_status, admin_remark, verified_by, verified_at) 
//         VALUES 
//             (:doc_id, :status, :comments, :user_id, NOW())
//         ON DUPLICATE KEY UPDATE 
//             verification_status = VALUES(verification_status),
//             admin_remark = VALUES(admin_remark),
//             verified_by = VALUES(verified_by),
//             verified_at = NOW()
//     ";
    
//     $stmt = $pdo->prepare($query);
//     $stmt->bindValue(':status', $status, PDO::PARAM_STR);
//     $stmt->bindValue(':comments', $comments, PDO::PARAM_STR);
//     $stmt->bindValue(':doc_id', $doc_id, PDO::PARAM_INT);
//     $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    
//     if ($stmt->execute()) {
//         $response = ['success' => true, 'message' => 'Document status updated successfully.'];
//     } else {
//         $response['message'] = 'Failed to save to database.';
//     }
// } catch (Exception $e) {
//     error_log('Verification API Error: ' . $e->getMessage());
//     $response['message'] = 'Database error: ' . $e->getMessage();
// }
//     }
// } else {
//     $response['message'] = 'Invalid request method.';
// }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doc_id = filter_input(INPUT_POST, 'doc_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $comments = filter_input(INPUT_POST, 'comments', FILTER_SANITIZE_STRING);
    // NEW: Capture the score
    $score = filter_input(INPUT_POST, 'score', FILTER_VALIDATE_INT) ?? 0;
    $user_id = $_SESSION['user_id'];

    if (!$doc_id || !$status) {
        $response['message'] = 'Invalid input.';
    } else {
        if ($status === 'Rejected') {
            $status = 'Re-upload Required';
            $score = 0; // Reset score on rejection if preferred
        }
        try {
            // NEW: Added 'score' to Insert and Update clauses
            $query = "
                INSERT INTO document_verification 
                    (upload_id, verification_status, admin_remark, score, verified_by, verified_at) 
                VALUES 
                    (:doc_id, :status, :comments, :score, :user_id, NOW())
                ON DUPLICATE KEY UPDATE 
                    verification_status = VALUES(verification_status),
                    admin_remark = VALUES(admin_remark),
                    score = VALUES(score),
                    verified_by = VALUES(verified_by),
                    verified_at = NOW()
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            $stmt->bindValue(':comments', $comments, PDO::PARAM_STR);
            $stmt->bindValue(':score', $score, PDO::PARAM_INT); // Bind score
            $stmt->bindValue(':doc_id', $doc_id, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Document processed successfully.'];
            } else {
                $response['message'] = 'Failed to save to database.';
            }
        } catch (Exception $e) {
            error_log('Verification API Error: ' . $e->getMessage());
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
    }
} else {
    $response['message'] = 'Invalid request method.';
}
echo json_encode($response);
?>