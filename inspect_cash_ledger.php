<?php
require __DIR__ . '/app/config/database.php';

echo "=== CASH_LEDGER STRUCTURE ===\n";

$stmt = $pdo->query("DESCRIBE cash_ledger");
$columns = $stmt->fetchAll();

foreach ($columns as $column) {
    echo $column['Field']
        . " | Type: " . $column['Type']
        . " | Null: " . $column['Null']
        . " | Key: " . $column['Key']
        . " | Default: " . ($column['Default'] ?? 'NULL')
        . "\n";
}

echo "\n=== CASH_LEDGER RECORDS ===\n";

$stmt = $pdo->query("SELECT * FROM cash_ledger ORDER BY id");
$rows = $stmt->fetchAll();

foreach ($rows as $row) {
    echo "\n--- RECORD ---\n";

    foreach ($row as $column => $value) {
        if (!is_numeric($column)) {
            echo $column . ': ' . ($value ?? 'NULL') . "\n";
        }
    }
}

echo "\n=== TOTAL RECORDS ===\n";
echo count($rows) . "\n";
