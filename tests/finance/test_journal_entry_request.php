<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Modules\Finance\Requests\JournalEntryRequest;

$request = new JournalEntryRequest();
$passed = 0;
$failed = 0;

function checkJournalRequest(string $label, bool $condition): void
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

function journalRequestThrows(callable $callback): bool
{
    try {
        $callback();
        return false;
    } catch (InvalidArgumentException $exception) {
        return true;
    }
}

$validInput = [
    'farm_id' => 1,
    'journal_date' => '2026-10-04',
    'transaction_code' => 'GENERAL_JOURNAL',
    'source_module' => 'FINANCE',
    'narration' => 'Test balanced journal',
    'lines' => [
        [
            'account_id' => 1,
            'entry_type' => 'DEBIT',
            'amount' => '100.00',
            'description' => 'Debit test',
        ],
        [
            'account_id' => 2,
            'entry_type' => 'CREDIT',
            'amount' => '100',
            'description' => 'Credit test',
        ],
    ],
];

try {
    $result = $request->validate($validInput);

    checkJournalRequest(
        'Accepts a valid balanced journal',
        $result['total_debit'] === '100.00'
        && $result['total_credit'] === '100.00'
        && count($result['lines']) === 2
    );

    checkJournalRequest(
        'Normalizes entry types',
        $request->validate(array_replace_recursive(
            $validInput,
            ['lines' => [
                ['entry_type' => ' debit '],
                ['entry_type' => ' credit '],
            ]]
        ))['lines'][0]['entry_type'] === 'DEBIT'
    );

    checkJournalRequest(
        'Trims header and line descriptions',
        $request->validate(array_replace_recursive(
            $validInput,
            [
                'narration' => '  Test journal  ',
                'lines' => [
                    ['description' => '  Debit line  '],
                    ['description' => '  Credit line  '],
                ],
            ]
        ))['narration'] === 'Test journal'
    );

    checkJournalRequest(
        'Normalizes optional empty strings to null',
        $request->validate(array_merge(
            $validInput,
            ['reference_no' => '  ', 'source_table' => '']
        ))['reference_no'] === null
    );

    checkJournalRequest(
        'Rejects an unsupported header field',
        journalRequestThrows(
            fn() => $request->validate(
                array_merge($validInput, ['company_id' => 1])
            )
        )
    );

    checkJournalRequest(
        'Rejects a missing narration',
        journalRequestThrows(
            function () use ($request, $validInput): void {
                unset($validInput['narration']);
                $request->validate($validInput);
            }
        )
    );

    checkJournalRequest(
        'Rejects an invalid calendar date',
        journalRequestThrows(
            fn() => $request->validate(array_merge(
                $validInput,
                ['journal_date' => '2026-02-30']
            ))
        )
    );

    checkJournalRequest(
        'Rejects a zero farm ID',
        journalRequestThrows(
            fn() => $request->validate(array_merge(
                $validInput,
                ['farm_id' => 0]
            ))
        )
    );

    checkJournalRequest(
        'Rejects a journal with fewer than two lines',
        journalRequestThrows(
            fn() => $request->validate(array_merge(
                $validInput,
                ['lines' => [$validInput['lines'][0]]]
            ))
        )
    );

    checkJournalRequest(
        'Rejects unbalanced totals',
        journalRequestThrows(
            fn() => $request->validate(array_replace_recursive(
                $validInput,
                ['lines' => [
                    ['amount' => '100.00'],
                    ['amount' => '99.00'],
                ]]
            ))
        )
    );

    checkJournalRequest(
        'Rejects an invalid entry type',
        journalRequestThrows(
            fn() => $request->validate(array_replace_recursive(
                $validInput,
                ['lines' => [
                    ['entry_type' => 'SIDEWAYS'],
                ]]
            ))
        )
    );

    checkJournalRequest(
        'Rejects a zero account ID',
        journalRequestThrows(
            fn() => $request->validate(array_replace_recursive(
                $validInput,
                ['lines' => [
                    ['account_id' => 0],
                ]]
            ))
        )
    );

    checkJournalRequest(
        'Rejects a zero amount',
        journalRequestThrows(
            fn() => $request->validate(array_replace_recursive(
                $validInput,
                ['lines' => [
                    ['amount' => '0.00'],
                ]]
            ))
        )
    );

    checkJournalRequest(
        'Rejects amounts with more than two decimal places',
        journalRequestThrows(
            fn() => $request->validate(array_replace_recursive(
                $validInput,
                ['lines' => [
                    ['amount' => '10.001'],
                ]]
            ))
        )
    );

    checkJournalRequest(
        'Rejects unsupported journal-line fields',
        journalRequestThrows(
            fn() => $request->validate(array_replace_recursive(
                $validInput,
                ['lines' => [
                    ['company_id' => 1],
                ]]
            ))
        )
    );

    checkJournalRequest(
        'Rejects a non-list lines structure',
        journalRequestThrows(
            fn() => $request->validate(array_merge(
                $validInput,
                ['lines' => ['debit' => $validInput['lines'][0]]]
            ))
        )
    );

    checkJournalRequest(
        'Rejects a missing account ID on a line',
        journalRequestThrows(
            function () use ($request, $validInput): void {
                unset($validInput['lines'][0]['account_id']);
                $request->validate($validInput);
            }
        )
    );

    checkJournalRequest(
        'Rejects a negative source ID',
        journalRequestThrows(
            fn() => $request->validate(array_merge(
                $validInput,
                ['source_id' => -1]
            ))
        )
    );
} catch (Throwable $exception) {
    $failed++;
    echo "[FAIL] Unexpected test error: "
        . $exception->getMessage() . "\n";
}

echo "\nResults: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);