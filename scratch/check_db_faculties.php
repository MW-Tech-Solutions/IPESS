<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "=== FACULTIES ===\n";
    $stmt = $pdo->query("SELECT * FROM faculties");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\n=== DEPARTMENTS ===\n";
    $stmt = $pdo->query("SELECT * FROM departments");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\n=== DEGREE TYPES ===\n";
    $stmt = $pdo->query("SELECT * FROM degree_types");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\n=== COURSES ===\n";
    $stmt = $pdo->query("SELECT * FROM courses");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
