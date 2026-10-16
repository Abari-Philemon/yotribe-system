<?php
require __DIR__ . '/vendor/autoload.php';

use App\Modules\Finance\Requests\JournalEntryRequest;
use InvalidArgumentException;

$request = new JournalEntryRequest();

$input = [
    'farm_id' => 1,
    'journal_date' => '2026-10-09',
    'transaction_code' => 'TEST',
    'source_module' => 'finance_test',
    'narration' => 'Negative test - intentionally unbalanced journal',
    'lines' => [
        [
            'account_id' => 12,
            'entry_type' => 'DEBIT',
            'amount' => '10.00',
        ],
        [
            'account_id' => 20,
            'entry_type' => 'CREDIT',
            'amount' => '9.00',
        ],
    ],
];

try {
    $request->validate($input);
    echo "FAIL: Unbalanced journal was accepted.\n";
    exit(1);
} catch (InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'Journal is not balanced')) {
        echo "PASS: Unbalanced journal rejected.\n";
        echo "Validation message: " . $e->getMessage() . "\n";
    } else {
        echo "FAIL: Unexpected validation error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
