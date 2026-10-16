<?php
declare(strict_types=1);

$files = [
    'app/modules/finance/Services/AccountService.php',
    'test_account_update.php',
];

foreach ($files as $file) {
    echo "\n--- {$file} ---\n";

    if (!is_file(__DIR__ . '/' . $file)) {
        echo "FILE NOT FOUND\n";
        continue;
    }

    $source = file_get_contents(__DIR__ . '/' . $file);

    foreach ([
        'updateAccount',
        'An account with child accounts',
        'journal_entry_lines',
        'ledger_balances',
        'beginTransaction',
        'AccountService',
    ] as $needle) {
        $position = stripos($source, $needle);

        if ($position !== false) {
            echo "\n[$needle]\n";
            echo substr($source, max(0, $position - 100), 420) . "\n";
        }
    }
}