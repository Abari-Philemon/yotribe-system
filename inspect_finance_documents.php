<?php
require __DIR__ . '/app/config/database.php';

echo "=== DOCUMENT TYPE TABLE STRUCTURE ===\n";
$stmt = $pdo->query("SHOW CREATE TABLE finance_document_types");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

echo "\n=== DOCUMENT SEQUENCE TABLE STRUCTURE ===\n";
$stmt = $pdo->query("SHOW CREATE TABLE finance_document_sequences");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

echo "\n=== EXISTING SEQUENCES ===\n";
$stmt = $pdo->query("
    SELECT id, company_id, document_type_id, financial_year,
           prefix, last_number, number_length, `separator`,
           reset_annually, is_active
    FROM finance_document_sequences
    WHERE company_id = 1
    ORDER BY id
");
print_r($stmt->fetchAll());
