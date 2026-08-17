<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/config/database.php';
$pdo = db();

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    try {
        $cols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($cols as $col) {
            $stmt = $pdo->prepare("SELECT `$col` FROM `$table` WHERE `$col` LIKE '%C:/%' OR `$col` LIKE '%htdocs%' LIMIT 10");
            $stmt->execute();
            $matches = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($matches)) {
                echo "Match found in Table: $table, Column: $col\n";
                foreach ($matches as $m) {
                    echo "  -> " . substr($m, 0, 100) . "\n";
                }
            }
        }
    } catch (Throwable $e) {}
}
?>
