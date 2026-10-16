<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/config/database.php';

$tables = [
    'bank_accounts' => ['ledger_account_id'],
    'cash_book' => ['account_id'],
    'finance_posting_rules' => ['debit_account_id', 'credit_account_id'],
    'finance_settings' => ['default_cash_account_id'],
    'income_categories' => ['ledger_account_id'],
    'expense_categories' => ['ledger_account_id'],
    'journal_entry_lines' => ['account_id'],
    'ledger_balances' => ['account_id'],
];

$accountId = 1;
$companyId = 1;

foreach ($tables as $table => $columns) {
    foreach ($columns as $column) {
        $sql = "SELECT COUNT(*) FROM `$table` WHERE `$column` = :account_id";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['account_id' => $accountId]);
            printf("%s.%s: %d reference(s)\n", $table, $column, (int) $stmt->fetchColumn());
        } catch (Throwable $e) {
            printf("%s.%s: CHECK FAILED (%s)\n", $table, $column, $e->getMessage());
        }
    }
}