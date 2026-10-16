<?php
require __DIR__ . '/app/config/database.php';

echo "=== LOAN-RELATED TABLES ===\n";

$stmt = $pdo->query("
    SELECT TABLE_NAME
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND (
          TABLE_NAME LIKE '%loan%'
          OR TABLE_NAME LIKE '%borrow%'
          OR TABLE_NAME LIKE '%liabil%'
      )
    ORDER BY TABLE_NAME
");

$tables = $stmt->fetchAll();

if (!$tables) {
    echo "NO LOAN/LIABILITY-RELATED TABLES FOUND\n";
    exit;
}

foreach ($tables as $table) {
    $tableName = $table['TABLE_NAME'];

    echo "\nTABLE: {$tableName}\n";

    $countStmt = $pdo->query("SELECT COUNT(*) FROM `{$tableName}`");
    echo "ROWS: " . $countStmt->fetchColumn() . "\n";

    echo "--- STRUCTURE ---\n";

    $structureStmt = $pdo->query("DESCRIBE `{$tableName}`");
    $columns = $structureStmt->fetchAll();

    foreach ($columns as $column) {
        echo $column['Field']
            . " | Type: " . $column['Type']
            . " | Null: " . $column['Null']
            . " | Key: " . $column['Key']
            . " | Default: " . ($column['Default'] ?? 'NULL')
            . "\n";
    }
}
