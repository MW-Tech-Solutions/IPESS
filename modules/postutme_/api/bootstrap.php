<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json');

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}
