<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';

// Authentication and Authorization check
require_login('ADMIN/login.php');
if (!has_permission('download_documents')) {
    http_response_code(403);
    exit('403 Forbidden - Insufficient Permissions');
}

if (!$pdo) {
    http_response_code(500);
    exit('Database connection unavailable');
}

$appIds = [];
if (isset($_GET['app_id'])) {
    $appIds[] = (int) $_GET['app_id'];
} elseif (isset($_POST['app_ids']) && is_array($_POST['app_ids'])) {
    $appIds = array_map('intval', $_POST['app_ids']);
} elseif (isset($_GET['app_ids'])) {
    $appIds = array_map('intval', explode(',', $_GET['app_ids']));
}

if (empty($appIds)) {
    http_response_code(400);
    exit('No application IDs specified');
}

// Prepare ZIP file
$zip = new ZipArchive();
$tempFile = tempnam(sys_get_temp_dir(), 'ipess_docs_');
if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Could not create temporary ZIP archive');
}

$filesAdded = 0;
$missingLog = "";

// Fetch applications and personal details
$inQuery = implode(',', array_fill(0, count($appIds), '?'));
$stmt = $pdo->prepare("
    SELECT a.application_id, a.application_number, p.first_name, p.surname
    FROM applications a
    LEFT JOIN personal_details p ON p.application_id = a.application_id
    WHERE a.application_id IN ($inQuery)
");
$stmt->execute($appIds);
$applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

$isBulk = count($applicants) > 1;

foreach ($applicants as $applicant) {
    $appId = (int) $applicant['application_id'];
    $appNo = $applicant['application_number'] ?: 'N_A';
    $firstName = preg_replace('/[^A-Za-z0-9_-]/', '', $applicant['first_name'] ?? '');
    $surname = preg_replace('/[^A-Za-z0-9_-]/', '', $applicant['surname'] ?? '');
    $folderName = $isBulk ? "{$appNo}_{$surname}_{$firstName}/" : "";

    // Fetch documents for this applicant
    $docStmt = $pdo->prepare("SELECT document_type, file_path FROM documents WHERE application_id = ?");
    $docStmt->execute([$appId]);
    $docs = $docStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($docs)) {
        $missingLog .= "Applicant {$appNo} ({$surname} {$firstName}): No uploaded documents found.\n";
        continue;
    }

    foreach ($docs as $doc) {
        $filePath = JOSTUM_ROOT . '/' . ltrim($doc['file_path'], '/');
        $docType = $doc['document_type'] ?: 'document';
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) ?: 'dat';
        $zipEntryName = $folderName . "{$docType}.{$ext}";

        if (file_exists($filePath) && is_file($filePath)) {
            $zip->addFile($filePath, $zipEntryName);
            $filesAdded++;
        } else {
            $missingLog .= "Applicant {$appNo} ({$surname} {$firstName}): File not found for '{$docType}' at path '{$doc['file_path']}'\n";
        }
    }
}

// Add missing files log to ZIP if there are warnings
if (!empty($missingLog)) {
    $zip->addFromString('warnings_log.txt', $missingLog);
}

$zip->close();

if ($filesAdded === 0 && empty($missingLog)) {
    @unlink($tempFile);
    http_response_code(404);
    exit('No uploaded documents found to download.');
}

// Set headers to trigger file download
$zipName = $isBulk ? 'ipess_bulk_documents_' . date('Ymd_His') . '.zip' : 'applicant_' . $applicants[0]['application_number'] . '_docs.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($tempFile));
header('Pragma: no-cache');
header('Expires: 0');

// Output file stream
readfile($tempFile);

// Delete temporary file from disk
@unlink($tempFile);
exit;
