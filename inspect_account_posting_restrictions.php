<?php
declare(strict_types=1);

$file = __DIR__ . '/app/modules/finance/Services/AccountService.php';
$source = file_get_contents($file);

if ($source === false) {
    exit("Could not read AccountService.php\n");
}

$start = strpos($source, 'public function updateAccount(');

if ($start === false) {
    exit("updateAccount() not found\n");
}

$next = strpos($source, "\n    public function ", $start + 1);
$method = $next === false
    ? substr($source, $start)
    : substr($source, $start, $next - $start);

foreach ([
    'Child-account restriction' => 'child',
    'Journal-entry reference checks' => 'journal_entry_lines',
    'Ledger-balance reference checks' => 'ledger_balances',
    'Cash-book reference checks' => 'cash_book',
    'Bank-account reference checks' => 'bank_accounts',
    'Income/expense category checks' => 'income_categories',
    'Posting-rule reference checks' => 'finance_posting_rules',
    'Default cash-account check' => 'default_cash_account_id',
] as $label => $needle) {
    echo (stripos($method, $needle) !== false ? '[FOUND] ' : '[MISSING] ')
        . $label . "\n";
}