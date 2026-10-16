<?php
require __DIR__ . '/app/config/database.php';

echo "=== FINANCE-RELATED TABLES ===\n";
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    if (stripos($table, 'account') !== false ||
        stripos($table, 'document_type') !== false ||
        stripos($table, 'document_sequence') !== false ||
        stripos($table, 'posting_rule') !== false) {
        echo $table . "\n";
    }
}

echo "\n=== DOCUMENT TYPES FOR COMPANY 1 ===\n";
$stmt = $pdo->query("
    SELECT id, company_id, code, name, prefix, is_active
    FROM finance_document_types
    WHERE company_id = 1
    ORDER BY id
");
print_r($stmt->fetchAll());
