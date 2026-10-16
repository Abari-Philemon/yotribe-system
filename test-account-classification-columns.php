<?php
require __DIR__ . '/app/config/database.php';

foreach (['account_types', 'account_classes'] as $table) {
    echo "\n--- {$table} columns ---\n";

    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        echo $column['Field'] . ' | ' . $column['Type'] . "\n";
    }
}
