<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/modules/finance/Entities/JournalEntry.php';

use App\Modules\Finance\Entities\JournalEntry;

$passed = 0;
$failed = 0;

function check(string $name, bool $condition): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "PASS: {$name}" . PHP_EOL;
    } else {
        $failed++;
        echo "FAIL: {$name}" . PHP_EOL;
    }
}

function makeEntry(): JournalEntry
{
    $entry = new JournalEntry();
    $entry->company_id = 1;
    $entry->farm_id = 1;
    $entry->financial_year_id = 1;
    $entry->financial_period_id = 1;
    $entry->document_sequence_id = 1;
    $entry->document_type_id = 1;
    $entry->created_by = 1;

    return $entry;
}

$entry = makeEntry();
check('New journal is DRAFT', $entry->isDraft());
check('New journal is not POSTED', !$entry->isPosted());

$entry->status = 'POSTED';
check('POSTED status detected', $entry->isPosted());

$entry->status = 'REVERSED';
check('REVERSED status detected', $entry->isReversed());

$entry->status = 'CANCELLED';
check('CANCELLED status detected', $entry->isCancelled());

$entry = makeEntry();
try {
    $entry->validateIdentity();
    check('Positive identity IDs accepted', true);
} catch (Throwable $e) {
    check('Positive identity IDs accepted', false);
}

$entry->farm_id = 0;
try {
    $entry->validateIdentity();
    check('Zero farm ID rejected', false);
} catch (InvalidArgumentException $e) {
    check('Zero farm ID rejected', true);
}

$entry = makeEntry();
$entry->total_debit = '100.00';
$entry->total_credit = '100.00';
check('Equal two-decimal totals are balanced', $entry->isBalanced());

$entry->total_credit = '99.99';
check('Unequal totals are not balanced', !$entry->isBalanced());

$entry->total_credit = '100';
check('Equivalent decimal formats are handled correctly', $entry->isBalanced());

$entry->total_debit = '-1.00';
$entry->total_credit = '-1.00';
check('Negative totals are rejected', !$entry->isBalanced());

echo PHP_EOL . "RESULT: {$passed} passed, {$failed} failed." . PHP_EOL;
exit($failed === 0 ? 0 : 1);