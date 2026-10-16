<?php
require __DIR__ . '/app/config/database.php';

echo "=== LOAD EXPENSE RECORDS ===\n";

$stmt = $pdo->query("
    SELECT
        id,
        date,
        farm_id,
        category,
        description,
        amount,
        payment_method,
        approved_by,
        created_at
    FROM expenses
    WHERE LOWER(category) = 'load'
    ORDER BY id
");

$rows = $stmt->fetchAll();

foreach ($rows as $row) {
    echo "\n--- LOAD EXPENSE ---\n";

    foreach ($row as $column => $value) {
        echo $column . ': ' . ($value ?? 'NULL') . "\n";
    }
}

echo "\n=== TOTAL LOAD EXPENSE ===\n";

$stmt = $pdo->query("
    SELECT
        COUNT(*) AS transaction_count,
        COALESCE(SUM(amount), 0) AS total_amount
    FROM expenses
    WHERE LOWER(category) = 'load'
");

$row = $stmt->fetch();

echo "Transactions: " . $row['transaction_count'] . "\n";
echo "Total: " . $row['total_amount'] . "\n";
