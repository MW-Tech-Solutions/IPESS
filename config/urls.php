<?php
function load_env_fallback_local(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $value = trim($value, "\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function app_base_url(): string
{
    if (!isset($_ENV['APP_BASE_URL'])) {
        load_env_fallback_local(__DIR__ . '/../.env');
    }
    $base = $_ENV['APP_BASE_URL'] ?? '';
    if ($base) {
        return rtrim($base, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host;
}

function app_url(string $path = ''): string
{
    $base = app_base_url();
    if ($path === '' || $path === '/') {
        return $base;
    }
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    return $base . $path;
}

function redirect_to(string $path, int $code = 302): void
{
    header('Location: ' . app_url($path), true, $code);
    exit();
}
?>
