<?php
/**
 * export-students.php
 * Exports student/applicant data as CSV.
 * GET ?status=all|Admitted|Rejected|Submitted|Pending
 * GET ?type=summary  → exports programme-level summary table instead
 */
session_start();
require_once __DIR__ . '/../../app/helpers/auth.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized access.');
}

$role = $_SESSION['role'] ?? '';
$userId = $_SESSION['user_id'];

if (!has_permission('export_csv', $role, $userId) && !has_permission('export_excel', $role, $userId)) {
    http_response_code(403);
    die('Forbidden. Insufficient permissions to export CSV/Excel.');
}

require_once 'db.php';

$allowedStatuses = ['Admitted', 'Rejected', 'Submitted'];
$statusParam   = $_GET['status'] ?? 'all';
$typeParam     = $_GET['type']   ?? 'students';

/* ── Programme Summary Export ─────────────────────────────── */
if ($typeParam === 'summary') {
    $sql = "
        SELECT
            pc.course                                                          AS Programme,
            pc.department                                                      AS Department,
            COUNT(a.application_id)                                            AS Total_Applications,
            SUM(CASE WHEN a.status = 'Admitted'  THEN 1 ELSE 0 END)          AS Admitted,
            SUM(CASE WHEN a.status = 'Rejected'  THEN 1 ELSE 0 END)          AS Rejected,
            SUM(CASE WHEN a.status = 'Submitted' THEN 1 ELSE 0 END)          AS Pending,
            ROUND(
                SUM(CASE WHEN a.status = 'Admitted' THEN 1 ELSE 0 END)
                / NULLIF(COUNT(a.application_id), 0) * 100, 1
            )                                                                  AS Approval_Rate_Pct
        FROM programme_choices pc
        JOIN applications a ON pc.application_id = a.application_id AND pc.faculty > 0
        GROUP BY pc.course, pc.department
        ORDER BY Total_Applications DESC
    ";
    $rows    = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $headers = ['Programme', 'Department', 'Total Applications', 'Admitted', 'Rejected', 'Pending', 'Approval Rate (%)'];
    $filename = 'programme_summary_' . date('Y-m-d') . '.csv';

/* ── Per-Status / All Students Export ─────────────────────── */
} else {
    $where = '';
    $label = 'all';

    if ($statusParam === 'Submitted') {
        $where = "AND a.status = 'Submitted'";
        $label = 'pending';
    } elseif (in_array($statusParam, $allowedStatuses, true)) {
        $where = "AND a.status = " . $pdo->quote($statusParam);
        $label = strtolower($statusParam);
    }

    $sql = "
        SELECT
            a.application_id                        AS application_id,
            a.application_number                    AS Application_Number,
            p.surname                               AS Surname,
            p.first_name                            AS First_Name,
            COALESCE(p.other_names, p.other_name)  AS Other_Names,
            COALESCE(p.gender, p.sex)               AS Gender,
            COALESCE(p.date_of_birth, p.dob)        AS Date_of_Birth,
            p.state_origin                          AS State_of_Origin,
            p.phone                                 AS Phone,
            u.email                                 AS Email,
            COALESCE(c.course_title, pc.course)     AS Programme,
            COALESCE(d.dept_name, pc.department)    AS Dept,
            COALESCE(dt.degree_name, pc.degree_type) AS Degree_Type,
            a.status                                AS Status,
            a.current_status                        AS Workflow_Status,
            a.submitted_at                          AS Submitted_At
        FROM applications a
        LEFT JOIN users            u  ON a.user_id        = u.user_id
        LEFT JOIN personal_details p  ON a.application_id = p.application_id
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
        LEFT JOIN departments      d  ON d.dept_id        = COALESCE(pc.department, a.department_id)
        LEFT JOIN courses          c  ON c.course_id      = pc.course
        LEFT JOIN degree_types     dt ON dt.degree_id     = pc.degree_type
        WHERE a.submitted_at IS NOT NULL
        $where
        ORDER BY a.submitted_at DESC
    ";
    $rows    = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    // O-Level & Qualifications Pre-fetching & Mapping
    $examsByApp = [];
    $resultsByExam = [];
    $educationByApp = [];
    if (!empty($rows)) {
        $appIds = array_column($rows, 'application_id');
        $placeholders = implode(',', array_fill(0, count($appIds), '?'));
        
        $stmtExams = $pdo->prepare("SELECT * FROM olevel_exams WHERE application_id IN ($placeholders) ORDER BY application_id ASC, sitting_number ASC");
        $stmtExams->execute($appIds);
        $allExams = $stmtExams->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allExams as $exam) {
            $examsByApp[$exam['application_id']][] = $exam;
        }

        if (!empty($allExams)) {
            $examIds = array_column($allExams, 'id');
            $examPlaceholders = implode(',', array_fill(0, count($examIds), '?'));
            $stmtResults = $pdo->prepare("SELECT * FROM olevel_results WHERE exam_id IN ($examPlaceholders) ORDER BY exam_id ASC, id ASC");
            $stmtResults->execute($examIds);
            $allResults = $stmtResults->fetchAll(PDO::FETCH_ASSOC);

            foreach ($allResults as $res) {
                $resultsByExam[$res['exam_id']][] = $res;
            }
        }

        try {
            $stmtEdu = $pdo->prepare("SELECT * FROM higher_education WHERE application_id IN ($placeholders) ORDER BY application_id ASC, id ASC");
            $stmtEdu->execute($appIds);
            $allEdu = $stmtEdu->fetchAll(PDO::FETCH_ASSOC);

            foreach ($allEdu as $edu) {
                $educationByApp[$edu['application_id']][] = $edu;
            }
        } catch (Throwable $e) {}
    }

    $globalSNo = 1;
    foreach ($rows as &$row) {
        $appId = $row['application_id'];

        $candidateName = trim(($row['Surname'] ?? '') . ' ' . ($row['First_Name'] ?? '') . ' ' . ($row['Other_Names'] ?? ''));
        if ($candidateName === '') {
            $candidateName = 'N/A';
        }

        $edus = $educationByApp[$appId] ?? [];
        $qualParts = [];
        foreach ($edus as $edu) {
            $qStr = trim(($edu['highest_qualification'] ?? '') . ' ' . ($edu['course_study'] ?? ''));
            if (!empty($edu['institution'])) {
                $qStr .= ' (' . $edu['institution'] . (!empty($edu['grad_year']) ? ', ' . $edu['grad_year'] : '') . ')';
            }
            $grade = $edu['class_of_degree'] ?? $edu['cgpa'] ?? '';
            if (!empty($grade)) {
                $qStr .= ' - ' . $grade;
            }
            if (!empty($qStr)) {
                $qualParts[] = $qStr;
            }
        }
        $qualificationsText = !empty($qualParts) ? implode('; ', $qualParts) : 'N/A';

        $exams = $examsByApp[$appId] ?? [];
        $numSittings = count($exams);

        $sitting1Type = 'N/A';
        $sitting1Subs = array_fill(0, 18, 'N/A');
        $sitting2Type = 'N/A';
        $sitting2Subs = array_fill(0, 18, 'N/A');

        $exam1 = null;
        $exam2 = null;
        foreach ($exams as $ex) {
            if ((int)$ex['sitting_number'] === 1) {
                $exam1 = $ex;
            } elseif ((int)$ex['sitting_number'] === 2) {
                $exam2 = $ex;
            }
        }
        
        if (!$exam1 && isset($exams[0]) && (int)$exams[0]['sitting_number'] !== 2) {
            $exam1 = $exams[0];
        }
        if (!$exam2 && isset($exams[1])) {
            $exam2 = $exams[1];
        } elseif (!$exam2 && isset($exams[0]) && (int)$exams[0]['sitting_number'] === 2) {
            $exam2 = $exams[0];
        }

        $olevelSummaryParts = [];

        if ($exam1) {
            $sitting1Type = $exam1['exam_type'];
            $res1 = $resultsByExam[$exam1['id']] ?? [];
            $subStrList = [];
            for ($i = 0; $i < 9; $i++) {
                if (isset($res1[$i])) {
                    $sitting1Subs[$i * 2] = $res1[$i]['subject_name'];
                    $sitting1Subs[$i * 2 + 1] = $res1[$i]['grade'];
                    $subStrList[] = $res1[$i]['subject_name'] . ': ' . $res1[$i]['grade'];
                }
            }
            if (!empty($subStrList)) {
                $olevelSummaryParts[] = 'Sitting 1 (' . $sitting1Type . '): ' . implode(', ', $subStrList);
            }
        }

        if ($exam2) {
            $sitting2Type = $exam2['exam_type'];
            $res2 = $resultsByExam[$exam2['id']] ?? [];
            $subStrList = [];
            for ($i = 0; $i < 9; $i++) {
                if (isset($res2[$i])) {
                    $sitting2Subs[$i * 2] = $res2[$i]['subject_name'];
                    $sitting2Subs[$i * 2 + 1] = $res2[$i]['grade'];
                    $subStrList[] = $res2[$i]['subject_name'] . ': ' . $res2[$i]['grade'];
                }
            }
            if (!empty($subStrList)) {
                $olevelSummaryParts[] = 'Sitting 2 (' . $sitting2Type . '): ' . implode(', ', $subStrList);
            }
        }

        $olevelSummary = !empty($olevelSummaryParts) ? implode(' | ', $olevelSummaryParts) : 'N/A';

        $formattedRow = [
            'S/No' => $globalSNo++,
            'Application Number' => (string)($row['Application_Number'] ?? ''),
            'Names' => $candidateName,
            'Sex' => (string)($row['Gender'] ?? 'N/A'),
            'Date of Birth' => (string)($row['Date_of_Birth'] ?? 'N/A'),
            'State' => (string)($row['State_of_Origin'] ?? 'N/A'),
            'Dept' => (string)($row['Dept'] ?? 'N/A'),
            'Qualifications' => $qualificationsText,
            'Phone Number' => (string)($row['Phone'] ?? 'N/A'),
            'Email' => (string)($row['Email'] ?? 'N/A'),
            'O-Level Summary' => $olevelSummary,
            'Status' => (string)($row['Status'] ?? 'N/A'),
            'Submitted At' => (string)($row['Submitted_At'] ?? 'N/A')
        ];

        $row = $formattedRow;
    }
    unset($row);

    $headers = [
        'S/No', 'Application Number', 'Names', 'Sex', 'Date of Birth',
        'State', 'Dept', 'Qualifications', 'Phone Number', 'Email',
        'O-Level Summary', 'Status', 'Submitted At'
    ];

    $filename = 'students_' . $label . '_' . date('Y-m-d') . '.xlsx';

    $possibleAutoloads = [
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../../../vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
        dirname(dirname(__DIR__)) . '/vendor/autoload.php',
        rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\') . '/vendor/autoload.php',
        rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\') . '/../vendor/autoload.php',
    ];

    foreach ($possibleAutoloads as $autoPath) {
        if (!empty($autoPath) && file_exists($autoPath)) {
            @require_once $autoPath;
            if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                break;
            }
        }
    }

    if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        $filename = 'students_' . $label . '_' . date('Y-m-d') . '.xlsx';

        if (!function_exists('getExcelColLetterAdmin')) {
            function getExcelColLetterAdmin($colIndex) {
                $letter = '';
                while ($colIndex > 0) {
                    $modulo = ($colIndex - 1) % 26;
                    $letter = chr(65 + $modulo) . $letter;
                    $colIndex = intval(($colIndex - $modulo) / 26);
                }
                return $letter;
            }
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Students Record');

        $colIdx = 1;
        foreach ($headers as $h) {
            $worksheet->setCellValue(getExcelColLetterAdmin($colIdx) . '1', $h);
            $colIdx++;
        }

        $lastColLetter = getExcelColLetterAdmin(count($headers));

        $rowNum = 2;
        $sheetSNo = 1;
        foreach ($rows as $r) {
            $r['S/No'] = $sheetSNo++;
            $colIdx = 1;
            foreach ($headers as $headerKey) {
                $val = $r[$headerKey] ?? '';
                $colLetter = getExcelColLetterAdmin($colIdx);
                if (in_array($headerKey, ['Application Number', 'Phone Number'], true)) {
                    $worksheet->setCellValueExplicit($colLetter . $rowNum, (string)($val ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $worksheet->setCellValue($colLetter . $rowNum, $val ?? '');
                }
                $colIdx++;
            }
            $rowNum++;
        }

        $lastDataRow = max(2, $rowNum - 1);

        try {
            $table = new \PhpOffice\PhpSpreadsheet\Worksheet\Table();
            $table->setName('AdminStudentTable');
            $table->setRange('A1:' . $lastColLetter . $lastDataRow);

            $tableStyle = new \PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle();
            $tableStyle->setTheme(\PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle::TABLE_STYLE_MEDIUM2);
            $tableStyle->setShowRowStripes(true);

            $table->setStyle($tableStyle);
            $worksheet->addTable($table);
        } catch (Throwable $e) {}

        for ($i = 1; $i <= count($headers); $i++) {
            $worksheet->getColumnDimension(getExcelColLetterAdmin($i))->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit();
    } else {
        /* Fallback: Multi-sheet XML .xls (always works without Composer) */
        $filename = 'students_' . $label . '_' . date('Y-m-d') . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        echo ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        echo ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        echo '<Styles><Style ss:ID="H"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#1F497D" ss:Pattern="Solid"/></Style></Styles>' . "\n";
        echo '<Worksheet ss:Name="Students"><Table>' . "\n";
        echo '<Row ss:StyleID="H">';
        foreach ($headers as $h) {
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars($h) . '</Data></Cell>';
        }
        echo '</Row>' . "\n";
        $sheetSNo = 1;
        foreach ($rows as $row) {
            $row['S/No'] = $sheetSNo++;
            echo '<Row>';
            foreach ($headers as $headerKey) {
                $v = $row[$headerKey] ?? '';
                echo '<Cell><Data ss:Type="String">' . htmlspecialchars((string)($v ?? '')) . '</Data></Cell>';
            }
            echo '</Row>' . "\n";
        }
        echo '</Table></Worksheet></Workbook>';
        exit();
    }
}

/* ── Fallback Stream CSV ───────────────────────────────────── */
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . str_replace('.xlsx', '.csv', $filename) . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
fputcsv($out, $headers);
foreach ($rows as $row) {
    fputcsv($out, array_values($row));
}
fclose($out);
exit();

fclose($out);
exit();
