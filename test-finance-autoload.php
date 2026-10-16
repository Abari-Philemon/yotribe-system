<?php
require __DIR__ . '/app/config/database.php';
require __DIR__ . '/vendor/autoload.php';

$classes = [
    \App\Modules\Finance\Services\CompanyContext::class,
    \App\Modules\Finance\Services\FinanceSettingsService::class,
    \App\Modules\Finance\Services\AccountService::class,
    \App\Modules\Finance\Services\JournalEntryService::class,
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "PASS: {$class}" . PHP_EOL;
    } else {
        echo "FAIL: {$class}" . PHP_EOL;
        exit(1);
    }
}

echo "Finance autoload verification completed." . PHP_EOL;
