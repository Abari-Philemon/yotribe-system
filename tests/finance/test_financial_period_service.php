<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config/database.php';

use App\Core\Database;
use App\Modules\Finance\Repositories\FinancialYearRepository;
use App\Modules\Finance\Repositories\FinancialPeriodRepository;
use App\Modules\Finance\Services\FinancialPeriodService;

$pdo = $pdo ?? $GLOBALS['pdo'] ?? null;

if (!$pdo instanceof PDO) {
    throw new RuntimeException('Could not obtain the configured PDO connection.');
}

$db = new Database($pdo);
$years = new FinancialYearRepository($db);
$periods = new FinancialPeriodRepository($db);
$service = new FinancialPeriodService($db, $years, $periods);

$passed = 0;
$failed = 0;
$originalPeriodId = null;

function checkResult(string $label, bool $condition): void
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

try {
    $original = $periods->findCurrentByCompanyAndYear(1, 1);
    if ($original === null) {
        throw new RuntimeException('No current period exists for company 1, year 1.');
    }

    $originalPeriodId = (int) $original['id'];

    $db->beginTransaction();

    $selected = $service->setCurrentPeriod(1, 1, 9);
    checkResult(
        'Selecting an open period returns period 9',
        (int) $selected['id'] === 9
    );

    $current = $periods->findCurrentByCompanyAndYear(1, 1);
    checkResult(
        'Repository identifies period 9 as current',
        $current !== null && (int) $current['id'] === 9
    );

    $count = $db->fetch(
        'SELECT COUNT(*) AS total
         FROM financial_periods
         WHERE company_id = :company_id
           AND financial_year_id = :year_id
           AND is_current = 1',
        ['company_id' => 1, 'year_id' => 1]
    );

    checkResult(
        'Exactly one period is current for the financial year',
        (int) ($count['total'] ?? 0) === 1
    );

    try {
        $service->generateMonthlyPeriods(1, 1);
        checkResult('Generation refuses to duplicate existing periods', false);
    } catch (Throwable $e) {
        checkResult('Generation refuses to duplicate existing periods', true);
    }
} catch (Throwable $e) {
    $failed++;
    echo "[FAIL] Unexpected test error: " . $e->getMessage() . "\n";
} finally {
    if ($db->getConnection()->inTransaction()) {
        $db->rollBack();
    }
}

$restored = $periods->findCurrentByCompanyAndYear(1, 1);
checkResult(
    'Original current period is preserved after rollback',
    $restored !== null && (int) $restored['id'] === $originalPeriodId
);

echo "\nResults: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);