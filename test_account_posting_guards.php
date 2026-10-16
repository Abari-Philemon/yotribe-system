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

    if ($condition) {
        echo "[PASS] {$label}\n";
        $passed++;
    } else {
        echo "[FAIL] {$label}\n";
        $failed++;
    }
}

function expectRejected(callable $action): bool
{
    try {
        $action();
        return false;
    } catch (InvalidArgumentException | RuntimeException $e) {
        return true;
    }
}

try {
    $db = new Database($pdo);
    $accounts = new AccountRepository($db);
    $service = new AccountService(
        $db,
        $accounts,
        new AccountTypeRepository($db),
        new AccountRequest()
    );

    $suffix = strtoupper(bin2hex(random_bytes(4)));

    $created = $service->create(1, [
        'account_type_id' => 1,
        'account_class_id' => 1,
        'account_code' => 'T' . $suffix,
        'system_key' => 'TEST_GUARD_' . $suffix,
        'account_name' => 'Temporary Posting Guard Test',
        'normal_balance' => 'debit',
        'opening_balance' => '0.00',
        'allow_posting' => true,
        'is_control_account' => false,
        'is_cash_account' => false,
        'is_bank_account' => false,
        'is_active' => true,
    ]);

    $id = (int) ($created['id'] ?? 0);
    checkResult('Temporary account created', $id > 0);

    if ($id < 1) {
        throw new RuntimeException('Temporary account ID was not returned.');
    }

    $disabled = $service->updateAccount(1, $id, [
        'allow_posting' => false,
    ]);

    checkResult(
        'Unreferenced account can become non-posting',
        (int) ($disabled['allow_posting'] ?? 1) === 0
    );

    $cashRejected = expectRejected(
        fn() => $service->updateAccount(1, $id, [
            'is_cash_account' => true,
        ])
    );

    checkResult(
        'Non-posting account cannot be marked as cash',
        $cashRejected
    );

    $afterCashAttempt = $service->getAccount(1, $id);

    checkResult(
        'Rejected cash update leaves account unchanged',
        (int) ($afterCashAttempt['allow_posting'] ?? 1) === 0
        && (int) ($afterCashAttempt['is_cash_account'] ?? 1) === 0
    );

    $bankRejected = expectRejected(
        fn() => $service->updateAccount(1, $id, [
            'is_bank_account' => true,
        ])
    );

    checkResult(
        'Non-posting account cannot be marked as bank',
        $bankRejected
    );

    $enabled = $service->updateAccount(1, $id, [
        'allow_posting' => true,
    ]);

    checkResult(
        'Account can be restored to posting-enabled',
        (int) ($enabled['allow_posting'] ?? 0) === 1
    );

    $bothRejected = expectRejected(
        fn() => $service->updateAccount(1, $id, [
            'is_cash_account' => true,
            'is_bank_account' => true,
        ])
    );

    checkResult(
        'Cash and bank flags cannot both be enabled',
        $bothRejected
    );

    $afterBothAttempt = $service->getAccount(1, $id);

    checkResult(
        'Rejected cash/bank combination leaves flags unchanged',
        (int) ($afterBothAttempt['is_cash_account'] ?? 1) === 0
        && (int) ($afterBothAttempt['is_bank_account'] ?? 1) === 0
    );

    $renamed = $service->updateAccount(1, $id, [
        'account_name' => 'Temporary Guard Test Updated',
    ]);

    checkResult(
        'Ordinary update still works',
        ($renamed['account_name'] ?? '') === 'Temporary Guard Test Updated'
    );
} catch (Throwable $e) {
    echo "[ERROR] " . get_class($e) . ': ' . $e->getMessage() . "\n";
    $failed++;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "\nResults: {$passed} passed; {$failed} failed.\n";
    echo "Temporary test transaction rolled back if still active.\n";
}