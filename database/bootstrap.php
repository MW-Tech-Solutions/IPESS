<?php
/**
 * IPESS Database Bootstrapper
 * ─────────────────────────────────────────────────────────────────
 * Safe, idempotent migration + seed runner.
 *
 * Guarantees that all core RBAC tables exist and contain the required
 * seed data BEFORE any page logic runs. Uses:
 *   - CREATE TABLE IF NOT EXISTS  → never destroys existing data
 *   - INSERT IGNORE               → never duplicates seed rows
 *   - A .bootstrap_done flag file → zero overhead after first run
 *
 * Include from app/bootstrap.php — it auto-detects the PDO connection.
 */

declare(strict_types=1);

// ── Flag file: skip all checks if already bootstrapped ──────────────
$_bootstrapFlag = defined('JOSTUM_ROOT')
    ? JOSTUM_ROOT . '/database/.bootstrap_done'
    : __DIR__ . '/../database/.bootstrap_done';

if (file_exists($_bootstrapFlag)) {
    return; // Already ran — zero overhead
}

// ── Load permissions registry ────────────────────────────────────────
$_registryFile = defined('JOSTUM_ROOT')
    ? JOSTUM_ROOT . '/helpers/permissions-registry.php'
    : __DIR__ . '/../helpers/permissions-registry.php';

if (file_exists($_registryFile)) {
    require_once $_registryFile;
}

// ── Get DB connection ─────────────────────────────────────────────────
try {
    if (!function_exists('db')) {
        return; // db() not available yet — skip silently
    }
    $__bsPdo = db();
} catch (Throwable $__bsEx) {
    return; // DB not available — skip silently, page will handle its own errors
}

try {
    // ════════════════════════════════════════════════════════════════
    // 1. CREATE TABLES IF NOT EXIST
    // ════════════════════════════════════════════════════════════════

    $__bsPdo->exec("
        CREATE TABLE IF NOT EXISTS `roles` (
            `role_id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `role_key`    VARCHAR(60)  NOT NULL UNIQUE,
            `role_name`   VARCHAR(100) NOT NULL,
            `description` TEXT         DEFAULT NULL,
            `is_system`   TINYINT(1)   NOT NULL DEFAULT 0,
            `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $__bsPdo->exec("
        CREATE TABLE IF NOT EXISTS `permissions` (
            `permission_id`  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `permission_key` VARCHAR(80)  NOT NULL UNIQUE,
            `permission_name`VARCHAR(120) NOT NULL,
            `description`    TEXT         DEFAULT NULL,
            `group_name`     VARCHAR(60)  DEFAULT NULL,
            `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $__bsPdo->exec("
        CREATE TABLE IF NOT EXISTS `role_permissions` (
            `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `role_key`       VARCHAR(60) NOT NULL,
            `permission_key` VARCHAR(80) NOT NULL,
            UNIQUE KEY `uq_role_perm` (`role_key`, `permission_key`),
            KEY `idx_role_key` (`role_key`),
            KEY `idx_perm_key` (`permission_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $__bsPdo->exec("
        CREATE TABLE IF NOT EXISTS `user_permissions` (
            `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id`        INT UNSIGNED NOT NULL,
            `permission_key` VARCHAR(80)  NOT NULL,
            `granted`        TINYINT(1)   NOT NULL DEFAULT 1,
            `granted_by`     INT UNSIGNED DEFAULT NULL,
            `granted_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_user_perm` (`user_id`, `permission_key`),
            KEY `idx_user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Ensure `description` and `is_system` columns exist on old `roles` tables
    // (handles migrations from older deployments that had a minimal schema)
    try {
        $__bsPdo->exec("ALTER TABLE `roles` ADD COLUMN `description` TEXT DEFAULT NULL");
    } catch (Throwable $_) {}
    try {
        $__bsPdo->exec("ALTER TABLE `roles` ADD COLUMN `is_system` TINYINT(1) NOT NULL DEFAULT 0");
    } catch (Throwable $_) {}
    try {
        $__bsPdo->exec("ALTER TABLE `roles` ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    } catch (Throwable $_) {}

    // ════════════════════════════════════════════════════════════════
    // 2. SEED ROLES
    // ════════════════════════════════════════════════════════════════

    if (function_exists('get_system_roles')) {
        $__roleStmt = $__bsPdo->prepare("
            INSERT IGNORE INTO `roles` (`role_key`, `role_name`, `description`, `is_system`)
            VALUES (?, ?, ?, ?)
        ");
        foreach (get_system_roles() as $__role) {
            $__roleStmt->execute([
                $__role['key'],
                $__role['name'],
                $__role['description'] ?? null,
                $__role['is_system'] ?? 0,
            ]);
        }
    }

    // ════════════════════════════════════════════════════════════════
    // 3. SEED PERMISSIONS
    // ════════════════════════════════════════════════════════════════

    if (function_exists('get_all_permission_groups')) {
        $__permStmt = $__bsPdo->prepare("
            INSERT IGNORE INTO `permissions` (`permission_key`, `permission_name`, `description`, `group_name`)
            VALUES (?, ?, ?, ?)
        ");
        foreach (get_all_permission_groups() as $__groupKey => $__group) {
            foreach ($__group['permissions'] as $__perm) {
                $__permStmt->execute([
                    $__perm['key'],
                    $__perm['name'],
                    $__perm['description'] ?? null,
                    $__groupKey,
                ]);
            }
        }
    }

    // ════════════════════════════════════════════════════════════════
    // 4. SEED ROLE → PERMISSION MAPPINGS
    // ════════════════════════════════════════════════════════════════

    if (function_exists('get_default_role_permissions')) {
        $__rpStmt = $__bsPdo->prepare("
            INSERT IGNORE INTO `role_permissions` (`role_key`, `permission_key`)
            VALUES (?, ?)
        ");
        foreach (get_default_role_permissions() as $__rKey => $__perms) {
            foreach ($__perms as $__pKey) {
                $__rpStmt->execute([$__rKey, $__pKey]);
            }
        }
    }

    // ════════════════════════════════════════════════════════════════
    // 5. Write flag file so this never re-runs on subsequent requests
    // ════════════════════════════════════════════════════════════════

    $__flagDir = dirname($_bootstrapFlag);
    if (!is_dir($__flagDir)) {
        @mkdir($__flagDir, 0755, true);
    }
    @file_put_contents(
        $_bootstrapFlag,
        "Bootstrap completed at " . date('Y-m-d H:i:s') . "\n"
    );

} catch (Throwable $__bsException) {
    // Never crash the page because of a bootstrap error — log and continue
    $__logFile = defined('JOSTUM_ROOT')
        ? JOSTUM_ROOT . '/error_log.txt'
        : __DIR__ . '/../error_log.txt';
    @file_put_contents(
        $__logFile,
        "[" . date('Y-m-d H:i:s') . "] Bootstrap Error: " . $__bsException->getMessage()
            . " in " . $__bsException->getFile() . " on line " . $__bsException->getLine() . "\n",
        FILE_APPEND
    );
}

// Clean up scope
unset($__bsPdo, $__bsEx, $__bsException, $__roleStmt, $__permStmt, $__rpStmt,
      $__role, $__perm, $__group, $__groupKey, $__rKey, $__perms, $__pKey,
      $_bootstrapFlag, $_registryFile, $__flagDir, $__logFile);
