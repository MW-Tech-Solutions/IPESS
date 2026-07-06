<?php
function app_root_path(): string
{
    static $root = null;
    if ($root !== null) {
        return $root;
    }

    $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: (__DIR__ . '/..'));
    $documentRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ($_SERVER['DOCUMENT_ROOT'] ?? ''));

    if ($documentRoot !== '' && stripos($projectRoot, $documentRoot) === 0) {
        $relative = substr($projectRoot, strlen($documentRoot));
        $relative = '/' . trim($relative, '/');
        $root = $relative === '/' ? '' : $relative;
    } else {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $parts = $scriptName !== '' ? explode('/', trim(str_replace('\\', '/', $scriptName), '/')) : [];
        $projectName = basename($projectRoot);
        
        $index = false;
        foreach ($parts as $i => $part) {
            if (strcasecmp($part, $projectName) === 0) {
                $index = $i;
                break;
            }
        }
        
        if ($index !== false) {
            $root = '/' . implode('/', array_slice($parts, 0, $index + 1));
        } else {
            $root = '';
        }
    }

    return rtrim($root, '/');
}

function app_origin(): string
{
    require_once __DIR__ . '/../app/config/app.php';
    $https = is_secure_connection();
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
    return $scheme . '://' . $host;
}

function app_url(string $path = ''): string
{
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

function app_absolute_url(string $path = ''): string
{
    return rtrim(app_origin(), '/') . app_url($path);
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
