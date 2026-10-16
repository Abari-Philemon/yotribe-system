<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config/database.php';

$checks = [
    'Periods with mismatched company and financial year' => '
        SELECT
            fp.id AS period_id,
            fp.company_id AS period_company_id,
            fp.financial_year_id,
            fy.company_id AS year_company_id
        FROM financial_periods fp
        INNER JOIN financial_years fy
            ON fy.id = fp.financial_year_id
        WHERE fp.company_id <> fy.company_id
    ',
    'Financial years with multiple current flags' => '
        SELECT company_id, COUNT(*) AS current_count
        FROM financial_years
        WHERE is_current = 1
        GROUP BY company_id
        HAVING COUNT(*) > 1
    ',
    'Financial years with multiple current periods' => '
        SELECT company_id, financial_year_id, COUNT(*) AS current_count
        FROM financial_periods
        WHERE is_current = 1
        GROUP BY company_id, financial_year_id
        HAVING COUNT(*) > 1
    ',
];

foreach ($checks as $label => $sql) {
    echo "\n--- {$label} ---\n";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    if ($rows === []) {
        echo "No violations found.\n";
        continue;
    }

    foreach ($rows as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . PHP_EOL;
    }
}
