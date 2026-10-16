<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/app/config/database.php';

$db = new \App\Core\Database($pdo);
$accounts = new \App\Modules\Finance\Repositories\AccountRepository($db);

$account = $accounts->findByCompanyAndId(999999, 12);

if ($account === null) {
    echo "PASS: Account lookup rejects an account outside the requested company scope.\n";
} else {
    echo "FAIL: Account lookup returned an account for the wrong company.\n";
    exit(1);
}
