<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/app/config/database.php';

use App\Core\Database;
use App\Modules\Finance\Repositories\AccountRepository;
use App\Modules\Finance\Repositories\FinancialYearRepository;
use App\Modules\Finance\Repositories\FinancialPeriodRepository;
use App\Modules\Finance\Repositories\FinanceSettingsRepository;
use App\Modules\Finance\Repositories\JournalEntryRepository;
use App\Modules\Finance\Requests\JournalEntryRequest;
use App\Modules\Finance\Services\JournalEntryService;

$db = new Database($pdo);

$service = new JournalEntryService(
    $db,
    new JournalEntryRepository($db),
    new AccountRepository($db),
    new FinancialYearRepository($db),
    new FinancialPeriodRepository($db),
    new FinanceSettingsRepository($db),
    new JournalEntryRequest()
);

$companyId = 1;
$yearId = 1;
$periodId = 10;

$input = [
    'farm_id' => 1,
    'journal_date' => '2026-10-09',
    'transaction_code' => 'TEST',
    'source_module' => 'finance_test',
    'narration' => 'Temporary closed-period validation test',
    'lines' => [
        ['account_id' => 12, 'entry_type' => 'DEBIT', 'amount' => '1.00'],
        ['account_id' => 20, 'entry_type' => 'CREDIT', 'amount' => '1.00'],
    ],
];

try {
    $pdo->beginTransaction();

    $pdo->prepare(
        "UPDATE financial_periods SET status = 'CLOSED'
         WHERE id = :id AND company_id = :company_id AND financial_year_id = :year_id"
    )->execute([
        'id' => $periodId,
        'company_id' => $companyId,
        'year_id' => $yearId,
    ]);

    try {
        $service->createForCompany($companyId, $input, 1);
        throw new RuntimeException('FAIL: Journal creation was allowed in a closed period.');
    } catch (RuntimeException $e) {
        if (str_contains($e->getMessage(), 'No matching open financial period')) {
            echo "PASS: Journal rejected when financial period is CLOSED.\n";
        } else {
            throw $e;
        }
    }

    $pdo->prepare(
        "UPDATE financial_periods SET status = 'OPEN'
         WHERE id = :id AND company_id = :company_id AND financial_year_id = :year_id"
    )->execute([
        'id' => $periodId,
        'company_id' => $companyId,
        'year_id' => $yearId,
    ]);

    $pdo->prepare(
        "UPDATE financial_years SET status = 'CLOSED'
         WHERE id = :id AND company_id = :company_id"
    )->execute([
        'id' => $yearId,
        'company_id' => $companyId,
    ]);

    try {
        $service->createForCompany($companyId, $input, 1);
        throw new RuntimeException('FAIL: Journal creation was allowed in a closed financial year.');
    } catch (RuntimeException $e) {
        if (str_contains($e->getMessage(), 'No open financial year covers')) {
            echo "PASS: Journal rejected when financial year is CLOSED.\n";
        } else {
            throw $e;
        }
    }

    $pdo->rollBack();
    echo "PASS: Test transaction rolled back; temporary status changes were not saved.\n";
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "TEST ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
