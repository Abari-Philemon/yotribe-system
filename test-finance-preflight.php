<?php
require __DIR__ . '/app/config/database.php';

$checks = [
    'Company' => "SELECT id, legal_name, status FROM companies WHERE id = 1",
    'Active posting accounts' => "SELECT COUNT(*) AS total FROM accounts WHERE company_id = 1 AND is_active = 1 AND allow_posting = 1",
    'Open financial year' => "SELECT COUNT(*) AS total FROM financial_years WHERE company_id = 1 AND status = 'OPEN' AND start_date <= '2026-10-09' AND end_date >= '2026-10-09'",
    'Open financial period' => "SELECT COUNT(*) AS total FROM financial_periods WHERE company_id = 1 AND status = 'OPEN' AND start_date <= '2026-10-09' AND end_date >= '2026-10-09'",
    'Journal sequence' => "SELECT id, company_id, document_type_id, financial_year, last_number, is_active FROM finance_document_sequences WHERE company_id = 1 AND document_type_id = 1 AND financial_year = 2026"
];

foreach ($checks as $label => $sql) {
    echo PHP_EOL . "=== {$label} ===" . PHP_EOL;
    $stmt = $pdo->query($sql);
    print_r($stmt->fetchAll());
}
