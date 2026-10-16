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

function hierarchyCheck(string $label, bool $ok): void
{
    global $passed, $failed;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . "\n";
    $ok ? $passed++ : $failed++;
}

function hierarchyRejects(callable $fn): bool
{
    try {
        $fn();
        return false;
    } catch (InvalidArgumentException | RuntimeException $e) {
        return true;
    }
}

function hierarchyInput(string $suffix, int $type, int $class, array $extra = []): array
{
    return array_merge([
        'account_type_id' => $type,
        'account_class_id' => $class,
        'account_code' => 'H' . $suffix,
        'system_key' => 'TEST_HIER_' . $suffix,
        'account_name' => 'Temporary Hierarchy ' . $suffix,
        'normal_balance' => $type === 1 ? 'debit' : 'credit',
        'opening_balance' => '0.00',
        'allow_posting' => true,
        'is_control_account' => false,
        'is_cash_account' => false,
        'is_bank_account' => false,
        'is_active' => true,
    ], $extra);
}

try {
    $db = new Database($pdo);
    $repo = new AccountRepository($db);
    $service = new AccountService(
        $db,
        $repo,
        new AccountTypeRepository($db),
        new AccountRequest()
    );

    $suffix = strtoupper(bin2hex(random_bytes(4)));

    // A type/class mismatch must be rejected.
    hierarchyCheck(
        'Mismatched account type and class rejected',
        hierarchyRejects(fn() => $service->create(
            1,
            hierarchyInput($suffix . 'A', 1, 6)
        ))
    );

    // Create a valid non-posting asset parent.
    $parent = $service->create(
        1,
        hierarchyInput($suffix . 'B', 1, 1, [
            'allow_posting' => false,
        ])
    );
    $parentId = (int) $parent['id'];

    hierarchyCheck('Valid non-posting parent created', $parentId > 0);

    // A matching child should be allowed.
    $child = $service->create(
        1,
        hierarchyInput($suffix . 'C', 1, 1, [
            'parent_account_id' => $parentId,
        ])
    );

    hierarchyCheck(
        'Child with matching type and class accepted',
        (int) ($child['parent_account_id'] ?? 0) === $parentId
    );

    // Different type/class from parent must be rejected.
    hierarchyCheck(
        'Child with mismatched parent classification rejected',
        hierarchyRejects(fn() => $service->create(
            1,
            hierarchyInput($suffix . 'D', 4, 6, [
                'parent_account_id' => $parentId,
            ])
        ))
    );

    // A posting-enabled parent must be rejected.
    $postingParent = $service->create(
        1,
        hierarchyInput($suffix . 'E', 1, 1)
    );

    hierarchyCheck(
        'Posting-enabled parent rejected',
        hierarchyRejects(fn() => $service->create(
            1,
            hierarchyInput($suffix . 'F', 1, 1, [
                'parent_account_id' => (int) $postingParent['id'],
            ])
        ))
    );

    // Inactive parent must be rejected. Deactivate only a temporary record
    // directly inside this transaction for this validation test.
    $inactiveParentId = (int) $parent['id'];

    $pdo->prepare(
        'UPDATE accounts
         SET is_active = 0
         WHERE company_id = 1 AND id = :id'
    )->execute(['id' => $inactiveParentId]);

    hierarchyCheck(
        'Inactive parent rejected',
        hierarchyRejects(fn() => $service->create(
            1,
            hierarchyInput($suffix . 'G', 1, 1, [
                'parent_account_id' => $inactiveParentId,
            ])
        ))
    );

    // Company isolation: company 999999 must not see company 1's parent.
    hierarchyCheck(
        'Parent from another company is not visible',
        $repo->findByCompanyAndId(999999, $parentId) === null
        && !$repo->validateParentAccount(999999, $parentId)
    );

    // Invalid classification ID must be rejected.
    hierarchyCheck(
        'Unknown classification rejected',
        hierarchyRejects(fn() => $service->create(
            1,
            hierarchyInput($suffix . 'H', 1, 999999)
        ))
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