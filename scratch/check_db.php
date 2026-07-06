<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "=== PROGRAMME CHOICES ===\n";
    $stmt = $pdo->query("SELECT * FROM programme_choices LIMIT 5");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\n=== DEGREE TYPES ===\n";
    $stmt = $pdo->query("SELECT * FROM degree_types LIMIT 5");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\n=== COURSES ===\n";
    $stmt = $pdo->query("SELECT * FROM courses LIMIT 5");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\n=== DEPARTMENTS ===\n";
    $stmt = $pdo->query("SELECT * FROM departments LIMIT 5");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\n=== STUDY MODES ===\n";
    $stmt = $pdo->query("SELECT * FROM study_modes LIMIT 5");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
