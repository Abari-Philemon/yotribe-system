<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/config/database.php';

use App\Core\Database;
use App\Modules\Finance\Repositories\FinancialPeriodRepository;

$passed = 0;
$failed = 0;

function checkResult(string $name, bool $result): void
{
    global $passed, $failed;

    echo ($result ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;

    if ($result) {
        $passed++;
    } else {
        $failed++;
    }
}

try {
    $db = new Database($pdo);
    $repo = new FinancialPeriodRepository($db);

    $periods = $repo->findAllByCompanyAndYear(1, 1);

    checkResult(
        'Find all 12 periods for the existing financial year',
        count($periods) === 12
    );

    checkResult(
        'Periods are ordered January through December',
        count($periods) === 12
            && (int) $periods[0]['period_no'] === 1
            && (int) $periods[11]['period_no'] === 12
    );

    $october = $repo->findByCompanyYearAndNumber(1, 1, 10);

    checkResult(
        'Find October by period number',
        $october !== null
            && (int) $october['id'] === 10
            && $october['start_date'] === '2026-10-01'
            && $october['end_date'] === '2026-10-31'
    );

    $byId = $repo->findByCompanyYearAndId(1, 1, 10);

    checkResult(
        'Find October by company, year, and period ID',
        $byId !== null && (int) $byId['period_no'] === 10
    );

    $current = $repo->findCurrentByCompanyAndYear(1, 1);

    checkResult(
        'Find the current October period',
        $current !== null && (int) $current['id'] === 10
    );

    $byDate = $repo->findByCompanyAndDate(1, '2026-10-04');

    checkResult(
        'Find the period containing 4 October 2026',
        $byDate !== null && (int) $byDate['period_no'] === 10
    );

    checkResult(
        'Detect overlap with an existing period',
        $repo->hasOverlappingDateRange(
            1,
            1,
            '2026-10-15',
            '2026-11-15'
        )
    );

    checkResult(
        'Exclude the existing period from overlap detection',
        !$repo->hasOverlappingDateRange(
            1,
            1,
            '2026-10-01',
            '2026-10-31',
            10
        )
    );

    checkResult(
        'Keep another company isolated',
        $repo->findByCompanyYearAndId(999999, 1, 10) === null
    );

    checkResult(
        'Reject an impossible calendar date',
        (function () use ($repo): bool {
            try {
                $repo->findByCompanyAndDate(1, '2026-02-30');
                return false;
            } catch (InvalidArgumentException $e) {
                return true;
            }
        })()
    );

    checkResult(
        'Reject a period number outside 1–12',
        (function () use ($repo): bool {
            try {
                $repo->findByCompanyYearAndNumber(1, 1, 13);
                return false;
            } catch (InvalidArgumentException $e) {
                return true;
            }
        })()
    );
} catch (Throwable $e) {
    $failed++;
    echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "Passed: {$passed}; Failed: {$failed}" . PHP_EOL;

exit($failed === 0 ? 0 : 1);