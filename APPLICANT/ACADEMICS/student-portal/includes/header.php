<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../../config/urls.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'STUDENT') {
    redirect_to('APPLICANT/ADMISSIONS/login.php');
}
require_once __DIR__ . '/../../../../ADMIN/includes/db.php';

$studentCanAccessAcademics = false;
try {
    $sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
    if ($sessionUserId > 0 && isset($pdo)) {
        $stmt = $pdo->prepare("SELECT status, current_status FROM applications WHERE user_id = ? ORDER BY application_id DESC LIMIT 1");
        $stmt->execute([$sessionUserId]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $status = strtoupper((string) ($app['status'] ?? ''));
        $currentStatus = strtoupper((string) ($app['current_status'] ?? ''));

        $studentCanAccessAcademics = (
            $status === 'ADMITTED'
            && in_array($currentStatus, ['ADMISSION_APPROVED', 'ADMISSION_ADMITTED'], true)
        );
    }
} catch (Throwable $e) {
    $studentCanAccessAcademics = false;
}

if (!$studentCanAccessAcademics) {
    redirect_to('dashboard.php');
    exit;
}
$_SESSION['student_can_access_academics'] = true;

$timeoutSeconds = 300;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeoutSeconds) {
    session_unset();
    session_destroy();
    redirect_to('APPLICANT/ADMISSIONS/login.php?timeout=1');
}
$_SESSION['last_activity'] = time();
if (!isset($page_title) || $page_title === '') {
    $page_title = 'JOSTUM PG Portal';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="/ADMIN/images/logo.jpeg">
<title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,600&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/jostum-theme.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>
