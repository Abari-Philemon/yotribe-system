<?php
require __DIR__ . '/app/config/database.php';

echo "=== CURRENT ACCOUNTS FOR COMPANY 1 ===\n";

$stmt = $pdo->prepare("
    SELECT
        id,
        account_code,
        system_key,
        account_name,
        account_type_id,
        account_class_id,
        normal_balance,
        account_level,
        account_path,
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

$stmt->execute();
$accounts = $stmt->fetchAll();

if (!$accounts) {
    echo "NO ACCOUNTS FOUND FOR COMPANY 1\n";
    exit;
}

foreach ($accounts as $account) {
    echo "\n";
    echo "ID: " . $account['id'] . "\n";
    echo "Code: " . $account['account_code'] . "\n";
    echo "System Key: " . ($account['system_key'] ?? 'NULL') . "\n";
    echo "Name: " . $account['account_name'] . "\n";
    echo "Type ID: " . $account['account_type_id'] . "\n";
    echo "Class ID: " . $account['account_class_id'] . "\n";
    echo "Normal Balance: " . $account['normal_balance'] . "\n";
    echo "Level: " . $account['account_level'] . "\n";
    echo "Path: " . ($account['account_path'] ?? 'NULL') . "\n";
    echo "Allow Posting: " . $account['allow_posting'] . "\n";
    echo "Control Account: " . $account['is_control_account'] . "\n";
    echo "Cash Account: " . $account['is_cash_account'] . "\n";
    echo "Bank Account: " . $account['is_bank_account'] . "\n";
    echo "System Account: " . $account['is_system'] . "\n";
    echo "Active: " . $account['is_active'] . "\n";
}

echo "\n=== ACCOUNT TOTAL ===\n";
echo "Total: " . count($accounts) . "\n";
