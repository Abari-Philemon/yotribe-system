<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Modules\Finance\Requests\FinancialYearRequest;

$request = new FinancialYearRequest();
$passed = 0;
$failed = 0;

function checkYearRequest(string $label, callable $test): void
{
    global $passed, $failed;

    try {
        if ($test()) {
            $passed++;
            echo "[PASS] {$label}\n";
        } else {
            $failed++;
            echo "[FAIL] {$label}\n";
        }
    } catch (Throwable $e) {
        $failed++;
        echo "[FAIL] {$label}: {$e->getMessage()}\n";
    }
}

$valid = [
    'year_code' => 'FY2027',
    'year_name' => 'Financial Year 2027',
    'start_date' => '2027-01-01',
    'end_date' => '2027-12-31',
];

checkYearRequest('Valid calendar financial year accepted', function () use ($request, $valid): bool {
    $result = $request->validate($valid);
    return $result['year_code'] === 'FY2027'
        && $result['start_date'] === '2027-01-01'
        && $result['end_date'] === '2027-12-31';
});

checkYearRequest('Whitespace around values is trimmed', function () use ($request, $valid): bool {
    $input = $valid;
    $input['year_code'] = ' FY2027 ';
    return $request->validate($input)['year_code'] === 'FY2027';
});

checkYearRequest('Missing required field is rejected', function () use ($request, $valid): bool {
    $input = $valid;
    unset($input['year_name']);
    try {
        $request->validate($input);
        return false;
    } catch (InvalidArgumentException $e) {
        return true;
    }
});

checkYearRequest('Invalid date such as February 30 is rejected', function () use ($request, $valid): bool {
    $input = $valid;
    $input['start_date'] = '2027-02-30';
    try {
        $request->validate($input);
        return false;
    } catch (InvalidArgumentException $e) {
        return true;
    }
});

checkYearRequest('Start date after end date is rejected', function () use ($request, $valid): bool {
    $input = $valid;
    $input['start_date'] = '2027-12-31';
    $input['end_date'] = '2027-01-01';
    try {
        $request->validate($input);
        return false;
    } catch (InvalidArgumentException $e) {
        return true;
    }
});

checkYearRequest('Non-calendar financial year is rejected', function () use ($request, $valid): bool {
    $input = $valid;
    $input['start_date'] = '2027-04-01';
    $input['end_date'] = '2028-03-31';
    try {
        $request->validate($input);
        return false;
    } catch (InvalidArgumentException $e) {
        return true;
    }
});

checkYearRequest('Invalid year-code characters are rejected', function () use ($request, $valid): bool {
    $input = $valid;
    $input['year_code'] = 'FY 2027!';
    try {
        $request->validate($input);
        return false;
    } catch (InvalidArgumentException $e) {
        return true;
    }
});

checkYearRequest('Unexpected fields are rejected', function () use ($request, $valid): bool {
    $input = $valid;
    $input['company_id'] = 999;
    try {
        $request->validate($input);
        return false;
    } catch (InvalidArgumentException $e) {
        return true;
    }
});

checkYearRequest('Year name longer than 100 characters is rejected', function () use ($request, $valid): bool {
    $input = $valid;
    $input['year_name'] = str_repeat('A', 101);
    try {
        $request->validate($input);
        return false;
    } catch (InvalidArgumentException $e) {
        return true;
    }
});

echo "\nResults: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);