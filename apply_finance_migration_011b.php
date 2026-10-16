<?php
require_once __DIR__ . '/app/config/database.php';

$path = __DIR__ . '/database/migrations/011b_financial_period_company_year_constraint.sql';
$sql = file_get_contents($path);

if ($sql === false || trim($sql) === '') {
    throw new RuntimeException('Migration file is missing or empty.');
}

$pdo->exec($sql);
echo "Migration 011b executed successfully." . PHP_EOL;
