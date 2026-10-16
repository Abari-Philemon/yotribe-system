<?php
require __DIR__ . '/app/config/database.php';

echo "=== TABLES RELATED TO ACCOUNT / COA DEFINITIONS ===\n";

$stmt = $pdo->query("
    SELECT
        TABLE_NAME
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND (
          TABLE_NAME LIKE '%account%'
          OR TABLE_NAME LIKE '%coa%'
          OR TABLE_NAME LIKE '%ledger%'
          OR TABLE_NAME LIKE '%category%'
      )
    ORDER BY TABLE_NAME
");

$tables = $stmt->fetchAll();

foreach ($tables as $table) {
    echo $table['TABLE_NAME'] . "\n";
}

echo "\n=== ACCOUNT-RELATED TABLE ROW COUNTS ===\n";

foreach ($tables as $table) {
    $tableName = $table['TABLE_NAME'];

    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM `{$tableName}`"
    );

    echo $tableName . ': ' . $stmt->fetchColumn() . "\n";
}
