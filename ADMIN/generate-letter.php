<?php
session_start();
require 'db.php';

if (!isset($_GET['app_no'])) {
    header("Location: admission-decisions.php");
    exit();
}

$appNumber = $_GET['app_no'];

try {
    $stmt = $pdo->prepare("
        SELECT a.application_number, a.submitted_at, p.title, p.surname, p.first_name, p.middle_name, p.address, pc.course, pc.degree_type, pc.faculty, pc.department, pc.mode_of_study, u.email,
               ap.admission_letter_status
        FROM applications a
        JOIN users u ON a.user_id = u.user_id
        LEFT JOIN personal_details p ON a.application_id = p.application_id
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
        LEFT JOIN admission_processing ap ON a.application_id = ap.application_id
        WHERE a.application_number = ? AND a.status = 'Admitted'
    ");
    $stmt->execute([$appNumber]);
    $applicant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$applicant) {
        die("<h1>Admission Letter Not Available</h1><p>This admission letter cannot be generated. The application was not found or has not been approved.</p><a href='admission-decisions.php'>Go back</a>");
    }

    if (($applicant['admission_letter_status'] ?? 'Inactive') !== 'Active') {
        die("<h1>Admission Letter Not Activated</h1><p>Your admission letter has not been activated yet by the ICT department. Please check back later.</p>");
    }

} catch (PDOException $e) {
    die("Error fetching application details: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admission Letter for <?php echo htmlspecialchars($applicant['application_number']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 12pt;
            line-height: 1.6;
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            padding: 0.5in;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            background: white;
        }
        .letter-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .letter-header img {
            max-width: 110px;
            margin-bottom: 15px;
            filter: drop-shadow(0 2px 2px rgba(0,0,0,0.2));
        }
        .letter-header h1 {
            font-size: 16pt;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        .letter-header h2 {
            font-size: 14pt;
            margin: 5px 0;
        }
        .letter-body {
            margin-top: 20px;
        }
        .recipient-address, .date-line {
            margin-bottom: 20px;
        }
        .salutation {
            font-weight: bold;
            margin-bottom: 15px;
        }
        .letter-title {
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
            font-size: 14pt;
            margin-bottom: 20px;
        }
        .highlight {
            font-weight: bold;
        }
        .signature-block {
            margin-top: 50px;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 250px;
            margin-top: 40px;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: sans-serif;
        }
        @media print {
            body {
                margin: 0;
                padding: 0.5in;
                box-shadow: none;
            }
            .print-button {
                display: none;
            }
        }
    </style>
</head>
<body>

    <button onclick="window.print()" class="print-button">Print Letter</button>

    <div class="letter-header">
        <img src="../images/logo.jpeg" alt="JOSTUM Logo">
        <h1>JOSEPH SARWUAN TARKA UNIVERSITY, MAKURDI</h1>
        <h2>(Formerly University of Agriculture, Makurdi)</h2>
        <p>P.M.B. 2373, Makurdi, Benue State, Nigeria.</p>
        <h3>Office of the Registrar</h3>
    </div>

    <div class="letter-body">
        <div class="date-line">
            Date: <?php echo date('F j, Y'); ?>
        </div>
        <div class="recipient-address">
            <span class="highlight">Our Ref:</span> JOSTUM/ADM/<?php echo date('Y'); ?>/<?php echo htmlspecialchars($applicant['application_number']); ?>
        </div>

        <div class="recipient-address">
            <span class="highlight">To:</span><br>
            Name: <?php echo htmlspecialchars(strtoupper($applicant['surname'] . ' ' . $applicant['first_name'] . ' ' . $applicant['middle_name'])); ?><br>
            Application Number: <?php echo htmlspecialchars($applicant['application_number']); ?><br>
            Address: <?php echo htmlspecialchars($applicant['address']); ?><br>
            Email: <?php echo htmlspecialchars($applicant['email']); ?>
        </div>

        <div class="salutation">
            Dear <?php echo htmlspecialchars($applicant['title'] . ' ' . $applicant['surname']); ?>,
        </div>

        <h3 class="letter-title">OFFER OF PROVISIONAL ADMISSION</h3>

        <p>
            I am pleased to inform you that you have been offered Provisional Admission into the Joseph Sarwuan Tarka University, Makurdi (JOSTUM) for the <?php echo date('Y'); ?>/<?php echo date('Y') + 1; ?> Academic Session.
        </p>

        <p>
            You have been admitted to study:
        </p>

        <ul>
            <li><strong>Programme:</strong> <span class="highlight"><?php echo htmlspecialchars($applicant['degree_type']); ?> in <?php echo htmlspecialchars($applicant['course']); ?></span></li>
            <li><strong>Faculty/College:</strong> <span class="highlight"><?php echo htmlspecialchars($applicant['faculty']); ?></span></li>
            <li><strong>Mode of Study:</strong> <span class="highlight"><?php echo htmlspecialchars($applicant['mode_of_study'] ?? 'Postgraduate'); ?></span></li>
            <li><strong>Duration:</strong> As approved by Senate</li>
        </ul>

        <p>
            This offer of admission is made subject to your meeting all the University and departmental requirements.
        </p>

        <h4>CONDITIONS FOR ACCEPTANCE</h4>
        <p>You are required to:</p>
        <ul>
            <li>Accept this offer on or before ________________.</li>
            <li>Proceed to the Admissions Office for documentation and screening.</li>
            <li>Present originals and photocopies of the following:</li>
        </ul>
        <ul>
            <li>JAMB / Application Slip</li>
            <li>O’Level Result(s)</li>
            <li>Birth Certificate / Declaration of Age</li>
            <li>Local Government Identification</li>
            <li>Passport Photographs (4 copies)</li>
        </ul>

        <h4>REGISTRATION AND FEES</h4>
        <p>Upon acceptance, you are to:</p>
        <ul>
            <li>Pay all prescribed fees through the official JOSTUM payment portal.</li>
            <li>Complete your online and physical registration within the stipulated period.</li>
        </ul>
        <p>Failure to register within the approved time may lead to forfeiture of this offer.</p>

        <h4>NOTE</h4>
        <p>
            This admission is provisional and may be withdrawn if it is discovered at any time that you do not possess the qualifications claimed or if you fail to meet any of the University’s regulations.
        </p>

        <p>
            Once again, congratulations on your admission into Joseph Sarwuan Tarka University, Makurdi. We look forward to welcoming you to the JOSTUM community.
        </p>

        <div class="signature-block">
            <p>Yours faithfully,</p>
            <br><br><br>
            <div class="signature-line"></div>
            <strong>Registrar</strong><br>
            Joseph Sarwuan Tarka University, Makurdi
        </div>
    </div>

</body>
</html>
