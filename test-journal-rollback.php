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
use Throwable;

$companyId = 1;
$farmId = 1;
$actorId = 1;
$journalDate = '2026-10-09';
$rolledBack = false;

if ($pdo->inTransaction()) {
    exit("ABORTED: PDO transaction already active; nothing changed.\n");
}

$db = new Database($pdo);

function baseline(PDO $pdo, int $companyId): array
{
    $result = [];

    $result['journals'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM journal_entries WHERE company_id = {$companyId}"
    )->fetchColumn();

    $result['lines'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM journal_entry_lines"
    )->fetchColumn();

    $result['sequence'] = (int) $pdo->query(
        "SELECT COALESCE(MAX(last_number), 0)
         FROM finance_document_sequences
         WHERE company_id = {$companyId}
           AND document_type_id = 1
           AND financial_year = 2026
           AND is_active = 1"
    )->fetchColumn();

    $result['balances'] = $pdo->query(
        "SELECT id, current_balance
         FROM accounts
         WHERE company_id = {$companyId}
         ORDER BY id"
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    return $result;
}

$before = baseline($pdo, $companyId);
$db->beginTransaction();

try {
    $accounts = new AccountRepository($db);
    $years = new FinancialYearRepository($db);
    $periods = new FinancialPeriodRepository($db);
    $settings = new FinanceSettingsRepository($db);
    $journals = new JournalEntryRepository($db);
    $request = new JournalEntryRequest();

    $service = new JournalEntryService(
        $db,
        $journals,
        $accounts,
        $years,
        $periods,
        $settings,
        $request
    );

    $result = $service->createForCompany(
        $companyId,
        [
            'farm_id' => $farmId,
            'journal_date' => $journalDate,
            'transaction_code' => 'TEST',
            'source_module' => 'finance_test',
            'reference_no' => 'ROLLBACK-TEST-20261009',
            'narration' => 'ROLLBACK TEST ONLY - no business transaction',
            'lines' => [
                [
                    'account_id' => 12,
                    'entry_type' => 'DEBIT',
                    'amount' => '1.00',
                    'description' => 'Rollback test only',
                ],
                [
                    'account_id' => 20,
                    'entry_type' => 'CREDIT',
                    'amount' => '1.00',
                    'description' => 'Rollback test only',
                ],
            ],
        ],
        $actorId
    );

    $journal = $result['journal'];
    $lines = $result['lines'];

    if (strtoupper((string) ($journal['status'] ?? '')) !== 'DRAFT') {
        throw new RuntimeException('Unexpected journal status; expected DRAFT.');
    }

    if (count($lines) !== 2) {
        throw new RuntimeException('Expected exactly two journal lines.');
    }

    if (
        number_format((float) ($journal['total_debit'] ?? 0), 2, '.', '')
        !== '1.00'
        || number_format((float) ($journal['total_credit'] ?? 0), 2, '.', '')
        !== '1.00'
    ) {
        throw new RuntimeException('Journal debit/credit totals are incorrect.');
    }

    echo "Journal created inside test transaction:\n";
    echo "Journal no: " . ($journal['journal_no'] ?? 'NOT RETURNED') . "\n";
    echo "Status: " . ($journal['status'] ?? 'NOT RETURNED') . "\n";
    echo "Debit: " . ($journal['total_debit'] ?? 'NOT RETURNED') . "\n";
    echo "Credit: " . ($journal['total_credit'] ?? 'NOT RETURNED') . "\n";
    echo "Lines:\n";

    foreach ($lines as $line) {
        echo sprintf(
            "  %s | %s | %s | %s\n",
            $line['entry_type'] ?? '?',
            $line['account_code'] ?? ($line['account_id'] ?? '?'),
            $line['account_name'] ?? '',
            $line['amount'] ?? '?'
        );
    }

    $db->rollback();
    $rolledBack = true;
    echo "Test transaction rolled back.\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollback();
        $rolledBack = true;
    }

    echo "TEST ERROR: " . $e->getMessage() . "\n";
}

if (!$rolledBack) {
    exit("SAFETY ERROR: rollback could not be confirmed.\n");
}

$after = baseline($pdo, $companyId);

if ($before === $after) {
    echo "PASS: journal counts, line counts, sequence and account balances match baseline.\n";
} else {
    echo "FAIL: post-rollback state differs from baseline.\n";
    echo "Before: " . json_encode($before) . "\n";
    echo "After:  " . json_encode($after) . "\n";
    exit(1);
}
