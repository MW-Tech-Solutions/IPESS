<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$otp_array = $input['otp'] ?? [];
$user_otp = implode('', $otp_array);

$email = $input['email'] ?? '';
$pass  = $input['password'] ?? '';

$session_otp   = $_SESSION['auth_otp'] ?? null;
$session_email = $_SESSION['auth_email'] ?? null;
$session_time  = $_SESSION['auth_time'] ?? 0;

if (time() - $session_time > 300) {
    echo json_encode(['success' => false, 'message' => 'OTP expired. Please reload.']);
    exit;
}

if (!$session_otp || $user_otp != $session_otp) {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP Code.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmtUser = $pdo->prepare("INSERT INTO applicant_accounts (email, password_hash) VALUES (?, ?)");
    $stmtUser->execute([$session_email, $hash]);
    
    $newUserId = $pdo->lastInsertId();

    $stmtApp = $pdo->prepare("INSERT INTO applications (user_id) VALUES (?)");
    $stmtApp->execute([$newUserId]);
    
    $newAppId = $pdo->lastInsertId();

    $notifTitle = "Welcome to JOSTUM";
    $notifMsg   = "Thank you for choosing JOSTUM, please ensure you complete your application on time";

    $stmtNotif = $pdo->prepare("
        INSERT INTO applicant_notifications (application_id, notification_title, notification_message) 
        VALUES (?, ?, ?)
    ");
    $stmtNotif->execute([$newAppId, $notifTitle, $notifMsg]);

    $pdo->commit();
    
    unset($_SESSION['auth_otp'], $_SESSION['auth_email'], $_SESSION['auth_time']);
    
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode(['success' => false, 'message' => 'Registration failed.']);
}
?>