<?php
header('Content-Type: application/json');

$title = trim($_POST['title'] ?? '');
$authors = trim($_POST['authors'] ?? '');
$year = trim($_POST['year'] ?? '');
$link = trim($_POST['link'] ?? '');

if ($title === '' || $authors === '' || $year === '' || $link === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'All fields are required.']);
    exit;
}

$upload_base = dirname(__DIR__) . '/uploads/resources';
$file = $upload_base . '/publications.json';
$items = [];

if (file_exists($file)) {
    $items = json_decode(file_get_contents($file), true) ?? [];
}

$items[] = [
    'title' => $title,
    'authors' => $authors,
    'year' => $year,
    'link' => $link,
];

file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT));

echo json_encode(['ok' => true, 'message' => 'Publication added.']);
