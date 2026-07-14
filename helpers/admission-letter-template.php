<?php

function admission_letter_column_exists(PDO $pdo, string $table, string $column): bool {
    try {
        $sanitizedTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $sanitizedColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $pdo->query("SELECT `{$sanitizedColumn}` FROM `{$sanitizedTable}` LIMIT 0");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function admission_letter_fetch(PDO $pdo, string $appNumber, ?int $userId = null): ?array {
    require_once __DIR__ . '/create_admission_processing.php';
    check_and_create_admission_processing($pdo);

    $hasCurrentStatus = admission_letter_column_exists($pdo, 'applications', 'current_status');
    $admitWhere = $hasCurrentStatus
        ? "(a.status = 'Admitted' OR a.current_status = 'ADMISSION_APPROVED')"
        : "a.status = 'Admitted'";

    $titleSelect = admission_letter_column_exists($pdo, 'personal_details', 'title') ? "p.title" : "'' AS title";
    $middleSelect = admission_letter_column_exists($pdo, 'personal_details', 'middle_name')
        ? "p.middle_name"
        : (admission_letter_column_exists($pdo, 'personal_details', 'other_name') ? "p.other_name AS middle_name" : "'' AS middle_name");
    $modeSelect = admission_letter_column_exists($pdo, 'programme_choices', 'mode_of_study') ? "pc.mode_of_study" : "'' AS mode_of_study";
    $hasApprovedAt = admission_letter_column_exists($pdo, 'applications', 'approved_at');
    $approvedSelect = $hasApprovedAt ? "a.approved_at" : "NULL AS approved_at";

    if ($appNumber === '' && $userId !== null) {
        $stmt = $pdo->prepare("SELECT application_number FROM applications WHERE user_id = ? AND {$admitWhere} ORDER BY application_id DESC LIMIT 1");
        $stmt->execute([$userId]);
        $appNumber = $stmt->fetchColumn() ?: '';
    }

    if ($appNumber === '') {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT a.application_number, a.submitted_at, a.updated_at, {$approvedSelect},
               {$titleSelect}, p.surname, p.first_name, {$middleSelect}, p.address,
               COALESCE(c.course_title, pc.course) AS course,
               COALESCE(dt.degree_name, pc.degree_type) AS degree_type,
               COALESCE(f.faculty_name, pc.faculty) AS faculty,
               COALESCE(d.dept_name, pc.department) AS department,
               COALESCE(sm.mode_name, {$modeSelect}) AS mode_of_study,
               u.email
        FROM applications a
        JOIN users u ON a.user_id = u.user_id
        LEFT JOIN personal_details p ON a.application_id = p.application_id
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
        LEFT JOIN faculties f ON pc.faculty = f.faculty_id
        LEFT JOIN departments d ON pc.department = d.dept_id
        LEFT JOIN degree_types dt ON pc.degree_type = dt.degree_id
        LEFT JOIN courses c ON pc.course = c.course_id
        LEFT JOIN study_modes sm ON pc.mode_of_study = sm.mode_id
        WHERE a.application_number = ? " . ($userId !== null ? "AND a.user_id = ?" : "") . " AND {$admitWhere}
        LIMIT 1
    ");
    $params = $userId !== null ? [$appNumber, $userId] : [$appNumber];
    $stmt->execute($params);
    $applicant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$applicant) {
        $stmt = $pdo->prepare("
            SELECT a.application_number, a.submitted_at, a.updated_at, {$approvedSelect},
                   {$titleSelect}, p.surname, p.first_name, {$middleSelect}, p.address,
                   COALESCE(c.course_title, pc.course) AS course,
                   COALESCE(dt.degree_name, pc.degree_type) AS degree_type,
                   COALESCE(f.faculty_name, pc.faculty) AS faculty,
                   COALESCE(d.dept_name, pc.department) AS department,
                   COALESCE(sm.mode_name, {$modeSelect}) AS mode_of_study,
                   u.email
            FROM applications a
            JOIN applicant_accounts u ON a.user_id = u.user_id
            LEFT JOIN personal_details p ON a.application_id = p.application_id
            LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
            LEFT JOIN faculties f ON pc.faculty = f.faculty_id
            LEFT JOIN departments d ON pc.department = d.dept_id
            LEFT JOIN degree_types dt ON pc.degree_type = dt.degree_id
            LEFT JOIN courses c ON pc.course = c.course_id
            LEFT JOIN study_modes sm ON pc.mode_of_study = sm.mode_id
            WHERE a.application_number = ? " . ($userId !== null ? "AND a.user_id = ?" : "") . " AND {$admitWhere}
            LIMIT 1
        ");
        $stmt->execute($params);
        $applicant = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if ($applicant) {
        $stmt_pass = $pdo->prepare("SELECT file_path FROM documents WHERE application_id = (SELECT application_id FROM applications WHERE application_number = ?) AND document_type = 'passport' LIMIT 1");
        $stmt_pass->execute([$applicant['application_number']]);
        $applicant['passport_path'] = $stmt_pass->fetchColumn() ?: null;

        // Fetch matric number and letter statuses
        $stmt_ap = $pdo->prepare("SELECT matric_number, admission_letter_status, acceptance_letter_status FROM admission_processing WHERE application_id = (SELECT application_id FROM applications WHERE application_number = ?) LIMIT 1");
        $stmt_ap->execute([$applicant['application_number']]);
        $ap_row = $stmt_ap->fetch(PDO::FETCH_ASSOC);
        $applicant['matric_number']            = $ap_row['matric_number']            ?? '';
        $applicant['admission_letter_status']  = $ap_row['admission_letter_status']  ?? 'Inactive';
        $applicant['acceptance_letter_status'] = $ap_row['acceptance_letter_status'] ?? 'Inactive';
    }

    return $applicant;
}

function admission_letter_logo_src(): string {
    $root = dirname(__DIR__);
    $candidates = [
        $root . '/ADMIN/images/ipess_logo.png',
        $root . '/ADMIN/images/logo.jpeg',
        $root . '/images/jostum.jpeg'
    ];
    foreach ($candidates as $path) {
        if (!file_exists($path)) {
            continue;
        }
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
        $data = base64_encode((string) file_get_contents($path));
        if ($data !== '') {
            return "data:{$mime};base64,{$data}";
        }
    }
    return '';
}

function admission_letter_passport_src(?string $path): string {
    if (!$path) return '';
    $root     = dirname(__DIR__);
    $fullPath = $root . '/' . ltrim($path, '/');
    if (file_exists($fullPath)) {
        $ext  = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
        $data = base64_encode((string) file_get_contents($fullPath));
        if ($data !== '') {
            return "data:{$mime};base64,{$data}";
        }
    }
    return '';
}

/* ══════════════════════════════════════════════════════════════════
   ADMISSION LETTER — Professional full-width A4 layout
   ══════════════════════════════════════════════════════════════════ */
function render_admission_letter_html(array $applicant, array $options = []): string {
    $includePrint = $options['include_print_button'] ?? true;
    $forPdf       = $options['for_pdf'] ?? false;

    /* ── Business logic (unchanged) ── */
    $acceptedBase = $applicant['approved_at'] ?? $applicant['updated_at'] ?? $applicant['submitted_at'] ?? date('Y-m-d');
    $acceptBy     = date('F j, Y', strtotime($acceptedBase . ' +14 days'));
    $logoSrc      = admission_letter_logo_src();
    $passportSrc  = admission_letter_passport_src($applicant['passport_path'] ?? null);

    if ($passportSrc !== '') {
        $passportHtml = "<img src=\"{$passportSrc}\" alt=\"Applicant Passport\" class=\"passport-img\">";
    } else {
        $passportHtml = "<div class=\"passport-placeholder\"><span>No<br>Photo</span></div>";
    }

    /* ── All data variables (unchanged) ── */
    $title             = htmlspecialchars($applicant['application_number'] ?? '', ENT_QUOTES, 'UTF-8');
    $fullName          = strtoupper(trim(($applicant['surname'] ?? '') . ' ' . ($applicant['first_name'] ?? '') . ' ' . ($applicant['middle_name'] ?? '')));
    $fullName          = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
    $address           = htmlspecialchars($applicant['address'] ?? '', ENT_QUOTES, 'UTF-8');
    $email             = htmlspecialchars($applicant['email'] ?? '', ENT_QUOTES, 'UTF-8');
    $salute            = htmlspecialchars(trim(($applicant['title'] ?? '') . ' ' . ($applicant['surname'] ?? '')), ENT_QUOTES, 'UTF-8');
    $applicationNumber = htmlspecialchars($applicant['application_number'] ?? '', ENT_QUOTES, 'UTF-8');
    $degreeType        = htmlspecialchars($applicant['degree_type'] ?? '', ENT_QUOTES, 'UTF-8');
    $course            = htmlspecialchars($applicant['course'] ?? '', ENT_QUOTES, 'UTF-8');
    $faculty           = htmlspecialchars($applicant['faculty'] ?? 'Institute of Procurement, Environmental and Social Standard (IPESS)', ENT_QUOTES, 'UTF-8');
    $department        = htmlspecialchars($applicant['department'] ?? '', ENT_QUOTES, 'UTF-8');
    $mode              = htmlspecialchars($applicant['mode_of_study'] ?? 'Postgraduate', ENT_QUOTES, 'UTF-8');
    $matricNumber      = htmlspecialchars($applicant['matric_number'] ?? '', ENT_QUOTES, 'UTF-8');
    $matricRow         = $matricNumber !== ''
        ? "<div class=\"info-row\"><span class=\"info-lbl\">Matric No.:</span><span class=\"info-val\"><strong class=\"accent\">{$matricNumber}</strong></span></div>"
        : '';

    $fontBody    = $forPdf ? 'Arial, sans-serif'    : '"Segoe UI", Arial, sans-serif';
    $fontHeading = $forPdf ? 'Georgia, serif'        : '"Merriweather", Georgia, serif';
    $printBtn    = $includePrint ? '<button onclick="window.print()" class="print-btn">&#128438; Print / Save</button>' : '';
    $shadow      = $forPdf ? 'none' : '0 2px 24px rgba(0,0,0,.10)';
    $bg          = $forPdf ? '#fff' : '#eef0f3';

    $today    = date('F j, Y');
    $year     = date('Y');
    $nextYear = (string)((int)$year + 1);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admission Letter – {$title}</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{background:{$bg};font-family:{$fontBody};color:#222;font-size:9.5pt;line-height:1.55;-webkit-print-color-adjust:exact;print-color-adjust:exact}
h1,h2,h3,h4{font-family:{$fontHeading};color:#063b29}

/* Page wrapper */
.page{width:210mm;max-width:100%;min-height:297mm;margin:0 auto;background:#fff;box-shadow:{$shadow};display:flex;flex-direction:column}

/* Letterhead band */
.lh-band{background:#063b29;padding:7px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.lh-logo{width:60px;height:60px;object-fit:contain;flex-shrink:0}
.lh-titles{flex:1;text-align:center;color:#fff}
.lh-main{font-size:11.5pt;font-weight:800;text-transform:uppercase;letter-spacing:.6px;line-height:1.2}
.lh-sub{font-size:9pt;font-weight:600;margin-top:3px;opacity:.93}
.lh-dept{font-size:7.5pt;margin-top:2px;opacity:.8;font-style:italic}
.lh-addr{font-size:7pt;margin-top:2px;opacity:.75}
.lh-passport{flex-shrink:0;display:flex;align-items:center;justify-content:flex-end}
.passport-img{width:72px;height:88px;object-fit:cover;display:block;border:2px solid rgba(255,255,255,.6)}
.passport-placeholder{width:72px;height:88px;display:flex;align-items:center;justify-content:center;font-size:7pt;color:rgba(255,255,255,.6);text-align:center;border:1px dashed rgba(255,255,255,.4)}

/* Ref / Date bar */
.ref-bar{background:#f4f9f6;border-bottom:2px solid #063b29;padding:5px 14px;display:flex;align-items:center;justify-content:space-between;font-size:8.5pt}
.ref-bar strong{color:#063b29}

/* Two-column info panel */
.info-panel{display:grid;grid-template-columns:1fr 1fr;gap:0;border-bottom:2px solid #dde6e0}
.info-col{padding:10px 14px}
.info-col+.info-col{border-left:1px solid #dde6e0;background:#f8fdf9}
.info-col h4{font-size:7.5pt;text-transform:uppercase;letter-spacing:.8px;color:#063b29;border-bottom:1px solid #c8dfd0;padding-bottom:4px;margin-bottom:7px;font-weight:700}
.info-row{display:flex;gap:6px;margin-bottom:4px;font-size:8.5pt}
.info-lbl{color:#555;width:110px;flex-shrink:0;font-weight:600}
.info-val{color:#111;flex:1;word-break:break-word;overflow-wrap:break-word}
.accent{color:#063b29;font-weight:800}

/* Letter body — full width */
.body{padding:12px 14px 0;flex:1}
.salutation{margin:8px 0 4px;font-size:9.5pt}
.letter-heading{text-align:center;font-size:11pt;font-weight:800;text-decoration:underline;text-transform:uppercase;letter-spacing:.6px;color:#063b29;margin:10px 0 8px}
.body p{text-align:justify;margin-bottom:8px;font-size:9.5pt}

/* Section boxes */
.section-box{border-radius:5px;padding:10px 13px;margin:10px 0;page-break-inside:avoid}
.box-conditions{background:#f4fbf7;border:1px solid #b8ddc8;border-left:4px solid #063b29}
.box-notice    {background:#fffbf2;border:1px solid #f0dfa0;border-left:4px solid #c8960c}
.box-verify    {background:#f0f6ff;border:1px solid #b8cef0;border-left:4px solid #1a4388}
.section-box h4{font-size:8.5pt;text-transform:uppercase;letter-spacing:.5px;font-weight:800;margin-bottom:7px}
.box-conditions h4{color:#063b29}
.box-notice h4{color:#7a5c00}
.box-verify h4{color:#1a3a7a}
.section-box p{margin:0 0 6px;font-size:9pt;line-height:1.55}
.section-box ol,.section-box ul{padding-left:16px;margin:0}
.section-box li{margin-bottom:5px;font-size:9pt;line-height:1.5}

/* Signature */
.sig-area{padding:14px 14px 16px}
.sig-line{border-top:1px solid #222;width:200px;margin:26px 0 4px}
.sig-name{font-weight:800;font-size:9.5pt}
.sig-role{font-size:8.5pt;color:#555;font-style:italic;margin-top:2px}

/* Print button (screen only) */
.print-btn{position:fixed;top:18px;right:18px;padding:8px 18px;background:#063b29;color:#fff;border:none;border-radius:5px;cursor:pointer;font-size:8.5pt;font-family:sans-serif;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.25)}
.print-btn:hover{background:#0a5c40}

@media print{
  body{background:#fff}
  .page{box-shadow:none;min-height:0;width:100%;max-width:100%}
  .print-btn{display:none}
}
</style>
</head>
<body>
{$printBtn}
<div class="page">

  <!-- ═══ LETTERHEAD ═══════════════════════════════════════════ -->
  <div class="lh-band">
    <img src="{$logoSrc}" alt="IPESS Logo" class="lh-logo">
    <div class="lh-titles">
      <div class="lh-main">Institute of Procurement, Environmental and Social Standard (IPESS)</div>
      <div class="lh-sub">Joseph Sarwuan Tarka University, Makurdi</div>
      <div class="lh-dept">Office of the Registrar, (Formerly University of Agriculture, Makurdi)</div>
      <div class="lh-addr">P.M.B. 2373, Makurdi, Benue State, Nigeria</div>
    </div>
    <div class="lh-passport">
      {$passportHtml}
    </div>
  </div>

  <!-- ═══ REF / DATE BAR ════════════════════════════════════════ -->
  <div class="ref-bar">
    <span><strong>Ref:</strong> JOSTUM/ADM/{$year}/{$applicationNumber}</span>
    <span><strong>Date:</strong> {$today}</span>
  </div>

  <!-- ═══ TWO-COLUMN INFO PANEL ════════════════════════════════ -->
  <div class="info-panel">
    <!-- Left: Applicant details -->
    <div class="info-col">
      <h4>Applicant Information</h4>
      <div class="info-row"><span class="info-lbl">Full Name:</span><span class="info-val"><strong>{$fullName}</strong></span></div>
      <div class="info-row"><span class="info-lbl">Application No.:</span><span class="info-val">{$applicationNumber}</span></div>
      <div class="info-row"><span class="info-lbl">Email Address:</span><span class="info-val">{$email}</span></div>
      <div class="info-row"><span class="info-lbl">Postal Address:</span><span class="info-val">{$address}</span></div>
    </div>
    <!-- Right: Admission details -->
    <div class="info-col">
      <h4>Admission Details</h4>
      <div class="info-row"><span class="info-lbl">Programme:</span><span class="info-val"><strong>{$degreeType} in {$course}</strong></span></div>
      <div class="info-row"><span class="info-lbl">Institute:</span><span class="info-val">{$faculty}</span></div>
      <div class="info-row"><span class="info-lbl">Department:</span><span class="info-val">{$department}</span></div>
      <div class="info-row"><span class="info-lbl">Mode of Study:</span><span class="info-val">{$mode}</span></div>
      <div class="info-row"><span class="info-lbl">Duration:</span><span class="info-val">As approved by Senate</span></div>
      <div class="info-row"><span class="info-lbl">Academic Session:</span><span class="info-val"><strong>{$year}/{$nextYear}</strong></span></div>
      {$matricRow}
    </div>
  </div>

  <!-- ═══ LETTER BODY ═══════════════════════════════════════════ -->
  <div class="body">
    <p class="salutation">Dear {$salute},</p>

    <h2 class="letter-heading">Offer of Provisional Admission</h2>

    <p>
      I am pleased to inform you that following a careful review of your application, you have been offered
      <strong>Provisional Admission</strong> into the <strong>Institute of Procurement, Environmental and
      Social Standard (IPESS), Joseph Sarwuan Tarka University (JOSTUM), Makurdi</strong>, for the
      <strong>{$year}/{$nextYear} Academic Session.</strong>
    </p>

    <p>
      You are required to <strong>accept this offer on or before {$acceptBy}</strong> (fourteen calendar days
      from the date of this letter). Failure to respond within the stipulated period may result in the
      automatic forfeiture of this offer without further notice.
    </p>

    <!-- ── Conditions of Admission ── -->
    <div class="section-box box-conditions">
      <h4>Conditions of Admission</h4>
      <p>This offer is strictly provisional and is subject to the following conditions:</p>
      <ol>
        <li>
          <strong>Verification of Credentials:</strong> You must present the <em>original copies</em> of all
          credentials — including O'Level results, Degree/HND certificates, NYSC Discharge/Exemption
          certificate, and Birth Certificate — for physical clearance before or at registration.
        </li>
        <li>
          <strong>Acceptance Fee:</strong> Payment of a non-refundable acceptance fee of <em>[Amount in Naira]</em>
          must be made via the university portal on or before <strong>{$acceptBy}</strong>. Failure to pay
          within this period will result in the forfeiture of this admission offer.
        </li>
        <li>
          <strong>Registration:</strong> You are required to complete your academic and departmental
          registration within <strong>two (2) weeks</strong> of the official resumption date for the session.
        </li>
        <li>
          <strong>Academic Regulations:</strong> You are expected to abide by all rules and regulations
          governing students of IPESS and JOSTUM at all times. The Institute reserves the right to withdraw
          this admission should it be discovered that you do not possess the required qualifications or that
          this admission was obtained through fraudulent means.
        </li>
      </ol>
    </div>

    <!-- ── Documents Required for Clearance ── -->
    <div class="section-box box-verify">
      <h4>Documents Required for Clearance</h4>
      <ul>
        <li>Original and photocopy of O'Level result(s)</li>
        <li>Original and photocopy of First Degree / HND Certificate or Statement of Result</li>
        <li>Original and photocopy of NYSC Discharge Certificate / Exemption Letter</li>
        <li>Original and photocopy of Birth Certificate / Sworn Declaration of Age</li>
        <li>Four (4) recent passport photographs (white background)</li>
        <li>Printed copy of this Admission Letter and Acceptance Letter</li>
        <li>Evidence of acceptance fee payment</li>
      </ul>
    </div>

    <!-- ── Important Notices ── -->
    <div class="section-box box-notice">
      <h4>Important Notices</h4>
      <ul>
        <li>This letter is <strong>not transferable</strong> and is valid only for the named applicant.</li>
        <li>Ensure all documents submitted during the application are authentic; falsification is grounds for automatic withdrawal of this offer.</li>
        <li>The acceptance fee is <strong>non-refundable</strong> under any circumstances once paid.</li>
        <li>Physical presence is mandatory at the IPESS Secretariat for clearance; proxies will <strong>not</strong> be accepted.</li>
      </ul>
    </div>

    <p>We congratulate you on this offer and look forward to welcoming you to IPESS, JOSTUM.</p>

  </div><!-- /.body -->

  <!-- ═══ SIGNATURE ════════════════════════════════════════════ -->
  <div class="sig-area">
    <p>Yours faithfully,</p>
    <div class="sig-line"></div>
    <p class="sig-name">Registrar</p>
    <p class="sig-role">For: Vice-Chancellor,<br>Joseph Sarwuan Tarka University, Makurdi</p>
  </div>

</div><!-- /.page -->
</body>
</html>
HTML;
}

/* ══════════════════════════════════════════════════════════════════
   ACCEPTANCE LETTER — Professional full-width A4 layout
   ══════════════════════════════════════════════════════════════════ */
function render_acceptance_letter_html(array $applicant, array $options = []): string {
    $includePrint = $options['include_print_button'] ?? true;
    $forPdf       = $options['for_pdf'] ?? false;

    /* ── Business logic (unchanged) ── */
    $acceptedBase = $applicant['approved_at'] ?? $applicant['updated_at'] ?? $applicant['submitted_at'] ?? date('Y-m-d');
    $deadline     = date('F j, Y', strtotime($acceptedBase . ' +14 days'));
    $logoSrc      = admission_letter_logo_src();
    $passportSrc  = admission_letter_passport_src($applicant['passport_path'] ?? null);

    if ($passportSrc !== '') {
        $passportHtml = "<img src=\"{$passportSrc}\" alt=\"Applicant Passport\" class=\"passport-img\">";
    } else {
        $passportHtml = "<div class=\"passport-placeholder\"><span>No<br>Photo</span></div>";
    }

    /* ── All data variables (unchanged) ── */
    $fullName          = strtoupper(trim(($applicant['surname'] ?? '') . ' ' . ($applicant['first_name'] ?? '') . ' ' . ($applicant['middle_name'] ?? '')));
    $fullName          = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
    $salute            = htmlspecialchars(trim(($applicant['title'] ?? '') . ' ' . ($applicant['surname'] ?? '')), ENT_QUOTES, 'UTF-8');
    $applicationNumber = htmlspecialchars($applicant['application_number'] ?? '', ENT_QUOTES, 'UTF-8');
    $degreeType        = htmlspecialchars($applicant['degree_type'] ?? '', ENT_QUOTES, 'UTF-8');
    $course            = htmlspecialchars($applicant['course'] ?? '', ENT_QUOTES, 'UTF-8');
    $faculty           = htmlspecialchars($applicant['faculty'] ?? 'Institute of Procurement, Environmental and Social Standard (IPESS)', ENT_QUOTES, 'UTF-8');
    $department        = htmlspecialchars($applicant['department'] ?? '', ENT_QUOTES, 'UTF-8');
    $mode              = htmlspecialchars($applicant['mode_of_study'] ?? 'Postgraduate', ENT_QUOTES, 'UTF-8');
    $matricNumber      = htmlspecialchars($applicant['matric_number'] ?? '', ENT_QUOTES, 'UTF-8');
    $email             = htmlspecialchars($applicant['email'] ?? '', ENT_QUOTES, 'UTF-8');
    $address           = htmlspecialchars($applicant['address'] ?? '', ENT_QUOTES, 'UTF-8');
    $matricRow         = $matricNumber !== ''
        ? "<div class=\"info-row\"><span class=\"info-lbl\">Matric No.:</span><span class=\"info-val\"><strong class=\"accent\">{$matricNumber}</strong></span></div>"
        : '';

    $fontBody    = $forPdf ? 'Arial, sans-serif'    : '"Segoe UI", Arial, sans-serif';
    $fontHeading = $forPdf ? 'Georgia, serif'        : '"Merriweather", Georgia, serif';
    $printBtn    = $includePrint ? '<button onclick="window.print()" class="print-btn">&#128438; Print / Save</button>' : '';
    $shadow      = $forPdf ? 'none' : '0 2px 24px rgba(0,0,0,.10)';
    $bg          = $forPdf ? '#fff' : '#eef0f3';

    $today    = date('F j, Y');
    $year     = date('Y');
    $nextYear = (string)((int)$year + 1);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Acceptance Letter – {$applicationNumber}</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{background:{$bg};font-family:{$fontBody};color:#222;font-size:9.5pt;line-height:1.55;-webkit-print-color-adjust:exact;print-color-adjust:exact}
h1,h2,h3,h4{font-family:{$fontHeading};color:#063b29}
.page{width:210mm;max-width:100%;min-height:297mm;margin:0 auto;background:#fff;box-shadow:{$shadow};display:flex;flex-direction:column}

/* Letterhead */
.lh-band{background:#063b29;padding:7px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.lh-logo{width:60px;height:60px;object-fit:contain;flex-shrink:0}
.lh-titles{flex:1;text-align:center;color:#fff}
.lh-main{font-size:11.5pt;font-weight:800;text-transform:uppercase;letter-spacing:.6px;line-height:1.2}
.lh-sub{font-size:9pt;font-weight:600;margin-top:3px;opacity:.93}
.lh-dept{font-size:7.5pt;margin-top:2px;opacity:.8;font-style:italic}
.lh-addr{font-size:7pt;margin-top:2px;opacity:.75}
.lh-passport{flex-shrink:0;display:flex;align-items:center;justify-content:flex-end}
.passport-img{width:72px;height:88px;object-fit:cover;display:block;border:2px solid rgba(255,255,255,.6)}
.passport-placeholder{width:72px;height:88px;display:flex;align-items:center;justify-content:center;font-size:7pt;color:rgba(255,255,255,.6);text-align:center;border:1px dashed rgba(255,255,255,.4)}

/* Ref bar */
.ref-bar{background:#f4f9f6;border-bottom:2px solid #063b29;padding:5px 14px;display:flex;align-items:center;justify-content:space-between;font-size:8.5pt}
.ref-bar strong{color:#063b29}

/* Confirmed badge */
.confirm-badge{background:#063b29;color:#fff;text-align:center;padding:5px 14px;font-size:8pt;letter-spacing:.8px;font-weight:700;text-transform:uppercase;-webkit-print-color-adjust:exact;print-color-adjust:exact}

/* Two-column info panel */
.info-panel{display:grid;grid-template-columns:1fr 1fr;gap:0;border-bottom:2px solid #dde6e0}
.info-col{padding:10px 14px}
.info-col+.info-col{border-left:1px solid #dde6e0;background:#f8fdf9}
.info-col h4{font-size:7.5pt;text-transform:uppercase;letter-spacing:.8px;color:#063b29;border-bottom:1px solid #c8dfd0;padding-bottom:4px;margin-bottom:7px;font-weight:700}
.info-row{display:flex;gap:6px;margin-bottom:4px;font-size:8.5pt}
.info-lbl{color:#555;width:110px;flex-shrink:0;font-weight:600}
.info-val{color:#111;flex:1;word-break:break-word;overflow-wrap:break-word}
.accent{color:#063b29;font-weight:800}

/* Body */
.body{padding:12px 14px 0;flex:1}
.salutation{margin:8px 0 4px;font-size:9.5pt}
.letter-heading{text-align:center;font-size:11pt;font-weight:800;text-decoration:underline;text-transform:uppercase;letter-spacing:.6px;color:#063b29;margin:10px 0 8px}
.body p{text-align:justify;margin-bottom:8px;font-size:9.5pt}

/* Section boxes */
.section-box{border-radius:5px;padding:10px 13px;margin:10px 0;page-break-inside:avoid}
.box-confirm{background:#f0fdf4;border:2px solid #063b29;border-radius:6px}
.box-notice {background:#fffbf2;border:1px solid #f0dfa0;border-left:4px solid #c8960c}
.box-verify {background:#f0f6ff;border:1px solid #b8cef0;border-left:4px solid #1a4388}
.section-box h4{font-size:8.5pt;text-transform:uppercase;letter-spacing:.5px;font-weight:800;margin-bottom:7px}
.box-confirm h4{color:#063b29}
.box-notice h4{color:#7a5c00}
.box-verify h4{color:#1a3a7a}
.section-box p{margin:0 0 4px;font-size:9pt;line-height:1.55}
.section-box ul{padding-left:16px;margin:0}
.section-box li{margin-bottom:5px;font-size:9pt;line-height:1.5}

/* Signature */
.sig-area{padding:14px 14px 16px}
.sig-line{border-top:1px solid #222;width:200px;margin:26px 0 4px}
.sig-name{font-weight:800;font-size:9.5pt}
.sig-role{font-size:8.5pt;color:#555;font-style:italic;margin-top:2px}

/* Print button */
.print-btn{position:fixed;top:18px;right:18px;padding:8px 18px;background:#063b29;color:#fff;border:none;border-radius:5px;cursor:pointer;font-size:8.5pt;font-family:sans-serif;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.25)}
.print-btn:hover{background:#0a5c40}

@media print{
  body{background:#fff}
  .page{box-shadow:none;min-height:0;width:100%;max-width:100%}
  .print-btn{display:none}
}
</style>
</head>
<body>
{$printBtn}
<div class="page">

  <!-- ═══ LETTERHEAD ═══════════════════════════════════════════ -->
  <div class="lh-band">
    <img src="{$logoSrc}" alt="IPESS Logo" class="lh-logo">
    <div class="lh-titles">
      <div class="lh-main">Institute of Procurement, Environmental and Social Standard (IPESS)</div>
      <div class="lh-sub">Joseph Sarwuan Tarka University, Makurdi</div>
      <div class="lh-dept">Office of the Registrar, (Formerly University of Agriculture, Makurdi)</div>
      <div class="lh-addr">P.M.B. 2373, Makurdi, Benue State, Nigeria</div>
    </div>
    <div class="lh-passport">
      {$passportHtml}
    </div>
  </div>

  <!-- ═══ REF / DATE BAR ════════════════════════════════════════ -->
  <div class="ref-bar">
    <span><strong>Ref:</strong> JOSTUM/ACC/{$year}/{$applicationNumber}</span>
    <span><strong>Date:</strong> {$today}</span>
  </div>

  <!-- ═══ CONFIRMED BADGE ═══════════════════════════════════════ -->
  <div class="confirm-badge">Admission Acceptance Confirmation</div>

  <!-- ═══ TWO-COLUMN INFO PANEL ════════════════════════════════ -->
  <div class="info-panel">
    <div class="info-col">
      <h4>Applicant Information </h4>
      <div class="info-row"><span class="info-lbl">Full Name:</span><span class="info-val"><strong>{$fullName}</strong></span></div>
      <div class="info-row"><span class="info-lbl">Application No.:</span><span class="info-val">{$applicationNumber}</span></div>
      <div class="info-row"><span class="info-lbl">Email Address:</span><span class="info-val">{$email}</span></div>
      <div class="info-row"><span class="info-lbl">Postal Address:</span><span class="info-val">{$address}</span></div>
    </div>
    <div class="info-col">
      <h4>Programme Details</h4>
      <div class="info-row"><span class="info-lbl">Programme:</span><span class="info-val"><strong>{$degreeType} in {$course}</strong></span></div>
      <div class="info-row"><span class="info-lbl">Institute:</span><span class="info-val">{$faculty}</span></div>
      <div class="info-row"><span class="info-lbl">Department:</span><span class="info-val">{$department}</span></div>
      <div class="info-row"><span class="info-lbl">Mode of Study:</span><span class="info-val">{$mode}</span></div>
      <div class="info-row"><span class="info-lbl">Academic Session:</span><span class="info-val"><strong>{$year}/{$nextYear}</strong></span></div>
      {$matricRow}
    </div>
  </div>

  <!-- ═══ LETTER BODY ═══════════════════════════════════════════ -->
  <div class="body">

    <h2 class="letter-heading">Acceptance of Offer of Provisional Admission</h2>

    <p>
      I, <strong>{$fullName}</strong>, hereby formally accept the Offer of Provisional Admission
      granted to me by the <strong>Institute of Procurement, Environmental and Social Standard (IPESS),
      Joseph Sarwuan Tarka University (JOSTUM), Makurdi</strong>, for the
      <strong>{$year}/{$nextYear} Academic Session.</strong>
    </p>

    <!-- Acceptance confirmation box -->
    <div class="section-box box-confirm">
      <h4>Acceptance Confirmation</h4>
      <p>
        I, <strong>{$fullName}</strong>, confirm that I have read, understood, and agreed to all the
        <strong>Conditions of Admission</strong> as stated in the Offer of Admission letter issued to me.
        I further confirm that I will pay or have already paid the non-refundable acceptance fee on or
        before <strong>{$deadline}</strong>, and that I accept full responsibility for meeting all
        requirements for registration and clearance.
      </p>
    </div>

    <!-- Registration Undertaking -->
    <div class="section-box box-verify">
      <h4>My Registration Undertaking</h4>
      <ul>
        <li>I will report to the IPESS Secretariat with this letter and all required original documents for physical clearance.</li>
        <li>I will complete my academic and departmental registration within <strong>two (2) weeks</strong> of the official resumption date.</li>
        <li>I will obtain my student ID card from the ICT Centre after successful clearance.</li>
        <li>I will collect my course registration form from the Postgraduate Coordinator.</li>
      </ul>
    </div>

    <!-- Acknowledgements -->
    <div class="section-box box-notice">
      <h4>My Acknowledgements</h4>
      <ul>
        <li>I understand that I must present this letter together with my <strong>Offer of Admission Letter</strong> at the time of clearance.</li>
        <li>I acknowledge that this letter is <strong>not transferable</strong> and is issued solely in my name.</li>
        <li>I confirm that all documents I submitted during the application process are authentic, and I understand that any falsification is grounds for the withdrawal of this admission.</li>
        <li>I agree to appear <strong>in person</strong> for clearance; I will not send a proxy on my behalf.</li>
      </ul>
    </div>

    <p>
      I am grateful for this opportunity and look forward to a rewarding academic experience at
      IPESS, Joseph Sarwuan Tarka University, Makurdi.
    </p>

  </div><!-- /.body -->

  <!-- ═══ STUDENT SIGNATURE ════════════════════════════════════ -->
  <div class="sig-area">
    <p>Yours faithfully,</p>
    <div class="sig-line"></div>
    <p class="sig-name">{$fullName}</p>
    <p class="sig-role">Applicant, Application No.: {$applicationNumber}</p>
    <p class="sig-role">Date: {$today}</p>
  </div>

</div><!-- /.page -->
</body>
</html>
HTML;
}
