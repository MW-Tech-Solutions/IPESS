<?php
/**
 * User Activity Logger Helper
 */

if (!function_exists("ensure_user_logs_tables")) {
    function ensure_user_logs_tables(PDO $pdo): void {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `user_login_logs` (
                    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `staff_id`      VARCHAR(100)  DEFAULT NULL,
                    `username`      VARCHAR(150)  DEFAULT NULL,
                    `full_name`     VARCHAR(255)  DEFAULT NULL,
                    `role`          VARCHAR(100)  DEFAULT NULL,
                    `event_type`    ENUM(\"LOGIN\",\"LOGOUT\",\"ACTION\") NOT NULL DEFAULT \"ACTION\",
                    `action`        VARCHAR(255)  DEFAULT NULL,
                    `details`       TEXT          DEFAULT NULL,
                    `entity_type`   VARCHAR(100)  DEFAULT NULL,
                    `entity_id`     VARCHAR(100)  DEFAULT NULL,
                    `ip_address`    VARCHAR(60)   DEFAULT NULL,
                    `user_agent`    VARCHAR(500)  DEFAULT NULL,
                    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_staff_id`   (`staff_id`),
                    INDEX `idx_role`       (`role`),
                    INDEX `idx_event_type` (`event_type`),
                    INDEX `idx_created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable $e) {}
    }
}

if (!function_exists("get_real_ip")) {
    function get_real_ip(): string {
        foreach (["HTTP_CF_CONNECTING_IP","HTTP_X_REAL_IP","HTTP_X_FORWARDED_FOR","REMOTE_ADDR"] as $key) {
            $val = $_SERVER[$key] ?? "";
            if ($val) return trim(explode(",", $val)[0]);
        }
        return "unknown";
    }
}

if (!function_exists("log_user_login")) {
    function log_user_login(PDO $pdo, $staffId, string $username, string $fullName, string $role): void {
        ensure_user_logs_tables($pdo);
        
        // Resolve full name and username if they are empty, numeric, or matching IDs
        if (empty($fullName) || is_numeric($fullName) || $fullName === (string)$staffId || $fullName === $username) {
            try {
                $stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ? LIMIT 1");
                $stmt->execute([(int)$staffId]);
                $dbName = $stmt->fetchColumn();
                if ($dbName) {
                    $fullName = trim($dbName);
                } else {
                    $stmtAccess = $pdo->prepare("SELECT FirstName, LastName FROM user_access WHERE staffIDs = ? LIMIT 1");
                    $stmtAccess->execute([(int)$staffId]);
                    $ua = $stmtAccess->fetch(PDO::FETCH_ASSOC);
                    if ($ua) {
                        $resolved = trim(($ua['FirstName'] ?? '') . ' ' . ($ua['LastName'] ?? ''));
                        if ($resolved !== '') {
                            $fullName = $resolved;
                        }
                    }
                }
            } catch (Throwable $e) {}
        }

        if (is_numeric($username)) {
            try {
                $stmtAccess = $pdo->prepare("SELECT userName FROM user_access WHERE staffIDs = ? LIMIT 1");
                $stmtAccess->execute([(int)$username]);
                $uName = $stmtAccess->fetchColumn();
                if ($uName) {
                    $username = trim($uName);
                }
            } catch (Throwable $e) {}
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO user_login_logs (staff_id, username, full_name, role, event_type, action, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, \"LOGIN\", \"User Login\", ?, ?, ?, NOW())");
            $stmt->execute([(string)$staffId, $username, $fullName, strtoupper($role), "User \"{$fullName}\" ({$username}) logged in with role: {$role}", get_real_ip(), substr($_SERVER["HTTP_USER_AGENT"] ?? "Unknown", 0, 500)]);
        } catch (Throwable $e) { error_log("log_user_login: " . $e->getMessage()); }
    }
}

if (!function_exists("log_user_activity")) {
    function log_user_activity(PDO $pdo, $staffId, string $username, string $fullName, string $role, string $action, string $details, string $entityType = "", $entityId = null): void {
        ensure_user_logs_tables($pdo);
        try {
            $stmt = $pdo->prepare("INSERT INTO user_login_logs (staff_id, username, full_name, role, event_type, action, details, entity_type, entity_id, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, \"ACTION\", ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([(string)$staffId, $username, $fullName, strtoupper($role), $action, $details, $entityType ?: null, $entityId !== null ? (string)$entityId : null, get_real_ip(), substr($_SERVER["HTTP_USER_AGENT"] ?? "Unknown", 0, 500)]);
        } catch (Throwable $e) { error_log("log_user_activity: " . $e->getMessage()); }
    }
}

if (!function_exists("log_from_session")) {
    function log_from_session(PDO $pdo, string $action, string $details, string $entityType = "", $entityId = null): void {
        $staffId  = $_SESSION["user_id"]   ?? $_SESSION["userid"] ?? "unknown";
        $username = $_SESSION["userid"]    ?? (string)$staffId;
        $fullName = $_SESSION["full_name"] ?? $_SESSION["name"]   ?? $username;
        $role     = $_SESSION["role"]      ?? $_SESSION["roleid"] ?? "UNKNOWN";

        // Resolve full name and username if they are empty, numeric, or matching IDs
        if (empty($fullName) || is_numeric($fullName) || $fullName === (string)$staffId || $fullName === $username) {
            try {
                $stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ? LIMIT 1");
                $stmt->execute([(int)$staffId]);
                $dbName = $stmt->fetchColumn();
                if ($dbName) {
                    $fullName = trim($dbName);
                } else {
                    $stmtAccess = $pdo->prepare("SELECT FirstName, LastName FROM user_access WHERE staffIDs = ? LIMIT 1");
                    $stmtAccess->execute([(int)$staffId]);
                    $ua = $stmtAccess->fetch(PDO::FETCH_ASSOC);
                    if ($ua) {
                        $resolved = trim(($ua['FirstName'] ?? '') . ' ' . ($ua['LastName'] ?? ''));
                        if ($resolved !== '') {
                            $fullName = $resolved;
                        }
                    }
                }
            } catch (Throwable $e) {}
        }

        if (is_numeric($username)) {
            try {
                $stmtAccess = $pdo->prepare("SELECT userName FROM user_access WHERE staffIDs = ? LIMIT 1");
                $stmtAccess->execute([(int)$username]);
                $uName = $stmtAccess->fetchColumn();
                if ($uName) {
                    $username = trim($uName);
                }
            } catch (Throwable $e) {}
        }

        log_user_activity($pdo, $staffId, $username, $fullName, $role, $action, $details, $entityType, $entityId);
    }
}
