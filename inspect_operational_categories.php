<?php
require __DIR__ . '/app/config/database.php';

echo "=== EXPENSE CATEGORIES IN USE ===\n";

$stmt = $pdo->query("
    SELECT
        category,
        COUNT(*) AS transaction_count,
        COALESCE(SUM(amount), 0) AS total_amount
    FROM expenses
    GROUP BY category
    ORDER BY category
");

$rows = $stmt->fetchAll();

foreach ($rows as $row) {
    echo "Category: " . ($row['category'] ?? 'NULL')
        . " | Transactions: " . $row['transaction_count']
        . " | Total: " . $row['total_amount'] . "\n";
}

echo "\n=== SALES TYPES IN USE ===\n";

$stmt = $pdo->query("
    SELECT
        sale_type,
        status,
        COUNT(*) AS transaction_count,
        COALESCE(SUM(total_amount), 0) AS total_amount
    FROM sales
    GROUP BY sale_type, status
    ORDER BY sale_type, status
");

$rows = $stmt->fetchAll();

foreach ($rows as $row) {
    echo "Sale Type: " . ($row['sale_type'] ?? 'NULL')
        . " | Status: " . ($row['status'] ?? 'NULL')
        . " | Transactions: " . $row['transaction_count']
        . " | Total: " . $row['total_amount'] . "\n";
}
