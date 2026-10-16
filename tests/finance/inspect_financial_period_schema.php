<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config/database.php';

foreach (['financial_years', 'financial_periods'] as $table) {
    echo "\n--- {$table} ---\n";

    $statement = $pdo->query("SHOW COLUMNS FROM `{$table}`");

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $column) {
        echo $column['Field']
            . ' | ' . $column['Type']
            . ' | NULL=' . $column['Null']
            . ' | DEFAULT=' . var_export($column['Default'], true)
            . PHP_EOL;
    }
}