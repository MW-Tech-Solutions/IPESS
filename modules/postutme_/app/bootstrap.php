<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (is_file(POSTUTME_JOSTUM_ROOT . '/config/urls.php')) {
    require_once POSTUTME_JOSTUM_ROOT . '/config/urls.php';
}

ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => function_exists('is_secure_connection') ? is_secure_connection() : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

enforce_session_timeout();
