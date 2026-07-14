<?php
declare(strict_types=1);

if (!function_exists('is_module_active')) {
    function is_module_active(string $module_key): bool
    {
        try {
            require_once __DIR__ . '/../config/database.php';
            $pdo = db();
            
            $stmt = $pdo->prepare("
                SELECT is_active 
                FROM system_modules 
                WHERE module_key = ?
            ");
            $stmt->execute([$module_key]);
            $val = $stmt->fetchColumn();
            if ($val !== false) {
                return (int)$val === 1;
            }
        } catch (Throwable $e) {
            // Suppress errors, default to active
        }
        return true;
    }
}

if (!function_exists('is_module_accessible')) {
    function is_module_accessible(string $module_key): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $role = $_SESSION['role'] ?? null;
        if ($role === 'SUPER_ADMIN') {
            return true;
        }
        return is_module_active($module_key);
    }
}
