<?php
require_once __DIR__ . '/app/config/database.php';

$sql = "
    SELECT TABLE_NAME, ENGINE
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME IN ('companies', 'financial_years', 'financial_periods')
    ORDER BY TABLE_NAME
";
foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['TABLE_NAME'] . ' | ENGINE=' . $row['ENGINE'] . PHP_EOL;
}

$sql = "
    SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND (
        (TABLE_NAME = 'companies' AND COLUMN_NAME = 'id')
        OR (TABLE_NAME = 'financial_years' AND COLUMN_NAME = 'company_id')
        OR (TABLE_NAME = 'financial_years' AND COLUMN_NAME = 'id')
    )
    ORDER BY TABLE_NAME, ORDINAL_POSITION
";
foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['TABLE_NAME'] . '.' . $row['COLUMN_NAME']
        . ' | ' . $row['COLUMN_TYPE']
        . ' | NULL=' . $row['IS_NULLABLE'] . PHP_EOL;
}
