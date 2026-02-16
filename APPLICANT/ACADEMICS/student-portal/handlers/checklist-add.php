<?php
header('Content-Type: application/json');

$task = trim($_POST['task'] ?? '');
if ($task === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Task is required.']);
    exit;
}

$upload_base = dirname(__DIR__) . '/uploads/progress';
$file = $upload_base . '/checklist.json';
$items = [];

if (file_exists($file)) {
    $items = json_decode(file_get_contents($file), true) ?? [];
}

$items[] = [
    'id' => 'chk-' . random_int(1000, 9999),
    'task' => $task,
    'status' => 'Pending',
];

file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT));

echo json_encode(['ok' => true, 'message' => 'Checklist item added.']);
