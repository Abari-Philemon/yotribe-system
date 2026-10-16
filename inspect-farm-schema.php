<?php
require __DIR__ . '/app/config/database.php';

foreach (['farms', 'company_farms'] as $table) {
    echo PHP_EOL . "=== {$table} ===" . PHP_EOL;
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        echo $column['Field'] . ' | ' . $column['Type'] . ' | '
            . $column['Null'] . ' | ' . ($column['Default'] ?? 'NULL') . PHP_EOL;
    }
}
