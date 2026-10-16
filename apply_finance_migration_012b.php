<?php
require_once __DIR__ . '/app/config/database.php';

$sql = file_get_contents(
    __DIR__ . '/database/migrations/012b_financial_period_current_unique.sql'
);

try {
    $pdo->exec($sql);
    echo "SUCCESS: Migration 012b executed." . PHP_EOL;
} catch (PDOException $e) {
    echo "DATABASE ERROR: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
