<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/config/database.php';

$file = __DIR__ . '/app/modules/finance/Services/AccountService.php';
$source = file_get_contents($file);

$start = strpos($source, 'public function updateAccount(');
if ($start === false) {
    exit("updateAccount() not found\n");
}

$next = strpos($source, "\n    public function ", $start + 1);
$method = $next === false
    ? substr($source, $start)
    : substr($source, $start, $next - $start);

$position = stripos($method, 'child');
if ($position === false) {
    exit("Child-account check not found\n");
}

echo "--- Child-account check in service ---\n";
echo substr($method, max(0, $position - 500), 1800) . "\n";

echo "\n--- Accounts table parent-related columns ---\n";
$stmt = $pdo->query("SHOW COLUMNS FROM accounts");

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
    if (
        stripos($column['Field'], 'parent') !== false ||
        stripos($column['Field'], 'company') !== false ||
        stripos($column['Field'], 'posting') !== false ||
        stripos($column['Field'], 'active') !== false
    ) {
        echo $column['Field'] . ' | ' . $column['Type'] . "\n";
    }
}