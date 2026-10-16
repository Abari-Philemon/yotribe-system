<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Core\Database;
use App\Modules\Finance\Repositories\FinanceSettingsRepository;
use App\Modules\Finance\Services\FinanceSettingsService;

function check(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("FAIL: " . $message);
    }
    echo "PASS: " . $message . PHP_EOL;
}

function expectInvalid(callable $callback, string $message): void {
    try {
        $callback();
    } catch (InvalidArgumentException) {
        echo "PASS: " . $message . PHP_EOL;
        return;
    }
    throw new RuntimeException("FAIL: " . $message);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("
    CREATE TABLE finance_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid CHAR(36) NOT NULL UNIQUE,
        company_id INTEGER NOT NULL UNIQUE,
        default_currency CHAR(3) NOT NULL DEFAULT 'NGN',
        currency_symbol VARCHAR(10) NOT NULL DEFAULT '₦',
        decimal_places INTEGER NOT NULL DEFAULT 2,
        financial_year_start_month INTEGER NOT NULL DEFAULT 1,
        financial_year_start_day INTEGER NOT NULL DEFAULT 1,
        default_tax_rate NUMERIC NOT NULL DEFAULT 0,
        allow_backdated_entries INTEGER NOT NULL DEFAULT 0,
        allow_negative_cash INTEGER NOT NULL DEFAULT 0,
        auto_post_journals INTEGER NOT NULL DEFAULT 1,
        default_cash_account_id INTEGER NULL,
        default_bank_account_id INTEGER NULL,
        created_by INTEGER NULL,
        updated_by INTEGER NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )
");

$db = new Database($pdo);
$repository = new FinanceSettingsRepository($db);
$service = new FinanceSettingsService($repository);

expectInvalid(
    fn() => $service->getByCompanyId(0),
    'Rejects an invalid company ID'
);

expectInvalid(
    fn() => $service->createForCompany(7, ['company_id' => 8]),
    'Rejects caller-supplied company_id'
);

expectInvalid(
    fn() => $service->createForCompany(7, ['created_by' => 15]),
    'Rejects caller-supplied created_by'
);

expectInvalid(
    fn() => $service->createForCompany(7, [], 0),
    'Rejects an invalid actor ID'
);

$id = $service->createForCompany(7, [
    'default_currency' => 'NGN',
    'currency_symbol' => '₦',
    'decimal_places' => 2,
], 15);

$row = $service->getByCompanyId(7);

check($id > 0, 'Creates finance settings and returns an ID');
check($row !== null, 'Retrieves settings by company ID');
check((int)$row['company_id'] === 7, 'Associates settings with the requested company');
check($row['created_by'] == 15 && $row['updated_by'] == 15, 'Records the trusted actor on creation');
check(strlen((string)$row['uuid']) === 36, 'Generates a UUID');
check($row['default_currency'] === 'NGN', 'Persists the currency setting');

expectInvalid(
    fn() => $service->createForCompany(7, []),
    'Rejects duplicate settings for the same company'
);

$updated = $service->updateForCompany(7, [
    'default_currency' => 'USD',
], 15);

$row = $service->getByCompanyId(7);

check($updated, 'Updates existing company settings');
check($row['default_currency'] === 'USD', 'Persists the updated currency');
check((int)$row['updated_by'] === 15, 'Records the trusted actor on update');
check(
    $service->updateForCompany(999, ['default_currency' => 'EUR']) === false,
    'Returns false when updating a company without settings'
);

expectInvalid(
    fn() => $service->updateForCompany(7, ['created_by' => 99]),
    'Rejects caller-supplied created_by during update'
);

echo PHP_EOL . "All FinanceSettingsService checks passed." . PHP_EOL;