<?php

function admission_letter_column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function admission_letter_fetch(PDO $pdo, string $appNumber, ?int $userId = null): ?array {
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

    if ($applicant) {
        return $applicant;
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
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function admission_letter_logo_src(): string {
    $root = dirname(__DIR__);
    $candidates = [
        $root . '/images/logo.jpeg',
        $root . '/images/jostum.jpeg'
    ];
    foreach ($candidates as $path) {
        if (!file_exists($path)) {
            continue;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
        $data = base64_encode((string) file_get_contents($path));
        if ($data !== '') {
            return "data:{$mime};base64,{$data}";
        }
    }
    return '';
}

function render_admission_letter_html(array $applicant, array $options = []): string {
    $includePrint = $options['include_print_button'] ?? true;
    $forPdf = $options['for_pdf'] ?? false;

    $acceptedBase = $applicant['approved_at'] ?? $applicant['updated_at'] ?? $applicant['submitted_at'] ?? date('Y-m-d');
    $acceptBy = date('F j, Y', strtotime($acceptedBase . ' +14 days'));
    $logoSrc = admission_letter_logo_src();

    $title = htmlspecialchars($applicant['application_number'] ?? '', ENT_QUOTES, 'UTF-8');
    $surname = strtoupper(trim(($applicant['surname'] ?? '') . ' ' . ($applicant['first_name'] ?? '') . ' ' . ($applicant['middle_name'] ?? '')));
    $surname = htmlspecialchars($surname, ENT_QUOTES, 'UTF-8');
    $address = htmlspecialchars($applicant['address'] ?? '', ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($applicant['email'] ?? '', ENT_QUOTES, 'UTF-8');
    $salute = htmlspecialchars(trim(($applicant['title'] ?? '') . ' ' . ($applicant['surname'] ?? '')), ENT_QUOTES, 'UTF-8');
    $applicationNumber = htmlspecialchars($applicant['application_number'] ?? '', ENT_QUOTES, 'UTF-8');
    $degreeType = htmlspecialchars($applicant['degree_type'] ?? '', ENT_QUOTES, 'UTF-8');
    $course = htmlspecialchars($applicant['course'] ?? '', ENT_QUOTES, 'UTF-8');
    $faculty = htmlspecialchars($applicant['faculty'] ?? '', ENT_QUOTES, 'UTF-8');
    $mode = htmlspecialchars($applicant['mode_of_study'] ?? 'Postgraduate', ENT_QUOTES, 'UTF-8');

    $fontBody = $forPdf ? "DejaVu Sans, Arial, sans-serif" : "\"Source Sans 3\", \"Segoe UI\", sans-serif";
    $fontHeading = $forPdf ? "DejaVu Sans, Georgia, serif" : "\"Merriweather\", Georgia, serif";
    $printButton = $includePrint ? '<button onclick="window.print()" class="print-button">Print Letter</button>' : '';

    $boxShadow = $forPdf ? 'none' : '0 0 20px rgba(0,0,0,0.1)';
    $background = $forPdf ? '#ffffff' : '#f1f3f6';

    $today = date('F j, Y');
    $year = date('Y');
    $nextYear = (string) ((int) $year + 1);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="/JOSTUM/ADMIN/images/logo.jpeg">
<meta charset="UTF-8">
    <title>Admission Letter for {$title}</title>
    <style>
        :root {
            --brand-blue: #063b29;
            --text-dark: #333;
            --text-muted: #666;
            --border-light: #eee;
        }

        body {
            background-color: {$background};
            font-family: {$fontBody};
            color: var(--text-dark);
            margin: 0;
            padding: 0;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: {$fontHeading};
            color: #063b29;
        }

        .letter-container {
            width: 100%;
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 8mm 10mm 15mm;
            border-radius: 4px;
            box-shadow: {$boxShadow};
        }

        .header-top {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 15px;
            margin-bottom: 6px;
        }

        .uni-logo { width: 110px; height: auto; }
        .header-titles h2 { color: var(--brand-blue); font-weight: 800; margin: 0; text-transform: uppercase; font-size: 1.2rem; line-height: 1.3; }
        .header-titles p { color: var(--text-muted); margin: 5px 0; font-size: 1rem; font-weight: 600; text-transform: uppercase; }
        .header-ruler {
            width: 100%;
            height: 0;
            border-top: 4px solid #063b29;
            border-bottom: 2px solid #063b29;
            margin: 8px 0 4px;
        }

        .letter-body { margin-top: 12px; font-size: 0.95rem; line-height: 1.55; }
        .recipient-address strong { font-weight: 700; }
        .recipient-address, .date-line { margin-bottom: 12px; }
        .letter-title { text-align: center; font-weight: 700; text-decoration: underline; font-size: 1rem; margin: 12px 0; color: #063b29; }
        .highlight { font-weight: 700; }
        .signature-block { margin-top: 32px; }
        .signature-line { border-top: 1px solid #000; width: 250px; margin-top: 30px; }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #1a4388;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: sans-serif;
        }

        @media (min-width: 768px) {
            .letter-container { padding: 8mm 10mm 15mm; margin: 0 auto; }
            .header-top { display: grid; grid-template-columns: 120px 1fr 120px; }
            .uni-logo { width: 110px; }
            .header-titles h2 { font-size: 1.6rem; }
        }

        @media print {
            body { background: white; margin: 0; }
            .print-button { display: none; }
            .letter-container { box-shadow: none; margin: 0; width: 100%; max-width: 100%; padding: 8mm 10mm 15mm; }
            .header-top { display: grid; grid-template-columns: 100px 1fr 100px; gap: 20px; }
            .uni-logo { width: 90px; }
            .header-titles h2 { font-size: 18pt; }
        }
    </style>
</head>
<body>
    {$printButton}
    <div class="letter-container">
        <header>
            <div class="header-top">
                <div class="logo-box">
                    <img src="{$logoSrc}" alt="JOSTUM Logo" class="uni-logo">
                </div>
                <div class="header-titles">
                    <h2>Joseph Sarwuan Tarka University, Makurdi</h2>
                    <p>Office of the Registrar</p>
                    <div class="fw-bold mt-1 text-dark" style="font-size: 0.85rem;">(Formerly University of Agriculture, Makurdi)</div>
                    <div class="fw-bold mt-1 text-dark" style="font-size: 0.85rem;">P.M.B. 2373, Makurdi, Benue State, Nigeria.</div>
                </div>
                <div class="d-none d-md-block"></div>
            </div>
            <div class="header-ruler" aria-hidden="true"></div>
        </header>

        <div class="letter-body">
        <div class="date-line">
            Date: {$today}
        </div>
        <div class="recipient-address">
            <span class="highlight">Our Ref:</span> JOSTUM/ADM/{$year}/{$applicationNumber}
        </div>

        <div class="recipient-address">
            <span class="highlight">To:</span><br>
            <strong>Name:</strong> {$surname}<br>
            <strong>Application Number:</strong> {$applicationNumber}<br>
            <strong>Address:</strong> {$address}<br>
            <strong>Email:</strong> {$email}
        </div>

        <div class="salutation">
            Dear {$salute},
        </div>

        <h3 class="letter-title">OFFER OF PROVISIONAL ADMISSION</h3>

        <p>
            I am pleased to inform you that you have been offered Provisional Admission into the Joseph Sarwuan Tarka University, Makurdi (JOSTUM) for the {$year}/{$nextYear} Academic Session.
        </p>

        <p>
            You have been admitted to study:
        </p>

        <ul>
            <li><strong>Programme:</strong> <span class="highlight">{$degreeType} in {$course}</span></li>
            <li><strong>Faculty/College:</strong> <span class="highlight">{$faculty}</span></li>
            <li><strong>Mode of Study:</strong> <span class="highlight">{$mode}</span></li>
            <li><strong>Duration:</strong> As approved by Senate</li>
        </ul>

        <p>
            You are required to accept this offer within fourteen (14) days of the date of this letter. If you do not accept within the stipulated time, the offer may lapse.
        </p>

        <p>
            Please note that the acceptance fee is non-refundable.
        </p>

        <p>
            Accept the offer and complete registration on or before <strong>{$acceptBy}</strong>.
        </p>

        <div class="signature-block">
            <p>Yours faithfully,</p>
            <div class="signature-line"></div>
            <p><strong>Registrar</strong></p>
        </div>
        </div>
    </div>
</body>
</html>
HTML;
}
