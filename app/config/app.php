<?php

declare(strict_types=1);

define('JOSTUM_ROOT', dirname(__DIR__, 2));
define('JOSTUM_APP', JOSTUM_ROOT . DIRECTORY_SEPARATOR . 'app');
define('JOSTUM_STORAGE', JOSTUM_ROOT . DIRECTORY_SEPARATOR . 'storage');
define('JOSTUM_UPLOADS', JOSTUM_STORAGE . DIRECTORY_SEPARATOR . 'uploads');

if (!defined('JOSTUM_SESSION_TIMEOUT')) {
    define('JOSTUM_SESSION_TIMEOUT', (int) ($_ENV['SESSION_TIMEOUT'] ?? 900));
}

if (!function_exists('env_value')) {
    function env_value(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? getenv($key);
        return $value === false || $value === null ? $default : (string) $value;
    }
}

if (!function_exists('jostum_load_env_file')) {
    function jostum_load_env_file(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            $value = trim(trim($value), "\"'");
            if ($key !== '' && getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
    }
}

if (is_file(JOSTUM_ROOT . '/vendor/autoload.php')) {
    require_once JOSTUM_ROOT . '/vendor/autoload.php';
    if (class_exists('Dotenv\\Dotenv') && is_file(JOSTUM_ROOT . '/.env')) {
        Dotenv\Dotenv::createImmutable(JOSTUM_ROOT)->safeLoad();
    }
}

jostum_load_env_file(JOSTUM_ROOT . '/.env');

if (!function_exists('is_secure_connection')) {
    function is_secure_connection(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');
    }
}

