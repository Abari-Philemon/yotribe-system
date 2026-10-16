<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config/database.php';

use App\Core\Database;
use App\Modules\Finance\Repositories\AccountRepository;
use App\Modules\Finance\Repositories\AccountTypeRepository;
use App\Modules\Finance\Requests\AccountRequest;
use App\Modules\Finance\Services\AccountService;

$pdo = $pdo ?? $GLOBALS['pdo'] ?? null;

if (!$pdo instanceof PDO) {
    throw new RuntimeException('Could not obtain the configured PDO connection.');
}

$db = new Database($pdo);
$accounts = new AccountRepository($db);
$accountTypes = new AccountTypeRepository($db);
$request = new AccountRequest();
$service = new AccountService($db, $accounts, $accountTypes, $request);

$passed = 0;
$failed = 0;

function checkAccountCreate(string $label, bool $condition): void
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

    $created = $service->create(1, [
        'account_type_id' => 1,
        'account_class_id' => 1,
        'parent_account_id' => null,
        'account_code' => 'TEST1000',
        'system_key' => 'TEST_CURRENT_ASSET',
        'account_name' => 'Test Current Asset',
        'short_name' => 'Test Asset',
        'description' => 'Temporary account used to verify AccountService creation.',
        'normal_balance' => 'debit',
        'opening_balance' => '0.00',
        'allow_posting' => true,
        'is_control_account' => false,
        'is_cash_account' => false,
        'is_bank_account' => false,
        'is_active' => true,
    ], 1);

    $createdId = (int) $created['id'];

    checkAccountCreate(
        'Creates a valid current asset account',
        $createdId > 0
        && (int) $created['company_id'] === 1
        && $created['account_code'] === 'TEST1000'
        && $created['account_name'] === 'Test Current Asset'
        && $created['normal_balance'] === 'debit'
        && (int) $created['allow_posting'] === 1
        && (int) $created['is_active'] === 1
    );

    checkAccountCreate(
        'Created account has zero opening balance',
        $created['opening_balance'] === '0.00'
        && $created['current_balance'] === '0.00'
    );

    checkAccountCreate(
        'Created account has correct hierarchy',
        (int) $created['account_level'] === 1
        && $created['account_path'] === 'TEST1000'
    );

    checkAccountCreate(
        'Created account belongs to company 1',
        $accounts->findByCompanyAndId(1, $createdId) !== null
        && $accounts->findByCompanyAndId(999999, $createdId) === null
    );

    $db->rollback();

    checkAccountCreate(
        'Temporary account does not persist after rollback',
        $accounts->findByCompanyAndCode(1, 'TEST1000') === null
    );

} catch (Throwable $exception) {
    $failed++;
    echo "[FAIL] Unexpected test error: "
        . $exception->getMessage() . "\n";

    if ($db->inTransaction()) {
        $db->rollback();
    }
}

echo "\nResults: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);

