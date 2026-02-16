<?php
header('Content-Type: application/json');

$item_id = trim($_POST['item_id'] ?? '');
if ($item_id === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Checklist item is required.']);
    exit;
}

$upload_base = dirname(__DIR__) . '/uploads/progress';
$file = $upload_base . '/checklist.json';
$items = [];

if (file_exists($file)) {
    $items = json_decode(file_get_contents($file), true) ?? [];
}

$updated = false;
foreach ($items as &$item) {
    if (($item['id'] ?? '') === $item_id) {
        $item['status'] = $item['status'] === 'Completed' ? 'Pending' : 'Completed';
        $item['updated_at'] = date('Y-m-d H:i');
        $updated = true;
        break;
    }
}
unset($item);

if (!$updated) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Checklist item not found.']);
    exit;
}

file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT));

echo json_encode(['ok' => true, 'message' => 'Checklist updated.']);
