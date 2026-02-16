<?php
header('Content-Type: application/json');

$semester = trim($_POST['semester'] ?? '');
$code = trim($_POST['code'] ?? '');
$title = trim($_POST['title'] ?? '');
$credits = isset($_POST['credits']) ? (int) $_POST['credits'] : 0;

if ($semester === '' || $code === '' || $title === '' || $credits <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'All fields are required.']);
    exit;
}

$upload_base = dirname(__DIR__) . '/uploads/progress';
$file = $upload_base . '/credits.json';
$items = [];

if (file_exists($file)) {
    $items = json_decode(file_get_contents($file), true) ?? [];
}

$items[] = [
    'semester' => $semester,
    'code' => strtoupper($code),
    'title' => $title,
    'credits' => $credits,
    'status' => 'Pending',
];

file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT));

echo json_encode(['ok' => true, 'message' => 'Credit record added.']);
