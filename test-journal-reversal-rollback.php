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
$today = date('Y-m-d');
$rolledBack = false;
$failure = null;

if ($pdo->inTransaction()) {
    exit("ABORTED: PDO transaction already active; nothing changed.\n");
}

function reversalBaseline(PDO $pdo, int $companyId): array
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
           AND financial_year = YEAR(CURDATE())
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

$before = reversalBaseline($pdo, $companyId);
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
            'journal_date' => $today,
            'transaction_code' => 'TEST',
            'source_module' => 'finance_test',
            'reference_no' => 'REVERSAL-ROLLBACK-' . date('Ymd-His'),
            'narration' => 'REVERSAL TEST ONLY - rollback required',
            'lines' => [
                [
                    'account_id' => 12,
                    'entry_type' => 'DEBIT',
                    'amount' => '1.00',
                    'description' => 'Temporary reversal test',
                ],
                [
                    'account_id' => 20,
                    'entry_type' => 'CREDIT',
                    'amount' => '1.00',
                    'description' => 'Temporary reversal test',
                ],
            ],
        ],
        $actorId
    );

    $originalId = (int) $created['journal']['id'];

    $postedOriginal = $service->postForCompany(
        $companyId,
        $originalId,
        $actorId
    );

    if (strtoupper((string) $postedOriginal['journal']['status']) !== 'POSTED') {
        throw new RuntimeException('The test source journal was not posted.');
    }

    echo "PASS: Temporary source journal posted.\n";

    $cashBeforeReversal = (string) $pdo->query(
        'SELECT current_balance FROM accounts WHERE company_id = 1 AND id = 12'
    )->fetchColumn();

    $capitalBeforeReversal = (string) $pdo->query(
        'SELECT current_balance FROM accounts WHERE company_id = 1 AND id = 20'
    )->fetchColumn();

    $result = $service->reverseForCompany(
        $companyId,
        $originalId,
        $actorId
    );

    $original = $result['original'];
    $reversal = $result['reversal']['journal'];
    $reversalId = (int) $reversal['id'];

    if (strtoupper((string) $original['status']) !== 'REVERSED') {
        throw new RuntimeException('Original journal status is not REVERSED.');
    }

    if ((int) $original['reversal_journal_id'] !== $reversalId) {
        throw new RuntimeException('Original journal does not link to the reversal.');
    }

    if (strtoupper((string) $reversal['status']) !== 'POSTED') {
        throw new RuntimeException('Reversal journal status is not POSTED.');
    }

    if ((string) $reversal['journal_date'] !== $today) {
        throw new RuntimeException(
            'Reversal date mismatch: expected ' . $today .
            ', received ' . (string) $reversal['journal_date']
        );
    }

    echo "PASS: Original is REVERSED and linked to the POSTED reversal.\n";
    echo "PASS: Reversal date defaults to today ($today).\n";

    $originalLines = $result['original_lines'];
    $reversalLines = $result['reversal']['lines'];

    if (count($originalLines) !== count($reversalLines)) {
        throw new RuntimeException('Original and reversal line counts differ.');
    }

    foreach ($originalLines as $index => $originalLine) {
        $reversalLine = $reversalLines[$index] ?? null;

        if ($reversalLine === null) {
            throw new RuntimeException('A reversal line is missing.');
        }

        $expectedType = strtoupper((string) $originalLine['entry_type']) === 'DEBIT'
            ? 'CREDIT'
            : 'DEBIT';

        if (
            (int) $originalLine['account_id'] !== (int) $reversalLine['account_id']
            || $expectedType !== strtoupper((string) $reversalLine['entry_type'])
            || bccomp(
                (string) $originalLine['amount'],
                (string) $reversalLine['amount'],
                2
            ) !== 0
        ) {
            throw new RuntimeException(
                'Reversal line ' . ($index + 1) . ' does not correctly invert its original.'
            );
        }
    }

    echo "PASS: Reversal lines invert the original accounts, entry types and amounts.\n";

    $cashAfterReversal = (string) $pdo->query(
        'SELECT current_balance FROM accounts WHERE company_id = 1 AND id = 12'
    )->fetchColumn();

    $capitalAfterReversal = (string) $pdo->query(
        'SELECT current_balance FROM accounts WHERE company_id = 1 AND id = 20'
    )->fetchColumn();

    $cashBaseline = (string) ($before['balances'][12] ?? '0.00');
    $capitalBaseline = (string) ($before['balances'][20] ?? '0.00');

    if (
        bccomp($cashBaseline, $cashAfterReversal, 2) !== 0
        || bccomp($capitalBaseline, $capitalAfterReversal, 2) !== 0
    ) {
        throw new RuntimeException(
            'Account balances did not return to their original baseline. ' .
            'Cash baseline=' . $cashBaseline .
            ', cash after reversal=' . $cashAfterReversal .
            ', capital baseline=' . $capitalBaseline .
            ', capital after reversal=' . $capitalAfterReversal
        );
    }

    echo "PASS: Posting the reversal restored the pre-reversal account balances.\n";

    $duplicateRejected = false;

    try {
        $service->reverseForCompany($companyId, $originalId, $actorId);
    } catch (Throwable $e) {
        $duplicateRejected = true;
        echo "Duplicate reversal rejection: " . $e->getMessage() . "\n";
    }

    if (!$duplicateRejected) {
        throw new RuntimeException('A second reversal of the same journal was not rejected.');
    }

    echo "PASS: Duplicate reversal rejected.\n";
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
    $after = reversalBaseline($pdo, $companyId);
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

echo "ALL REVERSAL TESTS PASSED; no test journals were retained.\n";