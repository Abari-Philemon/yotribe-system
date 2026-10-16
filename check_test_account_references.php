<?php
require __DIR__ . '/app/config/database.php';

$accountId = 1;

echo "=== ACCOUNT 1 FINANCIAL REFERENCES ===\n";

$checks = [
    'journal_entry_lines' => "
        SELECT COUNT(*) AS total
        FROM journal_entry_lines jel
        INNER JOIN journal_entries je ON je.id = jel.journal_entry_id
        WHERE jel.account_id = {$accountId}
          AND je.company_id = 1
    ",
    'ledger_balances' => "
        SELECT COUNT(*) AS total
        FROM ledger_balances
        WHERE account_id = {$accountId}
          AND company_id = 1
    ",
    'cash_book' => "
        SELECT COUNT(*) AS total
        FROM cash_book
        WHERE account_id = {$accountId}
          AND company_id = 1
    ",
    'bank_accounts' => "
        SELECT COUNT(*) AS total
        FROM bank_accounts
        WHERE ledger_account_id = {$accountId}
          AND company_id = 1
    ",
    'income_categories' => "
        SELECT COUNT(*) AS total
        FROM income_categories
        WHERE ledger_account_id = {$accountId}
          AND company_id = 1
    ",
    'expense_categories' => "
        SELECT COUNT(*) AS total
        FROM expense_categories
        WHERE ledger_account_id = {$accountId}
          AND company_id = 1
    ",
    'posting_rules_debit' => "
        SELECT COUNT(*) AS total
        FROM finance_posting_rules
        WHERE debit_account_id = {$accountId}
          AND company_id = 1
    ",
    'posting_rules_credit' => "
        SELECT COUNT(*) AS total
        FROM finance_posting_rules
        WHERE credit_account_id = {$accountId}
          AND company_id = 1
    "
];

foreach ($checks as $name => $sql) {
    $stmt = $pdo->query($sql);
    $row = $stmt->fetch();
    echo $name . ': ' . ($row['total'] ?? 0) . "\n";
}

echo "\n=== JOURNAL ENTRIES ===\n";
$stmt = $pdo->query("
    SELECT COUNT(*) AS total
    FROM journal_entries
    WHERE company_id = 1
");
print_r($stmt->fetch());

