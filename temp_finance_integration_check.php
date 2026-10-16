<?php
declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

$classes = [
    App\Core\Database::class,
    App\Modules\Finance\Repositories\FinanceSettingsRepository::class,
    App\Modules\Finance\Services\CompanyContext::class,
];

foreach ($classes as $class) {
    printf("%s: %s\n", $class, class_exists($class) ? 'PASS' : 'FAIL');
}
