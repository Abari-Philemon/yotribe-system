<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/app/config/database.php';

use App\Core\Database;
use App\Modules\Finance\Repositories\AccountRepository;
use App\Modules\Finance\Repositories\FinancialPeriodRepository;
use App\Modules\Finance\Repositories\FinancialYearRepository;
use App\Modules\Finance\Repositories\FinanceSettingsRepository;
use App\Modules\Finance\Repositories\JournalEntryRepository;
use App\Modules\Finance\Requests\JournalEntryRequest;
use App\Modules\Finance\Services\JournalEntryService;

$companyId = 1;
$invalidFarmId = 999999;
$actorId = 1;

if ($pdo->inTransaction()) {
    exit("ABORTED: PDO transaction already active; nothing changed.\n");
}

$db = new Database($pdo);

$before = [
    'journals' => (int) $pdo->query(
        "SELECT COUNT(*) FROM journal_entries WHERE company_id = 1"
    )->fetchColumn(),
    'lines' => (int) $pdo->query(
        "SELECT COUNT(*) FROM journal_entry_lines"
    )->fetchColumn(),
    'sequence' => (int) $pdo->query(
        "SELECT COALESCE(MAX(last_number), 0)
         FROM finance_document_sequences
         WHERE company_id = 1 AND document_type_id = 1
           AND financial_year = 2026 AND is_active = 1"
    )->fetchColumn(),
];

try {
    $service = new JournalEntryService(
        $db,
        new JournalEntryRepository($db),
        new AccountRepository($db),
        new FinancialYearRepository($db),
        new FinancialPeriodRepository($db),
        new FinanceSettingsRepository($db),
        new JournalEntryRequest()
    );

    try {
        $service->createForCompany(
            $companyId,
            [
                'farm_id' => $invalidFarmId,
                'journal_date' => '2026-10-09',
                'transaction_code' => 'TEST',
                'source_module' => 'finance_test',
                'narration' => 'Invalid farm scope test',
                'lines' => [
                    ['account_id' => 12, 'entry_type' => 'DEBIT', 'amount' => '1.00'],
                    ['account_id' => 20, 'entry_type' => 'CREDIT', 'amount' => '1.00'],
                ],
            ],
            $actorId
        );

        throw new RuntimeException('FAIL: Journal creation accepted a nonexistent farm.');
    } catch (RuntimeException $e) {
        if (str_contains($e->getMessage(), 'The farm is inactive or does not belong to this company.')) {
            echo "PASS: Journal rejected for a nonexistent farm.\n";
        } else {
            throw $e;
        }
    }

    $after = [
        'journals' => (int) $pdo->query(
            "SELECT COUNT(*) FROM journal_entries WHERE company_id = 1"
        )->fetchColumn(),
        'lines' => (int) $pdo->query(
            "SELECT COUNT(*) FROM journal_entry_lines"
        )->fetchColumn(),
        'sequence' => (int) $pdo->query(
            "SELECT COALESCE(MAX(last_number), 0)
             FROM finance_document_sequences
             WHERE company_id = 1 AND document_type_id = 1
               AND financial_year = 2026 AND is_active = 1"
        )->fetchColumn(),
    ];

    if ($before !== $after) {
        throw new RuntimeException(
            'FAIL: Database counts or sequence changed. Before='
            . json_encode($before) . ' After=' . json_encode($after)
        );
    }

    echo "PASS: Journal count, line count and document sequence remain unchanged.\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "TEST ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
