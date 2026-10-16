<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/app/config/database.php';

use App\Core\Database;
use App\Modules\Finance\Repositories\AccountRepository;
use App\Modules\Finance\Repositories\AccountTypeRepository;
use App\Modules\Finance\Requests\AccountRequest;
use App\Modules\Finance\Services\AccountService;

$db = new Database($pdo);
$accounts = new AccountRepository($db);
$types = new AccountTypeRepository($db);

$service = new AccountService(
    $db,
    $accounts,
    $types,
    new AccountRequest()
);

$companyId = 1; // Existing test company verified earlier.

$listed = $service->listAccounts($companyId);
$allListed = $service->listAccounts($companyId, false);
$account = $service->getAccount($companyId, 1);
$missing = $service->getAccount($companyId, 999999);
$wrongCompany = $service->getAccount(999999, 1);

$checks = [
    'Active account listing returns an array' => is_array($listed),
    'All-account listing returns an array' => is_array($allListed),
    'Existing account is retrieved' =>
        $account !== null && (int) $account['id'] === 1,
    'Missing account returns null' => $missing === null,
    'Wrong company cannot retrieve account' => $wrongCompany === null,
    'Existing account remains unchanged' =>
        $account !== null
        && $account['account_code'] === '1001'
        && $account['account_name'] === 'Afft Test',
];

foreach ($checks as $label => $passed) {
    printf("%s: %s\n", $passed ? 'PASS' : 'FAIL', $label);
}

printf("Active listing count: %d\n", count($listed));
printf("All listing count: %d\n", count($allListed));

if (in_array(false, $checks, true)) {
    exit(1);
}

echo "All account retrieval checks passed.\n";
