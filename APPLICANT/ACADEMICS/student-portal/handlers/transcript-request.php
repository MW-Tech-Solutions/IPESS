<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/mailer.php';

$type = trim($_POST['type'] ?? '');
$reason = trim($_POST['reason'] ?? '');

if ($type === '' || $reason === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Transcript type and reason are required.']);
    exit;
}

$upload_base = dirname(__DIR__) . '/uploads/academics';
$file = $upload_base . '/transcript-requests.json';
$items = [];

if (file_exists($file)) {
    $items = json_decode(file_get_contents($file), true) ?? [];
}

$reference = 'TRX-' . random_int(1000, 9999);
$items[] = [
    'type' => $type,
    'status' => 'Processing',
    'requested_at' => date('Y-m-d'),
    'reference' => $reference,
    'reason' => $reason,
];

file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT));

$mail_result = send_portal_mail(
    getenv('PORTAL_TEST_RECIPIENT') ?: 'student@example.com',
    'Postgraduate Student',
    'Transcript Request Received',
    "<p>Your transcript request ({$type}) has been received. Reference: <strong>{$reference}</strong>.</p><p>Reason: {$reason}</p>"
);

echo json_encode(['ok' => true, 'message' => 'Transcript request submitted.', 'reference' => $reference, 'mail' => $mail_result['message']]);
