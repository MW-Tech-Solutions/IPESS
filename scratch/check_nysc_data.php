<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "=== NYSC DETAILS ===\n";
    $stmt = $pdo->query("SELECT * FROM nysc_details LIMIT 10");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
