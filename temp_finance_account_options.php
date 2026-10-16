<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/app/config/database.php';

$db = new App\Core\Database($pdo);
$companyId = (new App\Modules\Finance\Services\CompanyContext($db))->resolve($_SESSION ?? []);

echo "Company ID: {$companyId}\n";

echo "\n=== Active cash ledger accounts ===\n";
$cash = $db->fetchAll(
    'SELECT id, account_code, account_name
     FROM accounts
     WHERE company_id = :company_id
       AND is_active = 1
       AND is_cash_account = 1
     ORDER BY account_code',
    ['company_id' => $companyId]
);
foreach ($cash as $row) {
    printf("%d | %s | %s\n", $row['id'], $row['account_code'], $row['account_name']);
}
if ($cash === []) echo "No active cash accounts found.\n";

echo "\n=== Active bank accounts ===\n";
$banks = $db->fetchAll(
    'SELECT id, bank_name, account_name, currency_code
     FROM bank_accounts
     WHERE company_id = :company_id
       AND is_active = 1
     ORDER BY bank_name, account_name',
    ['company_id' => $companyId]
);
foreach ($banks as $row) {
    printf("%d | %s | %s | %s\n", $row['id'], $row['bank_name'], $row['account_name'], $row['currency_code']);
}
if ($banks === []) echo "No active bank accounts found.\n";
