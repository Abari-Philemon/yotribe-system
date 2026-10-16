<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Modules\Finance\Requests\AccountRequest;

$request = new AccountRequest();
$checks = [];

$checks['Account name accepted'] =
    $request->validateUpdate(['account_name' => 'Updated Test']) ===
    ['account_name' => 'Updated Test'];

$checks['Account code trimmed'] =
    $request->validateUpdate(['account_code' => ' 2001 ']) ===
    ['account_code' => '2001'];

$checks['Boolean normalized'] =
    $request->validateUpdate(['allow_posting' => '0']) ===
    ['allow_posting' => false];

$checks['Null short name accepted'] =
    $request->validateUpdate(['short_name' => null]) ===
    ['short_name' => null];

$rejected = [
    'Empty update' => [],
    'Opening balance' => ['opening_balance' => '100.00'],
    'Company ID' => ['company_id' => 1],
    'Current balance' => ['current_balance' => '100.00'],
    'System flag' => ['is_system' => false],
    'System key' => ['system_key' => 'NEW_KEY'],
    'Account type' => ['account_type_id' => 1],
    'Account class' => ['account_class_id' => 1],
    'Parent account' => ['parent_account_id' => null],
    'Normal balance' => ['normal_balance' => 'credit'],
    'Active status' => ['is_active' => false],
];

foreach ($rejected as $label => $input) {
    try {
        $request->validateUpdate($input);
        $checks[$label . ' rejected'] = false;
    } catch (InvalidArgumentException $exception) {
        $checks[$label . ' rejected'] = true;
    }
}

foreach ($checks as $label => $passed) {
    printf("%s: %s\n", $passed ? 'PASS' : 'FAIL', $label);
}

if (in_array(false, $checks, true)) {
    exit(1);
}

echo "All account-update validation checks passed.\n";