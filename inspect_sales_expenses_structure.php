<?php
require __DIR__ . '/app/config/database.php';

echo "=== EXPENSES STRUCTURE ===\n";

$stmt = $pdo->query("DESCRIBE expenses");
$columns = $stmt->fetchAll();

foreach ($columns as $column) {
    echo $column['Field']
        . " | Type: " . $column['Type']
        . " | Null: " . $column['Null']
        . " | Key: " . $column['Key']
        . " | Default: " . ($column['Default'] ?? 'NULL')
        . "\n";
}

echo "\n=== SALES STRUCTURE ===\n";

$stmt = $pdo->query("DESCRIBE sales");
$columns = $stmt->fetchAll();

foreach ($columns as $column) {
    echo $column['Field']
        . " | Type: " . $column['Type']
        . " | Null: " . $column['Null']
        . " | Key: " . $column['Key']
        . " | Default: " . ($column['Default'] ?? 'NULL')
        . "\n";
}
