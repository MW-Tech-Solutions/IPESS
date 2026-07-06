<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

if (!function_exists('start_secure_session')) {
    function start_secure_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = is_secure_connection();
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

if (!function_exists('enforce_session_timeout')) {
    function enforce_session_timeout(?int $timeoutSeconds = null, string $loginPath = 'login.php'): void
    {
        start_secure_session();
        $timeoutSeconds = $timeoutSeconds ?? JOSTUM_SESSION_TIMEOUT;

        if (isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > $timeoutSeconds) {
            session_unset();
            session_destroy();
            require_once JOSTUM_ROOT . '/config/urls.php';
            redirect_to($loginPath . (str_contains($loginPath, '?') ? '&' : '?') . 'timeout=1');
        }

        $_SESSION['last_activity'] = time();
    }
}
