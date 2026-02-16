<?php
if (!function_exists('load_env_fallback')) {
function load_env_fallback(string $path): void
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
}

if (PHP_VERSION_ID >= 80200 && file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists('Dotenv\\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();
    }
} else {
    load_env_fallback(__DIR__ . '/../.env');
}

require_once __DIR__ . '/urls.php';

$host = $_ENV['DB_HOST'] ?? 'localhost';
$db   = $_ENV['DB_NAME'] ?? 'pg';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '997667';

$pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);    

// Optional auto-migration for critical workflow columns (disabled by default).
$autoMigrate = ($_ENV['APP_AUTO_MIGRATE'] ?? '0') === '1';
if ($autoMigrate) {
    try {
        $hasCurrentStatus = $pdo->query("SHOW COLUMNS FROM applications LIKE 'current_status'")->fetch(PDO::FETCH_ASSOC);
        if (!$hasCurrentStatus) {
            $pdo->exec("ALTER TABLE applications ADD COLUMN current_status VARCHAR(60) DEFAULT 'DRAFT'");
        }

        $hasCompletion = $pdo->query("SHOW COLUMNS FROM applications LIKE 'completion_percentage'")->fetch(PDO::FETCH_ASSOC);
        if (!$hasCompletion) {
            $pdo->exec("ALTER TABLE applications ADD COLUMN completion_percentage INT DEFAULT 0");
        }
    } catch (PDOException $e) {
        // Ignore schema changes if permissions are restricted.
    }
}
?>
