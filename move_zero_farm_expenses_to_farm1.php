<?php
require __DIR__ . '/app/config/database.php';

$stmt = $pdo->prepare("
    SELECT id, farm_id, category, description, amount
    FROM expenses
    WHERE farm_id = 0
    ORDER BY id
");

$stmt->execute();
$rows = $stmt->fetchAll();

echo "=== EXPENSES CURRENTLY WITH FARM ID 0 ===\n";

if (!$rows) {
    echo "No records found.\n";
    exit;
}

foreach ($rows as $row) {
    echo "ID: {$row['id']} | Category: {$row['category']} | Amount: {$row['amount']}\n";
}

echo "\nChanging farm_id from 0 to 1...\n";

$stmt = $pdo->prepare("
    UPDATE expenses
    SET farm_id = 1
    WHERE farm_id = 0
");

$stmt->execute();

echo "Updated rows: " . $stmt->rowCount() . "\n";

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM expenses
    WHERE farm_id = 0
");

$remaining = $stmt->fetchColumn();

echo "Remaining expenses with farm_id = 0: {$remaining}\n";

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM expenses
    WHERE farm_id = 1
");

$farmOneTotal = $stmt->fetchColumn();

echo "Expenses now assigned to Farm 1: {$farmOneTotal}\n";

if ((int)$remaining === 0) {
    echo "RESULT: FARM ID 0 EXPENSES MOVED TO FARM 1 SUCCESSFULLY\n";
} else {
    echo "RESULT: SOME FARM ID 0 EXPENSES REMAIN\n";
}
