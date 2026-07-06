<?php
declare(strict_types=1);

function spreadsheet_rows(string $path, string $extension): array
{
    $extension = strtolower($extension);
    if ($extension === 'csv') {
        return csv_rows($path);
    }
    if ($extension === 'xlsx') {
        return xlsx_rows($path);
    }
    if ($extension === 'xls') {
        return xls_rows($path);
    }
    throw new RuntimeException('Unsupported file type.');
}

function csv_rows(string $path): array
{
    $handle = fopen($path, 'rb');
    if (!$handle) {
        throw new RuntimeException('Unable to open CSV file.');
    }
    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        if (count(array_filter($row, fn($cell) => trim((string) $cell) !== '')) === 0) {
            continue;
        }
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

function xlsx_rows(string $path): array
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('PHP Zip extension is required for XLSX imports.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Unable to open XLSX file.');
    }

    $shared = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $xml = simplexml_load_string($sharedXml);
        foreach ($xml->si as $si) {
            $parts = [];
            if (isset($si->t)) {
                $parts[] = (string) $si->t;
            }
            foreach ($si->r ?? [] as $run) {
                $parts[] = (string) $run->t;
            }
            $shared[] = implode('', $parts);
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) {
        throw new RuntimeException('The XLSX file has no first worksheet.');
    }

    $xml = simplexml_load_string($sheetXml);
    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $cell) {
            $type = (string) $cell['t'];
            $value = (string) $cell->v;
            $cells[] = $type === 's' ? ($shared[(int) $value] ?? '') : $value;
        }
        if (count(array_filter($cells, fn($cell) => trim((string) $cell) !== '')) > 0) {
            $rows[] = $cells;
        }
    }
    return $rows;
}

function xls_rows(string $path): array
{
    if (class_exists('COM')) {
        $excel = new COM('Excel.Application');
        $excel->DisplayAlerts = false;
        $workbook = $excel->Workbooks->Open(realpath($path));
        $temp = tempnam(sys_get_temp_dir(), 'jostum_xls_') . '.csv';
        $workbook->SaveAs($temp, 6);
        $workbook->Close(false);
        $excel->Quit();
        $rows = csv_rows($temp);
        @unlink($temp);
        return $rows;
    }
    throw new RuntimeException('Legacy XLS import needs Microsoft Excel COM support on this server. Please upload CSV or XLSX.');
}

function normalize_header(string $header): string
{
    $header = strtolower(trim($header));
    $header = preg_replace('/[^a-z0-9]+/', '_', $header);
    return trim((string) $header, '_');
}

function import_jamb_candidates(string $path, string $extension, int $sessionId, ?int $batchId = null): array
{
    $rows = spreadsheet_rows($path, $extension);
    if (count($rows) < 2) {
        throw new RuntimeException('The import file must contain a header row and at least one candidate.');
    }

    $headers = array_map('normalize_header', array_shift($rows));
    $aliases = [
        'jamb_reg_no' => ['jamb_reg_no', 'jamb_registration_number', 'registration_number', 'reg_no', 'jamb_no'],
        'surname' => ['surname', 'last_name'],
        'first_name' => ['first_name', 'firstname', 'given_name'],
        'other_names' => ['other_names', 'middle_name', 'other_name'],
        'gender' => ['gender', 'sex'],
        'email' => ['email', 'email_address'],
        'phone' => ['phone', 'phone_number', 'gsm', 'mobile'],
        'course_applied' => ['course_applied', 'course', 'programme', 'program'],
        'course_code' => ['course_code', 'programme_code', 'program_code'],
        'course_name' => ['course_name', 'programme_name', 'program_name'],
        'utme_score' => ['utme_score', 'score', 'jamb_score'],
        'state_origin' => ['state_origin', 'state_of_origin', 'state'],
        'lga' => ['lga', 'local_government'],
        'utme_subject_1' => ['utme_subject_1', 'subject_1'],
        'utme_subject_2' => ['utme_subject_2', 'subject_2'],
        'utme_subject_3' => ['utme_subject_3', 'subject_3'],
        'utme_subject_4' => ['utme_subject_4', 'subject_4'],
    ];

    $positions = [];
    foreach ($aliases as $field => $names) {
        foreach ($names as $name) {
            $index = array_search($name, $headers, true);
            if ($index !== false) {
                $positions[$field] = $index;
                break;
            }
        }
    }
    if (!isset($positions['jamb_reg_no'], $positions['surname'], $positions['first_name'])) {
        throw new RuntimeException('Required columns: JAMB Reg No, Surname, First Name.');
    }

    $pdo = db();
    $sessionStmt = $pdo->prepare('SELECT year_label FROM admission_sessions WHERE id = ?');
    $sessionStmt->execute([$sessionId]);
    $admissionYear = (string) $sessionStmt->fetchColumn();
    $stmt = $pdo->prepare('INSERT INTO jamb_candidates (admission_session_id, admission_year, jamb_reg_no, surname, first_name, other_names, gender, email, phone, course_applied, utme_score, state_origin, lga, course_code, course_name, jamb_score, utme_subject_1, utme_subject_2, utme_subject_3, utme_subject_4, raw_payload, import_batch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE surname=VALUES(surname), first_name=VALUES(first_name), other_names=VALUES(other_names), gender=VALUES(gender), email=VALUES(email), phone=VALUES(phone), course_applied=VALUES(course_applied), utme_score=VALUES(utme_score), state_origin=VALUES(state_origin), lga=VALUES(lga), course_code=VALUES(course_code), course_name=VALUES(course_name), jamb_score=VALUES(jamb_score), utme_subject_1=VALUES(utme_subject_1), utme_subject_2=VALUES(utme_subject_2), utme_subject_3=VALUES(utme_subject_3), utme_subject_4=VALUES(utme_subject_4), raw_payload=VALUES(raw_payload), import_batch_id=VALUES(import_batch_id)');
    $imported = 0;
    $skipped = 0;

    foreach ($rows as $row) {
        $data = [];
        foreach ($positions as $field => $index) {
            $data[$field] = trim((string) ($row[$index] ?? ''));
        }
        if (($data['jamb_reg_no'] ?? '') === '') {
            $skipped++;
            continue;
        }
        $stmt->execute([
            $sessionId,
            $admissionYear,
            strtoupper($data['jamb_reg_no']),
            strtoupper($data['surname'] ?? ''),
            strtoupper($data['first_name'] ?? ''),
            strtoupper($data['other_names'] ?? ''),
            strtoupper($data['gender'] ?? ''),
            $data['email'] ?? '',
            $data['phone'] ?? '',
            $data['course_applied'] ?? ($data['course_name'] ?? ''),
            $data['utme_score'] !== '' ? (int) $data['utme_score'] : null,
            $data['state_origin'] ?? '',
            $data['lga'] ?? '',
            $data['course_code'] ?? '',
            $data['course_name'] ?? ($data['course_applied'] ?? ''),
            $data['utme_score'] !== '' ? (int) $data['utme_score'] : null,
            $data['utme_subject_1'] ?? '',
            $data['utme_subject_2'] ?? '',
            $data['utme_subject_3'] ?? '',
            $data['utme_subject_4'] ?? '',
            json_encode(array_combine($headers, array_slice(array_pad($row, count($headers), ''), 0, count($headers))), JSON_UNESCAPED_UNICODE),
            $batchId,
        ]);
        $imported++;
    }

    return ['imported' => $imported, 'skipped' => $skipped];
}
