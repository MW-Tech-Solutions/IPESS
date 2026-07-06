<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';

if (!function_exists('db')) {
    function db(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $host = env_value('DB_HOST', '127.0.0.1');
        $database = env_value('DB_NAME', 'pg');
        $username = env_value('DB_USER', 'root');
        $password = env_value('DB_PASS', '');
        $charset = env_value('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host={$host};dbname={$database};charset={$charset}";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Disable strict mode – prevents 'Field doesn't have default value' on Bluehost/cPanel servers
        $pdo->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");

        return $pdo;
    }
}

$pdo = db();
