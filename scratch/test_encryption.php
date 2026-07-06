<?php
require_once __DIR__ . '/../config/urls.php';

$appNo = 'APP/IPESS/2026/0020';
$encrypted = encrypt_app_number($appNo);
$decrypted = decrypt_app_number($encrypted);

echo "Original:  $appNo\n";
echo "Encrypted: $encrypted\n";
echo "Decrypted: $decrypted\n";

if ($appNo === $decrypted) {
    echo "SUCCESS: Encryption and Decryption match perfectly!\n";
} else {
    echo "ERROR: Decrypted value does not match original!\n";
}
