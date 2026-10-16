<?php
require __DIR__ . '/app/config/database.php';

echo "=== RELATED FINANCE TABLES ===\n";

$tables = [
    'income_categories',
    'expense_categories',
    'bank_accounts',
    'cash_book'
];

foreach ($tables as $table) {
    echo "\n--- {$table} ---\n";

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        print_r($stmt->fetchAll());
    } catch (Throwable $e) {
        echo "TABLE NOT FOUND: " . $e->getMessage() . "\n";
    }
}

