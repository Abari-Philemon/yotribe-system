<?php
require __DIR__ . '/app/config/database.php';

echo "=== EXPENSE REFERENCES FROM CASH_LEDGER ===\n";

$stmt = $pdo->query("
    SELECT
        cl.id AS cash_ledger_id,
        cl.date,
        cl.reference_id,
        cl.amount AS ledger_amount,
        cl.balance_after,
        cl.farm_id,
        e.*
    FROM cash_ledger cl
    LEFT JOIN expenses e
        ON e.id = cl.reference_id
    WHERE cl.source = 'expense'
    ORDER BY cl.id
");

$expenses = $stmt->fetchAll();

foreach ($expenses as $row) {
    echo "\n--- CASH LEDGER ID " . $row['cash_ledger_id'] . " ---\n";

    foreach ($row as $column => $value) {
        if (!is_numeric($column)) {
            echo $column . ': ' . ($value ?? 'NULL') . "\n";
        }
    }
}

echo "\n=== SALE REFERENCES FROM CASH_LEDGER ===\n";

$stmt = $pdo->query("
    SELECT
        cl.id AS cash_ledger_id,
        cl.date,
        cl.reference_id,
        cl.amount AS ledger_amount,
        cl.balance_after,
        cl.farm_id,
        s.*
    FROM cash_ledger cl
    LEFT JOIN sales s
        ON s.id = cl.reference_id
    WHERE cl.source = 'sale'
    ORDER BY cl.id
");

$sales = $stmt->fetchAll();

foreach ($sales as $row) {
    echo "\n--- CASH LEDGER ID " . $row['cash_ledger_id'] . " ---\n";

    foreach ($row as $column => $value) {
        if (!is_numeric($column)) {
            echo $column . ': ' . ($value ?? 'NULL') . "\n";
        }
    }
}

echo "\n=== REFERENCE SUMMARY ===\n";
echo "Expense references checked: " . count($expenses) . "\n";
echo "Sale references checked: " . count($sales) . "\n";
