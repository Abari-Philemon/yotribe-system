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
$failure = null;

if ($pdo->inTransaction()) {
    exit("ABORTED: PDO transaction already active; nothing changed.\n");
}

function postingBaseline(PDO $pdo, int $companyId): array
{
    $result = [];

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM journal_entries WHERE company_id = :company_id'
    );
    $stmt->execute(['company_id' => $companyId]);
    $result['journals'] = (int) $stmt->fetchColumn();

    $result['lines'] = (int) $pdo->query(
        'SELECT COUNT(*) FROM journal_entry_lines'
    )->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COALESCE(MAX(last_number), 0)
         FROM finance_document_sequences
         WHERE company_id = :company_id
           AND document_type_id = 1
           AND financial_year = 2026
           AND is_active = 1'
    );
    $stmt->execute(['company_id' => $companyId]);
    $result['sequence'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT id, current_balance
         FROM accounts
         WHERE company_id = :company_id
           AND id IN (12, 20)
         ORDER BY id'
    );
    $stmt->execute(['company_id' => $companyId]);
    $result['balances'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    return $result;
}

$before = postingBaseline($pdo, $companyId);
$db = new Database($pdo);

try {
    $db->beginTransaction();

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

    $created = $service->createForCompany(
        $companyId,
        [
            'farm_id' => $farmId,
            'journal_date' => $journalDate,
            'transaction_code' => 'TEST',
            'source_module' => 'finance_test',
            'reference_no' => 'POST-ROLLBACK-TEST-20261009',
            'narration' => 'POSTING TEST ONLY - rollback required',
            'lines' => [
                [
                    'account_id' => 12,
                    'entry_type' => 'DEBIT',
                    'amount' => '1.00',
                    'description' => 'Temporary posting test',
                ],
                [
                    'account_id' => 20,
                    'entry_type' => 'CREDIT',
                    'amount' => '1.00',
                    'description' => 'Temporary posting test',
                ],
            ],
        ],
        $actorId
    );

    $journalId = (int) $created['journal']['id'];

    if (strtoupper((string) $created['journal']['status']) !== 'DRAFT') {
        throw new RuntimeException('New journal was not created as DRAFT.');
    }

    $beforeCash = (string) $pdo->query(
        'SELECT current_balance FROM accounts WHERE company_id = 1 AND id = 12'
    )->fetchColumn();

    $beforeCapital = (string) $pdo->query(
        'SELECT current_balance FROM accounts WHERE company_id = 1 AND id = 20'
    )->fetchColumn();

    $posted = $service->postForCompany($companyId, $journalId, $actorId);

    if (strtoupper((string) $posted['journal']['status']) !== 'POSTED') {
        throw new RuntimeException('Posting did not return POSTED status.');
    }

    echo "PASS: Draft journal posted successfully inside the test transaction.\n";

    $afterCash = (string) $pdo->query(
        'SELECT current_balance FROM accounts WHERE company_id = 1 AND id = 12'
    )->fetchColumn();

    $afterCapital = (string) $pdo->query(
        'SELECT current_balance FROM accounts WHERE company_id = 1 AND id = 20'
    )->fetchColumn();

    if (bccomp($afterCash, bcadd($beforeCash, '1.00', 2), 2) !== 0) {
        throw new RuntimeException(
            "Cash balance movement is incorrect: before=$beforeCash after=$afterCash"
        );
    }

    if (bccomp($afterCapital, bcadd($beforeCapital, '1.00', 2), 2) !== 0) {
        throw new RuntimeException(
            "Capital balance movement is incorrect: before=$beforeCapital after=$afterCapital"
        );
    }

    echo "PASS: Cash account increased by 1.00.\n";
    echo "PASS: Owner Capital account increased by 1.00.\n";

    $duplicateRejected = false;

    try {
        $service->postForCompany($companyId, $journalId, $actorId);
    } catch (Throwable $e) {
        $duplicateRejected = true;
        echo "Duplicate posting rejection: " . $e->getMessage() . "\n";
    }

    if (!$duplicateRejected) {
        throw new RuntimeException('Duplicate posting was not rejected.');
    }

    echo "PASS: Duplicate posting rejected.\n";
} catch (Throwable $e) {
    $failure = $e->getMessage();
} finally {
    if ($db->inTransaction()) {
        try {
            $db->rollback();
            $rolledBack = true;
            echo "Test transaction rolled back.\n";
        } catch (Throwable $rollbackError) {
            $failure = ($failure ? $failure . ' | ' : '')
                . 'ROLLBACK FAILED: ' . $rollbackError->getMessage();
        }
    }
}

if (!$rolledBack) {
    exit("SAFETY ERROR: rollback could not be confirmed. " . ($failure ?? '') . "\n");
}

try {
    $after = postingBaseline($pdo, $companyId);
} catch (Throwable $e) {
    exit("SAFETY ERROR: could not verify database baseline: " . $e->getMessage() . "\n");
}

if ($before !== $after) {
    echo "FAIL: Database state differs from baseline after rollback.\n";
    echo "Before: " . json_encode($before) . "\n";
    echo "After:  " . json_encode($after) . "\n";
    exit(1);
}

echo "PASS: Journal count, line count, sequence and account balances restored.\n";

if ($failure !== null) {
    exit("TEST FAILED: " . $failure . "\n");
}

echo "ALL POSTING TESTS PASSED; no test journal was retained.\n";