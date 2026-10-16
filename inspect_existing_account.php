<?php
require __DIR__ . '/app/config/database.php';

$stmt = $pdo->query("
    SELECT
        id,
        uuid,
        company_id,
        account_code,
        system_key,
        account_name,
        description,
        opening_balance,
        current_balance,
        allow_posting,
        is_control_account,
        is_cash_account,
        is_bank_account,
        is_system,
        is_active,
        created_by,
        updated_by,
        created_at,
        updated_at
    FROM accounts
    WHERE id = 1
      AND company_id = 1
");

print_r($stmt->fetch());

