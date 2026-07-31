<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/urls.php';
require_once __DIR__ . '/admission-letter-template.php';

if (!isset($_SESSION['user_id'])) {
    redirect_to('APPLICANT/ADMISSIONS/login.php');
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$appNumber = $_GET['app_no'] ?? '';
$role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? null;
$isAdmin = ($role && in_array(strtoupper($role), ['SUPER_ADMIN', 'ICT_ADMIN', 'PORTAL_ADMIN', 'PG_SCHOOL_OFFICER', 'ADMISSIONS_OFFICER'], true));

try {
    $applicant = admission_letter_fetch($pdo, $appNumber, $isAdmin ? null : $userId);
    if (!$applicant) {
        die("Admission letter not available.");
    }
} catch (PDOException $e) {
    die("Error fetching admission letter details.");
}

echo render_admission_letter_html($applicant, [
    'include_print_button' => true,
    'for_pdf' => false
]);