<?php
require __DIR__ . '/app/config/database.php';

echo "=== COMPANY 1 INCOME CATEGORIES ===\n";
$stmt = $pdo->query("
    SELECT id, code, category_name, ledger_account_id, is_system, is_active
    FROM income_categories
    WHERE company_id = 1
    ORDER BY id
");
print_r($stmt->fetchAll());

echo "\n=== COMPANY 1 EXPENSE CATEGORIES ===\n";
$stmt = $pdo->query("
    SELECT id, code, category_name, ledger_account_id, requires_approval, is_system, is_active
    FROM expense_categories
    WHERE company_id = 1
    ORDER BY id
");
print_r($stmt->fetchAll());

echo "\n=== COMPANY 1 BANK ACCOUNTS ===\n";
$stmt = $pdo->query("
    SELECT id, ledger_account_id, bank_code, bank_name, account_name,
           account_number, currency_code, opening_balance, current_balance,
           is_default, is_active
    FROM bank_accounts
    WHERE company_id = 1
    ORDER BY id
");
print_r($stmt->fetchAll());

