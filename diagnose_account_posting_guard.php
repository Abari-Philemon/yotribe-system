<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/config/database.php';

use App\Core\Database;
use App\Modules\Finance\Repositories\AccountRepository;
use App\Modules\Finance\Repositories\AccountTypeRepository;
use App\Modules\Finance\Requests\AccountRequest;
use App\Modules\Finance\Services\AccountService;

$pdo->beginTransaction();

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
        'account_code' => 'D' . $suffix,
        'system_key' => 'TEST_DIAG_' . $suffix,
        'account_name' => 'Temporary Posting Diagnostic',
        'normal_balance' => 'debit',
        'opening_balance' => '0.00',
        'allow_posting' => true,
        'is_control_account' => false,
        'is_cash_account' => false,
        'is_bank_account' => false,
        'is_active' => true,
    ]);

    $id = (int) ($created['id'] ?? 0);
    echo "Created account ID: {$id}\n";

    if ($id < 1) {
        throw new RuntimeException('No valid temporary account ID returned.');
    }

    try {
        $result = $service->updateAccount(1, $id, [
            'allow_posting' => false,
        ]);

        echo "UPDATE RESULT: returned successfully\n";
        echo "Returned allow_posting: "
            . var_export($result['allow_posting'] ?? null, true) . "\n";
    } catch (Throwable $e) {
        echo "UPDATE EXCEPTION: " . get_class($e) . "\n";
        echo "MESSAGE: " . $e->getMessage() . "\n";
    }

    $after = $service->getAccount(1, $id);

    echo "RELOADED allow_posting: "
        . var_export($after['allow_posting'] ?? null, true) . "\n";
    echo "RELOADED account_name: "
        . var_export($after['account_name'] ?? null, true) . "\n";
} catch (Throwable $e) {
    echo "[FATAL] " . get_class($e) . ': ' . $e->getMessage() . "\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "Temporary test transaction rolled back if still active.\n";
}