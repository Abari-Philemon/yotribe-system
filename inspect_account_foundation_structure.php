<?php
require __DIR__ . '/app/config/database.php';

echo "=== account_types STRUCTURE ===\n";

$stmt = $pdo->query("DESCRIBE account_types");
$columns = $stmt->fetchAll();

foreach ($columns as $column) {
    echo $column['Field']
        . " | Type: " . $column['Type']
        . " | Null: " . $column['Null']
        . " | Key: " . $column['Key']
        . " | Default: " . ($column['Default'] ?? 'NULL')
        . "\n";
}

echo "\n=== account_classes STRUCTURE ===\n";

$stmt = $pdo->query("DESCRIBE account_classes");
$columns = $stmt->fetchAll();

foreach ($columns as $column) {
    echo $column['Field']
        . " | Type: " . $column['Type']
        . " | Null: " . $column['Null']
        . " | Key: " . $column['Key']
        . " | Default: " . ($column['Default'] ?? 'NULL')
        . "\n";
}
