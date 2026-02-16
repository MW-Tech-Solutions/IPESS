<?php

require __DIR__ . '../config/db.php'; 

try {
    $retention_hours = 24;

    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < (NOW() - INTERVAL ? HOUR)");
    $stmt->execute([$retention_hours]);

    $deleted_count = $stmt->rowCount();
    
    echo "[" . date('Y-m-d H:i:s') . "] Cleanup successful. Deleted $deleted_count old records.\n";

} catch (PDOException $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error cleaning login logs: " . $e->getMessage() . "\n";
}
?>