<?php
require_once __DIR__ . '/../app/bootstrap.php';
header('Content-Type: application/json');

function get_user_column_flags_test(PDO $pdo): array {
    $columns = ['role', 'role_id', 'password_hash', 'totp_secret', 'totp_enabled'];
    $flags = array_fill_keys($columns, false);
    foreach ($columns as $col) {
        try {
            $pdo->query("SELECT `{$col}` FROM users LIMIT 0");
            $flags[$col] = true;
        } catch (Throwable $e) {
            $flags[$col] = false;
        }
    }
    return $flags;
}

$email = 'muhdmukhtar2019@gmail.com';
$password = '12345678';

$flags = get_user_column_flags_test($pdo);
$passwordColumn = $flags['password_hash'] ? 'password_hash' : 'password';
$totpSelect = $flags['totp_secret'] ? ', u.totp_secret, u.totp_enabled' : '';

$user = null;
if ($flags['role']) {
    $stmt = $pdo->prepare("SELECT user_id, {$passwordColumn} AS password_hash, role, full_name{$totpSelect} FROM users WHERE email = :email LIMIT 1");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} elseif ($flags['role_id']) {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.{$passwordColumn} AS password_hash, r.role_key AS role, u.full_name{$totpSelect}
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.role_id
        WHERE u.email = :email
        LIMIT 1
    ");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$response = [
    'flags' => $flags,
    'user_found' => !is_null($user),
    'user_data' => $user ? [
        'user_id' => $user['user_id'],
        'role' => $user['role'] ?? null,
        'full_name' => $user['full_name'] ?? null,
        'password_hash' => $user['password_hash'] ?? null
    ] : null,
];

if ($user) {
    $response['password_match'] = password_verify($password, $user['password_hash']);
    $response['role_allowed'] = in_array(normalize_role($user['role'] ?? ''), array_map('normalize_role', [
        'SUPER_ADMIN', 'ICT_ADMIN', 'PORTAL_ADMIN', 'REGISTRY', 'ADMISSIONS_OFFICER', 'BURSARY', 'PG_SCHOOL_OFFICER', 'FACULTY_OFFICER', 'DEPARTMENT_ADMIN', 'HOD', 'SUPERVISOR', 'REVIEWER', 'ADMIN'
    ]), true);
}

echo json_encode($response, JSON_PRETTY_PRINT);
