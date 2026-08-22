<?php
/**
 * Download Applicant Package
 * Generates: Application Slip (PDF via dompdf) + all uploaded documents
 * Merged into a single PDF using FPDI/FPDF for PDF docs + dompdf for the slip.
 * Supports single applicant or bulk (ZIP or merged PDF).
 *
 * GET params (single):  app_no=XXXX  (or application_id=N)
 * POST params (bulk):   ids[]=1&ids[]=2&format=zip|pdf
 */
session_start();

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use setasign\Fpdi\Fpdi;

// --- Auth check ---
if (empty($_SESSION['user_id']) && empty($_SESSION['userid'])) {
    http_response_code(403);
    die('Unauthorized');
}

// ---------------------------------------------------------------------------
// Helper: resolve physical file path from stored DB path
// ---------------------------------------------------------------------------
function resolveFilePath(string $stored): string {
    $stored = ltrim($stored, '/');
    // absolute path stored?
    if (file_exists($stored)) return $stored;
    // relative to DOCUMENT_ROOT
    $dr = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
    if (file_exists($dr . '/' . $stored)) return $dr . '/' . $stored;
    // relative to ipessadmin
    $base = dirname(__DIR__);
    if (file_exists($base . '/' . $stored)) return $base . '/' . $stored;
    // uploads sub-path
    if (file_exists($base . '/uploads/' . basename($stored))) return $base . '/uploads/' . basename($stored);
    return '';
}

// ---------------------------------------------------------------------------
// Helper: Fetch full applicant data + documents by application_id
// ---------------------------------------------------------------------------
function fetchApplicant(PDO $pdo, int $appId): ?array {
    $stmt = $pdo->prepare("
        SELECT a.application_id, a.application_number, a.status, a.submitted_at,
               u.email,
               pd.first_name, pd.surname, pd.other_name, pd.phone, pd.address, pd.sex, pd.dob, pd.nationality, pd.state_origin,
               f.faculty_name, d.dept_name, dt.degree_name, c.course_title, sm.mode_name,
               pc.mode_of_study
        FROM applications a
        LEFT JOIN users u ON u.user_id = a.user_id
        LEFT JOIN personal_details pd ON pd.application_id = a.application_id
        LEFT JOIN programme_choices pc ON pc.application_id = a.application_id AND pc.faculty > 0
        LEFT JOIN faculties f ON f.faculty_id = pc.faculty
        LEFT JOIN departments d ON d.dept_id = pc.department
        LEFT JOIN degree_types dt ON dt.degree_id = pc.degree_type
        LEFT JOIN courses c ON c.course_id = pc.course
        LEFT JOIN study_modes sm ON sm.mode_id = pc.mode_of_study
        WHERE a.application_id = ?
        LIMIT 1
    ");
    $stmt->execute([$appId]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$app) return null;

    // Documents
    $dStmt = $pdo->prepare("SELECT doc_id, document_type, file_path, uploaded_at, status FROM documents WHERE application_id = ? ORDER BY uploaded_at ASC");
    $dStmt->execute([$appId]);
    $app['documents'] = $dStmt->fetchAll(PDO::FETCH_ASSOC);

    // Higher education
    $eStmt = $pdo->prepare("SELECT institution, highest_qualification AS degree, course_study AS field_of_study, grad_year AS graduation_year FROM higher_education WHERE application_id = ?");
    $eStmt->execute([$appId]);
    $app['education'] = $eStmt->fetchAll(PDO::FETCH_ASSOC);

    return $app;
}

// ---------------------------------------------------------------------------
// Helper: Generate application slip HTML (for dompdf)
// ---------------------------------------------------------------------------
function buildSlipHtml(array $app): string {
    $name = htmlspecialchars(trim(($app['surname'] ?? '') . ', ' . ($app['first_name'] ?? '') . ' ' . ($app['other_name'] ?? '')));
    $appNo     = htmlspecialchars($app['application_number'] ?? 'N/A');
    $email     = htmlspecialchars($app['email'] ?? 'N/A');
    $phone     = htmlspecialchars($app['phone'] ?? 'N/A');
    $faculty   = htmlspecialchars($app['faculty_name'] ?? 'N/A');
    $dept      = htmlspecialchars($app['dept_name'] ?? 'N/A');
    $degree    = htmlspecialchars($app['degree_name'] ?? 'N/A');
    $course    = htmlspecialchars($app['course_title'] ?? 'N/A');
    $mode      = htmlspecialchars($app['mode_name'] ?? 'N/A');
    $status    = htmlspecialchars($app['status'] ?? 'Unknown');
    $submitted = !empty($app['submitted_at']) ? date('d F Y', strtotime($app['submitted_at'])) : 'Not yet submitted';
    $address   = htmlspecialchars($app['address'] ?? 'N/A');
    $dob       = !empty($app['dob']) ? date('d F Y', strtotime($app['dob'])) : 'N/A';
    $sex       = htmlspecialchars($app['sex'] ?? 'N/A');
    $nationality = htmlspecialchars($app['nationality'] ?? 'N/A');
    $state     = htmlspecialchars($app['state_origin'] ?? 'N/A');
    $lga       = htmlspecialchars($app['lga'] ?? 'N/A');

    $statusBg = match($app['status'] ?? '') {
        'Admitted'  => '#166534',
        'Rejected'  => '#991b1b',
        'Submitted' => '#92400e',
        default     => '#374151'
    };

    $edRows = '';
    foreach (($app['education'] ?? []) as $edu) {
        $edRows .= '<tr>
            <td style="padding:5px 8px;border-bottom:1px solid #e5e7eb;">' . htmlspecialchars($edu['institution'] ?? '') . '</td>
            <td style="padding:5px 8px;border-bottom:1px solid #e5e7eb;">' . htmlspecialchars($edu['degree'] ?? '') . '</td>
            <td style="padding:5px 8px;border-bottom:1px solid #e5e7eb;">' . htmlspecialchars($edu['field_of_study'] ?? '') . '</td>
            <td style="padding:5px 8px;border-bottom:1px solid #e5e7eb;">' . htmlspecialchars($edu['graduation_year'] ?? '') . '</td>
        </tr>';
    }
    if (!$edRows) {
        $edRows = '<tr><td colspan="4" style="padding:8px;color:#9ca3af;font-style:italic;">No education records found</td></tr>';
    }

    $generated = date('d F Y, H:i A');

    // Read and base64-encode the local logo file for reliable rendering in dompdf
    $logoBase64 = '';
    $logoPath = dirname(__DIR__) . '/assets/img/logo.png';
    if (file_exists($logoPath)) {
        $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
    }
    
    $logoHtml = '';
    if ($logoBase64) {
        $logoHtml = '<img src="' . $logoBase64 . '" style="height: 55px; width: auto; display: block;">';
    }

    return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #111827; margin: 0; padding: 24px; background: #fff; }
  h1 { font-size: 13.5px; font-weight: bold; margin: 0; color: #1e3a5f; }
  h2 { font-size: 11px; font-weight: bold; margin: 2px 0; color: #374151; }
  h3 { font-size: 10px; color: #6b7280; font-weight: normal; margin: 0; }
  .sec { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.08em;
         color: #1e3a5f; background: #eff6ff; padding: 4px 8px; margin: 12px 0 6px;
         border-left: 3px solid #1e3a5f; }
  table.info { width: 100%; border-collapse: collapse; }
  table.info td { padding: 4px 6px; font-size: 10px; vertical-align: top; }
  table.info td.lbl { width: 33%; color: #6b7280; font-weight: bold; }
  table.info td.val { color: #111827; }
  table.edu { width: 100%; border-collapse: collapse; font-size: 9.5px; }
  table.edu th { background: #1e3a5f; color: #fff; padding: 5px 8px; text-align: left; font-size: 9px; }
  table.edu td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; }
  table.edu tr:nth-child(even) td { background: #f9fafb; }
  .badge { display: inline; padding: 2px 10px; border-radius: 3px; font-size: 9.5px;
           font-weight: bold; color: #fff; background: ' . $statusBg . '; }
  .footer { margin-top: 20px; padding-top: 8px; border-top: 1px solid #e5e7eb;
            font-size: 8.5px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>

<!-- HEADER -->
<table width="100%" cellpadding="0" cellspacing="0" style="border-bottom: 3px solid #1e3a5f; padding-bottom: 10px; margin-bottom: 14px;">
  <tr>
    <td width="15%" style="vertical-align: middle; text-align: left; padding-bottom: 5px;">
      ' . $logoHtml . '
    </td>
    <td width="85%" style="vertical-align: middle; text-align: center; padding-right: 8%; padding-bottom: 5px;">
      <h1>INSTITUTE OF PROCUREMENT, ENVIRONMENTAL AND SOCIAL STANDARDS (IPESS)</h1>
      <h2>School of Postgraduate Studies &mdash; Postgraduate Application Slip</h2>
      <h3>PMB 2373, Makurdi, Benue State, Nigeria</h3>
    </td>
  </tr>
</table>

<!-- APP META -->
<table width="100%" cellpadding="0" cellspacing="6" style="margin-bottom:14px;">
  <tr>
    <td width="33%" style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:4px;padding:8px 10px;">
      <div style="font-size:8px;text-transform:uppercase;color:#64748b;font-weight:bold;">Application Number</div>
      <div style="font-size:13px;font-weight:bold;color:#1e3a5f;margin-top:3px;">' . $appNo . '</div>
    </td>
    <td width="4"></td>
    <td width="33%" style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:4px;padding:8px 10px;">
      <div style="font-size:8px;text-transform:uppercase;color:#64748b;font-weight:bold;">Application Status</div>
      <div style="margin-top:4px;"><span class="badge">' . $status . '</span></div>
    </td>
    <td width="4"></td>
    <td width="33%" style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:4px;padding:8px 10px;">
      <div style="font-size:8px;text-transform:uppercase;color:#64748b;font-weight:bold;">Date Submitted</div>
      <div style="font-size:11px;font-weight:bold;color:#1e3a5f;margin-top:3px;">' . $submitted . '</div>
    </td>
  </tr>
</table>

<!-- PERSONAL INFO -->
<div class="sec">Personal Information</div>
<table class="info">
  <tr><td class="lbl">Full Name</td><td class="val"><b>' . $name . '</b></td><td class="lbl">Email Address</td><td class="val">' . $email . '</td></tr>
  <tr><td class="lbl">Phone Number</td><td class="val">' . $phone . '</td><td class="lbl">Sex</td><td class="val">' . $sex . '</td></tr>
  <tr><td class="lbl">Date of Birth</td><td class="val">' . $dob . '</td><td class="lbl">Nationality</td><td class="val">' . $nationality . '</td></tr>
  <tr><td class="lbl">State of Origin</td><td class="val">' . $state . '</td><td class="lbl">LGA</td><td class="val">' . $lga . '</td></tr>
  <tr><td class="lbl">Contact Address</td><td class="val" colspan="3">' . $address . '</td></tr>
</table>

<!-- PROGRAMME -->
<div class="sec">Programme of Study</div>
<table class="info">
  <tr><td class="lbl">Faculty / College</td><td class="val">' . $faculty . '</td><td class="lbl">Department</td><td class="val">' . $dept . '</td></tr>
  <tr><td class="lbl">Degree Type</td><td class="val">' . $degree . '</td><td class="lbl">Programme</td><td class="val">' . $course . '</td></tr>
  <tr><td class="lbl">Mode of Study</td><td class="val" colspan="3">' . $mode . '</td></tr>
</table>

<!-- EDUCATION -->
<div class="sec">Previous Education</div>
<table class="edu">
  <thead><tr><th>Institution</th><th>Qualification</th><th>Field of Study</th><th>Year</th></tr></thead>
  <tbody>' . $edRows . '</tbody>
</table>

<div class="footer">
  This slip was generated on ' . $generated . ' from the IPESS Postgraduate Portal.<br>
  This document is computer-generated and does not require a signature.
</div>

</body></html>';
}


// ---------------------------------------------------------------------------
// Helper: Render HTML to PDF bytes via dompdf
// ---------------------------------------------------------------------------
function renderSlipToPdf(string $html): string {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    return $dompdf->output();
}

// ---------------------------------------------------------------------------
// Helper: Write string to temp file, return path
// ---------------------------------------------------------------------------
function writeTempFile(string $contents, string $ext = 'pdf'): string {
    $path = sys_get_temp_dir() . '/ipess_' . uniqid() . '.' . $ext;
    file_put_contents($path, $contents);
    return $path;
}

// ---------------------------------------------------------------------------
// Helper: Merge multiple PDF files into one using FPDI
// Returns merged PDF bytes, or empty string on failure
// ---------------------------------------------------------------------------
function mergePdfs(array $pdfPaths): string {
    try {
        $fpdi = new Fpdi();
        foreach ($pdfPaths as $pdfPath) {
            if (!file_exists($pdfPath)) continue;
            try {
                $pageCount = $fpdi->setSourceFile($pdfPath);
                for ($i = 1; $i <= $pageCount; $i++) {
                    $tpl = $fpdi->importPage($i);
                    $size = $fpdi->getTemplateSize($tpl);
                    $fpdi->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
                    $fpdi->useTemplate($tpl);
                }
            } catch (Throwable $e) {
                // skip non-importable PDFs
                continue;
            }
        }
        return $fpdi->Output('', 'S');
    } catch (Throwable $e) {
        return '';
    }
}

// ---------------------------------------------------------------------------
// Helper: Convert an image to a one-page PDF (via dompdf)
// ---------------------------------------------------------------------------
function imageToPdfBytes(string $filePath): string {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mime = match($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png'         => 'image/png',
        'gif'         => 'image/gif',
        default       => ''
    };
    if (!$mime || !file_exists($filePath)) return '';
    $b64 = base64_encode(file_get_contents($filePath));
    $html = '<html><body style="margin:0;padding:0;background:#fff">
        <img src="data:' . $mime . ';base64,' . $b64 . '" style="max-width:100%;max-height:100%;display:block;margin:auto">
    </body></html>';
    return renderSlipToPdf($html);
}

// ---------------------------------------------------------------------------
// Helper: Build a single merged PDF for one applicant
// Returns bytes of merged PDF
// ---------------------------------------------------------------------------
function buildApplicantPdf(PDO $pdo, int $appId): string {
    $app = fetchApplicant($pdo, $appId);
    if (!$app) return '';

    // 1. Application slip
    $slipHtml  = buildSlipHtml($app);
    $slipBytes = renderSlipToPdf($slipHtml);
    $slipPath  = writeTempFile($slipBytes, 'pdf');
    $tempFiles = [$slipPath];

    // 2. Uploaded documents
    $docPaths = [];
    foreach ($app['documents'] as $doc) {
        $physPath = resolveFilePath($doc['file_path'] ?? '');
        if (!$physPath || !file_exists($physPath)) continue;
        $ext = strtolower(pathinfo($physPath, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            $docPaths[] = $physPath;
        } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $imgPdf = imageToPdfBytes($physPath);
            if ($imgPdf) {
                $tmpImg = writeTempFile($imgPdf, 'pdf');
                $docPaths[] = $tmpImg;
                $tempFiles[] = $tmpImg;
            }
        }
        // skip .doc, .docx etc — can't merge without extra library
    }

    // 3. Merge
    $allPaths = array_merge([$slipPath], $docPaths);
    $merged   = mergePdfs($allPaths);

    // 4. Cleanup temp files
    foreach ($tempFiles as $f) { if (file_exists($f)) @unlink($f); }

    return $merged ?: $slipBytes; // fallback to slip-only if merge fails
}

// ===========================================================================
// ROUTING
// ===========================================================================

// --- Bulk download based on filters ---
if (isset($_GET['action']) && $_GET['action'] === 'bulk_zip') {
    $q            = trim((string)($_GET['q'] ?? ''));
    $filterStatus = trim($_GET['status'] ?? '');
    $filterFaculty = (int)($_GET['faculty'] ?? 0);
    $filterDept   = (int)($_GET['department'] ?? 0);
    $filterYear   = (int)($_GET['year'] ?? 0);

    $allowedStatus = ['Draft', 'Submitted', 'Admitted', 'Rejected'];
    if (!in_array($filterStatus, $allowedStatus, true)) $filterStatus = '';

    $where  = ["NOT EXISTS (SELECT 1 FROM applications nx WHERE nx.user_id = a.user_id AND nx.application_id > a.application_id)"];
    $params = [];

    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[]  = "(u.email LIKE ? OR COALESCE(pd.first_name,'') LIKE ? OR COALESCE(pd.surname,'') LIKE ? OR COALESCE(a.application_number,'') LIKE ? OR COALESCE(pd.phone,'') LIKE ?)";
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($filterStatus)  { $where[] = 'a.status = ?';          $params[] = $filterStatus; }
    if ($filterFaculty) { $where[] = 'pc.faculty = ?';         $params[] = $filterFaculty; }
    if ($filterDept)    { $where[] = 'pc.department = ?';      $params[] = $filterDept; }
    if ($filterYear)    { $where[] = 'YEAR(a.submitted_at) = ?'; $params[] = $filterYear; }

    $joinSql = "
        FROM applications a
        INNER JOIN users u ON u.user_id = a.user_id
        LEFT JOIN personal_details pd ON pd.application_id = a.application_id
        LEFT JOIN programme_choices pc ON pc.application_id = a.application_id
        LEFT JOIN faculties f ON f.faculty_id = COALESCE(pc.faculty, 0)
        LEFT JOIN departments d ON d.dept_id = COALESCE(pc.department, a.department_id)
        LEFT JOIN courses c ON c.course_id = pc.course
        LEFT JOIN degree_types dt ON dt.degree_id = pc.degree_type
        WHERE " . implode(' AND ', $where);

    $selStmt = $pdo->prepare("
        SELECT a.application_id
        $joinSql
        GROUP BY a.application_id
        ORDER BY a.updated_at DESC, a.application_id DESC
    ");
    $selStmt->execute($params);
    $ids = $selStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($ids)) {
        http_response_code(404);
        die('No applicants found matching the filters.');
    }

    // ZIP format: one PDF per candidate
    set_time_limit(300); // Allow up to 5 minutes for generation
    $zipPath = sys_get_temp_dir() . '/ipess_bulk_' . uniqid() . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        die('Could not create ZIP file.');
    }

    foreach ($ids as $appId) {
        $app = fetchApplicant($pdo, (int)$appId);
        if (!$app) continue;
        $pdfBytes = buildApplicantPdf($pdo, (int)$appId);
        if (!$pdfBytes) continue;
        $filename = 'application-' . preg_replace('/[^A-Za-z0-9\-]/', '-', $app['application_number'] ?? (string)$appId) . '.pdf';
        $zip->addFromString($filename, $pdfBytes);
    }
    $zip->close();

    $zipSize = filesize($zipPath);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="applicants-filtered-' . date('Y-m-d') . '.zip"');
    header('Content-Length: ' . $zipSize);
    header('Cache-Control: private, no-cache');
    readfile($zipPath);
    @unlink($zipPath);
    exit;
}

// --- Single download ---
if (isset($_GET['app_id']) || isset($_GET['app_no'])) {
    $appId = 0;
    if (isset($_GET['app_id'])) {
        $appId = (int)$_GET['app_id'];
    } else {
        $stmt = $pdo->prepare("SELECT application_id FROM applications WHERE application_number = ? LIMIT 1");
        $stmt->execute([trim($_GET['app_no'])]);
        $appId = (int)$stmt->fetchColumn();
    }

    if (!$appId) { http_response_code(404); die('Application not found.'); }

    // Fetch once — reuse for both PDF generation and filename
    $app = fetchApplicant($pdo, $appId);
    if (!$app) { http_response_code(404); die('Applicant data not found.'); }

    $pdfBytes = buildApplicantPdf($pdo, $appId);
    if (!$pdfBytes) { http_response_code(500); die('Failed to generate PDF.'); }

    $safeNo   = preg_replace('/[^A-Za-z0-9\-]/', '-', $app['application_number'] ?? (string)$appId);
    $filename = 'application-' . $safeNo . '.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdfBytes));
    header('Cache-Control: private, no-cache');
    echo $pdfBytes;
    exit;
}

// --- Bulk download ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids    = array_map('intval', array_filter($_POST['ids'] ?? [], 'is_numeric'));
    $format = in_array($_POST['format'] ?? '', ['zip', 'pdf']) ? $_POST['format'] : 'zip';

    if (empty($ids)) {
        http_response_code(400);
        die('No applicants selected.');
    }

    if ($format === 'pdf') {
        // All applicants merged into one big PDF
        $allTempPaths = [];
        foreach ($ids as $appId) {
            $pdfBytes = buildApplicantPdf($pdo, $appId);
            if ($pdfBytes) {
                $tmp = writeTempFile($pdfBytes, 'pdf');
                $allTempPaths[] = $tmp;
            }
        }
        $merged = mergePdfs($allTempPaths);
        foreach ($allTempPaths as $f) { if (file_exists($f)) @unlink($f); }

        if (!$merged) {
            http_response_code(500);
            die('Failed to generate bulk PDF.');
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="applicants-bulk-' . date('Y-m-d') . '.pdf"');
        header('Content-Length: ' . strlen($merged));
        echo $merged;
        exit;

    } else {
        // ZIP format: one PDF per candidate
        $zipPath = sys_get_temp_dir() . '/ipess_bulk_' . uniqid() . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            http_response_code(500);
            die('Could not create ZIP file.');
        }

        foreach ($ids as $appId) {
            $app = fetchApplicant($pdo, $appId);
            if (!$app) continue;
            $pdfBytes = buildApplicantPdf($pdo, $appId);
            if (!$pdfBytes) continue;
            $filename = 'application-' . preg_replace('/[^A-Za-z0-9\-]/', '-', $app['application_number'] ?? $appId) . '.pdf';
            $zip->addFromString($filename, $pdfBytes);
        }
        $zip->close();

        $zipSize = filesize($zipPath);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="applicants-bulk-' . date('Y-m-d') . '.zip"');
        header('Content-Length: ' . $zipSize);
        header('Cache-Control: private, no-cache');
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    }
}

http_response_code(400);
die('Invalid request.');