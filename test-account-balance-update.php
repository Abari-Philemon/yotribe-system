<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/app/config/database.php';

use App\Core\Database;
use App\Modules\Finance\Repositories\AccountRepository;
use Throwable;

$companyId = 1;
$accountId = 12;
$rolledBack = false;

if ($pdo->inTransaction()) {
    exit("ABORTED: PDO transaction already active; nothing changed.\n");
}

$db = new Database($pdo);
$accounts = new AccountRepository($db);

$original = $db->fetch(
    'SELECT current_balance
     FROM accounts
     WHERE company_id = :company_id AND id = :account_id',
    ['company_id' => $companyId, 'account_id' => $accountId]
);

if ($original === null) {
    exit("ABORTED: Expected company account not found.\n");
}

$originalBalance = (string) $original['current_balance'];
$testBalance = $originalBalance === '123.45' || $originalBalance === '123.4500'
    ? '234.56'
    : '123.45';

try {
    $db->beginTransaction();

    $locked = $accounts->findByCompanyAndIdForUpdate($companyId, $accountId);

    if ($locked === null) {
        throw new RuntimeException('Account could not be locked.');
    }

    $accounts->updateCurrentBalanceForPosting(
        $companyId,
        $accountId,
        $testBalance
    );

    $afterChange = $accounts->findByCompanyAndId($companyId, $accountId);

    if (
        $afterChange === null
        || number_format((float) $afterChange['current_balance'], 2, '.', '')
            !== $testBalance
    ) {
        throw new RuntimeException('Changed balance was not saved correctly.');
    }

    echo "PASS: Changed balance updated inside transaction.\n";

    $accounts->updateCurrentBalanceForPosting(
        $companyId,
        $accountId,
        $testBalance
    );

    $unchanged = $accounts->findByCompanyAndId($companyId, $accountId);

    if (
        $unchanged === null
        || number_format((float) $unchanged['current_balance'], 2, '.', '')
            !== $testBalance
    ) {
        throw new RuntimeException('Unchanged balance check failed.');
    }

    echo "PASS: Reapplying the same balance did not throw an exception.\n";

    $db->rollback();
    $rolledBack = true;
    echo "Test transaction rolled back.\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollback();
        $rolledBack = true;
    }

    echo "TEST ERROR: " . $e->getMessage() . "\n";
}

if (!$rolledBack) {
    exit("SAFETY ERROR: rollback could not be confirmed.\n");
}

$restored = $db->fetch(
    'SELECT current_balance
     FROM accounts
     WHERE company_id = :company_id AND id = :account_id',
    ['company_id' => $companyId, 'account_id' => $accountId]
);

if (
    $restored !== null
    && number_format((float) $restored['current_balance'], 2, '.', '')
        === number_format((float) $originalBalance, 2, '.', '')
) {
    echo "PASS: Original account balance restored after rollback.\n";
} else {
    echo "FAIL: Original account balance does not match after rollback.\n";
    exit(1);
}