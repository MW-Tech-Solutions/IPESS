<?php
ob_start();

session_start();
require_once __DIR__ . '/../config/urls.php';

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

function sendJsonError($message) {
    ob_clean(); 
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

try {
    try {
        require '../config/db.php'; 
    } catch (Exception $e) {
        throw new Exception("Database connection failed.");
    }

    if (!isset($_SESSION['user_id'])) {
        if ($isAjax) sendJsonError('Unauthorized: session expired.');
        else { redirect_to('APPLICANT/ADMISSIONS/login.php'); }
    }

    if (!isset($_FILES['passport_file']) || $_FILES['passport_file']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception("No file uploaded. Please select a file.");
    }
    
    if ($_FILES['passport_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("File upload failed with error code: " . $_FILES['passport_file']['error']);
    }

    $file = $_FILES['passport_file'];

    $maxSize = 250 * 1024; 
    if ($file['size'] > $maxSize) {
        throw new Exception("File is too large. Maximum allowed size is 250KB.");
    }
    
    $allowed = [
    'image/jpeg'   => 'jpg', 
    'image/jpg'    => 'jpg', 
    'image/png'    => 'png', 
    'image/x-png'  => 'png'
];
    $mime = '';
    
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
    } else {
        $mime = $file['type'];
    }

    if (!array_key_exists($mime, $allowed)) {
        throw new Exception("Invalid file format. Only JPG and PNG allowed.");
    }

    $user_id = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("SELECT application_id FROM applications WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    $application_id = $app ? $app['application_id'] : null;

    if (!$application_id) {
        $stmt = $pdo->prepare("INSERT INTO applications (user_id, current_step) VALUES (?, 1)");
        $stmt->execute([$user_id]);
        $application_id = $pdo->lastInsertId();
    }

    $ext = $allowed[$mime];
    $filename = 'passport_' . $application_id . '_' . time() . '.' . $ext;
    
    $uploadDir = '../uploads/passports/';
    
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception("Failed to create upload directory.");
        }
    }

    $dest_path = $uploadDir . $filename;
    
    
$checkStmt = $pdo->prepare("SELECT file_path FROM documents WHERE application_id = ? AND document_type = 'passport'");
$checkStmt->execute([$application_id]);
$existingDoc = $checkStmt->fetch(PDO::FETCH_ASSOC);

if (move_uploaded_file($file['tmp_name'], $dest_path)) {
    
    if ($existingDoc && !empty($existingDoc['file_path'])) {
        $oldFilePath = '../' . $existingDoc['file_path']; 
        if (file_exists($oldFilePath)) {
            unlink($oldFilePath);
        }
    }
        
        $db_path = 'uploads/passports/' . $filename;

        $docStmt = $pdo->prepare("INSERT INTO documents (application_id, document_type, file_path) 
                                 VALUES (?, 'passport', ?) 
                                 ON DUPLICATE KEY UPDATE file_path = VALUES(file_path)");
        $docStmt->execute([$application_id, $db_path]);

        $_SESSION['passport_path'] = $db_path;
        $_SESSION['form_data']['step_1']['passport_file'] = $db_path;

        if ($isAjax) {
            ob_clean(); 
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'url' => $db_path]);
            exit;
        } else {
            $_SESSION['msg'] = ['type' => 'success', 'text' => 'Passport uploaded successfully!'];
            header("Location: dashboard.php?step=1");
            exit;
        }
    } else {
        throw new Exception("Failed to move uploaded file.");
    }

} catch (Exception $e) {
    if ($isAjax) {
        sendJsonError($e->getMessage());
    } else {
        $_SESSION['msg'] = ['type' => 'danger', 'text' => $e->getMessage()];
        header("Location: dashboard.php?step=1");
        exit;
    }
}
?>
