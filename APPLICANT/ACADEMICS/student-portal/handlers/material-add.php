<?php
header('Content-Type: application/json');

$title = trim($_POST['title'] ?? '');
$type = trim($_POST['type'] ?? '');
$link = trim($_POST['link'] ?? '');

if ($title === '' || $type === '' || $link === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'All fields are required.']);
    exit;
}

$upload_base = dirname(__DIR__) . '/uploads/resources';
$file = $upload_base . '/materials.json';
$items = [];

if (file_exists($file)) {
    $items = json_decode(file_get_contents($file), true) ?? [];
}

$items[] = [
    'title' => $title,
    'type' => $type,
    'link' => $link,
    'shared_by' => 'PG Office',
    'shared_at' => date('Y-m-d'),
];

file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT));

echo json_encode(['ok' => true, 'message' => 'Material shared.']);
