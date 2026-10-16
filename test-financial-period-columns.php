<?php
require __DIR__ . '/app/config/database.php';

foreach (['financial_years', 'financial_periods'] as $table) {
    echo PHP_EOL . "=== {$table} ===" . PHP_EOL;
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        echo $column['Field'] . ' | ' . $column['Type'] . PHP_EOL;
    }
}
