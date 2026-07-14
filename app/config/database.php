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

function ensure_database_compatibility(PDO $pdo): void {
    try {
        // Auto-heal/generate any missing tables
        $repairPath = __DIR__ . '/../../helpers/generate_all_tables.php';
        if (file_exists($repairPath)) {
            require_once $repairPath;
            if (function_exists('generate_all_missing_tables')) {
                generate_all_missing_tables($pdo);
            }
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                email VARCHAR(150) NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_reset_email (email),
                INDEX idx_reset_token (token_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS role_permissions (
                role_key VARCHAR(50) NOT NULL,
                permission_key VARCHAR(100) NOT NULL,
                PRIMARY KEY (role_key, permission_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_permissions (
                user_id INT NOT NULL,
                permission_key VARCHAR(100) NOT NULL,
                granted TINYINT(1) DEFAULT 1,
                PRIMARY KEY (user_id, permission_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS application_status_history (
                history_id INT AUTO_INCREMENT PRIMARY KEY,
                application_id INT NOT NULL,
                from_status VARCHAR(60) DEFAULT NULL,
                to_status VARCHAR(60) NOT NULL,
                actor_id INT DEFAULT NULL,
                actor_role VARCHAR(50) DEFAULT NULL,
                note TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_app_status (application_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $columns = [];
        try {
            $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {}

        if (!empty($columns)) {
            $neededColumns = [
                'totp_secret' => 'VARCHAR(64) NULL',
                'totp_enabled' => 'TINYINT(1) DEFAULT 0',
                'totp_verified_at' => 'DATETIME DEFAULT NULL',
                'reset_token' => 'VARCHAR(64) NULL',
                'reset_expires' => 'DATETIME NULL'
            ];

            foreach ($neededColumns as $col => $definition) {
                if (!in_array($col, $columns, true)) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN {$col} {$definition}");
                }
            }
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS system_settings (
                setting_id INT AUTO_INCREMENT PRIMARY KEY,
                institution_name VARCHAR(255) DEFAULT 'Institute of Procurement, Environmental and Social Standard IPESS JOSTUM',
                session_timeout_seconds INT DEFAULT 900,
                email_smtp_host VARCHAR(150) DEFAULT '',
                email_smtp_port INT DEFAULT 465,
                email_smtp_user VARCHAR(150) DEFAULT '',
                email_smtp_pass VARCHAR(150) DEFAULT '',
                email_smtp_encryption VARCHAR(10) DEFAULT 'ssl',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $count = (int) $pdo->query("SELECT COUNT(*) FROM system_settings")->fetchColumn();
        if ($count === 0) {
            $pdo->exec("
                INSERT INTO system_settings (institution_name)
                VALUES ('Institute of Procurement, Environmental and Social Standard IPESS JOSTUM')
            ");
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS supervisor_assignments (
                assignment_id INT AUTO_INCREMENT PRIMARY KEY,
                supervisor_id VARCHAR(50) NOT NULL,
                application_id INT NOT NULL,
                student_id INT NULL,
                assigned_by INT NULL,
                assigned_at DATETIME DEFAULT NULL,
                status VARCHAR(30) DEFAULT 'Assigned',
                UNIQUE KEY idx_app_sup (application_id, supervisor_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        try {
            $pdo->exec("
                CREATE OR REPLACE VIEW supervisors AS 
                SELECT supervisor_id, full_name, email, phone, specialization, status 
                FROM supervisor_profiles;
            ");
        } catch (Throwable $e) {}
    } catch (Throwable $e) {
        error_log("Database Auto-Correction Error: " . $e->getMessage());
    }
}

$pdo = db();

if (isset($pdo)) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (empty($_SESSION['db_compat_checked'])) {
            ensure_database_compatibility($pdo);
            $_SESSION['db_compat_checked'] = true;
        }
    } else {
        ensure_database_compatibility($pdo);
    }
}
