<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/config/database.php';

use App\Core\Database;
use App\Modules\Finance\Repositories\FinancialYearRepository;

$testsPassed = 0;
$testsFailed = 0;

function checkResult(string $name, bool $passed): void
{
    global $testsPassed, $testsFailed;

    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;

    if ($passed) {
        $testsPassed++;
    } else {
        $testsFailed++;
    }
}

try {
    $db = new Database($pdo);
    $repository = new FinancialYearRepository($db);

    $year = $repository->findByCompanyAndId(1, 1);

    checkResult(
        'Find existing 2026 financial year within company 1',
        $year !== null
            && (int) $year['id'] === 1
            && (int) $year['company_id'] === 1
            && $year['start_date'] === '2026-01-01'
            && $year['end_date'] === '2026-12-31'
    );

    $byCode = $repository->findByCompanyAndCode(
        1,
        (string) $year['year_code']
    );

    checkResult(
        'Find financial year by its existing code',
        $byCode !== null && (int) $byCode['id'] === 1
    );

    $years = $repository->findAllByCompany(1);

    checkResult(
        'List financial years for company 1',
        count($years) >= 1
    );

    $current = $repository->findCurrentByCompany(1);

    checkResult(
        'Find current financial year',
        $current !== null && (int) $current['id'] === 1
    );

    checkResult(
        'Detect overlapping dates',
        $repository->hasOverlappingDateRange(
            1,
            '2026-06-01',
            '2027-05-31'
        )
    );

    checkResult(
        'Allow the existing year when excluded from overlap check',
        !$repository->hasOverlappingDateRange(
            1,
            '2026-01-01',
            '2026-12-31',
            1
        )
    );

    checkResult(
        'Allow a non-overlapping 2027 date range',
        !$repository->hasOverlappingDateRange(
            1,
            '2027-01-01',
            '2027-12-31'
        )
    );

    checkResult(
        'Keep another company isolated',
        $repository->findByCompanyAndId(999999, 1) === null
    );
} catch (Throwable $e) {
    $testsFailed++;
    echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "Passed: {$testsPassed}; Failed: {$testsFailed}" . PHP_EOL;

exit($testsFailed === 0 ? 0 : 1);