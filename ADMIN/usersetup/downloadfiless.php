<?php
require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');
require_role(['DEVELOPER'], 'ADMIN/login.php');

$fileInput = $_GET["nam"] ?? '';
$fullPath = realpath(JOSTUM_ROOT . '/' . ltrim($fileInput, '/'));

if ($fullPath && strpos($fullPath, realpath(JOSTUM_ROOT)) === 0 && file_exists($fullPath) && is_file($fullPath)) {
    // Set headers to force download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . basename($fullPath));
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($fullPath));

    // Clear output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Read the file and send it to the output buffer
    readfile($fullPath);
    exit;
} else {
    // If the file doesn't exist or is outside the project root, display an error
    http_response_code(404);
    echo 'Error: File not found or access denied.';
}
?>
