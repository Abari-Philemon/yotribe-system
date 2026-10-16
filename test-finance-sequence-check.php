<?php
require __DIR__ . '/app/config/database.php';

$sql = "
    SELECT id, company_id, document_type_id, financial_year,
           last_number, number_length, `separator`, is_active
    FROM finance_document_sequences
    WHERE company_id = 1
      AND document_type_id = 1
      AND financial_year = 2026
";

$rows = $pdo->query($sql)->fetchAll();

echo "Journal sequence for company 1, year 2026:" . PHP_EOL;
print_r($rows);
