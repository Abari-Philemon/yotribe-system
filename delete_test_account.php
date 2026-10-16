<?php
require __DIR__ . '/app/config/database.php';

$accountId = 1;

$stmt = $pdo->prepare("
    SELECT id, account_code, system_key, account_name, is_system, is_active
    FROM accounts
    WHERE id = ?
");
$stmt->execute([$accountId]);
$account = $stmt->fetch();

if (!$account) {
    echo "ACCOUNT NOT FOUND\n";
    exit(1);
}

echo "=== ACCOUNT TO DELETE ===\n";
echo "ID: " . $account['id'] . "\n";
echo "Code: " . $account['account_code'] . "\n";
echo "System Key: " . $account['system_key'] . "\n";
echo "Name: " . $account['account_name'] . "\n";
echo "System Account: " . $account['is_system'] . "\n";
echo "Active: " . $account['is_active'] . "\n";

if ((int)$account['is_system'] === 1) {
    echo "\nABORTED: This is a system account.\n";
    exit(1);
}

echo "\nDeleting account ID 1...\n";

$stmt = $pdo->prepare("DELETE FROM accounts WHERE id = ?");
$stmt->execute([$accountId]);

echo "Deleted rows: " . $stmt->rowCount() . "\n";

$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM accounts WHERE id = ?");
$stmt->execute([$accountId]);
$remaining = $stmt->fetchColumn();

echo "Remaining account ID 1 rows: " . $remaining . "\n";

if ((int)$remaining === 0) {
    echo "RESULT: TEST ACCOUNT DELETED SUCCESSFULLY\n";
} else {
    echo "RESULT: ACCOUNT STILL EXISTS\n";
}
