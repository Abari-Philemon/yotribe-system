<?php
require __DIR__ . '/app/config/database.php';

echo "=== EXISTING POSTING RULES ===\n";
$stmt = $pdo->query("
    SELECT id, company_id, transaction_code, transaction_name,
           debit_account_id, credit_account_id, auto_post, is_active
    FROM finance_posting_rules
    WHERE company_id = 1
    ORDER BY id
");
print_r($stmt->fetchAll());

echo "\n=== COMPANY ACCOUNTS ===\n";
$stmt = $pdo->query("
    SELECT id, code, name, normal_balance, is_posting, is_cash_bank, is_active
    FROM finance_accounts
    WHERE company_id = 1
    ORDER BY code
");
print_r($stmt->fetchAll());

echo "\n=== DOCUMENT TYPES ===\n";
$stmt = $pdo->query("
    SELECT id, company_id, code, name, prefix, is_active
    FROM finance_document_types
    WHERE company_id = 1
    ORDER BY id
");
print_r($stmt->fetchAll());
