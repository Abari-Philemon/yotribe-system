<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config/database.php';

use App\Core\Database;
use App\Modules\Finance\Repositories\JournalEntryRepository;

$pdo = $pdo ?? $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
    throw new RuntimeException('Could not obtain the configured PDO connection.');
}

$repository = new JournalEntryRepository(new Database($pdo));
$passed = 0;
$failed = 0;

function checkJournalRepo(string $label, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "[PASS] {$label}\n";
    } else {
        $failed++;
        echo "[FAIL] {$label}\n";
    }
}

function journalRepoThrows(callable $callback): bool
{
    try {
        $callback();
        return false;
    } catch (InvalidArgumentException $exception) {
        return true;
    }
}

try {
    $companyOne = $repository->findAllByCompany(1);

    checkJournalRepo('Listing returns an array', is_array($companyOne));
    checkJournalRepo(
        'Company 1 has no journals in the current database',
        count($companyOne) === 0
    );
    checkJournalRepo(
        'Missing journal ID returns null',
        $repository->findByCompanyAndId(1, 999999999) === null
    );
    checkJournalRepo(
        'Missing journal number returns null',
        $repository->findByCompanyAndJournalNo(
            1, 'NO-SUCH-JOURNAL-TEST'
        ) === null
    );
    checkJournalRepo(
        'Missing journal number is not reported as existing',
        !$repository->journalNumberExists(1, 'NO-SUCH-JOURNAL-TEST')
    );
    checkJournalRepo(
        'Missing journal returns no lines',
        $repository->findLinesByJournalId(1, 999999999) === []
    );
    checkJournalRepo(
        'Unknown company returns an empty listing',
        $repository->findAllByCompany(999999999) === []
    );
    checkJournalRepo(
        'Unknown company cannot retrieve a journal',
        $repository->findByCompanyAndId(999999999, 1) === null
    );
    checkJournalRepo(
        'Valid status filter is accepted',
        is_array($repository->findAllByCompany(1, ['status' => 'DRAFT']))
    );
    checkJournalRepo(
        'Valid date range is accepted',
        is_array($repository->findAllByCompany(1, [
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
        ]))
    );
    checkJournalRepo(
        'Unsupported filter is rejected',
        journalRepoThrows(
            fn() => $repository->findAllByCompany(
                1, ['company_name' => 'Test']
            )
        )
    );
    checkJournalRepo(
        'Invalid status is rejected',
        journalRepoThrows(
            fn() => $repository->findAllByCompany(
                1, ['status' => 'INVALID']
            )
        )
    );
    checkJournalRepo(
        'Impossible date is rejected',
        journalRepoThrows(
            fn() => $repository->findAllByCompany(
                1, ['date_from' => '2026-02-30']
            )
        )
    );
    checkJournalRepo(
        'Reversed date range is rejected',
        journalRepoThrows(
            fn() => $repository->findAllByCompany(1, [
                'date_from' => '2026-12-31',
                'date_to' => '2026-01-01',
            ])
        )
    );
    checkJournalRepo(
        'Zero company ID is rejected',
        journalRepoThrows(fn() => $repository->findAllByCompany(0))
    );
    checkJournalRepo(
        'Limit above 500 is rejected',
        journalRepoThrows(fn() => $repository->findAllByCompany(1, [], 501))
    );
    checkJournalRepo(
        'Negative offset is rejected',
        journalRepoThrows(
            fn() => $repository->findAllByCompany(1, [], 10, -1)
        )
    );
    checkJournalRepo(
        'Blank journal number is rejected',
        journalRepoThrows(
            fn() => $repository->findByCompanyAndJournalNo(1, '   ')
        )
    );
    checkJournalRepo(
        'Invalid excluded journal ID is rejected',
        journalRepoThrows(
            fn() => $repository->journalNumberExists(1, 'TEST', 0)
        )
    );
} catch (Throwable $exception) {
    $failed++;
    echo "[FAIL] Unexpected test error: "
        . $exception->getMessage() . "\n";
}

echo "\nResults: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);