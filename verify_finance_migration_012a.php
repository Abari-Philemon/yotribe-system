<?php
require_once __DIR__ . '/app/config/database.php';

$sql = "
    SELECT
        COLUMN_NAME,
        COLUMN_TYPE,
        EXTRA
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'financial_years'
      AND COLUMN_NAME = 'current_company_id'
";

$stmt = $pdo->query($sql);
$column = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$column) {
    echo "FAIL: Generated column current_company_id was not found." . PHP_EOL;
    exit(1);
}

echo "COLUMN VERIFIED:" . PHP_EOL;
print_r($column);

$sql = "
    SELECT
        INDEX_NAME,
        NON_UNIQUE,
        COLUMN_NAME
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'financial_years'
      AND INDEX_NAME = 'uq_financial_year_current_company'
    ORDER BY SEQ_IN_INDEX
";

$index = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (!$index) {
    echo "FAIL: Unique index was not found." . PHP_EOL;
    exit(1);
}

echo "INDEX VERIFIED:" . PHP_EOL;
print_r($index);
