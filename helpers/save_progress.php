<?php
if (PHP_VERSION_ID >= 80200 && file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists('Dotenv\\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
    }
}
session_start();
$step_titles = [
    1 => 'Personal Information',
    2 => 'Programme Selection',
    3 => 'Academic History',
    4 => 'NYSC Details',
    5 => 'Work Experience',
    6 => 'Research Details',
    7 => 'Referee Details',
    8 => 'Document Upload',
    9 => 'Application Submission'
];
require '../config/db.php'; 
require_once __DIR__ . '/../config/urls.php';

if (!isset($_SESSION['user_id'])) {
    redirect_to('APPLICANT/ADMISSIONS/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$step_id = isset($_POST['step_id']) ? (int)$_POST['step_id'] : 1;
if ($step_id == 9) {
 
    if (!isset($_POST['captcha_verified']) || $_POST['captcha_verified'] !== '1') {
        $_SESSION['msg'] = ['type' => 'danger', 'text' => 'Security Error: Please complete the captcha.'];
        redirect_to('dashboard.php?step=9');
        exit();
    }
}
$stmt = $pdo->prepare("SELECT application_id FROM applications WHERE user_id = ? AND status = 'Draft'");
$stmt->execute([$user_id]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    $stmt = $pdo->prepare("INSERT INTO applications (user_id, current_step) VALUES (?, ?)");
    $stmt->execute([$user_id, $step_id]);
    $application_id = $pdo->lastInsertId();
} else {
    $application_id = $app['application_id'];
    $stmt = $pdo->prepare("UPDATE applications SET current_step = GREATEST(current_step, ?) WHERE application_id = ?");
    $stmt->execute([$step_id + 1, $application_id]);
}

$_SESSION['form_data']["step_$step_id"] = $_POST;

try {
    $pdo->beginTransaction();

    switch ($step_id) {
        case 1: 
            $sql = "INSERT INTO personal_details (application_id, surname, first_name, other_name, dob, sex, nationality, state_origin, lga, phone, address)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    surname=VALUES(surname), first_name=VALUES(first_name), other_name=VALUES(other_name), 
                    dob=VALUES(dob), sex=VALUES(sex), nationality=VALUES(nationality), 
                    state_origin=VALUES(state_origin), lga=VALUES(lga), phone=VALUES(phone), address=VALUES(address)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $application_id, strtoupper($_POST['surname']), strtoupper($_POST['firstName']), strtoupper($_POST['otherName']), 
                $_POST['dob'], $_POST['sex'], $_POST['nationality'], $_POST['state'], 
                $_POST['lga'], $_POST['phone'], ucwords(strtolower($_POST['address']))
            ]);
            $_SESSION['user_email'] = $_POST['email']; 

            
            break;

        case 2: 
            $raw_faculty = $_POST['faculty'] ?? '';
            $raw_dept = $_POST['department'] ?? '';
            $raw_degree = $_POST['degree_type'] ?? '';
            $raw_course = $_POST['course'] ?? '';
            $raw_mode = $_POST['mode'] ?? '';

            // 1. Resolve Department
            $dept_id = null;
            if ($raw_dept !== '') {
                if (is_numeric($raw_dept)) {
                    $dept_id = (int)$raw_dept;
                } else {
                    $dept_name = $raw_dept;
                    if ($raw_dept === 'Department of Procurement Standards') {
                        $dept_name = 'Procurement';
                    } elseif ($raw_dept === 'Department of Environmental Standards') {
                        $dept_name = 'Environmental Standard';
                    } elseif ($raw_dept === 'Department of Social Standards') {
                        $dept_name = 'Social Standard';
                    }
                    $stmt = $pdo->prepare("SELECT dept_id FROM departments WHERE dept_name = ? LIMIT 1");
                    $stmt->execute([$dept_name]);
                    $dept_id = (int)$stmt->fetchColumn() ?: null;
                }
            }

            // 2. Resolve Faculty
            $faculty_id = null;
            if ($raw_faculty !== '') {
                if (is_numeric($raw_faculty)) {
                    $faculty_id = (int)$raw_faculty;
                } else {
                    if ($dept_id) {
                        $stmt = $pdo->prepare("SELECT faculty_id FROM departments WHERE dept_id = ? LIMIT 1");
                        $stmt->execute([$dept_id]);
                        $faculty_id = (int)$stmt->fetchColumn() ?: null;
                    }
                }
            }

            // 3. Resolve Degree Type
            $degree_id = null;
            if ($raw_degree !== '') {
                if (is_numeric($raw_degree)) {
                    $degree_id = (int)$raw_degree;
                } else {
                    $stmt = $pdo->prepare("SELECT degree_id FROM degree_types WHERE degree_name = ? LIMIT 1");
                    $stmt->execute([$raw_degree]);
                    $degree_id = (int)$stmt->fetchColumn() ?: null;
                }
            }

            // 4. Resolve Course
            $course_id = null;
            if ($raw_course !== '') {
                if (is_numeric($raw_course)) {
                    $course_id = (int)$raw_course;
                } else {
                    $course_name = $raw_course;
                    if (strcasecmp($raw_course, 'PROCUREMENT MANAGEMENT') === 0) {
                        $course_name = 'Procurement';
                    } elseif (strcasecmp($raw_course, 'ENVIRONMENTAL SUSTAINABILITY') === 0) {
                        $course_name = 'Environmental Sustainability';
                    } elseif (strcasecmp($raw_course, 'SUSTAINABLE SOCIAL DEVELOPMENT') === 0) {
                        $course_name = 'Social Standard';
                    }
                    $stmt = $pdo->prepare("SELECT course_id FROM courses WHERE course_title = ? LIMIT 1");
                    $stmt->execute([$course_name]);
                    $course_id = (int)$stmt->fetchColumn() ?: null;
                }
            }

            // 5. Resolve Mode of Study
            $mode_id = null;
            if ($raw_mode !== '') {
                if (is_numeric($raw_mode)) {
                    $mode_id = (int)$raw_mode;
                } else {
                    $stmt = $pdo->prepare("SELECT mode_id FROM study_modes WHERE mode_name = ? LIMIT 1");
                    $stmt->execute([$raw_mode]);
                    $mode_id = (int)$stmt->fetchColumn() ?: null;
                }
            }

            $sql = "INSERT INTO programme_choices (application_id, faculty, department, degree_type, mode_of_study, course)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    faculty=VALUES(faculty), 
                    department=VALUES(department), 
                    degree_type=VALUES(degree_type), 
                    mode_of_study=VALUES(mode_of_study),
                    course=VALUES(course)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $application_id, $faculty_id, $dept_id, 
                $degree_id, $mode_id, $course_id
            ]);
            break;

        case 3: 
            $sql = "INSERT INTO higher_education (application_id, highest_qualification, course_study, institution, grad_year, cgpa, mode_study)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    highest_qualification=VALUES(highest_qualification), course_study=VALUES(course_study), 
                    institution=VALUES(institution), grad_year=VALUES(grad_year), cgpa=VALUES(cgpa), mode_study=VALUES(mode_study)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $application_id, $_POST['highest_qualification'], $_POST['course_study'], 
                $_POST['institution'], $_POST['grad_year'], $_POST['cgpa'], $_POST['mode_study']
            ]);

            function saveOLevelData($pdo, $application_id, $sitting_number, $prefix) {
                if (empty($_POST[$prefix . '_school'])) return;

                $examType = $_POST[$prefix . '_type'];
                if ($examType === 'Others' && !empty($_POST[$prefix . '_type_other'])) {
                    $examType = $_POST[$prefix . '_type_other'];
                }

                $examSql = "INSERT INTO olevel_exams (application_id, sitting_number, exam_type, school_name, exam_year, exam_number)
                            VALUES (?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE 
                            exam_type=VALUES(exam_type), school_name=VALUES(school_name), 
                            exam_year=VALUES(exam_year), exam_number=VALUES(exam_number)";
                
                $examStmt = $pdo->prepare($examSql);
                $examStmt->execute([
                    $application_id, 
                    $sitting_number, 
                    $examType, 
                    $_POST[$prefix . '_school'], 
                    $_POST[$prefix . '_year'], 
                    $_POST[$prefix . '_exam_number'] ?? 'N/A' 
                ]);

                $stmtId = $pdo->prepare("SELECT id FROM olevel_exams WHERE application_id = ? AND sitting_number = ?");
                $stmtId->execute([$application_id, $sitting_number]);
                $exam_id = $stmtId->fetchColumn();

                $pdo->prepare("DELETE FROM olevel_results WHERE exam_id = ?")->execute([$exam_id]);

                $subjects = $_POST[$prefix . '_subjects'] ?? [];
                $subjectOthers = $_POST[$prefix . '_subject_others'] ?? [];
                $grades = $_POST[$prefix . '_grades'] ?? [];
                
                $resStmt = $pdo->prepare("INSERT INTO olevel_results (exam_id, subject_name, grade) VALUES (?, ?, ?)");
                
                for ($i = 0; $i < count($subjects); $i++) {
                    $subjectName = trim($subjects[$i] ?? '');
                    if ($subjectName === 'Others') {
                        $subjectName = trim($subjectOthers[$i] ?? '');
                    }

                    if (!empty($subjectName) && !empty($grades[$i])) {
                        $resStmt->execute([$exam_id, $subjectName, $grades[$i]]);
                    }
                }
            }

            // Process Sittings
            saveOLevelData($pdo, $application_id, 1, 'ssce1');
            if (!empty($_POST['ssce2_school'])) {
                saveOLevelData($pdo, $application_id, 2, 'ssce2');
            }
            break;

        case 4: 
            $sql = "INSERT INTO nysc_details (application_id, nysc_status, certificate_number, completion_year)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE nysc_status=VALUES(nysc_status), certificate_number=VALUES(certificate_number), completion_year=VALUES(completion_year)";
            $stmt = $pdo->prepare($sql);
            $nysc_number = !empty($_POST['nysc_number']) ? $_POST['nysc_number'] : null;
            $nysc_year = !empty($_POST['nysc_year']) ? (int)$_POST['nysc_year'] : null;
            $stmt->execute([$application_id, $_POST['nysc_status'], $nysc_number, $nysc_year]);
            break;

        case 5: 
             $employer = !empty($_POST['employer']) ? $_POST['employer'] : null;
            $job_title = !empty($_POST['job_title']) ? $_POST['job_title'] : null;
            $years_exp = ($_POST['years_experience'] !== '') ? (int)$_POST['years_experience'] : null;

            $sql = "INSERT INTO work_experience (application_id, employment_status, employer, job_title, years_experience)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    employment_status=VALUES(employment_status), 
                    employer=VALUES(employer), 
                    job_title=VALUES(job_title), 
                    years_experience=VALUES(years_experience)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $application_id, 
                $_POST['emp_status'], 
                $employer, 
                $job_title, 
                $years_exp
            ]);
            break;

        case 6: 
            $sql = "INSERT INTO research_details (application_id, research_area, reason_for_choosing, statement_of_purpose, career_objectives)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE research_area=VALUES(research_area), reason_for_choosing=VALUES(reason_for_choosing), statement_of_purpose=VALUES(statement_of_purpose), career_objectives=VALUES(career_objectives)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$application_id, $_POST['proposed_research_area'], $_POST['reason_for_choosing_programme'], $_POST['statement_of_purpose'], $_POST['career_objectives']]);
            break;

        case 7:
            $pdo->prepare("DELETE FROM referees WHERE application_id = ?")->execute([$application_id]);
            $names = $_POST['ref_name'];
            $stmt = $pdo->prepare("INSERT INTO referees (application_id, full_name, title, organization, email, phone) VALUES (?, ?, ?, ?, ?, ?)");
            for ($i = 0; $i < count($names); $i++) {
                if (!empty($names[$i])) {
                    $stmt->execute([$application_id, $names[$i], $_POST['ref_title'][$i], $_POST['ref_org'][$i], $_POST['ref_email'][$i], $_POST['ref_phone'][$i]]);
                }
            }
            break;
        case 8: 
        $allowed_mime_types = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'application/pdf' => 'pdf'
        ];
        $max_file_size = 500 * 1024; 
        
        $parent_dir = dirname(__DIR__); 
        $upload_base_dir = $parent_dir . '/uploads/';

        $upload_map = [
            'passport_file'   => 'passports',
            'passport_profile_file' => 'passports',
            'olevel_file'     => 'olevel',
            'olevel_file_2'   => 'olevel',
            'degree_file'     => 'degree',
            'transcript_file' => 'transcripts',
            'nysc_file'       => 'nysc',
            'proposal_file'   => 'proposals'
        ];

        $db_doc_types = [
            'passport_file'   => 'passport',
            'passport_profile_file' => 'passport_profile',
            'olevel_file'     => 'olevel_1',
            'olevel_file_2'   => 'olevel_2',
            'degree_file'     => 'degree',
            'transcript_file' => 'transcript',
            'nysc_file'       => 'nysc',
            'proposal_file'   => 'proposal'
        ];

        $getOldStmt = $pdo->prepare("SELECT file_path FROM documents WHERE application_id = ? AND document_type = ?");
        $delStmt    = $pdo->prepare("DELETE FROM documents WHERE application_id = ? AND document_type = ?");
        $insStmt    = $pdo->prepare("INSERT INTO documents (application_id, document_type, file_path) VALUES (?, ?, ?)");

        if (!isset($_SESSION['form_data']['step_8'])) {
            $_SESSION['form_data']['step_8'] = [];
        }

        foreach ($upload_map as $field_name => $folder) {
            if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] === UPLOAD_ERR_OK) {
                
                $file = $_FILES[$field_name];
                
                if ($file['size'] > $max_file_size) {
                    throw new Exception("File for $field_name exceeds 500KB limit.");
                }

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime_type = $finfo->file($file['tmp_name']);

                if (!array_key_exists($mime_type, $allowed_mime_types)) {
                    throw new Exception("Invalid file format for $field_name. Allowed: JPG, PNG, PDF.");
                }

                $docType = $db_doc_types[$field_name];

                $getOldStmt->execute([$application_id, $docType]);
                $existingFile = $getOldStmt->fetch(PDO::FETCH_ASSOC);

                $target_dir = $upload_base_dir . $folder . '/';
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }

                $extension = $allowed_mime_types[$mime_type];

               
                $filename = $docType . '_' . $application_id . '_' . time() . '.' . $extension;
                
                $target_path = $target_dir . $filename;
                $db_relative_path = 'uploads/' . $folder . '/' . $filename;

                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    
                    if ($existingFile && !empty($existingFile['file_path'])) {
                        $physical_old_path = $parent_dir . '/' . $existingFile['file_path'];
                        if (file_exists($physical_old_path)) {
                            unlink($physical_old_path);
                        }
                    }

                    $delStmt->execute([$application_id, $docType]);
                    $insStmt->execute([$application_id, $docType, $db_relative_path]);

                    $_SESSION['form_data']['step_8'][$field_name] = $db_relative_path;
                } else {
                    throw new Exception("Failed to save file for $field_name.");
                }
            }
        }
        break;

       
            case 9: 
    if (isset($_POST['declaration'])) {
       
        $appYear = date('Y');
        $formattedSerial = str_pad($application_id, 4, '0', STR_PAD_LEFT);
        $appNumber = "APP/IPESS/{$appYear}/{$formattedSerial}";
        $stmtApp = $pdo->prepare("
                UPDATE applications 
                SET application_number = ?, 
                    submitted_at = NOW(),
                    current_step = 10 
                WHERE application_id = ?
            ");
            $stmtApp->execute([$appNumber, $application_id]);

            require_once __DIR__ . '/../classes/ApplicationProgressManager.php';
            $progManager = new ApplicationProgressManager($pdo);
            $progManager->initializeApplication($application_id);
            
            $stmtUser = $pdo->prepare("SELECT surname, first_name, other_name FROM personal_details WHERE application_id = ?");
            $stmtUser->execute([$application_id]);
            $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
            $fullName = strtoupper($userData['surname'] . ' ' . $userData['first_name'] . ' ' . $userData['other_name']);
            
            $protocol = function_exists('is_secure_connection') ? (is_secure_connection() ? "https" : "http") : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
            $domain   = $_SERVER['HTTP_HOST'];
            $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            $loginUrl = app_url('APPLICANT/ADMISSIONS/login.php');
            
                $baseDir = __DIR__ . '/../PhpMailer/src/'; 
                require_once $baseDir . 'Exception.php';
                require_once $baseDir . 'PHPMailer.php';
                require_once $baseDir . 'SMTP.php';

                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->SMTPAuth   = true;
               $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; 
                $mail->Host       = $_ENV['SMTP_HOST'];
                $mail->Username   = $_ENV['SMTP_USER'];
                $mail->Password   = $_ENV['SMTP_PASS'];
                $mail->Port       = $_ENV['SMTP_PORT'];
                $mail->setFrom($_ENV['SMTP_USER'], $_ENV['SMTP_FROM_NAME']);
                $mail->addAddress($_SESSION['user_email']);
                $mail->isHTML(true);
                $mail->Subject = "Application Received - ID: $appNumber";
                $mail->addEmbeddedImage(__DIR__ . '/../images/jostum.jpeg', 'jostum_logo'); 

                $mail->Body = "<div style='background-color: #1a120b; margin: 0; padding: 20px 0; width: 100%; font-family: Arial, sans-serif;'>
                    <table width='100%' border='0' cellspacing='0' cellpadding='0' style='background-color: #1a120b;'>
                        <tr>
                            <td align='center'>
                                <div style='max-width: 500px; width: 95%; margin: 0 auto; border: 1px solid #333; padding: 30px; border-radius: 8px; background-color: #1a120b; color: #ffffff; text-align: left;'>
                                    
                                    <div style='text-align: center; margin-bottom: 25px;'>
                                        <img src='cid:jostum_logo' alt='JOSTUM Logo' style='width: 120px; height: auto; display: block; margin: 0 auto;'>
                                    </div>

                                    <h2 style='color: #448aff; text-align: center; font-size: 24px; margin-top: 0; margin-bottom: 25px;'>Application Confirmation</h2>
                                    
                                    <p style='font-size: 15px; margin-bottom: 10px;'>Dear, $fullName,</p>
                                    <p style='font-size: 15px; margin-bottom: 30px; line-height: 1.5;'>Your postgraduate application has been received successfully.</p>

                                    <div style='background-color: #1d2533; border: 1px dashed #448aff; padding: 25px; text-align: center; margin-bottom: 30px;'>
                                        <p style='color: #aaaaaa; font-size: 12px; letter-spacing: 1.5px; margin: 0 0 10px 0; text-transform: uppercase;'>Your Application ID</p>
                                        <h1 style='color: #448aff; margin: 0; font-size: 32px; font-family: monospace;'>$appNumber</h1>
                                    </div>

                                    <p style='font-size: 14px; color: #bbbbbb; margin-bottom: 35px; text-align: center;'>
                                        Please keep this ID safe. You will need it to login to the portal and track your admission status.
                                    </p>

                                    <div style='text-align: center;'>
                                        <a href='{$loginUrl}' style='background-color: #007bff; color: #ffffff; padding: 15px 25px; text-decoration: none; border-radius: 5px; font-size: 15px; font-weight: bold; display: inline-block;'>
                                            Login to Track Admission Status 
                                        </a>
                                    </div>

                                    <div style='margin-top: 40px; border-top: 1px solid #333; padding-top: 20px; text-align: center;'>
                                        <p style='font-size: 12px; color: #ffffff; margin: 0 0 5px 0;'><strong>Joseph Sarwuan Tarka University, Makurdi</strong></p>
                                        <p style='font-size: 11px; color: #777777; margin: 0; line-height: 1.4;'>
                                            This is an automated acknowledgment. Please do not reply to this email.<br>
                                            For support, visit our <a href='#' style='color: #448aff; text-decoration: none;'>Help Desk</a> or contact us at support@jostum.edu.ng
                                        </p>
                                    </div>

                                </div>
                            </td>
                        </tr>
                    </table>
                </div>";

                $mail->send();

                 $pdo->commit(); 
                 unset($_SESSION['form_data']);
                 header("Location: success.php?app_no=" . urlencode(encrypt_app_number($appNumber)));
                 exit();
            }
            break;
    }

    $pdo->commit();
    
   $current_title = $step_titles[$step_id] ?? "Progress";

    $_SESSION['msg'] = [
        'type' => 'success', 
        'text' => '<strong>' . $current_title . '</strong> has been saved successfully!'
    ];

    if ($step_id < 9) {
        redirect_to('dashboard.php?step=' . ($step_id + 1));
    } else {
        redirect_to('dashboard.php?step=9');
    }
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['msg'] = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
    redirect_to('dashboard.php?step=' . $step_id);
}
?>
