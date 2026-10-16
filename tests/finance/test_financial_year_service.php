<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config/database.php';

use App\Core\Database;
use App\Modules\Finance\Repositories\FinancialYearRepository;
use App\Modules\Finance\Requests\FinancialYearRequest;
use App\Modules\Finance\Services\FinancialYearService;

$pdo = $pdo ?? $GLOBALS['pdo'] ?? null;

if (!$pdo instanceof PDO) {
    throw new RuntimeException('Could not obtain the configured PDO connection.');
}

$db = new Database($pdo);
$years = new FinancialYearRepository($db);
$request = new FinancialYearRequest();
$service = new FinancialYearService($db, $years, $request);

$passed = 0;
$failed = 0;
$originalCurrentYearId = null;

function checkYearResult(string $label, bool $condition): void
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

function expectYearException(callable $callback): bool
{
    try {
        $callback();
        return false;
    } catch (Throwable $exception) {
        return true;
    }
}

try {
    $originalCurrent = $years->findCurrentByCompany(1);

    if ($originalCurrent === null) {
        throw new RuntimeException(
            'Company 1 has no current financial year; refusing to run.'
        );
    }

    $originalCurrentYearId = (int) $originalCurrent['id'];

    $db->beginTransaction();

    $created = $service->create(1, [
        'year_code' => 'TEST2027',
        'year_name' => 'Test Financial Year 2027',
        'start_date' => '2027-01-01',
        'end_date' => '2027-12-31',
    ]);

    $createdId = (int) $created['id'];

    checkYearResult(
        'Creates a financial year for the specified company',
        $createdId > 0
        && (int) $created['company_id'] === 1
        && $created['year_code'] === 'TEST2027'
        && $created['status'] === 'OPEN'
        && (int) $created['is_current'] === 0
    );

    checkYearResult(
        'Created year can be retrieved by company and ID',
        $service->getYear(1, $createdId) !== null
    );

    checkYearResult(
        'Year is absent when queried under another company',
        $service->getYear(999999, $createdId) === null
    );

    checkYearResult(
        'Rejects a duplicate financial year code',
        expectYearException(
            fn() => $service->create(1, [
                'year_code' => 'TEST2027',
                'year_name' => 'Duplicate Test Year',
                'start_date' => '2028-01-01',
                'end_date' => '2028-12-31',
            ])
        )
    );

    checkYearResult(
        'Rejects an overlapping financial year date range',
        expectYearException(
            fn() => $service->create(1, [
                'year_code' => 'TEST2027OVERLAP',
                'year_name' => 'Overlapping Test Year',
                'start_date' => '2026-12-01',
                'end_date' => '2027-11-30',
            ])
        )
    );

    checkYearResult(
        'Rejects attempts to select another company year',
        expectYearException(
            fn() => $service->setCurrentYear(999999, $createdId)
        )
    );

    $selected = $service->setCurrentYear(1, $createdId);

    checkYearResult(
        'Sets the requested open year as current',
        (int) $selected['id'] === $createdId
        && (int) $selected['is_current'] === 1
    );

    $current = $years->findCurrentByCompany(1);

    checkYearResult(
        'Repository returns the newly selected current year',
        $current !== null && (int) $current['id'] === $createdId
    );

    $currentCount = $db->fetch(
        'SELECT COUNT(*) AS total
         FROM financial_years
         WHERE company_id = :company_id
           AND is_current = 1',
        ['company_id' => 1]
    );

    checkYearResult(
        'Exactly one financial year is current for the company',
        (int) ($currentCount['total'] ?? 0) === 1
    );

    $db->execute(
        'UPDATE financial_years
         SET status = :closed_status
         WHERE company_id = :company_id
           AND id = :year_id',
        [
            'closed_status' => 'CLOSED',
            'company_id' => 1,
            'year_id' => $createdId,
        ]
    );

    checkYearResult(
        'Rejects setting a closed financial year as current',
        expectYearException(
            fn() => $service->setCurrentYear(1, $createdId)
        )
    );

    $currentAfterClosedRejection = $years->findCurrentByCompany(1);

    checkYearResult(
        'Closed-year rejection preserves the selected current year',
        $currentAfterClosedRejection !== null
        && (int) $currentAfterClosedRejection['id'] === $createdId
    );

    $db->execute(
        'UPDATE financial_years
         SET status = :open_status
         WHERE company_id = :company_id
           AND id = :year_id',
        [
            'open_status' => 'OPEN',
            'company_id' => 1,
            'year_id' => $createdId,
        ]
    );

    $service->setCurrentYear(1, $originalCurrentYearId);

    checkYearResult(
        'Does not commit a caller-owned transaction',
        $db->inTransaction()
    );

    $service->setCurrentYear(1, $createdId);

    checkYearResult(
        'Year listing includes the newly created year',
        count(array_filter(
            $service->listYears(1),
            static fn(array $year): bool =>
                (int) $year['id'] === $createdId
        )) === 1
    );
} catch (Throwable $exception) {
    $failed++;
    echo "[FAIL] Unexpected test error: "
        . $exception->getMessage() . "\n";
} finally {
    if ($db->inTransaction()) {
        $db->rollback();
    }
}

try {
    $restored = $years->findCurrentByCompany(1);

    checkYearResult(
        'Original current financial year is preserved after rollback',
        $restored !== null
        && (int) $restored['id'] === $originalCurrentYearId
    );

    checkYearResult(
        'Temporary TEST2027 year does not persist after rollback',
        $years->findByCompanyAndCode(1, 'TEST2027') === null
    );
} catch (Throwable $exception) {
    $failed++;
    echo "[FAIL] Post-rollback verification error: "
        . $exception->getMessage() . "\n";
}

echo "\nResults: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);