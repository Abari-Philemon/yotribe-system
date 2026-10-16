<?php
require __DIR__ . '/app/config/database.php';

echo "=== FARM / COMPANY MAPPING ===\n";

$stmt = $pdo->query("
    SELECT
        cf.company_id,
        cf.farm_id,
        f.name AS farm_name,
        f.location,
        f.status
    FROM company_farms cf
    INNER JOIN farms f
        ON f.id = cf.farm_id
    WHERE cf.company_id = 1
    ORDER BY cf.farm_id
");

$mappings = $stmt->fetchAll();

foreach ($mappings as $mapping) {
    echo "\n";
    echo "Company ID: " . $mapping['company_id'] . "\n";
    echo "Farm ID: " . $mapping['farm_id'] . "\n";
    echo "Farm Name: " . $mapping['farm_name'] . "\n";
    echo "Location: " . $mapping['location'] . "\n";
    echo "Status: " . $mapping['status'] . "\n";
}

echo "\n=== EXPENSES BY FARM ===\n";

$stmt = $pdo->query("
    SELECT
        e.farm_id,
        COUNT(*) AS transaction_count,
        COALESCE(SUM(e.amount), 0) AS total_amount
    FROM expenses e
    GROUP BY e.farm_id
    ORDER BY e.farm_id
");

$expenseSummary = $stmt->fetchAll();

foreach ($expenseSummary as $row) {
    echo "Farm ID " . ($row['farm_id'] ?? 'NULL')
        . " | Transactions: " . $row['transaction_count']
        . " | Total: " . $row['total_amount'] . "\n";
}

echo "\n=== SALES BY FARM AND STATUS ===\n";

$stmt = $pdo->query("
    SELECT
        s.farm_id,
        s.status,
        COUNT(*) AS transaction_count,
        COALESCE(SUM(s.total_amount), 0) AS total_amount
    FROM sales s
    GROUP BY s.farm_id, s.status
    ORDER BY s.farm_id, s.status
");

$salesSummary = $stmt->fetchAll();

foreach ($salesSummary as $row) {
    echo "Farm ID " . $row['farm_id']
        . " | Status: " . $row['status']
        . " | Transactions: " . $row['transaction_count']
        . " | Total: " . $row['total_amount'] . "\n";
}
