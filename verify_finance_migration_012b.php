<?php
require_once __DIR__ . '/app/config/database.php';

$columnSql = "
    SELECT COLUMN_NAME, COLUMN_TYPE, EXTRA
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'financial_periods'
      AND COLUMN_NAME = 'current_financial_year_id'
";

$column = $pdo->query($columnSql)->fetch(PDO::FETCH_ASSOC);

if (!$column) {
    echo "FAIL: Generated column not found." . PHP_EOL;
    exit(1);
}

echo "COLUMN VERIFIED:" . PHP_EOL;
print_r($column);

$indexSql = "
    SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SEQ_IN_INDEX
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'financial_periods'
      AND INDEX_NAME = 'uq_financial_period_current_year'
    ORDER BY SEQ_IN_INDEX
";

$index = $pdo->query($indexSql)->fetchAll(PDO::FETCH_ASSOC);

if (count($index) !== 2) {
    echo "FAIL: Expected both columns in the unique index." . PHP_EOL;
    print_r($index);
    exit(1);
}

if ((int) $index[0]['NON_UNIQUE'] !== 0 ||
    $index[0]['COLUMN_NAME'] !== 'company_id' ||
    $index[1]['COLUMN_NAME'] !== 'current_financial_year_id') {
    echo "FAIL: Unique index definition is incorrect." . PHP_EOL;
    print_r($index);
    exit(1);
}

echo "UNIQUE INDEX VERIFIED:" . PHP_EOL;
print_r($index);
