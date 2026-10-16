<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/config/database.php';

$tables = [
    'accounts',
    'bank_accounts',
    'cash_book',
    'finance_posting_rules',
    'finance_settings',
    'income_categories',
    'expense_categories',
    'journal_entry_lines',
    'ledger_balances',
];

foreach ($tables as $table) {
    echo "\n--- {$table} ---\n";

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            printf(
                "%s | %s | %s\n",
                $column['Field'],
                $column['Type'],
                $column['Null']
            );
        }
    } catch (Throwable $e) {
        printf("CHECK FAILED: %s\n", $e->getMessage());
    }
}