<?php
function app_root_path(): string
{
    static $root = null;
    if ($root !== null) {
        return $root;
    }

    $uri = strtok($_SERVER['REQUEST_URI'] ?? '', '?#');
    $cleanUri = '/' . ltrim($uri ?: '', '/');

    // Only use '/IPESS' prefix if '/IPESS/' or '/ipess/' is explicitly present in the requested URL path (e.g. local XAMPP)
    if (preg_match('#^/ipess(/|$)#i', $cleanUri)) {
        $root = '/IPESS';
        return $root;
    }

    // On production/live domains (or when mapped to root), the application root is always empty string
    $root = '';
    return $root;
}

function app_origin(): string
{
    require_once __DIR__ . '/../app/config/app.php';
    $https = is_secure_connection();
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
    return $scheme . '://' . $host;
}

function app_url(?string $path = ''): string
{
    $path = $path ?? '';
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if (preg_match('#^(localhost|127\.0\.0\.1)(:\d+)?/#i', $path)) {
        $scheme = parse_url(app_origin(), PHP_URL_SCHEME) ?: 'http';
        return $scheme . '://' . ltrim($path, '/');
    }

    $root = app_root_path();

    if ($path === '' || $path === '/') {
        return $root !== '' ? $root . '/' : '/';
    }

    $path = '/' . ltrim($path, '/');

    if ($root !== '' && ($path === $root || strpos($path, $root . '/') === 0)) {
        return $path;
    }

    return ($root !== '' ? $root : '') . $path;
}

function app_absolute_url(?string $path = ''): string
{
    return rtrim(app_origin(), '/') . app_url($path ?? '');
}

function redirect_to(string $path, int $code = 302): void
{
    // Flush session data before redirect to prevent session loss on fast redirects
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $url = app_absolute_url($path);
    if (!headers_sent()) {
        header('Location: ' . $url, true, $code);
        exit();
    } else {
        echo '<script type="text/javascript">';
        echo 'window.location.href="' . $url . '";';
        echo '</script>';
        echo '<noscript>';
        echo '<meta http-equiv="refresh" content="0;url=' . $url . '" />';
        echo '</noscript>';
        exit();
    }
}

if (!function_exists('encrypt_app_number')) {
    function encrypt_app_number(string $appNo): string
    {
        $key = 'JOSTUM_APP_SECRET_KEY_2026';
        $encrypted = openssl_encrypt($appNo, 'AES-128-ECB', $key);
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($encrypted));
    }
}

if (!function_exists('decrypt_app_number')) {
    function decrypt_app_number(string $hash): string
    {
        $key = 'JOSTUM_APP_SECRET_KEY_2026';
        $data = str_replace(['-', '_'], ['+', '/'], $hash);
        $mod = strlen($data) % 4;
        if ($mod) {
            $data .= str_repeat('=', 4 - $mod);
        }
        $encrypted = base64_decode($data);
        if ($encrypted === false) {
            return '';
        }
        $decrypted = openssl_decrypt($encrypted, 'AES-128-ECB', $key);
        return $decrypted ?: '';
    }
}
