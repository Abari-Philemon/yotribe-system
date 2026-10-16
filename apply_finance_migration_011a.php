<?php
require_once __DIR__ . '/app/config/database.php';

$sql = file_get_contents(
    __DIR__ . '/database/migrations/011a_financial_year_company_constraint.sql'
);

if ($sql === false || trim($sql) === '') {
    throw new RuntimeException('Migration file is missing or empty.');
}

$pdo->exec($sql);
echo "Migration 011a executed successfully." . PHP_EOL;
