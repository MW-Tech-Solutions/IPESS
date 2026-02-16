<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/mailer.php';

$amount = isset($_POST['amount']) ? (int) $_POST['amount'] : 0;
$method = trim($_POST['method'] ?? '');

if ($amount <= 0 || $method === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Amount and payment method are required.']);
    exit;
}

$upload_base = dirname(__DIR__) . '/uploads/fees';
$file = $upload_base . '/payments.json';
$items = [];

if (file_exists($file)) {
    $items = json_decode(file_get_contents($file), true) ?? [];
}

$reference = 'PAY-' . random_int(1000, 9999);
$items[] = [
    'date' => date('Y-m-d'),
    'method' => $method,
    'amount' => $amount,
    'reference' => $reference,
    'status' => 'Completed',
];

file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT));

$mail_result = send_portal_mail(
    getenv('PORTAL_TEST_RECIPIENT') ?: 'student@example.com',
    'Postgraduate Student',
    'Payment Receipt',
    "<p>Your payment has been recorded.</p><p><strong>Amount:</strong> NGN " . number_format($amount) . "<br><strong>Method:</strong> {$method}<br><strong>Reference:</strong> {$reference}</p>"
);

echo json_encode(['ok' => true, 'message' => 'Payment recorded.', 'reference' => $reference, 'mail' => $mail_result['message']]);
