<?php
require_once __DIR__ . '/../includes/db.php';

function is_debug_allowed(): bool {
    $env = getenv('APP_ENV') ?: '';
    $debug = getenv('APP_DEBUG') ?: '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return $env === 'local' || $debug === '1' || in_array($host, ['127.0.0.1', '127.0.0.1'], true);
}

if (!is_debug_allowed()) {
    http_response_code(404);
    exit;
}

header('Content-Type: application/json');

function normalize_base32_secret(string $secret): string {
    $secret = strtoupper($secret);
    return preg_replace('/[^A-Z2-7]/', '', $secret);
}

function build_totp_uri(string $account, string $secret, string $issuer = 'JOSTUM PG'): string {
    $account = trim($account);
    $issuer = trim($issuer) !== '' ? trim($issuer) : 'JOSTUM PG';
    $secret = normalize_base32_secret($secret);

    $label = rawurlencode($issuer . ':' . $account);
    $issuerEnc = rawurlencode($issuer);

    return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuerEnc}&algorithm=SHA1&digits=6&period=30";
}

function base32_decode_custom(string $input): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $input = strtoupper(preg_replace('/[^A-Z2-7]/', '', $input));
    $bits = '';
    foreach (str_split($input) as $char) {
        $val = strpos($alphabet, $char);
        if ($val === false) {
            continue;
        }
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }
    $output = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $output .= chr(bindec($byte));
        }
    }
    return $output;
}

function generate_totp(string $secret, ?int $timeSlice = null, int $digits = 6): string {
    $timeSlice = $timeSlice ?? (int) floor(time() / 30);
    $secretKey = base32_decode_custom($secret);
    $counter = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $counter, $secretKey, true);
    $offset = ord($hash[19]) & 0x0F;
    $value = (
        ((ord($hash[$offset]) & 0x7f) << 24) |
        ((ord($hash[$offset + 1]) & 0xff) << 16) |
        ((ord($hash[$offset + 2]) & 0xff) << 8) |
        (ord($hash[$offset + 3]) & 0xff)
    );
    $mod = 10 ** $digits;
    return str_pad((string) ($value % $mod), $digits, '0', STR_PAD_LEFT);
}

if (!$pdo) {
    echo json_encode(['error' => 'Database unavailable.']);
    exit;
}

$email = trim($_GET['email'] ?? '');
if ($email === '') {
    echo json_encode(['error' => 'Missing email parameter.']);
    exit;
}

$stmt = $pdo->prepare("SELECT user_id, email, totp_secret, totp_enabled FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['error' => 'User not found.']);
    exit;
}

$settings = $pdo->query("SELECT * FROM system_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$issuer = trim($settings['institution_name'] ?? 'JOSTUM PG');
$secret = normalize_base32_secret((string) ($user['totp_secret'] ?? ''));

$timeSlice = (int) floor(time() / 30);
$currentCode = $secret !== '' ? generate_totp($secret, $timeSlice) : '';
$uri = build_totp_uri($user['email'], $secret, $issuer);

echo json_encode([
    'email' => $user['email'],
    'totp_enabled' => (int) ($user['totp_enabled'] ?? 0),
    'secret' => $secret,
    'otpauth_uri' => $uri,
    'time_slice' => $timeSlice,
    'current_totp' => $currentCode,
], JSON_PRETTY_PRINT);
