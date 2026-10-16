<?php
declare(strict_types=1);
require __DIR__ . '/app/config/database.php';

foreach (['finance_settings', 'accounts', 'bank_accounts'] as $table) {
    echo "\n--- {$table} ---\n";
    $statement = $pdo->query("SHOW COLUMNS FROM `$table`");
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $column) {
        printf(
            "%s | %s | NULL=%s | DEFAULT=%s\n",
            $column['Field'],
            $column['Type'],
            $column['Null'],
            var_export($column['Default'], true)
        );
    }
}
