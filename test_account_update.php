<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/config/database.php';

use App\Core\Database;
use App\Modules\Finance\Repositories\AccountRepository;
use App\Modules\Finance\Repositories\AccountTypeRepository;
use App\Modules\Finance\Requests\AccountRequest;
use App\Modules\Finance\Services\AccountService;

$passed = 0;
$failed = 0;
$pdo->beginTransaction();

function checkResult(string $label, bool $condition): void
{
    global $passed, $failed;
    echo ($condition ? '[PASS] ' : '[FAIL] ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}

function expectException(callable $callback, string $label): void
{
    try {
        $callback();
        checkResult($label, false);
    } catch (InvalidArgumentException | RuntimeException $e) {
        checkResult($label, true);
    }
}

try {
    $db = new Database($pdo);
    $accounts = new AccountRepository($db);
    $types = new AccountTypeRepository($db);
    $request = new AccountRequest();
    $service = new AccountService($db, $accounts, $types, $request);

    $suffix = strtoupper(bin2hex(random_bytes(4)));
    $code = 'T' . $suffix;

    $created = $service->create(1, [
        'account_type_id' => 1,
        'account_class_id' => 1,
        'account_code' => $code,
        'system_key' => 'TEST_UPDATE_' . $suffix,
        'account_name' => 'Temporary Update Test',
        'normal_balance' => 'debit',
        'opening_balance' => '0.00',
        'allow_posting' => true,
        'is_control_account' => false,
        'is_cash_account' => false,
        'is_bank_account' => false,
        'is_active' => true,
    ]);

    $accountId = (int) ($created['id'] ?? 0);
    checkResult('Temporary account created', $accountId > 0);

    if ($accountId <= 0) {
        throw new RuntimeException('Create did not return a valid account ID.');
    }

    $updated = $service->updateAccount(1, $accountId, [
        'account_name' => 'Temporary Updated Account',
        'short_name' => 'Temp Update',
        'account_code' => 'U' . $suffix,
    ]);

    checkResult(
        'Name, short name and code updated',
        ($updated['account_name'] ?? null) === 'Temporary Updated Account'
        && ($updated['short_name'] ?? null) === 'Temp Update'
        && ($updated['account_code'] ?? null) === 'U' . $suffix
    );

    expectException(
        fn() => $service->updateAccount(1, $accountId, [
            'account_code' => '1001',
        ]),
        'Duplicate account code rejected'
    );

    expectException(
        fn() => $service->updateAccount(1, $accountId, [
            'company_id' => 999,
        ]),
        'Protected company_id field rejected'
    );

    expectException(
        fn() => $service->updateAccount(1, $accountId, [
            'opening_balance' => '500.00',
        ]),
        'Protected opening_balance field rejected'
    );

    expectException(
        fn() => $service->updateAccount(999999, $accountId, [
            'account_name' => 'Must Not Update',
        ]),
        'Cross-company update rejected'
    );

    $afterIsolationCheck = $service->getAccount(1, $accountId);
    checkResult(
        'Failed cross-company update left account unchanged',
        ($afterIsolationCheck['account_name'] ?? null) === 'Temporary Updated Account'
    );
} catch (Throwable $e) {
    echo '[ERROR] ' . get_class($e) . ': ' . $e->getMessage() . "\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\nResults: {$passed} passed; {$failed} failed.\n";
    echo "Database transaction rolled back if still active.\n";
}