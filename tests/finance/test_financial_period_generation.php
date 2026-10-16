<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config/database.php';

use App\Core\Database;
use App\Modules\Finance\Repositories\FinancialYearRepository;
use App\Modules\Finance\Repositories\FinancialPeriodRepository;
use App\Modules\Finance\Services\FinancialPeriodService;

if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Configured PDO connection was not found.');
}

$db = new Database($pdo);
$years = new FinancialYearRepository($db);
$periods = new FinancialPeriodRepository($db);
$service = new FinancialPeriodService($db, $years, $periods);

$passed = 0;
$failed = 0;
$yearId = null;

function checkGeneration(string $label, bool $condition): void
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
    $db->beginTransaction();

    $db->execute(
        'INSERT INTO financial_years
            (uuid, company_id, year_code, year_name, start_date, end_date,
             status, is_current)
         VALUES
            (:uuid, :company_id, :year_code, :year_name, :start_date,
             :end_date, :status, :is_current)',
        [
            'uuid' => generateUuid(),
            'company_id' => 1,
            'year_code' => 'TEST2027',
            'year_name' => 'Temporary Test Financial Year 2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'status' => 'OPEN',
            'is_current' => 0,
        ]
    );

    $year = $db->fetch(
        'SELECT id FROM financial_years
         WHERE company_id = :company_id AND year_code = :year_code',
        ['company_id' => 1, 'year_code' => 'TEST2027']
    );

    if ($year === null) {
        throw new RuntimeException('Temporary financial year was not created.');
    }

    $yearId = (int) $year['id'];

    $generated = $service->generateMonthlyPeriods(1, $yearId);

    checkGeneration('Exactly 12 periods generated', count($generated) === 12);

    $all = $periods->findAllByCompanyAndYear(1, $yearId);
    checkGeneration('Repository returns all 12 periods', count($all) === 12);

    checkGeneration(
        'Period numbers run sequentially from 1 to 12',
        array_map(static fn(array $p): int => (int) $p['period_no'], $all)
            === range(1, 12)
    );

    checkGeneration(
        'January starts on 2027-01-01',
        $all[0]['start_date'] === '2027-01-01'
    );

    checkGeneration(
        'January ends on 2027-01-31',
        $all[0]['end_date'] === '2027-01-31'
    );

    checkGeneration(
        'December starts on 2027-12-01',
        $all[11]['start_date'] === '2027-12-01'
    );

    checkGeneration(
        'December ends on 2027-12-31',
        $all[11]['end_date'] === '2027-12-31'
    );

    checkGeneration(
        'Period codes follow YYYYMM format',
        $all[0]['period_code'] === '202701'
            && $all[11]['period_code'] === '202712'
    );

    checkGeneration(
        'Generated periods are not automatically marked current',
        count(array_filter(
            $all,
            static fn(array $p): bool => (int) $p['is_current'] === 1
        )) === 0
    );

    checkGeneration(
        'All generated periods are open',
        count(array_filter(
            $all,
            static fn(array $p): bool => $p['status'] !== 'OPEN'
        )) === 0
    );
} catch (Throwable $e) {
    $failed++;
    echo "[FAIL] Unexpected test error: " . $e->getMessage() . "\n";
} finally {
    if ($db->getConnection()->inTransaction()) {
        $db->rollBack();
    }
}

$remaining = $db->fetch(
    'SELECT COUNT(*) AS total FROM financial_years
     WHERE company_id = :company_id AND year_code = :year_code',
    ['company_id' => 1, 'year_code' => 'TEST2027']
);

checkGeneration(
    'Temporary financial year was removed by rollback',
    (int) ($remaining['total'] ?? -1) === 0
);

echo "\nResults: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);