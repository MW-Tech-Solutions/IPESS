<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    if (function_exists('app_url')) {
        return app_url('modules/postutme/' . ltrim($path, '/'));
    }
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Security token expired. Please go back and retry.');
    }
}

function current_user(): ?array
{
    $bridged = jostum_admin_bridge_user();
    if ($bridged) {
        return $bridged;
    }

    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function jostum_admin_bridge_user(): ?array
{
    $role = strtoupper((string) ($_SESSION['role'] ?? ''));
    $map = [
        'SUPER_ADMIN' => 'super_admin',
        'ICT_ADMIN' => 'ict_admin',
        'PORTAL_ADMIN' => 'admissions_officer',
        'ADMISSIONS_OFFICER' => 'admissions_officer',
        'PG_SCHOOL_OFFICER' => 'admissions_officer',
        'BURSARY' => 'finance_officer',
    ];

    if (empty($_SESSION['user_id']) || !isset($map[$role])) {
        return null;
    }

    $mainUserId = (int) $_SESSION['user_id'];
    $email = 'jostum-admin-' . $mainUserId . '@local.invalid';
    $name = 'JOSTUM ' . ucwords(strtolower(str_replace('_', ' ', $role)));
    $shadowRole = $map[$role];

    try {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) {
            $password = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
            $insert = db()->prepare('INSERT INTO users (name, email, phone, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?, 1)');
            $insert->execute([$name, $email, '', $password, $shadowRole]);
            $stmt->execute([$email]);
            $user = $stmt->fetch();
        } elseif ($user['role'] !== $shadowRole || (int) $user['is_active'] !== 1) {
            db()->prepare('UPDATE users SET role = ?, is_active = 1 WHERE id = ?')->execute([$shadowRole, (int) $user['id']]);
            $user['role'] = $shadowRole;
            $user['is_active'] = 1;
        }
        return $user ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function require_login(array $roles = []): array
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    if ($roles && !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
    return $user;
}

function enforce_session_timeout(): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }
    $minutes = max(5, (int) setting('session_timeout_minutes', '30'));
    $lastSeen = (int) ($_SESSION['last_seen_at'] ?? time());
    if (time() - $lastSeen > ($minutes * 60)) {
        session_destroy();
        session_start();
        flash('info', 'Your session timed out. Please log in again.');
        redirect('login.php');
    }
    $_SESSION['last_seen_at'] = time();
}

function setting(string $key, ?string $default = null): ?string
{
    $stmt = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row['setting_value'] ?? $default;
}

function save_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

function active_session(): array
{
    $stmt = db()->query('SELECT * FROM admission_sessions WHERE is_active = 1 ORDER BY id DESC LIMIT 1');
    $session = $stmt->fetch();
    if ($session) {
        return $session;
    }
    return ['year_label' => DEFAULT_ADMISSION_YEAR, 'application_fee' => 2000, 'is_open' => 1];
}

function audit_log(string $action, string $subjectType, ?int $subjectId = null, ?array $old = null, ?array $new = null): void
{
    $columns = column_exists('audit_logs', 'role')
        ? 'user_id, role, action, subject_type, subject_id, entity_type, entity_id, old_value_json, new_value_json, ip_address, user_agent'
        : 'user_id, action, subject_type, subject_id, ip_address, user_agent';
    $user = current_user();
    if (str_contains($columns, 'entity_type')) {
        $stmt = db()->prepare("INSERT INTO audit_logs ($columns) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $user['role'] ?? null,
            $action,
            $subjectType,
            $subjectId,
            $subjectType,
            $subjectId,
            $old ? json_encode($old) : null,
            $new ? json_encode($new) : null,
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
        ]);
        return;
    }
    $stmt = db()->prepare("INSERT INTO audit_logs ($columns) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'] ?? null, $action, $subjectType, $subjectId, $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250)]);
}

function applicant_progress(?array $applicant, ?array $payment, ?array $application): array
{
    $steps = [
        'Verify JAMB' => (bool) $applicant,
        'Profile' => (bool) ($applicant['user_id'] ?? null),
        'Payment' => payment_confirmed($payment),
        'Bio-data' => !empty($applicant['date_of_birth']) || !empty($applicant['profile_saved']),
        'O Level' => !empty($application['subjects_json']),
        'Uploads' => !empty($application['passport_path']) && !empty($application['olevel_result_path']),
        'Review' => (bool) $application,
        'Submit' => ($application['status'] ?? 'draft') !== 'draft',
        'Status' => ($application['status'] ?? '') !== '',
    ];
    return $steps;
}

function status_badge(string $status): string
{
    $map = [
        'draft' => 'secondary',
        'submitted' => 'primary',
        'under_review' => 'warning text-dark',
        'approved' => 'success',
        'rejected' => 'danger',
        'paid' => 'success',
        'successful' => 'success',
        'qualified' => 'success',
        'not_qualified' => 'danger',
        'recommended' => 'success',
        'not_recommended' => 'danger',
        'admitted' => 'success',
        'not_admitted' => 'danger',
        'pending' => 'warning text-dark',
        'failed' => 'danger',
        'not_started' => 'secondary',
    ];
    $class = $map[$status] ?? 'secondary';
    return '<span class="badge rounded-pill text-bg-' . $class . '">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
}

function upload_file(string $field, string $prefix, array $allowed): ?string
{
    if (empty($_FILES[$field]['name'])) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed for ' . $field);
    }
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Invalid file type for ' . $field);
    }
    $maxMb = max(1, (int) setting('upload_max_mb', '2'));
    if ($_FILES[$field]['size'] > $maxMb * 1024 * 1024) {
        throw new RuntimeException('File size must not exceed ' . $maxMb . 'MB.');
    }
    $mime = mime_content_type($_FILES[$field]['tmp_name']) ?: '';
    $validMimes = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'pdf' => ['application/pdf'],
    ];
    if (!in_array($mime, $validMimes[$ext] ?? [], true)) {
        throw new RuntimeException('The uploaded file content does not match the selected file type.');
    }
    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0775, true);
    }
    $name = $prefix . '_' . bin2hex(random_bytes(10)) . '.' . $ext;
    $target = UPLOAD_PATH . '/' . $name;
    move_uploaded_file($_FILES[$field]['tmp_name'], $target);
    return 'storage/uploads/' . $name;
}

function column_exists(string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = db()->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    $cache[$key] = (int) $stmt->fetchColumn() > 0;
    return $cache[$key];
}

function setting_bool(string $key, bool $default = false): bool
{
    $value = setting($key, $default ? '1' : '0');
    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
}

function valid_ng_phone(string $phone): bool
{
    return (bool) preg_match('/^(?:\+234|234|0)[789][01]\d{8}$/', preg_replace('/\s+|-/', '', $phone));
}

function normalize_jamb(string $jamb): string
{
    return strtoupper(preg_replace('/\s+/', '', trim($jamb)));
}

function candidate_full_name(array $candidate): string
{
    return trim(($candidate['surname'] ?? '') . ' ' . ($candidate['first_name'] ?? '') . ' ' . ($candidate['other_names'] ?? ''));
}

function candidate_course(array $candidate): string
{
    return $candidate['course_name'] ?: ($candidate['course_applied'] ?? '');
}

function candidate_score(array $candidate): string
{
    return (string) ($candidate['jamb_score'] ?? $candidate['utme_score'] ?? '');
}

function payment_confirmed(?array $payment): bool
{
    return in_array($payment['status'] ?? '', ['paid', 'successful'], true);
}

function generate_application_number(): string
{
    return 'JOSTUM-' . date('Y') . '-' . random_int(100000, 999999);
}

function login_limited(string $identifier): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE (identifier = ? OR ip_address = ?) AND success = 0 AND attempted_at > (NOW() - INTERVAL 15 MINUTE)');
    $stmt->execute([$identifier, $_SERVER['REMOTE_ADDR'] ?? '']);
    return (int) $stmt->fetchColumn() >= 5;
}

function record_login_attempt(string $identifier, bool $success): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (identifier, ip_address, success) VALUES (?, ?, ?)');
    $stmt->execute([$identifier, $_SERVER['REMOTE_ADDR'] ?? '', $success ? 1 : 0]);
}

function notify_user(?int $userId, string $subject, string $body): void
{
    $stmt = db()->prepare('INSERT INTO notifications (user_id, subject, body, status) VALUES (?, ?, ?, "queued")');
    $stmt->execute([$userId, $subject, $body]);
}

function application_locked(?array $application): bool
{
    if (!$application) {
        return false;
    }
    return ($application['status'] ?? 'draft') !== 'draft' && !setting_bool('allow_edit_after_submission');
}

function grade_points(string $grade): int
{
    return ['A1' => 6, 'B2' => 5, 'B3' => 4, 'C4' => 3, 'C5' => 2, 'C6' => 1, 'D7' => 0, 'E8' => 0, 'F9' => 0][strtoupper($grade)] ?? 0;
}

function compute_screening_result(int $applicantId, ?int $computedBy = null): array
{
    $stmt = db()->prepare('SELECT jc.jamb_score, jc.utme_score, COALESCE(sa.choice_course, jc.course_name, jc.course_applied) course_name FROM applicants a JOIN jamb_candidates jc ON jc.id = a.jamb_candidate_id LEFT JOIN screening_applications sa ON sa.applicant_id = a.id WHERE a.id = ?');
    $stmt->execute([$applicantId]);
    $base = $stmt->fetch();
    if (!$base) {
        throw new RuntimeException('Applicant not found.');
    }
    $subjectStmt = db()->prepare('SELECT os.grade FROM olevel_subjects os JOIN olevel_results orr ON orr.id = os.olevel_result_id WHERE orr.applicant_id = ?');
    $subjectStmt->execute([$applicantId]);
    $grades = $subjectStmt->fetchAll();
    $olevelPoints = array_sum(array_map(fn($row) => grade_points($row['grade']), $grades));
    $jamb = (int) ($base['jamb_score'] ?: $base['utme_score']);
    $jambNormalized = ($jamb / 400) * (float) setting('jamb_weight', '50');
    $olevelNormalized = min(50, ($olevelPoints / 30) * (float) setting('olevel_weight', '50'));
    $aggregate = round($jambNormalized + $olevelNormalized, 2);
    $programme = null;
    $p = db()->prepare('SELECT * FROM programmes WHERE name = ? OR code = ? LIMIT 1');
    $p->execute([$base['course_name'], $base['course_name']]);
    $programme = $p->fetch();
    $qualified = $jamb >= (int) ($programme['minimum_jamb_score'] ?? 140) && $aggregate >= (float) ($programme['cutoff_score'] ?? 50);
    $status = $qualified ? 'qualified' : 'not_qualified';
    $remarks = $qualified ? 'Candidate meets configured screening threshold.' : 'Candidate does not meet configured screening threshold.';
    $save = db()->prepare('INSERT INTO screening_results (applicant_id, jamb_score, olevel_score, aggregate_score, qualification_status, remarks, computed_at, computed_by) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?) ON DUPLICATE KEY UPDATE jamb_score=VALUES(jamb_score), olevel_score=VALUES(olevel_score), aggregate_score=VALUES(aggregate_score), qualification_status=VALUES(qualification_status), remarks=VALUES(remarks), computed_at=NOW(), computed_by=VALUES(computed_by)');
    $save->execute([$applicantId, $jamb, round($olevelNormalized, 2), $aggregate, $status, $remarks, $computedBy]);
    return compact('jamb', 'olevelNormalized', 'aggregate', 'status', 'remarks');
}
