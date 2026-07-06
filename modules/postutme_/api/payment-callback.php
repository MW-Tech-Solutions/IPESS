<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'POST required.'], 405);
}
$payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$reference = $payload['reference'] ?? $payload['rrr_reference'] ?? '';
$status = strtolower((string) ($payload['status'] ?? ''));
if ($reference === '') {
    json_response(['ok' => false, 'message' => 'Payment reference is required.'], 422);
}
$confirmed = in_array($status, ['success', 'successful', 'paid'], true);
$pdo = db();
$pdo->beginTransaction();
$pdo->prepare('UPDATE payments SET status = ?, paid_at = IF(? = "successful", NOW(), paid_at), gateway_response = ? WHERE reference = ? OR rrr_reference = ?')
    ->execute([$confirmed ? 'successful' : 'failed', $confirmed ? 'successful' : 'failed', json_encode($payload), $reference, $reference]);
$pdo->prepare('UPDATE invoices SET status = ?, paid_at = IF(? = "successful", NOW(), paid_at), gateway_response = ? WHERE rrr_reference = ?')
    ->execute([$confirmed ? 'successful' : 'failed', $confirmed ? 'successful' : 'failed', json_encode($payload), $reference]);
$pdo->commit();
json_response(['ok' => true]);
