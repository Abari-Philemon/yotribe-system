<?php
require __DIR__ . '/app/config/database.php';

echo "=== EXPENSES WITH FARM ID 0 ===\n";

$stmt = $pdo->query("
    SELECT *
    FROM expenses
    WHERE farm_id = 0
    ORDER BY id
");

$rows = $stmt->fetchAll();

foreach ($rows as $row) {
    echo "\n--- EXPENSE ---\n";

    foreach ($row as $column => $value) {
        if (!is_numeric($column)) {
            echo $column . ': ' . ($value ?? 'NULL') . "\n";
        }
    }
}

echo "\n=== SALES WITH EMPTY/NULL STATUS ===\n";

$stmt = $pdo->query("
    SELECT *
    FROM sales
    WHERE status IS NULL
       OR status = ''
    ORDER BY id
");

$rows = $stmt->fetchAll();

foreach ($rows as $row) {
    echo "\n--- SALE ---\n";

    foreach ($row as $column => $value) {
        if (!is_numeric($column)) {
            echo $column . ': ' . ($value ?? 'NULL') . "\n";
        }
    }
}

echo "\n=== SALES WITH REFUNDED STATUS ===\n";

$stmt = $pdo->query("
    SELECT
        id,
        farm_id,
        sale_no,
        sale_date,
        sale_type,
        status,
        subtotal,
        discount,
        tax,
        total_amount,
        amount_paid,
        balance,
        remarks
    FROM sales
    WHERE status = 'refunded'
    ORDER BY id
");

$rows = $stmt->fetchAll();

foreach ($rows as $row) {
    echo "\n--- REFUNDED SALE ---\n";

    foreach ($row as $column => $value) {
        echo $column . ': ' . ($value ?? 'NULL') . "\n";
    }
}
