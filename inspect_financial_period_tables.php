<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/database.php';

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    if (!preg_match('/financial|fiscal|period|journal|finance/i', $table)) {
        continue;
    }

    echo "\n=== {$table} ===\n";

    $safeTable = str_replace('`', '``', $table);
    $columns = $pdo->query("SHOW COLUMNS FROM `{$safeTable}`")
        ->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $column) {
        echo $column['Field'] . ' | '
            . $column['Type'] . ' | '
            . $column['Null'] . ' | '
            . $column['Key'] . "\n";
    }
}