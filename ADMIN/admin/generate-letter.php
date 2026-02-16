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
        SELECT a.application_number, a.submitted_at, p.title, p.surname, p.first_name, p.middle_name, p.address, pc.course, pc.degree_type, pc.faculty, pc.department, u.email
        FROM applications a
        JOIN users u ON a.user_id = u.user_id
        LEFT JOIN personal_details p ON a.application_id = p.application_id
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
        WHERE a.application_number = ? AND a.status = 'Admitted'
    ");
    $stmt->execute([$appNumber]);
    $applicant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$applicant) {
        die("<h1>Admission Letter Not Available</h1><p>This admission letter cannot be generated. The application was not found or has not been approved.</p><a href='admission-decisions.php'>Go back</a>");
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
    <link href="https://fonts.googleapis.com/css2?family=Times+New+Roman&family=Garamond&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Garamond', 'Times New Roman', serif;
            font-size: 12pt;
            line-height: 1.6;
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            padding: 15mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            background: white;
        }
        .letter-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .letter-header img {
            max-width: 100px;
            margin-bottom: 15px;
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
                padding: 10mm;
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
        <h2>(Office of the Registrar)</h2>
        <h3>SCHOOL OF POSTGRADUATE STUDIES</h3>
        <p>PMB 2373, MAKURDI, BENUE STATE, NIGERIA</p>
    </div>

    <div class="letter-body">
        <div class="date-line">
            <?php echo date('F j, Y'); ?>
        </div>

        <div class="recipient-address">
            <span class="highlight"><?php echo htmlspecialchars(strtoupper($applicant['surname'] . ' ' . $applicant['first_name'] . ' ' . $applicant['middle_name'])); ?></span><br>
            <?php echo htmlspecialchars($applicant['address']); ?><br>
            Email: <?php echo htmlspecialchars($applicant['email']); ?>
        </div>

        <div class="salutation">
            Dear <?php echo htmlspecialchars($applicant['title'] . ' ' . $applicant['surname']); ?>,
        </div>

        <h3 class="letter-title">PROVISIONAL OFFER OF ADMISSION</h3>

        <p>
            With reference to your application for admission into the postgraduate programme of Joseph Sarwuan Tarka University, Makurdi, I am pleased to inform you that the Postgraduate School Board has approved your admission for the <span class="highlight"><?php echo date('Y'); ?>/<?php echo date('Y') + 1; ?></span> academic session.
        </p>

        <p>
            The details of the admission are as follows:
        </p>

        <ul>
            <li><strong>Applicant Number:</strong> <span class="highlight"><?php echo htmlspecialchars($applicant['application_number']); ?></span></li>
            <li><strong>Faculty/College:</strong> <span class="highlight"><?php echo htmlspecialchars($applicant['faculty']); ?></span></li>
            <li><strong>Department:</strong> <span class="highlight"><?php echo htmlspecialchars($applicant['department']); ?></span></li>
            <li><strong>Programme of Study:</strong> <span class="highlight"><?php echo htmlspecialchars($applicant['degree_type']); ?> in <?php echo htmlspecialchars($applicant['course']); ?></span></li>
            <li><strong>Duration:</strong> To be specified based on programme.</li>
        </ul>

        <p>
            This offer is provisional and subject to your meeting the minimum entry requirements for the programme to which you have been admitted. You are required to present the originals of your credentials for verification at the point of registration.
        </p>

        <p>
            Please visit the university website for details on acceptance fees and registration procedures.
        </p>
        
        <p>
            Congratulations.
        </p>

        <div class="signature-block">
            <p>Yours faithfully,</p>
            <br><br><br>
            <div class="signature-line"></div>
            <strong>Registrar</strong>
        </div>
    </div>

</body>
</html>
