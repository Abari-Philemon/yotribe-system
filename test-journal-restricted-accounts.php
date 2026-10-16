<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/app/config/database.php';

use App\Core\Database;
use App\Modules\Finance\Repositories\AccountRepository;
use App\Modules\Finance\Repositories\AccountTypeRepository;
use App\Modules\Finance\Repositories\FinancialPeriodRepository;
use App\Modules\Finance\Repositories\FinancialYearRepository;
use App\Modules\Finance\Repositories\FinanceSettingsRepository;
use App\Modules\Finance\Repositories\JournalEntryRepository;
use App\Modules\Finance\Requests\AccountRequest;
use App\Modules\Finance\Requests\JournalEntryRequest;
use App\Modules\Finance\Services\AccountService;
use App\Modules\Finance\Services\JournalEntryService;
use Throwable;

$companyId = 1;
$farmId = 1;
$actorId = 1;
$journalDate = '2026-10-09';

if ($pdo->inTransaction()) {
    exit("ABORTED: PDO transaction already active; nothing changed.\n");
}

$db = new Database($pdo);
$db->beginTransaction();
$rolledBack = false;

try {
    $accountRepository = new AccountRepository($db);
    $accountTypes = new AccountTypeRepository($db);
    $accountService = new AccountService(
        $db,
        $accountRepository,
        $accountTypes,
        new AccountRequest()
    );

    $journalRepository = new JournalEntryRepository($db);
    $journalService = new JournalEntryService(
        $db,
        $journalRepository,
        $accountRepository,
        new FinancialYearRepository($db),
        new FinancialPeriodRepository($db),
        new FinanceSettingsRepository($db),
        new JournalEntryRequest()
    );

    $cases = [
        [
            'name' => 'inactive account',
            'account_code' => 'TSTI' . date('His'),
            'system_key' => 'test_inactive_' . bin2hex(random_bytes(5)),
            'account_name' => 'Temporary Inactive Test Account',
            'is_active' => false,
            'allow_posting' => true,
            'expected_message' => 'is inactive.',
        ],
        [
            'name' => 'non-postable account',
            'account_code' => 'TSTP' . date('His'),
            'system_key' => 'test_nonpostable_' . bin2hex(random_bytes(5)),
            'account_name' => 'Temporary Non-Postable Test Account',
            'is_active' => true,
            'allow_posting' => false,
            'expected_message' => 'does not allow posting.',
        ],
    ];

    foreach ($cases as $case) {
        $account = $accountService->create(
            $companyId,
            [
                'account_type_id' => 1,
                'account_class_id' => 1,
                'account_code' => $case['account_code'],
                'system_key' => $case['system_key'],
                'account_name' => $case['account_name'],
                'normal_balance' => 'debit',
                'is_active' => $case['is_active'],
                'allow_posting' => $case['allow_posting'],
            ],
            $actorId
        );

        $restrictedAccountId = (int) $account['id'];

        try {
            $journalService->createForCompany(
                $companyId,
                [
                    'farm_id' => $farmId,
                    'journal_date' => $journalDate,
                    'transaction_code' => 'TEST',
                    'source_module' => 'finance_test',
                    'reference_no' => 'RESTRICT-' . strtoupper(bin2hex(random_bytes(5))),
                    'narration' => 'Restricted-account validation test only',
                    'lines' => [
                        [
                            'account_id' => 12,
                            'entry_type' => 'DEBIT',
                            'amount' => '1.00',
                            'description' => 'Validation test',
                        ],
                        [
                            'account_id' => $restrictedAccountId,
                            'entry_type' => 'CREDIT',
                            'amount' => '1.00',
                            'description' => 'Validation test',
                        ],
                    ],
                ],
                $actorId
            );

            throw new RuntimeException(
                'FAIL: Journal service accepted the ' . $case['name'] . '.'
            );
        } catch (RuntimeException $e) {
            if (strpos($e->getMessage(), $case['expected_message']) === false) {
                throw new RuntimeException(
                    'Unexpected rejection for ' . $case['name'] . ': '
                    . $e->getMessage(),
                    0,
                    $e
                );
            }

            echo 'PASS: ' . $case['name'] . ' rejected: '
                . $e->getMessage() . PHP_EOL;
        }
    }

    $db->rollback();
    $rolledBack = true;
    echo "Test transaction rolled back; temporary accounts and journal changes were not retained.\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollback();
        $rolledBack = true;
    }

    echo 'TEST ERROR: ' . $e->getMessage() . PHP_EOL;
}

if (!$rolledBack) {
    exit("SAFETY ERROR: rollback could not be confirmed.\n");
}
