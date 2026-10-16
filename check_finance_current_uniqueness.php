<?php
require_once __DIR__ . '/app/config/database.php';

$checks = [
    'Duplicate current financial years' => "
        SELECT company_id, COUNT(*) AS total
        FROM financial_years
        WHERE is_current = 1
        GROUP BY company_id
        HAVING COUNT(*) > 1
    ",
    'Duplicate current periods within a financial year' => "
        SELECT company_id, financial_year_id, COUNT(*) AS total
        FROM financial_periods
        WHERE is_current = 1
        GROUP BY company_id, financial_year_id
        HAVING COUNT(*) > 1
    "
];

foreach ($checks as $label => $sql) {
    echo PHP_EOL . '--- ' . $label . ' ---' . PHP_EOL;
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo "No violations found." . PHP_EOL;
    } else {
        foreach ($rows as $row) {
            echo json_encode($row, JSON_UNESCAPED_SLASHES) . PHP_EOL;
        }
    }
}

$sql = "
    SELECT TABLE_NAME, COLUMN_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND (
        (TABLE_NAME = 'financial_years' AND COLUMN_NAME = 'current_company_id')
        OR
        (TABLE_NAME = 'financial_periods' AND COLUMN_NAME = 'current_financial_year_id')
      )
    ORDER BY TABLE_NAME, COLUMN_NAME
";

echo PHP_EOL . '--- Existing generated-column name check ---' . PHP_EOL;
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo "Proposed column names are available." . PHP_EOL;
} else {
    foreach ($rows as $row) {
        echo $row['TABLE_NAME'] . '.' . $row['COLUMN_NAME'] . PHP_EOL;
    }
}
