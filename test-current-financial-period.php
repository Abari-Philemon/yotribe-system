<?php
require __DIR__ . '/app/config/database.php';

echo "=== Financial year covering 2026-10-09 ===" . PHP_EOL;
$sql = "SELECT id, year_code, start_date, end_date, status, is_current
        FROM financial_years
        WHERE company_id = 1
          AND start_date <= '2026-10-09'
          AND end_date >= '2026-10-09'";
print_r($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));

echo PHP_EOL . "=== Financial period covering 2026-10-09 ===" . PHP_EOL;
$sql = "SELECT id, financial_year_id, period_no, period_code,
               start_date, end_date, status, is_current
        FROM financial_periods
        WHERE company_id = 1
          AND start_date <= '2026-10-09'
          AND end_date >= '2026-10-09'";
print_r($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
