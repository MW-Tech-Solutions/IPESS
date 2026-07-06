<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/mailer.php';

if (php_sapi_name() !== 'cli') {
    start_secure_session();
}

// Custom error/exception logging for remote debugging
set_exception_handler(function (Throwable $exception) {
    $logFile = JOSTUM_ROOT . '/error_log.txt';
    $message = "[" . date('Y-m-d H:i:s') . "] Uncaught Exception: " . $exception->getMessage() . 
               "\nFile: " . $exception->getFile() . " on line " . $exception->getLine() . 
               "\nStack trace:\n" . $exception->getTraceAsString() . "\n\n";
    @file_put_contents($logFile, $message, FILE_APPEND);
    
    // In case of fatal errors, let the user know an error occurred and was logged
    http_response_code(500);
    echo "<h1>500 Internal Server Error</h1>";
    echo "<p>A fatal error occurred. The details have been logged to <code>error_log.txt</code> in the server root.</p>";
    echo "<p><b>Error Details:</b> " . htmlspecialchars($exception->getMessage()) . " in " . htmlspecialchars($exception->getFile()) . " on line " . htmlspecialchars((string)$exception->getLine()) . "</p>";
    exit();
});

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $logFile = JOSTUM_ROOT . '/error_log.txt';
    $message = "[" . date('Y-m-d H:i:s') . "] PHP Error ($errno): $errstr\nFile: $errfile on line $errline\n\n";
    @file_put_contents($logFile, $message, FILE_APPEND);
    
    if ($errno === E_USER_ERROR || $errno === E_RECOVERABLE_ERROR) {
        http_response_code(500);
        echo "<h1>500 Internal Server Error</h1>";
        echo "<p>A fatal PHP error occurred. Details logged to <code>error_log.txt</code>.</p>";
        exit();
    }
    return false;
});


