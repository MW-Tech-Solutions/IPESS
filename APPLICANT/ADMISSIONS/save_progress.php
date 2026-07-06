<?php
require_once __DIR__ . '/../../app/bootstrap.php';


require_once __DIR__ . '/../../includes/completion_service.php';
require_once __DIR__ . '/../../includes/status_engine.php';
require_once __DIR__ . '/../../includes/permissions.php';

if (!isset($_SESSION['user_id'])) {
    redirect_to('APPLICANT/ADMISSIONS/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$step_id = isset($_POST['step_id']) ? (int)$_POST['step_id'] : 1;
if ($step_id == 9) {
 
    if (!isset($_POST['captcha_verified']) || $_POST['captcha_verified'] !== '1') {
        $_SESSION['msg'] = ['type' => 'danger', 'text' => 'Security Error: Please complete the captcha.'];
        redirect_to('APPLICANT/ADMISSIONS/dashboard.php?step=9');
        exit();
    }
}
$stmt = $pdo->prepare("SELECT application_id, current_status FROM applications WHERE user_id = ? ORDER BY application_id DESC LIMIT 1");
$stmt->execute([$user_id]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    $stmt = $pdo->prepare("INSERT INTO applications (user_id, current_step, status, current_status) VALUES (?, ?, 'Draft', 'DRAFT')");
    $stmt->execute([$user_id, $step_id]);
    $application_id = $pdo->lastInsertId();
} else {
    $application_id = $app['application_id'];
    $current_status = $app['current_status'] ?? 'DRAFT';
    if (!can_edit_application($current_status) && $step_id < 10) {
        $_SESSION['msg'] = ['type' => 'danger', 'text' => 'Your application is locked for editing.'];
        redirect_to('APPLICANT/ADMISSIONS/dashboard.php?step=10');
        exit();
    }
    // Update progress tracker
    $stmt = $pdo->prepare("UPDATE applications SET current_step = GREATEST(current_step, ?) WHERE application_id = ?");
    $stmt->execute([$step_id + 1, $application_id]);
}

// Store current step data in session for persistence
$_SESSION['form_data']["step_$step_id"] = $_POST;

try {
    // START GLOBAL TRANSACTION
    $pdo->beginTransaction();

    switch ($step_id) {
        case 1: // Personal Info
            $sql = "INSERT INTO personal_details (application_id, surname, first_name, other_name, dob, sex, nationality, state_origin, lga, phone, address)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    surname=VALUES(surname), first_name=VALUES(first_name), other_name=VALUES(other_name), 
                    dob=VALUES(dob), sex=VALUES(sex), nationality=VALUES(nationality), 
                    state_origin=VALUES(state_origin), lga=VALUES(lga), phone=VALUES(phone), address=VALUES(address)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $application_id, $_POST['surname'], $_POST['firstName'], $_POST['otherName'], 
                $_POST['dob'], $_POST['sex'], $_POST['nationality'], $_POST['state'], 
                $_POST['lga'], $_POST['phone'], $_POST['address']
            ]);
            $_SESSION['user_email'] = $_POST['email']; 
            break;

        case 2: // Programme Info
            $faculty_id = 0;
            if (!empty($_POST['faculty'])) {
                $stmt = $pdo->prepare("SELECT faculty_id FROM faculties WHERE faculty_name = ? LIMIT 1");
                $stmt->execute([$_POST['faculty']]);
                $faculty_id = (int)$stmt->fetchColumn() ?: 0;
            }

            $dept_id = 0;
            if (!empty($_POST['department'])) {
                $stmt = $pdo->prepare("SELECT dept_id FROM departments WHERE dept_name = ? LIMIT 1");
                $stmt->execute([$_POST['department']]);
                $dept_id = (int)$stmt->fetchColumn() ?: 0;
            }

            $degree_id = 0;
            if (!empty($_POST['degree_type'])) {
                $stmt = $pdo->prepare("SELECT degree_id FROM degree_types WHERE degree_name = ? OR (degree_name = 'Msc' AND ? = 'MSc') LIMIT 1");
                $stmt->execute([$_POST['degree_type'], $_POST['degree_type']]);
                $degree_id = (int)$stmt->fetchColumn() ?: 0;
            }

            $course_id = 0;
            if (!empty($_POST['course'])) {
                $stmt = $pdo->prepare("SELECT course_id FROM courses WHERE course_title = ? OR course_title LIKE ? LIMIT 1");
                $stmt->execute([$_POST['course'], '%' . $_POST['course']]);
                $course_id = (int)$stmt->fetchColumn() ?: 0;
            }

            $mode_id = 0;
            if (!empty($_POST['mode'])) {
                $stmt = $pdo->prepare("SELECT mode_id FROM study_modes WHERE mode_name = ? LIMIT 1");
                $stmt->execute([$_POST['mode']]);
                $mode_id = (int)$stmt->fetchColumn() ?: 0;
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
            $pdo->prepare("UPDATE applications SET department_id = ? WHERE application_id = ?")->execute([$dept_id, $application_id]);
            break;
            case 3: // Academic History & O-Levels
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
                $grades = $_POST[$prefix . '_grades'] ?? [];
                
                $resStmt = $pdo->prepare("INSERT INTO olevel_results (exam_id, subject_name, grade) VALUES (?, ?, ?)");
                
                for ($i = 0; $i < count($subjects); $i++) {
                    if (!empty($subjects[$i]) && !empty($grades[$i])) {
                        $resStmt->execute([$exam_id, $subjects[$i], $grades[$i]]);
                    }
                }
            }

            // Process Sittings
            saveOLevelData($pdo, $application_id, 1, 'ssce1');
            if (!empty($_POST['ssce2_school'])) {
                saveOLevelData($pdo, $application_id, 2, 'ssce2');
            }
            break;

        

        case 4: // NYSC
            $sql = "INSERT INTO nysc_details (application_id, nysc_status, certificate_number, completion_year)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE nysc_status=VALUES(nysc_status), certificate_number=VALUES(certificate_number), completion_year=VALUES(completion_year)";
            $stmt = $pdo->prepare($sql);
            $nysc_number = !empty($_POST['nysc_number']) ? $_POST['nysc_number'] : null;
            $nysc_year = !empty($_POST['nysc_year']) ? (int)$_POST['nysc_year'] : null;
            $stmt->execute([$application_id, $_POST['nysc_status'], $nysc_number, $nysc_year]);
            break;

    
        case 5: // Work Experience
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

        case 6: // Research
            $sql = "INSERT INTO research_details (application_id, research_area, reason_for_choosing, statement_of_purpose, career_objectives)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE research_area=VALUES(research_area), reason_for_choosing=VALUES(reason_for_choosing), statement_of_purpose=VALUES(statement_of_purpose), career_objectives=VALUES(career_objectives)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$application_id, $_POST['proposed_research_area'], $_POST['reason_for_choosing_programme'], $_POST['statement_of_purpose'], $_POST['career_objectives']]);
            break;

        case 7: // Referees
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
                'image/jpg'  => 'jpg',
                'image/png'  => 'png',
                'application/pdf' => 'pdf'
            ];
            $max_file_size = 2 * 1024 * 1024; 
            $upload_base_dir = __DIR__ . '/../../uploads/';


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

            $stmt = $pdo->prepare("INSERT INTO documents (application_id, document_type, file_path) VALUES (?, ?, ?)");
            $delStmt = $pdo->prepare("DELETE FROM documents WHERE application_id = ? AND document_type = ?");

            if (!isset($_SESSION['form_data']['step_8'])) {
                $_SESSION['form_data']['step_8'] = [];
            }

            foreach ($upload_map as $field_name => $folder) {
                if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] === UPLOAD_ERR_OK) {
                    
                    $file = $_FILES[$field_name];
                    
                    if ($file['size'] > $max_file_size) {
                        throw new Exception("File for $field_name exceeds 2MB limit.");
                    }

                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime_type = $finfo->file($file['tmp_name']);

                    if (!array_key_exists($mime_type, $allowed_mime_types)) {
                        throw new Exception("Invalid file format for $field_name. Allowed: JPG, PNG, PDF.");
                    }

                    $target_dir = $upload_base_dir . $folder . '/';
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0755, true);
                    }

                    $extension = $allowed_mime_types[$mime_type];
                    $filename = sprintf('%s_%s.%s', time(), bin2hex(random_bytes(8)), $extension);
                    $target_path = $target_dir . $filename;
                    $db_relative_path = 'uploads/' . $folder . '/' . $filename;

                    if (move_uploaded_file($file['tmp_name'], $target_path)) {
                        $docType = $db_doc_types[$field_name];
                        $delStmt->execute([$application_id, $docType]);
                        $stmt->execute([$application_id, $docType, $db_relative_path]);

                        $_SESSION['form_data']['step_8'][$field_name] = $db_relative_path;
                    } else {
                        throw new Exception("Failed to save file for $field_name.");
                    }
                }
            }
            break;

        case 9: 
            if (isset($_POST['declaration'])) {
                if (!can_submit_application($pdo, $application_id)) {
                    $_SESSION['msg'] = ['type' => 'danger', 'text' => 'Application completion must be at least ' . COMPLETION_SUBMIT_THRESHOLD . '% before submission.'];
                    redirect_to('APPLICANT/ADMISSIONS/dashboard.php?step=9');
                    exit();
                }
                $year = date('Y');
                $prefix = "APP/IPESS/{$year}/";
                $stmt_serial = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(application_number, '/', -1) AS UNSIGNED)) FROM applications WHERE application_number LIKE ?");
                $stmt_serial->execute([$prefix . '%']);
                $maxSerial = (int)$stmt_serial->fetchColumn();
                $nextSerial = $maxSerial + 1;
                $appNumber = $prefix . str_pad((string)$nextSerial, 4, '0', STR_PAD_LEFT);
                
                $stmt = $pdo->prepare("UPDATE applications SET status = 'Submitted', current_status = 'SUBMITTED', application_number = ?, submitted_at = NOW() WHERE application_id = ?");
                $stmt->execute([$appNumber, $application_id]);

                update_application_status($pdo, $application_id, 'SUBMITTED', [
                    'actor_id' => $user_id,
                    'actor_role' => 'STUDENT',
                    'note' => 'Application submitted'
                ]);

                $deptStmt = $pdo->prepare("SELECT department_id FROM applications WHERE application_id = ?");
                $deptStmt->execute([$application_id]);
                $deptId = $deptStmt->fetchColumn();
                if ($deptId) {
                    update_application_status($pdo, $application_id, 'ASSIGNED_TO_DEPARTMENT', [
                        'actor_id' => $user_id,
                        'actor_role' => 'SYSTEM',
                        'note' => 'Auto-assigned to department'
                    ]);
                }

                $nameStmt = $pdo->prepare("SELECT first_name, surname FROM personal_details WHERE application_id = ?");
                $nameStmt->execute([$application_id]);
                $nameRow = $nameStmt->fetch(PDO::FETCH_ASSOC);
                $fullName = $nameRow ? ($nameRow['first_name'] . ' ' . $nameRow['surname']) : 'Applicant';

                $mailBody = "
<div style='background-color: #1a120b; margin: 0; padding: 20px 0; width: 100%; font-family: Arial, sans-serif;'>
    <table width='100%' border='0' cellspacing='0' cellpadding='0' style='background-color: #1a120b;'>
        <tr>
            <td align='center'>
                <div style='max-width: 500px; width: 90%; margin: 0 auto; border: 1px solid #333; padding: 30px; border-radius: 8px; background-color: #1a120b; color: #ffffff; text-align: left;'>
                    
                    <h2 style='color: #448aff; text-align: center; font-size: 24px; margin-top: 0; margin-bottom: 25px;'>Application Confirmation</h2>
                    
                    <p style='font-size: 15px; margin-bottom: 10px;'>Dear $fullName,</p>
                    <p style='font-size: 15px; margin-bottom: 30px; line-height: 1.5;'>Your postgraduate application has been received successfully.</p>

                    <div style='background-color: #1d2533; border: 1px dashed #448aff; padding: 25px; text-align: center; margin-bottom: 30px;'>
                        <p style='color: #aaaaaa; font-size: 12px; letter-spacing: 1.5px; margin: 0 0 10px 0; text-transform: uppercase;'>Your Application ID</p>
                        <h1 style='color: #448aff; margin: 0; font-size: 32px; font-family: monospace; word-break: break-all;'>$appNumber</h1>
                    </div>

                    <p style='font-size: 14px; color: #bbbbbb; margin-bottom: 35px; text-align: center;'>
                        Please keep this ID safe. You will need it to login to the portal and track your admission status.
                    </p>

                    <div style='text-align: center;'>
                        <a href='" . app_url('APPLICANT/ADMISSIONS/login.php') . "' style='background-color: #007bff; color: #ffffff; padding: 15px 25px; text-decoration: none; border-radius: 5px; font-size: 15px; font-weight: bold; display: inline-block;'>
                            Login to Track Admission Status 
                        </a>
                        </div>

                    <p style='font-size: 11px; color: #777777; margin-top: 40px; border-top: 1px solid #333; padding-top: 20px; text-align: center;'>
                        This is an automated acknowledgment. For further inquiries, please contact the support desk.
                    </p>
                </div>
            </td>
        </tr>
    </table>
</div>";

                portal_send_mail(
                    $_SESSION['user_email'],
                    $fullName,
                    "Application Received - ID: $appNumber",
                    $mailBody,
                    "Your postgraduate application has been received successfully. Your Application ID is $appNumber."
                );

                update_completion($pdo, $application_id);

                $pdo->commit(); 
                unset($_SESSION['form_data']);
                redirect_to("APPLICANT/ADMISSIONS/success.php?app_no=" . urlencode(encrypt_app_number($appNumber)));
                exit();
            }
            break;
    }

    update_completion($pdo, $application_id);
    $pdo->commit();
    
    if ($step_id < 9) {
        redirect_to("APPLICANT/ADMISSIONS/dashboard.php?step=" . ($step_id + 1));
    } else {
        redirect_to('APPLICANT/ADMISSIONS/dashboard.php?step=9');
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['msg'] = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
    redirect_to("APPLICANT/ADMISSIONS/dashboard.php?step=" . $step_id);
}
$_SESSION['msg'] = ['type' => 'success', 'text' => 'Step ' . $step_id . ' saved successfully!'];
?>

