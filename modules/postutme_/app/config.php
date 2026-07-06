<?php
declare(strict_types=1);

const APP_NAME = 'JOSTUM POST-UTME Online Screening Portal';
define('POSTUTME_MODULE_ROOT', dirname(__DIR__));
define('POSTUTME_JOSTUM_ROOT', dirname(__DIR__, 3));

const APP_URL = 'http://localhost/JOSTUM/modules/postutme';
define('DB_HOST', getenv('POSTUTME_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('POSTUTME_DB_NAME') ?: 'postutme_jostum');
define('DB_USER', getenv('POSTUTME_DB_USER') ?: 'root');
define('DB_PASS', getenv('POSTUTME_DB_PASS') ?: '');
define('DB_CHARSET', getenv('POSTUTME_DB_CHARSET') ?: 'utf8mb4');

define('STORAGE_PATH', POSTUTME_MODULE_ROOT . '/storage');
define('UPLOAD_PATH', STORAGE_PATH . '/uploads');
define('JAMB_IMPORT_PATH', STORAGE_PATH . '/jamb_imports');

const DEFAULT_ADMISSION_YEAR = '2025/2026';
