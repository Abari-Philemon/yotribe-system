<?php
require __DIR__ . '/app/config/database.php';

echo "=== COMPANY 1 ACCOUNTS ===\n";

$stmt = $pdo->query("
    SELECT
        id,
        account_code,
        system_key,
        account_name,
        account_type_id,
        account_class_id,
        parent_account_id,
        normal_balance,
        opening_balance,
        current_balance,
        allow_posting,
        is_control_account,
        is_cash_account,
        is_bank_account,
        is_system,
        is_active
    FROM accounts
    WHERE company_id = 1
    ORDER BY account_code
");

print_r($stmt->fetchAll());

